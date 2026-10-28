<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * What the core makes of a visit, for the outer layer to send: a page's content as HTML with what goes in its head,
 * a redirect, or a document that is no page (the sitemap, `robots.txt`). The outer layer puts a page's content in
 * its frame (standalone) or its theme (WordPress), with its head: title, description, the same page in each
 * language, the picture a link's preview shows and what search engines read of it (`structured`, schema.org).
 */
final class Answer
{
    /**
     * @param array<string, string> $alternates the same page in each language, as paths, by language code: empty
     *                                          for a page that is not one (not found, unavailable)
     * @param list<string> $scripts scripts the page needs, by their asset's name, for the outer layer to load
     * @param array<mixed>|null $structured what search engines are told of it as schema.org data (JSON-LD)
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
        public readonly bool $indexed = true,
        public readonly array $scripts = [],
        public readonly ?string $image = null,
        public readonly ?array $structured = null,
        public readonly ?string $contentType = null,
    ) {
    }

    /**
     * @param array<string, string> $alternates
     * @param list<string> $scripts
     * @param array<mixed>|null $structured
     */
    public static function page(int $status, Language $language, string $page, string $content, string $title, ?string $description, array $alternates, bool $indexed = true, array $scripts = [], ?string $image = null, ?array $structured = null): self
    {
        return new self($status, $language, $content, $title, $description, $alternates, null, $page, $indexed && $status === 200, $scripts, $image, $structured);
    }

    public static function redirect(int $status, Language $language, string $location): self
    {
        return new self($status, $language, location: $location);
    }

    /** A document that is no page, sent as it is with its type: the sitemap, `robots.txt`. */
    public static function document(string $contentType, string $body): self
    {
        return new self(200, Language::Es, $body, contentType: $contentType);
    }

    public function isRedirect(): bool
    {
        return $this->location !== null;
    }

    public function isDocument(): bool
    {
        return $this->contentType !== null;
    }
}
