<?php

declare(strict_types=1);

namespace EventsSite;

use EventsSite\Api\Api;
use EventsSite\Api\Asking;
use EventsSite\Api\NotThere;
use EventsSite\Api\Unavailable;
use EventsSite\Cache\Cache;
use EventsSite\Pages\About;
use EventsSite\Pages\Event;
use EventsSite\Pages\Events;
use EventsSite\Pages\Home;
use EventsSite\Pages\Page;
use EventsSite\Pages\Suggest;
use EventsSite\Pages\Venue;
use Closure;
use Twig\Environment;

/**
 * The site, as the core knows it: a visit in, an answer out. It finds the page an address is, has it ask the API
 * through the cache, and makes its content; the outer layer sends it in a frame or a theme. An address that is no
 * page, or names what the API does not have, is "not found" in the address's language; an API that fails with
 * nothing kept makes the page "unavailable" (503), so that search engines come back rather than drop it.
 */
final class Site
{
    /** @var array<string, Page> */
    private array $pages;

    /**
     * @param array<string, Page>|null $pages by the name `Addresses` knows each by; the site's own when null
     * @param Closure(): int|null $now the time as a Unix time, the real one when null
     * @param string $secret what signs what the site gives out and must know again (when a form was shown)
     * @param string $siteUrl where the site answers, with no `/` at the end, for the addresses the sitemap gives in full
     * @param bool $indexed whether search engines may read it; when not, `robots.txt` turns them all away
     */
    public function __construct(
        private readonly Api $api,
        private readonly Cache $cache,
        private readonly Log $log,
        private readonly Environment $twig,
        ?array $pages = null,
        private readonly ?Closure $now = null,
        string $secret = '',
        private readonly string $siteUrl = '',
        private readonly bool $indexed = true,
    ) {
        $this->pages = $pages ?? self::pages($secret);
    }

    /** @return array<string, Page> */
    public static function pages(string $secret = ''): array
    {
        return [
            'suggest' => new Suggest($secret),
            'home' => new Home(),
            'about' => new About(),
            'events' => new Events(),
            'event' => new Event(),
            'venue' => new Venue(),
            'location' => new Events(Events::LOCATION),
            'category' => new Events(Events::CATEGORY),
        ];
    }

    public function answer(Visit $visit): Answer
    {
        // What search engines read before any page: where everything is, and that they may.
        if ($visit->path === '/sitemap.xml') {
            return $this->sitemap($visit);
        }

        if ($visit->path === '/robots.txt') {
            return Answer::document('text/plain; charset=utf-8', Sitemap::robots($this->siteUrl, $this->indexed));
        }

        // The bare site: whichever language the browser prefers. A redirect that depends on the browser is not
        // one to remember, so it is a 302.
        if ($visit->path === '/' || $visit->path === '') {
            $language = Language::preferredBy($visit->acceptLanguage);

            return Answer::redirect(302, $language, Addresses::of('home', $language));
        }

        if (preg_match('#^/(es|en)$#', $visit->path, $found)) {
            $language = Language::from($found[1]);

            return Answer::redirect(301, $language, Addresses::of('home', $language));
        }

        $route = Addresses::match($visit->path, $visit->query);

        if ($route === null || !isset($this->pages[$route->name])) {
            return $this->notFound($this->languageOf($visit));
        }

        $asking = new Asking($this->api, $this->cache, $this->log, $route->language, $visit->visitor, $this->now);

        try {
            $shown = $this->pages[$route->name]->show($route, $asking, $visit);
        } catch (NotThere) {
            return $this->notFound($route->language);
        } catch (Unavailable) {
            return $this->unavailable($route->language);
        }

        if ($shown->redirect !== null) {
            return Answer::redirect(303, $route->language, $shown->redirect);
        }

        $content = $this->twig->render($shown->template, ['language' => $route->language, 'route' => $route] + $shown->vars);

        return Answer::page(200, $route->language, $route->name, $content, $shown->title, $shown->description, [
            $route->language->value => Addresses::of($route->name, $route->language, $route->ref, $route->filters),
            $route->language->other()->value => Addresses::inOther($route),
        ], $shown->indexed, $shown->scripts, $shown->image, $shown->structured);
    }

    private function sitemap(Visit $visit): Answer
    {
        $asking = new Asking($this->api, $this->cache, $this->log, Language::Es, $visit->visitor, $this->now);

        try {
            return Answer::document('application/xml; charset=utf-8', (new Sitemap($this->siteUrl))->of($asking));
        } catch (Unavailable) {
            return $this->unavailable(Language::Es);
        }
    }

    private function notFound(Language $language): Answer
    {
        $content = $this->twig->render('pages/not-found.twig', ['language' => $language]);

        return Answer::page(404, $language, 'not-found', $content, (new Strings($language))->get('notFound.title'), null, []);
    }

    private function unavailable(Language $language): Answer
    {
        $content = $this->twig->render('pages/unavailable.twig', ['language' => $language]);

        return Answer::page(503, $language, 'unavailable', $content, (new Strings($language))->get('unavailable.title'), null, []);
    }

    /** The language of an address that is no page: its own first part if it has one, else the browser's. */
    private function languageOf(Visit $visit): Language
    {
        if (preg_match('#^/(es|en)/#', $visit->path, $found)) {
            return Language::from($found[1]);
        }

        return Language::preferredBy($visit->acceptLanguage);
    }
}
