<?php

declare(strict_types=1);

namespace EventsSite;

/**
 * A file a visitor sent with a form, as the outer layer received it: where it is on disk for now, the name and kind
 * the browser gave it, its size, and PHP's word on how the upload went (`UPLOAD_ERR_*`). Nothing in it is trusted:
 * the API judges the file itself.
 */
final class Upload
{
    public function __construct(
        public readonly string $path,
        public readonly string $name,
        public readonly string $type,
        public readonly int $size,
        public readonly int $error = UPLOAD_ERR_OK,
    ) {
    }

    /** One file of PHP's `$_FILES`, or null where none was chosen. */
    public static function fromPhp(mixed $file): ?self
    {
        if (!is_array($file) || !is_string($file['tmp_name'] ?? null) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }

        return new self($file['tmp_name'], (string) ($file['name'] ?? ''), (string) ($file['type'] ?? ''), (int) ($file['size'] ?? 0), (int) $file['error']);
    }

    /** Whether it arrived whole. One too big for the server never reaches the page (`UPLOAD_ERR_INI_SIZE`). */
    public function arrived(): bool
    {
        return $this->error === UPLOAD_ERR_OK && is_file($this->path);
    }

    /** Whether it did not arrive for being too big. */
    public function tooBig(): bool
    {
        return in_array($this->error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true);
    }
}
