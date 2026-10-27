# events-site

The public website of the events listing: plain PHP 8.2+ with Twig, building every page on the server from the events API, which is in a private repository (`robotzero1/Events`, checked out as `../Events`). This repository is public, as a portfolio piece: nothing secret goes in it, nor anything that would help someone abuse the site, nor links into the private repository. The spec is issue #1; the tickets are #2 to #12. How to run and test it is in `README.md`.

## Decisions

- **Two layers.** The core (`src/`, `templates/`, `lang/`) knows nothing of where it runs; the standalone layer (`standalone/`, `public/`) runs it on its own and is what a WordPress plugin is to replace. Anything WordPress would do differently (asking over HTTP, keeping answers, logging, the page frame, sending a response) is behind an interface or in the standalone layer. The core gives back an `Answer`.
- **No framework.** Composer is for autoloading, Twig and PHPUnit only. Written for PHP 8.2 (`config.platform` in `composer.json` holds the lock to it): no PHP 8.3+ features.
- **Addresses are `Addresses`**, in both languages, the words translated (`/es/evento/{ref}`, `/en/event/{ref}`) and a listing's filters too (`desde`, `from`). A page is found by the name `Addresses` gives it, in `Site::pages()`. An address that is no page, or that names what the API does not have (`NotThere`), is a 404 in the address's language.
- **The bare `/`** is a 302 to the browser's language (`Language::preferredBy()`), Spanish otherwise; `/es` and `/en` are a 301 to `/es/` and `/en/`.
- **A page asks the API only through `Api\Asking`**, with a lifetime: `REFERENCE_LIST` (an hour), `LISTING` (five minutes), or null for a Search, which is never kept so that each is counted in the API's Search log. An answer is kept a day past its lifetime (`GRACE`) and used when the API fails, which is logged; with nothing kept the page is `Unavailable`, a 503 with `Retry-After` and `noindex`.
- **Every request to the API** carries `Accept-Language` and the visitor's address in `X-Forwarded-For`, which the API believes from the server itself alone.
- **Twig escapes everything** (`autoescape`, `strict_variables`); the one `|raw` is the frame putting in a page's content, which its own template made. What visitors and other websites wrote is never put in as HTML.
- **Every word the site says** is in `lang/es.php` and `lang/en.php`, with the same keys (a test holds them to that), through `t()` in a template and `Strings` in PHP.
- **Everything is inside one `<div class="events-site">`**, so that a WordPress theme and the site's CSS leave each other alone.
- **The look is Almanaque** (#2, `docs/mockups/`): calm and text-led, an agenda by day.

## Tests

- **Pages are tested against saved answers** (`tests/answers/`, served by `Tests\Support\SavedApi`), captured from the real API in its known test state by `bin/capture-answers.php` and never written by hand. A page that asks something new adds the question to `tests/answers/questions.php` and captures again.
- **`StandInApi` and `FailingApi`** are for what saved answers cannot show: what was asked, and an API that fails.
- **`CurlApiTest` starts PHP's own server** with `tests/fixtures/echo-api.php` to see what curl really sends.
- **The `real` suite** (`tests/Real/`) compares each saved answer's shape with the live API's, run through `bin/with-local-api.php`. It is run before a deploy and is not part of the default suite or of GitHub Actions.
- **On Windows**, a command given to `bin/with-local-api.php` that is a PHP script is run with PHP; the API server it starts is stopped whatever happens, since left running it holds the command's output open.

## Blog

Each ticket gets a build-log draft on the owner's blog, as for the API: see the API repository's `CLAUDE.md`. This repository is public, so the blog may link to it.
