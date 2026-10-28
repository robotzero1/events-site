<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\TestCase;

/**
 * Each Location's and each Category's own page, against the known state: Motril, where the book fair and two films
 * are, and Film, which the two films are.
 */
final class PlacePagesTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    /** What the API was last asked for events. */
    private function asked(): array
    {
        $asked = array_values(array_filter($this->api->asked, fn ($asked) => $asked['path'] === '/events'));
        $this->assertNotEmpty($asked, 'The API was not asked for events.');

        return end($asked)['query'];
    }

    public function test_a_locations_page(): void
    {
        $response = $this->visit('/en/events-in/motril');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame(['location' => 'motril', 'per_page' => 20], $this->asked());
        $this->assertSame("What's on in Motril", $this->text($page, '//main//h1'));
        $this->assertSame("What's on in Motril · Costa Tropical · What's on", $this->text($page, '//title'));
        $this->assertSame('Concerts, markets, fiestas, films and more in Motril, day by day.', $this->attribute($page, '//meta[@name="description"]/@content'));
        $this->assertSame('3 events', $this->text($page, '//p[@class="count"]'));
        // It is the page for search engines to keep.
        $this->assertSame(0, $page->query('//meta[@name="robots"]')->length);
        $this->assertSame('https://events.example/en/events-in/motril', $this->attribute($page, '//link[@rel="canonical"]/@href'));
    }

    public function test_its_form_leaves_out_the_place_and_leads_back_to_it(): void
    {
        $page = $this->page($this->visit('/en/events-in/motril'));

        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//form[@class="filters"]/@action'));
        $this->assertSame(0, $page->query('//select[@id="f-place"]')->length);
        $this->assertSame(1, $page->query('//select[@id="f-category"]')->length);
    }

    public function test_a_locations_page_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/eventos-en/motril'));

        $this->assertSame('Qué hay en Motril', $this->text($page, '//main//h1'));
        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//a[@hreflang="en"]/@href'));
    }

    public function test_a_place_chosen_in_the_address_does_not_move_it(): void
    {
        $this->visit('/en/events-in/motril', ['place' => 'almunecar']);

        $this->assertSame(['location' => 'motril', 'per_page' => 20], $this->asked());
    }

    public function test_narrowed_further_it_is_no_longer_for_search_engines(): void
    {
        $this->api->instead('/events', SavedApi::answer('/events', ['category' => 'cine', 'per_page' => 20], Language::En));

        $page = $this->page($this->visit('/en/events-in/motril', ['category' => 'cine']));

        $this->assertSame(['category' => 'cine', 'location' => 'motril', 'per_page' => 20], $this->asked());
        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
        $this->assertSame('cine', $this->attribute($page, '//select[@id="f-category"]//option[@selected]/@value'));
    }

    public function test_its_pages_stay_on_it(): void
    {
        $answer = SavedApi::answer('/events', ['location' => 'motril', 'per_page' => 20], Language::En);
        $answer['meta'] = ['current_page' => 2, 'last_page' => 3, 'total' => 43] + $answer['meta'];
        $this->api->instead('/events', $answer);

        $page = $this->page($this->visit('/en/events-in/motril', ['page' => '2']));

        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//a[@rel="prev"]/@href'));
        $this->assertSame('/en/events-in/motril?page=3', $this->attribute($page, '//a[@rel="next"]/@href'));
    }

    public function test_a_categorys_page(): void
    {
        $page = $this->page($this->visit('/en/category/cine'));

        $this->assertSame(['category' => 'cine', 'per_page' => 20], $this->asked());
        $this->assertSame('Film', $this->text($page, '//main//h1'));
        $this->assertSame("Film: what's on along the coast · Costa Tropical · What's on", $this->text($page, '//title'));
        $this->assertSame('Film from Almuñécar to Motril, day by day.', $this->attribute($page, '//meta[@name="description"]/@content'));
        $this->assertSame(['The Spirit of the Beehive', 'Cría cuervos'], $this->texts($page, '//h3'));
        $this->assertSame(0, $page->query('//select[@id="f-category"]')->length);
        $this->assertSame(1, $page->query('//select[@id="f-place"]')->length);
        $this->assertSame('/en/category/cine', $this->attribute($page, '//form[@class="filters"]/@action'));
    }

    public function test_a_categorys_page_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/categoria/cine'));

        $this->assertSame('Cine', $this->text($page, '//main//h1'));
        $this->assertSame('Cine: qué hay en la costa · Costa Tropical · Agenda', $this->text($page, '//title'));
    }

    public function test_a_location_or_category_that_is_not_there_is_not_found(): void
    {
        $this->assertSame(404, $this->visit('/en/events-in/nowhere')->status);
        $this->assertSame(404, $this->visit('/en/category/nothing')->status);
        // Nor is a Region one: it has no page of its own.
        $this->assertSame(404, $this->visit('/en/events-in/granada')->status);
        $this->assertSame([], array_filter($this->api->asked, fn ($asked) => $asked['path'] === '/events'));
    }

    public function test_the_listings_lines_lead_to_their_venue_location_and_category(): void
    {
        $page = $this->page($this->visit('/en/events'));

        $this->assertSame('/en/events-in/motril', $this->attribute($page, '(//p[@class="ev-where"])[1]/a/@href'));
        $this->assertSame('/en/venue/centro-cultural', $this->attribute($page, '(//p[@class="ev-where"])[2]/a[1]/@href'));
        $this->assertSame('/en/category/cine', $this->attribute($page, '(//p[@class="ev-meta"])[2]//a/@href'));
    }
}
