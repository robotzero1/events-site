<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

use CURLFile;
use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Api\NotThere;
use EventsSite\Api\Refused;
use EventsSite\Language;

/**
 * The API asked with PHP's curl. Every request says which language it wants and passes on the visitor's address in
 * `X-Forwarded-For`, which the API believes from its own machine alone. On the server the API is asked by its own
 * address (`https://events.firemouth.net/api`) but connected to on the machine itself (`$connectTo`, `127.0.0.1`),
 * as curl's `CURLOPT_RESOLVE` does: the certificate is the site's, and the API sees the request come from
 * `127.0.0.1`, which it believes. Asking `http://127.0.0.1` would not do: the server sends plain HTTP on to HTTPS.
 */
final class CurlApi implements Api
{
    /** A form with a poster is given longer than a question: the file has to go up. */
    private const SENDING_SECONDS = 30;

    public function __construct(
        private readonly string $base,
        private readonly ?string $connectTo = null,
        private readonly int $seconds = 5,
    ) {
    }

    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        $address = rtrim($this->base, '/') . $path . ($query === [] ? '' : '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));

        return $this->ask($path, $address, $language, $visitor, [], $this->seconds);
    }

    public function post(string $path, array $fields, array $files, Language $language, string $visitor): array
    {
        $form = $fields;

        foreach ($files as $name => $upload) {
            $form[$name] = new CURLFile($upload->path, $upload->type !== '' ? $upload->type : null, $upload->name !== '' ? $upload->name : 'upload');
        }

        // Given as an array, curl sends it as multipart/form-data, as a file needs.
        return $this->ask($path, rtrim($this->base, '/') . $path, $language, $visitor, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $form], max($this->seconds, self::SENDING_SECONDS));
    }

    /** The API's name and port, and the address to connect to for them: `events.firemouth.net:443:127.0.0.1`. */
    private function resolving(): string
    {
        $parts = parse_url($this->base);
        $port = $parts['port'] ?? (($parts['scheme'] ?? 'http') === 'https' ? 443 : 80);

        return "{$parts['host']}:{$port}:{$this->connectTo}";
    }

    /** @return array<mixed> */
    private function ask(string $path, string $address, Language $language, string $visitor, array $options, int $seconds): array
    {
        $headers = [
            'Accept: application/json',
            "Accept-Language: {$language->value}",
            "X-Forwarded-For: {$visitor}",
            'User-Agent: events-site',
        ];

        if ($this->connectTo !== null) {
            $options[CURLOPT_RESOLVE] = [$this->resolving()];
        }

        $curl = curl_init($address);
        curl_setopt_array($curl, $options + [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT => $seconds,
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

        $answer = json_decode((string) $body, true);

        if ($status === 422 || $status === 429) {
            throw new Refused($status, is_array($answer['errors'] ?? null) ? $answer['errors'] : [], (string) ($answer['message'] ?? ''));
        }

        if ($status !== 200 && $status !== 201) {
            throw new ApiFailed("{$path} answered {$status}");
        }

        if (!is_array($answer)) {
            throw new ApiFailed("{$path} did not answer with JSON");
        }

        return $answer;
    }
}
