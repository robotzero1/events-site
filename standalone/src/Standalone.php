<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

use EventsSite\Api\Api;
use EventsSite\Cache\Cache;
use EventsSite\Log;
use EventsSite\Site;
use EventsSite\Templates;
use EventsSite\Upload;
use EventsSite\Visit;
use Twig\Environment;

/**
 * The site running on its own: the core's answer put in the site's own frame and sent. This is the layer a
 * WordPress plugin replaces, the theme then being the frame.
 */
final class Standalone
{
    private Site $site;

    private Assets $assets;

    /** @param string $siteUrl where the site answers, with no `/` at the end, for addresses given in full */
    public function __construct(
        Api $api,
        Cache $cache,
        Log $log,
        private readonly Environment $twig,
        private readonly string $siteUrl,
        ?array $pages = null,
        ?Assets $assets = null,
        ?\Closure $now = null,
        string $secret = 'not a secret: for tests',
    ) {
        $this->site = new Site($api, $cache, $log, $twig, $pages, $now, $secret);
        $this->assets = $assets ?? Assets::standard();
    }

    /** The site as its configuration says (`config.php`). */
    public static function configured(array $config): self
    {
        return new self(
            new CurlApi($config['api'], $config['api_host'] ?: null),
            new FileCache($config['var'] . '/cache'),
            new FileLog($config['var'] . '/log/site.log'),
            self::templates($config['var'] . '/twig'),
            rtrim($config['site_url'], '/'),
            secret: Secret::of($config),
        );
    }

    /** The core's templates and the frame's, compiled into `$cache` if given. */
    public static function templates(string|false $cache = false): Environment
    {
        return Templates::environment(['standalone' => dirname(__DIR__) . '/templates'], $cache);
    }

    public function respond(Visit $visit): Response
    {
        if (str_starts_with($visit->path, '/assets/')) {
            return $this->assets->respond($visit->path) ?? new Response(404, ['Content-Type' => 'text/plain; charset=utf-8'], 'Not found');
        }

        $answer = $this->site->answer($visit);

        if ($answer->isRedirect()) {
            return new Response($answer->status, [
                'Location' => $answer->location,
                'Vary' => 'Accept-Language',
            ], '');
        }

        $body = $this->twig->render('@standalone/frame.twig', [
            'language' => $answer->language,
            'answer' => $answer,
            'site_url' => $this->siteUrl,
            'other' => $answer->alternates[$answer->language->other()->value] ?? null,
            'stylesheets' => [$this->assets->address('site.css'), $this->assets->address('frame.css')],
            'scripts' => array_map(fn (string $name) => $this->assets->address($name), $answer->scripts),
        ]);

        $headers = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Language' => $answer->language->value,
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        if ($answer->status === 503) {
            $headers['Retry-After'] = '300';
        }

        return new Response($answer->status, $headers, $body);
    }

    /** A visit as PHP's own server variables give it, with a form's fields and files when one was sent. */
    public static function visitFrom(array $server, array $query, array $form = [], array $files = []): Visit
    {
        $path = parse_url((string) ($server['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));

        return new Visit(
            is_string($path) ? rawurldecode($path) : '/',
            $query,
            $server['HTTP_ACCEPT_LANGUAGE'] ?? null,
            (string) ($server['REMOTE_ADDR'] ?? '127.0.0.1'),
            $method,
            $method === 'POST' ? $form : [],
            $method === 'POST' ? array_filter(array_map([Upload::class, 'fromPhp'], $files)) : [],
        );
    }
}
