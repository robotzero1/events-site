<?php

declare(strict_types=1);

namespace EventsSite\Tests\Real;

use EventsSite\Api\Refused;
use EventsSite\Language;
use EventsSite\Standalone\CurlApi;
use EventsSite\Tests\Support\SavedApi;
use EventsSite\Upload;
use PHPUnit\Framework\TestCase;

/**
 * A Suggestion sent to the real API as the Suggest page sends one, which no saved answer can show: taken, with its
 * poster, and refused. Run against the API in its known state, which this adds to.
 */
final class SendingASuggestionTest extends TestCase
{
    private function api(): CurlApi
    {
        return new CurlApi(getenv('EVENTS_API') ?: $this->markTestSkipped('EVENTS_API is not set: run this through bin/with-local-api.php.'), seconds: 30);
    }

    public function test_a_suggestion_is_taken_as_the_saved_api_takes_one(): void
    {
        $answer = $this->api()->post('/suggestions', ['content' => 'A real check of the Suggest page.', 'link' => 'https://example.com/event'], [], Language::En, '127.0.0.1');

        $this->assertSame(SavedApi::TAKEN, $answer);
    }

    public function test_a_suggestion_is_taken_with_its_poster(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'poster');
        file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));

        try {
            $answer = $this->api()->post('/suggestions', ['content' => 'With its poster.'], ['poster' => new Upload($file, 'poster.png', 'image/png', (int) filesize($file))], Language::En, '127.0.0.1');
        } finally {
            unlink($file);
        }

        $this->assertSame(SavedApi::TAKEN, $answer);
    }

    public function test_one_with_nothing_said_is_refused_for_its_content(): void
    {
        try {
            $this->api()->post('/suggestions', ['link' => 'not a link'], [], Language::En, '127.0.0.1');
            $this->fail('It was taken.');
        } catch (Refused $refused) {
            $this->assertSame(422, $refused->status);
            $this->assertSame(['content', 'link'], array_keys($refused->fields));
        }
    }
}
