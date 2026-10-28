<?php

declare(strict_types=1);

namespace EventsSite\Pages;

use EventsSite\Addresses;
use EventsSite\Api\Asking;
use EventsSite\Api\Refused;
use EventsSite\Api\Unavailable;
use EventsSite\Route;
use EventsSite\Strings;
use EventsSite\Visit;

/**
 * Anyone tells the site about an event, with no account: what it is, a link, an email address to reply to and a
 * poster, sent on to the API (`POST /suggestions`), which keeps it for an Approver. Taken, the visitor is sent on to
 * the thank-you (`?sent=1`), so that reloading sends nothing again. What the API refuses is said beside its field in
 * the site's own words, with what was typed kept.
 *
 * Two quiet checks turn away what a program sends: a field people never see (`website`), which a program fills in,
 * and the time the form was shown, signed so that it cannot be made up, which a person does not answer within
 * seconds. Either is given the same thank-you and nothing reaches the API, so a program learns nothing from it.
 */
final class Suggest implements Page
{
    /** The field people never see. */
    public const HONEYPOT = 'website';

    /** The signed time the form was shown. */
    public const SHOWN = 'shown';

    /** Sent sooner than this after the form was shown, it was sent by a program. */
    public const LEAST_SECONDS = 3;

    /** Shown longer ago than this, the form is shown again to be sent afresh. */
    public const MOST_SECONDS = 86400;

    /** What the visitor fills in, as the API names each. */
    private const FIELDS = ['content', 'link', 'email'];

    public function __construct(private readonly string $secret)
    {
    }

    public function show(Route $route, Asking $asking, Visit $visit): Shown
    {
        $strings = new Strings($route->language);

        if (!$visit->isPost()) {
            return isset($route->filters['sent'])
                ? new Shown('pages/suggested.twig', [], $strings->get('suggest.thanksTitle'), null, indexed: false)
                : $this->form($route, $asking, $strings);
        }

        $sent = Shown::redirect(Addresses::of('suggest', $route->language, filters: ['sent' => '1']));
        $typed = array_map(fn (string $field) => trim($visit->field($field)), array_combine(self::FIELDS, self::FIELDS));
        $shownAt = $this->shownAt($visit->field(self::SHOWN));

        // A program: the same thank-you, and nothing sent.
        if ($visit->field(self::HONEYPOT) !== '' || $shownAt === null || $asking->time() - $shownAt < self::LEAST_SECONDS) {
            return $sent;
        }

        if ($asking->time() - $shownAt > self::MOST_SECONDS) {
            return $this->form($route, $asking, $strings, $typed, ['form' => $strings->get('suggest.stale')]);
        }

        $poster = $visit->files['poster'] ?? null;

        if ($poster !== null && !$poster->arrived()) {
            return $this->form($route, $asking, $strings, $typed, ['poster' => $strings->get($poster->tooBig() ? 'suggest.refused.posterSize' : 'suggest.refused.poster')]);
        }

        try {
            $asking->post('/suggestions', array_filter($typed, fn (string $value) => $value !== ''), $poster !== null ? ['poster' => $poster] : []);
        } catch (Refused $refused) {
            return $this->form($route, $asking, $strings, $typed, $refused->isTooOften()
                ? ['form' => $strings->get('suggest.tooOften')]
                : self::refusals($refused, $strings, $poster !== null));
        } catch (Unavailable) {
            return $this->form($route, $asking, $strings, $typed, ['form' => $strings->get('suggest.unavailable')]);
        }

        return $sent;
    }

    /**
     * The form, with what was typed and what was refused, and the time it is shown signed afresh.
     *
     * @param array<string, string> $typed
     * @param array<string, string> $refusals
     */
    private function form(Route $route, Asking $asking, Strings $strings, array $typed = [], array $refusals = []): Shown
    {
        return new Shown('pages/suggest.twig', [
            'typed' => $typed,
            'refusals' => $refusals,
            'shown' => $this->signed($asking->time()),
            'honeypot' => self::HONEYPOT,
            'shown_field' => self::SHOWN,
        ], $strings->get('suggest.title'), $strings->get('suggest.description'), scripts: ['suggest.js']);
    }

    /** The time the form is shown, with its signature: `1793000000.ab12…`. */
    private function signed(int $time): string
    {
        return $time . '.' . hash_hmac('sha256', "suggest|{$time}", $this->secret);
    }

    /** When the form was shown, if the time sent with it is one the site signed; null otherwise. */
    private function shownAt(string $signed): ?int
    {
        if (!preg_match('/^(\d{1,12})\.([0-9a-f]{64})$/', $signed, $parts)) {
            return null;
        }

        return hash_equals(hash_hmac('sha256', "suggest|{$parts[1]}", $this->secret), $parts[2]) ? (int) $parts[1] : null;
    }

    /** @return array<string, string> the site's own words for each field the API refused */
    private static function refusals(Refused $refused, Strings $strings, bool $withPoster): array
    {
        $refusals = [];

        foreach (array_keys($refused->fields) as $field) {
            $ours = in_array($field, ['content', 'link', 'email', 'poster'], true) ? $field : 'form';
            $refusals[$ours] = $strings->get($ours === 'form' ? 'suggest.unavailable' : "suggest.refused.{$ours}");
        }

        // A poster cannot be kept in the form: it is to be chosen again.
        if ($withPoster && !isset($refusals['poster'])) {
            $refusals['posterAgain'] = $strings->get('suggest.posterAgain');
        }

        return $refusals;
    }
}
