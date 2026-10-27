<?php

declare(strict_types=1);

namespace EventsSite;

/** Which page an address is: its name, its language, its ref if it names one, and a listing's filters. */
final class Route
{
    /** @param array<string, string> $filters */
    public function __construct(
        public readonly string $name,
        public readonly Language $language,
        public readonly ?string $ref = null,
        public readonly array $filters = [],
    ) {
    }
}
