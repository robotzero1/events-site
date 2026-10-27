<?php

// For PHP's own server in development (`php -S 127.0.0.1:8090 -t public public/router.php`): a file that is there
// is sent as it is, and every other address goes to the site, as nginx does on the server.

if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require __DIR__ . '/index.php';
