<?php

declare(strict_types=1);

namespace EventsSite\Api;

use EventsSite\Language;

/**
 * The events API, as the core knows it. Standalone it is asked with curl; in WordPress it will be asked with
 * WordPress's own HTTP functions. Nothing else in the core asks it directly: pages go through `Asking`, which keeps
 * its answers.
 */
interface Api
{
    /**
     * Ask for something, as `GET /api{$path}`, in the language given, on behalf of the visitor at `$visitor`, whose
     * address is passed on so that the API counts them as themselves. Gives the decoded answer.
     *
     * @param array<string, scalar> $query
     * @return array<mixed>
     *
     * @throws NotThere when the API says there is no such thing (404)
     * @throws Refused when it will not, for what the visitor asked (422) or for asking too often (429)
     * @throws ApiFailed when it does not answer, or answers with anything but what was asked for
     */
    public function get(string $path, array $query, Language $language, string $visitor): array;
}
