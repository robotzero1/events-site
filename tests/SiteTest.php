<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use DOMDocument;
use DOMXPath;
use EventsSite\Api\Api;
use EventsSite\Api\Asking;
use EventsSite\Pages\Page;
use EventsSite\Pages\Shown;
use EventsSite\Route;
use EventsSite\Site;
use EventsSite\Standalone\Response;
use EventsSite\Standalone\Standalone;
use EventsSite\Cache\Kept;
use EventsSite\Tests\Support\ArrayCache;
use EventsSite\Tests\Support\FailingApi;
use EventsSite\Tests\Support\MemoryLog;
use EventsSite\Tests\Support\SavedApi;
use EventsSite\Visit;
use PHPUnit\Framework\TestCase;

/** The standalone site as a browser has it: an address in, a whole page out. */
final class SiteTest extends TestCase
{
    private Api $api;
    private ArrayCache $cache;
    private MemoryLog $log;

    protected function setUp(): void
    {
        $this->api = new SavedApi();
        $this->cache = new ArrayCache();
        $this->log = new MemoryLog();
    }

    /** @param array<string, Page>|null $pages */
    private function visit(string $path, array $query = [], ?string $acceptLanguage = null, string $visitor = '127.0.0.1', ?array $pages = null): Response
    {
        $site = new Standalone($this->api, $this->cache, $this->log, Standalone::templates(), 'https://events.example', $pages);

        return $site->respond(new Visit($path, $query, $acceptLanguage, $visitor));
    }

    private function xpath(Response $response): DOMXPath
    {
        $document = new DOMDocument();
        $this->assertTrue(@$document->loadHTML($response->body), 'The page is not HTML.');

        return new DOMXPath($document);
    }

    private function text(DOMXPath $page, string $query): string
    {
        $found = $page->query($query);
        $this->assertGreaterThan(0, $found->length, "Nothing at {$query}.");

        return trim($found->item(0)->textContent);
    }

    private function attribute(DOMXPath $page, string $query): string
    {
        $found = $page->query($query);
        $this->assertSame(1, $found->length, "Not one at {$query}.");

        return $found->item(0)->nodeValue;
    }

    /** A page that asks the API something, as every page but About will. */
    private function asking(): array
    {
        return array_replace(Site::pages(), ['about' => new class implements Page {
            public function show(Route $route, Asking $asking): Shown
            {
                $regions = $asking->get('/regions', [], Asking::REFERENCE_LIST);

                return new Shown('pages/about.twig', ['regions' => $regions], 'About');
            }
        }]);
    }

    public function test_the_bare_site_sends_a_browser_to_the_language_it_prefers(): void
    {
        $english = $this->visit('/', acceptLanguage: 'en-GB,en;q=0.9');
        $spanish = $this->visit('/', acceptLanguage: 'es-ES,es;q=0.9');
        $other = $this->visit('/', acceptLanguage: 'fr-FR');

        $this->assertSame([302, '/en/'], [$english->status, $english->headers['Location']]);
        $this->assertSame([302, '/es/'], [$spanish->status, $spanish->headers['Location']]);
        $this->assertSame([302, '/es/'], [$other->status, $other->headers['Location']]);
        $this->assertSame('Accept-Language', $english->headers['Vary']);
    }

    public function test_a_language_without_its_slash_is_sent_to_its_front_page(): void
    {
        $response = $this->visit('/en');

        $this->assertSame([301, '/en/'], [$response->status, $response->headers['Location']]);
    }

    public function test_the_about_page_in_english(): void
    {
        $response = $this->visit('/en/about');
        $page = $this->xpath($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('en', $this->attribute($page, '/html/@lang'));
        $this->assertSame("About this site · Costa Tropical · What's on", $this->text($page, '//title'));
        $this->assertSame('What this listing is and who makes it.', $this->attribute($page, '//meta[@name="description"]/@content'));
        $this->assertSame('About this site', $this->text($page, '//main//h1'));
        $this->assertSame('https://events.example/en/about', $this->attribute($page, '//link[@rel="canonical"]/@href'));
        $this->assertSame('https://events.example/es/acerca', $this->attribute($page, '//link[@rel="alternate"][@hreflang="es"]/@href'));
        $this->assertSame('https://events.example/en/about', $this->attribute($page, '//link[@rel="alternate"][@hreflang="en"]/@href'));
        $this->assertSame('https://events.example/', $this->attribute($page, '//link[@rel="alternate"][@hreflang="x-default"]/@href'));
    }

    public function test_the_about_page_in_spanish_links_to_the_english(): void
    {
        $response = $this->visit('/es/acerca');
        $page = $this->xpath($response);

        $this->assertSame('es', $this->attribute($page, '/html/@lang'));
        $this->assertSame('Acerca de esta web', $this->text($page, '//main//h1'));
        $this->assertSame('/en/about', $this->attribute($page, '//a[@hreflang="en"]/@href'));
        $this->assertSame('English', $this->text($page, '//a[@hreflang="en"]'));
        $this->assertSame('/es/', $this->attribute($page, '//a[@class="brand"]/@href'));
        $this->assertSame('es', $response->headers['Content-Language']);
    }

    public function test_everything_is_inside_one_class_for_a_theme_to_leave_alone(): void
    {
        $page = $this->xpath($this->visit('/en/about'));

        $this->assertSame(1, $page->query('/html/body/*')->length);
        $this->assertSame('events-site', $this->attribute($page, '/html/body/div/@class'));
    }

    public function test_an_unknown_address_is_not_found_in_its_language(): void
    {
        $spanish = $this->visit('/es/nada');
        $english = $this->visit('/en/nothing-here');
        $neither = $this->visit('/fr/rien', acceptLanguage: 'en');

        $this->assertSame(404, $spanish->status);
        $this->assertSame('No encontrado', $this->text($this->xpath($spanish), '//main//h1'));
        $this->assertSame(404, $english->status);
        $this->assertSame('Not found', $this->text($this->xpath($english), '//main//h1'));
        $this->assertSame('Not found', $this->text($this->xpath($neither), '//main//h1'));
    }

    public function test_a_not_found_page_is_not_indexed_and_offers_the_other_languages_front_page(): void
    {
        $page = $this->xpath($this->visit('/en/nothing-here'));

        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
        $this->assertSame(0, $page->query('//link[@rel="canonical"]')->length);
        $this->assertSame('/es/', $this->attribute($page, '//a[@hreflang="es"]/@href'));
    }

    public function test_a_page_not_built_yet_is_not_found(): void
    {
        $this->assertSame(404, $this->visit('/en/events')->status);
    }

    public function test_a_page_asks_the_api_with_the_visitors_address_and_its_language(): void
    {
        $this->api = $saved = new SavedApi();
        $this->visit('/es/acerca', visitor: '203.0.113.7', pages: $this->asking());

        $this->assertSame('/regions', $saved->asked[0]['path']);
        $this->assertSame('es', $saved->asked[0]['language']->value);
        $this->assertSame('203.0.113.7', $saved->asked[0]['visitor']);
    }

    public function test_when_the_api_fails_the_page_is_made_from_the_answer_kept(): void
    {
        $this->visit('/en/about', pages: $this->asking());

        // Every answer kept is now past its time, and the API fails.
        foreach ($this->cache->kept as $key => $kept) {
            $this->cache->kept[$key] = new Kept($kept->answer, time() - 1, time() + 3600);
        }

        $this->api = new FailingApi();

        $response = $this->visit('/en/about', pages: $this->asking());

        $this->assertSame(200, $response->status);
        $this->assertSame('About this site', $this->text($this->xpath($response), '//main//h1'));
        $this->assertCount(1, $this->log->failures);
    }

    public function test_when_the_api_fails_with_nothing_kept_the_page_is_unavailable(): void
    {
        $this->api = new FailingApi();

        $response = $this->visit('/es/acerca', pages: $this->asking());
        $page = $this->xpath($response);

        $this->assertSame(503, $response->status);
        $this->assertSame('300', $response->headers['Retry-After']);
        $this->assertSame('Ahora mismo no se pueden mostrar los eventos', $this->text($page, '//main//h1'));
        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
        $this->assertCount(1, $this->log->failures);
    }

    public function test_a_page_whose_thing_the_api_does_not_have_is_not_found(): void
    {
        $pages = Site::pages() + ['event' => new class implements Page {
            public function show(Route $route, Asking $asking): Shown
            {
                $asking->get("/events/{$route->ref}", [], Asking::LISTING);

                throw new \LogicException('It was there.');
            }
        }];

        $response = $this->visit('/en/event/no-such-event', pages: $pages);

        $this->assertSame(404, $response->status);
        $this->assertSame('Not found', $this->text($this->xpath($response), '//main//h1'));
    }
}
