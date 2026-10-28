<?php

// How the standalone site is set up. Each setting can be given in the environment, or in `config.local.php`
// beside this file, which is not kept in git and returns an array of the settings it changes.

$config = [
    // The API, up to and including `/api`.
    'api' => getenv('EVENTS_API') ?: 'http://127.0.0.1:8000/api',
    // The address to connect to for the API's name, as on the server, where it is asked by its name over HTTPS
    // but on the machine itself (127.0.0.1), whose requests are the ones the API believes X-Forwarded-For from.
    // Empty to connect as the name says.
    'api_connect_to' => getenv('EVENTS_API_CONNECT_TO') ?: '',
    // Where the site answers, for the addresses given in full in a page's head.
    'site_url' => getenv('EVENTS_SITE_URL') ?: 'http://127.0.0.1:8090',
    // What signs what the site must know it gave out itself, as when a form was shown. Empty: one is made at random
    // and kept in `var/secret`.
    'secret' => getenv('EVENTS_SITE_SECRET') ?: '',
    // Where the cache, the compiled templates, the log and the secret are kept.
    'var' => getenv('EVENTS_SITE_VAR') ?: __DIR__ . '/var',
];

if (is_file(__DIR__ . '/config.local.php')) {
    $config = array_replace($config, require __DIR__ . '/config.local.php');
}

return $config;
