<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\TestCase;

/**
 * What search engines and link previews read: an event's schema.org data, every page's Open Graph tags, the sitemap
 * and robots.txt. Against the known state: the book fair in Motril, on all day from today to two days on, and a film
 * at the Centro Cultural eight days on at eight.
 */
final class SearchEnginesTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    /** @return list<array<string, mixed>> the schema.org data in a page */
    private function structured(string $body): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $found), 'The page has no schema.org data.');

        return json_decode($found[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_an_all_day_event_as_schema_org_has_it(): void
    {
        $events = $this->structured($this->visit('/en/event/book-fair')->body);

        $this->assertCount(1, $events);
        $this->assertSame([
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => 'Book fair',
            'description' => 'New and second-hand books under the arcades, with readings in the evening.',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'location' => [
                '@type' => 'Place',
                'name' => 'Motril',
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => 'Motril', 'addressRegion' => 'Granada', 'addressCountry' => 'ES'],
            ],
            'startDate' => self::inDays(0),
            'endDate' => self::inDays(2),
            'eventStatus' => 'https://schema.org/EventScheduled',
        ], $events[0]);
    }

    public function test_a_timed_event_at_a_venue_has_its_offset_in_spain(): void
    {
        $event = $this->structured($this->visit('/en/event/el-espiritu-de-la-colmena')->body)[0];
        $offset = (new \DateTimeImmutable(self::inDays(8) . ' 20:00', new \DateTimeZone('Europe/Madrid')))->format('P');

        $this->assertSame(self::inDays(8) . "T20:00:00{$offset}", $event['startDate']);
        $this->assertArrayNotHasKey('endDate', $event);
        $this->assertSame('Centro Cultural', $event['location']['name']);
    }

    public function test_everything_an_event_can_say(): void
    {
        $fair = SavedApi::answer('/events/book-fair', [], Language::En);
        $fair['data']['price_from'] = 4.5;
        $fair['data']['organiser'] = ['ref' => 'pablo-ortega', 'name' => 'Pablo Ortega'];
        $fair['data']['photos'] = [['ref' => 'abcd1234', 'url' => 'https://events.example/storage/photos/a.jpg', 'name' => 'Stalls', 'description' => null, 'language' => 'en', 'approved' => true]];
        $second = $fair['data']['occurrences'][0];
        $second['starts_at'] = self::inDays(9) . 'T18:00';
        $second['finishes_at'] = self::inDays(9) . 'T21:30';
        $second['all_day'] = false;
        $second['cancelled'] = true;
        $fair['data']['occurrences'][] = $second;
        $fair['data']['venue'] = ['ref' => 'centro-cultural', 'name' => 'Centro Cultural', 'description' => null, 'language' => 'es', 'status' => 'published'];
        $this->api->instead('/events/book-fair', $fair);
        $venue = SavedApi::answer('/venues/centro-cultural', [], Language::En);
        $venue['data'] += ['address' => 'Calle José Felipe Soto, 1'];
        $venue['data']['lat'] = 36.7465;
        $venue['data']['lng'] = -3.5179;
        $this->api->instead('/venues/centro-cultural', $venue);

        $events = $this->structured($this->visit('/en/event/book-fair')->body);

        $this->assertCount(2, $events);
        $this->assertSame(['@type' => 'Offer', 'price' => '4.50', 'priceCurrency' => 'EUR'], $events[0]['offers']);
        $this->assertSame(['@type' => 'Organization', 'name' => 'Pablo Ortega'], $events[0]['organizer']);
        $this->assertSame(['https://events.example/storage/photos/a.jpg'], $events[0]['image']);
        $this->assertSame('Calle José Felipe Soto, 1', $events[0]['location']['address']['streetAddress']);
        $this->assertSame(['@type' => 'GeoCoordinates', 'latitude' => 36.7465, 'longitude' => -3.5179], $events[0]['location']['geo']);
        $this->assertSame('https://schema.org/EventScheduled', $events[0]['eventStatus']);
        $this->assertSame('https://schema.org/EventCancelled', $events[1]['eventStatus']);
        $this->assertStringStartsWith(self::inDays(9) . 'T21:30:00', $events[1]['endDate']);
    }

    public function test_a_cancelled_event_is_cancelled_at_every_time(): void
    {
        $fair = SavedApi::answer('/events/book-fair', [], Language::En);
        $fair['data']['status'] = 'cancelled';
        $this->api->instead('/events/book-fair', $fair);

        $this->assertSame('https://schema.org/EventCancelled', $this->structured($this->visit('/en/event/book-fair')->body)[0]['eventStatus']);
    }

    public function test_nothing_an_event_says_can_close_the_script_it_is_in(): void
    {
        $fair = SavedApi::answer('/events/book-fair', [], Language::En);
        $fair['data']['name'] = '</script><script>alert(1)</script>';
        $this->api->instead('/events/book-fair', $fair);

        $body = $this->visit('/en/event/book-fair')->body;

        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertSame('</script><script>alert(1)</script>', $this->structured($body)[0]['name']);
    }

    public function test_only_an_events_page_has_schema_org_data(): void
    {
        $this->assertStringNotContainsString('application/ld+json', $this->visit('/en/events')->body);
        $this->assertStringNotContainsString('application/ld+json', $this->visit('/en/venue/centro-cultural')->body);
    }

    public function test_a_shared_event_shows_its_name_description_and_picture(): void
    {
        $fair = SavedApi::answer('/events/book-fair', [], Language::En);
        $fair['data']['photos'] = [['ref' => 'abcd1234', 'url' => 'https://events.example/storage/photos/a.jpg', 'name' => 'Stalls', 'description' => null, 'language' => 'en', 'approved' => true]];
        $this->api->instead('/events/book-fair', $fair);

        $page = $this->page($this->visit('/en/event/book-fair'));

        $this->assertSame('Book fair', $this->attribute($page, '//meta[@property="og:title"]/@content'));
        $this->assertSame('New and second-hand books under the arcades, with readings in the evening.', $this->attribute($page, '//meta[@property="og:description"]/@content'));
        $this->assertSame('https://events.example/storage/photos/a.jpg', $this->attribute($page, '//meta[@property="og:image"]/@content'));
        $this->assertSame('https://events.example/en/event/book-fair', $this->attribute($page, '//meta[@property="og:url"]/@content'));
        $this->assertSame('en_GB', $this->attribute($page, '//meta[@property="og:locale"]/@content'));
        $this->assertSame('es_ES', $this->attribute($page, '//meta[@property="og:locale:alternate"]/@content'));
        $this->assertSame('summary_large_image', $this->attribute($page, '//meta[@name="twitter:card"]/@content'));
    }

    public function test_every_page_has_a_preview_and_one_with_no_picture_a_small_one(): void
    {
        $page = $this->page($this->visit('/es/'));

        $this->assertSame('Qué hay en la costa', $this->attribute($page, '//meta[@property="og:title"]/@content'));
        $this->assertSame('Costa Tropical · Agenda', $this->attribute($page, '//meta[@property="og:site_name"]/@content'));
        $this->assertSame('es_ES', $this->attribute($page, '//meta[@property="og:locale"]/@content'));
        $this->assertSame('https://events.example/es/', $this->attribute($page, '//meta[@property="og:url"]/@content'));
        $this->assertSame(0, $page->query('//meta[@property="og:image"]')->length);
        $this->assertSame('summary', $this->attribute($page, '//meta[@name="twitter:card"]/@content'));
    }

    public function test_the_sitemap_lists_every_page_in_both_languages(): void
    {
        $response = $this->visit('/sitemap.xml');

        $this->assertSame(200, $response->status);
        $this->assertSame('application/xml; charset=utf-8', $response->headers['Content-Type']);

        $sitemap = new \DOMDocument();
        $this->assertTrue($sitemap->loadXML($response->body));
        $xpath = new \DOMXPath($sitemap);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        $xpath->registerNamespace('x', 'http://www.w3.org/1999/xhtml');
        $addresses = array_map(fn ($loc) => $loc->textContent, iterator_to_array($xpath->query('//s:url/s:loc')));

        foreach ([
            '/es/', '/en/', '/es/eventos', '/en/events', '/es/sugerir', '/en/suggest', '/es/acerca', '/en/about',
            '/es/eventos-en/motril', '/en/events-in/almunecar', '/es/categoria/cine', '/en/category/mercados',
            '/es/lugar/centro-cultural', '/en/venue/centro-cultural',
            '/es/evento/book-fair', '/en/event/book-fair', '/es/evento/cria-cuervos', '/en/event/el-espiritu-de-la-colmena',
        ] as $path) {
            $this->assertContains('https://events.example' . $path, $addresses);
        }

        $this->assertSame(count(array_unique($addresses)), count($addresses));
        // Each names itself and its other language.
        $this->assertSame(
            ['es' => 'https://events.example/es/evento/book-fair', 'en' => 'https://events.example/en/event/book-fair'],
            array_column(array_map(fn ($link) => ['code' => $link->getAttribute('hreflang'), 'href' => $link->getAttribute('href')], iterator_to_array($xpath->query('//s:url[s:loc="https://events.example/en/event/book-fair"]/x:link'))), 'href', 'code'),
        );
    }

    public function test_the_sitemap_follows_the_events_pages(): void
    {
        $first = SavedApi::answer('/events', ['per_page' => 100, 'page' => 1], Language::Es);
        $first['meta']['last_page'] = 2;
        $this->api->instead('/events', $first);

        $this->visit('/sitemap.xml');

        $this->assertSame([1, 2], array_values(array_map(fn ($asked) => $asked['query']['page'], array_filter($this->api->asked, fn ($asked) => $asked['path'] === '/events'))));
    }

    public function test_robots_may_read_every_page_and_are_told_of_the_sitemap(): void
    {
        $response = $this->visit('/robots.txt');

        $this->assertSame('text/plain; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame("User-agent: *\nDisallow: /api/\nDisallow: /admin/\n\nSitemap: https://events.example/sitemap.xml\n", $response->body);
    }
}
