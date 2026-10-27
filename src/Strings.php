<?php

declare(strict_types=1);

namespace EventsSite;

use OutOfBoundsException;

/** The words the site itself says, in one language, from `lang/{language}.php`. */
final class Strings
{
    /** @var array<string, array<string, string>> */
    private static array $loaded = [];

    public function __construct(private readonly Language $language)
    {
    }

    /** The words for `$key`, with each `%s` filled in from `$fillers` in order. */
    public function get(string $key, string|int|float ...$fillers): string
    {
        $words = self::all($this->language)[$key] ?? throw new OutOfBoundsException("No words for \"{$key}\" in {$this->language->value}.");

        return $fillers === [] ? $words : sprintf($words, ...$fillers);
    }

    /** @return array<string, string> */
    public static function all(Language $language): array
    {
        return self::$loaded[$language->value] ??= require dirname(__DIR__) . "/lang/{$language->value}.php";
    }
}
