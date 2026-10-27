<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Templates;
use PHPUnit\Framework\TestCase;

final class TemplatesTest extends TestCase
{
    private function render(string $template, array $vars, Language $language = Language::En): string
    {
        $twig = Templates::environment();

        return $twig->createTemplate($template)->render(['language' => $language] + $vars);
    }

    public function test_what_a_template_is_given_is_written_as_text(): void
    {
        $this->assertSame(
            '&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt; &amp; more',
            $this->render('{{ words }}', ['words' => '<script>alert("hi")</script> & more']),
        );
    }

    public function test_the_sites_own_words_are_in_the_pages_language(): void
    {
        $this->assertSame('All events / Todos los eventos', $this->render("{{ t('nav.events') }}", []) . ' / ' . $this->render("{{ t('nav.events') }}", [], Language::Es));
    }

    public function test_an_address_is_in_the_pages_language(): void
    {
        $this->assertSame('/es/evento/feria-del-libro', $this->render("{{ path('event', 'feria-del-libro') }}", [], Language::Es));
        $this->assertSame('/en/events?from=2026-10-30', $this->render("{{ path('events', null, {from: '2026-10-30'}) }}", []));
    }

    public function test_a_missing_value_is_a_mistake_not_an_empty_space(): void
    {
        $this->expectException(\Twig\Error\RuntimeError::class);
        $this->render('{{ nothing }}', []);
    }
}
