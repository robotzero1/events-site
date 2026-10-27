<?php

declare(strict_types=1);

namespace EventsSite\Api;

use Closure;
use EventsSite\Cache\Cache;
use EventsSite\Cache\Kept;
use EventsSite\Language;
use EventsSite\Log;

/**
 * How a page asks the API: through the cache. An answer is used as it is for its lifetime and kept a day longer,
 * and that older answer is what a page is made from when the API fails, so that a moment's failure, as at a
 * deploy, is not seen. With nothing kept a failure is `Unavailable`. One visit asks for one visitor and in one
 * language.
 */
final class Asking
{
    /** Regions, Locations, Categories and Venue types change seldom. */
    public const REFERENCE_LIST = 3600;

    /** A listing, an event, a Venue: a change made in the panel shows within this. */
    public const LISTING = 300;

    /** How long past its lifetime an answer is kept, for when the API fails. */
    public const GRACE = 86400;

    private Closure $now;

    public function __construct(
        private readonly Api $api,
        private readonly Cache $cache,
        private readonly Log $log,
        private readonly Language $language,
        private readonly string $visitor,
        ?Closure $now = null,
    ) {
        $this->now = $now ?? time(...);
    }

    public function language(): Language
    {
        return $this->language;
    }

    /**
     * The API's answer to `GET /api{$path}`, kept for `$lifetime` seconds. With a lifetime of null it is never kept
     * or taken from what is kept, as a Search is not: each Search is answered afresh and counted in the API's log.
     *
     * @param array<string, scalar> $query
     * @return array<mixed>
     *
     * @throws NotThere
     * @throws Unavailable when the API fails and nothing is kept
     */
    public function get(string $path, array $query, ?int $lifetime): array
    {
        if ($lifetime === null) {
            try {
                return $this->api->get($path, $query, $this->language, $this->visitor);
            } catch (ApiFailed $failure) {
                $this->log->failure("{$path}: {$failure->getMessage()}");

                throw new Unavailable($failure->getMessage(), 0, $failure);
            }
        }

        $key = $this->key($path, $query);
        $now = ($this->now)();
        $kept = $this->cache->get($key);

        if ($kept !== null && $kept->keptUntil < $now) {
            $kept = null;
        }

        if ($kept !== null && $kept->freshUntil >= $now) {
            return $kept->answer;
        }

        try {
            $answer = $this->api->get($path, $query, $this->language, $this->visitor);
        } catch (ApiFailed $failure) {
            $this->log->failure("{$path}: {$failure->getMessage()}" . ($kept !== null ? ' (an older answer was used)' : ''));

            if ($kept !== null) {
                return $kept->answer;
            }

            throw new Unavailable($failure->getMessage(), 0, $failure);
        }

        $this->cache->put($key, new Kept($answer, $now + $lifetime, $now + $lifetime + self::GRACE));

        return $answer;
    }

    /** One key for one question: the language, the path and the query whatever its order. */
    private function key(string $path, array $query): string
    {
        ksort($query);

        return hash('sha256', $this->language->value . ' ' . $path . '?' . http_build_query($query));
    }
}
