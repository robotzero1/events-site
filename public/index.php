<?php

declare(strict_types=1);

// The door of the standalone site: every address that is not a file comes here.

use EventsSite\Standalone\Standalone;

require dirname(__DIR__) . '/vendor/autoload.php';

Standalone::configured(require dirname(__DIR__) . '/config.php')
    ->respond(Standalone::visitFrom($_SERVER, $_GET, $_POST, $_FILES))
    ->send();
