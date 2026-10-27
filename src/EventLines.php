<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * The API's lines of events (`EventSummaryResource`) as a list shows them: grouped by day, the day being the first
 * the event is on from where the list starts, or one after another with each line's own day, as a Search best
 * match first is. Each holds only words ready to show, so that its template has nothing to work out.
 */
final class EventLines
{
    private Strings $strings;
    private When $when;

    public function __construct(private readonly Language $language)
    {
        $this->strings = new Strings($language);
        $this->when = new When($language);
    }

    /**
     * The events by day, in the API's order, each day once: what a list in the listing's own order shows.
     *
     * @param list<array<string, mixed>> $events
     * @param string $from the first day the list is of, `2026-10-30`
     * @return list<array{day: string, heading: string, today: bool, lines: list<array<string, mixed>>}>
     */
    public function byDay(array $events, string $from, string $today): array
    {
        $days = [];

        foreach ($events as $event) {
            $first = $event['occurrences'][0] ?? null;

            if ($first === null) {
                continue;
            }

            // An event already on when the list starts, as a fair that began yesterday, is under the list's first day.
            $day = max(When::dayOf($first['starts_at']), $from);

            $days[$day] ??= ['day' => $day, 'heading' => $this->when->heading($day, $today), 'today' => $day === $today, 'lines' => []];
            $days[$day]['lines'][] = $this->line($event, false);
        }

        return array_values($days);
    }

    /**
     * The events one after another, each with its own day: what a Search best match first shows.
     *
     * @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public function oneByOne(array $events): array
    {
        return array_values(array_map(fn (array $event) => $this->line($event, true), array_filter($events, fn (array $event) => isset($event['occurrences'][0]))));
    }

    /** @return array<string, mixed> */
    private function line(array $event, bool $withDay): array
    {
        $first = $event['occurrences'][0];
        $others = count($event['occurrences']) - 1;
        $lastDay = When::lastDayOf($first);

        return [
            'ref' => $event['ref'],
            'name' => $event['name'],
            // The language its name is in, when it has none in the page's.
            'lang' => $event['language'] !== $this->language->value ? $event['language'] : null,
            'when' => $withDay ? $this->when->dayAndTime($first) : $this->when->time($first),
            'where' => $this->where($event),
            'until' => $lastDay !== null ? $this->strings->get('line.until', $this->when->day($lastDay)) : null,
            'more' => match (true) {
                $others === 1 => $this->strings->get('line.moreOne'),
                $others > 1 => $this->strings->get('line.moreMany', $others),
                default => null,
            },
            'price' => $event['price_from'] !== null ? $this->strings->get('line.price', $this->number((float) $event['price_from'], 2)) : null,
            'categories' => implode(', ', array_map(fn (array $category) => $category['name'], $event['categories'])),
            'rating' => $event['review_count'] > 0 && $event['rating'] !== null
                ? ($event['review_count'] === 1
                    ? $this->strings->get('line.ratingOne', $this->number((float) $event['rating'], 1))
                    : $this->strings->get('line.ratingMany', $this->number((float) $event['rating'], 1), $event['review_count']))
                : null,
            'cancelled' => $event['status'] === 'cancelled' || $first['cancelled'],
            'photo' => $event['photo'] !== null ? ['url' => $event['photo']['url'], 'alt' => (string) ($event['photo']['name'] ?? '')] : null,
        ];
    }

    private function where(array $event): string
    {
        $location = $event['location']['name'] ?? '';
        $venue = $event['venue']['name'] ?? null;

        return $venue !== null ? "{$venue}, {$location}" : $location;
    }

    /** A number as the language writes it, `4.50` or `4,50`, with no decimals when it is whole. */
    private function number(float $number, int $places): string
    {
        $decimals = fmod($number, 1.0) === 0.0 ? 0 : $places;

        return $this->language === Language::Es
            ? number_format($number, $decimals, ',', '.')
            : number_format($number, $decimals, '.', ',');
    }
}
