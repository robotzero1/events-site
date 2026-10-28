<?php

declare(strict_types=1);

namespace EventsSite;

/** What someone else wrote, made ready to put in a page: always as text, and an address only if it is a web one. */
final class Text
{
    /** @return list<string> paragraphs, kept apart by an empty line; a paragraph's other lines are kept for `nl2br`. */
    public static function paragraphs(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', preg_split('/\R\s*\R/u', $text)), fn ($paragraph) => $paragraph !== ''));
    }

    /** The start of a text for a search engine's result or a link's preview: about 155 letters, cut at a word. */
    public static function summary(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return null;
        }

        $plain = trim(preg_replace('/\s+/u', ' ', $text));

        if (mb_strlen($plain) <= 155) {
            return $plain;
        }

        $cut = mb_substr($plain, 0, 155);

        return rtrim(mb_substr($cut, 0, (int) mb_strrpos($cut, ' ')), ' ,.;:') . '…';
    }

    /**
     * An address to link to, only if it is a web page's (`http`, `https`): anything else, as `javascript:`, would
     * run or open something when a visitor followed it, and is not linked.
     */
    public static function webAddress(?string $address): ?string
    {
        if ($address === null) {
            return null;
        }

        $scheme = strtolower((string) parse_url(trim($address), PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true) && filter_var(trim($address), FILTER_VALIDATE_URL) !== false ? trim($address) : null;
    }

    /** A web address as it is read, without its scheme, `www.` or a closing `/`: `cineclub.example/pelicula`. */
    public static function shortAddress(string $address): string
    {
        return rtrim(preg_replace('#^https?://(www\.)?#i', '', $address), '/');
    }

    /** A phone number to dial (`tel:`), its digits and a leading `+` only, or null if there are none. */
    public static function dialled(?string $phone): ?string
    {
        $digits = preg_replace('/(?!^\+)[^0-9]/', '', trim((string) $phone));

        return $digits !== null && preg_match('/\d/', $digits) ? 'tel:' . $digits : null;
    }

    /** An email address to write to (`mailto:`), only if it looks like one. */
    public static function mailed(?string $email): ?string
    {
        return $email !== null && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false ? 'mailto:' . trim($email) : null;
    }
}
