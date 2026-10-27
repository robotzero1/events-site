<?php

declare(strict_types=1);

namespace EventsSite\Tests\Real;

use EventsSite\Api\NotThere;
use EventsSite\Language;
use EventsSite\Standalone\CurlApi;
use EventsSite\Tests\Support\SavedApi;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The saved answers the tests stand on are still how the real API answers: the same fields, holding the same kinds
 * of value. When the API changes a resource this fails, and the answers are captured again and the pages that read
 * them looked at. Run against the API in its known state: `php bin/with-local-api.php vendor/bin/phpunit --testsuite real`.
 */
final class AnswersStillMatchTest extends TestCase
{
    /** @return array<string, array{string, array, string}> */
    public static function questions(): array
    {
        $questions = [];

        foreach (require dirname(__DIR__) . '/answers/questions.php' as [$path, $query, $language]) {
            $questions["{$language} {$path}" . ($query === [] ? '' : '?' . http_build_query($query))] = [$path, $query, $language];
        }

        return $questions;
    }

    #[DataProvider('questions')]
    public function test_the_api_answers_as_it_did_when_its_answer_was_saved(string $path, array $query, string $language): void
    {
        $base = getenv('EVENTS_API') ?: $this->markTestSkipped('EVENTS_API is not set: run this through bin/with-local-api.php.');
        $language = Language::from($language);
        $saved = json_decode((string) file_get_contents(SavedApi::fileFor($path, $query, $language)), true);

        try {
            $live = ['status' => 200, 'answer' => (new CurlApi($base, seconds: 30))->get($path, $query, $language, '127.0.0.1')];
        } catch (NotThere) {
            $live = ['status' => 404, 'answer' => null];
        }

        $this->assertSame($saved['status'], $live['status']);
        $this->assertSame(self::shape($saved['answer']), self::shape($live['answer']));
    }

    /**
     * What an answer is made of without its values: each field's name and kind, and of a list, the shape of its
     * first item. A field that is sometimes null is not told from one of another kind, so null is its own kind.
     */
    private static function shape(mixed $value): mixed
    {
        if (!is_array($value)) {
            return get_debug_type($value);
        }

        if (array_is_list($value)) {
            return $value === [] ? 'empty list' : ['list of' => self::shape($value[0])];
        }

        $shape = [];

        foreach ($value as $key => $item) {
            $shape[$key] = self::shape($item);
        }

        ksort($shape);

        return $shape;
    }
}
