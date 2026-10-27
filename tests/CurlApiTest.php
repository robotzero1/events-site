<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Api\ApiFailed;
use EventsSite\Api\NotThere;
use EventsSite\Language;
use EventsSite\Standalone\CurlApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** What the standalone site really sends the API, tried against PHP's own server answering as the API might. */
final class CurlApiTest extends TestCase
{
    private const PORT = 8097;

    /** @var resource|null */
    private static $server = null;

    public static function setUpBeforeClass(): void
    {
        self::$server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . self::PORT, __DIR__ . '/fixtures/echo-api.php'],
            [1 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], 2 => ['file', PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true],
        );

        for ($try = 0; $try < 50; $try++) {
            if (@fsockopen('127.0.0.1', self::PORT) !== false) {
                return;
            }

            usleep(100_000);
        }

        self::fail('The stand-in server did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    private function api(?string $host = null, int $seconds = 5): CurlApi
    {
        return new CurlApi('http://127.0.0.1:' . self::PORT . '/api/', $host, $seconds);
    }

    public function test_it_asks_for_json_in_the_language_for_the_visitor(): void
    {
        $sent = $this->api()->get('/echo', ['q' => 'música & baile', 'page' => 2], Language::Es, '203.0.113.7');

        $this->assertSame('application/json', $sent['accept']);
        $this->assertSame('es', $sent['language']);
        $this->assertSame('203.0.113.7', $sent['forwarded_for']);
        $this->assertSame('q=m%C3%BAsica%20%26%20baile&page=2', $sent['query']);
        $this->assertSame('127.0.0.1:' . self::PORT, $sent['host']);
    }

    public function test_it_names_the_site_when_told_to(): void
    {
        $this->assertSame('events.example', $this->api('events.example')->get('/echo', [], Language::En, '127.0.0.1')['host']);
    }

    public function test_a_404_is_not_there(): void
    {
        $this->expectException(NotThere::class);
        $this->api()->get('/missing', [], Language::En, '127.0.0.1');
    }

    /** @return array<string, array{string}> */
    public static function failures(): array
    {
        return [
            'a server error' => ['/broken'],
            'a refusal' => ['/refused'],
            'an answer that is not JSON' => ['/text'],
        ];
    }

    #[DataProvider('failures')]
    public function test_anything_but_an_answer_is_a_failure(string $path): void
    {
        $this->expectException(ApiFailed::class);
        $this->api()->get($path, [], Language::En, '127.0.0.1');
    }

    public function test_an_api_that_is_not_there_is_a_failure(): void
    {
        $this->expectException(ApiFailed::class);
        (new CurlApi('http://127.0.0.1:1/api'))->get('/echo', [], Language::En, '127.0.0.1');
    }

    public function test_an_api_that_is_too_slow_is_a_failure(): void
    {
        $this->expectException(ApiFailed::class);
        $this->api(seconds: 1)->get('/slow', [], Language::En, '127.0.0.1');
    }
}
