<?php

declare(strict_types=1);

namespace EventsSite\Tests\Support;

use EventsSite\Api\Api;
use EventsSite\Api\ApiFailed;
use EventsSite\Api\NotThere;
use EventsSite\Api\Refused;
use EventsSite\Language;
use LogicException;

/**
 * The API as it answered when its answers were last captured (`bin/capture-answers.php`): the real API in its known
 * test state, never an answer written by hand. A question that was not captured is a mistake in the test, not a
 * failure of the API.
 */
final class SavedApi implements Api
{
    /** @var list<array{path: string, query: array, language: Language, visitor: string}> */
    public array $asked = [];

    /** @var array<string, array<mixed>|\Throwable> */
    private array $instead = [];

    /**
     * Every form sent, with its fields and the names of its files.
     *
     * @var list<array{path: string, fields: array<string, string>, files: array<string, \EventsSite\Upload>, language: Language, visitor: string}>
     */
    public array $sent = [];

    /** What the API says when it takes a Suggestion (`SuggestionController::store()`, 201). */
    public const TAKEN = ['message' => 'Thank you. Your suggestion has been sent.'];

    /**
     * Have one path answered otherwise, whatever its query: for what the known state cannot show, as a price or a
     * page beyond the first. The answer should be a saved one changed (`answer()`), so that its shape stays the API's.
     *
     * @param array<mixed>|\Throwable $answer
     */
    public function instead(string $path, array|\Throwable $answer): self
    {
        $this->instead[$path] = $answer;

        return $this;
    }

    /**
     * A form sent is never captured, sending one changing what the API holds: it is taken as the API takes a
     * Suggestion, or answered as `instead()` says. The real check sends one to the real API.
     */
    public function post(string $path, array $fields, array $files, Language $language, string $visitor): array
    {
        $this->sent[] = ['path' => $path, 'fields' => $fields, 'files' => $files, 'language' => $language, 'visitor' => $visitor];

        if (isset($this->instead[$path])) {
            return $this->instead[$path] instanceof \Throwable ? throw $this->instead[$path] : $this->instead[$path];
        }

        return self::TAKEN;
    }

    /** A saved answer, to be changed and given `instead()`. */
    public static function answer(string $path, array $query, Language $language): array
    {
        return json_decode((string) file_get_contents(self::fileFor($path, $query, $language)), true, flags: JSON_THROW_ON_ERROR)['answer'];
    }

    public function get(string $path, array $query, Language $language, string $visitor): array
    {
        $this->asked[] = ['path' => $path, 'query' => $query, 'language' => $language, 'visitor' => $visitor];

        if (isset($this->instead[$path])) {
            return $this->instead[$path] instanceof \Throwable ? throw $this->instead[$path] : $this->instead[$path];
        }

        $file = self::fileFor($path, $query, $language);

        if (!is_file($file)) {
            throw new LogicException("No saved answer to {$path} in {$language->value}: add it to tests/answers/questions.php and capture the answers again.");
        }

        $saved = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return match ($saved['status']) {
            200 => $saved['answer'],
            404 => throw new NotThere($path),
            422, 429 => throw new Refused($saved['status'], $saved['answer']['errors'] ?? [], $saved['answer']['message'] ?? ''),
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
