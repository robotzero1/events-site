<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Api\Refused;
use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\TestCase;

/**
 * The listing and Search, against the known state: a book fair on from today to two days on, with one Review, and
 * two films at the Centro Cultural in Motril, eight and twelve days on.
 */
final class EventsPageTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    /** A saved line of the listing, the book fair's, to be changed for what the known state cannot show. */
    private static function line(Language $language = Language::En): array
    {
        return SavedApi::answer('/events', ['per_page' => 20], $language)['data'][0];
    }

    /** The listing answered with these lines, and its pages as given. */
    private function listing(array $lines, int $page = 1, int $pages = 1, Language $language = Language::En): void
    {
        $answer = SavedApi::answer('/events', ['per_page' => 20], $language);
        $answer['data'] = $lines;
        $answer['meta'] = ['current_page' => $page, 'last_page' => $pages, 'total' => ($pages - 1) * 20 + count($lines)] + $answer['meta'];
        $this->api->instead('/events', $answer);
    }

    public function test_whats_on_from_today_by_day(): void
    {
        $response = $this->visit('/en/events');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame("What's on", $this->text($page, '//main//h1'));
        $this->assertSame('3 events', $this->text($page, '//p[@class="count"]'));
        $this->assertSame([self::written(0) . ' Today', self::written(8), self::written(12)], $this->texts($page, '//section[@class="day"]/h2'));
        $this->assertSame(['Book fair', 'The Spirit of the Beehive', 'Cría cuervos'], $this->texts($page, '//li[contains(@class, "ev")]//h3'));
        $this->assertSame('/en/event/book-fair', $this->attribute($page, '//h3/a[text()="Book fair"]/@href'));
        $this->assertSame(0, $page->query('//meta[@name="robots"]')->length);
    }

    public function test_a_line_says_when_where_how_long_and_what_people_thought(): void
    {
        $page = $this->page($this->visit('/en/events'));

        $this->assertSame('All day', $this->text($page, '//li[contains(@class, "ev")]//p[@class="ev-when"]'));
        $this->assertSame('Motril · until ' . self::written(2, 'day'), $this->text($page, '//li[contains(@class, "ev")]//p[@class="ev-where"]'));
        $this->assertSame('Markets ★ 4 · 1 review', $this->text($page, '//li[contains(@class, "ev")]//p[@class="ev-meta"]'));
        $this->assertSame('20:00', $this->text($page, '//li[contains(@class, "ev")]//p[@class="ev-when"]', 1));
        $this->assertSame('Centro Cultural, Motril', $this->text($page, '//li[contains(@class, "ev")]//p[@class="ev-where"]', 1));
    }

    public function test_the_listing_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/eventos'));

        $this->assertSame('Agenda', $this->text($page, '//main//h1'));
        $this->assertSame('3 eventos', $this->text($page, '//p[@class="count"]'));
        $this->assertSame([self::written(0, language: Language::Es) . ' Hoy', self::written(8, language: Language::Es), self::written(12, language: Language::Es)], $this->texts($page, '//section[@class="day"]/h2'));
        $this->assertSame('Todo el día', $this->text($page, '//p[@class="ev-when"]'));
        $this->assertSame('Motril · hasta el ' . self::written(2, 'day', Language::Es), $this->text($page, '//p[@class="ev-where"]'));
        $this->assertSame('Mercados ★ 4 · 1 reseña', $this->text($page, '//p[@class="ev-meta"]'));
        $this->assertSame('/es/evento/book-fair', $this->attribute($page, '//h3/a[text()="Feria del libro"]/@href'));
    }

    public function test_the_form_names_its_fields_in_the_pages_language(): void
    {
        $spanish = $this->page($this->visit('/es/eventos'));
        $english = $this->page($this->visit('/en/events'));

        $this->assertSame(['q', 'desde', 'hasta', 'lugar', 'categoria'], $this->names($spanish));
        $this->assertSame(['q', 'from', 'to', 'place', 'category'], $this->names($english));
        $this->assertSame('/es/eventos', $this->attribute($spanish, '//form[@class="filters"]/@action'));
    }

    private function names(\DOMXPath $page): array
    {
        return array_map(fn (\DOMNode $field) => $field->getAttribute('name'), iterator_to_array($page->query('//form[@class="filters"]//*[@name]')));
    }

    public function test_the_places_are_the_regions_with_their_locations(): void
    {
        $page = $this->page($this->visit('/en/events'));

        $this->assertSame(['Anywhere', 'All of Granada', 'Almuñécar', 'Motril'], $this->texts($page, '//select[@id="f-place"]//option'));
        $this->assertSame(['', 'region:granada', 'almunecar', 'motril'], array_map(fn ($option) => $option->getAttribute('value'), iterator_to_array($page->query('//select[@id="f-place"]//option'))));
        $this->assertSame(['Anything', 'Film', 'Markets', 'Music'], array_slice($this->texts($page, '//select[@id="f-category"]//option'), 0, 4));
    }

    public function test_a_location_chosen_narrows_the_list_and_is_not_for_search_engines(): void
    {
        $response = $this->visit('/en/events', ['place' => 'motril']);
        $page = $this->page($response);

        $this->assertSame(['location' => 'motril', 'per_page' => 20], $this->asked('/events'));
        $this->assertSame('What\'s on in Motril', $this->text($page, '//main//h1'));
        $this->assertSame('motril', $this->attribute($page, '//select[@id="f-place"]//option[@selected]/@value'));
        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
    }

    public function test_a_region_is_asked_for_as_a_region(): void
    {
        $page = $this->page($this->visit('/en/events', ['place' => 'region:granada']));

        $this->assertSame(['region' => 'granada', 'per_page' => 20], $this->asked('/events'));
        $this->assertSame('What\'s on in Granada', $this->text($page, '//main//h1'));
    }

    public function test_a_category_narrows_the_list(): void
    {
        $page = $this->page($this->visit('/en/events', ['category' => 'mercados']));

        $this->assertSame(['category' => 'mercados', 'per_page' => 20], $this->asked('/events'));
        $this->assertSame(['Book fair'], $this->texts($page, '//h3'));
        $this->assertSame('mercados', $this->attribute($page, '//select[@id="f-category"]//option[@selected]/@value'));
    }

    public function test_the_other_languages_link_keeps_what_was_asked(): void
    {
        $this->api->instead('/events', SavedApi::answer('/events', ['per_page' => 20], Language::Es));

        $page = $this->page($this->visit('/es/eventos', ['lugar' => 'motril', 'categoria' => 'cine', 'desde' => '2026-11-01']));

        $this->assertSame('/en/events?from=2026-11-01&place=motril&category=cine', $this->attribute($page, '//a[@hreflang="en"]/@href'));
    }

    public function test_a_search_finds_by_words_best_match_first_one_by_one(): void
    {
        $response = $this->visit('/en/events', ['q' => 'market']);
        $page = $this->page($response);

        $this->assertSame(['q' => 'market', 'per_page' => 20], $this->asked('/events'));
        $this->assertSame('1 event · Found by “market”', $this->text($page, '//p[@class="count"]'));
        // Not grouped by day: each line has its own.
        $this->assertSame(0, $page->query('//section[@class="day"]')->length);
        $this->assertSame(self::written(0, 'short'), $this->text($page, '//p[@class="ev-when"]'));
        $this->assertSame('relevance', $this->attribute($page, '//select[@id="f-sort"]//option[@selected]/@value'));
        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
    }

    public function test_a_search_by_date_is_grouped_by_day(): void
    {
        $page = $this->page($this->visit('/en/events', ['q' => 'market', 'sort' => 'date']));

        $this->assertSame(['q' => 'market', 'sort' => 'date', 'per_page' => 20], $this->asked('/events'));
        $this->assertSame([self::written(0) . ' Today'], $this->texts($page, '//section[@class="day"]/h2'));
    }

    public function test_a_search_is_asked_afresh_every_time(): void
    {
        $this->visit('/en/events', ['q' => 'market']);
        $this->visit('/en/events', ['q' => 'market']);
        $this->visit('/en/events');
        $this->visit('/en/events');

        $this->assertCount(3, array_filter($this->api->asked, fn ($asked) => $asked['path'] === '/events'));
    }

    public function test_a_search_that_finds_nothing_says_so_and_offers_everything(): void
    {
        $page = $this->page($this->visit('/en/events', ['q' => 'zzzz']));

        $this->assertSame('Nothing was found for “zzzz”.', $this->text($page, '//p[@class="none"]'));
        $this->assertSame(0, $page->query('//p[@class="count"]')->length);
        $this->assertSame('/en/events', $this->attribute($page, '//a[text()="See everything coming up"]/@href'));
    }

    public function test_a_day_that_is_no_day_is_refused_beside_its_field_in_the_sites_words(): void
    {
        // The API's refusal is in English whatever the language; the page says it in its own.
        $refusal = SavedApi::answer('/events', ['from' => 'not-a-day', 'per_page' => 20], Language::En);
        $this->api->instead('/events', new Refused(422, $refusal['errors'], $refusal['message']));

        $response = $this->visit('/es/eventos', ['desde' => 'not-a-day']);
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('Eso no es un día desde el que buscar.', $this->text($page, '//p[@id="f-from-refused"]'));
        $this->assertSame('true', $this->attribute($page, '//input[@id="f-from"]/@aria-invalid'));
        $this->assertSame(0, $page->query('//p[@class="count"]')->length);
    }

    public function test_a_place_that_is_not_there_is_refused_beside_its_field(): void
    {
        $page = $this->page($this->visit('/en/events', ['place' => 'nowhere']));

        $this->assertSame("That place isn't on the list.", $this->text($page, '//p[@id="f-place-refused"]'));
        $this->assertSame("What's on", $this->text($page, '//main//h1'));
    }

    public function test_searching_too_often_is_said_over_the_form(): void
    {
        $this->api->instead('/events', new Refused(429, [], 'Too Many Attempts.'));

        $response = $this->visit('/en/events', ['q' => 'market']);

        $this->assertSame(200, $response->status);
        $this->assertSame("You've searched a lot in a short time. Please wait a minute and try again.", $this->text($this->page($response), '//p[@role="alert"]'));
    }

    public function test_a_price_a_photo_more_dates_and_a_cancellation(): void
    {
        $line = self::line();
        $line['price_from'] = 4.5;
        $line['status'] = 'cancelled';
        $line['photo'] = ['ref' => 'abcd1234', 'url' => 'https://events.example/storage/photos/fair.jpg', 'name' => 'Stalls in the square', 'description' => null, 'language' => 'en', 'approved' => true];
        $line['occurrences'][] = $line['occurrences'][0];
        $line['occurrences'][] = $line['occurrences'][0];
        $this->listing([$line]);

        $page = $this->page($this->visit('/en/events'));

        $this->assertSame('ev with-photo cancelled', $this->attribute($page, '//li[contains(@class, "ev")]/@class'));
        $this->assertSame('Cancelled From 4.50 € Markets ★ 4 · 1 review', $this->text($page, '//p[@class="ev-meta"]'));
        $this->assertSame('Motril · until ' . self::written(2, 'day') . ' · and 2 more dates', $this->text($page, '//p[@class="ev-where"]'));
        $this->assertSame('https://events.example/storage/photos/fair.jpg', $this->attribute($page, '//img[@class="ev-photo"]/@src'));
        $this->assertSame('Stalls in the square', $this->attribute($page, '//img[@class="ev-photo"]/@alt'));
    }

    public function test_a_price_in_spanish(): void
    {
        $line = self::line(Language::Es);
        $line['price_from'] = 12;
        $this->listing([$line], language: Language::Es);

        $this->assertSame('Desde 12 € Mercados ★ 4 · 1 reseña', $this->text($this->page($this->visit('/es/eventos')), '//p[@class="ev-meta"]'));
    }

    public function test_a_name_in_the_other_language_is_marked_as_such(): void
    {
        $line = self::line();
        $line['name'] = 'Feria del libro';
        $line['language'] = 'es';
        $this->listing([$line]);

        $this->assertSame('es', $this->attribute($this->page($this->visit('/en/events')), '//h3/a/@lang'));
    }

    public function test_what_an_event_says_is_written_as_text(): void
    {
        $line = self::line();
        $line['name'] = '<script>alert("hi")</script>';
        $line['location']['name'] = '<b>Motril</b>';
        $this->listing([$line]);

        $body = $this->visit('/en/events')->body;

        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt;', $body);
        $this->assertStringContainsString('&lt;b&gt;Motril&lt;/b&gt;', $body);
    }

    public function test_an_event_already_on_when_the_list_starts_is_under_its_first_day(): void
    {
        // The book fair began today; looked for from tomorrow it is on that day.
        $this->listing([self::line()]);

        $page = $this->page($this->visit('/en/events', ['from' => self::inDays(1)]));

        $this->assertSame([self::written(1)], $this->texts($page, '//section[@class="day"]/h2'));
    }

    public function test_pages_beyond_the_first(): void
    {
        $this->listing([self::line()], page: 2, pages: 3);

        $page = $this->page($this->visit('/en/events', ['place' => 'motril', 'page' => '2']));

        $this->assertSame('Page 2 of 3', $this->text($page, '//nav[@class="pager"]/span'));
        $this->assertSame('/en/events?place=motril', $this->attribute($page, '//a[@rel="prev"]/@href'));
        $this->assertSame('/en/events?place=motril&page=3', $this->attribute($page, '//a[@rel="next"]/@href'));
        $this->assertSame('41 events', $this->text($page, '//p[@class="count"]'));
    }

    public function test_one_page_has_no_pager(): void
    {
        $this->assertSame(0, $this->page($this->visit('/en/events'))->query('//nav[@class="pager"]')->length);
    }

    public function test_the_listings_page_two_is_still_for_search_engines(): void
    {
        $this->listing([self::line()], page: 2, pages: 2);

        $this->assertSame(0, $this->page($this->visit('/en/events', ['page' => '2']))->query('//meta[@name="robots"]')->length);
    }

    /** What the API was last asked at a path. */
    private function asked(string $path): array
    {
        $asked = array_values(array_filter($this->api->asked, fn ($asked) => $asked['path'] === $path));
        $this->assertNotEmpty($asked, "The API was not asked {$path}.");

        return end($asked)['query'];
    }
}
