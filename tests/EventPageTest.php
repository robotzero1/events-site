<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\TestCase;

/**
 * An event's own page, against the known state: the book fair, on from today to two days on, in Motril, with a
 * Review by Marta Gil and a Comment by Teo Blanco; and a film at the Centro Cultural eight days on, read from the
 * film club's website.
 */
final class EventPageTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    /** The book fair's own answer, to be changed for what the known state cannot show. */
    private static function fair(Language $language = Language::En): array
    {
        return SavedApi::answer('/events/book-fair', [], $language);
    }

    public function test_an_event_in_full(): void
    {
        $response = $this->visit('/en/event/book-fair');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('Book fair', $this->text($page, '//main//h1'));
        $this->assertSame('Book fair · Costa Tropical · What\'s on', $this->text($page, '//title'));
        $this->assertSame('New and second-hand books under the arcades, with readings in the evening.', $this->text($page, '//div[@class="description"]/p'));
        $this->assertSame('New and second-hand books under the arcades, with readings in the evening.', $this->attribute($page, '//meta[@name="description"]/@content'));
        $this->assertSame([self::written(0) . ' – ' . self::written(2, 'day')], $this->texts($page, '//ul[@class="times"]/li'));
        $this->assertSame('Motril (Granada)', $this->text($page, '//aside//dt[text()="Where"]/following-sibling::dd'));
        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//aside//a[text()="Motril"]/@href'));
        $this->assertSame('/en/category/mercados', $this->attribute($page, '//aside//a[text()="Markets"]/@href'));
        $this->assertSame('https://events.example/es/evento/book-fair', $this->attribute($page, '//link[@hreflang="es"]/@href'));
        $this->assertSame(0, $page->query('//p[@class="status"]')->length);
        $this->assertSame(0, $page->query('//p[@class="more"]')->length);
    }

    public function test_its_reviews_and_comments_by_name_and_day(): void
    {
        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('4 ★ from 1 review', $this->text($page, '//p[@class="average"]'));
        $this->assertSame('Worth the trip', $this->text($page, '//li[@class="review"]/h3'));
        $this->assertSame('4 of 5 · Marta Gil · ' . self::written(0, 'day'), $this->text($page, '//li[@class="review"]/p[@class="byline"]'));
        $this->assertSame('More stalls than last year, and the readings were a nice touch.', $this->text($page, '//li[@class="review"]/p[@class="words"]'));
        $this->assertSame('Teo Blanco · ' . self::written(0, 'day'), $this->text($page, '//li[@class="comment"]/p[@class="byline"]'));
        $this->assertSame('Is there anywhere to park near the arcades?', $this->text($page, '//li[@class="comment"]/p[@class="words"]'));
    }

    public function test_an_event_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/evento/book-fair'));

        $this->assertSame('Feria del libro', $this->text($page, '//main//h1'));
        $this->assertSame([self::written(0, language: Language::Es) . ' – ' . self::written(2, 'day', Language::Es)], $this->texts($page, '//ul[@class="times"]/li'));
        $this->assertSame('4 de 5 · Marta Gil · ' . self::written(0, 'day', Language::Es), $this->text($page, '//li[@class="review"]/p[@class="byline"]'));
        $this->assertSame('/es/eventos-en/motril', $this->attribute($page, '//aside//a[text()="Motril"]/@href'));
    }

    public function test_an_event_at_a_venue_read_from_another_website(): void
    {
        $page = $this->page($this->visit('/en/event/el-espiritu-de-la-colmena'));

        $this->assertSame('/en/venue/centro-cultural', $this->attribute($page, '//aside//a[text()="Centro Cultural"]/@href'));
        $this->assertSame([self::written(8) . ', 20:00'], $this->texts($page, '//ul[@class="times"]/li'));
        $this->assertSame('https://cineclub.example/Pelicula/el-espiritu-de-la-colmena/', $this->attribute($page, '//p[@class="more"]/a/@href'));
        $this->assertSame('No one has reviewed it yet.', $this->text($page, '//section[@class="reviews"]/p'));
        $this->assertSame(0, $page->query('//section[@class="comments"]')->length);
        // The Venue has no coordinates, so there is no map to send anyone to.
        $this->assertSame(0, $page->query('//a[text()="See it on a map"]')->length);
    }

    public function test_a_venue_with_an_address_and_coordinates_is_found_on_a_map(): void
    {
        $venue = SavedApi::answer('/venues/centro-cultural', [], Language::En);
        $venue['data'] += ['address' => 'Calle José Felipe Soto, 1'];
        $venue['data']['lat'] = 36.7465;
        $venue['data']['lng'] = -3.5179;
        $this->api->instead('/venues/centro-cultural', $venue);

        $page = $this->page($this->visit('/en/event/el-espiritu-de-la-colmena'));

        $this->assertStringContainsString('Calle José Felipe Soto, 1', $this->text($page, '//aside//dt[text()="Where"]/following-sibling::dd'));
        $this->assertSame('https://www.openstreetmap.org/?mlat=36.7465&mlon=-3.5179#map=17/36.7465/-3.5179', $this->attribute($page, '//a[text()="See it on a map"]/@href'));
    }

    public function test_an_event_whose_times_are_over_says_it_has_ended_and_where_to_look(): void
    {
        $this->day = self::inDays(3);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('This event has ended.', $this->text($page, '//p[@class="status"]'));
        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//p[@class="coming"]/a/@href'));
        $this->assertSame("See what's coming up in Motril", $this->text($page, '//p[@class="coming"]/a'));
        // Its Reviews are still there.
        $this->assertSame('Worth the trip', $this->text($page, '//li[@class="review"]/h3'));
    }

    public function test_on_its_last_day_it_has_not_ended(): void
    {
        $this->day = self::inDays(2);
        $this->time = '23:30';

        $this->assertSame(0, $this->page($this->visit('/en/event/book-fair'))->query('//p[@class="status"]')->length);
    }

    public function test_an_ended_event_at_a_venue_leads_to_the_venue(): void
    {
        $this->day = self::inDays(9);

        $page = $this->page($this->visit('/en/event/el-espiritu-de-la-colmena'));

        $this->assertSame('/en/venue/centro-cultural', $this->attribute($page, '//p[@class="coming"]/a/@href'));
    }

    public function test_a_cancelled_event_and_a_cancelled_time(): void
    {
        $fair = self::fair();
        $fair['data']['status'] = 'cancelled';
        $fair['data']['occurrences'][0]['cancelled'] = true;
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('This event was cancelled.', $this->text($page, '//p[@class="status"]'));
        $this->assertSame('cancelled', $this->attribute($page, '//ul[@class="times"]/li/@class'));
        $this->assertStringEndsWith('Cancelled', $this->text($page, '//ul[@class="times"]/li'));
    }

    public function test_its_photos_with_their_descriptions_and_captions(): void
    {
        $fair = self::fair();
        $fair['data']['photos'] = [
            ['ref' => 'abcd1234', 'url' => 'https://events.example/storage/photos/a.jpg', 'name' => 'Stalls under the arcades', 'description' => 'The first evening.', 'language' => 'en', 'approved' => true],
            ['ref' => 'efgh5678', 'url' => 'https://events.example/storage/photos/b.jpg', 'name' => null, 'description' => null, 'language' => null, 'approved' => true],
        ];
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame(['Stalls under the arcades', ''], array_map(fn ($img) => $img->getAttribute('alt'), iterator_to_array($page->query('//div[@class="gallery"]//img'))));
        $this->assertSame(['The first evening.'], $this->texts($page, '//div[@class="gallery"]//figcaption'));
    }

    public function test_its_price_and_tags_and_organiser(): void
    {
        $fair = self::fair();
        $fair['data']['price_from'] = 5;
        $fair['data']['tags'] = ['libros', 'lectura'];
        $fair['data']['organiser'] = ['ref' => 'pablo-ortega', 'name' => 'Pablo Ortega'];
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('From 5 €', $this->text($page, '//aside//dt[text()="Price"]/following-sibling::dd'));
        $this->assertSame(['libros', 'lectura'], $this->texts($page, '//ul[@class="taglist"]/li'));
        $this->assertSame('Pablo Ortega', $this->text($page, '//aside//dt[text()="Organised by"]/following-sibling::dd'));
    }

    public function test_a_text_in_the_other_language_says_so(): void
    {
        $fair = self::fair();
        $fair['data']['name'] = 'Feria del libro';
        $fair['data']['language'] = 'es';
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('There is no English text for this event yet, so it is shown in Spanish.', $this->text($page, '//p[@class="in-other"]'));
        $this->assertSame('es', $this->attribute($page, '//div[@class="event-head"]/@lang'));
        $this->assertSame('es', $this->attribute($page, '//div[@class="description"]/@lang'));
    }

    public function test_a_description_keeps_its_paragraphs_and_lines(): void
    {
        $fair = self::fair();
        $fair['data']['description'] = "First paragraph.\n\nSecond, with\na line of its own.";
        $this->api->instead('/events/book-fair', $fair);

        $body = $this->visit('/en/event/book-fair')->body;

        $this->assertStringContainsString('<p>First paragraph.</p><p>Second, with<br />' . "\n" . 'a line of its own.</p>', $body);
    }

    public function test_what_people_wrote_is_text(): void
    {
        $reviews = SavedApi::answer('/events/book-fair/reviews', [], Language::En);
        $reviews['data'][0]['title'] = '<img src=x onerror=alert(1)>';
        $reviews['data'][0]['content'] = '<script>alert("hi")</script>';
        $reviews['data'][0]['author']['name'] = '<b>Marta</b>';
        $this->api->instead('/events/book-fair/reviews', $reviews);

        $body = $this->visit('/en/event/book-fair')->body;

        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringNotContainsString('<img src=x', $body);
        $this->assertStringContainsString('&lt;b&gt;Marta&lt;/b&gt;', $body);
    }

    public function test_more_reviews_than_one_page_says_how_many(): void
    {
        $reviews = SavedApi::answer('/events/book-fair/reviews', [], Language::En);
        $reviews['meta']['total'] = 60;
        $this->api->instead('/events/book-fair/reviews', $reviews);

        $this->assertStringContainsString('These are the newest 1 of 60 reviews.', $this->text($this->page($this->visit('/en/event/book-fair')), '//section[@class="reviews"]'));
    }

    public function test_earlier_years_reviews_under_their_year(): void
    {
        $fair = self::fair();
        $review = SavedApi::answer('/events/book-fair/reviews', [], Language::En)['data'][0];
        $fair['data']['earlier_editions'] = [
            ['ref' => 'book-fair-2025', 'year' => 2025, 'reviews' => [$review]],
            ['ref' => 'book-fair-2024', 'year' => 2024, 'reviews' => []],
        ];
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame(['2025'], $this->texts($page, '//section[@class="earlier"]/h3'));
        $this->assertSame('Worth the trip', $this->text($page, '//section[@class="earlier"]//li[@class="review"]/h4'));
    }

    public function test_a_source_address_that_is_not_a_web_page_is_not_linked(): void
    {
        $fair = self::fair();
        $fair['data']['source_url'] = 'javascript:alert(1)';
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame(0, $page->query('//p[@class="more"]')->length);
    }

    public function test_an_event_that_is_not_there_is_not_found(): void
    {
        $response = $this->visit('/en/event/no-such-event');

        $this->assertSame(404, $response->status);
        $this->assertSame('Not found', $this->text($this->page($response), '//main//h1'));
    }
}
