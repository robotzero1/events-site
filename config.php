<?php

// How the standalone site is set up. Each setting can be given in the environment, or in `config.local.php`
// beside this file, which is not kept in git and returns an array of the settings it changes.

$config = [
    // The API, up to and including `/api`.
    'api' => getenv('EVENTS_API') ?: 'http://127.0.0.1:8000/api',
    // The name to ask the API by when it is asked at a bare address on the same machine, so that nginx gives
    // the request to the API. Empty to send none.
    'api_host' => getenv('EVENTS_API_HOST') ?: '',
    // Where the site answers, for the addresses given in full in a page's head.
    'site_url' => getenv('EVENTS_SITE_URL') ?: 'http://127.0.0.1:8090',
    // Where the cache, the compiled templates and the log are kept.
    'var' => getenv('EVENTS_SITE_VAR') ?: __DIR__ . '/var',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}

return $config;
