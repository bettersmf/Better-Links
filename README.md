# Better Links

**Smart, secure link previews for SMF 2.1.x.**

Better Links turns pasted URLs into clean preview cards with titles, descriptions, images, and metadata while keeping normal named links untouched.

> **Keep SMF familiar. Make it better.**

## Features

- Rich previews for full HTTP/HTTPS URLs
- Bare-domain detection with HTTPS/HTTP fallback
- Links recognized inside normal sentence text
- Named links such as `Click Here` intentionally remain normal links
- Optional **No Preview** control for authors
- Preview cards render below message text and before attachments
- Open Graph metadata support
- Twitter Card metadata support
- JSON-LD fallback support
- HTML title, heading, and paragraph fallbacks
- Page-image and favicon/icon fallbacks
- Multiple preview cards per post
- Preview Manager and Test URL diagnostics
- Cache statistics and maintenance tools
- Orphan row and stray cached-image cleanup
- Preserve-data uninstall/reinstall support
- SSRF, DNS, TLS, and bounded-fetch hardening
- No SMF core-file edits

## Current Stable Version

**1.0.3**

Better Links 1.0.3 is the current stable release.

It includes the Better Links branding/icon work from 1.0.2 and fixes compatibility with SMF inline image BBC so URLs inside `[img]` tags are not incorrectly converted into preview cards.

## Compatibility

- Simple Machines Forum **2.1.x**
- PHP 8.x friendly
- Requires PHP cURL for remote preview fetching
- Designed to work without SMF core-file modifications

## How It Works

Better Links detects eligible URLs in post content and fetches bounded metadata from the remote page. It then builds a compact preview card using the best metadata available.

Named links are intentionally left alone. If someone takes the time to write a label such as `Click Here`, Better Links respects that choice rather than replacing it with a preview card.

## Security

Remote fetching includes defensive controls intended to reduce SSRF and unsafe-network behavior, including DNS/TLS checks and bounded fetching.

## Administration

Better Links includes administration tools for preview settings, diagnostics, cache statistics, cleanup, and URL testing.

## Development Line

The stable branch is **1.0.3**.

A separate **1.1.0 development line** has also explored search-engine-friendly URLs for public SMF topics, boards, Better Downloads, Better Gallery, and Better Portal content. That work is not part of the current stable release.

## Support & More Better SMF MODs

Better Links is part of the **Better SMF** project.

Visit **https://bettersmf.com/** for support, discussion, downloads, and the rest of the Better SMF MOD ecosystem.

## Development Philosophy

Better SMF MODs are built around a simple idea:

**Keep SMF familiar. Make it better.**

Better Links follows that philosophy by enhancing pasted links without changing how SMF users normally write or read posts.

## License

A project license will be published separately. Until then, all rights are reserved unless otherwise stated.
