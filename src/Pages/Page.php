<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Api\Unavailable;
use EventsSite\Route;

/** One kind of page: what it asks the API for an address, and what it gives its template. */
interface Page
{
    /**
     * @throws NotThere when what the address names is not there
     * @throws Unavailable when the API fails and nothing is kept
     */
    public function show(Route $route, Asking $asking): Shown;
}
