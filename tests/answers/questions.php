<?php

// What the saved answers are answers to: each question is asked of the real API in its known test state by
// `php bin/with-local-api.php php bin/capture-answers.php`, and its answer kept under `tests/answers/`, which is
// what `SavedApi` gives the tests. A page that asks something new adds the question here and captures again.
// Each is [path, query, language].

return [
    ['/regions', [], 'es'],
    ['/regions', [], 'en'],
    ['/categories', [], 'es'],
    ['/categories', [], 'en'],
    // The API's own "not found", which a page turns into its own.
    ['/events/no-such-event', [], 'en'],
];
