<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Language;

/**
 * An API a test sets up by hand: it gives the answer it was told for a path, fails when told to, and remembers
 * every question it was asked, with the language and the visitor's address.
 */
final class StandInApi implements Api
{
    /** @var array<string, array<mixed>> */
    private array $answers = [];

    private bool $failing = false;

    /** @var list<array{path: string, query: array, language: Language, visitor: string}> */
    public array $asked = [];

    /** @param array<mixed> $answer */
    public function gives(string $path, array $answer): self
    {
        $this->answers[$path] = $answer;

        return $this;
    }

    public function fails(bool $failing = true): self
    {
        $this->failing = $failing;

        return $this;
    }

    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        $this->asked[] = ['path' => $path, 'query' => $query, 'language' => $language, 'visitor' => $visitor];

        if ($this->failing) {
            throw new ApiFailed("{$path} answered 500");
        }

        return $this->answers[$path] ?? throw new \LogicException("The stand-in API was told nothing about {$path}.");
    }
}
