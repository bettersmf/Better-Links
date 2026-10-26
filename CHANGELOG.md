## 1.0.4

- Corrected legacy upgrade bookkeeping so all supported direct upgrades record the installed Better Links version as 1.0.4.
- Added a direct upgrade path from Better Links 1.0.3.
- Normalized release metadata and documentation versioning.
- Completed a stable-release audit of package integrity, PHP syntax, language coverage, lifecycle behavior, admin session protection, output escaping, and remote-fetch security controls.
- No database schema changes.

## 1.0.3

- Fixed a conflict between Better Links and SMF inline image BBC.
- URLs inside `[img]...[/img]` and parameterized image tags such as `[img width=260]...[/img]` are now excluded from automatic link previews.
- Prevents inline images from rendering as broken images while their source URL is moved into a Better Links preview card.
- Added direct upgrade support from Better Links 1.0.2.

## 1.0.2

- Applied the provided Better Links brand icon to the SMF Admin Configuration area and dropdown using native Better Links branding.
- Standardized the admin/configuration display name as **Better Links** while preserving internal BetterPosts hooks and calls.
- Added direct upgrade support from Better Links 1.0.1.

# Better Links Changelog

## v1.0.1

First stable release of **Better Links** for SMF 2.1.x, built directly from the tested Better Posts `1.0.0-RC4` baseline.

### Highlights
- Branded Better Links admin icon for the Admin area dropdown and configuration tile.
- Full HTTP/HTTPS URLs.
- Bare-domain detection and HTTPS/HTTP fallback resolution.
- Links recognized inside normal sentence text.
- Named links such as `Click Here` intentionally remain normal links with no preview.
- No Preview author control.
- Cards render below message text and before attachments.
- Open Graph, Twitter Card, JSON-LD, HTML title/H1 and paragraph fallbacks.
- Page-image and favicon/icon fallbacks.
- Preview Manager and Test URL diagnostics.
- Cache statistics and maintenance.
- Orphan row / stray cached-image cleanup.
- Preserve-data uninstall/reinstall with preview recovery.
- Native destructive uninstall option.
- SSRF/DNS/TLS hardening and bounded remote fetching.
- No SMF core-file edits.
- Direct upgrade support from all Better Posts development builds and RC1-RC4.
- No database schema changes from RC4.

### Release audit
- PHP syntax validation passed for all packaged PHP files.
- `package-info.xml` parsed successfully.
- Package file/action references verified.
- Hook install/uninstall symmetry verified.
- Language-key coverage verified.
- Package Manager `$settings` collision protection retained.
- Theme-context guard retained.
- Preserve/destructive uninstall separation verified.
- Security hardening markers verified.
- ZIP integrity verified.
