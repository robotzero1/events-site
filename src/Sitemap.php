<?php

declare(strict_types=1);

namespace EventsSite;

use EventsSite\Api\Asking;

/**
 * Where everything is, for search engines (`/sitemap.xml`): every event from today on, every published Venue, every
 * Location's and Category's page and the fixed pages, each in both languages and naming its other one. An event that
 * has ended leaves it and keeps its address. Everything it asks the API is kept an hour.
 */
final class Sitemap
{
    /** The events are asked for in pages this big, and no more of them than this. */
    private const PER_PAGE = 100;
    private const MOST_PAGES = 50;

    public function __construct(private readonly string $siteUrl)
    {
    }

    /** `robots.txt`: every page may be read, but for the API's and the panel's, and the sitemap is here. */
    public static function robots(string $siteUrl): string
    {
        return "User-agent: *\nDisallow: /api/\nDisallow: /admin/\n\nSitemap: {$siteUrl}/sitemap.xml\n";
    }

    public function of(Asking $asking): string
    {
        $pages = [['home', null], ['events', null], ['suggest', null], ['about', null]];

        foreach (Places::of($asking)->regions as $region) {
            foreach ($region['locations'] as $location) {
                $pages[] = ['location', $location['ref']];
            }
        }

        foreach ($asking->get('/categories', [], Asking::REFERENCE_LIST)['data'] as $category) {
            $pages[] = ['category', $category['ref']];
        }

        foreach ($asking->get('/venues', [], Asking::REFERENCE_LIST)['data'] as $venue) {
            $pages[] = ['venue', $venue['ref']];
        }

        for ($page = 1, $last = 1; $page <= $last && $page <= self::MOST_PAGES; $page++) {
            $answer = $asking->get('/events', ['per_page' => self::PER_PAGE, 'page' => $page], Asking::REFERENCE_LIST);
            $last = (int) $answer['meta']['last_page'];

            foreach ($answer['data'] as $event) {
                $pages[] = ['event', $event['ref']];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";

        foreach ($pages as [$name, $ref]) {
            $addresses = [];

            foreach (Language::cases() as $language) {
                $addresses[$language->value] = $this->siteUrl . Addresses::of($name, $language, $ref);
            }

            foreach ($addresses as $address) {
                $xml .= '  <url><loc>' . self::escaped($address) . '</loc>';

                foreach ($addresses as $code => $alternate) {
                    $xml .= '<xhtml:link rel="alternate" hreflang="' . $code . '" href="' . self::escaped($alternate) . '"/>';
                }

                $xml .= "</url>\n";
            }
        }

        return $xml . "</urlset>\n";
    }

    private static function escaped(string $text): string
    {
        return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
