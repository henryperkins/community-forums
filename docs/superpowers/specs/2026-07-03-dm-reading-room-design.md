# Direct Messages — "one reading room" (reimagine) · design

**Date:** 2026-07-03
**Branch:** `worktree-dm-reimagine` (isolated worktree, based on `origin/main` @ 6a8d357)
**Source of truth for look & behavior:** `design_handoff_dm_reimagine/` (engineering handoff + `prototype/dm-reimagined.standalone.html` + `prototype/SOURCE.md`). The handoff is a **design reference**, not code to copy. The Imladris design-system project is `ui_kits/dm/*` (React/JSX prototype).

## Problem

The current DM surface (`templates/dm/{index,new,show}.php`) reads as *"very everywhere and confusing"*: several near-identical screens that all lead to the same place, every secondary control (mute · leave · report · members) standing visible at once, and every element boxed in its own bordered card.

## Goal

Reimplement DMs as **one reading room** — a conversation **list**, the open **conversation**, and a **collapsible details rail** — with secondary controls tucked into menus and the rail, and messages de-boxed into grouped "letters." Keep the **full ceremonial Imladris register** unchanged (serif type, parchment + evergreen + a single mallorn gold, gilt monograms, the eight-point star); change **only information architecture and density**.

This is **high-fidelity**: colors/type/spacing/radii/motion are final and come from tokens already in `public/assets/app.css :root`. Recreate faithfully with the app's existing tokens and partials.

## Hard constraints (non-negotiable)

- **Strict CSP** (`src/Security/SecurityHeaders.php`): `script-src 'self'; style-src 'self'` — **no** inline `<style>`/`style="…"`, **no** inline `<script>`/`onclick`, no `unsafe-inline`, no CDN, no React, no build step. All CSS in `public/assets/app.css`; all JS in the single `public/assets/app.js`.
- **Progressive enhancement.** Every interaction works with **no JavaScript** (server-rendered forms + links), then is *enhanced* when `document.documentElement.classList.contains('has-js')`.
- **Routes, controllers, DB, CSRF, feature-gates unchanged**, except two *additive reads* (below). Keep every `$this->csrfField()`. Keep the `group_dms` feature gate (deploy-dark) around all group-only UI.
- **No new colors.** Reuse `:root` tokens. No emoji; line icons are **Lucide inline SVG** via a new `partials/icon.php`; brand marks are the existing ✦ commend + eight-point-star partials.

## Repo-grounded facts (verified)

- **Tokens** used by the prototype's `kit.css` all exist in `app.css :root` (surfaces, brand, the single gold `--accent-2` = `--gold-500`, `--gold-soft/200/ink`, ink scale, `--font-display/label/body/mono`, radii `sm/md/lg/pill`, shadows `xs…xl`, `--ease-calm`, `--presence`, `--topbar-h`, `--maxw`). **Only `--dur-1/--dur-2` are undefined** → use literal `140ms`/`240ms` with `var(--ease-calm)` (the app's existing motion convention: 140/240/420ms).
- **PE hooks to reuse verbatim** (`public/assets/app.js`):
  - `has-js` set on `<html>` (line 8).
  - `.composer-input` autosize (lines 16–21) — already matches the DM composer's textarea class.
  - **`nav-open`** off-canvas drawer: body-class toggle + scrim + Escape (lines 255–278) → **mobile details-rail overlay + rail toggle**.
  - **`details.composer-details`** native-`<details>` modal: full-viewport `::before` backdrop + Escape/scrim-click/Cancel (lines 284–308) → **new-message dialog** and the **··· menus**.
- **Backend data shape** already delivered to `dm/show` covers everything except the read receipt (`conversation`, `is_group`, `is_owner`, `can_reply`, `participants`, `events`, `messages` [`id`, `user_id`, `author_display_name/username`, `created_at`, `body`, `body_html`], `reference_cards` keyed by message id, `other`, `page`, `pages`, `reasons`).
- `ConversationRepository::markRead()` writes `conversation_participants.last_read_message_id` (migration `0025`); `otherParticipant()` returns the other user id. `listForUser($userId)` returns `is_unread`, `last_message_at`, `last_body`, `other_display_name/username`, `kind`, `title`, `participant_names`.
- `partials/icon.php` does **not** exist yet. `partials/monogram.php` exists (`name`, `username`, `gilt`; add optional `presence`).

## Two additive backend reads (only backend change)

1. **Read receipt (direct only).** Expose the *other* participant's `last_read_message_id` for a direct conversation so `show.php` renders **Read** (other's last_read ≥ my last message id), else **Delivered** (**Sent** for an optimistic just-posted reply). Add e.g. `ConversationRepository::otherLastReadMessageId($conversationId, $userId): ?int`; pass `other_last_read_message_id` from `renderConversation()`. Groups: skip receipts.
2. **List search (optional `q`).** `ConversationRepository::listForUser($userId, ?string $q = null)` gains a `LIKE` over last body / other display-name; `ConversationController::index()` reads `?q=`. Purely additive.

No new routes, no new tables, no migration.

## Architecture — one `.dm-shell`, three panes

`index.php` and `show.php` render the **same shell**: the list is always the left column. `index.php` = shell with an empty right pane (empty state); `show.php` = shell with the conversation filled + the rail. Layout:

- **≥1181px:** `list │ conversation │ details <aside>` (3 columns; rail collapsible, default open, state persisted in `localStorage` when `.has-js`).
- **≤1180px:** rail becomes a **right-edge drawer** over the conversation (rail default closed).
- **≤900px:** single pane — list ↔ conversation with a back control; rail as a full-screen overlay (the `nav-open` pattern).

**Grouped letters.** Consecutive messages by the same `user_id` group into one run with a single author line + time. **Theirs:** plain body on parchment, small monogram at the run start. **Mine:** the one gold plate (`--gold-soft` bg, `--gold-200` border), right-aligned (`.dm-mine`). Preserve `id="m{id}"`, sanitized `body_html`, and the reference-cards block (lightened: hairline + gold left-rule, smaller). Day divider is a hairline + small-caps label (no pill).

**Tucked-away controls.** One `···` overflow in the conversation header (menu items just submit the existing POST forms — mute/leave/rename/transfer — or open the rail). One `···` revealed on message hover/focus → Copy (enhancement-only `navigator.clipboard`) + Report (the existing `<select reason_code>` + details + POST `/dm/{id}/report`). Nothing secondary visible at rest.

**Details rail** (`partials/dm_rail.php`, server-rendered `<aside class="dm-inforail">`):
- **Direct:** gilt xl monogram + name + `@handle` + tier pill + facts (Presence, Joined) + Mute (POST `/messages/{id}/mute`) + Block / Report conversation (danger).
- **Group** (inside `group_dms` gate): members list (roles, `left` state) + owner tools (add / rename / remove / transfer — the existing POST forms, relocated) + Mute + Leave (POST `/members/remove` with the current user).
- Pure relocation + restyle of markup already in `show.php`; no new endpoints.

**New-message dialog.** `/messages/new` stays the **no-JS route** with today's fields (`to`, optional `title` when `group_dms`, `body`, POST `/messages`). When `.has-js`, the list `+` opens **the same form** as a `details`/dialog modal. One markup, two presentations — not a second form.

**Toast.** Reuse the existing flash channel (`redirectWithFlash`) for "Group renamed.", "Reported…", etc.; restyle the flash as the small centered pill (`.dm-toast`).

**Motion & a11y.** `--ease-calm` at 140/240/420ms; wrap non-essential transitions in `@media (prefers-reduced-motion: no-preference)`. Every hover-revealed control is reachable by keyboard focus. 44px tap targets. Status is word + colour, never colour alone.

## Files (7)

- `templates/dm/index.php` — de-boxed one-line rows + gold unread dot, `+` button (link no-JS / dialog `.has-js`), `q` search, shared shell.
- `templates/dm/show.php` — 3-pane shell, one header + `···` menu, grouped letters, per-message `···`, receipt, pill composer.
- `templates/dm/new.php` — same form, opened as a dialog when `.has-js`.
- `templates/partials/dm_rail.php` — **new** details rail (direct + group).
- `templates/partials/icon.php` — **new** Lucide inline-SVG helper: `plus, search, more-horizontal, panel-right, users, bell-off, user, edit-3, user-plus, log-out, ban, flag, copy, x, check, arrow-up`. Stroke ~1.8, round caps (paths from `prototype/SOURCE.md`).
- `public/assets/app.css` — replace/extend the `.dm-*` block (~L2552–2990) + responsive (~L4067) with the reading-room rules (drawer ≤1180, single-pane ≤900, `.dm-toast`).
- `public/assets/app.js` — enhancement for the `···` menus, compose dialog, rail toggle + mobile overlay, composer Enter-to-send (extend the existing `has-js` / `composer-details` / `nav-open` code).
- `src/Controller/ConversationController.php` (+ `src/Repository/ConversationRepository.php`) — the two additive reads.

## Phasing (each phase = its own commit + evidence)

1. **CSS + template de-box** (no JS, no backend): grouped letters, theirs plain / mine one gold plate, one-line rows, lighter reference cards + day divider, reading-room shell. Biggest "less everywhere" win. **← review checkpoint: show the diff + browser evidence before continuing.**
2. **Details rail** partial + header `···` menu; move members / owner tools / mute / leave / block / report into it. New `icon.php`.
3. **Compose dialog** + **list search** (PE over the existing routes; additive `q`).
4. **Read receipts** (additive read) + mobile rail overlay + Enter-to-send + `.dm-toast` + motion polish.
5. **Consolidation pass:** apply the five principles (below) to the inbox/thread/profile chrome. **Scope caveat:** touches surfaces beyond DMs — a focused sub-design is presented at the start of Phase 5 and each surface lands as a separate reviewable commit. No blanket refactor.

## Consolidation principles (Phase 5, and the north star throughout)

1. One canonical surface per task; secondary routes become no-JS fallbacks that open in place under `.has-js`.
2. Reveal controls on demand (header `···` + row/message-hover `···`); destructive actions behind the menu/rail, styled `danger`.
3. A right-hand details rail (identity → facts → quiet actions → danger), collapsible; a column at wide widths, an overlay on mobile.
4. De-box: grouped runs + hairline dividers over nested cards; one accent plate for "mine/primary"; one decorative move per surface.
5. Status = word + colour (never colour alone, never emoji); reuse `:root` tokens; motion `--ease-calm` 140/240/420ms, `prefers-reduced-motion` respected.

## Evidence (per phase, per DESIGN §13)

- `composer test` green (unit + integration; TDD the additive backend reads before wiring templates).
- Playwright/browser capture of the DM surface (list, conversation, rail open/closed, compose dialog, per-message menu, no-JS fallback) into `docs/evidence/`. "Inert schema is not evidence" — behavior must be exercised.
- No-JS proof: each interaction demonstrated with scripting disabled.

## Isolation / concurrency

Codex is running concurrently in the main checkout (passkeys branch). This work is isolated in the `worktree-dm-reimagine` git worktree with its own test DB (`retroboards_test_dm`) so PHPUnit's drop+re-migrate never touches Codex's `retroboards_test`. No shared-tree branch switch.

## Non-goals

- No WebSockets / live updates (DECISIONS: short-polling only).
- No change to DM eligibility/blocks/throttles, message sanitization, pagination, or the `group_dms` deploy-dark posture.
- No new colors, fonts, routes, or tables.
