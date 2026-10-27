<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Strings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LanguageTest extends TestCase
{
    /** @return array<string, array{?string, Language}> */
    public static function headers(): array
    {
        return [
            'English' => ['en', Language::En],
            'British English, then Spanish' => ['en-GB,en;q=0.9,es;q=0.8', Language::En],
            'Spanish of Spain' => ['es-ES,es;q=0.9', Language::Es],
            'English weighted below Spanish' => ['en;q=0.5,es;q=0.9', Language::Es],
            'neither' => ['fr-FR,de;q=0.8', Language::Es],
            'neither, then English' => ['fr-FR,de;q=0.8,en;q=0.3', Language::En],
            'nothing' => [null, Language::Es],
            'any' => ['*', Language::Es],
            'English refused' => ['en;q=0', Language::Es],
        ];
    }

    #[DataProvider('headers')]
    public function test_the_language_a_browser_prefers(?string $header, Language $expected): void
    {
        $this->assertSame($expected, Language::preferredBy($header));
    }

    public function test_both_languages_have_words_for_the_same_things(): void
    {
        $this->assertSame(array_keys(Strings::all(Language::Es)), array_keys(Strings::all(Language::En)));
    }
}
