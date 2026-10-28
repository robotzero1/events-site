<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\EventLines;
use EventsSite\Route;
use EventsSite\Visit;
use EventsSite\Strings;
use EventsSite\When;

/**
 * The front page: what's on today and in the next seven days, by day, with a search field and the quickest ways
 * into the listing (today, this weekend, every day). `GET /events` up to a week on, 50 at most: the listing has the
 * rest. A day with nothing on is not shown.
 */
final class Home implements Page
{
    /** How many days after today it shows. */
    public const DAYS = 7;

    /** How many events it shows at most. */
    public const MOST = 50;

    public function show(Route $route, Asking $asking, Visit $visit): Shown
    {
        $strings = new Strings($route->language);
        $today = $asking->today();
        [$friday, $sunday] = When::weekend($today);

        $answer = $asking->get('/events', ['to' => When::after($today, self::DAYS), 'per_page' => self::MOST], Asking::LISTING);

        return new Shown('pages/home.twig', [
            'days' => (new EventLines($route->language))->byDay($answer['data'], $today, $today),
            'today' => $today,
            'weekend' => ['from' => $friday, 'to' => $sunday],
        ], $strings->get('home.title'), $strings->get('site.description'));
    }
}
