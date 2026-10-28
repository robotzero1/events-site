<?php

declare(strict_types=1);

namespace EventsSite;

use DateTimeImmutable;
use DateTimeZone;
use EventsSite\Api\Asking;

/**
 * What search engines are told of an event, as schema.org data (JSON-LD): each of its times an `Event` of its own,
 * as Google asks of one that happens more than once, with its place (the Venue's street address and coordinates
 * when it has them, and its Location), its price as an offer, whether it is cancelled, its pictures and its
 * Organiser. A time is given with its offset in Spain, a day alone as a day.
 */
final class StructuredData
{
    /** No more times than this are given: a long run would make the page heavy for nothing. */
    public const MOST_TIMES = 50;

    /**
     * @param array<string, mixed> $event the API's event (`EventResource`)
     * @param array<string, mixed>|null $venue the API's Venue (`VenueResource`), when it has one
     * @return list<array<string, mixed>>
     */
    public static function event(array $event, ?array $venue): array
    {
        $shared = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => $event['name'],
            'description' => $event['description'] !== null && trim($event['description']) !== '' ? trim($event['description']) : null,
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => self::place($event, $venue),
            'image' => array_values(array_filter(array_map(fn (array $photo) => Text::webAddress($photo['url']), $event['photos']))) ?: null,
            'offers' => $event['price_from'] !== null ? [
                '@type' => 'Offer',
                'price' => number_format((float) $event['price_from'], 2, '.', ''),
                'priceCurrency' => 'EUR',
            ] : null,
            'organizer' => $event['organiser'] !== null ? ['@type' => 'Organization', 'name' => $event['organiser']['name']] : null,
        ], fn ($value) => $value !== null);

        $times = [];

        foreach (array_slice($event['occurrences'], 0, self::MOST_TIMES) as $occurrence) {
            $cancelled = $event['status'] === 'cancelled' || $occurrence['cancelled'];

            $times[] = array_filter([
                ...$shared,
                'startDate' => self::moment($occurrence['starts_at'], $occurrence['all_day']),
                'endDate' => $occurrence['finishes_at'] !== null ? self::moment($occurrence['finishes_at'], $occurrence['all_day']) : null,
                'eventStatus' => 'https://schema.org/' . ($cancelled ? 'EventCancelled' : 'EventScheduled'),
            ], fn ($value) => $value !== null);
        }

        return $times;
    }

    /** A time as schema.org writes one, with its offset in Spain (`2026-11-04T20:00:00+01:00`), or a day alone. */
    private static function moment(string $local, bool $allDay): string
    {
        return $allDay
            ? When::dayOf($local)
            : (new DateTimeImmutable($local, new DateTimeZone(Asking::TIME_ZONE)))->format('Y-m-d\TH:i:sP');
    }

    /** @return array<string, mixed> */
    private static function place(array $event, ?array $venue): array
    {
        $address = array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => $venue['address'] ?? null,
            'addressLocality' => $event['location']['name'],
            'addressRegion' => $event['location']['region']['name'] ?? null,
            'addressCountry' => 'ES',
        ], fn ($value) => $value !== null && $value !== '');

        return array_filter([
            '@type' => 'Place',
            'name' => $event['venue']['name'] ?? $event['location']['name'],
            'address' => $address,
            'geo' => isset($venue['lat'], $venue['lng']) ? ['@type' => 'GeoCoordinates', 'latitude' => $venue['lat'], 'longitude' => $venue['lng']] : null,
        ], fn ($value) => $value !== null);
    }
}
