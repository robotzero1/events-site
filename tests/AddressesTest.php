<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Addresses;
use EventsSite\Language;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AddressesTest extends TestCase
{
    /** @return array<string, array{string, string, Language, ?string}> */
    public static function pages(): array
    {
        return [
            'home, es' => ['/es/', 'home', Language::Es, null],
            'home, en' => ['/en/', 'home', Language::En, null],
            'listing, es' => ['/es/eventos', 'events', Language::Es, null],
            'listing, en' => ['/en/events', 'events', Language::En, null],
            'event, es' => ['/es/evento/feria-del-libro', 'event', Language::Es, 'feria-del-libro'],
            'event, en' => ['/en/event/feria-del-libro', 'event', Language::En, 'feria-del-libro'],
            'Venue, es' => ['/es/lugar/teatro-calderon', 'venue', Language::Es, 'teatro-calderon'],
            'Venue, en' => ['/en/venue/teatro-calderon', 'venue', Language::En, 'teatro-calderon'],
            'Location, es' => ['/es/eventos-en/almunecar', 'location', Language::Es, 'almunecar'],
            'Location, en' => ['/en/events-in/almunecar', 'location', Language::En, 'almunecar'],
            'Category, es' => ['/es/categoria/conciertos', 'category', Language::Es, 'conciertos'],
            'Category, en' => ['/en/category/conciertos', 'category', Language::En, 'conciertos'],
            'Suggest, es' => ['/es/sugerir', 'suggest', Language::Es, null],
            'Suggest, en' => ['/en/suggest', 'suggest', Language::En, null],
            'About, es' => ['/es/acerca', 'about', Language::Es, null],
            'About, en' => ['/en/about', 'about', Language::En, null],
            'a ref with digits' => ['/en/event/book-fair-2', 'event', Language::En, 'book-fair-2'],
        ];
    }

    #[DataProvider('pages')]
    public function test_an_address_is_its_page_and_the_page_has_that_address(string $path, string $name, Language $language, ?string $ref): void
    {
        $route = Addresses::match($path);

        $this->assertNotNull($route);
        $this->assertSame($name, $route->name);
        $this->assertSame($language, $route->language);
        $this->assertSame($ref, $route->ref);
        $this->assertSame($path, Addresses::of($name, $language, $ref));
    }

    /** @return array<string, array{string}> */
    public static function nothing(): array
    {
        return [
            'no language' => ['/eventos'],
            'another language' => ['/fr/events'],
            'the words of the other language' => ['/es/events'],
            'a page with no ref' => ['/en/event/'],
            'a ref with capitals' => ['/en/event/Book-Fair'],
            'a ref with a slash' => ['/en/event/a/b'],
            'a trailing slash' => ['/en/about/'],
            'the language without its slash' => ['/en'],
        ];
    }

    #[DataProvider('nothing')]
    public function test_an_address_that_is_no_page(string $path): void
    {
        $this->assertNull(Addresses::match($path));
    }

    public function test_a_listings_filters_are_named_in_its_language(): void
    {
        $route = Addresses::match('/es/eventos', ['desde' => '2026-10-30', 'lugar' => 'motril', 'q' => ' flamenco ', 'from' => 'ignored', 'pagina' => '2']);

        $this->assertSame(['from' => '2026-10-30', 'place' => 'motril', 'q' => 'flamenco', 'page' => '2'], $route->filters);
        $this->assertSame('/en/events?from=2026-10-30&place=motril&q=flamenco&page=2', Addresses::inOther($route));
    }

    public function test_an_empty_filter_is_left_out_of_an_address(): void
    {
        $this->assertSame('/es/eventos?categoria=cine', Addresses::of('events', Language::Es, filters: ['category' => 'cine', 'q' => '', 'from' => '']));
    }

    public function test_a_filter_given_as_a_list_is_ignored(): void
    {
        $this->assertSame([], Addresses::match('/en/events', ['q' => ['a', 'b']])->filters);
    }

    public function test_words_are_written_safely_into_an_address(): void
    {
        $this->assertSame('/es/eventos?q=m%C3%BAsica%20%26%20baile', Addresses::of('events', Language::Es, filters: ['q' => 'música & baile']));
    }
}
