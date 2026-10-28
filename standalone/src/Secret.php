<?php

declare(strict_types=1);

namespace EventsSite\Standalone;

/**
 * The site's secret, which signs what it must know it gave out itself (when a form was shown). Given by the
 * configuration, or else made at random the first time it is needed and kept in `var/secret`, readable by the site
 * alone. In WordPress it will be one of WordPress's own salts.
 */
final class Secret
{
    public static function of(array $config): string
    {
        if (($config['secret'] ?? '') !== '') {
            return (string) $config['secret'];
        }

        $file = $config['var'] . '/secret';
        $kept = @file_get_contents($file);

        if (is_string($kept) && strlen(trim($kept)) >= 32) {
            return trim($kept);
        }

        if (!is_dir($config['var'])) {
            @mkdir($config['var'], 0750, true);
        }

        $made = bin2hex(random_bytes(32));
        @file_put_contents($file, $made, LOCK_EX);
        @chmod($file, 0600);

        // Whichever was written first is the one: two visits at once must not each keep their own.
        $kept = @file_get_contents($file);

        return is_string($kept) && strlen(trim($kept)) >= 32 ? trim($kept) : $made;
    }
}
