<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TextTest extends TestCase
{
    /** @return array<string, array{?string, ?string}> */
    public static function addresses(): array
    {
        return [
            'a web page' => ['https://cineclub.example/Pelicula/x/', 'https://cineclub.example/Pelicula/x/'],
            'plain http' => ['http://ayto.example/web', 'http://ayto.example/web'],
            'with spaces round it' => ['  https://a.example/  ', 'https://a.example/'],
            'a script' => ['javascript:alert(1)', null],
            'a script in capitals' => ['JavaScript:alert(1)', null],
            'data' => ['data:text/html,<script>alert(1)</script>', null],
            'mail' => ['mailto:a@example.com', null],
            'no scheme' => ['www.example.com', null],
            'nothing' => [null, null],
        ];
    }

    #[DataProvider('addresses')]
    public function test_only_a_web_pages_address_is_linked(?string $address, ?string $linked): void
    {
        $this->assertSame($linked, Text::webAddress($address));
    }

    public function test_a_web_address_as_it_is_read(): void
    {
        $this->assertSame('centro.example', Text::shortAddress('https://www.centro.example/'));
        $this->assertSame('cineclub.example/Pelicula', Text::shortAddress('http://cineclub.example/Pelicula/'));
    }

    public function test_a_phone_number_to_dial(): void
    {
        $this->assertSame('tel:958600000', Text::dialled('958 60 00 00'));
        $this->assertSame('tel:+34958600000', Text::dialled('+34 958-60-00-00'));
        $this->assertNull(Text::dialled('ask at the bar'));
        $this->assertNull(Text::dialled(null));
    }

    public function test_an_email_address_to_write_to(): void
    {
        $this->assertSame('mailto:centro@example.com', Text::mailed(' centro@example.com '));
        $this->assertNull(Text::mailed('not an address'));
        $this->assertNull(Text::mailed('a@example.com?subject=x'));
    }

    public function test_paragraphs_are_kept_apart_by_an_empty_line(): void
    {
        $this->assertSame(['One.', "Two,\nwith a line."], Text::paragraphs("One.\n\n\nTwo,\nwith a line.\n"));
        $this->assertSame(['One.', 'Two.'], Text::paragraphs("One.\r\n\r\nTwo."));
        $this->assertSame([], Text::paragraphs("  \n "));
    }

    public function test_a_summary_is_cut_at_a_word(): void
    {
        $long = str_repeat('word ', 40);
        $summary = Text::summary($long);

        $this->assertLessThanOrEqual(156, mb_strlen($summary));
        $this->assertStringEndsWith('word…', $summary);
        $this->assertSame('Short and sweet.', Text::summary("Short\nand  sweet."));
        $this->assertNull(Text::summary(''));
    }
}
