<?php

declare(strict_types=1);

namespace Foxws\Podman\Enums;

/**
 * A systemd calendar event for a timer's "OnCalendar=", such as the one
 * that fires at the start of every minute.
 */
enum TimerCalendar: string
{
    case EveryMinute = '*-*-* *:*:00';
}
