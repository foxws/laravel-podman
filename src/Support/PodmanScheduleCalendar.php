<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Cron\CronExpression;
use DateTimeZone;
use Foxws\Podman\Enums\TimerCalendar;
use Foxws\Podman\Enums\Weekday;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;

/**
 * Turns the app's task schedule into systemd "OnCalendar=" lines, so the
 * scheduler timer only starts "schedule:run" in the minutes a task is due,
 * instead of every minute. "schedule:run" runs the tasks due in the
 * minute it starts, so firing in exactly those minutes runs every task.
 *
 * Each due minute also gets a wake-up 30 seconds before it. The scheduler
 * then starts the database and cache ahead of time, and waits for the due
 * minute before it runs "schedule:run", so a slow start can't push it
 * into the next minute and skip the tasks.
 *
 * Falls back to every minute when the schedule is empty, has sub-minute
 * tasks, or uses cron syntax systemd can't express (such as "L", "W" or
 * "#").
 *
 * @phpstan-type CalendarSpec array{weekdays: list<int>|null, months: list<int>|null, days: list<int>|null, lastDayOfMonth: bool, hours: list<int>|null, minutes: list<int>|null, timezone: string}
 */
class PodmanScheduleCalendar
{
    /**
     * How many seconds before a due minute the scheduler wakes up.
     */
    public const int WakeUpLeadSeconds = 30;

    /**
     * @var array<string, int>
     */
    protected const array MonthNames = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    public function __construct(
        protected ?Schedule $schedule = null,
    ) {}

    /**
     * The "OnCalendar=" lines for the scheduler timer, one per line: the
     * due minutes, then their wake-ups.
     */
    public function render(): string
    {
        return implode("\n", array_map(
            fn (string $calendar): string => "OnCalendar={$calendar}",
            [...$this->calendars(), ...$this->wakeUpCalendars()],
        ));
    }

    /**
     * The minutes a task is due.
     *
     * @return list<string>
     */
    public function calendars(): array
    {
        $specs = $this->specs();

        if ($specs === null) {
            return [TimerCalendar::EveryMinute->value];
        }

        return $this->formatSpecs($specs, 0);
    }

    /**
     * The wake-ups, 30 seconds before each due minute. A wake-up that's
     * missing (such as before a task due every minute of an hour) only
     * means the scheduler starts at the due minute, as without wake-ups.
     *
     * @return list<string>
     */
    public function wakeUpCalendars(): array
    {
        $specs = $this->specs();

        if ($specs === null) {
            return [];
        }

        $wakeUps = [];

        foreach ($specs as $spec) {
            array_push($wakeUps, ...$this->wakeUpSpecs($spec));
        }

        return $this->formatSpecs($wakeUps, 60 - self::WakeUpLeadSeconds);
    }

    /**
     * Converts a cron expression to systemd calendar events, or null when
     * systemd can't express it.
     *
     * @return list<string>|null
     */
    public function fromCron(string $expression, string $timezone = 'UTC'): ?array
    {
        $specs = $this->cronSpecs($expression, $timezone);

        if ($specs === null) {
            return null;
        }

        foreach ($specs as $spec) {
            if ($this->isEveryMinute($spec)) {
                return [TimerCalendar::EveryMinute->value];
            }
        }

        return $this->formatSpecs($specs, 0);
    }

    /**
     * The schedule's calendar specs, or null when the timer has to fire
     * every minute.
     *
     * @return list<CalendarSpec>|null
     */
    protected function specs(): ?array
    {
        $events = $this->schedule()->events();

        if ($events === []) {
            return null;
        }

        $specs = [];

        foreach ($events as $event) {
            $eventSpecs = $this->eventSpecs($event);

            if ($eventSpecs === null) {
                return null;
            }

            array_push($specs, ...$eventSpecs);
        }

        foreach ($specs as $spec) {
            if ($this->isEveryMinute($spec)) {
                return null;
            }
        }

        return $specs;
    }

    /**
     * @return list<CalendarSpec>|null
     */
    protected function eventSpecs(Event $event): ?array
    {
        if ($event->isRepeatable()) {
            return null;
        }

        $timezone = $this->timezone($event);

        if ($timezone === null) {
            return null;
        }

        return $this->cronSpecs($event->expression, $timezone);
    }

    /**
     * Cron runs a task when either the day of the month or the weekday
     * matches if both are set, while systemd needs both to match, so such
     * an expression becomes two specs.
     *
     * @return list<CalendarSpec>|null
     */
    protected function cronSpecs(string $expression, string $timezone): ?array
    {
        try {
            $parts = (new CronExpression($expression))->getParts();
        } catch (InvalidArgumentException) {
            return null;
        }

        if (count($parts) !== 5) {
            return null;
        }

        [$minute, $hour, $day, $month, $weekday] = $parts;

        $minutes = $this->expand($minute, 0, 59);
        $hours = $this->expand($hour, 0, 23);
        $days = $this->expand($day, 1, 31);
        $months = $this->expand($month, 1, 12, self::MonthNames);
        $weekdays = $this->expand($weekday, 0, 7, Weekday::cronNames());

        if (in_array(false, [$minutes, $hours, $days, $months, $weekdays], true)) {
            return null;
        }

        $spec = [
            'weekdays' => $this->normalizeWeekdays($weekdays),
            'months' => $months,
            'days' => $days,
            'lastDayOfMonth' => false,
            'hours' => $hours,
            'minutes' => $minutes,
            'timezone' => $timezone,
        ];

        if ($spec['days'] !== null && $spec['weekdays'] !== null) {
            return [
                [...$spec, 'weekdays' => null],
                [...$spec, 'days' => null],
            ];
        }

        return [$spec];
    }

    /**
     * The specs for the minute before the spec's minutes. Going back from
     * the first minute of a day also goes back a weekday or a day of the
     * month, and from the first day of a month to its last day.
     *
     * @param  CalendarSpec  $spec
     * @return list<CalendarSpec>
     */
    protected function wakeUpSpecs(array $spec): array
    {
        if ($spec['minutes'] === null || $spec['lastDayOfMonth']) {
            return [];
        }

        $wakeUps = [];
        $minutes = $this->previous($spec['minutes']);

        if ($minutes !== []) {
            $wakeUps[] = [...$spec, 'minutes' => $minutes];
        }

        if (! in_array(0, $spec['minutes'], true)) {
            return $wakeUps;
        }

        if ($spec['hours'] === null) {
            $wakeUps[] = [...$spec, 'minutes' => [59]];

            return $wakeUps;
        }

        $hours = $this->previous($spec['hours']);

        if ($hours !== []) {
            $wakeUps[] = [...$spec, 'hours' => $hours, 'minutes' => [59]];
        }

        if (! in_array(0, $spec['hours'], true)) {
            return $wakeUps;
        }

        $dayBefore = [...$spec, 'hours' => [23], 'minutes' => [59]];

        if ($spec['weekdays'] !== null) {
            $weekdays = array_map(fn (int $day): int => ($day + 6) % 7, $spec['weekdays']);
            sort($weekdays);

            $wakeUps[] = [...$dayBefore, 'weekdays' => $weekdays];

            return $wakeUps;
        }

        if ($spec['days'] === null) {
            $wakeUps[] = $dayBefore;

            return $wakeUps;
        }

        $days = $this->previous($spec['days'], 1);

        if ($days !== []) {
            $wakeUps[] = [...$dayBefore, 'days' => $days];
        }

        if (in_array(1, $spec['days'], true)) {
            $months = $spec['months'] === null ? null : array_map(fn (int $month): int => ($month + 10) % 12 + 1, $spec['months']);

            if ($months !== null) {
                sort($months);
            }

            $wakeUps[] = [...$dayBefore, 'days' => null, 'lastDayOfMonth' => true, 'months' => $months];
        }

        return $wakeUps;
    }

    /**
     * The values one lower, leaving out the range's first value, which
     * goes back into the next larger unit (such as minute 0 to the hour
     * before).
     *
     * @param  list<int>  $values
     * @return list<int>
     */
    protected function previous(array $values, int $min = 0): array
    {
        $lower = [];

        foreach ($values as $value) {
            if ($value > $min) {
                $lower[] = $value - 1;
            }
        }

        return $lower;
    }

    /**
     * @param  CalendarSpec  $spec
     */
    protected function isEveryMinute(array $spec): bool
    {
        return $spec['minutes'] === null && $spec['hours'] === null && $spec['days'] === null
            && $spec['months'] === null && $spec['weekdays'] === null && ! $spec['lastDayOfMonth'];
    }

    /**
     * @param  list<CalendarSpec>  $specs
     * @return list<string>
     */
    protected function formatSpecs(array $specs, int $second): array
    {
        return array_values(array_unique(array_map(
            fn (array $spec): string => $this->formatSpec($spec, $second),
            $specs,
        )));
    }

    /**
     * @param  CalendarSpec  $spec
     */
    protected function formatSpec(array $spec, int $second): string
    {
        $weekdays = $spec['weekdays'] === null ? '' : "{$this->formatWeekdays($spec['weekdays'])} ";
        $months = $this->format($spec['months'], 1, 12);
        $date = $spec['lastDayOfMonth'] ? "*-{$months}~01" : "*-{$months}-{$this->format($spec['days'], 1, 31)}";
        $time = sprintf('%s:%s:%02d', $this->format($spec['hours'], 0, 23), $this->format($spec['minutes'], 0, 59), $second);

        return "{$weekdays}{$date} {$time} {$spec['timezone']}";
    }

    /**
     * Expands a cron field to its values, null for "any value", or false
     * when it uses syntax systemd can't express.
     *
     * @param  array<string, int>  $names
     * @return list<int>|null|false
     */
    protected function expand(string $field, int $min, int $max, array $names = []): array|null|false
    {
        if ($field === '*' || $field === '?') {
            return null;
        }

        $values = [];

        foreach (explode(',', strtoupper($field)) as $part) {
            if (preg_match('/^(\*|[0-9A-Z]+)(?:-([0-9A-Z]+))?(?:\/([0-9]+))?$/', $part, $matches) !== 1) {
                return false;
            }

            $step = isset($matches[3]) ? (int) $matches[3] : 1;
            $start = $matches[1] === '*' ? $min : $this->value($matches[1], $names);
            $end = match (true) {
                $matches[1] === '*' => $max,
                ($matches[2] ?? '') !== '' => $this->value($matches[2], $names),
                isset($matches[3]) => $max,
                default => $start,
            };

            if ($start === null || $end === null || $step < 1 || $start < $min || $end > $max || $start > $end) {
                return false;
            }

            array_push($values, ...range($start, $end, $step));
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  array<string, int>  $names
     */
    protected function value(string $value, array $names): ?int
    {
        if (ctype_digit($value)) {
            return (int) $value;
        }

        return $names[$value] ?? null;
    }

    /**
     * Folds cron's two Sundays (0 and 7) into one, and treats every weekday
     * as "any day".
     *
     * @param  list<int>|null  $weekdays
     * @return list<int>|null
     */
    protected function normalizeWeekdays(?array $weekdays): ?array
    {
        if ($weekdays === null) {
            return null;
        }

        $weekdays = array_values(array_unique(array_map(fn (int $day): int => $day % 7, $weekdays)));
        sort($weekdays);

        return count($weekdays) === 7 ? null : $weekdays;
    }

    /**
     * Formats values as a systemd calendar component: "*" for every value,
     * "a/b" for a repetition up to the end of the range, "a..b" for a range,
     * or a list.
     *
     * @param  list<int>|null  $values
     */
    protected function format(?array $values, int $min, int $max): string
    {
        if ($values === null || $values === [] || $values === range($min, $max)) {
            return '*';
        }

        $count = count($values);
        $last = $values[array_key_last($values)];
        $pad = fn (int $value): string => str_pad((string) $value, 2, '0', STR_PAD_LEFT);

        if ($count === 1) {
            return $pad($values[0]);
        }

        $step = $values[1] - $values[0];
        $isProgression = $values === range($values[0], $last, $step);

        if ($isProgression && $step === 1 && $count > 2) {
            return "{$pad($values[0])}..{$pad($last)}";
        }

        if ($isProgression && $step > 1 && $count > 2 && $max < $last + $step) {
            return "{$pad($values[0])}/{$step}";
        }

        return implode(',', array_map($pad, $values));
    }

    /**
     * @param  list<int>  $weekdays
     */
    protected function formatWeekdays(array $weekdays): string
    {
        return implode(',', array_map(fn (int $day): string => Weekday::from($day)->systemdName(), $weekdays));
    }

    /**
     * The event's timezone name, or null when systemd won't know it, such
     * as a UTC offset.
     */
    protected function timezone(Event $event): ?string
    {
        $timezone = $event->timezone ?? Config::get('app.timezone') ?? 'UTC';
        $name = $timezone instanceof DateTimeZone ? $timezone->getName() : (string) $timezone;

        return in_array($name, DateTimeZone::listIdentifiers(), true) ? $name : null;
    }

    protected function schedule(): Schedule
    {
        return $this->schedule ?? app(Schedule::class);
    }
}
