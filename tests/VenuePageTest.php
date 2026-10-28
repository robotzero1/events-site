<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Language;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\TestCase;

/**
 * A Venue's page, against the known state: the Centro Cultural in Motril, a hall written in Spanish only, with
 * nothing but its name, type and Location, and two films coming up, eight and twelve days on.
 */
final class VenuePageTest extends TestCase
{
    use Browsing;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
    }

    /** The Venue with everything a Venue can have, from its saved answer. */
    private function everything(): void
    {
        $venue = SavedApi::answer('/venues/centro-cultural', [], Language::En);
        $venue['data'] = array_replace($venue['data'], [
            'description' => "The town's cultural centre.\n\nFilms on Wednesdays and Sundays.",
            'address' => 'Calle José Felipe Soto, 1',
            'phone' => '958 60 00 00',
            'email' => 'centro@example.com',
            'website' => 'https://www.centro.example/',
            'lat' => 36.7465,
            'lng' => -3.5179,
            'social_links' => [
                ['platform' => 'Instagram', 'url' => 'https://instagram.example/centro'],
                ['platform' => '', 'url' => 'https://x.example/centro'],
                ['platform' => 'Not a web page', 'url' => 'javascript:alert(1)'],
            ],
            'photos' => [
                ['ref' => 'abcd1234', 'url' => 'https://events.example/storage/photos/hall.jpg', 'name' => 'The hall from the street', 'description' => null, 'language' => 'en', 'approved' => true],
            ],
        ]);
        $this->api->instead('/venues/centro-cultural', $venue);
    }

    public function test_a_venue_with_what_is_coming_up_there_by_day(): void
    {
        $response = $this->visit('/en/venue/centro-cultural');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('Centro Cultural', $this->text($page, '//main//h1'));
        $this->assertSame('Hall · Motril (Granada)', $this->text($page, '//p[@class="kind"]'));
        $this->assertSame([self::written(8), self::written(12)], $this->texts($page, '//section[@class="coming-up"]//section[@class="day"]/h3'));
        $this->assertSame(['The Spirit of the Beehive', 'Cría cuervos'], $this->texts($page, '//section[@class="coming-up"]//h4'));
        $this->assertSame('/en/event/el-espiritu-de-la-colmena', $this->attribute($page, '//h4/a[text()="The Spirit of the Beehive"]/@href'));
        // Its own events say nothing of where they are: it is here.
        $this->assertSame(0, $page->query('//p[@class="ev-where"]')->length);
        $this->assertSame('Hall in Motril', $this->attribute($page, '//meta[@name="description"]/@content'));
    }

    public function test_a_venue_with_little_leaves_out_what_it_has_not(): void
    {
        $page = $this->page($this->visit('/en/venue/centro-cultural'));

        $this->assertSame(['Where'], $this->texts($page, '//aside//dt'));
        $this->assertSame('Motril (Granada)', $this->text($page, '//aside//dd'));
        $this->assertSame(0, $page->query('//div[@class="gallery"]')->length);
        $this->assertSame(0, $page->query('//div[@class="description"]')->length);
    }

    public function test_a_name_in_the_other_language_is_marked_but_not_remarked_on(): void
    {
        $page = $this->page($this->visit('/en/venue/centro-cultural'));

        $this->assertSame('es', $this->attribute($page, '//div[@class="event-head"]/@lang'));
        $this->assertSame(0, $page->query('//p[@class="in-other"]')->length);
    }

    public function test_a_description_in_the_other_language_says_so(): void
    {
        $venue = SavedApi::answer('/venues/centro-cultural', [], Language::En);
        $venue['data']['description'] = 'El centro cultural del pueblo.';
        $this->api->instead('/venues/centro-cultural', $venue);

        $page = $this->page($this->visit('/en/venue/centro-cultural'));

        $this->assertSame('There is no English text for this place yet, so it is shown in Spanish.', $this->text($page, '//p[@class="in-other"]'));
        $this->assertSame('es', $this->attribute($page, '//div[@class="description"]/@lang'));
    }

    public function test_a_venue_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/lugar/centro-cultural'));

        $this->assertSame([self::written(8, language: Language::Es), self::written(12, language: Language::Es)], $this->texts($page, '//section[@class="day"]/h3'));
        $this->assertSame('Próximamente aquí', $this->text($page, '//h2[@id="coming-up"]'));
        $this->assertSame(0, $page->query('//p[@class="in-other"]')->length);
        $this->assertSame('/en/venue/centro-cultural', $this->attribute($page, '//a[@hreflang="en"]/@href'));
    }

    public function test_a_venue_with_everything(): void
    {
        $this->everything();

        $page = $this->page($this->visit('/en/venue/centro-cultural'));

        $this->assertSame(['Where', 'Get in touch', 'Elsewhere'], $this->texts($page, '//aside//dt'));
        $this->assertStringStartsWith('Calle José Felipe Soto, 1', $this->text($page, '//aside//dd'));
        $this->assertSame('https://www.openstreetmap.org/?mlat=36.7465&mlon=-3.5179#map=17/36.7465/-3.5179', $this->attribute($page, '//a[text()="See it on a map"]/@href'));
        $this->assertSame('tel:958600000', $this->attribute($page, '//a[text()="958 60 00 00"]/@href'));
        $this->assertSame('mailto:centro@example.com', $this->attribute($page, '//a[text()="centro@example.com"]/@href'));
        $this->assertSame('https://www.centro.example/', $this->attribute($page, '//a[text()="centro.example"]/@href'));
        $this->assertSame(['Instagram', 'x.example/centro'], $this->texts($page, '//aside//dt[text()="Elsewhere"]/following-sibling::dd//a'));
        $this->assertSame('The hall from the street', $this->attribute($page, '//div[@class="gallery"]//img/@alt'));
        $this->assertSame(["The town's cultural centre.", 'Films on Wednesdays and Sundays.'], $this->texts($page, '//div[@class="description"]/p'));
        $this->assertSame("The town's cultural centre. Films on Wednesdays and Sundays.", $this->attribute($page, '//meta[@name="description"]/@content'));
    }

    public function test_an_address_that_is_not_a_web_page_is_not_linked(): void
    {
        $this->everything();

        $body = $this->visit('/en/venue/centro-cultural')->body;

        $this->assertStringNotContainsString('javascript:', $body);
        $this->assertStringNotContainsString('Not a web page', $body);
    }

    public function test_a_venue_with_nothing_coming_up_says_so_and_offers_its_town(): void
    {
        $venue = SavedApi::answer('/venues/centro-cultural', [], Language::En);
        $venue['data']['upcoming_events'] = [];
        $this->api->instead('/venues/centro-cultural', $venue);

        $page = $this->page($this->visit('/en/venue/centro-cultural'));

        $this->assertSame('Nothing is coming up here just now.', $this->text($page, '//section[@class="coming-up"]/p'));
        $this->assertSame('/en/events-in/motril', $this->attribute($page, '//section[@class="coming-up"]//a/@href'));
    }

    public function test_a_venue_that_is_not_there_is_not_found(): void
    {
        $this->assertSame(404, $this->visit('/en/venue/no-such-venue')->status);
    }
}
