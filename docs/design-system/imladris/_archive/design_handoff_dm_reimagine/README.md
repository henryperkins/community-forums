# Handoff: Direct Messages — "one reading room" (and consolidating the UI under it)

## Overview

Rework RetroBoards' **Direct Messages** from a scattered, multi‑screen surface into a single **reading room**: a conversation **list**, the open **conversation**, and a collapsible **details rail** — with all secondary controls tucked into menus and the rail. Then use the same principles to consolidate the rest of the product's UI.

The trigger (in the stakeholder's words): the current DMs feel *"very everywhere and confusing."* Three specific complaints:
1. *"Several main screens that lead to the same thing."*
2. *"Controls (mute, leave, report, members) always visible instead of tucked away."*
3. *"Every element boxed in its own bordered card."*

The direction keeps the **full ceremonial Imladris register** (serif type, parchment + evergreen + a single mallorn gold, gilt monograms, the eight‑point star). Nothing about the visual language changes — only the **information architecture and density**.

---

## About the design files

`prototype/` contains an **HTML/React reference** that renders on the Imladris design‑system bundle. It is a **design reference showing intended look and behavior — not production code to copy**. Open `prototype/dm-reimagined.standalone.html` to see and click the target; read `prototype/SOURCE.md` for exact structure, class names, spacing, and copy.

**Your task is to recreate this design in the real app's existing environment** — the server‑rendered **PHP templates**, the single external stylesheet **`public/assets/app.css`**, and the single external script **`public/assets/app.js`** — honoring the app's strict CSP and progressive‑enhancement conventions. Do **not** introduce React, a build step, a CDN, or inline styles/scripts.

## Fidelity: **High‑fidelity.**

Colors, typography, spacing, radii, and motion are final and come from tokens already in `app.css :root` (the prototype's tokens were transcribed from that file, unchanged). Recreate faithfully with the app's existing tokens and partials.

---

## Re-sync the embedded design-system copy (stale — read before lifting any CSS)

Canonical is the **live design system** (`index.html` and the `ui_kits/` + `tokens/` it renders). The repo also carries a **dated, vendored snapshot** at `docs/design-system/imladris/` (pulled 2026-06-29). Treat that snapshot as a **mirror, not the source of truth** — it is behind canonical, and its Messages kit will mislead you if you copy from it:

- **Its DM kit is the *pre-reimagine* two-pane design.** `docs/design-system/imladris/ui_kits/dm/kit.css` is the older *list + conversation* layout — **no** details rail, two-line row previews, a still-present `.dm-group-meta` participant line, and none of the lock / "Private counsel" signature. It does **not** represent this handoff's target; use `prototype/` here as the reference, not the snapshot.
- **There is no river to "fix" in the snapshot.** Its DM rows are already parchment + gold (`.dm-row.active { background: var(--brand-subtle); box-shadow: inset 3px 0 0 var(--accent-2) }`; `unread-dot { background: var(--accent-2) }`). The cool "Bruinen" DM room never lived there — it existed only briefly in the *reimagine* source and in the earlier draft of this bundle, and has now been **reverted**. If someone points at "the old blue DMs," they mean that reverted draft, not the snapshot.

**The one real colour delta** — the river → gold correction now baked into this handoff's `prototype/kit.css` — is the `.app-root --dm-*` block. If you ever regenerate or diff the reimagine source, these are the canonical values (never reintroduce the `--river-*` column):

| `--dm-*` variable  | Reverted (river draft)                                            | Canonical (parchment / gold)        |
| ------------------ | ---------------------------------------------------------------- | ----------------------------------- |
| `--dm-ground`      | `color-mix(in srgb, var(--river-100) 40%, var(--surface-page))`  | `var(--surface-page)`               |
| `--dm-raised`      | `color-mix(in srgb, var(--river-100) 34%, var(--surface-raised))`| `var(--surface-raised)`             |
| `--dm-sunken`      | `color-mix(in srgb, var(--river-200) 40%, var(--surface-sunken))`| `var(--surface-sunken)`             |
| `--dm-accent`      | `var(--river-500)`                                               | `var(--accent-2)` (= `--gold-500`)  |
| `--dm-accent-soft` | `var(--river-100)`                                               | `var(--gold-soft)`                  |
| `--dm-accent-line` | `var(--river-200)`                                               | `var(--gold-200)`                   |
| `--dm-accent-ink`  | `var(--river-700)`                                               | `var(--gold-ink)`                   |
| `--dm-active-wash` | `color-mix(in srgb, var(--river-100) 62%, var(--surface-raised))`| `var(--brand-subtle)`               |

Plus two focus rings that hard-coded a river glow — both are now the gold `--focus-ring` token (already present in `app.css :root`, L102):
- `.dm-search input:focus` → `box-shadow: 0 0 0 3px var(--focus-ring);`
- `.dm-composer-field:focus-within` → `box-shadow: 0 0 0 3px var(--focus-ring);`

**Net for implementation:** you're building DMs directly in `app.css` (not consuming the snapshot), and `app.css` already uses the gold `--focus-ring` and never defined `--dm-*` — so in practice this just means **carry no `--river-*` value into the `.dm-*` block; use the parchment/gold column.** Re-pulling the vendored snapshot is optional housekeeping.

---

## Hard constraints (read first)

- **Strict CSP** (`src/Security/SecurityHeaders.php`): `default-src 'self'; script-src 'self'; style-src 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'; img-src 'self' data:`. **No inline `<style>`/`style="…"`, no inline `<script>`/`onclick`, no `unsafe-inline`, no CDN.** All CSS goes in `public/assets/app.css`; all JS goes in the single `public/assets/app.js`.
- **Progressive enhancement.** Every interaction must work with **no JavaScript** (server‑rendered forms + links), then be *enhanced* when `document.documentElement.classList.contains('has-js')`. Reuse the patterns already in `app.js`:
  - **`details.composer-details`** — a **native‑`<details>` modal**: the open `<details>` paints a full‑viewport `::before` backdrop; `app.js` adds Escape‑to‑close, a Cancel button, and backdrop‑click dismissal. This is the pattern for the **new‑message dialog** and the **··· menus**.
  - **`nav-open`** — a body‑class off‑canvas drawer with a scrim + Escape. This is the pattern for the **mobile details‑rail overlay** and the **rail toggle**.
  - `document.documentElement.classList.add('has-js')` runs first; gate all enhancements (and any `:hover`‑reveal that must not trap keyboard users) accordingly.
- **Fonts** are self‑hosted / system‑serif fallback (CSP blocks Google Fonts). Keep the existing families; don't add `@import`.
- **No emoji.** Line icons are **Lucide** as **inline SVG** (add/extend a `partials/icon.php` helper — never a CDN sprite). Brand marks are the existing **✦ commend** and **eight‑point star** partials.
- **Routes, controllers, DB, CSRF, and feature‑gates are unchanged** except one *additive* read for the read receipt (see State/Data). Keep every `$this->csrfField()`; keep the **group‑DM feature gate** (`group_dms`, deploy‑dark Phase 4) around all group‑only UI.

---

## Consolidation principles (apply beyond DMs)

These are the reusable rules that make the DM redesign work — carry them into the inbox, thread, and profile chrome as you "consolidate the existing UI under this direction":

1. **One canonical surface per task.** Don't ship several co‑equal screens for one job. Keep secondary routes (e.g. `/messages/new`) as **no‑JS fallbacks** that, when JS is present, open **in place** as a dialog or rail over the primary surface.
2. **Reveal controls on demand.** A single **···** overflow in a header plus a **···** revealed on row/message hover — never a standing row of buttons. Destructive actions live behind the menu or in the rail, styled `danger`, never in the default eyeline.
3. **A right‑hand details rail** (identity → facts → quiet actions → danger) is the home for "everything about this thing." It's collapsible, a real column at wide widths and an overlay on mobile. Reuse the same rail shell for a thread's participants or a profile peek.
4. **De‑box.** Prefer **grouped runs + hairline dividers** over nested bordered cards. Give "mine / primary" **one** accent plate; leave everything else as plain text on parchment. One decorative move per surface (the faint star watermark), not a field of boxes.
5. **Status is a word + colour** (Solved/leaf, Needs answer/amber, Danger/rust), never colour alone and never an emoji. **Reuse `:root` tokens**; introduce **no new colors**. Motion is `--ease-calm` at `140/240/420ms`, `prefers-reduced-motion` respected.

---

## Ground it in the app chrome (integrated from the flagship inbox)

The single biggest "less everywhere" win: **render the DM surface inside the app's existing chrome, not as a standalone page** — do this in Phase 1, before the de-box. The flagship **Community Inbox** already ships a left **nav rail** (Home / Inbox / Messages / Following / Drafts) and, on mobile, a **bottom tab bar** with a center compose FAB. The DM shell must sit *inside that same layout* with **Messages** marked active (`aria-current="page"` + the gold `inset 3px 0 0` rule) — reuse the existing base-layout / nav partial; do **not** rebuild or fork it. This is what makes DMs read as one destination in the product instead of a detached island.

### Tell private counsel apart from the public hall (important)

Grounding DMs in the same chrome makes them look *too* like a forum thread. Differentiate the **reading room** (not the global chrome) so you know at a glance which you're in — **by a word, not a colour.** The room stays in the canonical warm register (parchment surfaces + the single mallorn gold); Messages is set apart by an explicit **privacy signature**, in keeping with the system rule that *status is a word + colour, never colour alone*:
- **A lock signature — the primary cue.** A small **lock + "Private counsel"** eyebrow leads the list header and every conversation header (`Private group` for groups), and the opening divider reads *"Private — only those named here can read."* This is the fastest "which am I in" cue, and it survives where colour can't (print, forced-colors, colour-blind readers).
- **A warm room, one accent.** The list + conversation + details + composer sit on the **standard parchment surfaces** (`--surface-page` / `--surface-raised` / `--surface-sunken`) — the same room as the rest of the product, **not** a separate tint. Every status signal in the room — unread dot, active-row rule, filter-chip active, read receipt, reference-card label — uses the **single gold accent** (`--accent-2` = `--gold-500`, plus `--gold-200` / `--gold-ink`), exactly like the forum inbox. **Do not tint the room:** a cool river-tinted ground (`--river-*`) was an earlier direction and has been **reverted** to align with `index.html` — see *"Re-sync the embedded design-system copy"* below for the exact before → after.
- **Gold = voice + esteem + status; green = actions.** *My* message plate stays the ceremonial **gold** plate; gilt monograms, rank pills, and the tier pill stay gold. Green stays the action colour (send / compose). **River** stays reserved for its **canonical** roles only — info flashes, artifact / reference links, monogram avatar tints — and is **never** the room ground. So: **gold = my voice + esteem + status, green = actions, parchment = the room.**

Other small treatments lifted from that same inbox (token-only, no new colors):
- **Filter chips** — All / Unread as small-caps Marcellus pills (active = the gold wash `--gold-soft` + `--gold-200` border).
- **Quoted reply -> blockquote** — a quoted line as `<blockquote class="dm-quote">` with a gold left-rule (`--rule-gold`), italic, `--text-muted`; quoted author in a small-caps label.
- **Rank pill** — beside a **group** author's name, a small tier/role pill (`--gold-100` / `--gold-200` / `--gold-ink`), like the inbox's "Steward". Direct 1:1 shows none.
- **Composer avatar** — the sender's `monogram` beside the composer field.

---

## Surfaces & exact mapping (before → after)

Current DM CSS lives in `public/assets/app.css` roughly **L2552–2990** (`.dm-*`) plus the responsive block near **L4067–4090**. The prototype's `kit.css` is the target for that block (same class names where possible, so most of your diff is CSS).

### 1) List — `templates/dm/index.php`
- **Header.** Keep the `eyebrow` + `<h1>Messages</h1>`. Turn **"New message"** from an `<a href="/messages/new">` into a round **`+`** button that opens the **compose dialog** when `.has-js`; with no JS it stays a link to `/messages/new`.
- **Add a conversation search.** A `GET` form (`/messages?q=…`) with a pill input (server filters `last_body` / other display‑name); when `.has-js`, filter the already‑rendered list client‑side for instant feedback. Additive `q` param only.
- **Filter.** Keep the All / Unread pills (`/messages?filter=unread`).
- **Rows.** Keep `.dm-row` (grid: monogram · name · time · preview). **Remove** the `.dm-group-meta` participant‑name line. Unread → a **single gold dot** at the row's far right (`.dm-unread-dot`), and a one‑line preview (`-webkit-line-clamp:1`). No per‑row box.
- **Shared shell.** In the new model, the list and the open conversation are the **same `.dm-shell`** (list is always the left column); `index.php` is that shell with an empty right pane, `show.php` is the same shell with the conversation filled — not two different page shapes.

### 2) Conversation — `templates/dm/show.php` (the big one)
- **Layout.** `.dm-shell` becomes **list | conversation | details `<aside>`**: 3 columns at **≥1181px**, the rail as a **right‑edge drawer** at **≤1180px**, and **single‑pane** at **≤900px** (list ↔ conversation with a back control; rail as a full overlay). Render the rail as a server‑side `<aside class="dm-inforail">` (see partial below).
- **Header → one row.** `monogram(gilt)` + title + a sub‑line (**direct:** `@handle · presence`; **group:** `N in counsel`), then a **details‑toggle** button and **one ··· overflow menu**. **Delete** the always‑visible **Mute** / **Leave** buttons and the inline **`.dm-group-panel`** — both move into the rail / the menu. The menu items just submit the **existing POST forms** (mute, leave, rename, transfer) or open the rail.
- **Messages → grouped "letters."** Group **consecutive messages by `user_id`** into runs. Render the author line **once per run** (`.dm-name` + one time). **Theirs:** plain body text on parchment (no bubble border/shadow), with a small monogram at the run's start. **Mine:** keep `.dm-mine` as the **one gold plate** (`--gold-soft` bg, `--gold-200` border), right‑aligned. Preserve `id="m{id}"`, the sanitized `body_html`, and the `reference-cards` block (lighten it: hairline + gold left‑rule, smaller).
- **Per‑message ···.** Keep the existing `<details class="dm-report">` but **restyle its `<summary>` as a hover‑revealed ···** that opens a small menu with **Copy** (enhancement‑only, `navigator.clipboard`) and **Report** (the existing reason `<select>` + details + POST to `/dm/{id}/report`). Hidden until hover/focus.
- **Read receipt.** Under my last run, for **direct** conversations only, render **Read / Delivered / Sent** (see State/Data). Skip for groups.
- **Composer.** Keep the `POST /messages/{id}` form and `data-composer-context="dm"`. Restyle to the calm **pill**: an auto‑growing `textarea` + a round **send** button; hint "Enter to send · Shift+Enter for newline" wired in `app.js` (no‑JS still submits with the button).

### 3) New message — `templates/dm/new.php` → the dialog body
- Keep **`/messages/new`** as the **no‑JS route** with today's fields (`to`, optional `title` when `group_dms` on, `body`, posting to `/messages`). When `.has-js`, the list's **`+`** opens a **`details`/`<dialog>` modal** whose body **is that same form**. One markup, two presentations — do not build a second form.

### 4) Details rail — new `templates/partials/dm_rail.php`
- **Direct:** `monogram(gilt, xl)` + name + `@handle` + **tier pill** + a small facts list (**Presence**, **Joined**) + **Mute** (POST `/messages/{id}/mute`) + **Block** / **Report conversation** (danger).
- **Group** (inside the `group_dms` gate): the **members** list (roles, `left` state) + **owner tools** (add / rename / remove / transfer — the **existing POST forms**, relocated) + **Mute** + **Leave** (POST `/members/remove` with the current user).
- This is purely **relocation + restyle** of markup that already exists in `show.php`; no new endpoints.

---

## Interactions & behavior (CSP‑safe recipes)

- **··· menu:** `<details class="dm-menu"><summary aria-label="More">…</summary><div class="dm-menu-pop" role="menu">…</div></details>`. CSS‑only fallback: clicking the summary toggles. Enhancement in `app.js`: close on Escape and on outside‑click (extend the existing document click handler); optional roving focus. Position the pop with CSS (`position:absolute; right:0`).
- **Compose dialog / rail toggle / mobile overlay:** mirror `composer-details` and `nav-open` exactly — a class toggle on a container, a `::before` or sibling **scrim**, Escape + Cancel + backdrop dismissal in `app.js`. Persist the desktop rail's open/closed state in `localStorage` when `.has-js` (default **open ≥1181px**, **closed ≤1180px**).
- **Hover reveal:** CSS `:hover` / `:focus-within` only; ensure every hover‑revealed control is reachable by keyboard focus.
- **Motion:** `--ease-calm`, durations `140 / 240 / 420ms`; wrap non‑essential transitions in `@media (prefers-reduced-motion: no-preference)`.
- **Toast:** reuse the existing **flash** channel (`redirectWithFlash`) for "Reported to the wardens.", "Group renamed.", etc.; restyle the flash as the small centered pill in `kit.css` (`.dm-toast`).

---

## State / data

- **No new routes or tables.** One **additive read**: expose the *other* participant's `last_read_message_id` for a **direct** conversation (already maintained by `ConversationRepository::markRead()` on `conversation_participants`). In `ConversationController::renderConversation()` add e.g. `other_last_read_message_id`; `show.php` renders **Read** when it ≥ my last message id, else **Delivered** (or **Sent** for an optimistic just‑posted reply). Groups: skip receipts.
- **Optional list search:** a `q` query param → `ConversationRepository::listForUser()` gains a `LIKE` on last body / other display‑name. Purely additive.
- Everything else (create, reply, mute, add/remove/rename/transfer, report, mark‑read, pagination, `body_html` sanitize, feature gates) is **unchanged**.

---

## Design tokens (reuse `app.css :root` — add no colors)

Use the semantic names (they already exist; the prototype uses the same):
- **Surfaces / lines:** `--surface-page`, `--surface-raised`, `--surface-sunken`, `--surface-inverse`, `--border-hair`.
- **Brand / accent:** `--brand`, `--brand-hover`, `--brand-subtle`, `--on-brand-subtle`; the single gold `--accent-2` (=`--gold-500`), `--gold-soft`, `--gold-ink`, `--gold-200`.
- **Status:** `--leaf`/`--success`, `--amber`/`--warning`, `--rust`/`--danger`, `--presence`.
- **Ink:** `--text-strong`, `--text-body`, `--text-muted`, `--text-faint`.
- **Type:** `--font-display` (Cormorant), `--font-label` (Marcellus caps), `--font-body` (EB Garamond), `--font-mono` (JetBrains).
- **Shape / depth / motion:** radii `sm 4 / md 7 / lg 12 / pill`; shadows `xs…xl`; `--ease-calm` + `140/240/420ms`.

Confirm each name against `app.css :root` before use; if the app names a token differently, prefer the app's name.

---

## Assets

- **Monogram:** existing `partials/monogram` (`name`, `username`, `gilt`, and — add if missing — `presence`).
- **Icons (Lucide, inline SVG):** add/extend `partials/icon.php` with: `plus`, `search`, `more-horizontal`, `panel-right`, `users`, `bell-off` (mute), `user`, `edit-3` (rename), `user-plus`, `log-out` (leave), `ban` (block), `flag` (report), `copy`, `x`, `check`, `arrow-up` (send). Stroke ~1.8, round caps. Exact paths are in `prototype/SOURCE.md` (the `Overlays.jsx` section).
- **Brand marks:** existing ✦ commend + eight‑point star partials.

---

## Files

**Prototype (reference — in this bundle):**
- `prototype/dm-reimagined.standalone.html` — open this; the clickable target (all source inlined, runs offline).
- `prototype/SOURCE.md` — the un‑bundled prototype source (`index.html` + `kit.css` + each `.jsx` + `data.js`) in one readable file: exact structure, class names, copy, and icon paths.

**Real files to change (in `community-forums/`):**
- `templates/dm/index.php` — list header, search, de‑boxed rows, shared shell.
- `templates/dm/show.php` — 3‑pane shell, one header + ··· menu, grouped letters, per‑message ···, receipt, pill composer.
- `templates/dm/new.php` — same form, opened as a dialog when `.has-js`.
- `templates/partials/dm_rail.php` — **new** details rail (direct + group).
- `templates/partials/icon.php` — **new/extend** Lucide inline‑SVG helper.
- `public/assets/app.css` — replace/extend the `.dm-*` block (~L2552–2990) + responsive (~L4067) with `kit.css`'s rules (drawer at ≤1180, single‑pane ≤900, `.dm-toast`).
- `public/assets/app.js` — enhancement for the ··· menus, the compose dialog, the rail toggle + mobile overlay, composer Enter‑to‑send (extend the existing `has-js` / `composer-details` / `nav-open` code).
- `src/Controller/ConversationController.php` (+ `Repository/ConversationRepository.php`) — additive `other_last_read_message_id`, and optional `q` search.

---

## Suggested phasing

1. **CSS + template de‑box** (grouped letters, lighter plates, one‑line rows). No JS, no backend — ships value immediately and is the biggest "less everywhere" win.
2. **Details rail** partial; move members / owner tools / mute / leave / block / report into it; header **···** menu.
3. **Compose dialog** + **list search** (progressive enhancement over the existing routes).
4. **Read receipts** (additive backend read) + mobile overlay + motion polish.
5. **Consolidation pass:** apply principles 1–5 to the main inbox and thread chrome for one coherent product.

---

## Paste‑into‑Claude‑Code prompt

> You're working in the `community-forums` repo (vanilla PHP, server‑rendered, **strict CSP**: `script-src 'self'; style-src 'self'` — no inline styles/scripts, no CDN; one external `public/assets/app.css` and one `public/assets/app.js`, progressive‑enhancement gated on `document.documentElement.classList.contains('has-js')`).
>
> Read `design_handoff_dm_reimagine/README.md` and open `design_handoff_dm_reimagine/prototype/dm-reimagined.standalone.html` for the target. Reimplement the Direct Messages surface to match it, in the existing PHP templates + `app.css` + `app.js`, honoring the CSP and reusing `:root` tokens (add no new colors). The reading room stays in the canonical warm **parchment/gold** register — do **not** tint it; Messages is set apart by the lock + "Private counsel" signature, not colour. **Render it inside the app's existing nav chrome — the inbox's left nav rail plus the mobile bottom tab bar — with Messages active; reuse that layout partial, don't fork it.** Keep all current routes, controllers, CSRF, and the `group_dms` feature gate; the only backend change is additive (expose the other participant's `last_read_message_id` for the read receipt, and an optional `q` list search).
>
> Do **Phase 1 first** (CSS + template de‑box: group consecutive messages into author runs, theirs plain / mine one gold plate, one‑line rows, lighter reference cards and day divider) and show me the diff before continuing. Then Phase 2 (details rail + header ··· menu), Phase 3 (compose dialog + search), Phase 4 (read receipts + mobile overlay). Mirror the existing `composer-details` modal and `nav-open` drawer patterns in `app.js` for the dialog/menus/rail — do not add a framework or a build step. Every interaction must still work with JavaScript disabled.
