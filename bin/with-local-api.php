<?php

declare(strict_types=1);

// Runs a command against the real events API on this machine, in its known test state: it fills the API's test
// database with `php artisan panel:seed --fresh`, starts the API with PHP's own server on a port of its own, runs
// the command with EVENTS_API saying where it is, and stops the API afterwards, whatever came of the command.
//
//     php bin/with-local-api.php php bin/capture-answers.php
//     php bin/with-local-api.php vendor/bin/phpunit --testsuite real
//
// The API's repository is private and is looked for at ../Events, or wherever EVENTS_API_DIR says. Its test
// database must be set up as its own README says. Nothing it does reaches outside this machine: its keys for
// other services are emptied, no email goes anywhere and no website is asked anything.

$command = array_slice($argv, 1) ?: exit("Usage: php bin/with-local-api.php <command> [arguments]\n");
$apiDir = realpath(getenv('EVENTS_API_DIR') ?: dirname(__DIR__, 2) . '/Events') ?: exit("The events API was not found: set EVENTS_API_DIR.\n");
$port = 8012;

$environment = getenv() + [];
$environment = array_replace($environment, [
    'DB_DATABASE' => 'events_test',
    'CACHE_STORE' => 'array',
    'MAIL_MAILER' => 'array',
    'READER_ASKS_NOBODY' => 'true',
    // No key for any outside service. Not '': on Windows proc_open drops a variable that is empty, and the
    // key in the API's .env used. Laravel reads 'null' as none.
    'VOYAGE_API_KEY' => 'null',
    'OPENAI_API_KEY' => 'null',
    'ANTHROPIC_API_KEY' => 'null',
    'APP_ENV' => 'local',
]);

echo "Making the API's known state...\n";
$seed = proc_open([PHP_BINARY, 'artisan', 'panel:seed', '--fresh'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $apiDir, $environment);
$output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);

if (proc_close($seed) !== 0) {
    exit("panel:seed failed:\n{$output}");
}

$server = proc_open(
    [PHP_BINARY, '-S', "127.0.0.1:{$port}", $apiDir . '/vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php'],
    [1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
    $pipes,
    $apiDir . '/public',
    $environment,
    ['bypass_shell' => true],
);

// Whatever happens next, the API is stopped: left running it would hold this command's output open for good.
register_shutdown_function(static function () use ($server): void {
    proc_terminate($server);
});

$api = "http://127.0.0.1:{$port}/api";
$up = false;

for ($try = 0; $try < 50 && !$up; $try++) {
    usleep(200_000);
    $up = @file_get_contents("{$api}/categories") !== false;
}

if (!$up) {
    exit("The API did not start on port {$port}.\n");
}

echo "The API is at {$api}.\n";

// A PHP script such as vendor/bin/phpunit is run by PHP itself, as Windows cannot run it by its name.
$first = is_file(dirname(__DIR__) . '/' . $command[0]) ? (string) fgets(fopen(dirname(__DIR__) . '/' . $command[0], 'r')) : '';

if (str_contains($first, 'php')) {
    array_unshift($command, PHP_BINARY);
}

$run = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, dirname(__DIR__), array_replace(getenv(), ['EVENTS_API' => $api]), ['bypass_shell' => true]);

if ($run === false) {
    exit('Could not run ' . implode(' ', $command) . "\n");
}

exit(proc_close($run));
