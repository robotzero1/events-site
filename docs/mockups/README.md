# Mockups

The look of the site, in two directions, before any page was built (#2). The home page, the listing and an event's page share one set of markup; the two directions differ only in `styles.css`, as the site's own pages will.

- **A · Plaza** (`.dir-a`): bright and photo-led, like a poster. A blue head, photos large in cards, a bold grotesque for headings.
- **B · Almanaque** (`.dir-b`): calm and text-led, an agenda by day. Times in a column of their own, small photos, a serif for headings, one green.

Everything is under `.events-site`, and the layout answers the width of `.events-site` itself (container queries) rather than the window's, so that it fits whatever column a WordPress theme gives it later.

The `.dc.html` files are the markup as it was drawn on a design canvas: the HTML inside `<x-dc>` is what matters, and `{{dirClass}}` is `dir-a` or `dir-b`. The content is made up and the photos are labelled placeholders. The fonts came from Google Fonts for the mockup; the site will serve its own copies, so that nothing is asked of another website.

## The choice

The owner chose **B · Almanaque** on 2026-10-27, because its listings are easier to read, with the thought that it can be made prettier later. Direction A is kept here for reference only.
