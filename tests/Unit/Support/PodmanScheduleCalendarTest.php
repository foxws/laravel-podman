<?php

declare(strict_types=1);

use Foxws\Podman\Enums\TimerCalendar;
use Foxws\Podman\Support\PodmanScheduleCalendar;
use Illuminate\Console\Scheduling\Schedule;

beforeEach(function () {
    config(['app.timezone' => 'UTC']);

    $this->schedule = new Schedule;
    $this->calendar = new PodmanScheduleCalendar($this->schedule);
});

it('fires only in the minutes a task is due', function () {
    $this->schedule->command('horizon:snapshot')->hourly();
    $this->schedule->command('model:prune')->dailyAt('02:30');
    $this->schedule->command('auth:clear-resets')->dailyAt('02:30');

    expect($this->calendar->render())->toBe(<<<'UNIT'
        OnCalendar=*-*-* *:00:00 UTC
        OnCalendar=*-*-* 02:30:00 UTC
        UNIT);
});

it('falls back to every minute without scheduled tasks', function () {
    expect($this->calendar->calendars())->toBe([TimerCalendar::EveryMinute->value]);
});

it('falls back to every minute when a task runs every minute', function () {
    $this->schedule->command('model:prune')->daily();
    $this->schedule->command('queue:prune-batches')->everyMinute();

    expect($this->calendar->calendars())->toBe([TimerCalendar::EveryMinute->value]);
});

it('falls back to every minute for sub-minute tasks', function () {
    $this->schedule->command('model:prune')->daily();
    $this->schedule->command('queue:prune-batches')->everyTenSeconds();

    expect($this->calendar->calendars())->toBe([TimerCalendar::EveryMinute->value]);
});

it('falls back to every minute for cron syntax systemd cannot express', function () {
    $this->schedule->command('model:prune')->cron('0 0 L * *');

    expect($this->calendar->calendars())->toBe([TimerCalendar::EveryMinute->value]);
});

it('falls back to every minute for a timezone systemd does not know', function () {
    $this->schedule->command('model:prune')->daily()->timezone('+02:00');

    expect($this->calendar->calendars())->toBe([TimerCalendar::EveryMinute->value]);
});

it('uses the timezone of each task, or the app', function () {
    config(['app.timezone' => 'Europe/Amsterdam']);

    $this->schedule->command('model:prune')->daily();
    $this->schedule->command('auth:clear-resets')->daily()->timezone('America/New_York');

    expect($this->calendar->calendars())->toBe([
        '*-*-* 00:00:00 Europe/Amsterdam',
        '*-*-* 00:00:00 America/New_York',
    ]);
});

it('converts cron expressions to systemd calendar events', function (string $expression, ?array $calendars) {
    expect($this->calendar->fromCron($expression))->toBe($calendars);
})->with([
    'every minute' => ['* * * * *', ['*-*-* *:*:00']],
    'every five minutes' => ['*/5 * * * *', ['*-*-* *:00/5:00 UTC']],
    'twice an hour' => ['0,30 * * * *', ['*-*-* *:00,30:00 UTC']],
    'office hours' => ['0 9-17 * * 1-5', ['Mon,Tue,Wed,Thu,Fri *-*-* 09..17:00:00 UTC']],
    'weekly on sunday' => ['0 0 * * 7', ['Sun *-*-* 00:00:00 UTC']],
    'weekday names' => ['0 0 * * SAT,SUN', ['Sun,Sat *-*-* 00:00:00 UTC']],
    'monthly' => ['30 4 1 * *', ['*-*-01 04:30:00 UTC']],
    'quarterly' => ['0 0 1 1-12/3 *', ['*-01/3-01 00:00:00 UTC']],
    'month names' => ['0 0 1 JAN,JUL *', ['*-01,07-01 00:00:00 UTC']],
    'alias' => ['@yearly', ['*-01-01 00:00:00 UTC']],
    'every weekday is any day' => ['0 0 * * 0-6', ['*-*-* 00:00:00 UTC']],
    'day of the month or weekday' => ['0 0 1 * 1', ['*-*-01 00:00:00 UTC', 'Mon *-*-* 00:00:00 UTC']],
    'nth weekday' => ['0 0 * * 1#2', null],
    'nearest weekday' => ['0 0 15W * *', null],
]);
