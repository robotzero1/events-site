# events-site

The public website of a regional events listing for the coast of Granada, in Spanish and English: what's on by day, search, events, Venues, the events in each town and of each kind, and a form for anyone to suggest an event.

It is written in plain PHP with Twig and builds every page on the server from a separate events API, which is not public. It is built to run on its own first and to become a WordPress plugin later: the core (asking the API, keeping its answers, turning them into HTML) knows nothing of where it runs, and only a thin outer layer does.

The design is in the issues, starting with the spec. Work in progress.

## Licence

MIT
