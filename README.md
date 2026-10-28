# events-site

The public website of a regional events listing in Spain, in Spanish and English: what's on by day, search, each event and Venue, the events in each town and of each kind, and a form for anyone to suggest an event.

It is plain PHP with Twig and builds every page on the server from a separate events API, which is not public. It is made to run on its own first and to become a WordPress plugin later. The design is in the issues, starting with the spec (#1).

## How it is built

- **The core** (`src/`, `templates/`, `lang/`) asks the API, keeps its answers for a while and turns them into HTML. It knows nothing of where it runs: the API, the cache and the log are interfaces (`Api\Api`, `Cache\Cache`, `Log`), and it gives back an `Answer` (a page's content and what goes in its head, or a redirect) for whatever runs it to send.
- **The standalone layer** (`standalone/`, `public/`) runs it on its own: curl for the API, files for the cache and the log, and the page frame. A WordPress plugin is to take its place, with WordPress's HTTP functions, transients and theme.
- **Addresses** (`Addresses`) are the same in both: the language first and the words in it, `/es/evento/{ref}` and `/en/event/{ref}`, a listing's filters named in its language too.
- **The API's answers are kept** (`Api\Asking`): Regions, Locations, Categories and Venue types for an hour, listings, events and Venues for five minutes, a Search never. Each is kept a day longer, and used when the API fails; with nothing kept, the page is a 503.
- **Every request to the API** carries the page's language and the visitor's own address (`X-Forwarded-For`), so that the API counts each visitor as themselves.
- **Twig escapes everything** it is given. What visitors and other websites wrote never goes into a page as HTML.
- **The look** is `assets/site.css`, all of it inside `.events-site` and laid out by the width of that box rather than the window's, so that a WordPress theme and the site leave each other alone. Its font, Source Serif 4, is served by the site itself (`assets/fonts/`, under the SIL Open Font License), so a page loads nothing from another website. The standalone site sends both from `/assets/`, a stylesheet's address carrying a fingerprint of its contents. The mockups it came from are in `docs/mockups/`.

## Running it

PHP 8.2 or newer with curl, and Composer.

```sh
composer install
php -d upload_max_filesize=6M -d post_max_size=8M -S 127.0.0.1:8090 -t public public/router.php
```

The two limits are for a poster sent with a Suggestion: PHP's own server would otherwise refuse one over 2 MB. On a server PHP-FPM reads them from `public/.user.ini`.

It asks the API at `http://127.0.0.1:8000/api` unless told otherwise. Its settings are in `config.php`, each one also given by the environment or by a `config.local.php` beside it, which git ignores:

| Setting | Environment | Means |
| --- | --- | --- |
| `api` | `EVENTS_API` | The API, up to and including `/api` |
| `api_host` | `EVENTS_API_HOST` | The name to ask the API by, when it is asked at a bare address on the same machine |
| `site_url` | `EVENTS_SITE_URL` | Where the site answers, for the addresses given in full in a page's head |
| `secret` | `EVENTS_SITE_SECRET` | What signs the time a form was shown. Empty: one is made at random and kept in `var/secret` |
| `var` | `EVENTS_SITE_VAR` | Where the cache, the compiled templates, the log (`log/site.log`) and the secret are kept |

Emptying `var/cache` forgets every answer kept.

## Tests

```sh
vendor/bin/phpunit
```

They run on GitHub Actions at every push, on PHP 8.2 and 8.4. They never ask a real API: pages are given **saved answers** (`tests/answers/`), which were captured from the real API in its known test state and are never written by hand. Each question is listed in `tests/answers/questions.php`.

Two things need the API itself, which is in a private repository, checked out beside this one as `../Events` (or wherever `EVENTS_API_DIR` says) with its test database set up. `bin/with-local-api.php` fills that database with its known state, starts the API on port 8012, runs a command against it and stops it again:

```sh
# Capture the saved answers again, after a question is added or the API changes
php bin/with-local-api.php php bin/capture-answers.php

# Check that the saved answers are still how the API answers, before a deploy
php bin/with-local-api.php vendor/bin/phpunit --testsuite real
```

## Licence

MIT
