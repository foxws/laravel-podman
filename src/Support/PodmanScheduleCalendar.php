<?php

declare(strict_types=1);

namespace Foxws\Podman\Support;

use Cron\CronExpression;
use DateTimeZone;
use Foxws\Podman\Enums\TimerCalendar;
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
 * Falls back to every minute when the schedule is empty, has sub-minute
 * tasks, or uses cron syntax systemd can't express (such as "L", "W" or
 * "#").
 */
class PodmanScheduleCalendar
{
    /**
     * @var array<int, string>
     */
    protected const array Weekdays = [0 => 'Sun', 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat'];

    /**
     * @var array<string, int>
     */
    protected const array MonthNames = [
        'JAN' => 1, 'FEB' => 2, 'MAR' => 3, 'APR' => 4, 'MAY' => 5, 'JUN' => 6,
        'JUL' => 7, 'AUG' => 8, 'SEP' => 9, 'OCT' => 10, 'NOV' => 11, 'DEC' => 12,
    ];

    /**
     * @var array<string, int>
     */
    protected const array WeekdayNames = [
        'SUN' => 0, 'MON' => 1, 'TUE' => 2, 'WED' => 3, 'THU' => 4, 'FRI' => 5, 'SAT' => 6,
    ];

    public function __construct(
        protected ?Schedule $schedule = null,
    ) {}

    /**
     * The "OnCalendar=" lines for the scheduler timer, one per line.
     */
    public function render(): string
    {
        return implode("\n", array_map(
            fn (string $calendar): string => "OnCalendar={$calendar}",
            $this->calendars(),
        ));
    }

    /**
     * @return list<string>
     */
    public function calendars(): array
    {
        $events = $this->schedule()->events();

        if ($events === []) {
            return [TimerCalendar::EveryMinute->value];
        }

        $calendars = [];

        foreach ($events as $event) {
            $eventCalendars = $this->eventCalendars($event);

            if ($eventCalendars === null) {
                return [TimerCalendar::EveryMinute->value];
            }

            array_push($calendars, ...$eventCalendars);
        }

        if (in_array(TimerCalendar::EveryMinute->value, $calendars, true)) {
            return [TimerCalendar::EveryMinute->value];
        }

        return array_values(array_unique($calendars));
    }

    /**
     * @return list<string>|null
     */
    protected function eventCalendars(Event $event): ?array
    {
        if ($event->isRepeatable()) {
            return null;
        }

        $timezone = $this->timezone($event);

        if ($timezone === null) {
            return null;
        }

        return $this->fromCron($event->expression, $timezone);
    }

    /**
     * Converts a cron expression to systemd calendar events, or null when
     * systemd can't express it. Cron runs a task when either the day of the
     * month or the weekday matches if both are set, while systemd needs both
     * to match, so such an expression becomes two calendar events.
     *
     * @return list<string>|null
     */
    public function fromCron(string $expression, string $timezone = 'UTC'): ?array
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
        $weekdays = $this->expand($weekday, 0, 7, self::WeekdayNames);

        if (in_array(false, [$minutes, $hours, $days, $months, $weekdays], true)) {
            return null;
        }

        $weekdays = $this->normalizeWeekdays($weekdays);

        if ($minutes === null && $hours === null && $days === null && $months === null && $weekdays === null) {
            return [TimerCalendar::EveryMinute->value];
        }

        $time = "{$this->format($hours, 0, 23)}:{$this->format($minutes, 0, 59)}:00 {$timezone}";

        if ($days !== null && $weekdays !== null) {
            return [
                "{$this->formatDate($months, $days)} {$time}",
                "{$this->formatWeekdays($weekdays)} {$this->formatDate($months, null)} {$time}",
            ];
        }

        $prefix = $weekdays === null ? '' : "{$this->formatWeekdays($weekdays)} ";

        return ["{$prefix}{$this->formatDate($months, $days)} {$time}"];
    }

    /**
     * @param  list<int>|null  $months
     * @param  list<int>|null  $days
     */
    protected function formatDate(?array $months, ?array $days): string
    {
        return "*-{$this->format($months, 1, 12)}-{$this->format($days, 1, 31)}";
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
        return implode(',', array_map(fn (int $day): string => self::Weekdays[$day], $weekdays));
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
