<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * One request for a page, as the core needs it, wherever it runs: the address's path and query, the browser's
 * languages and the visitor's own address, which is passed on to the API.
 */
final class Visit
{
    /** @param array<string, mixed> $query as PHP reads a query string */
    public function __construct(
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?string $acceptLanguage = null,
        public readonly string $visitor = '127.0.0.1',
    ) {
    }
}
