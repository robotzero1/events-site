<?php

declare(strict_types=1);

namespace EventsSite;

/** The website's own log, of the API's failures. Standalone it is a file; in WordPress, its error log. */
interface Log
{
    public function failure(string $message): void;
}
