<?php

declare(strict_types=1);

namespace EventsSite\Cache;

/**
 * An answer kept: what the API said, until when it is used as it is (`freshUntil`), and until when it is kept at
 * all (`keptUntil`), which is for when the API fails. Both are Unix times.
 */
final class Kept
{
    /** @param array<mixed> $answer */
    public function __construct(
        public readonly array $answer,
        public readonly int $freshUntil,
        public readonly int $keptUntil,
    ) {
    }
}
