<?php

declare(strict_types=1);

namespace Foxws\Podman\Enums;

/**
 * A day of the week, numbered as cron numbers it (Sunday is 0).
 */
enum Weekday: int
{
    case Sunday = 0;
    case Monday = 1;
    case Tuesday = 2;
    case Wednesday = 3;
    case Thursday = 4;
    case Friday = 5;
    case Saturday = 6;

    /**
     * The cron names and their numbers, such as "MON" => 1.
     *
     * @return array<string, int>
     */
    public static function cronNames(): array
    {
        $names = [];

        foreach (self::cases() as $weekday) {
            $names[strtoupper($weekday->systemdName())] = $weekday->value;
        }

        return $names;
    }

    /**
     * The name systemd calendar events use, such as "Mon".
     */
    public function systemdName(): string
    {
        return substr($this->name, 0, 3);
    }
}
