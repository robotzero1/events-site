<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Cache\Cache;
use EventsSite\Cache\Kept;

/** A cache that lasts as long as the test. It keeps whatever it is given, as a real one does until `keptUntil`. */
final class ArrayCache implements Cache
{
    /** @var array<string, Kept> */
    public array $kept = [];

    public function get(string $key): ?Kept
    {
        return $this->kept[$key] ?? null;
    }

    public function put(string $key, Kept $kept): void
    {
        $this->kept[$key] = $kept;
    }
}
