# Changelog

## 2026-07-14 — RetroBoards runtime adoption (Part 2)
- Reconciled the imported `4efe4e33` inspection through application commit
  `6d81da5`: current `/admin/features` readiness classifications and the
  production `--gold-800` consumer are reflected in the local source mirror.
- Added an allowlisted generator for tokens, bundled fonts, and reusable
  component CSS. Preview JavaScript, UI kits, documentation CSS, uploads, and
  archived application snapshots remain design references only.
- Wrapped the runtime CSS in low-priority cascade layers beneath the unlayered
  application compatibility layer; WYSIWYG, package-theme, and branding CSS
  retain their existing later override order.
- Kept the authoring bundle's reduced-motion specimen intact but filtered its
  global `!important` timing rule from production. Important declarations
  reverse cascade-layer priority and had defeated the Study's explicit
  `animation: none`; RetroBoards already owns global and feature-specific
  reduced-motion behavior.
- Added generated-asset, feature-flag, composer-anatomy, token-definition, and
  reviewed application-surface drift gates. Any later member/admin/community/
  composer spec, template/browser asset, or feature-flag change now requires
  explicit parity review.

## 2026-07-14 — Modernization pass (Part 1 of the adoption plan)
Inspected RetroBoards `henryperkins/community-forums@4efe4e33` (main). Authority order per DECISIONS.md v1.6.

### Composer brought to the shared-shell contract (COMPOSER.md v0.8)
- `components.css`: old composer block replaced with the production shell CSS **verbatim** (box, engraved icon toolbar + overflow, upload tray, actions bar, meta row, suggestion/emoji/draft-sync surfaces, responsive + coarse-pointer + reduced-motion rules) + `.field-error`.
- `Composer.jsx`/`.d.ts` rewritten: four mounts, production toolbar order/labels/shortcuts/icon paths, Aa toggle, ＋ attach, 😊 emoji, "as *Name*" identity, Anonymous chip + disclosure, Preview, circular ✒ send, uploads, draft/counter meta, error/submitting/disabled states.
- All consumers migrated; the superseded "Posting as" strip / text-button toolbar / standalone-textarea anatomy removed everywhere (cards, both templates, kit, spec, prompt docs, thread-view dock).

### Architecture repairs
- `--text-body` collision fixed: it stays a semantic **color**; body size renamed `--text-size-body`.
- Fonts self-hosted: Google Fonts `@import` → bundled WOFF2 in `assets/fonts/` + OFL licenses; matches the app's CSP class.
- App CSS/JS snapshots moved out of usable source to `_archive/app-snapshots/2026-07-14-4efe4e33/`; 2026-06 design pull archived to `_archive/design-pull-2026-06/`. Archives are reference-only.
- Preview bundle regenerated from updated sources (`_ds_bundle.js`).

### Guidance corrections
- Emoji: decorative/status emoji in chrome stay prohibited; authored-content emoji + composer emoji tooling documented as supported product features (README, SKILL, vocabulary).
- `feature-ui/` statuses refreshed to flag truth at the commit: 13 of 14 GA default-on; `link_previews` implemented-dark.
- README provenance: inspected commit + archive rule recorded.

### Contracts
- Added `PRODUCTION_PARITY.md`, `RUNTIME_CONTRACT.md`, `production-contract.json`; `manifest.json` rewritten as the inspection manifest.

### Known gaps (tracked in `manifest.json → unresolved_gaps`)
Admin-kit platform sections, auth-kit passkeys/invites, and system pages (setup/error/privacy/unsubscribe/gated) — to be added before the Part 1 acceptance gate closes.
