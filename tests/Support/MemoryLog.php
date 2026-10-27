<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Log;

final class MemoryLog implements Log
{
    /** @var list<string> */
    public array $failures = [];

    public function failure(string $message): void
    {
        $this->failures[] = $message;
    }
}
