<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\EventLines;
use EventsSite\Route;
use EventsSite\Visit;
use EventsSite\Strings;
use EventsSite\Text;

/**
 * A Venue: `GET /venues/{ref}`, which holds its coming events too. How to find it and reach it, its photos, and
 * what is coming up there by day. What it does not have is left out rather than said to be missing, so that a Venue
 * with little looks complete. A pending one the API does not give, and is not found.
 */
final class Venue implements Page
{
    public function show(Route $route, Asking $asking, Visit $visit): Shown
    {
        $language = $route->language;
        $strings = new Strings($language);
        $venue = $asking->get("/venues/{$route->ref}", [], Asking::LISTING)['data'];

        return new Shown('pages/venue.twig', [
            'venue' => $venue,
            'lang' => $venue['language'] !== $language->value ? $venue['language'] : null,
            'paragraphs' => Text::paragraphs($venue['description']),
            'address' => $venue['address'] ?? null,
            'map' => isset($venue['lat'], $venue['lng'])
                ? "https://www.openstreetmap.org/?mlat={$venue['lat']}&mlon={$venue['lng']}#map=17/{$venue['lat']}/{$venue['lng']}"
                : null,
            'phone' => ($dial = Text::dialled($venue['phone'])) !== null ? ['dial' => $dial, 'words' => $venue['phone']] : null,
            'email' => ($mail = Text::mailed($venue['email'])) !== null ? ['mail' => $mail, 'words' => trim($venue['email'])] : null,
            'website' => ($site = Text::webAddress($venue['website'])) !== null ? ['url' => $site, 'words' => Text::shortAddress($site)] : null,
            // Only a web page's address is linked; one that is not is left out.
            'links' => array_values(array_filter(array_map(fn (array $link) => ($url = Text::webAddress($link['url'])) !== null
                ? ['url' => $url, 'words' => trim((string) $link['platform']) !== '' ? $link['platform'] : Text::shortAddress($url)]
                : null, $venue['social_links']))),
            'photos' => array_map(fn (array $photo) => [
                'url' => $photo['url'],
                'alt' => (string) ($photo['name'] ?? ''),
                'caption' => $photo['description'],
            ], $venue['photos']),
            'days' => (new EventLines($language))->byDay($venue['upcoming_events'], $asking->today(), $asking->today()),
        ], $venue['name'], Text::summary($venue['description']) ?? $strings->get('venue.description', $venue['venue_type']['name'], $venue['location']['name']));
    }
}
