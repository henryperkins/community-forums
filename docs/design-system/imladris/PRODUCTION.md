# Production contract and design ownership

This file is the maintained consumer contract for Imladris. The system owns
presentation; RetroBoards owns behavior. When they differ, resolve product and
technical questions through `DECISIONS.md` → `PRODUCT_DESIGN.md` → `SCHEMA.md`
→ the surface specs → accepted ADRs and current application contracts/tests.
Imladris references never remove, downgrade, or enable a product feature.

## Runtime contract

- **Server-rendered first.** PHP templates and real URLs own the experience.
  Every flow must work with JavaScript disabled; external same-origin JS may
  decorate it through the existing endpoints and `data-*` hooks.
- **Strict CSP.** Production styles and scripts are external and same-origin;
  no inline `<script>`, `<style>`, `style=` attribute, or CDN font/runtime is
  valid production markup. The generated CSS and fonts are self-hosted.
- **Design previews are not application runtime.** React components, imported
  UI kits, and `.dc.html` loaders are source references unless a reproducible
  upstream compiler is brought into this repository. See `PREVIEW_STATUS.md`.
- **Composer.** `COMPOSER.md` owns the current four-mount contract. Canonical
  content is Markdown; the server-rendered textarea remains the submit source
  and no-JS/kill-switch fallback. Full-navigation submit, per-render
  idempotency, and draft behavior are specified there and in ADRs 0013/0020.
- **Tokens and themes.** Production CSS consumes semantic tokens so the
  parchment/twilight registers switch consistently. Application-owned behavior
  and compatibility rules remain in `public/assets/app.css`; the generated
  Imladris layer is built from the allowlisted resources and mirror sources.
- **Content voice and iconography.** UI chrome uses sentence case, the council
  lexicon, Lucide line icons, and the two established brand stars. Status is a
  word plus color; authored-content emoji remain supported.

## Current ownership and status sources

| Question | Maintained source |
|---|---|
| Which design artifact represents a production screen? | [`github.md`](github.md) screen map, interpreted with [`LOCAL_RECONCILIATION.md`](LOCAL_RECONCILIATION.md) for local adaptations and held-back upstream changes. |
| Which feature flags are currently available/default-on? | `src/Core/FeatureFlags.php` (`DEFAULTS`), the owning ADR, and the feature runbook. The imported `production-contract.json` is an inspection snapshot from 2026-07-14, not live application status. |
| Which commit was inspected when this mirror was imported? | `manifest.json` and the provenance section in `README.md`; this is mirror provenance, not the current application commit. |
| Can an imported preview be executed here? | [`PREVIEW_STATUS.md`](PREVIEW_STATUS.md). |
| What was retired or superseded in the mirror? | [`RETIRED.md`](RETIRED.md) and [`CHANGELOG.md`](CHANGELOG.md). |

The design mirror last synced its upstream screen map on 2026-09-12. Local
application reconciliations and production-transfer updates are recorded
chronologically in `LOCAL_RECONCILIATION.md`; do not infer runtime parity from
an old inspected-commit table.

## Retired duplicate contracts

`RUNTIME_CONTRACT.md` and `PRODUCTION_PARITY.md` are compatibility pointers to
this file. Do not add a second runtime contract or a separately maintained
production parity matrix there. Current behavior is verified in the application
and its tests; this mirror's ownership map and local deviations are recorded
above.
