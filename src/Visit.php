<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * One request for a page, as the core needs it, wherever it runs: the address's path and query, the browser's
 * languages and the visitor's own address, which is passed on to the API, and for a form sent, its fields and files.
 */
final class Visit
{
    /**
     * @param array<string, mixed> $query as PHP reads a query string
     * @param array<string, mixed> $form a form's fields as PHP reads them, for a `POST`
     * @param array<string, Upload> $files a form's files
     */
    public function __construct(
        public readonly string $path,
        public readonly array $query = [],
        public readonly ?string $acceptLanguage = null,
        public readonly string $visitor = '127.0.0.1',
        public readonly string $method = 'GET',
        public readonly array $form = [],
        public readonly array $files = [],
    ) {
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    /** A field of the form sent, as text: empty for none, or for one sent as a list. */
    public function field(string $name): string
    {
        $value = $this->form[$name] ?? '';

        return is_string($value) ? $value : '';
    }
}
