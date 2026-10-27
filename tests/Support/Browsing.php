<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use DOMDocument;
use DOMXPath;
use EventsSite\Language;
use EventsSite\Standalone\Response;
use EventsSite\When;
use EventsSite\Standalone\Standalone;
use EventsSite\Visit;

/**
 * For a test that visits the standalone site and reads the page it gets, with the saved answers and the clock set
 * to the day they were captured, the known state's days being counted from then.
 */
trait Browsing
{
    protected SavedApi $api;
    protected ArrayCache $cache;
    protected MemoryLog $log;

    /** The day the visit is made, `2026-10-30`: the day the answers were captured unless a test says otherwise. */
    protected ?string $day = null;

    /** The time of day it is made, local time. */
    protected string $time = '12:00';

    protected function setUpBrowsing(): void
    {
        $this->api = new SavedApi();
        $this->cache = new ArrayCache();
        $this->log = new MemoryLog();
    }

    /** The day the saved answers were captured, `2026-10-27`. */
    protected static function captured(): string
    {
        return trim((string) file_get_contents(dirname(__DIR__) . '/answers/today.txt'));
    }

    /** The day `$days` after the capture, `2026-10-29`: the known state's days are counted from it. */
    protected static function inDays(int $days): string
    {
        return (new \DateTimeImmutable(self::captured()))->modify("+{$days} days")->format('Y-m-d');
    }

    /**
     * That day in words, as the page writes it (`heading`, `day` or `short`), for a test whose expectation would
     * otherwise hold only on the day the answers were captured. `When` is tested on fixed days by `WhenTest`.
     */
    protected static function written(int $days, string $how = 'heading', Language $language = Language::En): string
    {
        return (new When($language))->{$how}(self::inDays($days), self::captured());
    }

    protected function visit(string $path, array $query = [], ?string $acceptLanguage = null): Response
    {
        $now = (new \DateTimeImmutable(($this->day ?? self::captured()) . ' ' . $this->time, new \DateTimeZone('Europe/Madrid')))->getTimestamp();
        $site = new Standalone($this->api, $this->cache, $this->log, Standalone::templates(), 'https://events.example', now: fn () => $now);

        return $site->respond(new Visit($path, $query, $acceptLanguage, '203.0.113.7'));
    }

    protected function page(Response $response): DOMXPath
    {
        $document = new DOMDocument();
        $this->assertTrue(@$document->loadHTML($response->body), 'The page is not HTML.');

        return new DOMXPath($document);
    }

    /** The text at an XPath, its spaces tidied. */
    protected function text(DOMXPath $page, string $query, int $which = 0): string
    {
        $found = $page->query($query);
        $this->assertGreaterThan($which, $found->length, "Nothing at {$query}.");

        return trim(preg_replace('/\s+/u', ' ', $found->item($which)->textContent));
    }

    /** @return list<string> the text of everything at an XPath, its spaces tidied */
    protected function texts(DOMXPath $page, string $query): array
    {
        $texts = [];

        foreach ($page->query($query) as $node) {
            $texts[] = trim(preg_replace('/\s+/u', ' ', $node->textContent));
        }

        return $texts;
    }

    protected function attribute(DOMXPath $page, string $query): string
    {
        $found = $page->query($query);
        $this->assertSame(1, $found->length, "Not one at {$query}.");

        return $found->item(0)->nodeValue;
    }
}
