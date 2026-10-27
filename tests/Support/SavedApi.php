<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Api\NotThere;
use EventsSite\Language;
use LogicException;

/**
 * The API as it answered when its answers were last captured (`bin/capture-answers.php`): the real API in its known
 * test state, never an answer written by hand. A question that was not captured is a mistake in the test, not a
 * failure of the API.
 */
final class SavedApi implements Api
{
    /** @var list<array{path: string, language: Language, visitor: string}> */
    public array $asked = [];

    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        $this->asked[] = ['path' => $path, 'language' => $language, 'visitor' => $visitor];

        $file = self::fileFor($path, $query, $language);

        if (!is_file($file)) {
            throw new LogicException("No saved answer to {$path} in {$language->value}: add it to tests/answers/questions.php and capture the answers again.");
        }

        $saved = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return match ($saved['status']) {
            200 => $saved['answer'],
            404 => throw new NotThere($path),
            default => throw new ApiFailed("{$path} answered {$saved['status']}"),
        };
    }

    /** Where the answer to a question is kept: a file named after it, by language. */
    public static function fileFor(string $path, array $query, Language $language): string
    {
        ksort($query);
        $name = trim(str_replace('/', '--', $path), '-') . ($query === [] ? '' : '@' . http_build_query($query));

        return dirname(__DIR__) . "/answers/{$language->value}/" . preg_replace('/[^A-Za-z0-9@=&._-]/', '_', $name) . '.json';
    }
}
