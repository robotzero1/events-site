<?php

declare(strict_types=1);

namespace EventsSite;

/** Numbers as each language writes them: `4.50` and `1,200` in English, `4,50` and `1.200` in Spanish. */
final class Numbers
{
    /** A number with `$places` decimals, or none when it is whole. */
    public static function write(float $number, Language $language, int $places): string
    {
        $places = fmod($number, 1.0) === 0.0 ? 0 : $places;

        return $language === Language::Es
            ? number_format($number, $places, ',', '.')
            : number_format($number, $places, '.', ',');
    }

    /** A price: two decimals when it has cents. */
    public static function price(float $price, Language $language): string
    {
        return self::write($price, $language, 2);
    }

    /** A rating, to one decimal as the API gives it. */
    public static function rating(float $rating, Language $language): string
    {
        return self::write($rating, $language, 1);
    }
}
