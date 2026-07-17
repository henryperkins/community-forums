# Imladris runtime adoption evidence

**Date:** 2026-07-14
**Scope:** import the refreshed Imladris bundle and make it the production
presentation foundation without replacing or regressing RetroBoards behavior.

## Source and freshness reconciliation

- Imported artifact: `imladris-design-system.zip`
- SHA-256: `2ee3201e3bfcaa82ed371af8709fd0737a54c69332119d006f6f0a51aa57dbeb`
- Bundle inspection commit: `4efe4e33db6475ce9c59190ba82c72cbd7d4b868`
- Consuming application audit commit:
  `6d81da590a12bd09bb8d0e282c042aa03d755a94`

The production delta after the bundle inspection was audited before adoption.
It added the read-only readiness classification to `/admin/features`; the
admin UI-kit seed/compiled preview now uses those current classifications.
`--gold-800` was also retained because the production staff-badge and monogram
variants still consume it. `production-contract.json` records the reconciliation
commit and classifies all 57 declared flags (49 default-on, 8 default-dark).

## Runtime boundary

`composer build:imladris` produces the checked-in runtime closure:

- `public/assets/imladris.css`, generated only from `tokens/{fonts,colors,
  typography,spacing}.css` and `components.css`;
- 15 self-hosted WOFF2 font files under `public/assets/fonts/imladris/`; and
- normalized source/license copies plus provenance in `resources/imladris/`.

The stylesheet uses `imladris.tokens` and `imladris.components` cascade layers.
The unlayered `app.css` remains authoritative for the shell, feature states, and
compatibility behavior. WYSIWYG, package-theme, and operator-branding styles
retain their later link order. Preview `_ds_bundle.js`, UI kits, documentation
CSS, templates, feature demos, uploads, scratch material, and `_archive/` never
enter the production asset graph.

`config/imladris-runtime-baseline.json` adds the freshness gate that was missing
from the earlier design-system workflow. It digests the normalized member,
admin, community, and composer surface specs; `FeatureFlags.php`; every PHP
template; and every production CSS/JavaScript asset except the generated
Imladris file. A newer production surface therefore makes
`composer verify:imladris` fail until design parity is reviewed and the baseline
is deliberately refreshed.

## Regression found and closed

The first broad browser pass found a real cascade-layer edge case: the imported
spacing token source has a global reduced-motion fallback with `!important`.
Important declarations reverse normal cascade-layer priority, so its `0.001ms`
duration beat the Study's unlayered, feature-specific `animation: none` rule.

The authoring source remains intact for standalone design previews. The runtime
generator filters that one cross-cutting behavior block and rejects any other
design-system `!important` declaration. Reduced motion remains application-owned.
The failing desktop/mobile Study checks then passed with computed duration `0s`.

## Automated evidence

| Verification | Result |
|---|---|
| Full PHPUnit inventory | **Pass** — 2,237 tests, 16,273 assertions, 1 expected skip |
| Current composer matrix: `community-inbox-theme`, `composer-shell`, `wysiwyg-composer` | **Pass** — 81 applicable tests, 31 intentional viewport skips |
| Official rich-content/Study browser group | **Pass** — 21 applicable tests, 7 intentional project skips |
| Official main browser-evidence group | **Pass** — 107 applicable tests, 13 intentional project skips |
| WYSIWYG bundle drift: `npm run check:wysiwyg` | **Pass** |
| Imladris generator/contract: `composer verify:imladris` | **Pass** |
| Patch hygiene: `git diff --check` | **Pass** |

The browser runs covered parchment/twilight, desktop/mobile, no-JavaScript,
strict CSP, accessibility, composer lifecycle teardown, plain/WYSIWYG modes,
uploads and Markdown reorder, draft conflict handling, theme preview/safe-mode/
rollback, public/auth/settings/admin/moderation/appeals/provider/invitation/
Thread Intelligence surfaces, and the active/default-dark feature inventory.
Server request evidence also returned `200` for `/assets/imladris.css` and the
self-hosted EB Garamond, Cormorant Garamond, Marcellus, and JetBrains Mono files.

## Visual evidence

The refreshed evidence set contains 180 desktop/mobile screenshots. Representative
artifacts inspected after capture:

- [Desktop home](browser/desktop/01-home.png)
- [Mobile home](browser/mobile/01-home.png)
- [Desktop Inbox](browser/desktop/10-inbox.png)
- [Mobile Inbox](browser/mobile/10-inbox.png)
- [Desktop current composer and emoji dialog](browser/desktop/82-composer-emoji.png)
- [Mobile current composer and emoji dialog](browser/mobile/82-composer-emoji.png)
- [Desktop rich-content contract](browser/desktop/83-rich-content.png)
- [Mobile rich-content contract](browser/mobile/83-rich-content.png)
- [Desktop feature-readiness inventory](browser/desktop/admin-feature-readiness.png)
- [Mobile feature-readiness inventory](browser/mobile/admin-feature-readiness.png)
