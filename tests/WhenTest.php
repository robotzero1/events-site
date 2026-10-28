<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\When;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Days and times in words, on fixed days: 30 October 2026 was a Friday. */
final class WhenTest extends TestCase
{
    private static function at(string $starts, ?string $finishes = null, bool $allDay = false): array
    {
        return ['all_day' => $allDay, 'starts_at' => $starts, 'finishes_at' => $finishes, 'cancelled' => false];
    }

    public function test_a_day_as_a_heading_and_within_a_sentence(): void
    {
        $this->assertSame('Friday 30 October', (new When(Language::En))->heading('2026-10-30'));
        $this->assertSame('Viernes 30 de octubre', (new When(Language::Es))->heading('2026-10-30'));
        $this->assertSame('viernes 30 de octubre', (new When(Language::Es))->day('2026-10-30'));
    }

    public function test_a_day_of_another_year_has_its_year(): void
    {
        $this->assertSame('Friday 1 January 2027', (new When(Language::En))->day('2027-01-01', '2026-10-30'));
        $this->assertSame('viernes 1 de enero de 2027', (new When(Language::Es))->day('2027-01-01', '2026-10-30'));
        $this->assertSame('Friday 30 October', (new When(Language::En))->day('2026-10-30', '2026-01-01'));
    }

    public function test_a_day_in_short(): void
    {
        $this->assertSame('Fri 30 Oct', (new When(Language::En))->short('2026-10-30'));
        $this->assertSame('vie 30 oct', (new When(Language::Es))->short('2026-10-30'));
    }

    /** @return array<string, array{array, Language, string}> */
    public static function occurrences(): array
    {
        return [
            'a time' => [self::at('2026-10-30T20:30'), Language::En, 'Friday 30 October, 20:30'],
            'a time with its finish' => [self::at('2026-10-30T18:00', '2026-10-30T21:30'), Language::En, 'Friday 30 October, 18:00–21:30'],
            'into the next day' => [self::at('2026-10-30T22:00', '2026-10-31T02:00'), Language::En, 'Friday 30 October, 22:00 – Saturday 31 October, 02:00'],
            'a day' => [self::at('2026-10-30', null, true), Language::En, 'Friday 30 October'],
            'a run of days' => [self::at('2026-10-29', '2026-11-02', true), Language::En, 'Thursday 29 October – Monday 2 November'],
            'a run in Spanish' => [self::at('2026-10-29', '2026-11-02', true), Language::Es, 'Jueves 29 de octubre – lunes 2 de noviembre'],
            'a time in Spanish' => [self::at('2026-10-30T20:30'), Language::Es, 'Viernes 30 de octubre, 20:30'],
        ];
    }

    #[DataProvider('occurrences')]
    public function test_an_occurrence_in_full(array $occurrence, Language $language, string $words): void
    {
        $this->assertSame($words, (new When($language))->occurrence($occurrence, '2026-10-30'));
    }

    /** @return array<string, array{string, string, string}> */
    public static function weekends(): array
    {
        // 26 October 2026 was a Monday.
        return [
            'on a Monday, the coming one' => ['2026-10-26', '2026-10-30', '2026-11-01'],
            'on a Thursday, the coming one' => ['2026-10-29', '2026-10-30', '2026-11-01'],
            'on a Friday, from that day' => ['2026-10-30', '2026-10-30', '2026-11-01'],
            'on a Saturday, what is left of it' => ['2026-10-31', '2026-10-31', '2026-11-01'],
            'on a Sunday, that day' => ['2026-11-01', '2026-11-01', '2026-11-01'],
            'across the end of a year' => ['2026-12-31', '2027-01-01', '2027-01-03'],
        ];
    }

    #[DataProvider('weekends')]
    public function test_this_weekend(string $today, string $from, string $to): void
    {
        $this->assertSame([$from, $to], When::weekend($today));
    }

    /** @return array<string, array{array, string, bool}> */
    public static function over(): array
    {
        return [
            'a time with a finish, before it' => [self::at('2026-10-30T18:00', '2026-10-30T21:30'), '2026-10-30T21:29', false],
            'a time with a finish, at it' => [self::at('2026-10-30T18:00', '2026-10-30T21:30'), '2026-10-30T21:30', true],
            'a time with no finish, later that day' => [self::at('2026-10-30T18:00'), '2026-10-30T23:59', false],
            'a time with no finish, the day after' => [self::at('2026-10-30T18:00'), '2026-10-31T00:00', true],
            'a day, on it' => [self::at('2026-10-30', null, true), '2026-10-30T23:59', false],
            'a day, the day after' => [self::at('2026-10-30', null, true), '2026-10-31T00:00', true],
            'a run, on its last day' => [self::at('2026-10-29', '2026-11-02', true), '2026-11-02T22:00', false],
            'a run, after it' => [self::at('2026-10-29', '2026-11-02', true), '2026-11-03T00:00', true],
        ];
    }

    #[DataProvider('over')]
    public function test_whether_an_occurrence_is_over_as_the_api_counts_it(array $occurrence, string $now, bool $over): void
    {
        $this->assertSame($over, When::isOver($occurrence, $now));
    }
}
