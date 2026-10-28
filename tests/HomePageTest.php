<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use EventsSite\When;
use PHPUnit\Framework\TestCase;

/**
 * The front page, against the known state: the book fair, on from today, is the one event in the coming week, the
 * films being eight and twelve days on.
 */
final class HomePageTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    private function week(Language $language = Language::En): array
    {
        return SavedApi::answer('/events', ['to' => self::inDays(7), 'per_page' => 50], $language);
    }

    public function test_the_coming_week_by_day(): void
    {
        $response = $this->visit('/en/');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame(['to' => self::inDays(7), 'per_page' => 50], array_values(array_filter($this->api->asked, fn ($asked) => $asked['path'] === '/events'))[0]['query']);
        $this->assertSame("What's on along the coast", $this->text($page, '//main//h1'));
        $this->assertSame("Costa Tropical · What's on", $this->text($page, '//title'));
        $this->assertSame([self::written(0) . ' Today'], $this->texts($page, '//section[@class="day"]/h2'));
        $this->assertSame(['Book fair'], $this->texts($page, '//section[@class="day"]//h3'));
        $this->assertSame('/en/events', $this->attribute($page, '//a[text()="See every day"]/@href'));
        $this->assertSame(0, $page->query('//meta[@name="robots"]')->length);
    }

    public function test_the_front_page_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/'));

        $this->assertSame('Qué hay en la costa', $this->text($page, '//main//h1'));
        $this->assertSame([self::written(0, language: Language::Es) . ' Hoy'], $this->texts($page, '//section[@class="day"]/h2'));
        $this->assertSame('/en/', $this->attribute($page, '//a[@hreflang="en"]/@href'));
        $this->assertSame('/es/eventos', $this->attribute($page, '//form[@role="search"]/@action'));
        $this->assertSame('q', $this->attribute($page, '//form[@role="search"]//input/@name'));
    }

    public function test_the_quickest_ways_into_the_listing(): void
    {
        [$friday, $sunday] = When::weekend(self::captured());
        $today = self::captured();

        $page = $this->page($this->visit('/es/'));

        $this->assertSame("/es/eventos?desde={$today}&hasta={$today}", $this->attribute($page, '//a[text()="Hoy"]/@href'));
        $this->assertSame("/es/eventos?desde={$friday}&hasta={$sunday}", $this->attribute($page, '//a[text()="Este fin de semana"]/@href'));
        $this->assertSame('/es/eventos', $this->attribute($page, '//a[text()="Todos los días"]/@href'));
    }

    public function test_a_day_with_nothing_on_is_left_out(): void
    {
        // The fair today and a film five days on: those two days, and nothing for the days between.
        $week = $this->week();
        $film = SavedApi::answer('/events', ['per_page' => 20], Language::En)['data'][1];
        $film['occurrences'][0]['starts_at'] = self::inDays(5) . 'T20:00';
        $week['data'][] = $film;
        $this->api->instead('/events', $week);

        $page = $this->page($this->visit('/en/'));

        $this->assertSame([self::written(0) . ' Today', self::written(5)], $this->texts($page, '//section[@class="day"]/h2'));
    }

    public function test_a_week_with_nothing_on_says_so(): void
    {
        $week = $this->week();
        $week['data'] = [];
        $week['meta']['total'] = 0;
        $this->api->instead('/events', $week);

        $page = $this->page($this->visit('/en/'));

        $this->assertSame('Nothing is on in the next week.', $this->text($page, '//p[@class="none"]'));
        $this->assertSame('/en/events', $this->attribute($page, '//a[text()="See every day"]/@href'));
    }

    public function test_the_bare_site_leads_to_it(): void
    {
        $this->assertSame('/en/', $this->visit('/', acceptLanguage: 'en')->headers['Location']);
        $this->assertSame(200, $this->visit('/en/')->status);
    }
}
