<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

use EventsSite\Cache\Cache;
use EventsSite\Cache\Kept;

/**
 * The API's answers kept as files in a folder of their own, one for each question. A file is written whole and then
 * put in place, so a visit never reads one half written. One kept past its time is deleted when it is next read;
 * the folder can be emptied at any time, as a deploy does.
 */
final class FileCache implements Cache
{
    public function __construct(private readonly string $folder)
    {
    }

    public function get(string $key): ?Kept
    {
        $file = $this->file($key);
        $text = @file_get_contents($file);

        if ($text === false) {
            return null;
        }

        $kept = json_decode($text, true);

        if (!is_array($kept) || !isset($kept['answer'], $kept['fresh_until'], $kept['kept_until']) || !is_array($kept['answer'])) {
            @unlink($file);

            return null;
        }

        if ($kept['kept_until'] < time()) {
            @unlink($file);

            return null;
        }

        return new Kept($kept['answer'], (int) $kept['fresh_until'], (int) $kept['kept_until']);
    }

    public function put(string $key, Kept $kept): void
    {
        if (!is_dir($this->folder)) {
            @mkdir($this->folder, 0750, true);
        }

        $file = $this->file($key);
        $part = $file . '.' . bin2hex(random_bytes(4));
        $text = json_encode(['fresh_until' => $kept->freshUntil, 'kept_until' => $kept->keptUntil, 'answer' => $kept->answer], JSON_UNESCAPED_UNICODE);

        if ($text !== false && @file_put_contents($part, $text) !== false) {
            @rename($part, $file);
        }
    }

    private function file(string $key): string
    {
        return $this->folder . '/' . preg_replace('/[^a-z0-9]/', '', $key) . '.json';
    }
}
