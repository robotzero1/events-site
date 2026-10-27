<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Every pair of colours the site reads together passes WCAG 2.2 AA: 4.5 to 1 for text, 3 to 1 for the focus
 * outline against what it is drawn on. The colours are read from the stylesheet itself, so that a change to one is
 * held to this.
 */
final class ContrastTest extends TestCase
{
    /** @return array<string, array{string, string, float}> */
    public static function pairs(): array
    {
        return [
            'text on the page' => ['ink', 'ground', 4.5],
            'text on a panel' => ['ink', 'panel', 4.5],
            'quieter text on the page' => ['muted', 'ground', 4.5],
            'quieter text on a panel' => ['muted', 'panel', 4.5],
            'a link on the page' => ['link', 'ground', 4.5],
            'a link on a panel' => ['link', 'panel', 4.5],
            'a button' => ['accent-ink', 'accent', 4.5],
            'the today mark' => ['today-ink', 'today', 4.5],
            'a rating' => ['star', 'ground', 4.5],
            'a cancelled mark' => ['danger', 'danger-bg', 4.5],
            'a refusal on the page' => ['danger', 'ground', 4.5],
            'a refusal on a panel' => ['danger', 'panel', 4.5],
            'an ended mark' => ['ended', 'ended-bg', 4.5],
            'text in a field' => ['ink', 'field', 4.5],
            'a photo still to come' => ['ph-ink', 'panel', 4.5],
            'the focus outline on the page' => ['focus', 'ground', 3.0],
            'the focus outline on a panel' => ['focus', 'panel', 3.0],
            'a field\'s edge on the page' => ['field-line', 'ground', 3.0],
        ];
    }

    #[DataProvider('pairs')]
    public function test_a_pair_of_colours_can_be_read(string $foreground, string $background, float $least): void
    {
        $colours = self::colours();
        $ratio = self::ratio($colours[$foreground], $colours[$background]);

        $this->assertGreaterThanOrEqual($least, $ratio, sprintf('--%s on --%s is %.2f to 1.', $foreground, $background, $ratio));
    }

    /** @return array<string, string> the stylesheet's colours by name, as `#rrggbb` */
    private static function colours(): array
    {
        $css = (string) file_get_contents(dirname(__DIR__) . '/assets/site.css');
        preg_match_all('/--([a-z0-9-]+):\s*(#[0-9a-f]{6})\s*;/i', $css, $found, PREG_SET_ORDER);

        return array_column($found, 2, 1);
    }

    private static function ratio(string $one, string $other): float
    {
        $lighter = max(self::luminance($one), self::luminance($other));
        $darker = min(self::luminance($one), self::luminance($other));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function luminance(string $colour): float
    {
        $channels = array_map(
            static function (string $hex): float {
                $value = hexdec($hex) / 255;

                return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
            },
            str_split(substr($colour, 1), 2),
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
