# Better Links Changelog

## 1.0.3

Current stable release.

- Fixed a conflict between Better Links and SMF inline image BBC.
- URLs inside `[img]...[/img]` and parameterized image tags such as `[img width=260]...[/img]` are excluded from automatic link previews.
- Prevents inline images from rendering as broken images while their source URL is moved into a Better Links preview card.
- Added direct upgrade support from Better Links 1.0.2.

## 1.0.2

- Applied the Better Links brand icon to the SMF Admin Configuration area and dropdown.
- Standardized the visible admin/configuration branding as **Better Links** while preserving the proven internal BetterPosts hook/call structure.
- Added direct upgrade support from Better Links 1.0.1.

## 1.0.1

First stable Better Links release built from the tested Better Posts 1.0.0-RC4 baseline.

### Highlights

- Full HTTP/HTTPS URL previews
- Bare-domain detection
- Inline sentence-link detection
- Named-link exclusion
- No Preview author control
- Cards below message text and before attachments
- Open Graph, Twitter Card, JSON-LD, title/H1/paragraph fallbacks
- Page-image and favicon/icon fallbacks
- Preview Manager and Test URL diagnostics
- Cache statistics and maintenance
- Orphan row / stray cached-image cleanup
- Preserve-data uninstall/reinstall
- SSRF/DNS/TLS hardening
- No SMF core-file edits

## 1.1.0 development line

Development work after 1.0.3 added an optional SEO URL foundation for simple public SMF topics and boards and later expanded into Better Downloads, Better Gallery, and Better Portal integration.

The 1.1.0 development line remains separate from the current stable 1.0.3 release.
