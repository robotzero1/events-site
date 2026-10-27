<?php

declare(strict_types=1);

namespace EventsSite;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Days and times in words, from the API's own strings: `2027-06-05T20:00` for a timed Occurrence, `2027-06-05` for
 * an all-day one, both local time at the event. Nothing is moved to another time zone: a day is worked on as a day
 * of the calendar, the names of days and months coming from the language's words.
 */
final class When
{
    private Strings $strings;

    public function __construct(private readonly Language $language)
    {
        $this->strings = new Strings($language);
    }

    /** The day of an Occurrence's start or finish, `2027-06-05`. */
    public static function dayOf(string $at): string
    {
        return substr($at, 0, 10);
    }

    /** The time of day of a timed one, `20:00`, or null for a day alone. */
    public static function timeOf(string $at): ?string
    {
        return strlen($at) > 10 ? substr($at, 11, 5) : null;
    }

    /** A day in full as a heading, its first letter a capital: "Friday 30 October", "Viernes 30 de octubre". */
    public function heading(string $day, ?string $today = null): string
    {
        return self::capitalised($this->day($day, $today));
    }

    private static function capitalised(string $words): string
    {
        return mb_strtoupper(mb_substr($words, 0, 1)) . mb_substr($words, 1);
    }

    /**
     * A day in full as it is written within a sentence, "Friday 30 October", "viernes 30 de octubre", with its year
     * when that is not this year's.
     */
    public function day(string $day, ?string $today = null): string
    {
        [$weekday, $date, $month, $year] = $this->parts($day);
        $words = $today !== null && substr($today, 0, 4) !== substr($day, 0, 4)
            ? $this->strings->get('when.dayYear', $this->strings->get("day.{$weekday}"), $date, $this->strings->get("month.{$month}"), $year)
            : $this->strings->get('when.day', $this->strings->get("day.{$weekday}"), $date, $this->strings->get("month.{$month}"));

        return $words;
    }

    /** A day in short, "Fri 30 Oct". */
    public function short(string $day): string
    {
        [$weekday, $date, $month] = $this->parts($day);

        return $this->strings->get('when.short', $this->strings->get("dayShort.{$weekday}"), $date, $this->strings->get("monthShort.{$month}"));
    }

    /** When on its day an Occurrence starts: "20:30", or "All day". */
    public function time(array $occurrence): string
    {
        return $occurrence['all_day'] ? $this->strings->get('when.allDay') : (string) self::timeOf($occurrence['starts_at']);
    }

    /** Its day and time together, as a line of a list not grouped by day gives it: "Fri 30 Oct, 20:30". */
    public function dayAndTime(array $occurrence): string
    {
        $day = $this->short(self::dayOf($occurrence['starts_at']));

        return $occurrence['all_day'] ? $day : $this->strings->get('when.dayAndTime', $day, self::timeOf($occurrence['starts_at']));
    }

    /**
     * An Occurrence in full, as an event's own page lists them, its first letter a capital: "Friday 30 October,
     * 18:00–21:30", "Jueves 29 de octubre – lunes 2 de noviembre", "Friday 30 October, 22:00 – Saturday 31 October,
     * 02:00".
     */
    public function occurrence(array $occurrence, ?string $today = null): string
    {
        return self::capitalised($this->occurrenceWords($occurrence, $today));
    }

    private function occurrenceWords(array $occurrence, ?string $today): string
    {
        $start = self::dayOf($occurrence['starts_at']);
        $finish = $occurrence['finishes_at'] !== null ? self::dayOf($occurrence['finishes_at']) : null;

        if ($occurrence['all_day']) {
            return $finish !== null && $finish > $start
                ? $this->strings->get('when.dayRange', $this->day($start, $today), $this->day($finish, $today))
                : $this->day($start, $today);
        }

        $at = $this->strings->get('when.dayAndTime', $this->day($start, $today), self::timeOf($occurrence['starts_at']));

        if ($occurrence['finishes_at'] === null) {
            return $at;
        }

        return $finish === $start
            ? $this->strings->get('when.dayAndTime', $this->day($start, $today), $this->strings->get('when.timeRange', self::timeOf($occurrence['starts_at']), self::timeOf($occurrence['finishes_at'])))
            : $this->strings->get('when.dayRange', $at, $this->strings->get('when.dayAndTime', $this->day($finish, $today), self::timeOf($occurrence['finishes_at'])));
    }

    /**
     * Whether an Occurrence is over at `$now` (`2026-10-30T21:15`, local time), as the API counts it: a timed one
     * with a finish once that has passed, one with none after its start day, an all-day one after its last day.
     */
    public static function isOver(array $occurrence, string $now): bool
    {
        $today = self::dayOf($now);

        if ($occurrence['all_day']) {
            return (self::lastDayOf($occurrence) ?? self::dayOf($occurrence['starts_at'])) < $today;
        }

        return $occurrence['finishes_at'] !== null
            ? $occurrence['finishes_at'] <= $now
            : self::dayOf($occurrence['starts_at']) < $today;
    }

    /** The last day of an all-day Occurrence that runs over several, or null. */
    public static function lastDayOf(array $occurrence): ?string
    {
        if (!$occurrence['all_day'] || $occurrence['finishes_at'] === null) {
            return null;
        }

        $last = self::dayOf($occurrence['finishes_at']);

        return $last > self::dayOf($occurrence['starts_at']) ? $last : null;
    }

    /** @return array{int, int, int, int} the day of the week (1 for Monday), the date, the month and the year */
    private function parts(string $day): array
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));

        if ($date === false) {
            throw new \InvalidArgumentException("\"{$day}\" is no day.");
        }

        return [(int) $date->format('N'), (int) $date->format('j'), (int) $date->format('n'), (int) $date->format('Y')];
    }
}
