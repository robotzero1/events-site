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
    /** @param array<string, mixed> $vars */
    public function __construct(
        public readonly string $template,
        public readonly array $vars,
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly bool $indexed = true,
        /** Scripts it needs, by their asset's name (`suggest.js`), each one that the page also works without. */
        public readonly array $scripts = [],
        /** Where to send the visitor instead (303), as after a form is taken, so that reloading does not send it again. */
        public readonly ?string $redirect = null,
    ) {
    }

    /** Send the visitor on to `$address` (a path) instead of showing a page. */
    public static function redirect(string $address): self
    {
        return new self('', [], '', redirect: $address);
    }
}
