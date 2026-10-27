<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\Route;
use EventsSite\Strings;

/** What the site is and who makes it. Its words are the owner's to write; until then it says so. */
final class About implements Page
{
    public function show(Route $route, Asking $asking): Shown
    {
        $strings = new Strings($route->language);

        return new Shown('pages/about.twig', [], $strings->get('about.title'), $strings->get('about.description'));
    }
}
