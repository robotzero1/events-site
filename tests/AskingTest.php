<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Api\Unavailable;
use EventsSite\Language;
use EventsSite\Tests\Support\ArrayCache;
use EventsSite\Tests\Support\MemoryLog;
use EventsSite\Tests\Support\SavedApi;
use EventsSite\Tests\Support\StandInApi;
use PHPUnit\Framework\TestCase;

final class AskingTest extends TestCase
{
    private StandInApi $api;
    private ArrayCache $cache;
    private MemoryLog $log;
    private int $now = 1_800_000_000;

    protected function setUp(): void
    {
        $this->api = (new StandInApi())->gives('/regions', ['data' => ['Granada']]);
        $this->cache = new ArrayCache();
        $this->log = new MemoryLog();
    }

    private function asking(Language $language = Language::Es, string $visitor = '203.0.113.7'): Asking
    {
        return new Asking($this->api, $this->cache, $this->log, $language, $visitor, fn () => $this->now);
    }

    public function test_the_api_is_asked_in_the_pages_language_for_the_visitor(): void
    {
        $this->asking(Language::En, '198.51.100.20')->get('/regions', ['page' => 2], Asking::LISTING);

        $this->assertSame('/regions', $this->api->asked[0]['path']);
        $this->assertSame(['page' => 2], $this->api->asked[0]['query']);
        $this->assertSame(Language::En, $this->api->asked[0]['language']);
        $this->assertSame('198.51.100.20', $this->api->asked[0]['visitor']);
    }

    public function test_an_answer_is_kept_for_its_lifetime(): void
    {
        $this->asking()->get('/regions', [], Asking::REFERENCE_LIST);
        $this->now += Asking::REFERENCE_LIST;
        $answer = $this->asking()->get('/regions', [], Asking::REFERENCE_LIST);

        $this->assertSame(['data' => ['Granada']], $answer);
        $this->assertCount(1, $this->api->asked);
    }

    public function test_after_its_lifetime_the_api_is_asked_again(): void
    {
        $this->asking()->get('/regions', [], Asking::LISTING);
        $this->now += Asking::LISTING + 1;
        $this->api->gives('/regions', ['data' => ['Granada', 'Málaga']]);

        $this->assertSame(['data' => ['Granada', 'Málaga']], $this->asking()->get('/regions', [], Asking::LISTING));
        $this->assertCount(2, $this->api->asked);
    }

    public function test_each_language_and_question_is_kept_apart(): void
    {
        $this->asking(Language::Es)->get('/regions', ['a' => 1, 'b' => 2], Asking::LISTING);
        $this->asking(Language::En)->get('/regions', ['a' => 1, 'b' => 2], Asking::LISTING);
        $this->asking(Language::Es)->get('/regions', ['a' => 1], Asking::LISTING);
        // The same question with its query in another order is the same question.
        $this->asking(Language::Es)->get('/regions', ['b' => 2, 'a' => 1], Asking::LISTING);

        $this->assertCount(3, $this->api->asked);
    }

    public function test_a_search_is_never_kept(): void
    {
        $this->asking()->get('/regions', ['q' => 'jazz'], null);
        $this->asking()->get('/regions', ['q' => 'jazz'], null);

        $this->assertCount(2, $this->api->asked);
        $this->assertSame([], $this->cache->kept);
    }

    public function test_when_the_api_fails_an_older_answer_is_used_for_a_day(): void
    {
        $this->asking()->get('/regions', [], Asking::LISTING);
        $this->api->fails();
        $this->now += Asking::LISTING + Asking::GRACE;

        $this->assertSame(['data' => ['Granada']], $this->asking()->get('/regions', [], Asking::LISTING));
        $this->assertStringContainsString('/regions: /regions answered 500 (an older answer was used)', $this->log->failures[0]);
    }

    public function test_after_a_day_an_older_answer_is_not_used(): void
    {
        $this->asking()->get('/regions', [], Asking::LISTING);
        $this->api->fails();
        $this->now += Asking::LISTING + Asking::GRACE + 1;

        $this->expectException(Unavailable::class);
        $this->asking()->get('/regions', [], Asking::LISTING);
    }

    public function test_when_the_api_fails_with_nothing_kept_it_is_unavailable_and_logged(): void
    {
        $this->api->fails();

        try {
            $this->asking()->get('/regions', [], Asking::LISTING);
            $this->fail('It was not unavailable.');
        } catch (Unavailable) {
            $this->assertCount(1, $this->log->failures);
        }
    }

    public function test_a_failed_search_is_unavailable(): void
    {
        $this->api->fails();

        $this->expectException(Unavailable::class);
        $this->asking()->get('/regions', ['q' => 'jazz'], null);
    }

    public function test_what_is_not_there_is_not_there_and_not_kept(): void
    {
        $asking = new Asking(new SavedApi(), $this->cache, $this->log, Language::En, '127.0.0.1');

        $this->expectException(NotThere::class);

        try {
            $asking->get('/events/no-such-event', [], Asking::LISTING);
        } finally {
            $this->assertSame([], $this->cache->kept);
            $this->assertSame([], $this->log->failures);
        }
    }
}
