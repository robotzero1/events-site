<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Language;

/** An API that does not answer, as one that is down or being deployed. */
final class FailingApi implements Api
{
    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        throw new ApiFailed('No answer');
    }
}
