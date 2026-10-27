<?php

// What the saved answers are answers to: each question is asked of the real API in its known test state by
// `php bin/with-local-api.php php bin/capture-answers.php`, and its answer kept under `tests/answers/`, which is
// what `SavedApi` gives the tests. A page that asks something new adds the question here and captures again.
// Each is [path, query, language].

return [
    ['/regions', [], 'es'],
    ['/regions', [], 'en'],
    ['/regions/granada/locations', [], 'es'],
    ['/regions/granada/locations', [], 'en'],
    ['/categories', [], 'es'],
    ['/categories', [], 'en'],
    // The listing, as the site asks for it: 20 to a page, and a place as a Location or a Region.
    ['/events', ['per_page' => 20], 'es'],
    ['/events', ['per_page' => 20], 'en'],
    ['/events', ['location' => 'motril', 'per_page' => 20], 'en'],
    ['/events', ['region' => 'granada', 'per_page' => 20], 'en'],
    ['/events', ['category' => 'mercados', 'per_page' => 20], 'en'],
    // A Search, best match first and by date, and one that finds nothing.
    ['/events', ['q' => 'market', 'per_page' => 20], 'en'],
    ['/events', ['q' => 'market', 'sort' => 'date', 'per_page' => 20], 'en'],
    ['/events', ['q' => 'zzzz', 'per_page' => 20], 'en'],
    // What the listing refuses.
    ['/events', ['from' => 'not-a-day', 'per_page' => 20], 'en'],
    ['/events', ['location' => 'nowhere', 'per_page' => 20], 'en'],
    // The API's own "not found", which a page turns into its own.
    ['/events/no-such-event', [], 'en'],
];
