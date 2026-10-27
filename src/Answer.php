<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * What the core makes of a visit, for the outer layer to send: a page's content as HTML with what goes in its head,
 * or a redirect. The outer layer puts the content in its frame (standalone) or its theme (WordPress).
 */
final class Answer
{
    /**
     * @param array<string, string> $alternates the same page in each language, as paths, by language code: empty
     *                                          for a page that is not one (not found, unavailable)
     */
    private function __construct(
        public readonly int $status,
        public readonly Language $language,
        public readonly string $content = '',
        public readonly string $title = '',
        public readonly ?string $description = null,
        public readonly array $alternates = [],
        public readonly ?string $location = null,
        public readonly ?string $page = null,
    ) {
    }

    /** @param array<string, string> $alternates */
    public static function page(int $status, Language $language, string $page, string $content, string $title, ?string $description, array $alternates): self
    {
        return new self($status, $language, $content, $title, $description, $alternates, null, $page);
    }

    public static function redirect(int $status, Language $language, string $location): self
    {
        return new self($status, $language, location: $location);
    }

    public function isRedirect(): bool
    {
        return $this->location !== null;
    }
}
