---
name: RetroBoards — Imladris
description: A councillor's hall for durable conversation — parchment, evergreen, and a single mallorn gold, set entirely in serif.
colors:
  parchment-50: "#FAF6EC"
  parchment-100: "#F5EFE1"
  parchment-200: "#ECE4D2"
  parchment-300: "#DED2B8"
  mist-100: "#EEF1ED"
  mist-200: "#DCE3DD"
  ink-900: "#1B231D"
  ink-700: "#313B33"
  ink-500: "#515C52"
  ink-400: "#5C685D"
  ink-300: "#94A095"
  green-900: "#1C2E24"
  green-800: "#24402F"
  green-700: "#2E4A3A"
  green-600: "#3A5C49"
  green-500: "#4E7459"
  green-400: "#6E9479"
  green-200: "#BCD0BF"
  green-100: "#DCE8DD"
  green-050: "#EDF3ED"
  river-900: "#1E3040"
  river-700: "#2C4D63"
  river-500: "#3F6E89"
  river-400: "#5E8CA6"
  river-200: "#BAD2DF"
  river-100: "#DCE9F0"
  gold-800: "#6B5120"
  gold-700: "#9A7530"
  gold-600: "#B08A3A"
  gold-500: "#C29A44"
  gold-400: "#D2B062"
  gold-200: "#EAD9A8"
  gold-100: "#F4EBCF"
  gold-ink: "#7E5F22"
  twilight-900: "#161D24"
  twilight-800: "#1E2730"
  twilight-700: "#283440"
  leaf: "#4E7459"
  amber: "#B7842F"
  rust: "#9C4A33"
  slate: "#3F6E89"
typography:
  display:
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, serif"
    fontSize: "2.25rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, serif"
    fontSize: "1.75rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, serif"
    fontSize: "1.375rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  body:
    fontFamily: "EB Garamond, Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.62
    letterSpacing: "normal"
  label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, serif"
    fontSize: "0.72rem"
    fontWeight: 400
    lineHeight: 1
    letterSpacing: "0.16em"
  mono:
    fontFamily: "JetBrains Mono, SFMono-Regular, ui-monospace, Menlo, Consolas, monospace"
    fontSize: "0.72rem"
    fontWeight: 400
    lineHeight: 1
    letterSpacing: "normal"
rounded:
  sm: "4px"
  md: "7px"
  lg: "12px"
  xl: "20px"
  pill: "999px"
spacing:
  "1": "0.25rem"
  "2": "0.5rem"
  "3": "0.75rem"
  "4": "1rem"
  "5": "1.5rem"
  "6": "2rem"
  "8": "3rem"
  "12": "7rem"
components:
  button-primary:
    backgroundColor: "{colors.green-700}"
    textColor: "{colors.parchment-50}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-primary-hover:
    backgroundColor: "{colors.green-800}"
    textColor: "{colors.parchment-50}"
  button-secondary:
    backgroundColor: "{colors.parchment-50}"
    textColor: "{colors.ink-900}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-ghost:
    textColor: "{colors.ink-700}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-accent:
    backgroundColor: "{colors.gold-500}"
    textColor: "{colors.ink-900}"
    typography: "{typography.label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  chip-status:
    backgroundColor: "{colors.green-050}"
    textColor: "{colors.green-800}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "3px 9px"
  pill:
    backgroundColor: "{colors.parchment-200}"
    textColor: "{colors.ink-500}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "2px 10px"
  tab-active:
    backgroundColor: "{colors.green-700}"
    textColor: "{colors.parchment-50}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    padding: "6px 13px"
  input:
    backgroundColor: "{colors.parchment-50}"
    textColor: "{colors.ink-900}"
    typography: "{typography.body}"
    rounded: "{rounded.md}"
    padding: "9px 11px"
  card:
    backgroundColor: "{colors.parchment-50}"
    rounded: "{rounded.lg}"
    padding: "18px"
  thread-row:
    backgroundColor: "{colors.parchment-50}"
    rounded: "{rounded.lg}"
    padding: "14px 16px"
  post:
    backgroundColor: "{colors.parchment-50}"
    textColor: "{colors.ink-700}"
    typography: "{typography.body}"
    rounded: "{rounded.lg}"
    padding: "18px 20px"
  post-accepted:
    backgroundColor: "{colors.green-050}"
    textColor: "{colors.ink-700}"
    rounded: "{rounded.lg}"
    padding: "18px 20px"
  monogram:
    backgroundColor: "{colors.green-100}"
    textColor: "{colors.green-800}"
    typography: "{typography.label}"
    rounded: "{rounded.pill}"
    width: "36px"
    height: "36px"
---

# Design System: RetroBoards — Imladris

> **This file is the visual system only.** Product and technical truth lives in `PRODUCT_DESIGN.md` (renamed from `DESIGN.md` on 2026-08-27); durable product context lives in `PRODUCT.md`; `DECISIONS.md` wins on any conflict. Machine-readable extensions — tonal ramps, shadows, motion, breakpoints, component snippets — live in `.impeccable/design.json`.
>
> **Authoritative sources.** Tokens: `resources/imladris/tokens/{colors,typography,spacing,fonts}.css`, generated into `public/assets/imladris.css`. Components: `public/assets/app.css`, with the curated transcription in `docs/design-system/imladris/components.css`. The two token copies were verified byte-identical on 2026-08-27.

## Overview

**Creative North Star: "The Councillor's Hall"**

This is a room where people keep counsel together and the record survives the conversation. Warm stone and aged parchment, evergreen boughs at the windows, one thread of mallorn gold catching the candlelight — and, underneath the ceremony, a working table. The register is Tolkien-adjacent without cosplay: considered, literary, quietly premium, and never a toy social app. Where most forum software looks cheap, Imladris is dressed.

Gravity is the point. The whole product is set in serif — four families, no sans anywhere — because a conversation worth keeping for years deserves the typography of a book rather than a chat client. Colour is warm and low-chroma; nothing is neon; the single gold accent is an *indicator*, never a field. Motion is calm and short. Nothing bounces in Rivendell.

Ceremony is earned, not applied. The hall is dignified, but the table is plain: the everyday controls a member touches a hundred times a day are quiet, legible, and unornamented. Ornament belongs to colophons and footnotes — the marks of esteem, the gilt ring on an accepted answer, the eight-pointed star watermark behind a topic header — not to functional chrome.

**Key Characteristics:**

- All-serif voice: display, lapidary caps, book prose, and tabular mono — four families, zero sans.
- Parchment ground, evergreen brand, one mallorn-gold accent used sparingly.
- Warm-ink shadows, never pure black; surfaces flat at rest and lifting only on state.
- Status always carries a word as well as a colour.
- Two registers, day and twilight, driven entirely by semantic tokens.
- Restrained radii — nothing rounder than 12px except tokens, which are pills.

## Colors

Warm, low-chroma, and drawn from a single landscape: parchment and stone for the ground, evergreen for authority, river-blue for information, and mallorn gold as the one bright thread.

### Primary

- **Evergreen** (`#2E4A3A`, `green-700`): the brand. Links, primary buttons, the OP badge, active filter pills, the active-row wash. It carries authority, not attention — it is the colour of the institution rather than of a call to action.
- **Evergreen Deep** (`#24402F`, `green-800`) and **Evergreen Press** (`#1C2E24`, `green-900`): hover and pressed states for anything evergreen, plus ink on the pale evergreen wash.
- **Evergreen Wash** (`#EDF3ED`, `green-050`): the active-row and OP-badge ground. Pale enough that a whole list of them would still read as parchment.

### Secondary

- **Mallorn Gold** (`#C29A44`, `gold-500`): the single accent. Unread dots, active indicators, the commend star, the gilt avatar ring, the focus halo, the gold rule on blockquotes. It marks *where to look*, and it is never a surface.
- **Gold Ink** (`#7E5F22`, `gold-ink`): the darker gold reserved for small text on parchment, where `gold-500` would fail AA. Board hashes, regard counts, engraved panel headings.
- **Gold Leaf** (`#F4EBCF`, `gold-100`) and **Gilt Edge** (`#EAD9A8`, `gold-200`): the pale golds behind "needs answer" chips, staff badges, and the on-state of a commend.

### Tertiary

- **Bruinen Blue** (`#3F6E89`, `river-500`): information and the cool counterpoint. Artifact links, the DM register, info status, and one of the four monogram tints. Present so the palette is not monotonously warm; never competing with gold for the eye.

### Neutral

- **Parchment** (`#FAF6EC` → `#DED2B8`, `parchment-50…300`): the world. `parchment-50` raises (cards, topbar, inputs), `parchment-100` is the page ground, `parchment-200` sinks (pills, code, wells), `parchment-300` is the default hairline.
- **Mist** (`#EEF1ED`, `#DCE3DD`): the cooler neutral, an alternative ground and the softer border.
- **Ink** (`#1B231D` → `#94A095`, `ink-900…300`): the text scale, warm near-black down to soft grey-green. Body prose sits at `ink-700`, headings at `ink-900`, meta at `ink-500`, faint meta at `ink-400`.
- **Twilight** (`#161D24`, `#1E2730`, `#283440`): the night register's surfaces.

### The status ledger

Status hues are named, not numbered, and each pairs a hue with a wash and an ink: **Leaf** `#4E7459` (solved), **Amber** `#B7842F` (needs answer), **Rust** `#9C4A33` (danger), **Slate** `#3F6E89` (info), plus a neutral pending. Decision-made borrows evergreen; pinned and staff borrow gold; archived is a dashed border with no fill.

### Named Rules

**The One Gold Rule.** Mallorn gold is an indicator, never a field. It may be a dot, a rule, a ring, a star, a hairline, or a halo — it may not be the background of anything larger than a chip. *Audit test: if a gold region on screen is bigger than a status chip, it is wrong.*

**The Word-and-Colour Rule.** Status is never carried by colour alone. Every state that has a hue also has a word: "Solved", "Needs answer", "Decision", "Locked", "Archived". Colour-blind and monochrome readers lose nothing. *Audit test: cover the screen's colour and the state is still readable.*

**The Semantic-Only Rule.** Application code paints from semantic tokens (`--surface-raised`, `--brand`, `--on-done`), never from primitives (`--parchment-50`, `--green-700`). The twilight register is a re-pointing of semantics; anything painted from a primitive fails to flip. *Audit test: a new rule that names a primitive scale token is a bug unless it is inside the token layer itself.*

## Typography

**Display Font:** Cormorant Garamond (with Hoefler Text, Garamond, Georgia, serif)
**Body Font:** EB Garamond (with Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif)
**Label Font:** Marcellus (with Optima, Palatino Linotype, Palatino, serif)
**Mono Font:** JetBrains Mono (with SFMono-Regular, ui-monospace, Menlo, Consolas, monospace)

All four are self-hosted WOFF2 under OFL 1.1, latin subset — the product runs a same-origin CSP, so there is no font CDN. Every family variable keeps a system-serif fallback so the register reads before the webfonts arrive.

**Character:** A book, not an app. Cormorant's high-contrast display serif gives headings and thread titles the air of a title page; EB Garamond sets prose at a genuinely readable 17px/1.62; Marcellus supplies lapidary roman capitals for the small structural furniture — eyebrows, button labels, chips, meta lines — and JetBrains Mono handles anything countable. The serifs are set **medium (500), not bold**: restraint reads as quality.

### Hierarchy

- **Display** (500, 2.25rem, 1.15, −0.01em): `h1` and page titles. Profile names step up to 2.4rem.
- **Headline** (500, 1.75rem, 1.15): `h2`. The inbox heading sits between at 1.85rem.
- **Title** (500, 1.375rem, 1.15): `h3`, board names. Thread-row titles run 1.2rem, and the study-view topic title 2.15rem with a 28ch measure.
- **Body** (400, 1.0625rem/17px, 1.62): posts and prose. Measure ≈ 64ch.
- **Label** (400, 0.72rem, 0.16em tracking, uppercase): eyebrows, chips, meta lines. Button labels are the same family at 0.9rem and 0.03em tracking — and are **not** uppercased.
- **Mono** (400, 0.72rem, tabular numerals): timestamps, counts, routes, breadcrumbs, regard figures.

### Named Rules

**The All-Serif Rule.** There is no sans-serif in this product. Four families, three of them serif and one a mono for data. A sans-serif anywhere is an intrusion from another design system. *Audit test: grep any new stylesheet for `sans-serif` outside a fallback stack.*

**The Lapidary Caps Rule.** The only uppercase text is Marcellus, tracked at 0.08–0.16em, at label sizes. It is a typographic device, not emphasis and not shouting. Sentence case everywhere else — including buttons, headings, and chips' underlying copy. *Audit test: any `text-transform: uppercase` on something that is not a Marcellus label is wrong.*

**The Tabular Rule.** Anything countable — reply counts, regard, timestamps, member totals — is set in JetBrains Mono with `font-variant-numeric: tabular-nums`, so columns of numbers align and a changing count does not reflow its row.

## Layout

A full-height application shell of three columns plus a top bar. The rails are fixed tokens: top bar **62px**, sidebar **272px**, topic list **410px**, and a **1280px** content maximum for centred pages.

The three panes map to real URLs rather than to client state: `/` is the forum index, `/inbox` the personalised topic inbox, `/c/{slug}` a board's fixed-order list, `/t/{id}-{slug}` the conversation. Navigation is server-rendered; JavaScript decorates.

**Density is a first-class axis, not a preference toggle bolted on.** The topic list ships three presentations from the same markup: *comfortable* (a parchment card per row with avatar, byline, chips, two-line snippet, meta), *compact* (one ruled scannable line, snippet hidden, author folded into the meta), and *board* (a ruled entry on a 64px minimum floor with activity in a right-hand rail and no board label, because a board does not label itself). A long title wraps and the row grows — 64px is a minimum, never a crop.

**Spacing** runs on a 4px base: 4, 8, 12, 16, 24, 32, 48, 112px. Cards are padded 18px, thread rows 14/16px, posts 18/20px, and the gap between rows is 10px in comfortable and zero in the ruled densities, where a hairline does the separating.

**Responsive.** The architectural breakpoint is **860px**: three panes collapse to one column, the sidebar becomes a slide-in drawer, and a conversation grows a back link to the list it came from. Secondary breakpoints at 900px (admin chrome wraps, its tier scrolls) and 760px (in-pane density) carry the rest.

### Named Rules

**The Real-URL Rule.** Every pane state is a URL that renders server-side and survives a hard refresh, a share, and a crawler. A view that only exists after JavaScript runs is not a view. *Audit test: load it with JavaScript disabled; if the content is gone, the layout is wrong.*

**The Two-Breakpoint Rule.** 860px is the shell breakpoint and 900px is the admin-chrome breakpoint. The stylesheet currently carries twelve distinct max-widths; that sprawl is debt, not vocabulary. New responsive work reuses an existing breakpoint or justifies a new one in review.

## Elevation & Depth

Layered, warm, and shallow. Depth comes from a five-step shadow scale plus tonal layering between three parchment surfaces — raised, page, sunken — and the border hairline does much of the work that a shadow would do elsewhere. Nothing is glassy except two deliberate chrome bars (the topbar and the admin bar), which sit at ~90% surface with a 10px backdrop blur so content scrolls under them legibly.

Shadows are cast in **warm ink** (`rgba(27,35,29,…)`), never neutral black. On twilight surfaces they deepen to `rgba(22,29,36,…)`.

### Shadow Vocabulary

- **`--shadow-xs`** (`0 1px 2px rgba(27,35,29,.06)`): the resting state of every card, thread row, post, and button. Barely there — enough to separate parchment from parchment.
- **`--shadow-sm`** (`0 1px 3px rgba(27,35,29,.07), 0 1px 2px rgba(27,35,29,.05)`): the selected row, the accepted answer, a switch knob.
- **`--shadow-md`** (`0 4px 14px rgba(27,35,29,.08), 0 2px 5px rgba(27,35,29,.05)`): hover lift on an interactive row.
- **`--shadow-lg`** (`0 12px 32px rgba(27,35,29,.12), 0 4px 10px rgba(27,35,29,.06)`): popovers and menus.
- **`--shadow-xl`** (`0 24px 60px rgba(22,29,36,.18), 0 8px 18px rgba(22,29,36,.08)`): modal dialogs only.
- **`--shadow-inset`** (`inset 0 1px 2px rgba(27,35,29,.07)`): fields and tracks — the impression of something pressed into the page.
- **`--gilt`** (`inset 0 0 0 1px rgba(194,154,68,.38)`): not a shadow but a thin gold inner ring, marking a "precious" avatar — the OP, an accepted answer, a profile, a top-three leaderboard place.

### Named Rules

**The Warm-Shadow Rule.** No pure-black drop shadows anywhere. Every shadow is mixed from ink or twilight so it reads as candlelight on parchment rather than as a UI kit default. *Audit test: any `rgba(0,0,0,…)` in a shadow is wrong.*

**The Lift-On-State Rule.** Surfaces are flat at rest at `--shadow-xs`. Elevation is a *response* — hover, selection, focus — not a decoration. A thread row lifts exactly 1px and gains `--shadow-md` on hover; nothing lifts without a reason.

## Shapes

Restrained and rectilinear. Radii are `sm 4px`, `md 7px`, `lg 12px`, `xl 20px`, and `pill 999px`, and the system uses the small end far more than the large: cards, rows, and posts at 12px; buttons, fields, and menu items at 7px; inline code at 4px.

The **pill is reserved for tokens** — chips, badges, tags, filter tabs, segmented controls, the search field, the tier marker. Pill-ness means "this is a small labelled thing", so a pill-shaped button or card would misread as a status token.

Borders do real work: a 1px `--border-hair` in parchment-300 is the system's default line, and much of the interface is built from hairlines rather than fills. Interactive controls step up to 1.5px so the outline reads as an affordance.

The recurring silhouette is the **left rule**: a 3px vertical band on the leading edge of a thread row, coloured by status (leaf for solved, amber for needs-answer, evergreen for decision, gold for pinned) and transparent when there is nothing to say. In the board density it thins to 2px and steps outside the row. It is how the eye scans a list of forty topics for the three that matter.

### Named Rules

**The Twelve-Max Rule.** Nothing that holds content is rounder than 12px. 20px exists for a handful of large containers and should be justified; pills are for tokens only. *Audit test: a `border-radius` above 12px on anything that is not a token or a large container is wrong.*

## Components

The everyday register is **plain**: quiet surfaces, hairline borders, restrained radii, and Marcellus labels. This is the system default and the direction of travel.

### Buttons

- **Shape:** gently curved (`7px`), 1px transparent border, `--shadow-xs` at rest, pressing to `translateY(0.5px) scale(.995)`.
- **Primary:** evergreen (`#2E4A3A`) on parchment text (`#FAF6EC`), padded `9px 17px`, Marcellus at 0.9rem with 0.03em tracking, **sentence case**. Hover deepens to `green-800`.
- **Secondary:** raised parchment (`#FAF6EC`) with ink text and a 1.5px `--border-soft` outline, no shadow. Hover sinks the fill to `parchment-200` and strengthens the border.
- **Ghost:** transparent with `ink-700` text and a transparent 1.5px border, so it occupies the same box as its siblings and does not shift the row on hover.
- **Accent:** mallorn gold (`#C29A44`) with `ink-900` text — the one place gold is a fill, reserved for a single moment of emphasis per screen.
- **Danger:** rust (`#9C4A33`) on white.
- **Disabled:** 50% opacity, `not-allowed`, and hover suppressed.
- **Icons:** 16px, stroked at 1.9 with round caps and joins, `fill: none`, `stroke: currentColor`.

### Chips, badges, pills and tags

- **Chips and badges** (status and role) are Marcellus caps at 0.62rem with 0.1em tracking, `3px 9px`, pill-shaped, and always bordered — a wash plus a 1px border in the matching hue, so they read on any surface. Icons inside are 11px stroked at 2.
- **Pills** are the larger, quieter status token: `2px 10px`, 0.72rem, sunken parchment.
- **Tags** are the smallest: `2px 8px`, 0.6rem, 0.08em tracking, for board and meta labels.
- **Tier markers** (`Member · Veteran · Loremaster · Legend`) are 0.58rem caps at 0.11em, each tier taking its own hue: gold for Legend, evergreen for Loremaster, river for Veteran, neutral for Member.

### Cards and containers

- **Corner style:** 12px.
- **Background:** raised parchment (`#FAF6EC`) on a `parchment-100` page.
- **Border:** 1px `--border-hair`.
- **Shadow:** `--shadow-xs` at rest; see Elevation.
- **Internal padding:** 18px, or `14px 16px` for a thread row and `18px 20px` for a post.

### Inputs and fields

- **Style:** raised parchment, 1.5px `--border-soft`, 7px radius, `--shadow-inset`, set in the body serif at inherited size and padded `9px 11px`. A search field takes the pill variant on the sunken page colour.
- **Focus:** the gold halo — border shifts to `gold-400`, a 2px evergreen outline at 1px offset, and a layered `0 0 0 3px` gold focus ring over the inset. Focus is unmistakable and warm rather than the browser default.
- **Labels:** Marcellus at 0.82rem, muted ink, 5px above the control.
- **Errors:** rust text at 0.85rem directly beneath the field, with underlined inline links.
- **Switches:** a 42×24 pill track, sunken parchment with a strong border and inset shadow, carrying an 18px round knob ringed in `gold-200`; checked fills the track evergreen and gilds the knob to `gold-200` with a `gold-500` ring. Transitions run `--dur-base` on `--ease-calm`.

### Navigation

- **Topbar** (62px): parchment at ~92% with a 10px backdrop blur and a hairline bottom border. Brand star and wordmark in Cormorant, a pill search field ("Search the council…"), the bell with a gold count dot, and the identity cluster with a 28px monogram and a leaf presence dot.
- **Sidebar rail** (272px): sunken parchment. Quick filters, Marcellus gold-ink category headers, gold `#` board rows with count pills, and a DM list with presence dots.
- **Filter tabs:** Marcellus pills at `6px 13px`; active fills evergreen with parchment text.
- **Segmented control:** a sunken pill shell with 3px padding; the active item fills evergreen.
- **Underline tabs** (sort, profile): no fill; the active item goes strong-ink, semibold, with a 2px gold underline drawn as an inset shadow.
- **Admin chrome:** a sticky two-row block — a 58px identity row (brand, wordmark, exit link, an uppercase mode pill) above a scrolling tier of area links. The tier deliberately uses the pill register so it never reads as a duplicate of a page's own underline sub-tabs one heading below.

### Signature: the thread row

The system's most-repeated object and the place its character is clearest. A parchment card with a 3px status left-rule, a 44px monogram, a Cormorant byline, status chips, a Cormorant title at 1.2rem, a two-line clamped snippet in muted ink, and a Marcellus meta line with a gold-ink board hash. Unread state is carried by a **gold dot with a 2px translucent gold halo** plus a border shift to `green-200` and a semibold title — three quiet signals rather than one loud one. Selected state washes the row in `--brand-subtle` and turns the left rule leaf-green. Hover lifts 1px to `--shadow-md`.

### Signature: the monogram

A tinted ground with legible dark ink, rotating through ten variants across evergreen, river, gold, mist and parchment — so a list of members is quietly varied without anyone being assigned a "colour". 36px default, 26–64px by context, always a circle, always Marcellus. The `--gilt` inner ring marks the precious ones.

### Legacy: the lapidary register

An ornamented treatment — chamfered octagonal frames drawn as eight background-gradient layers with a matching `clip-path`, doubled gold rules, diamond bullets, set-gem checkboxes, engraved panel headings. It currently dresses **13 of 13 account templates and 5 of 6 auth templates**, and **none** of the forum surfaces, admin (0/45), or moderation (0/4).

**This is drift, not doctrine.** The plain register above is the system default. The lapidary treatment is expensive to maintain (each state restates eight gradient layers), it cannot carry an outer focus ring because `clip-path` cuts everything outside the octagon, and it makes two surfaces of one product look like two products. Treat it as legacy: do not extend it, and prefer the plain equivalent whenever an engraved component is touched.

## Do's and Don'ts

### Do:

- **Do** paint from semantic tokens (`--surface-raised`, `--brand`, `--on-done`) so the twilight register flips for free.
- **Do** pair every status colour with a status word, in the taxonomy already established (Solved, Needs answer, Decision, Pinned, Locked, Archived).
- **Do** set button labels in Marcellus, sentence case, at 0.9rem/0.03em — tracked capitals are for chips, eyebrows, and meta lines only.
- **Do** put anything countable in JetBrains Mono with tabular numerals.
- **Do** keep prose at 17px/1.62 with a measure near 64ch, and topic titles near 28ch.
- **Do** reach for a hairline before a shadow, and for `--shadow-xs` before anything heavier.
- **Do** honour `prefers-reduced-motion: reduce` — the stylesheet already collapses every duration to 0.001ms, and new motion must inherit that.
- **Do** ship every state server-rendered first; JavaScript decorates through `data-*` hooks and the existing JSON endpoints.

### Don't:

- **Don't** introduce a sans-serif, a fifth family, or a webfont from a CDN. The CSP is same-origin and the fonts are self-hosted under OFL.
- **Don't** write an inline `<style>` block, an inline `<script>`, or a `style="…"` attribute. `style-src 'self'` blocks all three, and the page fails silently. *Audit test: `grep -ro 'style="' templates/ | wc -l` must stay at 0.*
- **Don't** use gold as a background for anything larger than a chip, and don't use two accents — the palette has exactly one.
- **Don't** put emoji in UI chrome. Status is a word and a colour. (Emoji in member-authored content is a product feature and stays.)
- **Don't** paint directly from a primitive scale token in application CSS.
- **Don't** round anything holding content past 12px, and don't make a button or card pill-shaped.
- **Don't** use a pure-black shadow, or add elevation to something that is merely at rest.
- **Don't** extend the chamfered lapidary register to new surfaces; it is legacy and shrinking.
- **Don't** invent a new breakpoint. 860px collapses the shell and 900px wraps the admin chrome; reuse them.
- **Don't** rename the forum lexicon. Reply is **counsel**, reputation is **regard**, badges are **marks of esteem**, like is **commend** — this vocabulary is a binding brand commitment recorded in `PRODUCT.md`.
