<?php

declare(strict_types=1);

namespace EventsSite\Tests;

use EventsSite\Api\ApiFailed;
use EventsSite\Api\Refused;
use EventsSite\Tests\Support\Browsing;
use EventsSite\Upload;
use PHPUnit\Framework\TestCase;

/**
 * Suggesting an event. A form sent is never captured from the API, sending one changing what it holds: the saved
 * API takes it as the API takes a Suggestion, and the real check sends one to the real API.
 */
final class SuggestPageTest extends TestCase
{
    use Browsing;

    private string $poster;

    protected function setUp(): void
    {
        $this->setUpBrowsing();
        // A real picture, one pixel of PNG, for a poster.
        $this->poster = tempnam(sys_get_temp_dir(), 'poster');
        file_put_contents($this->poster, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
    }

    protected function tearDown(): void
    {
        @unlink($this->poster);
    }

    /** The form as shown now, and the time it says it was shown, signed. */
    private function shown(string $path = '/en/suggest'): string
    {
        return $this->attribute($this->page($this->visit($path)), '//input[@name="shown"]/@value');
    }

    /** A form shown at noon and sent a minute later, as a person does. */
    private function sendAsAPerson(array $form, array $files = [], string $path = '/en/suggest')
    {
        $form += ['shown' => $this->shown($path)];
        $this->time = '12:01';

        return $this->send($path, $form, $files);
    }

    public function test_the_form(): void
    {
        $response = $this->visit('/en/suggest');
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('Suggest an event', $this->text($page, '//main//h1'));
        $this->assertSame('multipart/form-data', $this->attribute($page, '//form[@class="suggest-form"]/@enctype'));
        $this->assertSame('/en/suggest', $this->attribute($page, '//form[@class="suggest-form"]/@action'));
        $this->assertSame(['content', 'link', 'email', 'poster', 'website', 'shown'], array_map(fn ($field) => $field->getAttribute('name'), iterator_to_array($page->query('//form[@class="suggest-form"]//*[@name]'))));
        $this->assertSame('-1', $this->attribute($page, '//input[@name="website"]/@tabindex'));
        $this->assertSame('true', $this->attribute($page, '//input[@name="website"]/parent::div/@aria-hidden'));
        $this->assertMatchesRegularExpression('#^/assets/suggest\.js\?v=[0-9a-f]{12}$#', $this->attribute($page, '//script/@src'));
    }

    public function test_the_form_in_spanish(): void
    {
        $page = $this->page($this->visit('/es/sugerir'));

        $this->assertSame('Sugerir un evento', $this->text($page, '//main//h1'));
        $this->assertSame('/es/sugerir', $this->attribute($page, '//form[@class="suggest-form"]/@action'));
        $this->assertSame('/en/suggest', $this->attribute($page, '//a[@hreflang="en"]/@href'));
    }

    public function test_a_suggestion_is_sent_on_and_the_visitor_thanked(): void
    {
        $response = $this->sendAsAPerson(['content' => '  Jazz in the square on Friday at nine.  ', 'link' => 'https://jazz.example/friday', 'email' => 'me@example.com']);

        $this->assertSame(303, $response->status);
        $this->assertSame('/en/suggest?sent=1', $response->headers['Location']);
        $this->assertCount(1, $this->api->sent);
        $this->assertSame('/suggestions', $this->api->sent[0]['path']);
        $this->assertSame(['content' => 'Jazz in the square on Friday at nine.', 'link' => 'https://jazz.example/friday', 'email' => 'me@example.com'], $this->api->sent[0]['fields']);
        $this->assertSame([], $this->api->sent[0]['files']);
        $this->assertSame('en', $this->api->sent[0]['language']->value);
        $this->assertSame('203.0.113.7', $this->api->sent[0]['visitor']);
    }

    public function test_what_was_left_empty_is_not_sent(): void
    {
        $this->sendAsAPerson(['content' => 'A market.', 'link' => '', 'email' => ' ']);

        $this->assertSame(['content' => 'A market.'], $this->api->sent[0]['fields']);
    }

    public function test_a_poster_is_sent_with_it(): void
    {
        $poster = new Upload($this->poster, 'cartel.png', 'image/png', (int) filesize($this->poster));

        $this->sendAsAPerson(['content' => 'See the poster.'], ['poster' => $poster]);

        $this->assertSame(['poster' => $poster], $this->api->sent[0]['files']);
    }

    public function test_the_thank_you(): void
    {
        $response = $this->visit('/es/sugerir', ['enviado' => '1']);
        $page = $this->page($response);

        $this->assertSame('Gracias', $this->text($page, '//main//h1'));
        $this->assertSame('noindex', $this->attribute($page, '//meta[@name="robots"]/@content'));
        $this->assertSame(0, $page->query('//form[@class="suggest-form"]')->length);
    }

    public function test_in_spanish_the_thank_you_is_in_spanish(): void
    {
        $response = $this->sendAsAPerson(['content' => 'Un mercadillo.'], path: '/es/sugerir');

        $this->assertSame('/es/sugerir?enviado=1', $response->headers['Location']);
        $this->assertSame('es', $this->api->sent[0]['language']->value);
    }

    public function test_a_program_that_fills_in_the_hidden_field_is_thanked_and_nothing_is_sent(): void
    {
        $response = $this->sendAsAPerson(['content' => 'Cheap watches', 'website' => 'https://spam.example']);

        $this->assertSame([303, '/en/suggest?sent=1'], [$response->status, $response->headers['Location']]);
        $this->assertSame([], $this->api->sent);
    }

    public function test_a_form_sent_within_seconds_of_being_shown_is_thanked_and_nothing_is_sent(): void
    {
        $shown = $this->shown();

        $response = $this->send('/en/suggest', ['content' => 'Cheap watches', 'shown' => $shown]);

        $this->assertSame([303, '/en/suggest?sent=1'], [$response->status, $response->headers['Location']]);
        $this->assertSame([], $this->api->sent);
    }

    public function test_a_time_the_site_did_not_sign_is_thanked_and_nothing_is_sent(): void
    {
        $this->time = '12:01';
        $forged = (string) ((new \DateTimeImmutable(self::captured() . ' 11:00', new \DateTimeZone('Europe/Madrid')))->getTimestamp()) . '.' . str_repeat('a', 64);

        $this->send('/en/suggest', ['content' => 'Cheap watches', 'shown' => $forged]);
        $this->send('/en/suggest', ['content' => 'Cheap watches']);

        $this->assertSame([], $this->api->sent);
    }

    public function test_a_form_left_open_a_day_is_shown_again_to_be_sent_afresh(): void
    {
        $shown = $this->shown();
        $this->day = self::inDays(2);

        $response = $this->send('/en/suggest', ['content' => 'A concert.', 'shown' => $shown]);
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('This form was open for a long time. Please check it and send it again.', $this->text($page, '//p[@role="alert"]'));
        $this->assertSame('A concert.', $this->text($page, '//textarea'));
        $this->assertNotSame($shown, $this->attribute($page, '//input[@name="shown"]/@value'));
        $this->assertSame([], $this->api->sent);
    }

    public function test_what_the_api_refuses_is_said_beside_its_field_with_what_was_typed_kept(): void
    {
        $this->api->instead('/suggestions', new Refused(422, ['content' => ['The content field is required.'], 'link' => ['The link field must be a valid URL.']]));

        $response = $this->sendAsAPerson(['content' => '', 'link' => 'not a link', 'email' => 'me@example.com']);
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertSame('Please say what the event is, in up to 5,000 letters.', $this->text($page, '//p[@id="s-content-refused"]'));
        $this->assertSame("That isn't a web address. It should start with https://.", $this->text($page, '//p[@id="s-link-refused"]'));
        $this->assertSame('true', $this->attribute($page, '//input[@id="s-link"]/@aria-invalid'));
        $this->assertSame('not a link', $this->attribute($page, '//input[@id="s-link"]/@value'));
        $this->assertSame('me@example.com', $this->attribute($page, '//input[@id="s-email"]/@value'));
    }

    public function test_a_refusal_in_spanish_is_in_spanish(): void
    {
        $this->api->instead('/suggestions', new Refused(422, ['email' => ['The email field must be a valid email address.']]));

        $page = $this->page($this->sendAsAPerson(['content' => 'Un mercadillo.', 'email' => 'yo@'], path: '/es/sugerir'));

        $this->assertSame('Eso no es una dirección de correo.', $this->text($page, '//p[@id="s-email-refused"]'));
    }

    public function test_a_poster_refused_or_not_says_to_choose_it_again(): void
    {
        $poster = new Upload($this->poster, 'cartel.png', 'image/png', (int) filesize($this->poster));

        $this->api->instead('/suggestions', new Refused(422, ['poster' => ['The poster field must be an image.']]));
        $refused = $this->page($this->sendAsAPerson(['content' => 'A poster.'], ['poster' => $poster]));

        $this->api->instead('/suggestions', new Refused(422, ['link' => ['The link field must be a valid URL.']]));
        $this->time = '12:00';
        $notRefused = $this->page($this->sendAsAPerson(['content' => 'A poster.', 'link' => 'x'], ['poster' => $poster]));

        $this->assertSame("That isn't a photo the site can take: it must be a JPEG, PNG or WebP of up to 5 MB.", $this->text($refused, '//p[@id="s-poster-refused"]'));
        $this->assertSame('If you chose a poster, please choose it again.', $this->text($notRefused, '//p[@id="s-poster-refused"]'));
    }

    public function test_a_poster_too_big_to_arrive_is_said_and_nothing_is_sent(): void
    {
        $tooBig = new Upload('', 'huge.jpg', '', 0, UPLOAD_ERR_INI_SIZE);

        $page = $this->page($this->sendAsAPerson(['content' => 'A poster.'], ['poster' => $tooBig]));

        $this->assertSame('That photo is too big to send. Please choose a smaller one.', $this->text($page, '//p[@id="s-poster-refused"]'));
        $this->assertSame([], $this->api->sent);
    }

    public function test_sending_too_often_is_said_over_the_form(): void
    {
        $this->api->instead('/suggestions', new Refused(429, [], 'Too Many Attempts.'));

        $page = $this->page($this->sendAsAPerson(['content' => 'Another one.']));

        $this->assertSame("You've sent several suggestions in a short time. Please wait a while and try again.", $this->text($page, '//p[@role="alert"]'));
        $this->assertSame('Another one.', $this->text($page, '//textarea'));
    }

    public function test_when_the_api_fails_what_was_typed_is_kept_and_the_failure_logged(): void
    {
        $this->api->instead('/suggestions', new ApiFailed('No answer'));

        $response = $this->sendAsAPerson(['content' => 'Fireworks at midnight.']);
        $page = $this->page($response);

        $this->assertSame(200, $response->status);
        $this->assertStringStartsWith("Your suggestion couldn't be sent just now.", $this->text($page, '//p[@role="alert"]'));
        $this->assertSame('Fireworks at midnight.', $this->text($page, '//textarea'));
        $this->assertCount(1, $this->log->failures);
    }

    public function test_what_was_typed_is_written_back_as_text(): void
    {
        $this->api->instead('/suggestions', new Refused(422, ['link' => ['The link field must be a valid URL.']]));

        $body = $this->sendAsAPerson(['content' => '</textarea><script>alert(1)</script>', 'link' => '"><script>alert(2)</script>'])->body;

        $this->assertStringNotContainsString('<script>alert', $body);
        $this->assertStringContainsString('&lt;/textarea&gt;&lt;script&gt;alert(1)&lt;/script&gt;', $body);
    }
}
