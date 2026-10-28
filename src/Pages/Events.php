<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Api\Refused;
use EventsSite\EventLines;
use EventsSite\Places;
use EventsSite\Route;
use EventsSite\Strings;

/**
 * What's on, found by days, place, Category and words: `GET /events`, 20 to a page. Without words it is grouped by
 * day; a Search best match first is a list of its own. The address is the whole of what is asked, so a result can
 * be shared, and what the API will not take is said beside its field in the site's own words. A Search is never
 * kept, and a listing narrowed by hand is not for search engines.
 *
 * The same page is each Location's (`LOCATION`) and each Category's (`CATEGORY`), with that filter fixed by the
 * address's ref and left out of the form: the pages people search for ("conciertos en Motril"), and so the ones
 * search engines are given. A ref that is no Location or Category is not found.
 */
final class Events implements Page
{
    public const PER_PAGE = 20;

    public const ALL = 'all';
    public const LOCATION = 'location';
    public const CATEGORY = 'category';

    /** The API's name for each field it may refuse, as the form names it. */
    private const FIELDS = ['from' => 'from', 'to' => 'to', 'location' => 'place', 'region' => 'place', 'category' => 'category', 'q' => 'q', 'sort' => 'sort', 'page' => 'page'];

    public function __construct(private readonly string $mode = self::ALL)
    {
    }

    public function show(Route $route, Asking $asking): Shown
    {
        $strings = new Strings($route->language);
        $places = Places::of($asking);
        $categories = $asking->get('/categories', [], Asking::REFERENCE_LIST)['data'];

        // What the visitor chose, which the form shows; the page's own filter is not theirs to change.
        $chosen = match ($this->mode) {
            self::LOCATION => array_diff_key($route->filters, ['place' => true]),
            self::CATEGORY => array_diff_key($route->filters, ['category' => true]),
            default => $route->filters,
        };
        $filters = match ($this->mode) {
            self::LOCATION => $chosen + ['place' => (string) $route->ref],
            self::CATEGORY => $chosen + ['category' => (string) $route->ref],
            default => $chosen,
        };

        [$heading, $title, $description] = $this->words($route, $strings, $places, $categories, $filters);

        $searching = isset($filters['q']);
        $byRelevance = $searching && ($filters['sort'] ?? 'relevance') === 'relevance';
        $refusals = [];
        $answer = null;

        try {
            $answer = $asking->get('/events', self::query($filters), $searching ? null : Asking::LISTING);
        } catch (Refused $refused) {
            $refusals = self::refusals($refused, $strings);
        }

        $lines = new EventLines($route->language);
        $meta = $answer['meta'] ?? null;

        return new Shown('pages/events.twig', [
            'here' => ['name' => $route->name, 'ref' => $route->ref],
            'fixed' => $this->mode,
            'filters' => $chosen,
            'places' => $places->regions,
            'categories' => $categories,
            'searching' => $searching,
            'heading' => $heading,
            'refusals' => $refusals,
            'events' => $answer === null ? null : ($byRelevance
                ? ['one_by_one' => $lines->oneByOne($answer['data'])]
                : ['by_day' => $lines->byDay($answer['data'], $filters['from'] ?? $asking->today(), $asking->today())]),
            'total' => $meta['total'] ?? 0,
            'page' => $meta['current_page'] ?? 1,
            'pages' => $meta['last_page'] ?? 1,
        ], $title, $description, self::isPlain($chosen));
    }

    /**
     * The page's heading, title and description: the listing's own, or its Location's or Category's, by name. A ref
     * that is neither is not found.
     *
     * @return array{string, string, string}
     */
    private function words(Route $route, Strings $strings, Places $places, array $categories, array $filters): array
    {
        if ($this->mode === self::LOCATION) {
            $name = $places->nameOf((string) $route->ref) ?? throw new NotThere("No Location {$route->ref}");
            $heading = $strings->get('events.titleIn', $name);

            return [$heading, $heading, $strings->get('location.description', $name)];
        }

        if ($this->mode === self::CATEGORY) {
            $category = array_values(array_filter($categories, fn (array $category) => $category['ref'] === $route->ref))[0]
                ?? throw new NotThere("No Category {$route->ref}");

            return [$category['name'], $strings->get('category.title', $category['name']), $strings->get('category.description', $category['name'])];
        }

        $placeName = isset($filters['place']) ? $places->nameOf($filters['place']) : null;
        $heading = $placeName !== null ? $strings->get('events.titleIn', $placeName) : $strings->get('events.title');

        return [$heading, $heading, $strings->get('events.description')];
    }

    /** What the API is asked for the filters given: the place as a Region or a Location, and 20 to a page. */
    public static function query(array $filters): array
    {
        $query = array_intersect_key($filters, array_flip(['from', 'to', 'category', 'q', 'page']));

        if (isset($filters['place'])) {
            $query += Places::query($filters['place']);
        }

        if (isset($filters['q'], $filters['sort'])) {
            $query['sort'] = $filters['sort'];
        }

        return $query + ['per_page' => self::PER_PAGE];
    }

    /** Whether it is the page as everyone sees it, which search engines may keep: nothing chosen but its page. */
    private static function isPlain(array $chosen): bool
    {
        return array_diff_key($chosen, ['page' => true]) === [];
    }

    /** @return array<string, string> the site's own words for each field refused, or for the whole form under `form` */
    private static function refusals(Refused $refused, Strings $strings): array
    {
        if ($refused->isTooOften()) {
            return ['form' => $strings->get('events.tooOften')];
        }

        $refusals = [];

        foreach (array_keys($refused->fields) as $field) {
            $ours = self::FIELDS[$field] ?? 'page';
            $refusals[$ours] = $strings->get("events.refused.{$ours}");
        }

        return $refusals;
    }
}
