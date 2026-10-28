<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * Every address of the site, in both languages: which page an address is, and the address of a page. The words of
 * a path and the names of a listing's filters are in the address's own language; a ref is the API's own, the same
 * in both. A WordPress plugin is to give its pages the same addresses, so this is the core's and not the outer
 * layer's.
 */
final class Addresses
{
    /** Each page's path after the language, `{ref}` standing for its ref. */
    private const PATHS = [
        'home' => ['es' => '', 'en' => ''],
        'events' => ['es' => 'eventos', 'en' => 'events'],
        'event' => ['es' => 'evento/{ref}', 'en' => 'event/{ref}'],
        'venue' => ['es' => 'lugar/{ref}', 'en' => 'venue/{ref}'],
        'location' => ['es' => 'eventos-en/{ref}', 'en' => 'events-in/{ref}'],
        'category' => ['es' => 'categoria/{ref}', 'en' => 'category/{ref}'],
        'suggest' => ['es' => 'sugerir', 'en' => 'suggest'],
        'about' => ['es' => 'acerca', 'en' => 'about'],
    ];

    /** A listing's filters: the name the code knows each by, then its name in each language's addresses. */
    private const FILTERS = [
        'from' => ['es' => 'desde', 'en' => 'from'],
        'to' => ['es' => 'hasta', 'en' => 'to'],
        'place' => ['es' => 'lugar', 'en' => 'place'],
        'category' => ['es' => 'categoria', 'en' => 'category'],
        'q' => ['es' => 'q', 'en' => 'q'],
        'sort' => ['es' => 'orden', 'en' => 'sort'],
        'page' => ['es' => 'pagina', 'en' => 'page'],
        // That a Suggestion has been sent, on the thank-you that follows.
        'sent' => ['es' => 'enviado', 'en' => 'sent'],
    ];

    private const REF = '[a-z0-9][a-z0-9-]*';

    /**
     * The page an address is, or null if it is none. `$query` is the address's query as PHP reads it; only the
     * filters named in the address's language are taken, by the names the code knows them by.
     */
    public static function match(string $path, array $query = []): ?Route
    {
        if (!preg_match('#^/(es|en)/(.*)$#', $path, $parts)) {
            return null;
        }

        $language = Language::from($parts[1]);
        $rest = $parts[2];

        foreach (self::PATHS as $name => $paths) {
            $pattern = '#^' . str_replace('\{ref\}', '(' . self::REF . ')', preg_quote($paths[$language->value], '#')) . '$#';

            if (preg_match($pattern, $rest, $found)) {
                return new Route($name, $language, $found[1] ?? null, self::filtersFrom($query, $language));
            }
        }

        return null;
    }

    /**
     * The address of a page, as a path from the site's root, with its filters named in its language. A filter
     * that is empty is left out.
     */
    public static function of(string $name, Language $language, ?string $ref = null, array $filters = []): string
    {
        $path = '/' . $language->value . '/' . str_replace('{ref}', (string) $ref, self::PATHS[$name][$language->value]);
        $query = [];

        foreach (self::FILTERS as $filter => $names) {
            if (isset($filters[$filter]) && $filters[$filter] !== '') {
                $query[$names[$language->value]] = $filters[$filter];
            }
        }

        return $query === [] ? $path : $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** The name of a listing's filter in a language's addresses, as a form names its field: `desde`, `from`. */
    public static function filterName(string $filter, Language $language): string
    {
        return self::FILTERS[$filter][$language->value];
    }

    /** The same page in the other language, filters and all. */
    public static function inOther(Route $route): string
    {
        return self::of($route->name, $route->language->other(), $route->ref, $route->filters);
    }

    /** @return array<string, string> */
    private static function filtersFrom(array $query, Language $language): array
    {
        $filters = [];

        foreach (self::FILTERS as $filter => $names) {
            $value = $query[$names[$language->value]] ?? null;

            if (is_string($value) && trim($value) !== '') {
                $filters[$filter] = trim($value);
            }
        }

        return $filters;
    }
}
