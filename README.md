# Better Links v1.0.4

Better Links is a rich-link-preview enhancement for Simple Machines Forum (SMF) 2.1.x.

It lets users paste full URLs, type bare domains, or include links naturally inside sentences. The original link remains clickable in the post while a polished preview card is rendered below the message text and before SMF attachments.

## Requirements

- SMF 2.1.x
- PHP cURL extension
- cURL HTTP/HTTPS support
- SSL/TLS support in cURL

## Link recognition

Better Links recognizes visible links such as:

- `https://example.com`
- `example.com`
- `www.example.com`
- `example.com/path`
- `Read more at example.com/path when you have time.`
- `[url]https://example.com[/url]`
- `[url=https://example.com]https://example.com[/url]`

Bare domains are tried in this order:

1. `https://www.example.com`
2. `https://example.com`
3. `http://www.example.com`
4. `http://example.com`

The first candidate that returns a valid HTML/XHTML page becomes the clickable destination and preview target. If all candidates fail, the original text remains untouched.

Deliberately renamed links are respected. For example:

`[url=https://example.com]Click Here[/url]`

stays an ordinary named link and does **not** generate a preview.

## Rich preview cards

Cards can include:

- site identity
- page title
- description/opening text
- Open Graph metadata/image
- Twitter Card metadata/image
- JSON-LD metadata
- meaningful page-image fallback
- icon/favicon fallback

Cards render below the message body. Multiple eligible links can produce multiple cards in message order.

## No Preview

Better Links adds a **No Preview** button to the SMF BBC toolbar.

A No Preview link remains clickable but does not generate a preview card.

## Admin tools

Admin -> Configuration -> Better Links includes:

- enable/disable previews
- cURL capability status
- branded Better Links admin icon
- cache lifetime
- connection timeout
- total fetch timeout
- maximum HTML size
- maximum image size
- cache statistics
- Test URL diagnostics
- Preview Manager
- Inspect / Refresh / Expire
- orphan/stray-image scan and cleanup
- Recover Preserved Previews

## Networking and security

Protections include:

- HTTP/HTTPS only
- explicit ports restricted to 80/443
- URL credential rejection
- URL length/control-character validation
- private/reserved/loopback IP rejection
- DNS validation
- `CURLOPT_RESOLVE` address pinning
- redirect-by-redirect revalidation
- TLS peer/host verification
- response byte limits
- MIME allowlists
- local image caching
- `LIBXML_NONET`

## Uninstall behavior

Better Links uses SMF's native optional data-removal checkbox.

With the box unchecked, uninstall converts private Better Links BBC back to native clickable SMF URL BBC, detaches hooks, removes package files, and preserves preview data/cache/configuration. Reinstall can use **Recover Preserved Previews** to reconnect matching cached previews without fetching the web.

With the box checked, posts are still normalized first, then Better Links removes its preview table, cached images and settings.

## Upgrade compatibility

Better Links v1.0.4 supports direct upgrades from the Better Posts development and RC packages through `1.0.0-RC4`, as well as Better Links `1.0.0`, `1.0.1`, and `1.0.2`.

Internal `betterposts_*` function names, settings keys, table names and package files are intentionally retained for upgrade/data compatibility. The released product name is **Better Links**.

## Core edits

Better Links does not modify SMF core files or `Display.template.php`.

## License

MIT License. See `LICENSE`.
