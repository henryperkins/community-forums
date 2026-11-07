---
name: Forum
description: A conventional, accessible interface for reading and participating in a community.
colors:
  surface: "#ffffff"
  surface-muted: "#f8fafc"
  text: "#172b4d"
  text-muted: "#475569"
  action: "#1d4ed8"
  control-border: "#64748b"
typography:
  body:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.6
  prose:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1.125rem"
    fontWeight: 400
    lineHeight: 1.65
  title:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: "1.875rem"
    fontWeight: 700
    lineHeight: 1.25
  meta:
    fontFamily: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif'
    fontSize: ".875rem"
    fontWeight: 400
    lineHeight: 1.5
rounded:
  sm: "4px"
  md: "6px"
  lg: "8px"
  xl: "12px"
---

# Forum visual system

**Active direction, 2026-11-07.** ADR 0056 replaces Imladris as the built-in
presentation. This document governs visual decisions beneath
`PRODUCT_DESIGN.md`; it does not change forum behavior, authorization or content.
The previous document is retained in `docs/history/DESIGN-imladris.md`.

## Reading and hierarchy

Use the platform's system sans-serif stack for interface and discussion text.
Use system monospace for code. The optional stored `book` heading preference
uses a system serif; legacy preference keys remain readable without loading the
old web fonts. Do not use ornamental or tightly tracked text for functional UI.

The root stays at `100%` of the browser's chosen default. Interface text is
`1rem`; post prose is `1.125rem` with unitless `1.65` line height and a `65ch`
maximum reading measure. Secondary text starts at `.875rem`. Small/medium/large
member preferences remain proportional to the browser default. These values
are project design choices, not WCAG-prescribed font sizes.

Use sentence case, explicit page headings and descriptive links. Keep paragraphs
left-aligned; let titles, descriptions, long names and URLs wrap. Underline links
within prose and help text. Code blocks and genuinely two-dimensional tables
may scroll locally; the entire page must not overflow at narrow widths.

## Colors and states

The full source of truth is `resources/forum/tokens.css`. Light mode uses white
and slate-neutral surfaces with dark slate text and blue actions. Dark mode
uses dark slate surfaces, light text and pale blue actions. System mode follows
the operating system in CSS before paint.

| Role | Light | Dark |
|---|---|---|
| Page | `#ffffff` | `#111827` |
| Raised content | `#ffffff` | `#1f2937` |
| Primary text | `#172b4d` | `#f8fafc` |
| Secondary text | `#475569` | `#cbd5e1` |
| Link/action | `#1d4ed8` | `#93c5fd` |
| Field boundary | `#64748b` | `#94a3b8` |
| Focus outline | `#0b57d0` | `#bfdbfe` |

Ordinary text, including metadata, help and placeholders, needs at least 4.5:1
against its actual background. Meaningful control boundaries, states and
graphics need at least 3:1 against adjacent colors where WCAG 1.4.11 applies.
Decorative dividers may be lighter. Never communicate an error, selection,
presence or unread state through color alone.

Primary buttons have a filled action surface and a verified contrasting label.
Secondary controls use an explicit border. Focus uses a real 3px outline with
offset and a contrasting surround; preserve the outline in forced colors.
Use a 44px target for primary controls, navigation and icon buttons. WCAG 2.2
AA's 24px minimum and exceptions are documented separately.

## Layout and components

Preserve the existing Boards, Inbox and Messages routes and three-pane behavior.
Desktop chrome has a quiet header, a clearly separated board rail and a readable
content column. At 860px and below, the rail becomes the existing accessible
drawer. Use explicit list rows, filters, timestamps and reply counts. Keep
density settings, but do not make text illegible to fit more rows.

With the Inbox reading pane open from 1280px, retain the upstream 410px queue
width and let the topic fill the remaining frame. The compact reply field must
fit a full line, including padding, at the member's chosen text size.

Use restrained 4–12px corners, small shadows for overlays and simple separators.
The default mark is a conversation bubble; uploaded community logos take
precedence. Decorative fantasy stars, textures and photograph backgrounds do
not belong behind reading or authentication content. Authored images retain
their existing content and alternative-text behavior.

Authentication, account, moderation and administration surfaces share the same
typography, field boundaries and focus treatment. Keep native labels, inputs,
buttons, links and disclosures. An accessible library does not replace testing
the assembled interface.

## Runtime ownership

1. `resources/forum/components.css` retains compatible structural components.
   The generator wraps these in `forum.components` in `forum-base.css`.
2. `public/assets/app.css` holds feature/layout rules in the `application` layer.
3. `resources/forum/tokens.css` and `resources/forum/theme.css` generate the
   unlayered `forum-theme.css`, which owns the active presentation.
4. Editor styles, declarative theme packages and operator branding retain their
   existing later precedence and validation rules.

Run `composer build:forum`, then `npm run build`; use `composer check:forum`
and `npm run check:assets` to verify generated inputs. Do not edit generated
Forum CSS directly. Historical Imladris assets stay available for retained
release URLs and archival checks, but the active shell does not load them.

Saved `classic`, `imladris` and other established values are compatibility keys,
not names for the current visual system. No database migration rewrites member
preferences, community copy, reaction names or operator package selections.

## Accessibility and validation

Target WCAG 2.2 AA, including applicable Level A requirements. Test both themes,
keyboard navigation, focus recovery, 320 CSS px reflow, text spacing, text
enlargement, reduced motion, forced colors and the no-JavaScript HTML floor.
Do not infer conformance from an axe scan or from token arithmetic alone.

Read `docs/design-system/forum-accessibility.md` for primary-source guidance
and the validation matrix, and `docs/design-system/forum-library-evaluation.md`
for the UI-library assessment. Record completed checks and remaining manual
assistive-technology work in the implementation evidence. Merge, deployment
and production verification are separate states.
