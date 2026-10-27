<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

/**
 * The site's stylesheets and fonts, sent from `/assets/`: the core's look (`assets/`) and the standalone frame's own
 * (`standalone/assets/`). A stylesheet's address carries a fingerprint of its contents (`?v=`), so a browser keeps
 * it for a year and still fetches a changed one at once. Only files that are there and of a kind named here are
 * sent; anything else is not found. On the server nginx may send these itself.
 */
final class Assets
{
    private const TYPES = [
        'css' => 'text/css; charset=utf-8',
        'woff2' => 'font/woff2',
        'js' => 'text/javascript; charset=utf-8',
    ];

    /** @var array<string, string> */
    private array $fingerprints = [];

    /** @param list<string> $folders looked in in order */
    public function __construct(private readonly array $folders)
    {
    }

    public static function standard(): self
    {
        return new self([dirname(__DIR__, 2) . '/assets', dirname(__DIR__) . '/assets']);
    }

    /** The address of an asset, with its fingerprint. */
    public function address(string $name): string
    {
        $file = $this->file($name) ?? throw new \LogicException("There is no asset {$name}.");

        return '/assets/' . $name . '?v=' . ($this->fingerprints[$name] ??= substr(hash_file('sha256', $file), 0, 12));
    }

    /** The response for an address under `/assets/`, or null if it is no asset. */
    public function respond(string $path): ?Response
    {
        if (!str_starts_with($path, '/assets/')) {
            return null;
        }

        $file = $this->file(substr($path, strlen('/assets/')));

        if ($file === null) {
            return null;
        }

        return new Response(200, [
            'Content-Type' => self::TYPES[pathinfo($file, PATHINFO_EXTENSION)],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ], (string) file_get_contents($file));
    }

    private function file(string $name): ?string
    {
        // Letters, digits, dashes and dots, in folders no deeper than one, so that nothing outside can be named.
        if (!preg_match('#^([a-z0-9-]+/)?[a-z0-9][a-z0-9.-]*\.([a-z0-9]+)$#', $name, $parts) || !isset(self::TYPES[$parts[2]]) || str_contains($name, '..')) {
            return null;
        }

        foreach ($this->folders as $folder) {
            if (is_file("{$folder}/{$name}")) {
                return "{$folder}/{$name}";
            }
        }

        return null;
    }
}
