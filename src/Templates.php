<?php

declare(strict_types=1);

namespace EventsSite;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * The Twig that makes the pages. Every value is escaped as HTML unless a template says otherwise, which nothing
 * the API gives ever is: what visitors and other websites wrote goes in as text. A template knows its page's
 * language as `language`; `t()` gives the site's own words in it and `path()` the address of a page in it.
 */
final class Templates
{
    /**
     * @param array<string, string> $namespaces further folders of templates, by the name a template is found by
     *                                          (`@standalone/frame.twig`), for the outer layer's own
     */
    public static function environment(array $namespaces = [], string|false $cache = false): Environment
    {
        $loader = new FilesystemLoader(dirname(__DIR__) . '/templates');

        foreach ($namespaces as $name => $folder) {
            $loader->addPath($folder, $name);
        }

        $twig = new Environment($loader, [
            'cache' => $cache,
            // A changed template is compiled again, on the server too, so a deploy needs no step for it.
            'auto_reload' => true,
            'autoescape' => 'html',
            'strict_variables' => true,
        ]);

        $twig->addFunction(new TwigFunction(
            't',
            fn (array $context, string $key, array $fillers = []): string => (new Strings($context['language']))->get($key, ...$fillers),
            ['needs_context' => true, 'is_variadic' => true],
        ));

        $twig->addFunction(new TwigFunction(
            'path',
            fn (array $context, string $name, ?string $ref = null, array $filters = []): string => Addresses::of($name, $context['language'], $ref, $filters),
            ['needs_context' => true],
        ));

        return $twig;
    }
}
