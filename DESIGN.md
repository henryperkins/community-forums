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
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, Times New Roman, serif"
    fontSize: "2.25rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  headline:
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, Times New Roman, serif"
    fontSize: "1.75rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  title:
    fontFamily: "Cormorant Garamond, Hoefler Text, Garamond, Georgia, Times New Roman, serif"
    fontSize: "1.375rem"
    fontWeight: 500
    lineHeight: 1.15
    letterSpacing: "-0.01em"
  body:
    fontFamily: "EB Garamond, Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.62
  label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.72rem"
    fontWeight: 400
    letterSpacing: "0.16em"
  mono:
    fontFamily: "JetBrains Mono, SFMono-Regular, ui-monospace, Menlo, Consolas, monospace"
    fontSize: "0.72rem"
    fontWeight: 400
  body-ui:
    fontFamily: "EB Garamond, Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif"
    fontSize: "1.0625rem"
    fontWeight: 400
    lineHeight: 1.6
  button-label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.9rem"
    fontWeight: 400
    letterSpacing: "0.03em"
  chip-label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.62rem"
    fontWeight: 400
    lineHeight: 1
    letterSpacing: "0.1em"
  small:
    fontFamily: "EB Garamond, Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif"
    fontSize: "0.95rem"
    fontWeight: 400
  fine:
    fontFamily: "EB Garamond, Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif"
    fontSize: "0.86rem"
    fontWeight: 400
  meta:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.78rem"
    fontWeight: 400
  chip:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.7rem"
    fontWeight: 400
  tab-label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.8rem"
    fontWeight: 400
    letterSpacing: "0.03em"
  avatar-label:
    fontFamily: "Marcellus, Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif"
    fontSize: "0.8rem"
    fontWeight: 500
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
    typography: "{typography.button-label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-primary-hover:
    backgroundColor: "color-mix(in srgb, var(--accent) 82%, #000)"
    textColor: "{colors.parchment-50}"
  button-secondary:
    backgroundColor: "{colors.parchment-50}"
    textColor: "{colors.ink-900}"
    typography: "{typography.button-label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-ghost:
    textColor: "{colors.ink-700}"
    typography: "{typography.button-label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-accent:
    backgroundColor: "{colors.gold-500}"
    textColor: "{colors.ink-900}"
    typography: "{typography.button-label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  button-danger:
    backgroundColor: "{colors.rust}"
    textColor: "#FFFFFF"
    typography: "{typography.button-label}"
    rounded: "{rounded.md}"
    padding: "9px 17px"
  chip-status:
    backgroundColor: "{colors.green-050}"
    textColor: "{colors.green-800}"
    typography: "{typography.chip-label}"
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
  tab-ruled-active:
    textColor: "{colors.green-700}"
    typography: "{typography.tab-label}"
    padding: "8px 14px"
  input:
    backgroundColor: "{colors.parchment-50}"
    textColor: "{colors.ink-900}"
    typography: "{typography.body-ui}"
    rounded: "{rounded.md}"
    padding: "9px 11px"
  card:
    backgroundColor: "{colors.parchment-50}"
    rounded: "{rounded.md}"
    padding: "18px"
  thread-row:
    backgroundColor: "transparent"
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
    typography: "{typography.avatar-label}"
    rounded: "{rounded.pill}"
    width: "36px"
    height: "36px"
---

# Design System: RetroBoards — Imladris

> **This file is the visual system only.** Product and technical truth lives in `PRODUCT_DESIGN.md` (renamed from `DESIGN.md` on 2026-08-27); durable product context lives in `PRODUCT.md`; `DECISIONS.md` wins on any conflict. Machine-readable extensions — tonal ramps, shadows, motion, breakpoints, component snippets — live in `.impeccable/design.json`.
>
> **Authoritative sources.** Foundations: `resources/imladris/tokens/{colors,typography,spacing,fonts}.css` and `resources/imladris/components.css`, generated into the low-priority layers of `public/assets/imladris.css`. The final application register is the unlayered `public/assets/app.css`; it wins wherever it overrides those layers. The authoring token copies in `docs/design-system/imladris/tokens/` were verified byte-identical on 2026-10-06. Application-owned semantic tokens (`--field-rule`, `--stage-*`) and the system-dark register live in `app.css` (ADR 0039).
>
> **Reading the tokens.** Frontmatter follows the [portable DESIGN.md format](https://raw.githubusercontent.com/google-labs-code/design.md/main/docs/spec.md). Colours are the stock primitives; component entries capture stock day defaults and specific variants, not every preference or surface override. Paint through the semantic aliases below. The sidecar's snippets carry the final application treatments and inherit live CSS variables. Ramps reuse the authored scales; Amber and Rust receive generated OKLCH preview ramps, not additional application tokens.

## Overview

**Creative North Star: "The Councillor's Hall"**

This is a room where people keep counsel together and the record survives the conversation. Warm stone and aged parchment, evergreen boughs at the windows, one thread of mallorn gold catching the candlelight — and, underneath the ceremony, a working table. The register is Tolkien-adjacent without cosplay: considered, literary, quietly premium, and never a toy social app. Where most forum software looks cheap, Imladris is dressed.

Gravity is the point. The whole product is set in serif — four families, no sans anywhere — because a conversation worth keeping for years deserves the typography of a book rather than a chat client. Colour is warm and low-chroma; nothing is neon; the single gold accent is an *indicator*, never a field, apart from the DM Own-Letter Gold-Wash Exception below. Motion is calm and short. Nothing bounces in Rivendell.

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

- **Mallorn Gold** (`#C29A44`, `gold-500`): the single accent. Unread dots, active indicators, the commend star, the gilt avatar ring, the focus halo, the gold rule on blockquotes. It marks *where to look*, with the DM Own-Letter Gold-Wash Exception below permitting a message surface.
- **Gold Ink** (`#7E5F22`, `gold-ink`): the darker gold reserved for small text on parchment, where `gold-500` would fail AA. Board hashes, regard counts, engraved panel headings.
- **Gold Leaf** (`#F4EBCF`, `gold-100`) and **Gilt Edge** (`#EAD9A8`, `gold-200`): the pale golds behind "needs answer" chips, staff badges, and the on-state of a commend.

### Tertiary

- **Bruinen Blue** (`#3F6E89`, `river-500`): information and the cool counterpoint. Artifact links, the DM register, info status, and one of the four monogram tints. Present so the palette is not monotonously warm; never competing with gold for the eye.

### Neutral

- **Parchment** (`#FAF6EC` → `#DED2B8`, `parchment-50…300`): the world. `parchment-50` raises (cards, topbar, inputs), `parchment-100` is the page ground, `parchment-200` sinks (pills, code, wells), `parchment-300` is the default hairline.
- **Mist** (`#EEF1ED`, `#DCE3DD`): the cooler neutral, an alternative ground and the softer border.
- **Ink** (`#1B231D` → `#94A095`, `ink-900…300`): the text scale, warm near-black down to soft grey-green. Body prose sits at `ink-700`, headings at `ink-900`, meta at `ink-500`, faint meta at `ink-400`.
- **Twilight** (`#161D24`, `#1E2730`, `#283440`): the night register's surfaces.

### The semantic registers

The server stamps `data-theme="light|dark|system"` on `<html>`. System follows `prefers-color-scheme`; its dark aliases are restated in the application layer so a default-system visit gets the same register as an explicit dark choice. Operator branding loads afterward and can override action tokens.

| Purpose | Semantic token | Day | Twilight |
|---|---|---|---|
| Page / raised / sunken | `--surface-page` / `--surface-raised` / `--surface-sunken` | parchment-100 / 50 / 200 | twilight-900 / 800 / 700 |
| Action / action ink | `--accent` / `--accent-contrast` | green-700 / parchment-50 | gold-400 / twilight-900 |
| Quiet brand / brand wash | `--brand` / `--brand-subtle` | green-700 / green-050 | green-500 / translucent evergreen |
| Heading / prose / metadata | `--text-strong` / `--text-body` / `--text-muted` | ink-900 / ink-700 / ink-500 | parchment-50 / pale parchment-green / muted pale ink |
| Small gold text | `--gold-ink` | dark gold ink | gold-400 |
| Engraved field boundary | `--field-rule` | gold-700 | gold-600 |
| Artifact reference | `--artifact-link` | river-500 | river-200 |
| Drawer / dialog dimming | `--scrim` | translucent twilight | deeper translucent twilight |

The older aliases `--surface`, `--surface-2`, `--surface-3` and `--border` still serve compatibility rules. Their twilight mapping differs from raised/page/sunken in places: `--surface` resolves to twilight-700, while `--surface-raised` resolves to twilight-800. Document and preview the token a component actually reads. Established action controls take their existing fills, including gold in twilight; the One Gold Rule continues to constrain decorative fields.

### The status ledger

Status hues are named, not numbered, and each pairs a hue with a wash and an ink: **Leaf** `#4E7459` (solved), **Amber** `#B7842F` (needs answer), **Rust** `#9C4A33` (danger), **Slate** `#3F6E89` (info), plus a neutral pending. Decision-made borrows evergreen; pinned and staff borrow gold; archived is a dashed border with no fill.

### Named Rules

**The One Gold Rule.** Mallorn gold is an indicator, never a field. It may be a dot, a rule, a ring, a star, a hairline, or a halo — it may not be the background of anything larger than a chip, apart from the DM Own-Letter Gold-Wash Exception below. *Audit test: outside that exception, if a gold region on screen is bigger than a status chip, it is wrong.*

**The DM Own-Letter Gold-Wash Exception.** In the DM register, "mine" letters wear the gold wash; nothing else on the surface may. This approved exception to the One Gold Rule is limited to the viewer's own message plates. Existing controls retain their established action tokens; the exception grants no other surface a gold wash.

**The Word-and-Colour Rule.** Status is never carried by colour alone. Every state that has a hue also has a word: "Solved", "Needs answer", "Decision", "Locked", "Archived". Colour-blind and monochrome readers lose nothing. *Audit test: cover the screen's colour and the state is still readable.*

**The Semantic-Only Rule.** Application code paints from semantic tokens (`--surface-raised`, `--brand`, `--on-done`), never from primitives (`--parchment-50`, `--green-700`). The twilight register is a re-pointing of semantics; anything painted from a primitive fails to flip. *Audit test: a new rule that names a primitive scale token is a bug unless it is inside the token layer itself.*

## Typography

**Display Font:** Cormorant Garamond (with Hoefler Text, Garamond, Georgia, Times New Roman, serif)
**Body Font:** EB Garamond (with Iowan Old Style, Palatino Linotype, Palatino, Georgia, serif)
**Label Font:** Marcellus (with Optima, Palatino Linotype, Palatino, Cormorant Garamond, serif)
**Mono Font:** JetBrains Mono (with SFMono-Regular, ui-monospace, Menlo, Consolas, monospace)

All four are self-hosted WOFF2 under OFL 1.1, latin subset — the product runs a same-origin CSP, so there is no font CDN. Every family variable keeps a system-serif fallback so the register reads before the webfonts arrive.

**Character:** A book, not an app. Cormorant's high-contrast display serif gives headings and thread titles the air of a title page; EB Garamond sets prose at a genuinely readable 17px/1.62; Marcellus supplies lapidary roman capitals for the small structural furniture — eyebrows, button labels, chips, meta lines — and JetBrains Mono handles anything countable. The serifs are set **medium (500), not bold**: restraint reads as quality.

### Hierarchy

- **Display** (500, 2.25rem, 1.15, −0.01em): `h1` and page titles. Profile names step up to 2.4rem.
- **Headline** (500, 1.75rem, 1.15): `h2`. The inbox heading sits between at 1.85rem.
- **Title** (500, 1.375rem, 1.15): `h3`, board names. Thread-row titles run 1.2rem, and the study-view topic title 2.15rem with a 28ch measure.
- **Body** (400, 1.0625rem/17px, 1.62): post prose. The shell and ordinary fields use the same size at 1.6 leading (`body-ui`). The 17px equivalence assumes the medium 16px root; reading preferences set the root to 14, 16 or 18px, and rem-based type follows it.
- **Label** (400, 0.72rem, 0.16em tracking, uppercase): eyebrows, chips, meta lines. Button labels are the same family at 0.9rem and 0.03em tracking — and are **not** uppercased.
- **Mono** (400, 0.72rem, tabular numerals): timestamps, counts, routes, breadcrumbs, regard figures.
- **Small / Fine / Meta / Chip** (0.95 / 0.86 / 0.78 / 0.7rem): the named small-text scale for supporting prose, helper lines, structural metadata and the smallest new labels. The scale's `--text-chip` floor is 0.7rem. Older chip, tag, tier and row selectors still contain smaller values; `chip-label` records the existing 0.62rem default, not a new scale step to copy.

**Reading measures** are separate from the shell rails: `--measure-prose` (64ch), `--measure-narrow` (56ch), `--measure-wide` (72ch), and `--measure-column` (646px). The last is a reading-column alignment contract, not a character measure. Existing application overrides still include 66ch on `.post-body`; use the named measures for new reading surfaces without claiming every older selector already consumes them.

### Named Rules

**The All-Serif Rule.** There is no sans-serif in this product. Four families, three of them serif and one a mono for data. A sans-serif anywhere is an intrusion from another design system. *Audit test: grep any new stylesheet for `sans-serif` outside a fallback stack.*

**The Lapidary Caps Rule.** The only uppercase text is Marcellus, tracked at 0.08–0.16em, at label sizes. It is a typographic device, not emphasis and not shouting. Sentence case everywhere else — including buttons, headings, and chips' underlying copy. *Audit test: any `text-transform: uppercase` on something that is not a Marcellus label is wrong.*

**The Tabular Rule.** Anything countable — reply counts, regard, timestamps, member totals — is set in JetBrains Mono with `font-variant-numeric: tabular-nums`, so columns of numbers align and a changing count does not reflow its row.

## Layout

A full-height application shell of three columns plus a top bar. The rails are fixed tokens: top bar **62px** on desktop and **108px** at the mobile drawer breakpoint, sidebar **272px**, topic list **410px**, and a **1280px** content maximum for centred pages. The mobile top bar gives Boards, Inbox, and Messages their own row; a bar with a single route (a guest's) keeps one 62px row, and drawer and scroll offsets follow whichever height the bar takes.

The three panes map to real URLs rather than to client state: `/` is the forum index, `/inbox` the personalised topic inbox, `/c/{slug}` a board's fixed-order list, `/t/{id}-{slug}` the conversation. Navigation is server-rendered; JavaScript decorates.

**Density is a first-class axis, not a preference toggle bolted on.** A topic list in a page reads at two densities from the same markup: *comfortable* (a flat ruled row with avatar, byline, chips, two-line snippet and meta) and *compact* (one scannable line, snippet hidden, author folded into the meta). The foundation's card-shaped topic row is overridden by the application's ruled register. The board and the inbox are **presentations**, not densities, and keep their own geometry at either density: the *board* uses six tracks — gutter, avatar, copy, status, activity, star — on a 64px minimum floor, or 56px when avatars are hidden. Its status owns a column, so this presentation has no status left-rule; a board does not label itself. The *queue* is a ruled triage row that leads with the reason a topic is there. A long title wraps and the row grows — these heights are minimums, never crops.

**Spacing** runs on a 4px base: 4, 8, 12, 16, 24, 32, 48, 112px. Cards are padded 18px, default thread rows 14/16px, and posts 18/20px. The application topic list uses zero row gap and a separating hairline; the foundation's 10px card gap is not the final list treatment.

**Responsive.** The architectural breakpoint is **860px**: three panes collapse to one column, the sidebar becomes a slide-in drawer, and a conversation grows a back link to the list it came from. Secondary breakpoints at 900px (the chrome compacts: the admin bar wraps and its tier scrolls, the member top bar and board rail tighten, and Messages drops to one pane) and 760px (in-pane density) carry most of the rest.

**Messages has surface-specific widths.** Above 900px it shows list and conversation. Its on-demand details rail becomes a column at 1400px with the board rail closed; with the board rail open, the implemented rail stays a drawer through 1699px and becomes a column at 1700px. That 1700px step remains an unconfirmed deviation in `.impeccable/surfaces/messages.md` §8, not an approved addition to the shared breakpoint vocabulary. At 900px and below the room shows one pane and Details opens as a full overlay. The root design guide records these widths; the surface brief owns the direction and open decision.

### Named Rules

**The Real-URL Rule.** Every pane state is a URL that renders server-side and survives a hard refresh, a share, and a crawler. A view that only exists after JavaScript runs is not a view. *Audit test: load it with JavaScript disabled; if the content is gone, the layout is wrong.*

**The Two-Breakpoint Rule.** 860px is the shell breakpoint and 900px the chrome breakpoint; 760px is the in-pane density step. Application styles also contain surface-specific thresholds; those are implementation facts, not a larger shared vocabulary. New responsive work reuses 860, 900 or 760, or justifies a new width in review. Recording the Messages 1700px deviation does not approve it.

## Elevation & Depth

Layered, warm, and shallow. Depth comes from a five-step shadow scale plus tonal layering between three parchment surfaces — raised, page, sunken — and the border hairline does much of the work that a shadow would do elsewhere. Nothing is glassy except two deliberate chrome bars (the topbar and the admin bar), which sit at ~90% surface with a 10px backdrop blur so content scrolls under them legibly.

Shadows are cast in **warm ink** (`rgba(27,35,29,…)`), never neutral black. The modal scale uses twilight ink (`rgba(22,29,36,…)`); the shared shadow tokens retain their authored values in both theme registers rather than receiving a second dark-theme scale.

### Shadow Vocabulary

- **`--shadow-xs`** (`0 1px 2px rgba(27,35,29,.06)`): the foundation's low resting elevation, retained by ordinary cards, buttons and posts. Ruled application topic rows override it to no shadow.
- **`--shadow-sm`** (`0 1px 3px rgba(27,35,29,.07), 0 1px 2px rgba(27,35,29,.05)`): the selected row, the accepted answer, a switch knob.
- **`--shadow-md`** (`0 4px 14px rgba(27,35,29,.08), 0 2px 5px rgba(27,35,29,.05)`): available hover elevation in the foundation; the final application topic row responds with a tonal wash instead.
- **`--shadow-lg`** (`0 12px 32px rgba(27,35,29,.12), 0 4px 10px rgba(27,35,29,.06)`): popovers and menus.
- **`--shadow-xl`** (`0 24px 60px rgba(22,29,36,.18), 0 8px 18px rgba(22,29,36,.08)`): modal dialogs only.
- **`--shadow-inset`** (`inset 0 1px 2px rgba(27,35,29,.07)`): fields and tracks — the impression of something pressed into the page.
- **`--gilt`** (`inset 0 0 0 1px rgba(194,154,68,.38)`): not a shadow but a thin gold inner ring, marking a "precious" avatar — the OP, an accepted answer, a profile, a top-three leaderboard place.

### Named Rules

**The Warm-Shadow Rule.** No pure-black drop shadows anywhere. Every shadow is mixed from ink or twilight so it reads as candlelight on parchment rather than as a UI kit default. *Audit test: any `rgba(0,0,0,…)` in a shadow is wrong.*

**The Lift-On-State Rule.** Ordinary surfaces use no shadow or the shallow `--shadow-xs`. Elevation is a *response* — hover, selection, focus — not a decoration. The foundation's interactive row offers a 1px lift and `--shadow-md`; the application topic list deliberately overrides both with a tonal hover wash. Use the final surface treatment rather than reinstating a foundation effect it has superseded.

**The Chrome-On-Top Rule.** Page content stacks at `z-index` 30 or below. The member bar sits above it at `--z-chrome` (40), so nothing on the page paints over the bar or its account menu. The phone drawer's scrim (`--z-scrim`, 55) and rail (`--z-drawer`, 60) come next. Popovers anchored to page content, sheets and dialogs already start at 42. A page control that must cover the bar is an overlay and takes an overlay's tier. These are application tokens (ADR 0039's block in `app.css`), because the layer's own bar sits at 20. *Audit test: on a phone, scroll content under the bar and tap its lower edge; the tap must land on the bar.*

## Shapes

Restrained and rectilinear. Radii are `sm 4px`, `md 7px`, `lg 12px`, `xl 20px`, and `pill 999px`, and the system uses the small end far more than the large: system containers and posts at 12px; buttons, fields and menu items at 7px; inline code at 4px. The generic application card reads `--radius` (7px by default, 6px in compact density); the board's ruled row is square.

The **pill is reserved for tokens** — chips, badges, tags, filter tabs, segmented controls, the search field, the tier marker. Pill-ness means "this is a small labelled thing", so a pill-shaped button or card would misread as a status token.

Borders do real work: a 1px `--border-hair` in parchment-300 is the system's default line, and much of the interface is built from hairlines rather than fills. Interactive controls step up to 1.5px so the outline reads as an affordance.

The recurring silhouette is the **left rule**: a 3px vertical band on the leading edge of the default topic row, coloured by status and transparent when there is nothing to say. It is one way the eye scans a long list for the topics that matter. The board presentation puts the status word in its own reserved column and removes the rule; the queue has its own triage grammar. A signature belongs to the presentation that uses it, not automatically to every row.

### Named Rules

**The Twelve-Max Rule.** Nothing that holds content is rounder than 12px. 20px exists for a handful of large containers and should be justified; pills are for tokens only. *Audit test: a `border-radius` above 12px on anything that is not a token or a large container is wrong.*

## Components

The register is **plain**: quiet surfaces, hairline borders, restrained radii, and Marcellus labels. Since 2026-09-13 it is the system's only geometry — the ornamented surfaces differ in ink, never in the shape of a corner.

### Buttons

- **Shape:** gently curved (`7px`), 1px transparent border, `--shadow-xs` at rest, pressing to `translateY(0.5px) scale(.995)`.
- **Primary:** evergreen (`#2E4A3A`) on parchment text (`#FAF6EC`), padded `9px 17px`, Marcellus at 0.9rem with 0.03em tracking, **sentence case**. Hover deepens the resting fill toward black, `color-mix(in srgb, var(--accent) 82%, #000)` (the danger button's recipe): about `green-800` by day, a deeper gold in twilight, and the operator's own colour under branding, never a different hue (ADR 0039).
- **Secondary:** raised parchment (`#FAF6EC`) with ink text and a 1.5px `--border-soft` outline, no shadow. Hover sinks the fill to `parchment-200` and strengthens the border.
- **Ghost:** transparent with `ink-700` text and a transparent 1.5px border, so it occupies the same box as its siblings and does not shift the row on hover.
- **Accent:** mallorn gold (`#C29A44`) with `ink-900` text — the one place gold is a fill, reserved for a single moment of emphasis per screen.
- **Danger:** rust on white. The existing `.btn.danger` pins its dark-theme fill to `--rust`, preserving white-ink contrast; the foundation's `.btn-danger` follows `--danger`. Those selectors are not interchangeable in twilight.
- **Disabled:** 50% opacity, `not-allowed`, and hover suppressed.
- **Icons:** 16px, stroked at 1.9 with round caps and joins, `fill: none`, `stroke: currentColor`.

### Chips, badges, pills and tags

- **Chips and badges** (status and role) are Marcellus caps at 0.62rem with 0.1em tracking, `3px 9px`, pill-shaped, and always bordered — a wash plus a 1px border in the matching hue, so they read on any surface. Icons inside are 11px stroked at 2.
- **Pills** are the larger, quieter status token: `2px 10px`, 0.72rem, sunken parchment.
- **Tags** are the smallest: `2px 8px`, 0.6rem, 0.08em tracking, for board and meta labels.
- **Tier markers** (`Member · Veteran · Loremaster · Legend`) are 0.58rem caps at 0.11em, each tier taking its own hue: gold for Legend, evergreen for Loremaster, river for Veteran, neutral for Member.
- **The console status pill** is `.state`: `1px 9px`, Marcellus at 0.66rem, sentence or token case as written. A lifecycle word keys its tone (`state-active`, `state-revoked`), and any other label names one (`state-done`, `state-review`, `state-danger`, `state-muted`, `state-staff`). Outside the console the same class is a dot and a word. A new status is a tone on `.state`, never a new `*-pill` class (ADR 0037).

### Cards and containers

- **Corner style:** the foundation container is 12px; the generic application `.card` uses the preference-aware `--radius` (7px default, 6px compact).
- **Background:** the foundation reads `--surface-raised`; the generic application card reads the older `--surface` alias. Both are parchment by day and resolve to different twilight surfaces.
- **Border:** 1px `--border-hair` in the foundation, the legacy `--border` alias on the generic application card.
- **Shadow:** the foundation's shallow `--shadow-xs` carries through to the generic application card; ruled lists override it to no shadow. See Elevation.
- **Internal padding:** 18px, or `14px 16px` for a thread row and `18px 20px` for a post.

### Inputs and fields

- **Style:** raised parchment, 1.5px `--border-soft`, 7px radius, `--shadow-inset`, set in the body serif at inherited size and padded `9px 11px`. A search field takes the pill variant on the sunken page colour. Until 2026-09-13 the application stylesheet overrode this with a 1px `--border` hairline at 6px and no inset, and — because `app.css` is unlayered — that is what actually painted on every `<input>` and `<select>`, while `<textarea>` got the spec. There is now **one** field register (ADR 0034).
- **Focus:** the gold halo — border shifts to `gold-400`, a 2px action-colour outline at 1px offset, and a layered `0 0 0 3px` `--focus-ring` over the inset. The outline follows evergreen by day, gold in twilight, and the operator's action colour under branding. Engraved fields have their own border and focus overrides.
- **Engraved frames** (the lapidary fields on the auth screens, account settings, appeals and the Messages compose form) draw their edge in `--field-rule`: `gold-700` by day and `gold-600` in twilight. That holds 3:1 against the card and against the field's own fill in both registers, as WCAG 1.4.11 asks of a field's boundary. The layer's `--gold-200` measured 1.30:1 by day and drew a near-white line by night (ADR 0039).
- **Labels:** Marcellus at 0.82rem, muted ink, 5px above the control.
- **Errors:** rust text at 0.85rem directly beneath the field, with underlined inline links.
- **Switches:** a 42×24 pill track, sunken parchment with a strong border and inset shadow, carrying an 18px round knob ringed in `gold-200`; checked fills the track evergreen and gilds the knob to `gold-200` with a `gold-500` ring. Transitions run `--dur-base` on `--ease-calm`.

### Navigation

- **Topbar** (62px, 108px in the mobile member shell): a raised-surface wash with a 10px backdrop blur and a hairline bottom border. Brand star and wordmark in Cormorant; Boards, Inbox and Messages are direct links with full mobile labels and server-rendered counts. Search is a trigger labelled "Search the council…", followed by the bell and identity cluster. The operator's lockup takes only the room the other controls leave: a long community name ends in an ellipsis, and an uploaded logo scales down inside its 28px band, so neither pushes the account menu off-screen. The pane toggles have fixed names ("Board rail", "Reading pane") and carry their state in `aria-pressed`; each draws its band filled while the pane is shown and outlined while it is hidden (the star toggle's grammar). The reading-pane toggle appears only from 1280px, where the pane is a column. Gold in the bar means unread, and the Inbox leads: its count wears the thread row's unread mark, the gold dot and its 2px halo, beside medium-weight numerals. Messages, the bell and the account menu's Notifications row share one quiet chip: the gold-soft wash with gold ink, in JetBrains Mono at the chip floor with tabular figures. The bell's count sits beside the glyph and never covers it. The current surface carries a 2px rule in the pill's own ink as well as the wash; the rule is a border, so it survives forced colours. At the 860px drawer breakpoint the first row's controls (drawer opener, lockup, search, bell, seat) are 44px targets with up to 8px between them. Gaps ease to 4px with the available bar width, including classic scrollbar gutters; outer and counted-bell padding yield before targets do. The routes' row keeps the desktop's 13px labels at the same floor, with horizontal padding that adapts so Large text and capped counts fit at 320px, and the reading and focus order follow the rows (app.js moves the routes after the account controls there). The community's name stays at every width it can show at least about five letters; below that it drops whole and the mark stands alone, never a stub. On a phone the lockup's wrapper takes the row's spare room as a size container, so the link holds only what it shows and the search joins the actions on the right. Above 1080px the name and the search share the room. The divider stands only beside a pane toggle. Touch screens above 860px keep the same 44px floor (`pointer: coarse`). Every bar control keeps its own corner when focused and wears the gold halo. Shortcut hints appear only with JavaScript, which answers them, in the platform's own modifier (⌘ on Apple devices, Ctrl elsewhere) and with `aria-keyshortcuts`; the hint sits at the chip floor. A count's words live on its link ("Inbox, 4 unread topics"; Messages, the bell and Moderation alike) and its digits are `aria-hidden`. The bell is current on `/notifications`, with the routes' wash and rule. The account menu falls into three groups divided by hairlines (your places; settings and authority; Log out), and Settings wears the sliders glyph rather than Profile's person. Auth pages use the plain shell and do not acquire the member shell's mobile height (ADR 0042).
- **Shared subheader:** a page-surface row inside the main content column, ahead of flash messages, with a `--border-hair` bottom rule and `--space-2` vertical gutters. Inbox heading/unread information, Show/topic count, Sort, actions and creation share one physical row. Other simple member headings and applicable controls occupy its leading side, superseding the earlier empty-row/separate-heading treatment. Compact captions follow the existing available-width step, using semantic type and spacing tokens while retaining 44px targets and named current choices; menus and focus rings remain viewport-safe. Board/topic breadcrumbs or Boards directory tabs render once; long board names wrap within the leading column, retaining full text and room for creation. Home, board and topic content headings, profile identity and conversation headings keep their content hierarchy. The trailing create trigger is a quiet 44px target in label type, strong ink, a hairline and the ordinary rounded corner, with a sunken-surface hover and the gold focus halo. Above 860px it shows "+ New" and a chevron; at 860px and below it shows only a plus, named "New topic or message". Its native menu offers board-aware New topic, then New message, and marks the current task with `aria-current="page"`. With `dms` off it is a direct New topic link, retaining its name when the phone hides its label. Members get the row on app and plain member-header pages; guests retain original page headers and applicable leading navigation, with no empty band. It scrolls with the page; Messages and Inbox reserve its height above their scrolling rooms. The board slab keeps its accent New topic action. The popover follows the Chrome-On-Top Rule, using the existing menu tier at 50 (ADR 0043).
- **Sidebar rail** (272px): sunken parchment, with category-grouped board links, personal board folders and saved feeds. Board rows share active state, unread pills and private/hidden labels wherever they appear; active rows keep their board URL and a gold left rule. The footer carries public presence and “See everyone online.” Inbox filters and direct messages live on their own surfaces. Document-scrolling rails stay below the member header; the mobile drawer carries its own 44px close cross at its top corner (Topic tools' grammar), the enhanced drawer moves focus to it, contains keyboard navigation while the main page is covered, and restores opener focus on dismissal. Without scripting there is no drawer: on a phone the rail is a bounded block above the content that scrolls away with the page.
- **Filter tabs:** Marcellus pills at `6px 13px`; active fills evergreen with parchment text.
- **Segmented control:** a sunken pill shell with 3px padding; the active item fills evergreen.
- **Underline tabs:** no fill, with a 2px gold active underline drawn as an inset shadow. The foundation sets active strong-ink; the generic application's unlayered link rule overrides that to `--accent`, including the hover underline, unless the surface provides its own compatibility rule. The sidecar captures that final generic application treatment. Ruled section rows (`.text-tabs.is-ruled`, including the profile) wrap on a full-width hairline and keep Marcellus at its inherited regular weight; other underline tabs retain the existing semibold treatment. URL-changing tabs and segmented switches are links with `aria-current="page"` (ADR 0040).
- **Admin chrome:** a sticky two-row block — a 58px identity row (brand, wordmark, exit link, an uppercase mode pill) above a scrolling tier of area links on desktop. At 860px and below, a native disclosure names the current area and opens the same ordered, role- and flag-gated destinations without JavaScript. Area navigation uses the pill register, distinct from the page's underline section tabs one heading below.

### Signature: the thread row

The system's most-repeated object and the place its character is clearest. The default application presentation is a flat, ruled entry with a 3px status left-rule, a 44px avatar or monogram, a Cormorant byline, status chips, a Cormorant title at 1.2rem, a two-line clamped snippet in muted ink, and a Marcellus meta line with a gold-ink board reference. Unread state carries a **gold dot with a 2px translucent gold halo** and a semibold title. Selected state uses `--brand-subtle`; hover uses a tonal wash without lifting the row. The foundation's card border and hover shadow are overridden by the application layer.

It is **one partial** (`templates/partials/thread_row.php`) with a `presentation` axis — `default` for a list inside a page, `board` for the canonical index, `inbox` for the personal queue — and never a second row object (ADR 0036). Topic facts read identically in every presentation: the status word from the ledger, last activity as elapsed time on a `<time>` carrying the exact instant, a snooze as a date. Viewer facts render everywhere too, loud in the queue and quiet on the index. The queue's selection box, star toggle and row menu are slots on the row. Each presentation is styled under its own modifier, and restates what it keeps against the generic row and compact rules, which belong to the default list.

### The star

A topic star is a personal bookmark, and its word is **Star** / **Starred** — not Commend, which is the reaction. It has one glyph, the four-point **commend star** (`partials/icon`, `commend-star`), and one control in two sizes (`templates/partials/star_toggle.php`): the labelled pill in a topic's head, and a 28px icon toggle in a queue row whose accessible name carries the topic's title and whose state is `aria-pressed`. The toggle draws the star in outline until it is set and filled once it is, because `--text-faint` and `--gold-ink` are too close in lightness for ink alone to carry the state. Where the star is a marker rather than a control, as on the board index, it is the same glyph in `--star` with `role="img"` and the name "Starred". A board favourite uses the same outline/filled grammar.

### Signature: the monogram

A tinted ground with legible dark ink, rotating through ten variants across evergreen, river, gold, mist and parchment — so a list of members is quietly varied without anyone being assigned a "colour". 36px default, 26–64px by context, always a circle, always Marcellus. The `--gilt` inner ring marks the precious ones.

An uploaded avatar replaces that fallback wherever the member is drawn. `partials/monogram.php` retains the circular `.monogram` frame and optional gilt ring, with `object-fit: cover` for the image. The adjacent identity supplies its name, so the image and fallback are decorative. Anonymous authors keep the masked fallback (USER §5.2).

### Shared components

When a job already has a shared component, use it. A variant is a parameter or a tone, not a new class (ADR 0036, ADR 0037).

- **Topic row** — `partials/thread_row.php`, with a `presentation` of `default`, `board` or `inbox`.
- **Star** — `partials/star_toggle.php`, as the labelled pill or the icon toggle.
- **Console pager** — `partials/pager.php`, for a known page count or only "is there another page?", with a URL builder for routes that count from 0.
- **Empty state** — `partials/empty_state.php`: a heading, a sentence, and at most one action.
- **Back link** — `partials/back_link.php`, with one 13px chevron.
- **Alert plate** — `.callout`, in info, `callout-review` or `callout-danger`, on member and operator surfaces alike. A field's own error is `.field-error` beneath it.
- **Status token** — `.chip` for topic status, and `.state` for console status (above).
- **Glyphs and identity** — `partials/icon.php` and `partials/monogram.php`.

Families that still have more than one implementation are listed, ranked, in ADR 0037.

### The lapidary register, and the chamfer that is gone from it

The ornamented treatment that dresses the account templates and five of the six auth screens — gold-ruled parchment frames, engraved panel headings, gold row dots. It is **ink, not geometry**: an engraved frame is the same box as a plain one, drawn in gold.

The auth screens sit on a **stage** that is twilight in both registers, while the seal card on it flips with the theme. The stage paints from tokens that do not flip: `--stage-ground`, `--stage-raised` (the skip link's plate), `--stage-ink` and `--stage-accent`, the one gold of the brand star, the card's rule, and focus on the stage. Focus there cannot use the page's `--accent`: it is evergreen by day, drawn for parchment, and measured 1.75:1 on the stage (ADR 0039).

It was not always. Until **2026-09-13** these frames cut their corners at 45°, an octagon made from a `clip-path` plus eight background-gradient layers standing in for a border, with a second inset octagon on `.scribe-panel` and `.auth-card` as a doubled rule. That chamfer is removed (**ADR 0033**). It was expensive — every state restated all eight layers — and it was actively harmful: a `clip-path` cuts everything outside the octagon, so the outer focus ring on an engraved field, a choice card and a search well had *never rendered*, and the octagon on `/compose` flooded the title field solid gold on focus.

Six frames changed and none moved: `.auth-card`, `.input-engraved`/`.textarea-engraved`, `.scribe-panel`, `.field-row`, `.choice-card`, and the `.search-query-well` trio. Each keeps its ink and its padding, and the corner becomes `--radius-md` or `--radius-lg`. Five of the six now draw their edge as a real 1–1.5px border; the trio is the exception and always was — it draws its edge as `inset 0 0 0 1.5px` over `border: 0`, which is fine, because an inset ring follows a radius where it could not follow a chamfer.

**Do not reintroduce a cut corner, or a rotated square.** A frame is a border and a radius; a marker is a dot. The same pass turned the diamond `.row-bullet` and the `.choice-card` selected-marker into dots and deleted the orphaned set-gem toggles from the application stylesheet, so the register now carries no angular geometry at all — `clip-path: polygon` and `rotate(45deg)` both appear zero times in `app.css`, and a test holds them there. The treatment is still legacy in the sense that it should not spread to new surfaces, but it no longer makes two surfaces of one product a different shape.

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
- **Don't** write an inline `<style>` block, an inline `<script>`, or a `style="…"` attribute. The same-origin `style-src` and `script-src` policies block those forms. This constraint applies to application templates; the sidecar's CSS strings are tooling specimens, not styles to paste inline into PHP.
- **Don't** use gold as a background for anything larger than a chip outside the DM Own-Letter Gold-Wash Exception, and don't use two accents — the palette has exactly one.
- **Don't** put emoji in UI chrome. Status is a word and a colour. (Emoji in member-authored content is a product feature and stays.)
- **Don't** print ★ or ☆. The commend star, drawn with `partials/icon`, is the one esteem glyph; `templates/` holds the characters at zero and a test holds them there.
- **Don't** paint directly from a primitive scale token in application CSS.
- **Don't** round anything holding content past 12px, and don't make a button or card pill-shaped. *Two live exceptions are unresolved rather than sanctioned — `.star-btn`/`.topic-tools-open` and `.board-mute-toggle` are buttons wearing pills because the handoff canvases draw them that way while this document and `components.css` say 7px. The Messages room adds two more to the same open question (2026-09-23): `.dm-newpill`, the "New messages" button, which the committed Messages mock draws as a pill; and the room's toast flash (`.main > .flash:has(+ .dm-shell)`), a `role="status"` notice rather than a button, but a pill-shaped plate holding a sentence. ADR 0034 records the conflict (the Messages pair in its 2026-09-23 addendum); it needs a ruling, not a sweep.*
- **Don't** use a pure-black shadow, or add elevation to something that is merely at rest.
- **Don't** cut a corner. No `clip-path` octagons, no frame whose edge is drawn as background-gradient layers — a frame is a real border plus a radius, and a `clip-path` on a control silently eats its outer focus ring. Don't extend the lapidary register to new surfaces either; it is legacy and shrinking.
- **Don't** invent a new breakpoint. 860px collapses the shell, 900px compacts the chrome, and 760px stacks in-pane layouts; reuse them.
- **Don't** rename the lexicon where it has shipped. On member surfaces reputation is **regard**, badges are **marks of esteem**, like is **commend**, and the leaderboard is **top contributors**. *Counsel* is a register word ("Add your counsel", "Private counsel"), not the name of the reply control, which reads **Reply** with an "N replies" count (ADR 0027); the admin and moderation consoles use plain words such as Reputation and Badges (ADR 0024). `PRODUCT.md` records the lexicon as a binding brand commitment; how far it reaches beyond the shipped member strings is an open owner decision (ADR 0024, obligation 5).
