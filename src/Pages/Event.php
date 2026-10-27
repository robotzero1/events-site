<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Numbers;
use EventsSite\Route;
use EventsSite\Strings;
use EventsSite\When;

/**
 * An event in full: `GET /events/{ref}`, with its Reviews and Comments, each a request of its own, and its Venue,
 * whose coordinates (and street address, once the API gives one) the event's own answer does not hold. An event
 * whose times are all over stays, saying so, and so does a cancelled one: both keep their Reviews, which are what
 * people look for on an event that has been.
 */
final class Event implements Page
{
    public function show(Route $route, Asking $asking): Shown
    {
        $language = $route->language;
        $strings = new Strings($language);
        $when = new When($language);
        $today = $asking->today();
        $now = $asking->now();

        $event = $asking->get("/events/{$route->ref}", [], Asking::LISTING)['data'];
        $reviews = $asking->get("/events/{$route->ref}/reviews", [], Asking::LISTING);
        $comments = $asking->get("/events/{$route->ref}/comments", [], Asking::LISTING);
        $venue = null;

        if ($event['venue'] !== null) {
            try {
                $venue = $asking->get("/venues/{$event['venue']['ref']}", [], Asking::LISTING)['data'];
            } catch (NotThere) {
                // Taken away a moment ago: the event's own answer still names it.
            }
        }

        $occurrences = $event['occurrences'];
        $ended = $occurrences !== [] && array_filter($occurrences, fn (array $occurrence) => !When::isOver($occurrence, $now)) === [];
        $written = fn (array $one) => [
            'words' => $one['content'],
            'by' => $strings->get('event.byOn', $one['author']['name'], $when->day(When::dayOf($one['created_at']), $today)),
        ];

        return new Shown('pages/event.twig', [
            'event' => $event,
            'lang' => $event['language'] !== $language->value ? $event['language'] : null,
            'paragraphs' => self::paragraphs($event['description']),
            'cancelled' => $event['status'] === 'cancelled',
            'ended' => $ended,
            'times' => array_map(fn (array $occurrence) => [
                'words' => $when->occurrence($occurrence, $today),
                'cancelled' => $occurrence['cancelled'],
            ], $occurrences),
            'venue' => $event['venue'] === null ? null : [
                'ref' => $event['venue']['ref'],
                'name' => $event['venue']['name'],
                'address' => $venue['address'] ?? null,
                'map' => isset($venue['lat'], $venue['lng'])
                    ? "https://www.openstreetmap.org/?mlat={$venue['lat']}&mlon={$venue['lng']}#map=17/{$venue['lat']}/{$venue['lng']}"
                    : null,
            ],
            'photos' => array_map(fn (array $photo) => [
                'url' => $photo['url'],
                'alt' => (string) ($photo['name'] ?? ''),
                'caption' => $photo['description'],
            ], $event['photos']),
            'price' => $event['price_from'] !== null ? $strings->get('line.price', Numbers::price((float) $event['price_from'], $language)) : null,
            'rating' => $event['review_count'] > 0 && $event['rating'] !== null ? [
                'value' => Numbers::rating((float) $event['rating'], $language),
                'from' => $event['review_count'] === 1 ? $strings->get('event.averageOne') : $strings->get('event.averageMany', $event['review_count']),
            ] : null,
            'reviews' => array_map(fn (array $review) => $written($review) + [
                'title' => $review['title'],
                'rating' => $strings->get('event.outOf', $review['rating']),
                'photos' => array_map(fn (array $photo) => ['url' => $photo['url'], 'alt' => (string) ($photo['name'] ?? '')], $review['photos']),
            ], $reviews['data']),
            'more_reviews' => $reviews['meta']['total'] > count($reviews['data'])
                ? $strings->get('event.moreReviews', count($reviews['data']), $reviews['meta']['total']) : null,
            'comments' => array_map($written, $comments['data']),
            'more_comments' => $comments['meta']['total'] > count($comments['data'])
                ? $strings->get('event.moreComments', count($comments['data']), $comments['meta']['total']) : null,
            'earlier' => array_map(fn (array $edition) => [
                'year' => $edition['year'],
                'reviews' => array_map(fn (array $review) => $written($review) + [
                    'title' => $review['title'],
                    'rating' => $strings->get('event.outOf', $review['rating']),
                ], $edition['reviews']),
            ], array_filter($event['earlier_editions'], fn (array $edition) => $edition['reviews'] !== [])),
        ], $event['name'], self::summary($event['description']) ?? $strings->get('site.description'));
    }

    /** @return list<string> a description's paragraphs, kept apart by an empty line; its other lines are kept. */
    private static function paragraphs(?string $description): array
    {
        if ($description === null || trim($description) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $description)), fn ($paragraph) => $paragraph !== ''));
    }

    /** The start of a description, for a search engine's result or a link's preview: about 155 letters, cut at a word. */
    private static function summary(?string $description): ?string
    {
        if ($description === null || trim($description) === '') {
            return null;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', $description));

        if (mb_strlen($plain) <= 155) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, 155);

        return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ' ,.;:') . '…';
    }
}
