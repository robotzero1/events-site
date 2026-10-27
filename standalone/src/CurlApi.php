<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Api\NotThere;
use EventsSite\Language;

/**
 * The API asked with PHP's curl. Every request says which language it wants and passes on the visitor's address in
 * `X-Forwarded-For`, which the API believes from this server alone. On the server the API is asked on the same
 * machine: `$host` then names the site, so that nginx gives the request to the API.
 */
final class CurlApi implements Api
{
    public function __construct(
        private readonly string $base,
        private readonly ?string $host = null,
        private readonly int $seconds = 5,
    ) {
    }

    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        $address = rtrim($this->base, '/') . $path . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        $headers = [
            'Accept: application/json',
            "Accept-Language: {$language->value}",
            "X-Forwarded-For: {$visitor}",
            'User-Agent: events-site',
        ];

        if ($this->host !== null) {
            $headers[] = "Host: {$this->host}";
        }

        $curl = curl_init($address);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $this->seconds,
            CURLOPT_FOLLOWLOCATION => false,
        ]);

        $body = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error = curl_error($curl);

        if ($body === false) {
            throw new ApiFailed("No answer from {$path}: {$error}");
        }

        if ($status === 404) {
            throw new NotThere($path);
        }

        if ($status !== 200) {
            throw new ApiFailed("{$path} answered {$status}");
        }

        $answer = json_decode((string) $body, true);

        if (!is_array($answer)) {
            throw new ApiFailed("{$path} did not answer with JSON");
        }

        return $answer;
    }
}
