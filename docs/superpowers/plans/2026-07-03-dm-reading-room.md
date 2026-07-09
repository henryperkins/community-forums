# Direct Messages — "one reading room" (reimagine)

> Archived design record — implementation plan + design spec(s) merged during the dm-reading-room doc consolidation; see the ADR / runbook / PR referenced below for shipped status.

---

# Design spec (what & why)

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

---

# Implementation Plan (how / task breakdown)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reimplement the DM surface as one reading room (list │ conversation │ collapsible rail) with de-boxed grouped letters and tucked-away controls, faithfully in the real PHP + single-`app.css` + single-`app.js` stack.

**Architecture:** Server-rendered PHP templates, progressive enhancement gated on `has-js`, strict CSP (no inline style/script, no CDN/React). Existing `:root` tokens only. All routes/CSRF/feature-gates unchanged except two additive backend reads (read receipt, list search). Design reference: `design_handoff_dm_reimagine/` (`prototype/SOURCE.md` = exact structure/classes/copy; `ui_kits/dm/kit.css` = the target CSS block).

**Tech Stack:** PHP 8.2 templates (`templates/`), vanilla CSS (`public/assets/app.css`), vanilla JS PE (`public/assets/app.js`), PHPUnit (integration via in-process kernel), Playwright browser evidence.

## Global Constraints

- **CSP:** no inline `<style>`/`style="…"`, no inline `<script>`/`onclick`, no CDN, no React, no build step. CSS → `app.css`; JS → `app.js`.
- **PE:** every interaction works with JS off first; enhance under `document.documentElement.classList.contains('has-js')`.
- **No new colors.** Reuse `:root` tokens. `--dur-1/--dur-2` are undefined → use literal `140ms`/`240ms` with `var(--ease-calm)`.
- **No emoji.** Line icons = Lucide inline SVG via `partials/icon.php`. Brand marks = existing ✦ + eight-point-star partials.
- **Keep every `$this->csrfField()`.** Keep the `group_dms` gate around all group-only UI.
- **Preserve test-pinned markup:** `templates/dm/index.php` must keep the filter pill exactly `class="pill is-active" href="/messages?filter=unread" aria-current="page"` (asserted by `AppDirectMessageTest::test_messages_index_unread_filter_...`). Message bodies must still render as text (SENTINEL assertions). The reply form must keep `<textarea name="body">` + echoed `$body` + error text (422 draft-loss test).
- **Test DB:** this worktree uses `retroboards_test_dm` (isolated from Codex).

---

## Phase 1 — CSS + template de-box (NO JS, NO backend) ← review checkpoint

**Scope boundary:** de-box the *message stream* and the *list rows* only. The live-conversation-list-in-`show.php`, the 3-pane shell, and the details rail move to Phase 2 (they need the controller to pass `conversations` to `dm/show`, which is a backend change). Phase 1 stays purely template + CSS so it ships the biggest "less everywhere" win with zero risk.

### Task 1.1: De-box the conversation list rows (`templates/dm/index.php`)

**Files:**
- Modify: `templates/dm/index.php`
- Regression test: `tests/Integration/Core/AppDirectMessageTest.php` (existing; must stay green)

**Changes:**
- Header "New message": change `<a class="btn btn-small" href="/messages/new">New message</a>` → a round icon button `<a class="dm-new-btn" href="/messages/new" aria-label="New message" title="New message"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></a>`. (No-JS link now; dialog enhancement is Phase 3.) — *depends on Task 1.0 icon partial; if deferring icons to Phase 2, use a literal inline `+` SVG in `index.php` is NOT allowed inline… use the icon partial. So Task 1.0 (icon partial, minimal set `plus`) is a prerequisite here.*
- Keep the `.dm-listpane-filters` All/Unread pills **verbatim** (test-pinned).
- Row: **remove** the `.dm-group-meta` participant-name `<span>` (handoff §1). Move the unread marker from the top-row inline `.unread-dot` to a single trailing dot: drop the `<span class="unread-dot">` in `.dm-row-top`, and after `.dm-preview` add `<?php if (!empty($c['is_unread'])): ?><span class="dm-unread-dot" aria-label="Unread"></span><?php endif; ?>`.
- Keep grid cells: monogram, `.dm-row-top>.dm-other`, `.dm-time`, `.dm-preview`. Preview stays one line (CSS clamp).

- [ ] **Step 1 — Baseline:** `vendor/bin/phpunit tests/Integration/Core/AppDirectMessageTest.php` → expect PASS (record count).
- [ ] **Step 2 — Edit `index.php`** per above.
- [ ] **Step 3 — Regression:** re-run the file → expect PASS, same count. Confirm the filter-pill test still passes (grep the rendered assertion).
- [ ] **Step 4 — Commit** `feat(dm): de-box conversation list rows (round +, trailing unread dot, drop group-meta)`.

### Task 1.0: Minimal icon partial (prerequisite for the `+`)

**Files:** Create `templates/partials/icon.php`

**Interface (Produces):** `$this->partial('partials/icon', ['name' => 'plus'])` → inline `<svg class="icon icon-plus" …>`. A `$name → path` map; unknown name renders nothing. Stroke 1.8, round caps, `viewBox="0 0 24 24"`, `width/height=16`, `fill=none stroke=currentColor`, `aria-hidden="true"`. Seed only the icons Phase 1 needs (`plus`); extend the map in Phase 2 with the full Lucide set (paths in `prototype/SOURCE.md` → `Overlays.jsx`).

- [ ] **Step 1:** Create `partials/icon.php` with the `plus` path `M12 5v14M5 12h14`.
- [ ] **Step 2:** Render check — `vendor/bin/phpunit tests/Integration/Core/AppDirectMessageTest.php` (index renders the icon without error).
- [ ] **Step 3:** Commit `feat(dm): add Lucide inline-SVG icon partial (seed: plus)`.

*(Execution note: do Task 1.0 before 1.1.)*

### Task 1.2: Group the message stream into "letters" (`templates/dm/show.php`)

**Files:**
- Modify: `templates/dm/show.php` (the `.dm-scroll` message loop, ~L108-156)
- Regression test: `AppDirectMessageTest` (SENTINEL visibility, 422 draft echo)

**Changes:** Replace the per-message loop with a **grouped-run** render. Group consecutive `$messages` by `user_id`:

```php
<?php
// Group consecutive messages by author into "letters".
$groups = [];
$run = null;
foreach ($messages as $m) {
    if ($run !== null && (int) $run['user_id'] === (int) $m['user_id']) {
        $run['items'][] = $m;
    } else {
        $run = ['user_id' => (int) $m['user_id'], 'items' => [$m]];
        $groups[] = &$run;
        unset($run); $run = &$groups[count($groups) - 1];
    }
}
unset($run);
?>
<?php foreach ($groups as $g): ?>
    <?php
    $first = $g['items'][0];
    $mine = $current_user !== null && (int) $first['user_id'] === $current_user->id();
    $authorName = ($first['author_display_name'] ?? '') !== '' ? $first['author_display_name'] : $first['author_username'];
    ?>
    <div class="dm-group<?= $mine ? ' mine' : '' ?>">
        <?php if (!$mine): ?>
            <span class="dm-mono-col"><?= $this->partial('partials/monogram', ['name' => $authorName, 'username' => $first['author_username']]) ?></span>
        <?php endif; ?>
        <div class="dm-msgs">
            <div class="dm-ghead">
                <span class="dm-name"><?= $mine ? 'You' : $e($authorName) ?></span>
                <span class="dm-gtime"><?= $e(human_datetime($first['created_at'])) ?></span>
            </div>
            <?php foreach ($g['items'] as $m): ?>
                <div class="dm-line" id="m<?= (int) $m['id'] ?>">
                    <div class="dm-body">
                        <?php if (($m['body_html'] ?? '') !== ''): ?><?= $m['body_html'] /* sanitised at write time */ ?><?php else: ?><p><?= $e($m['body']) ?></p><?php endif; ?>
                    </div>
                    <?php if (!$mine): ?>
                        <span class="dm-line-menu">
                            <details class="dm-report">
                                <summary class="dm-dotbtn" aria-label="Message actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                                <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="dm-report-form">
                                    <?= $this->csrfField() ?>
                                    <select name="reason_code" class="input input-small"><?php foreach ($reasons as $rc): ?><option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option><?php endforeach; ?></select>
                                    <input type="text" name="reason" class="input input-small" placeholder="Details (optional)" maxlength="255">
                                    <button class="btn btn-small danger" type="submit">Report</button>
                                </form>
                            </details>
                        </span>
                    <?php endif; ?>
                </div>
                <?php $cards = ($reference_cards ?? [])[(int) $m['id']] ?? []; ?>
                <?php if (!empty($cards)): ?>
                    <div class="reference-cards" aria-label="Referenced content">
                        <?php foreach ($cards as $card): ?>
                            <a class="reference-card" href="<?= $e($card['url']) ?>"><span class="ref-type"><?= $e($card['type']) ?></span><strong><?= $e($card['title']) ?></strong><?php if (($card['meta'] ?? '') !== ''): ?><span class="ref-meta"><?= $e($card['meta']) ?></span><?php endif; ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
```

Keep the day divider (`<div class="dm-day">Beginning of your counsel</div>`) at the top of `.dm-scroll-inner`, the pagination partial, and the composer form unchanged (composer restyle is CSS only). Wrap the groups in `<div class="dm-scroll-inner">` for the max-width reading column. **Add `more-horizontal` to the icon partial map** (path `<circle cx=5 …/>` triple-dot — actually the filled-dots form from `Overlays.jsx` `More`).

- [ ] **Step 1 — Baseline:** run `AppDirectMessageTest` → PASS.
- [ ] **Step 2 — Add `more-horizontal` to `partials/icon.php`.**
- [ ] **Step 3 — Edit `show.php`** message loop → grouped runs (code above).
- [ ] **Step 4 — Regression:** run `AppDirectMessageTest` → PASS (SENTINEL-0060 visible, 422 echo intact). Fix if red.
- [ ] **Step 5 — Commit** `feat(dm): group consecutive messages into de-boxed letters`.

### Task 1.3: Reading-room CSS for rows + letters (`public/assets/app.css`)

**Files:** Modify `public/assets/app.css` — the `.dm-*` block (~L2551-2990).

**Changes:** Port the message/row rules from `ui_kits/dm/kit.css`, keeping the existing 2-column `.dm-shell` for now (3-col shell + rail = Phase 2). Specifically add/replace:
- `.dm-new-btn` (round gold-outline button).
- `.dm-row` grid + `.dm-unread-dot` (trailing gold dot) + `.dm-preview { -webkit-line-clamp: 1 }`; drop `.dm-group-meta`.
- `.dm-day` (hairline + small-caps label, no pill).
- `.dm-scroll-inner` (max-width 720px reading column).
- `.dm-group`, `.dm-group.mine`, `.dm-mono-col`, `.dm-ghead`, `.dm-name`, `.dm-gtime`, `.dm-msgs`, `.dm-line`, `.dm-body`; mine → `--gold-soft` bg + `--gold-200` border + `--radius-lg`; theirs → plain `padding: 1px 0`.
- `.dm-line-menu` hover/focus-within reveal (`opacity:0` → `1`); `.dm-dotbtn`.
- `.reference-cards`/`.reference-card` lighten (hairline + `--accent-2` left rule, smaller type).
- Composer pill restyle: `.dm-composer`, `.dm-composer-field`, textarea, `.dm-send`.
- Remove now-dead rules (`.dm-message`, `.dm-bubble`, `.dm-message-head`, `.dm-mine .dm-bubble`, `.dm-group-meta`) — but keep `.dm-mine` semantics via `.dm-group.mine`.
- Token substitutions: `var(--dur-1, 140ms)` stays valid (fallback applies); or replace with `140ms`. No new colors.

Motion: wrap non-essential transitions already carry `var(--ease-calm)`; add a global `@media (prefers-reduced-motion: reduce) { .dm-* transitions/animations: none }` guard near the block.

- [ ] **Step 1 — Edit `app.css`** `.dm-*` block per above (source: `kit.css`).
- [ ] **Step 2 — CSP/lint guard:** `grep -nE "style=|<script|onclick" templates/dm/*.php templates/partials/icon.php` → expect no matches; `grep -c "@import\|http" public/assets/app.css` for the block → no new CDN refs.
- [ ] **Step 3 — Full DM regression:** `vendor/bin/phpunit tests/Integration/Core/AppDirectMessageTest.php` → PASS.
- [ ] **Step 4 — Commit** `style(dm): reading-room CSS for de-boxed rows and grouped letters`.

### Task 1.4: Phase 1 evidence + checkpoint

- [ ] **Step 1 — Full suite:** `composer test` → green (or explain any pre-existing reds vs main).
- [ ] **Step 2 — Browser evidence:** drive the DM surface (seeded conversation) with a browser — capture list (de-boxed rows + gold dot), conversation (grouped letters: theirs plain, mine gold plate, lighter cards + day divider), and a **JS-disabled** pass proving the stream + report `<details>` + composer still work. Save PNGs to `docs/evidence/dm-reimagine/phase1/`.
- [ ] **Step 3 — Adversarial verify** (workflow): CSP (no inline), PE (no-JS), a11y (focusable report control, labels), fidelity vs `SOURCE.md`, and DM regression. Fix confirmed findings.
- [ ] **Step 4 — STOP.** Present the diff + evidence for review before Phase 2.

---

## Phase 2 — details rail + header ··· menu (outline)

- Controller: pass `conversations => listForUser($user->id())` to `dm/show` so the **live list** is the left column of the shared 3-pane shell (replaces `.dm-return-pane`). Additive read, no new query beyond the existing method.
- `partials/icon.php`: extend to the full Lucide set (`search, more-horizontal, panel-right, users, bell-off, user, edit-3, user-plus, log-out, ban, flag, copy, x, check, arrow-up`).
- New `partials/dm_rail.php`: direct (identity/tier/facts + mute + block/report) and group (members/roles + owner tools + mute + leave, inside `group_dms` gate). Relocate the inline `.dm-group-panel` + standing Mute/Leave out of `show.php` into the rail/menu.
- `show.php` header → one row: monogram(gilt) + title + sub-line + details-toggle + one `···` overflow whose items submit the existing POST forms.
- `app.css`: 3-column `.dm-shell.has-rail`, `.dm-inforail`, `.dm-menu-*` popover, `.dm-thread-head` one-row; responsive drawer ≤1180.
- `app.js`: `···` menu (extend the `composer-details`/document-click pattern) + rail toggle (localStorage, default open ≥1181 / closed ≤1180).
- Evidence: composer test + browser (rail open/closed, menu, group behind gate) + no-JS (menu = open `<details>`, rail = server `<aside>`).

## Phase 3 — compose dialog + list search (outline)

- `dm/new.php`: same form; `.has-js` opens it as a `details` dialog from the list `+`. `dm/index.php`: add the GET `q` search input (pill).
- Controller `index()`: read `?q=`; `ConversationRepository::listForUser($userId, ?string $q = null)` gains a `LIKE` over last body / other display-name. **TDD** the repo `q` filter (new integration test: seed 2 convos, assert `q` narrows).
- `app.js`: client instant-filter of the rendered list; compose-dialog open/close (mirror `composer-details`).
- Evidence: composer test (incl. new `q` test) + browser + no-JS (search = GET form, compose = `/messages/new`).

## Phase 4 — read receipts + mobile overlay + polish (outline)

- `ConversationRepository::otherLastReadMessageId($conversationId, $userId): ?int`; controller passes `other_last_read_message_id`; `show.php` renders Read/Delivered/Sent under my last run, direct only. **TDD** the repo method + a controller test (other reads → "Read").
- `app.js`: mobile rail overlay (`nav-open` pattern) + Enter-to-send (Shift+Enter newline) on the DM composer.
- `app.css`: `.dm-toast` (restyle flash pill), single-pane ≤900 with back control, motion polish.
- Evidence: composer test (incl. receipt test) + browser across breakpoints + no-JS.

## Phase 5 — consolidation pass (outline; sub-design first)

- Present a focused sub-design applying the 5 principles to inbox/thread/profile chrome. Each surface = its own reviewable commit. No blanket refactor. Only after 1-4 land + are approved.

---

# Self-review (review notes)

- **Spec coverage:** every spec file (index/show/new/dm_rail/icon/app.css/app.js/controller+repo) has a task across Phases 1-4; the two additive reads are TDD'd in Phases 3-4; Phase 5 is explicitly deferred to a sub-design. ✓
- **Placeholders:** Phase 1 tasks carry concrete code/commands; Phases 2-5 are intentionally outlines (detailed when reached, per the checkpoint cadence the user chose). ✓
- **Type/name consistency:** `.dm-group(.mine)`, `.dm-unread-dot`, `.dm-line`, `.dm-body`, `partials/icon` name-map, `otherLastReadMessageId`, `listForUser($userId, ?$q)` used consistently. ✓
