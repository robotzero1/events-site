<?php

declare(strict_types=1);

// Asks the real API every question in tests/answers/questions.php and keeps each answer for the tests. Run it
// against the API in its known test state, which `with-local-api.php` starts:
//
//     php bin/with-local-api.php php bin/capture-answers.php

use EventsSite\Api\NotThere;
use EventsSite\Language;
use EventsSite\Standalone\CurlApi;
use EventsSite\Tests\Support\SavedApi;

require dirname(__DIR__) . '/vendor/autoload.php';

$base = getenv('EVENTS_API') ?: exit("EVENTS_API is not set: run this through bin/with-local-api.php.\n");
$api = new CurlApi($base, seconds: 30);

foreach (require dirname(__DIR__) . '/tests/answers/questions.php' as [$path, $query, $language]) {
    $language = Language::from($language);

    try {
        $saved = ['status' => 200, 'answer' => $api->get($path, $query, $language, '127.0.0.1')];
    } catch (NotThere) {
        $saved = ['status' => 404, 'answer' => null];
    }

    $file = SavedApi::fileFor($path, $query, $language);
    @mkdir(dirname($file), 0777, true);
    file_put_contents($file, json_encode($saved, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

    echo "{$language->value} {$path}: {$saved['status']}\n";
}
