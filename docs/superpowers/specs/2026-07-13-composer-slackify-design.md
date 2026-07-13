# Composer Shell — “The Writing Desk”

**Date:** 2026-07-13
**Status:** Approved (scope, toolbar language, and approach confirmed by Henry in session)
**Owner:** RetroBoards core theme

## Context

RetroBoards presents durable forum topics through a Slack/email-style three-pane shell, and the Study thread view (spec `2026-07-12-thread-view-study-design.md`) has just rebuilt the reading surface around that idea. The composer has not caught up. A full review of `COMPOSER.md`, the four mounts, `public/assets/composer.js`, `app.css`, the evidence captures, and the 2026-07-11 frontend tech-debt audit found that the composer’s *engine* is already strong — local + server drafts with conflict resolution, `@`/`#` reference pickers with correct APG combobox semantics, slash inserts and GIPHY, paste/drag image upload with alt text, Milkdown WYSIWYG default-ON — but its *presentation* is a stack of seven loose rows (identity strip, toolbar, textarea, anonymity checkbox, submit button, always-on counter, discard button) rather than one contained input, and three of the most Slack-defining mechanics are specified but unshipped:

1. **No keyboard send exists by default.** DECISIONS.md §6 #2 locks “**Enter-to-send** (Slack-like default)”, but the shipped default is `enter_to_send => false` (`src/Core/App.php:543`, `src/Support/PreferenceSchema.php:56`, `templates/layout.php:5`), and `composer.js` treats any modifier+Enter as newline, so `Ctrl/Cmd+Enter` does not send either.
2. **Emoji is a stub.** The toolbar “Emoji” button inserts the literal text `:smile:` (`composer.js` `ACTIONS.emoji`); there is no `:` autocomplete and no picker, although custom emoji already have an admin surface (`src/Controller/AdminCustomEmojiController.php`).
3. **No attach affordance.** Upload is paste/drag only; COMPOSER.md §7 lists the file-picker path.

Additional review findings this design addresses: the slash and reference menus are in-flow blocks that reflow the card (`app.css:948`, `:976`) instead of floating; the counter renders `n / 20000` from character zero (spec §11 says near-limit) and lies on DM pages whose real cap is 5000 (tech-debt #3); the always-on preview pane duplicates the message now that WYSIWYG is default-ON (spec §10 wants a toggle); the mobile compact dock leaks a Discard chip and an orphaned monogram (`app.css:1531–1542` hide-list misses them; see `docs/evidence/browser/mobile/80-thread-study.png`); the toolbar has no numbered-list control (spec §4); and topics loaded into the Community Inbox pane are never enhanced at all (tech-debt #5), leaving the flagship view a bare textarea.

Authority is unchanged: `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > `COMPOSER.md`/`USER.md` and the repository security invariants outrank this document. In particular:

- the canonical Markdown `<textarea>` remains the submit source and the no-JS/kill-switch surface; all submissions stay CSRF-protected form POSTs through the existing endpoints;
- `rich_composer=false` remains the broad kill switch that prevents all enhanced composer assets from loading; `wysiwyg_composer` behavior is unchanged;
- COMPOSER.md §15’s unified contract holds: the input surface is identical in all four mounts; only wrappers differ;
- strict CSP holds — no inline script or style anywhere in this work.

Session decisions by Henry (2026-07-13): scope is **container + mechanics** (submission stays form-POST; no optimistic AJAX send); the formatting toolbar becomes **engraved stroke icons with serif tooltips plus an `Aa` show/hide toggle**, consciously superseding the Imladris handoff §5.2 sentence-case text-label toolbar for the composer; implementation approach is the **server-first restructure** (shared shell partial), mirroring the Study spec’s selected approach.

## Scope

### In scope

- a shared `templates/partials/composer_shell.php` rendering the unified container for all four mounts: thread reply, new thread (board disclosure + `/compose` page), DM (conversation, new, and `dm_list` quick reply), and post edit (including wiki edit);
- the container anatomy: format row (JS-injected icons), auto-growing input, in-container bottom action bar (server-rendered identity chip, anonymity toggle chip, submit; JS-injected attach/Aa/emoji/preview), meta row (draft indicator + near-limit counter), in-container upload chips;
- send mechanics: Enter-to-send default ON per DECISIONS §6 #2; `Ctrl/Cmd+Enter` always sends; `Shift+Enter` newline with smart-list continuation; mobile soft-Enter inserts newline; submit-state machine with double-submit guard; `Esc` blurs;
- a real emoji surface: `:` trigger through the existing suggestion machinery plus a compact picker popover, server-backed (curated unicode set + custom emoji), no client-side emoji dataset;
- an attach (`＋`) button routed through the existing `/upload` XHR path, images only;
- floating popovers (slash, reference, emoji) anchored above the input on desktop; unified bottom-sheet treatment on mobile;
- preview converted from an always-on pane to an explicit toggle (COMPOSER.md §10);
- counter reading the input’s own `maxlength` (single-sourcing per tech-debt #3) and appearing near the limit only;
- the mobile compact dock rebuilt structurally so resting state shows only the pill and send;
- Inbox reading-pane re-enhancement: export `RetroBoardsComposer.enhanceWithin(root)` and call it from the Inbox `loadThread` path (tech-debt #5) — prerequisite for the rest to matter in the flagship view;
- numbered-list toolbar control; styled `<progress>`; context placeholders per COMPOSER.md §2;
- focused PHPUnit and Playwright regression coverage, axe scans, evidence refresh, and adding the composer browser spec to the CI evidence script;
- follow-up edits to `COMPOSER.md` (changelog entry) and `templates/account/composing.php` copy.

### Out of scope

- optimistic AJAX send/reconcile/rollback (COMPOSER.md §9.3) — deferred; requires JSON reply endpoints;
- global keyboard shortcuts (`r`/`c` focus composer, `Cmd/Ctrl+K` palette) and `↑` edit-last-post;
- typing indicators, scheduled send, audio/video, GIF beyond the existing GIPHY slash flow;
- non-image attachments, link unfurl/embeds, tables UI (all P2 per DECISIONS §6);
- a “replying to” quote chip wrapper (candidate next slice; the Study Quote action already covers the mechanic);
- new routes, migrations, feature flags, or changes to submission endpoints, validation, sanitisation, drafts storage, or rate limiting;
- server-rendered no-JS upload (uploads remain an enhancement, as today);
- the settings bio/signature textareas and the thread-summary curation form — they reuse `.composer-input` styling but are not composer mounts and keep their current markup;
- WYSIWYG bundle conditional loading (tech-debt #4) and full combobox-helper consolidation (tech-debt #8) — tracked debt, not this slice.

## Approaches considered

### 1. Server-first restructure — selected

Change the server-rendered markup itself: every mount renders the shared shell partial; interactive controls that must work without JS (anonymity, submit) are real form elements inside the action bar; `composer.js` injects the JS-only affordances into designated slots. DOM order equals visual order, the no-JS journey gets the same anatomy, and the shell partial ends the drift between six hand-rolled mount forms. This mirrors the approach the Study spec selected for the thread view.

### 2. CSS-led re-composition — rejected

Grid/`order` rearrangement of the existing DOM diverges tab order from visual order, cannot move the submit button inside the container, and grows the already-leaking compact-state hide-list. The Study spec rejected this pattern for the same reasons.

### 3. Client-side mount — rejected

Having `composer.js` build the whole container at enhance time leaves no-JS users a second, permanently divergent layout and requires runtime-reparenting form controls (fragile for form association, autofill, and accessibility). Against the repository’s server-first ethos.

## Design

### 1. The shell partial

`templates/partials/composer_shell.php` accepts a mount config: `action`, `context` (`reply` | `new_thread` | `dm` | `edit`), `target_id`, `placeholder`, `maxlength`, `body_name`/`body_value`, `submit_label`, `allow_anonymous` (+ current checked state), `identity` (display name/username or null for edit), `data-*` passthrough (`data-no-draft`, `data-no-wysiwyg`, `data-thread-composer`), and optional wrapper slots rendered above the box (title + board picker for new thread; recipient fields for DM). It emits:

```
<form class="composer composer-shell" …>
  [wrapper slot]
  <div class="composer-box">
    <!-- format row injected by composer.js, as the toolbar is today -->
    <textarea class="composer-input" …>
    <div class="composer-upload-tray"><!-- populated by JS on upload --></div>
    <div class="composer-actions-bar">
      <div class="composer-actions-start">
        <!-- JS injects: ＋ attach · Aa · 😊 emoji -->
        [identity chip] [anonymity toggle chip]
      </div>
      <div class="composer-actions-end">
        <!-- JS injects: ⎙ preview toggle -->
        <button type="submit" class="btn composer-send">…</button>
      </div>
    </div>
  </div>
  <div class="composer-meta-row">
    [draft indicator slot] [anonymity disclosure] [counter slot]
  </div>
  <!-- preview pane / draft-sync panel appended below by JS as today -->
</form>
```

CSRF and the server-stamped idempotency key render exactly as today. The **no-JS surface is the current honest surface**: textarea, anonymity chip, submit — the toolbar, attach, emoji, Aa, preview, counter, and draft indicator are all enhancements now too (the toolbar and paste/drag upload already are).

**Identity chip:** a small monogram + “as {display name}” muted text replaces the full-width “Posting as” strip. **Anonymity** stays a real `<label><input type="checkbox" name="is_anonymous"></label>` styled as a labeled chip (“Anonymous”), never icon-only. A CSS rule — `.composer-shell:has(input[name="is_anonymous"]:checked)` — reveals the moderator-visibility disclosure line in the meta row and dims the identity chip; the policy copy (“your name is hidden from other members; moderators can still see it”) is therefore visible whenever the toggle is on, with or without JavaScript, and never lives only in a tooltip.

### 2. Visual treatment

`.composer-box`: hairline `var(--border)`, existing radius and raised-surface tokens, and a `:focus-within` accent ring so the whole box reads as the focused object. The format row sits above the input behind a bottom hairline; buttons are 28px engraved stroke icons in the style of `templates/partials/icon.php`, grouped emphasis | block | insert with the existing hairline separators, in this order: bold, italic, strikethrough, inline code | quote, heading, bulleted list, **numbered list (new)**, code block, spoiler | link. Emoji leaves the format row for the action bar (it is an insert, not formatting). Active state keeps `aria-pressed` and the gold-accent treatment.

Tooltips are CSS-only (`data-tip` attribute + `::after`), Marcellus, in the form “Bold · Ctrl+B”; `aria-label`/`aria-keyshortcuts` remain on the buttons. The `Aa` action-bar button shows/hides the format row; its state persists per browser in `localStorage`; the row is **visible by default**. The send control is an accent-filled quill-glyph `<button type="submit">` at the action bar’s right end, with the mount’s label as its accessible name (“Reply”, “Create topic”, “Send”, “Save changes”). Both display registers (parchment and twilight) style through existing tokens only; no new hardcoded colors.

### 3. Send mechanics

- **Enter-to-send defaults ON**, implementing DECISIONS §6 #2. The default flips in `src/Support/PreferenceSchema.php` (composing section), `src/Core/App.php` `shareViewGlobals` composing fallback, and `templates/layout.php:5`. Preferences are stored per-section on save, so members who ever saved composing settings keep their explicit value; only never-saved members receive the new default. `tests/Unit/Preferences/PreferenceSchemaTest.php` updates accordingly.
- **`Ctrl/Cmd+Enter` always submits**, regardless of the preference — replacing today’s modifier+Enter no-op branch in `wireKeys`.
- **`Shift+Enter` always inserts a newline** and, when `smart_lists` is on, runs the existing list-continuation logic — otherwise the default flip would silently remove smart lists for source-mode users (Milkdown handles lists natively, so the WYSIWYG surface is unaffected).
- **Mobile:** on coarse pointers (`matchMedia('(pointer: coarse)')`), soft-keyboard Enter inserts a newline even when enter-to-send is on; sending is the button (DECISIONS §6 #2’s mobile clause).
- **Submit-state machine:** trimmed-empty (and no pending upload markdown) → send disabled; typing → enabled; on submit → disabled with a CSS spinner glyph (reduced-motion renders a static state) until navigation. This adds a client double-submit guard in front of the existing server idempotency dedupe. Without JS the button is always enabled and server validation governs, as today.
- **Menu precedence:** when a slash/reference/emoji menu is open, Enter selects the highlighted option — the existing keydown-ordering contract (menus register before `wireKeys`, using `stopImmediatePropagation`) is preserved and now includes the emoji popover.
- **`Esc`** blurs the input; drafts already autosave so nothing is lost.
- `templates/account/composing.php` copy updates to describe the new default and that `Ctrl/Cmd+Enter` always sends.

### 4. Emoji

- The `:` trigger joins the reference state machine with a **minimum two-character query** (so times like “10:30” and ordinary colons do not open it), excluded inside code fences like the other triggers, querying the existing endpoint: `/composer/suggest?trigger=:&q=…`.
- `src/Controller/ComposerController.php` gains an emoji provider for that trigger: a curated server-side unicode set (roughly 300 entries with names/keywords, a static PHP array — no schema change) merged with enabled rows from the custom-emoji table (respecting its feature gating). Responses reuse the existing suggestion item shape; custom emoji return their shortcode form.
- Selecting a suggestion inserts the unicode character (canonical, per COMPOSER.md §6.2) or `:shortcode:` for custom emoji, through the existing insertion contract (textarea and WYSIWYG adapters both).
- The 😊 action-bar button opens a compact popover: a recents row (`localStorage`, most recent ~24), a search field backed by the same endpoint, and the curated set grouped by category. No emoji dataset ships to the client; search round-trips are debounced like the preview.
- The old toolbar Emoji stub is removed.

### 5. Attach

The `＋` button (action bar far left) triggers a visually-hidden `<input type="file" accept="image/*" multiple>`; selected files route through the existing `uploadImage()` XHR path to `/upload` with the same purpose detection, placeholder-token, alt-text, reorder, and failure semantics. Upload cards become compact chips inside the box above the action bar: 48px thumbnail, filename/status, alt-text field (kept — accessibility), Up/Down/Remove buttons (kept for touch), and a token-styled `<progress>` replacing the default blue bar. Attachment kinds remain images-only; non-image files stay P2.

### 6. Floating popovers

`.composer-box` becomes the positioning context. On desktop widths the slash menu, reference menu, and emoji popover render `position: absolute; bottom: 100%` (opening upward, Slack-style) with a small offset, max-height and internal scroll — the box no longer changes height when a menu opens. At ≤640px all three use the existing fixed bottom-sheet treatment the reference menu already has (the slash menu joins it). Combobox semantics (`role`, `aria-expanded`, `aria-activedescendant`, keyboard handling) are unchanged; the emoji popover follows the same APG pattern. The duplicated-combobox consolidation remains tech-debt #8 and is not forced into this slice; shared positioning styles are factored once.

### 7. Meta row: drafts, counter, preview

- **Draft indicator:** the standalone “Discard draft” button is replaced by a quiet meta-row line, “Draft saved · Discard”, rendered only when a draft exists (same conditions as today’s button visibility; Discard keeps the same local+server discard behavior). The Drafts page and server-draft conflict panel are unchanged.
- **Counter:** `buildCounter` reads the textarea’s own `maxLength` (falling back to a `data-body-max` stamp), removing the `BODY_MAX` constant — DM composers stop displaying `n / 20000` against a 5000 cap (tech-debt #3). The counter renders only at ≥90% of the limit and keeps the existing `over` state. Template `maxlength` values continue to come from the mounts (config-fed single-sourcing per the debt item’s fix is welcome but only the JS side is required here).
- **Preview:** the always-on pane is replaced by an explicit ⎙ toggle in the action bar (COMPOSER.md §10). First activation lazily renders through the existing `/composer/preview` endpoint; while active it live-updates as today. The `show_preview` preference now means “preview toggle starts on” and continues to default true for source-mode users; when the WYSIWYG surface is active the toggle starts off (the rich surface is the live view; the pane is the server-truth parity view for spoilers/mentions/embeds). COMPOSER.md §5’s `Cmd/Ctrl+Shift+P` shortcut is intentionally not bound — it collides with Firefox’s private-window shortcut; preview stays button-driven and the divergence is recorded in COMPOSER.md’s changelog.

### 8. Mobile compact dock

The compact resting state collapses the box to a one-line pill plus the send button; the format row, action-bar inserts, identity/anonymity chips, meta row, upload tray, and preview pane are all inside the box/meta-row structure, so the resting state is defined by collapsing those two containers rather than enumerating child classes — the current leak list (`app.css:1531–1542`) and its escapees (Discard chip, orphaned monogram) disappear structurally. Focus/input still expands (existing delegated `focusin`/`input` handlers), expansion remains one-way per visit, and the existing `dvh`/safe-area/keyboard-inset behavior of `.thread-dock` is retained unchanged.

### 9. Mount specifics

- **Reply** (`templates/partials/composer.php`): placeholder “Reply to “{thread title}”…”; keeps `data-thread-composer`, the dock card treatment, and the anonymity chip when the board allows it.
- **New thread** (`templates/partials/new_thread_form.php`, `templates/compose.php`, board `<details>` disclosure): title input and board picker render in the wrapper slot above the box; placeholder “Start a new topic in #{board}…”; submit label “Create topic”.
- **DM** (`templates/dm/show.php`, `templates/dm/new.php`, `templates/partials/dm_compose_fields.php`, `templates/partials/dm_list.php`): placeholder “Message @{user}…” (or the conversation name); 5000 `maxlength` preserved; the `dm_list` quick reply keeps `data-no-wysiwyg`; the `.dm-composer` toolbar re-ordering hack (`app.css:3618`) is retired by the shell’s native order.
- **Edit** (`templates/partials/post_toolbar.php`, both post edit and wiki edit): submit label “Save changes”, `data-no-draft` preserved, no identity chip, anonymity field only where it exists today.
- **Inbox prerequisite:** `composer.js` exports `RetroBoardsComposer.enhanceWithin(root)` (idempotent via the existing `data-rb-enhanced` guard); the Inbox `loadThread` success path in `app.js` calls it on the inserted `.thread-conversation` (tech-debt #5). The Study drawer initializer already establishes this insertion-hook pattern.

## Progressive enhancement and state

Server-rendered HTML is complete and submittable before JavaScript runs. Enhanced state is limited to: format-row visibility (`localStorage`), open popover, emoji recents (`localStorage`), preview-toggle state, draft contents (existing keys, unchanged), and the transient submit state. No new server state, routes, or schema. `rich_composer=false` prevents all enhanced assets from loading and leaves the no-JS surface; `wysiwyg_composer` continues to gate only the Milkdown adapter through the existing bridge, and every new affordance (emoji, attach, preview toggle, popovers) drives the adapter contract so it works on both the textarea and WYSIWYG surfaces.

## Failure handling

- Suggestion/emoji/preview endpoint failures fail dark: menus hide, typing is never blocked, no console spam beyond the existing quiet catches.
- Upload failures keep today’s card-level error text and placeholder-token removal.
- Validation failures keep the anti-draft-loss contract: the server re-renders 422 with `reply_old`/`edit_old` into the same shell; the box renders expanded with the error above the input.
- A thread locked mid-compose, permission loss, or rate limit surfaces through existing server responses; the client adds nothing that pretends success (submit spinner ends at navigation).
- If `enhanceWithin` runs on markup lacking shell slots (stale fragment), enhancement no-ops per element exactly as `enhance()` does today.

## Accessibility and security

- All writes remain CSRF-protected form POSTs; no GET mutates state; the idempotency key stays server-rendered.
- Strict CSP holds: no inline script/style; tooltips and the anonymity disclosure are CSS (`data-tip` attributes, `:has()`); icons come from the existing partial.
- Every icon button has `aria-label` (+ `aria-keyshortcuts` where bound) and is tab-reachable; the format row’s show/hide is announced via `aria-expanded` on the `Aa` control; popovers keep the APG combobox/listbox semantics and `Esc`/outside-click dismissal with focus return.
- The anonymity control remains a real labeled checkbox with its disclosure text visible when active — never icon-only, never tooltip-only.
- Touch targets ≥44px on coarse pointers (icon buttons gain touch padding); the 28px visual glyph sits in a ≥44px hit area on mobile.
- `prefers-reduced-motion` disables the send spinner animation, popover transitions, and box expansion animation.
- Focus order matches DOM order: wrapper → format row (when visible) → input → action bar start → action bar end → meta row. The `:focus-within` ring meets the existing focus-token contrast.
- No horizontal overflow at 390px or 200% zoom; the popovers clamp to the box width.

## Testing

Test-first throughout; per-test DB isolation as usual.

### Server-side (PHPUnit)

- the shell renders per mount with correct action, context/target data attributes, CSRF field, idempotency field, `maxlength`, placeholder, submit label, and anonymity chip only where the board allows it;
- the edit mount renders `data-no-draft` and no identity chip; the DM quick reply keeps `data-no-wysiwyg`;
- `/composer/suggest?trigger=:` returns curated unicode matches; includes enabled custom emoji and respects their gating; unknown trigger still 4xx/no-ops as today;
- composing defaults: schema default `enter_to_send = true`; `PreferenceSchemaTest` updated; saved explicit preferences round-trip unchanged;
- no-JS markup contains exactly one submittable copy of every mount’s form (shell introduces no duplicates).

### Browser (Playwright) and evidence

- container anatomy desktop + mobile (light and dark registers): format row, in-box action bar, send at bottom-right, no counter at rest;
- Enter sends by default; `Shift+Enter` newlines and continues lists; `Ctrl/Cmd+Enter` sends with the preference off; mobile soft-Enter newlines;
- emoji: `:` autocomplete inserts unicode; picker popover inserts from recents/search; custom emoji inserts `:shortcode:`;
- attach: `＋` uploads via file picker; chip row renders inside the box; progress bar styled;
- popovers: opening the slash/reference/emoji menus does not change `.composer-box` height (no layout shift); mobile renders bottom sheets;
- counter appears only near the limit and shows the DM cap correctly on a DM page;
- draft row: appears on typing, Discard clears local + server draft;
- compact dock at 390×844: resting state shows only pill + send (no Discard chip, no monogram), expands on focus, stays above the keyboard inset;
- Inbox: load a topic dynamically and verify the pane composer is fully enhanced (format row, draft save, pickers) — the tech-debt #5 evidence;
- no-JS: submit a real reply through the shell; anonymity disclosure appears when checked;
- axe scans on the shell, open popovers, and expanded mobile dock report no serious/critical violations; reduced-motion check covers the spinner and popovers;
- evidence refresh: `80-thread-study` (desktop/mobile), `17-composer-upload`, `26-slash-menu`, a new composer-emoji capture, and `docs/evidence/browser/README.md`; the composer spec file joins the `evidence` script in `tests/browser/package.json` (CI runs only what that script names — tech-debt #2).

### Completion checks

- focused PHPUnit per red/green cycle; full `composer test` green; PHP syntax checks on changed files;
- focused Playwright desktop/mobile/no-JS/axe green; evidence command writes the captures;
- `git diff --check` clean; no inline CSP violations; no new flag, route, or migration in the diff;
- COMPOSER.md changelog entry (v0.7) recording: container anatomy, icon toolbar superseding handoff §5.2 labels, Enter-to-send default shipped, preview-toggle delivery with the `Ctrl+Shift+P` divergence, near-limit counter, emoji delivery path, attach button; USER.md §4.5 copy checked for consistency;
- deferrals (optimistic send; global keys; `↑` edit-last; quote chip) recorded in the carryover ledger per the ADR deferral convention rather than silently dropped.

## Expected files

- `templates/partials/composer_shell.php` (new);
- mount conversions: `templates/partials/composer.php`, `templates/partials/new_thread_form.php`, `templates/compose.php`, `templates/dm/show.php`, `templates/dm/new.php`, `templates/partials/dm_compose_fields.php`, `templates/partials/dm_list.php`, `templates/partials/post_toolbar.php`;
- `public/assets/composer.js` (icon toolbar, action-bar injection, send states, key handling, emoji trigger + popover, attach, popover positioning, counter, draft row, `enhanceWithin` export), `public/assets/app.js` (Inbox call site), `public/assets/app.css`;
- `src/Controller/ComposerController.php` (emoji suggestion provider), `src/Support/PreferenceSchema.php`, `src/Core/App.php` (composing fallback), `templates/layout.php` (default stamp), `templates/account/composing.php` (copy);
- `src/client/wysiwyg/milkdown-adapter.ts` only if the emoji insertion contract needs an adapter hook beyond `insertMarkdown` (expected: none);
- tests: `tests/Unit/Preferences/PreferenceSchemaTest.php`, a focused composer-shell integration test, `tests/browser/` composer spec (+ `tests/browser/package.json` evidence/a11y scripts), evidence images + README;
- `COMPOSER.md` changelog entry; carryover-ledger notes for the deferrals.

No migration, route, feature flag, or service-contract change is expected.
