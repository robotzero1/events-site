<?php

declare(strict_types=1);

namespace EventsSite\Cache;

/**
 * Where the API's answers are kept between visits. Standalone it is files on disk; in WordPress it will be
 * transients. It holds what it is given until `keptUntil` and knows nothing of freshness: that is `Asking`'s.
 */
interface Cache
{
    public function get(string $key): ?Kept;

    public function put(string $key, Kept $kept): void;
}
