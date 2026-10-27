<?php

declare(strict_types=1);

namespace EventsSite\Api;

use RuntimeException;

/**
 * The API would not do what was asked, for the visitor's own reasons: what they typed (422, with the fields it
 * names) or asking too often (429). It is said to the visitor in the site's own words, never the API's, which are
 * English only.
 */
final class Refused extends RuntimeException
{
    /** @param array<string, list<string>> $fields the API's messages by the name of each field it refused */
    public function __construct(
        public readonly int $status,
        public readonly array $fields = [],
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : "The API refused it ({$status}).");
    }

    public function isTooOften(): bool
    {
        return $this->status === 429;
    }
}
