# Composer — Make Enter-to-send Discoverable

**Date:** 2026-07-19
**Status:** Design approved in session (Henry)
**Owner:** RetroBoards core theme
**Related:** `docs/superpowers/specs/2026-07-13-composer-slackify-design.md` ("The Writing Desk", which shipped the Enter-to-send default and the shared `composer_shell.php`)

## Context

The shared composer sends on **Enter** by default and inserts a newline on **Shift+Enter** — the Slack-like default recommended by `DECISIONS.md §6 #2` and shipped by the 2026-07-13 "Writing Desk" work. The behaviour is deliberate and already thoughtful: it is context-aware (a bare Enter does *not* submit mid-list, mid-quote, inside a code fence, or inside open inline code — `composer.js:2303` `textareaEnterShouldSubmit`), it is disabled on coarse pointers (`composer.js:2357` `coarsePointer()`, gated at `:2403`), `Ctrl`/`Cmd`+`Enter` always sends (`composer.js:2398`), and `Shift`/`Alt`+`Enter` always inserts a newline (`:2397`).

**The gap:** none of this is visible at the point of use. Nothing at the composer tells a first-time author that Enter will send. The only place the rule is written down is buried in `/settings/composing`. A user typing a multi-paragraph reply who presses Enter to start the next paragraph will publish a half-finished post instead — a genuine foot-gun for a *long-form* surface, even though the mechanic itself is correct.

This design **keeps** the Slack-like default (no behaviour change, no spec reversal — stays aligned with `DECISIONS.md §6 #2` and the "Community Inbox" product identity in §1) and closes the discoverability gap with a small, server-rendered, preference-aware cue.

### Authority / precedence

Unchanged: `DECISIONS.md` > `PRODUCT_DESIGN.md` > `SCHEMA.md` > `COMPOSER.md`/`USER.md`, and the security invariants outrank this document. In particular:
- **Strict CSP holds** — no inline `<script>`/`<style>`. The cue is server-rendered HTML styled by the external `app.css`. **No JavaScript is required.**
- The canonical Markdown `<textarea>` remains the submit source and no-JS fallback; nothing here touches the submit path.
- No schema, no migration, no feature flag, no change to `enter_to_send`'s default (`src/Support/PreferenceSchema.php:56` stays `true`).

## Goal

A signed-in author can, without leaving the composer, **see** how Enter behaves for them and **change** it — turning an invisible mechanic into a self-documenting, self-service one.

## Non-goals

- Changing the send behaviour or the `enter_to_send` default (that was the rejected "Choice 2"; it would reverse `DECISIONS.md §6 #2` and recent deliberate work).
- A live, context-aware cue that updates per-keystroke as you move in and out of lists/quotes (rejected "Approach B" — marginal gain, needs JS). May be a future enhancement; explicitly out of scope here.
- Any change to the guest experience (guests never see the composer).

## Design

### 1. A preference-aware cue in the composer footer

Add one element to the existing **`.composer-meta-row`** of `templates/partials/composer_shell.php` (lines 100–104) — the row that already carries the draft indicator and character counter. That row is hidden while a reply composer is collapsed and revealed on focus/expand (`app.css:1843`), so the cue appears exactly when the author starts writing, not as ambient chrome. The new-thread, DM, and edit mounts show the meta row normally.

The cue text is **read from the shared `$composing` global** (populated at `src/Core/App.php:550` from `PreferenceService::composing()`, `src/Service/PreferenceService.php:105`), guarded with the same fallback the layout uses (`layout.php:5`) so a DB-less/pre-migration render is safe:

```php
$shellComposing = is_array($composing ?? null) ? $composing : ['enter_to_send' => true];
$shellEnterToSend = !empty($shellComposing['enter_to_send']);
```

Two states, with each key wrapped in a `<kbd>` element, mirroring the existing copy at `templates/account/composing.php:23` (no decorative glyphs — the key words themselves are the affordance):

- **Enter-to-send ON (default):** <kbd>Enter</kbd> to send · <kbd>Shift</kbd>+<kbd>Enter</kbd> for a new line
- **Enter-to-send OFF (user opted out):** <kbd>Ctrl</kbd>/<kbd>⌘</kbd>+<kbd>Enter</kbd> to send

The copy is static (no user data); the `·` separator sits between the two `<kbd>` groups as plain text.

### 2. The cue is a subtle link to the setting

The cue element is an anchor to `/settings/composing`, so the same affordance that explains the rule also lets the author flip it:

```html
<a class="composer-hint" href="/settings/composing"
   title="Change in composing settings">…kbd copy…</a>
```

- Styled muted/small to match the meta row (`color: var(--text-muted); font-size: .78rem`), with the link affordance shown on hover/focus (underline) rather than a loud always-on link colour — "subtle link", per the approved decision.
- Draft loss is not a concern: local + server draft autosave already persist the in-progress body, so navigating to settings and back is safe.
- The `title` (and an `aria-label` carrying the same "…— change in composing settings" clarification) makes the link's destination explicit for pointer and screen-reader users, since the visible text describes behaviour rather than a destination.

### 3. Touch devices

Enter-to-send is inert on coarse pointers, so the keyboard cue would mislead there. Hide it with CSS only — no JS, consistent with `composer.js`'s own `coarsePointer()` bail:

```css
@media (pointer: coarse) { .composer-hint { display: none; } }
```

On touch the author simply taps Send, which sits in the same box.

### 4. Reinforcement + accessibility

- **Send button** (`composer_shell.php:94`, the `✒` button): add a preference-aware `title` — `Send (Enter)` when ON, `Send (Ctrl/⌘+Enter)` when OFF — a second, hover-level cue. (`aria-label` stays the submit label.)
- **Screen readers:** extend the textarea's `aria-describedby` (`composer_shell.php:67`) to reference the cue's id, merged with the existing conditional error id, so focusing the field announces the send rule. A stable id `composer-hint-{instance}` is derived from `$shellInstance`, matching the existing `composer-*-{instance}` id scheme.

### 5. Placement / CSS

- New `.composer-hint` rule in `public/assets/app.css` near the `.composer-meta-row` block (≈ lines 1108–1124).
- Desktop: cue sits at the **start** of the meta row; the character counter stays right-aligned. The grid (`grid-template-columns: auto minmax(0,1fr) auto`, `app.css:1110`) gains the hint as a leading `auto` cell (→ `auto auto minmax(0,1fr) auto`) or the hint spans and wraps — whichever keeps the counter's position unchanged.
- Narrow widths (existing breakpoint `app.css:1290`): the cue wraps to its own line (or is permitted to hide if space-constrained) without pushing the counter off-row.
- The cue must **truncate/wrap gracefully** and never cause horizontal overflow of the composer card.

## Scope

### In scope

- `templates/partials/composer_shell.php` — the cue element, the `aria-describedby` merge, and the send-button `title` (one shared template → all reply/new-thread/DM/edit mounts).
- `public/assets/app.css` — `.composer-hint` styling + coarse-pointer media query.

### Out of scope / untouched

- `composer.js` (behaviour is already correct; no JS added).
- `PreferenceSchema`, `PreferenceService`, migrations, feature flags, `DECISIONS.md`/`COMPOSER.md`/`USER.md` (no behaviour or default change).
- The `/settings/composing` page itself (already documents the rule; it is the link target).

## Testing & evidence (PRODUCT_DESIGN §13)

Behaviour-visible UI change ⇒ PHPUnit **and** browser evidence.

- **Integration (PHPUnit), via the in-process kernel** — new cases in `tests/Integration/Core/AppUserPreferencesTest.php` (which already asserts `data-enter-to-send`, giving precedent and the pref-toggle harness):
  - Signed-in user, default prefs → a thread page's composer contains the `.composer-hint` element, the "Enter to send" copy, and `href="/settings/composing"`.
  - Same user with `enter_to_send` saved **off** → the cue shows the "Ctrl/⌘+Enter to send" copy instead.
  - Assert observable HTTP output (rendered markup), not row counts (per the transaction-rollback test isolation rule).
- **Browser (Playwright)** — extend `tests/browser/composer-shell.spec.ts`: focus the reply composer, assert the cue is visible with the expected text and link target; capture a screenshot into `docs/evidence/` for the phase ledger.

## Risks / edge cases

- **Meta-row layout regression.** Adding a cell risks shifting the counter or overflowing on narrow screens. Mitigation: counter stays right-aligned; cue wraps/hides at the narrow breakpoint; verify against the existing collapsed/expanded reply-composer rules (`app.css:1839–1854`).
- **Pref/pointer mismatch.** The cue's *text* is server-derived from the pref; whether Enter *actually* sends also depends on client pointer type. The coarse-pointer CSS hide keeps the two consistent (touch → no cue; fine pointer → cue matches the pref).
- **Accessibility of a descriptive link.** A link whose visible text describes behaviour rather than destination is clarified by `title`/`aria-label`, keeping the destination explicit.
- **DB-less/pre-migration render.** The `$composing` guard defaults to `enter_to_send => true`, so the shell renders the default cue without touching the DB (consistent with `shareViewGlobals` tolerating missing tables).

## Resolved decisions

- Keep the Slack-like Enter-to-send default (Choice 1); do **not** flip it (Choice 2 rejected).
- Static, preference-aware, server-rendered cue (Approach A); not live/context-aware (B), not tooltip-only (C).
- The cue **is** a subtle link to `/settings/composing` (not plain text).
