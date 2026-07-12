# Handoff: Thread View — "The Study"

## Overview

A redesign of the community-forums **topic/thread view** (`/t/{id}-{slug}`, currently `templates/thread.php`). The design replaces today's stack of always-open control panels (workflow bar, status forms, tags editor, poll builder, split/merge panel, memory tools — all rendered above the first post) with a **quiet reading surface**: the header carries only the title, status, byline, tags, a one-tap Star, and a single **"Topic tools"** button. Every other control lives in a **slide-in drawer** of accordion sections. Per-post actions move to a **hover toolbar** so the message stream stays pure prose.

This was the winning direction ("C · The Study") of a three-way exploration; the interactive prototype lives at `templates/thread-view/ThreadView.dc.html` in the Imladris design-system project.

## About the Design Files

The files in this bundle are **design references created in HTML** — interactive prototypes showing intended look and behavior, **not production code to copy directly**. The task is to **recreate this design in the community-forums codebase's existing environment** — vanilla PHP templates + a strict-CSP, no-framework front end (`templates/thread.php`, `templates/partials/post.php`, `public/assets/app.css`) — using its established patterns (server-rendered forms, native `<details>`, CSP-safe `app.js` listeners). If implementing elsewhere, choose the framework that fits that project and keep this document as the source of truth.

## Fidelity

**High-fidelity.** Colors, typography, spacing, radii, shadows, and copy are final and use the Imladris token vocabulary already transcribed in `public/assets/app.css :root`. Recreate pixel-perfectly with the app's existing classes/tokens; do not invent new colors or type styles.

## Screens / Views

### 1. Thread page (desktop, max-width 860px column, page bg `--surface-page`)

**Topbar** (existing pattern, unchanged): 58px; bg `color-mix(in srgb, var(--parchment-50) 92%, transparent)` + `backdrop-filter: blur(10px)`; bottom hairline `--border-hair`. Brand 8-pt star (24px, `--green-700`) + "Imladris" wordmark (display serif 600, 1.25rem, `--green-900`); search pill (34px, `--surface-sunken`, radius-pill, "Search the council…" + `⌘K` mono); viewer identity (30px monogram + name) or a primary "Log in" button for guests.

**Thread head** (deliberately quiet — this is the direction's thesis):
- Breadcrumb: mono .76rem `--text-muted` — `‹ Home / #the-archive` (hash in `--gold-600`, board link `--accent`).
- **H1**: display serif (Cormorant Garamond) 500, 2.15rem, line-height 1.14, letter-spacing −0.01em, `--text-strong`, max 28ch, `text-wrap: balance`.
- **Status chip** inline before the title text (and Pinned/Locked chips when set): pill, padding 3px 10px, Marcellus caps .6rem, letter-spacing .14em. Colors (status → text / bg / border):
  - Open → `--on-pending` / `--surface-pending` / `--border-hair`
  - Needs answer → `--on-review` / `--surface-review` / `--gold-200`
  - Solved → `--on-done` / `--surface-done` / `--green-200` (label "✓ Solved")
  - Decision made → `--green-800` / `--brand-subtle` / `--green-200`
  - Archived → `--text-muted` / `--surface-sunken` / `--border-hair`
  - Pinned → `--gold-700` / `--gold-100` / `--gold-200` · Locked → muted/sunken/hairline
- **Byline row** (label serif .78rem `--text-muted`), bottom hairline under it: `Opened by Erestor · 5 replies[ · Tended by @erestor][ · Quiet until tomorrow]` — assignment and snooze render as byline *facts*, not controls.
- **Tags**: read-only chips in the byline row (22px, `--surface-sunken`, hairline, label .66rem). Editing happens in the drawer.
- **Participant stack**: "IN COUNCIL" caps .66rem + overlapping 26px monograms (−7px overlap, 2px `--surface-page` ring) + "+N".
- **★ Star** (the one-tap primary — always on the line): pill 32px; off = `--surface-raised`/hairline with ☆; on = `--gold-soft` bg, `--gold-ink` text, `--gold-200` border, ★ `--gold-600`.
- **"Topic tools" button** (signed-in only): pill 32px, `--border-strong` border, 12px 8-pt gold star glyph, Marcellus .74rem.

**Living brief card** (reading aid — stays on the page): `--surface-raised`, hairline border **+ 3px `--rule-gold` left rule**, radius 12px, padding 13/18/13/19. Header: ✦ `--star` + "LIVING BRIEF" caps .66em `--gold-ink` + refresh stamp mono .66rem (`Refreshed Jul 12 · 6 posts weighed`). Body .97rem/1.6, max 72ch. Source line: "Drawn from [the guard's precedent] and [the accepted answer]" — links `--artifact-link` to `#p{id}` anchors. Staff see "Curate in Topic tools →" (`--accent`), which opens the drawer to the brief section.

**Poll card** (content, stays in the stream): `--surface-raised`, hairline, radius 12px. Header ✦ + "POLL · CHOOSE ONE" caps + Open (`--brand-subtle`/`--green-800`) or Closed (muted) chip. Question: display serif 1.3rem. Not-yet-voted members see option rows (parchment-50, hairline, radius 7px, 14px radio ring; hover `--brand-subtle` + `--green-200` border); after voting / when closed / for guests: result rows — option label .95rem, gold "YOUR VOICE" chip on your pick, count mono right, and a `<meter min=0 max={total} value={n}>` bar (the app already renders polls with `<meter>`). Footer: "27 voices of the council" + staff "Close poll"/"Reopen poll" quiet link; guests see "Log in to cast a voice."

**Post stream**:
- Row: padding 10px 14px, margin 6px 0, radius 12px. **Accepted answer**: an underlay layer `--surface-done` bg + 1px `--green-200` border (whole row reads as a green plate) + flag line "✓ MARKED AS THE ANSWER ✦" (caps .7rem `--on-done`) above the header.
- **Avatar column** 48px: 44px monogram — the DS `Monogram` component / app `.monogram .mono-0..9` classes; **gilt ring** (`--gilt`) for OP and accepted-answer authors. Below it the **regard plinth**: `✦ 3,940` (display serif 600 1rem, star `--star`) over "COMMENDS" (caps .5rem, ls .1em, `--text-faint`). Suppressed for anonymous posts.
- **Header row**: author (body serif 600, 1.02rem, `--text-strong`; anonymous = *"A quiet voice"* italic `--text-muted`), tier chip (Legend gold / Loremaster `--brand-subtle` / Veteran river / Member sunken — caps .58rem), OP (`--brand-subtle`/`--green-800`), Staff (solid `--green-700`, parchment text), Wiki (river), Anonymous (sunken/faint), "Revealed · logged" (gold-soft; appears after a warden reveal), timestamp (label .7rem `--text-faint`).
- **Body**: 1.02rem / 1.62, `--text-body`, max 66ch. Blockquote: 2px `--rule-gold` left rule, italic, `--text-strong`. Lists: 22px indent.
- **Grouped replies**: consecutive post by the same non-anonymous author within 10 minutes drops avatar + header (keeps a 48px spacer + small timestamp). OP, accepted, staff, and wiki posts are never grouped.
- **Day dividers**: hairlines flanking caps .66rem ls .18em `--text-faint` ("THE TWELFTH OF JULY").
- **Reactions row**: pill chips `✦ Commend · 4` (Marcellus .7rem; ✦/✓/❋ glyphs in `--star`). Off: `--surface-raised`/hairline/`--text-muted` (hover warms to gold). Mine: `--gold-soft` / `--gold-ink` / `--gold-200`. Keep the repo's real reaction set; restyle the chips.
- **Hover toolbar** (per post, on row hover or while its menu is open; also `:focus-within`): floating pill, absolute top −13px right 12px, `--surface-raised`, `--border-soft`, shadow-md, 3px padding, 28px round icon buttons:
  `✦` quick-commend (gold-600, hover `--gold-soft`) · `＋` reaction picker (menu of the 3 reactions with ✓ on yours) · `❝` quote into composer · `✓` accept as answer (staff; hidden on OP and current answer; `--success`, hover `--surface-done`) · `···` overflow → Copy link / **own posts:** Edit (inline textarea + Save/Cancel), Delete / **staff:** Make wiki ⇄ Remove wiki flag, "Reveal author — logged" (anonymous posts), "Remove (warden)" in `--rust` / Report (faint). Menus: radius 7px, shadow-lg, 140ms rise.

**Composer dock** (sticky bottom, gradient fade into `--surface-page`): card `--surface-raised`, `--border-strong`, radius 12px, shadow-md. "Posting as {viewer}" strip (24px monogram, label .72rem, hairline underneath) + "Draft saved" mono hint when dirty. Serif textarea (1.02rem, borderless, min 56px). Toolbar: B / I / S | ❝ / `</>` as 26×24 quiet buttons; right: `N / 20000` mono counter + **Reply** primary (accent bg, `--accent-contrast`, Marcellus .76rem ls .06em, radius 7px; disabled at 45% opacity until the draft is non-empty). Guests get the join bar ("You are browsing as a guest — *log in to add your counsel.*" + Log in primary); locked topics show a lock notice bar instead.

### 2. Topic tools drawer (the heart of the direction)

- **Scrim**: `rgba(22,29,36,.42)` + blur(2px), 240ms fade. **Panel**: fixed right, `min(392px, 92vw)`, full height, `--parchment-50`, left hairline, shadow-xl, slide-in 260ms `cubic-bezier(.22,.61,.36,1)`.
- **Header**: 8-pt gold star + "Topic tools" (display serif 1.3rem) + × close. Esc and scrim-click close. Footer note (mono .62rem): "Esc closes. Warden acts are recorded in the ledger."
- **Accordion** (one section open at a time; header rows: Marcellus .8rem title + a live summary in mono .64rem + chevron; hairline separators):
  1. **Your watch** (signed-in): segmented Instant / Daily / Off (active = `--accent` fill, `--accent-contrast` text) + "Quiet until" toggle chips Today / Tomorrow / Next week (active = gold-soft). Footnote: "Watching and snooze are yours alone."
  2. **Standing** (all roles; controls staff-only): current status chip in the header row; staff get 5 status rows with ✓ on current + a "Reason — recorded in the ledger" input (reason attaches to the next change); below, the **Status ledger** — `Solved ← Needs answer / Elrond · Jul 12 at 10:12 / "Accepted Arwen's proposal"` entries, newest first.
  3. **Tags**: current chips with × to remove (staff) + dashed `＋ tag` suggestions from the board's tag set.
  4. **Living brief** (staff): summary text + refreshed stamp; actions **Refresh** (async — on failure toast "Not enough eligible posts for an automatic refresh — 6 weighed, 8 required."), **Edit summary** (inline textarea + Save/Cancel; curation is logged), **Hide from page / Show on page**.
  5. **Wardens' tools** (staff): "Tended by" roster rows (22px monograms + ✓ on assignee; "Elrond — me" self-assign) + Unassign · **Pin** / **Lock** toggle switches (30×18 track — gold-500 for pin, `--rust` for lock; 14px parchment knob) · Clear accepted answer · Close/Reopen poll · Remove/Restore poll · **Split or merge…** (opens modal).

### 3. Split / merge modal

Overlay `rgba(22,29,36,.42)` + blur(3px). Dialog `min(600px, 92vw)`, top 8vh, `--parchment-50`, radius 12px, shadow-xl, padding 22/26/24, 200ms rise. **Split**: scrollable checklist of replies (author · #id + one-line excerpt, 15px accent checkboxes) + "New topic title…" input + primary button "Split N out" (validates selection and title). **Merge**: target input + secondary "Merge topics" + italic note "All posts would move into the chosen topic; a signpost remains here." Both actions confirm via toast; split offers **Undo**.

### 4. Toast

Fixed bottom-center, `--twilight-800` bg, `--parchment-100` Marcellus .78rem, radius 7px, shadow-xl, 180ms rise, auto-dismiss ~3.6s, optional caps `--gold-400` action (e.g. UNDO after delete/remove/split).

## Interactions & Behavior

- Star, watch, snooze: instant toggle + confirming toast. Snooze chips toggle off on re-tap.
- Status change: updates every chip on the page, prepends a ledger entry `{to, from, actor, timestamp, reason?}`, clears the reason field.
- Accept as answer (hover ✓): moves the green plate + gilt ring to that post, sets status → Solved (with a ledger entry if it changed), toast. "Clear accepted answer" reverses.
- Lock: composer swaps to the locked bar, Locked chip appears in the H1. Pin: Pinned chip appears.
- Poll: one vote per member → switches that user to results; totals update; staff close/reopen/remove/restore.
- Quote (❝): prepends `> first 120 chars…` to the composer draft, toast.
- Delete / Remove (warden) / Split: destructive actions show an **Undo** toast (restore at original index).
- Reveal author: replaces the mask with "Lindir (was anonymous)" + "Revealed · logged" chip — an audited, one-way act.
- Reply: appends a post authored by the viewer, clears the draft.
- All popover menus close on outside click and Esc.
- Motion: menus/toasts rise 140–200ms; drawer 260ms; all with `--ease-calm: cubic-bezier(.22,.61,.36,1)`. No infinite animations.

## State Management

Per-viewer: `role` (guest/member/staff), `starred`, `watch` (instant/daily/off), `snooze`, `myPollVote`, `myReactions[postId]`, composer `draft`, `editingPostId`.
Per-topic: `status` + `statusHistory[]`, `assignee`, `tags[]`, `pinned`, `locked`, `acceptedPostId`, poll `{votes, closed, present}`, brief `{text, refreshedAt, shownOnPage}`.
UI: `drawer {open, section}`, open menu ids, hovered post, active toast `{message, action?}`.
In the real app, all mutations are existing POST endpoints (`/t/{id}/star`, `/subscribe`, `/status`, `/snooze`, `/assign`, `/tags`, `/posts/{id}/react|accept|delete|wiki|report`, `/mod/t/{id}/pin|lock|split|merge`, poll routes) — the drawer/toolbar only re-house the forms.

## Role gating

- **Guest**: read-only. Sees status/tags/brief/poll results, participant stack, join bar. No star, tools button, hover toolbar, or reaction toggling.
- **Member**: star, watch, snooze, react, quote, report, reply; edit/delete own posts. Drawer shows Your watch / Standing (read + ledger) / Tags (read).
- **Staff (warden)**: everything — status, assign, tags edit, pin/lock, accept/clear, poll admin, brief curation, wiki flags, reveal-author, remove, split/merge.

## Design Tokens

All from the Imladris system (`public/assets/app.css :root` in the app; `tokens/*.css` in the DS project). Key values:

- Parchment 50/100/200/300: `#FAF6EC` `#F5EFE1` `#ECE4D2` `#DED2B8` · Mist 200 `#DCE3DD`
- Ink 900/700/500/400/300: `#1B231D` `#313B33` `#515C52` `#5C685D` `#94A095`
- Green 900/800/700/500/200/100/050: `#1C2E24` `#24402F` `#2E4A3A` `#4E7459` `#BCD0BF` `#DCE8DD` `#EDF3ED`
- River 900/700/200/100: `#1E3040` `#2C4D63` `#BAD2DF` `#DCE9F0`
- Gold 700/600/500/400/200/100: `#9A7530` `#B08A3A` `#C29A44` `#D2B062` `#EAD9A8` `#F4EBCF` · gold-ink `#7E5F22`
- Status hues: leaf `#4E7459`, amber `#B7842F`, rust `#9C4A33` · Twilight 800/900: `#1E2730` `#161D24`
- Semantics used: `--surface-page/raised/sunken`, `--border-hair/soft/strong`, `--brand-subtle`, `--gold-soft`, `--surface-done/-review/-pending` + `--on-*`, `--rule-gold`, `--star`, `--artifact-link`, `--accent`/`--accent-contrast`, `--gilt` (inset gold ring)
- Radii: 4 / 7 / 12 / 20 / pill · Shadows: `--shadow-xs/sm/md/lg/xl` (warm ink, never pure black)
- Motion: `--ease-calm: cubic-bezier(.22,.61,.36,1)`; 140ms fast / 240ms base
- Type: Cormorant Garamond (display), Marcellus (labels/buttons — tracked caps), EB Garamond (body, 17px base), JetBrains Mono (data). The live app deliberately ships system-serif fallbacks under CSP; keep its stacks.

## Implementation Notes (target codebase)

- `thread.php` header: keep breadcrumb/H1/byline; delete the workflow bar, workflow-actions forms, tags `<details>`, poll builder, and split/merge panel from the header flow — they move into the drawer, **reusing the existing forms verbatim**.
- Drawer: an `<aside>` toggled by CSP-safe listeners in `app.js` (the codebase pattern); sections can be native `<details>` (accordion via a small exclusive-open enhancer). **No-JS fallback**: render the same block statically under the header — today's layout becomes the graceful degradation.
- Hover toolbar: pure CSS in the real app (`.post:hover .post-toolbar, .post:focus-within .post-toolbar { opacity: 1; }`) — no JS needed for reveal; `···` menus can be native `<details class="dm-menu">` (existing popover pattern).
- Toasts: extend the existing flash-message pattern; Undo = a form button in the flash.
- Poll bars: keep `<meter>`; the prototype matches it.

## Mobile treatment (≤768px)

- Header stacks; star + Topic tools right-aligned under the title; participants collapse to "+7 in council".
- **Drawer becomes a bottom sheet**: full width, max-height 86vh, slide-up, grab handle; same sections (matches the app's Phase-4 off-canvas/scrim mobile patterns).
- Hover doesn't exist on touch: show a quiet always-visible row of `✦ ❝ ···` (44px targets) beside each post's reactions instead of the floating toolbar.
- Composer: sticky bottom pill + circular send (per RetroBoards Mobile frames). Split/merge modal goes full-screen sheet. 44px minimum tap targets throughout.

## Assets

- 8-pt elven star (brand + Topic tools glyph): `M50 6 L59 41 L94 50 L59 59 L50 94 L41 59 L6 50 L41 41 Z` (viewBox 0 0 100 100). Full brand star paths are in `imladris-spec.md`.
- Simple stroke icons inline (chevron `M15 18l-6-6 6-6` / `M6 9l6 6 6-6`, check `M20 6L9 17l-5-5`, bell, lock) — stroke-width 2–2.4, round caps.
- Monograms: the DS `Monogram` component / the app's `.monogram .mono-0..9` classes (deterministic tint from username hash; `.monogram-gilt` ring).
- No raster images.

## Files

- `ThreadView.dc.html` — the interactive hi-fi prototype (view it live in the Imladris design-system project at `templates/thread-view/`; it has a working role Tweak: guest/member/staff, plus poll/brief feature-flag Tweaks).
- `thread-data.js` — the sample topic content (posts, poll, statuses, ledger, roster).
- `ds-base.js`, `support.js` — prototype runtime loaders (reference only; not for production).
- Tokens: `tokens/colors.css`, `tokens/typography.css`, `tokens/spacing.css` in the DS project ≙ `public/assets/app.css :root` in the app.
