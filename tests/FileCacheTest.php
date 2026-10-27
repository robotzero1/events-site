<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Cache\Kept;
use EventsSite\Standalone\FileCache;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    private string $folder;

    protected function setUp(): void
    {
        $this->folder = sys_get_temp_dir() . '/events-site-cache-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->folder . '/*') ?: [] as $file) {
            unlink($file);
        }

        @rmdir($this->folder);
    }

    public function test_what_is_put_is_got_back(): void
    {
        $cache = new FileCache($this->folder);
        $cache->put('abc123', new Kept(['data' => ['Almuñécar']], time() + 60, time() + 120));

        $kept = (new FileCache($this->folder))->get('abc123');

        $this->assertSame(['data' => ['Almuñécar']], $kept->answer);
        $this->assertSame(time() + 60, $kept->freshUntil);
    }

    public function test_nothing_put_is_nothing(): void
    {
        $this->assertNull((new FileCache($this->folder))->get('abc123'));
    }

    public function test_one_kept_past_its_time_is_nothing_and_goes(): void
    {
        $cache = new FileCache($this->folder);
        $cache->put('abc123', new Kept(['data' => []], time() - 120, time() - 60));

        $this->assertNull($cache->get('abc123'));
        $this->assertSame([], glob($this->folder . '/*'));
    }

    public function test_a_file_that_is_not_one_of_its_own_is_nothing(): void
    {
        mkdir($this->folder);
        file_put_contents($this->folder . '/abc123.json', '{"half":');

        $this->assertNull((new FileCache($this->folder))->get('abc123'));
    }
}
