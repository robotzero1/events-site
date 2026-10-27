<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * The site's two languages. Each is the first part of every address (`/es/…`, `/en/…`) and what the API is asked
 * in. Spanish comes first: it is what a visitor who asks for neither is given.
 */
enum Language: string
{
    case Es = 'es';
    case En = 'en';

    public function other(): self
    {
        return $this === self::Es ? self::En : self::Es;
    }

    /**
     * The language a browser prefers of the two, from its `Accept-Language` header, by the weight it gives each.
     * Spanish when it names neither.
     */
    public static function preferredBy(?string $acceptLanguage): self
    {
        $best = null;
        $bestWeight = 0.0;

        foreach (explode(',', (string) $acceptLanguage) as $position => $part) {
            $pieces = explode(';', trim($part));
            $tag = strtolower(trim($pieces[0]));
            $weight = 1.0;

            foreach (array_slice($pieces, 1) as $parameter) {
                if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $parameter, $found)) {
                    $weight = (float) $found[1];
                }
            }

            $language = self::tryFrom(explode('-', $tag)[0]);

            // The earlier of two with the same weight wins, as the header lists them in order.
            if ($language !== null && $weight > $bestWeight) {
                $best = $language;
                $bestWeight = $weight;
            }
        }

        return $best ?? self::Es;
    }
}
