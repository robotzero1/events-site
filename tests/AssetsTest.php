<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Standalone\Assets;
use EventsSite\Standalone\Standalone;
use EventsSite\Tests\Support\ArrayCache;
use EventsSite\Tests\Support\MemoryLog;
use EventsSite\Tests\Support\SavedApi;
use EventsSite\Visit;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AssetsTest extends TestCase
{
    private function site(): Standalone
    {
        return new Standalone(new SavedApi(), new ArrayCache(), new MemoryLog(), Standalone::templates(), 'https://events.example');
    }

    public function test_a_page_links_its_stylesheets_by_a_fingerprint_of_their_contents(): void
    {
        $body = $this->site()->respond(new Visit('/en/about'))->body;
        $fingerprint = substr(hash_file('sha256', dirname(__DIR__) . '/assets/site.css'), 0, 12);

        $this->assertStringContainsString("<link rel=\"stylesheet\" href=\"/assets/site.css?v={$fingerprint}\">", $body);
        $this->assertMatchesRegularExpression('#<link rel="stylesheet" href="/assets/frame.css\?v=[0-9a-f]{12}">#', $body);
    }

    public function test_the_stylesheet_is_sent_to_be_kept(): void
    {
        $response = $this->site()->respond(new Visit('/assets/site.css'));

        $this->assertSame(200, $response->status);
        $this->assertSame('text/css; charset=utf-8', $response->headers['Content-Type']);
        $this->assertSame('public, max-age=31536000, immutable', $response->headers['Cache-Control']);
        $this->assertStringContainsString('.events-site', $response->body);
    }

    public function test_the_standalone_frames_own_stylesheet_and_the_font_are_sent(): void
    {
        $this->assertSame(200, $this->site()->respond(new Visit('/assets/frame.css'))->status);

        $font = $this->site()->respond(new Visit('/assets/fonts/source-serif-4-latin-wght.woff2'));
        $this->assertSame(200, $font->status);
        $this->assertSame('font/woff2', $font->headers['Content-Type']);
    }

    /** @return array<string, array{string}> */
    public static function notAssets(): array
    {
        return [
            'nothing of the name' => ['/assets/nothing.css'],
            'a file of another kind' => ['/assets/fonts/OFL.txt'],
            'a way out of the folder' => ['/assets/../composer.json'],
            'a way out, written otherwise' => ['/assets/fonts/../../config.php'],
            'a folder' => ['/assets/fonts'],
            'too deep' => ['/assets/a/b/site.css'],
        ];
    }

    #[DataProvider('notAssets')]
    public function test_anything_else_under_assets_is_not_found(string $path): void
    {
        $this->assertSame(404, $this->site()->respond(new Visit($path))->status);
    }

    public function test_every_stylesheet_keeps_inside_the_sites_class(): void
    {
        // A WordPress theme's own pages must be left alone: every rule of the site's stylesheet starts with
        // .events-site, but the font it declares.
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/site.css');
        $css = preg_replace('#/\*.*?\*/#s', '', $css);
        $css = preg_replace('#@font-face\s*\{[^}]*\}#', '', $css);
        $css = preg_replace('#@container[^{]*\{#', '', $css);

        preg_match_all('#([^{}]+)\{[^{}]*\}#', $css, $rules);

        foreach ($rules[1] as $selectors) {
            foreach (explode(',', $selectors) as $selector) {
                $this->assertStringStartsWith('.events-site', trim($selector), "A rule outside .events-site: {$selector}");
            }
        }

        $this->assertNotEmpty($rules[1]);
    }

    public function test_an_asset_that_is_not_there_has_no_address(): void
    {
        $this->expectException(\LogicException::class);
        Assets::standard()->address('nothing.css');
    }
}
