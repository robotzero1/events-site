<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Api\Unavailable;
use EventsSite\Route;
use EventsSite\Visit;

/** One kind of page: what it asks the API for an address, and what it gives its template. The visit is given for a
 * page that takes a form. */
interface Page
{
    /**
     * @throws NotThere when what the address names is not there
     * @throws Unavailable when the API fails and nothing is kept
     */
    public function show(Route $route, Asking $asking, Visit $visit): Shown;
}
