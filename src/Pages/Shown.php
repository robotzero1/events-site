<?php

declare(strict_types=1);

namespace EventsSite\Pages;

/**
 * What a page gives: its template, what the template is given, and its title and description for the page's head,
 * already in the page's language. A page that is not `indexed` is one search engines are asked to leave out, as a
 * Search or a listing narrowed by hand is: the pages of each Location and Category are the ones for them.
 */
final class Shown
{
    /**
     * @param array<string, mixed> $vars
     * @param list<string> $scripts scripts it needs, by their asset's name (`suggest.js`), each one that the page also
     *                              works without
     * @param string|null $redirect where to send the visitor instead (303), as after a form is taken, so that
     *                              reloading does not send it again
     * @param string|null $image the picture a link's preview shows of it, in full
     * @param array<mixed>|null $structured what search engines are told of it as schema.org data (JSON-LD)
     */
    public function __construct(
        public readonly string $template,
        public readonly array $vars,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly bool $indexed = true,
        public readonly array $scripts = [],
        public readonly ?string $redirect = null,
        public readonly ?string $image = null,
        public readonly ?array $structured = null,
    ) {
    }

    /** Send the visitor on to `$address` (a path) instead of showing a page. */
    public static function redirect(string $address): self
    {
        return new self('', [], '', redirect: $address);
    }
}
