<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

use EventsSite\Log;

/** The website's log, a file of lines, each with when. */
final class FileLog implements Log
{
    public function __construct(private readonly string $file)
    {
    }

    public function failure(string $message): void
    {
        $folder = dirname($this->file);

        if (!is_dir($folder)) {
            @mkdir($folder, 0750, true);
        }

        @file_put_contents($this->file, gmdate('Y-m-d\TH:i:s\Z') . ' ' . str_replace(["\r", "\n"], ' ', $message) . "\n", FILE_APPEND | LOCK_EX);
    }
}
