# RetroBoards — Engineering Handoff (implemented)

> **Landed here from the Claude Design handoff workspace** (commit `2ff68db` there).
> Paths like `project/…` and `chats/…` below refer to that export bundle, not this
> repo; in this repo the design system's source of truth is
> `docs/design-system/imladris/`. Open `index.html` in this directory to read the
> rendered document.

This is the **real, rendered implementation** of the engineering-handoff design that
was mocked up in Claude Design and exported to `project/`. The design prototype used a
proprietary design-tool runtime (`support.js` / `<x-dc>` / `<x-import>` / a React
`DCLogic` script); this implementation drops all of that and renders the document as
**plain, self-contained HTML painted from the real Imladris design tokens** — exactly the
approach the design system prescribes for static artifacts ("link `styles.css` and write
markup with the documented classNames", per its `SKILL.md` in the product repo,
`henryperkins/community-forums` → `docs/design-system/imladris/SKILL.md`; this bundle
ships only the system's `README.md`).

## What's here

| File | Role |
|---|---|
| `index.html` | The handoff document — cover + §1–§8, rendered natively. Includes a parchment/twilight theme toggle. |
| `styles/imladris.css` | The Imladris design system, assembled **verbatim** from the source tokens + doc components (see below). |
| `styles/handoff.css` | Page-level layout (the `.doc` shell) transcribed from the design's own inline styles, plus the theme-toggle control. |

The original design bundle is left untouched under `project/`, `chats/`, and the
top-level `README.md`.

## How it maps to the design

Every `<x-import>` component in `project/EngineeringHandoff.dc.html` is rendered as the
exact HTML its React component emits (transcribed from
`project/_ds/.../_ds_bundle.js`):

- **DocCover** → `.doc-cover` (eight-point mark, kicker, title, italic dek, gold rule,
  lede, 2×2 meta grid, contents rail).
- **SectionHeader** (`level="section"` / `level="sub"`) → `.doc-section` / `.doc-section.is-sub`.
- **SpecTable** → `.doc-table-wrap > table.doc-table.is-zebra` (caption, sunken caps header, serif body).
- **Callout** (`tone` = note/info/warn, `variant="panel"`) → `.doc-callout[.doc-callout-*][.is-panel]`.
- **Figure** → `.doc-figure`; the design's `<image-slot>` (a design-tool-only drag/drop
  element) is rendered as the design system's own canonical **drop-in slot**
  (`.doc-figure-slot`, 16:9) since no real screenshots exist yet.

All data (routes, table families, capability matrix, evidence numbers, worker names,
flags, the Thread-Intelligence contract) is transcribed from the design's `renderVals()`.

### `styles/imladris.css` provenance

Assembled verbatim, in the design system's own `@import` order, from
`project/_ds/imladris-design-system-c3e02753-.../`:
`tokens/fonts.css` → `tokens/colors.css` → `tokens/typography.css` → `tokens/spacing.css`
→ the status-chip rules from `components.css` → `components/doc.css`. Values are unchanged,
so the twilight register and greyscale print behave as designed. Fonts load from Google
Fonts (as the design system does); robust system-serif fallbacks keep the register if the
CDN is blocked.

## Viewing

Open `index.html` in any browser — no build step, no framework, no runtime. It prints
cleanly to US-Letter (each `§` starts a new page) and honours `prefers-reduced-motion`.

## Source of truth

The document describes the real product, `henryperkins/community-forums`, at **Phase 5
Gate A**. This is a Phase-5 refresh of the repo's existing (Phase-4) handoff template at
`docs/design-system/imladris/templates/engineering-handoff/`.

## Fact-check against source

Every factual claim in the document — the 24 member routes and 30 operator/machine
routes, the 10 background worker class names, the 7 feature flags and 3 kill-switches,
the capability catalogue (54 keys in `Cap.php` / `CapabilityCatalog`), the schema
(115 tables / 77 migrations), the 12 table families' representative tables, ADRs
0017–0019, the Thread-Intelligence contract, and the Legacy/Shadow/Enforce capability
modes — was checked against `henryperkins/community-forums` and is **accurate**.

One correction was applied: the §1 evidence table's caption cited a single file
(`docs/evidence/phase5/gate-a-closeout.md`) for five evidence numbers, but that file is
only a 22-line requirement→evidence-file index with no figures of its own. The numbers
themselves (1,831 tests/9,396 assertions; 71 passed/1 skipped; 26 passed/2 skipped;
1,551/1,551 resolver-parity tuples; 17/17 upgrade-rehearsal checks) are all correct —
they just live in four sibling files under `docs/evidence/phase5/`
(`p5-16-regression-route-matrix.md`, `p5-16-browser-a11y-nojs.md`,
`resolver-parity.md`, `p5-16-runbook-rehearsals.md`). The caption now cites the
directory and those files, with the index noted as the map between them.

Note: the design prototype (`project/EngineeringHandoff.dc.html`, the §1 SpecTable
`caption=` attribute, line 78) intentionally still carries the original single-file
citation — the exported bundle is kept untouched by policy. Anyone re-syncing this
implementation from the prototype must re-apply this correction.

## Review notes (2026-07-12)

A review of this implementation confirmed one latent design-system defect left
deliberately unfixed here: the DS declares `--text-body` twice in `:root` — first as
the ink-700 body colour (from `tokens/colors.css`) and later as the `1.0625rem`
type-scale size (from `tokens/typography.css`) — so the later declaration wins and
every `color: var(--text-body)` is invalid at computed-value time; body copy inherits
the ink-900 heading colour instead. The prototype rendered the same way (identical
source order in the DS's own `styles.css`), so fixing it would match the documented
token intent but diverge from the prototype's actual pixels. Decision pending; the two
colliding declarations are `styles/imladris.css:127` (colour) vs `:211` (size).

Applied from the same review: wide tables now scroll horizontally inside their frame
instead of clipping columns on narrow viewports; printing always uses the parchment
register even when twilight is active on screen; an inline `<head>` guard applies a
saved twilight preference before first paint (no flash); `color-scheme` follows the
active register so UA scrollbars match; and the fixed theme toggle no longer overlaps
the cover kicker below ~760px.
