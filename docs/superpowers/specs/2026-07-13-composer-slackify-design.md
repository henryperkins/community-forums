# Composer Shell — “The Writing Desk”

**Date:** 2026-07-13 (revised same day after adversarial review)
**Status:** Approved scope/toolbar/approach (Henry, in session); revision incorporates the 65-agent review’s six must-fixes, the delivery split, and verified minors
**Owner:** RetroBoards core theme

## Context

RetroBoards presents durable forum topics through a Slack/email-style three-pane shell, and the Study thread view (spec `2026-07-12-thread-view-study-design.md`) has just rebuilt the reading surface around that idea. The composer has not caught up. A full review of `COMPOSER.md`, the mounts, `public/assets/composer.js`, `app.css`, the evidence captures, and the 2026-07-11 frontend tech-debt audit found that the composer’s *engine* is already strong — local + server drafts with conflict resolution, `@`/`#` reference pickers with correct APG combobox semantics, slash inserts and GIPHY, paste/drag image upload with alt text, Milkdown WYSIWYG default-ON — but its *presentation* is a stack of seven loose rows (identity strip, toolbar, textarea, anonymity checkbox, submit button, always-on counter, discard button) rather than one contained input, and three of the most Slack-defining mechanics are specified but unshipped:

1. **No keyboard send exists by default.** DECISIONS.md §6 #2 locks “**Enter-to-send** (Slack-like default)”, but the shipped default is `enter_to_send => false` (`src/Support/PreferenceSchema.php:56`, `src/Core/App.php:543`, `templates/layout.php:5`, plus a fourth dead-but-inconsistent literal at `src/Service/PreferenceService.php:111`), and `composer.js` treats any modifier+Enter as newline, so `Ctrl/Cmd+Enter` does not send either.
2. **Emoji is a stub.** The toolbar “Emoji” button inserts the literal text `:smile:` (`composer.js` `ACTIONS.emoji`); there is no `:` autocomplete and no picker, although custom emoji already have an admin surface and service (`CustomEmojiService::catalogue()`).
3. **No attach affordance.** Upload is paste/drag only; COMPOSER.md §7 lists the file-picker path.

Additional review findings this design addresses: the slash and reference menus are in-flow blocks that reflow the card (`app.css:948`, `:976`) instead of floating; the counter renders `n / 20000` from character zero (spec §11 says near-limit) and lies on DM pages whose real cap is 5000 (tech-debt #3); the always-on preview pane duplicates the message now that WYSIWYG is default-ON (spec §10 wants a toggle); the mobile compact dock leaks a Discard chip and an orphaned monogram (`app.css:1531–1542` hide-list misses them); the toolbar has no numbered-list control (spec §4); and topics loaded into the Community Inbox pane are never enhanced at all (tech-debt #5), leaving the flagship view a bare textarea.

Authority is unchanged: `DECISIONS.md` > `DESIGN.md` > `SCHEMA.md` > `COMPOSER.md`/`USER.md` and the repository security invariants outrank this document. In particular:

- the canonical Markdown `<textarea>` remains the submit source and the no-JS/kill-switch surface; all submissions stay CSRF-protected form POSTs through the existing endpoints;
- `rich_composer=false` remains the broad kill switch that prevents all enhanced composer assets from loading; `wysiwyg_composer` behavior is unchanged;
- COMPOSER.md §15’s unified contract holds: the input surface is identical across the mounts; only wrappers differ;
- strict CSP holds — no inline script or style anywhere in this work.

Session decisions by Henry (2026-07-13): scope is **container + mechanics** (submission stays form-POST; no optimistic AJAX send); the formatting toolbar becomes **engraved stroke icons with serif tooltips plus an `Aa` show/hide toggle**, consciously superseding the Imladris handoff §5.2 sentence-case text-label toolbar for the composer; implementation approach is the **server-first restructure** (shared shell partial), mirroring the Study spec’s selected approach.

**Mount framing (one consistent count):** four mount *types* — reply, new thread, DM, edit — realized by **eight concrete body-input forms**: `templates/partials/composer.php`; `templates/partials/new_thread_form.php`; `templates/compose.php`; `templates/dm/show.php`; the `dm/new.php` + `partials/dm_compose_fields.php` pair; `templates/partials/dm_list.php`; and the post-edit and wiki-edit forms in `templates/partials/post_toolbar.php`. Other forms that merely carry `class="composer"` for styling (delete, report, thread-summary curation) have no `.composer-input` body and are **not** converted; the settings bio/signature textareas are likewise out.

## Scope

### In scope

Delivered as **three review-sized slices** (see “Delivery slices”), together comprising:

- a shared `templates/partials/composer_shell.php` rendering the unified container for all eight forms above;
- the container anatomy: format row (JS-injected icons), auto-growing input, in-container bottom action bar (server-rendered identity chip, anonymity toggle chip, submit; JS-injected attach/Aa/emoji/preview), meta row (draft indicator + near-limit counter + always-present anonymity disclosure), in-container upload chips, and an optional below-input wrapper slot (wiki-edit reason field);
- send mechanics: Enter-to-send default ON per DECISIONS §6 #2 with a **context-aware Enter contract on both surfaces** (§3); `Ctrl/Cmd+Enter` always sends; `Shift+Enter` newline; mobile soft-Enter inserts newline; submit-state machine with an in-flight guard; `Esc` blurs;
- a real emoji surface: `:` trigger through the existing suggestion machinery (requires widening **both** trigger implementations, including `milkdown-adapter.ts`) plus a picker specified as a **dialog + grid**, server-backed, no client-side emoji dataset;
- an attach (`＋`) button routed through the existing `/upload` XHR path, images only, `accept` limited to the DECISIONS §6 #6 types;
- floating popovers (slash, reference, emoji autocomplete) anchored above the input on desktop; unified bottom-sheet treatment on mobile; enumerated retargeting of every JS insertion point (§6);
- preview converted from an always-on pane to an explicit toggle (COMPOSER.md §10) with a defined persistence model;
- counter reading the input’s own `maxlength` (tech-debt #3) and appearing near the limit only, with polite live-region announcements;
- the mobile compact dock rebuilt structurally so resting state shows only the pill and send;
- Inbox reading-pane re-enhancement: a **feature-guarded** `RetroBoardsComposer.enhanceWithin(root)` call from the Inbox `loadThread` path (tech-debt #5);
- numbered-list toolbar control with a narrow-width overflow strategy (§2); styled `<progress>`; context placeholders per COMPOSER.md §2 (escaped, truncation-safe);
- focused PHPUnit and Playwright regression coverage (including the enter-to-send flip’s real red test, a `rich_composer=off` Inbox regression, and WYSIWYG list-authoring), inline axe scans in the composer spec, evidence refresh, and adding that spec to the CI `evidence` script;
- follow-up edits to `COMPOSER.md` (changelog **and** the §5 shortcut table row), `templates/account/composing.php` copy, and the preference-service literal.

### Out of scope

- optimistic AJAX send/reconcile/rollback (COMPOSER.md §9.3) — deferred; requires JSON reply endpoints. **Documented consequence:** submitting from the Inbox reading pane (including via Enter-to-send) remains a full-page navigation to the canonical thread. This is accepted for this slice — suppressing Enter-to-send only in the pane would make the same composer send differently by location, which is worse. Optimistic send is the real fix and stays the first follow-up candidate;
- global keyboard shortcuts (`r`/`c` focus composer, `Cmd/Ctrl+K` palette) and `↑` edit-last-post;
- typing indicators, scheduled send, audio/video, GIF beyond the existing GIPHY slash flow;
- non-image attachments, link unfurl/embeds, tables UI (all P2 per DECISIONS §6);
- a “replying to” quote chip wrapper (candidate next slice; the Study Quote action already covers the mechanic and gets a regression check here);
- new routes, migrations, feature flags, or changes to submission endpoints, validation, sanitisation, drafts storage, or rate limiting. **Flag justification:** the new affordances (emoji, attach, preview toggle, popovers) are progressive enhancements of the already-flagged composer subsystem — `rich_composer` gates every enhanced asset and remains the kill switch — so no new dark-defaulting flag is introduced (matching the Study restyle precedent);
- server-rendered no-JS upload (uploads remain an enhancement, as today);
- WYSIWYG bundle conditional loading (tech-debt #4) and full combobox-helper consolidation (tech-debt #8) — tracked debt, not this slice.

## Approaches considered

### 1. Server-first restructure — selected

Change the server-rendered markup itself: every mount renders the shared shell partial; interactive controls that must work without JS (anonymity, submit) are real form elements inside the action bar; `composer.js` injects the JS-only affordances into designated slots. DOM order equals visual order, the no-JS journey gets the same anatomy, and the shell partial ends the drift between the eight hand-rolled forms. This mirrors the approach the Study spec selected for the thread view.

### 2. CSS-led re-composition — rejected

Grid/`order` rearrangement of the existing DOM diverges tab order from visual order, cannot move the submit button inside the container, and grows the already-leaking compact-state hide-list. The Study spec rejected this pattern for the same reasons.

### 3. Client-side mount — rejected

Having `composer.js` build the whole container at enhance time leaves no-JS users a second, permanently divergent layout and requires runtime-reparenting form controls (fragile for form association, autofill, and accessibility). Against the repository’s server-first ethos.

## Delivery slices

One design, three review-sized diffs, in order:

1. **Container core** (genuinely interdependent): shell partial + all eight form conversions + send mechanics (§3) + counter + preview toggle + mobile compact dock + popover repositioning of the *existing* menus. This is the slice with the mount blast radius — each converted form must independently preserve its action, CSRF field, **server-rendered idempotency key (which the shell adds to the five forms that lack one today — only `composer.php`, `new_thread_form.php`, and `compose.php` render it now)**, field names, `data-*` passthroughs, anti-draft-loss 422 re-render, and a no-JS-submittable copy.
2. **Emoji surface** (self-contained): suggestion-provider branch in the service layer, curated catalog, `:` trigger in both detection implementations, the picker dialog.
3. **Inbox re-enhancement + attach** (decorate the container once it exists): guarded `enhanceWithin` call + the `＋` upload button and in-box upload chips.

Each slice lands with its own tests and evidence; deferrals between slices ride the carryover ledger per the ADR convention.

## Design

### 1. The shell partial

`templates/partials/composer_shell.php` accepts a mount config: `action`, `context` (`reply` | `new_thread` | `dm` | `edit`), `target_id`, `placeholder`, `maxlength`, `body_name`/`body_value`, `submit_label`, `allow_anonymous` (+ current checked state + **per-mount disclosure wording**), `identity` (display name/username or null for edit), `data-*` passthrough (`data-no-draft`, `data-no-wysiwyg`, `data-thread-composer`), an optional wrapper slot above the box (title + board picker for new thread; recipient fields for DM), and an optional **below-input slot** inside the box (the wiki-edit `reason` field). It emits:

```
<form class="composer composer-shell" …>
  [wrapper slot]
  <div class="composer-box">
    <!-- format row injected by composer.js, as the toolbar is today -->
    <textarea class="composer-input" …>
    [below-input slot]
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
    [draft indicator slot] [anonymity disclosure — always in DOM] [counter slot]
  </div>
  <!-- preview pane / draft-sync panel appended below the box by JS (§6) -->
</form>
```

CSRF renders as today; the **idempotency key is server-rendered by the shell in every mount** (today only three forms have it; the JS `stampIdempotency` fallback remains). The **no-JS surface is the current honest surface**: textarea, anonymity chip with visible disclosure, submit — the toolbar, attach, emoji, Aa, preview, counter, and draft indicator are all enhancements now too (the toolbar and paste/drag upload already are). Submit endpoints and `action` strings do not change (draft keys derive from `action`; changing one would orphan existing drafts).

**Identity chip:** a small monogram + “as {display name}” muted text replaces the full-width “Posting as” strip; `max-width` with `text-overflow: ellipsis` and `dir="auto"` guard long names and RTL.

**Anonymity:** stays a real `<label><input type="checkbox" name="is_anonymous"></label>` styled as a labeled chip (“Anonymous”), never icon-only. **The disclosure text renders unconditionally in the meta row** (per-mount wording — the thread/reply wording differs from `/compose`’s multi-board “only takes effect on boards that allow it” variant) and is tied to the checkbox via `aria-describedby`, so the consequence is readable *before* the user decides, exactly as today, with or without JavaScript or `:has()` support. `.composer-shell:has(input[name="is_anonymous"]:checked)` only **raises the disclosure’s prominence** (color/weight) and dims the identity chip — following the codebase’s own `:has()`-as-progressive-polish convention (`app.css:3458`, “`:has()` browsers only; others keep the standard flash plate”).

### 2. Visual treatment

`.composer-box`: hairline `var(--border)`, existing radius and raised-surface tokens, and a `:focus-within` accent ring so the whole box reads as the focused object. The format row sits above the input behind a bottom hairline; buttons are 28px engraved stroke icons in the style of `templates/partials/icon.php`, inside ≥44px hit areas on coarse pointers, grouped emphasis | block | insert with the existing hairline separators, in this order: bold, italic, strikethrough, inline code | quote, heading, bulleted list, **numbered list (new)**, code block, spoiler | link. Emoji leaves the format row for the action bar (it is an insert, not formatting). Active state keeps `aria-pressed` and the gold-accent treatment.

**Narrow widths:** eleven ≥44px targets cannot fit at 390px. The format row applies COMPOSER.md §4’s own overflow mechanism: at narrow widths it shows the essential set (bold, italic, link, bulleted list) plus a **“＋” overflow button** opening a popover listing the remaining controls with their text labels (Marcellus — the handoff labels live on here). No horizontal scroll, no wrap.

**Tooltips and names:** tooltips are CSS-only (`data-tip` + `::after`), Marcellus, shown on `:hover` **and `:focus-visible`**, in the form “Bold · Ctrl+B”. Because `aria-keyshortcuts` is advisory and largely unannounced, the bound shortcut is folded into the accessible name — `aria-label="Bold (Ctrl+B)"` — for the buttons that have one; `aria-keyshortcuts` is kept as supplementary semantics.

The `Aa` action-bar button shows/hides the format row via the `hidden` attribute (removing it from **both** the accessibility tree and tab order), reflected by `aria-expanded` on the `Aa` control; its state persists per browser in `localStorage` (`rb-composer:format-row`); the row is **visible by default**. The send control is an accent-filled quill-glyph `<button type="submit">` at the action bar’s right end, with the mount’s label as its accessible name (“Reply”, “Create topic”, “Send”, “Save changes”, “Save wiki edit”). Both display registers (parchment and twilight) style through existing tokens only.

### 3. Send mechanics

- **Enter-to-send defaults ON**, implementing DECISIONS §6 #2. The default flips in `src/Support/PreferenceSchema.php:56`, `src/Core/App.php:543` (`shareViewGlobals` fallback), `templates/layout.php:5`, **and the dead-but-inconsistent fallback at `src/Service/PreferenceService.php:111`**. Preferences are stored per-section on save, so members who ever saved composing settings keep their explicit value (`AppUserPreferencesTest` already proves the round-trip); only never-saved members receive the new default.
- **Context-aware Enter contract, identical on both surfaces** (this replaces v1’s incorrect “Milkdown is unaffected” claim — plain Enter is exactly where ProseMirror’s native list continuation lives, and `wireKeys` binds capture-phase on the Milkdown host via `keyTargets()`, so an unconditional submit would make multi-item lists un-authorable in the default-ON rich editor, where `Shift+Enter` is a hard break, not a new item):
  - Enter **sends** only when the caret is *outside* a list, blockquote, or code context;
  - inside such a context Enter behaves editorially — continues the list/quote/fence natively; Enter on an empty trailing item exits the block **without sending** (the next Enter sends);
  - implementation: before submitting, the keydown handler consults the active adapter — the WYSIWYG adapter answers from the ProseMirror selection (`enterShouldSubmit()`: not inside `listItem`/`blockquote`/code), the textarea adapter from the existing `continueList`/fence line checks. If the answer is “editorial”, the handler does not `preventDefault`/submit and lets the surface’s native behavior (or `continueList`) run.
  - **`Ctrl/Cmd+Enter` always submits**, from any context, regardless of the preference — replacing today’s modifier+Enter no-op branch.
  - **`Shift+Enter`** always inserts a newline/hard-break and never submits.
  - **Mobile:** on coarse pointers (`matchMedia('(pointer: coarse)')`), soft-keyboard Enter is always editorial/newline; sending is the button (DECISIONS §6 #2’s mobile clause).
- **Submit-state machine:** trimmed-empty → send disabled; typing → enabled; on submit → disabled with a CSS spinner (reduced-motion renders a static glyph, paired with a polite live-region “Sending…” announcement so feedback isn’t motion-only) until navigation. **The Enter keydown path short-circuits while a submit is in flight** (a disabled button does not stop `requestSubmit()`); the server idempotency dedupe remains the real double-submit guarantee. Without JS the button is always enabled and server validation governs, as today.
- **Menu precedence:** when a slash/reference/emoji-autocomplete menu is open, Enter selects the highlighted option — the existing keydown-ordering contract (menus register before `wireKeys`, using `stopImmediatePropagation`) is preserved and extended to the `:` menu.
- **`Esc`** blurs the input; drafts already autosave so nothing is lost.
- `templates/account/composing.php` copy updates for the new default, that `Ctrl/Cmd+Enter` always sends, and the list/quote/code Enter behavior.

### 4. Emoji

- The `:` trigger joins the reference state machine in **both trigger implementations** — `composer.js` (`referenceState`, `:1336`) **and** `src/client/wysiwyg/milkdown-adapter.ts` (`referenceState()` at `:399`, `textareaReferenceState()`, and the `ReferenceState.trigger` type union at `:24`, which currently hard-types `'@' | '#'` — a compile-time blocker, and since `wysiwyg_composer` is default-ON the adapter is the majority path). The query charset extends beyond `[A-Za-z0-9_-]` to include `+` (shortcodes like `:+1:`).
- Trigger conditions: the existing leading boundary `(^|[\s(])` is **load-bearing and must be preserved — it, not query length, is what keeps “10:30” from matching** (no boundary before “10”). A **minimum two-character query** is additionally required before the menu opens, purely to reduce popover noise on fragments like “:p”. Excluded inside code fences like the other triggers. Endpoint: `/composer/suggest?trigger=:&q=…`.
- **Layering:** the controller change is one line (accept `:` in the trigger whitelist). The provider logic lives in `ComposerSuggestionService::suggest()` as a new match arm, sourcing a curated unicode set (~300 entries with names/keywords) from a new `src/Support/EmojiCatalog.php` and merging enabled custom emoji via the existing `CustomEmojiService::catalogue()` — controllers stay thin per CLAUDE.md.
- Selecting a suggestion inserts the unicode character (canonical, per COMPOSER.md §6.2) or `:shortcode:` for custom emoji, through the existing insertion contract on both surfaces.
- **The 😊 picker is not a combobox and is specified separately:** the `:` inline autocomplete reuses the textarea-as-combobox pattern (focus never leaves the input). The picker button opens a **`role="dialog"`** popover (bottom sheet on mobile) named “Emoji”, containing a labeled search `<input type="search">` (backed by the same endpoint, debounced) and a **`role="grid"`** of emoji cells — recents row first (`localStorage` key `rb-composer:emoji-recents`, capped at 24), then the curated set by category. Focus moves into the search field on open; Tab cycles within the dialog; arrow keys navigate the grid two-dimensionally; each cell is a button whose accessible name is the emoji’s name; Enter/Space activates the focused cell (the form’s Enter-to-send never sees it — focus is inside the dialog); `Esc` closes and returns focus to the 😊 trigger. Insertion goes through the adapter at the remembered caret position.
- The old toolbar Emoji stub is removed.

### 5. Attach

The `＋` button (action bar far left) triggers a visually-hidden file input with **`accept` limited to the server’s actual whitelist — `.png,.jpg,.jpeg,.webp,.gif` (DECISIONS §6 #6)** — `multiple` allowed; selected files route through the existing `uploadImage()` XHR path to `/upload` with the same purpose detection, placeholder-token, alt-text, reorder, and failure semantics. Upload cards become compact chips inside the box above the action bar: 48px thumbnail, filename/status, alt-text field (kept — accessibility), Up/Down/Remove buttons (kept for touch), and a token-styled `<progress>` replacing the default blue bar; the tray keeps its existing `aria-live="polite"` so progress and completion announce. Attachment kinds remain images-only; non-image files stay P2.

### 6. Floating popovers and JS insertion-point retargeting

`.composer-box` becomes the positioning context. On desktop widths the slash menu, reference menu, and emoji-autocomplete menu render `position: absolute; bottom: 100%` (opening upward, Slack-style) with a small offset, max-height and internal scroll — the box no longer changes height when a menu opens. At ≤640px all three use the existing fixed bottom-sheet treatment the reference menu already has (the slash menu joins it). Combobox semantics for the inline menus are unchanged; the emoji **dialog** follows §4’s own model. The duplicated-combobox consolidation remains tech-debt #8; shared positioning styles are factored once.

Because the textarea moves inside `.composer-box`, every `composer.js` insertion point that targets `ta.parentNode`/`ta.nextSibling` today is **explicitly retargeted**:

| Element (current mount point) | New mount point |
|---|---|
| format toolbar (`buildToolbar`, before `ta`) | top of `.composer-box` |
| slash menu (`:1102`) and reference menu (`:1415`), after `ta` | children of `.composer-box` (anchor for `bottom:100%`) |
| character counter (`buildCounter`, `:248`) | `.composer-meta-row` counter slot |
| draft indicator/discard (`buildDiscard`) | `.composer-meta-row` draft slot |
| preview pane (`buildPreview`, `:257`) | after `.composer-box`, inside the form |
| draft-sync panel (`:321`) | after `.composer-box`, inside the form |
| upload tray (`uploadTray`, `:776`) | inside `.composer-box`, above the action bar (server-rendered slot) |
| action-bar injections (attach, Aa, 😊, ⎙) | `.composer-actions-start` / `.composer-actions-end` slots |

`enhance()` continues to key off `form.composer` + `.composer-input`; forms without a body input (delete/report/etc.) remain untouched no-ops.

### 7. Meta row: drafts, counter, preview

- **Draft indicator:** the standalone “Discard draft” button is replaced by a quiet meta-row line, “Draft saved · Discard”, rendered only when a draft exists (same visibility conditions; Discard keeps the same local+server discard behavior). The Drafts page and server-draft conflict panel are unchanged.
- **Counter:** `buildCounter` reads the textarea’s own `maxLength`, falling back to a `data-body-max` stamp; **if neither exists, no counter renders**. It appears only at ≥90% of the limit, keeps the `over` state, and carries `aria-live="polite"` so crossing the threshold (and going over) is announced. DM composers stop displaying `n / 20000` against a 5000 cap (tech-debt #3). Template `maxlength` values continue to come from the mounts.
- **Preview:** the always-on pane is replaced by an explicit ⎙ toggle in the action bar (COMPOSER.md §10). First activation lazily renders through the existing `/composer/preview` endpoint; while active it live-updates as today. **Persistence model:** toggle state persists per browser in `localStorage` (`rb-composer:preview`), like `Aa`; when no stored state exists, the initial state is the `show_preview` preference **and** the source-mode surface being active (when WYSIWYG is the active surface the initial state is off — the rich surface is the live view; the pane is the server-truth parity view). `templates/account/composing.php:24`’s label updates from “Show a live preview while composing” to “Start with the preview pane open (source mode)”. COMPOSER.md §5’s `Cmd/Ctrl+Shift+P` binding is intentionally not implemented (Firefox private-window collision); **both** the §5 shortcut-table row and the changelog record the divergence.

### 8. Mobile compact dock

The compact resting state collapses the box to a one-line pill plus the send button; the format row, action-bar inserts, identity/anonymity chips, meta row, upload tray, and preview pane are all inside the box/meta-row structure, so the resting state is defined by collapsing those two containers rather than enumerating child classes — the current leak list (`app.css:1531–1542`) and its escapees (Discard chip, orphaned monogram) disappear structurally. Focus/input still expands (existing delegated `focusin`/`input` handlers), expansion remains one-way per visit, and the existing `dvh`/safe-area/keyboard-inset behavior of `.thread-dock` is retained unchanged.

### 9. Mount specifics

- **Reply** (`templates/partials/composer.php`): placeholder “Reply to “{thread title}”…” — **the interpolated title is escaped with `$e()` like all template output** and relies on the input’s natural placeholder clipping for length; keeps `data-thread-composer`, the dock card treatment, and the anonymity chip + reply-context disclosure where the board allows it.
- **New thread** (`templates/partials/new_thread_form.php`, `templates/compose.php`, board `<details>` disclosure): title input and board picker render in the wrapper slot above the box; placeholder “Start a new topic in #{board}…” (escaped); submit label “Create topic”; `/compose` keeps its multi-board disclosure wording via the per-mount param.
- **DM** (`templates/dm/show.php`, `templates/dm/new.php`, `templates/partials/dm_compose_fields.php`, `templates/partials/dm_list.php`): placeholder “Message @{user}…” (escaped); 5000 `maxlength` preserved; the `dm_list` quick reply keeps `data-no-wysiwyg`; all DM render and 422 paths propagate `show_avatars`; the `.dm-composer` toolbar re-ordering hack (`app.css:3618`) is retired by the shell’s native order.
- **Edit** (`templates/partials/post_toolbar.php`): post edit — submit label “Save changes”, `data-no-draft` preserved, no identity chip, anonymity field only where it exists today. **Wiki edit** — same shell with the `reason` input in the below-input slot and “Save wiki edit” as the label; the adjacent revert form is not a mount and is untouched.
- **Study Quote regression:** the Study view’s Quote control appends Markdown to this same reply textarea; acceptance includes Quote still inserting on both surfaces, on the canonical page **and** on an Inbox-loaded thread after `enhanceWithin`.
- **Inbox prerequisite (guarded):** `composer.js` exports idempotent `RetroBoardsComposer.enhanceWithin(root)` plus `destroyWithin(root)`. Before `app.js` clears or replaces the reading fragment it calls `destroyWithin` to abort per-form fetch/XHR work, clear timers, remove document listeners, and destroy the active/fallback adapters; after installation it calls `enhanceWithin`. Both calls stay **behind namespace guards because `app.js` loads unconditionally (`layout.php:79`) while `composer.js` loads only when `rich_composer` is on (`layout.php:80`);** an unguarded call under the kill switch would throw inside the `.then` chain and trip the `.catch` full-page fallback, defeating in-pane loading in exactly the configuration the kill switch protects.

## Progressive enhancement and state

Server-rendered HTML is complete and submittable before JavaScript runs. Enhanced state is limited to: format-row visibility (`localStorage: rb-composer:format-row`), preview-toggle state (`rb-composer:preview`), emoji recents (`rb-composer:emoji-recents`, capped 24), open popover/dialog, draft contents (existing keys, unchanged — form `action` strings do not change), and the transient submit state. No new server state, routes, or schema. `rich_composer=false` prevents all enhanced assets from loading, leaves the no-JS surface, and — with the §9 guard — leaves Inbox in-pane loading working. `wysiwyg_composer` continues to gate only the Milkdown adapter through the existing bridge, and every new affordance (emoji, attach, preview toggle, popovers, Enter contract) drives the adapter contract so it works on both surfaces.

## Failure handling

- **Kill switch:** with `rich_composer` off, `window.RetroBoardsComposer` is absent; the guarded Inbox call no-ops and in-pane loading continues with the plain form.
- Suggestion/emoji/preview endpoint failures fail dark: menus hide, typing is never blocked.
- Upload failures keep today’s card-level error text and placeholder-token removal.
- Validation failures keep the anti-draft-loss contract: the server re-renders 422 with `reply_old`/`edit_old` into the same shell; the box renders expanded with the error above the input.
- A thread locked mid-compose, permission loss, or rate limit surfaces through existing server responses; the client adds nothing that pretends success (submit spinner ends at navigation; the in-flight guard prevents repeat keyboard submits).
- If `enhanceWithin` runs on markup lacking shell slots (stale fragment), enhancement no-ops per element exactly as `enhance()` does today.

## Accessibility and security

- All writes remain CSRF-protected form POSTs; no GET mutates state; the idempotency key is server-rendered and transactionally consumed in **all** mounts (topic/reply, DM start/reply, post edit, and wiki edit).
- Strict CSP holds: no inline script/style; tooltips and the anonymity emphasis are CSS (`data-tip` attributes, `:has()` as polish only); icons come from the existing partial.
- Every icon button is tab-reachable with an accessible name that includes its bound shortcut (“Bold (Ctrl+B)”); tooltips show on `:hover` and `:focus-visible`; `aria-keyshortcuts` kept as supplement. The format row’s visibility uses `hidden` (out of tab order) + `aria-expanded` on `Aa`.
- The anonymity control remains a real labeled checkbox; its disclosure text is **always in the DOM**, per-mount worded, and bound via `aria-describedby` — `:has()` only raises prominence. Never icon-only, never tooltip-only, never revealed-only-after-consent.
- The emoji picker is a named `role="dialog"` with searchbox + `role="grid"` cells (named per emoji), focus-managed open/close, in-dialog Tab cycling, 2-D arrow navigation, and `Esc`-to-trigger return; the `:` autocomplete keeps the textarea-combobox pattern.
- Live regions: upload tray (existing), counter threshold crossings, and a polite “Sending…” announcement paired with the reduced-motion static submit state.
- Touch targets ≥44px on coarse pointers; the narrow-width format row uses the essential-set + “＋” overflow rather than shrinking targets.
- `prefers-reduced-motion` disables the send spinner animation, popover transitions, and box expansion animation.
- Focus order matches DOM order: wrapper → format row (when visible) → input → below-input slot → action bar start → action bar end → meta row. The `:focus-within` ring meets the existing focus-token contrast.
- No horizontal overflow at 390px or 200% zoom, including with a long thread title in the placeholder and the identity chip’s ellipsis behavior; popovers clamp to the box width.

## Testing

Test-first throughout; per-test DB isolation as usual.

### Server-side (PHPUnit)

- the shell renders per mount with correct action, context/target data attributes, CSRF field, **idempotency field in all eight forms**, `maxlength`, escaped placeholder, submit label, below-input slot (wiki reason), and anonymity chip + correct per-mount disclosure only where allowed — disclosure present in the DOM regardless of checkbox state;
- the edit mounts render `data-no-draft` and no identity chip; the DM quick reply keeps `data-no-wysiwyg`;
- every identity-bearing mount, including dedicated-topic and DM validation re-renders, honors `show_avatars=false`; duplicate submits across all shell write contexts replay one matching result and do not duplicate DM messages or wiki revisions;
- `/composer/suggest?trigger=:` returns curated unicode matches via `ComposerSuggestionService`; includes enabled custom emoji and respects their gating; `+` in queries matches `:+1:`-style shortcodes; unknown triggers keep today’s rejection behavior;
- composing defaults: schema default `enter_to_send = true`; **`tests/Integration/Core/AppUserPreferencesTest.php:257–277` updated — it currently hard-asserts `data-enter-to-send="0"` for a fresh user and is the test that actually goes red on the flip** (its existing second half already proves saved-explicit round-trips); `PreferenceSchemaTest` gains an explicit default assertion; an explicit *saved-OFF round-trips unchanged* case is added; `PreferenceService.php:111` reconciled;
- no-JS markup contains exactly one submittable copy of every mount’s form.

### Browser (Playwright) and evidence

- container anatomy desktop + mobile (light and dark registers): format row, in-box action bar, send at bottom-right, no counter at rest, disclosure visible;
- Enter contract: plain Enter sends outside blocks; **inside a WYSIWYG list plain Enter creates the next item (multi-item list authoring works under the default);** Enter on an empty trailing item exits without sending; same matrix on the textarea surface; `Shift+Enter` newlines; `Ctrl/Cmd+Enter` sends from inside a list; mobile soft-Enter newlines; a second Enter during an in-flight submit does not double-fire;
- emoji: `:` autocomplete inserts unicode on both surfaces (adapter regex/type widened); picker dialog — focus lands in search, grid arrow-navigates, Enter inserts without submitting the form, `Esc` returns focus to the trigger; custom emoji inserts `:shortcode:`;
- attach: `＋` uploads via file picker (accept list per DECISIONS §6 #6); chip row renders inside the box; progress styled and announced; two-image Up/Down reorder updates the active rich adapter and survives canonical submit;
- Inbox fragment replacement calls `destroyWithin` before installing the next topic, destroys the old adapter exactly once, then enhances the replacement once;
- popovers: opening the slash/reference/emoji menus does not change `.composer-box` height; mobile renders bottom sheets;
- counter appears only near the limit, announces politely, and shows the DM cap correctly on a DM page;
- draft row appears on typing; Discard clears local + server draft;
- compact dock at 390×844: resting state shows only pill + send, expands on focus, stays above the keyboard inset; expanded-state format row shows essential set + “＋” overflow with no horizontal overflow — including a long-thread-title placeholder fixture;
- Inbox: load a topic dynamically and verify the pane composer is fully enhanced (format row, draft save, pickers) — the tech-debt #5 evidence; **a `rich_composer=off` regression proving in-pane topic loading still works (no thrown error, plain form renders)**; Study Quote inserts into the pane composer post-enhancement; a note in the spec run that in-pane submit intentionally navigates to the canonical thread;
- no-JS: submit a real reply through the shell; disclosure visible before and after checking Anonymous;
- **inline `AxeBuilder` scans in this spec file** (the standalone `a11y` script does not run in CI; api-tokens/providers specs set the inline precedent) covering the shell, open popovers, the emoji dialog, and the expanded mobile dock — no serious/critical violations; reduced-motion check covers the spinner (static state + “Sending…” announcement) and popovers;
- evidence refresh: `80-thread-study` (desktop/mobile), `17-composer-upload`, `26-slash-menu`, a new composer-emoji capture, and `docs/evidence/browser/README.md`; **the composer spec file joins the `evidence` script in `tests/browser/package.json`** (CI runs only what that script names).

### Completion checks

- focused PHPUnit per red/green cycle; full `composer test` green; PHP syntax checks on changed files;
- `npm run build:wysiwyg` rebuilt and the committed bundle diff reviewed (the adapter changes in §4 make this mandatory);
- focused Playwright desktop/mobile/no-JS/axe green; evidence command writes the captures;
- `git diff --check` clean; no inline CSP violations; no new flag, route, or migration in the diff;
- COMPOSER.md updates: changelog entry (v0.7) recording container anatomy, icon toolbar superseding handoff §5.2 labels, Enter-to-send default shipped with the context-aware contract, preview-toggle delivery, near-limit counter, emoji delivery path, attach button; **plus the §5 shortcut-table row annotation for the unbound `Cmd/Ctrl+Shift+P`**; USER.md §4.5 copy checked for consistency;
- deferrals (optimistic send — including the documented Inbox navigate-on-send consequence; global keys; `↑` edit-last; quote chip) recorded in the carryover ledger per the ADR deferral convention.

## Expected files

- `templates/partials/composer_shell.php` (new);
- mount conversions: `templates/partials/composer.php`, `templates/partials/new_thread_form.php`, `templates/compose.php`, `templates/dm/show.php`, `templates/dm/new.php`, `templates/partials/dm_compose_fields.php`, `templates/partials/dm_list.php`, `templates/partials/post_toolbar.php` (post edit + wiki edit);
- `public/assets/composer.js` (icon toolbar + overflow, action-bar injection, context-aware Enter + in-flight guard, `:` trigger, emoji dialog, attach, popover positioning + retargeted insertion points per §6, counter, draft row, guarded enhance/destroy lifecycle exports), `public/assets/app.js` (guarded Inbox destroy/install/enhance call sites), `public/assets/app.css`;
- **`src/client/wysiwyg/milkdown-adapter.ts`** (`ReferenceState` trigger union `:24`, `referenceState()` `:399`, `textareaReferenceState()`, query charset, `enterShouldSubmit()` adapter hook) and the rebuilt committed `wysiwyg-composer.js`/`.css` bundles;
- `src/Controller/ComposerController.php` (one-line trigger whitelist), `src/Service/ComposerSuggestionService.php` (emoji match arm), `src/Support/EmojiCatalog.php` (new curated set), reusing `CustomEmojiService::catalogue()`;
- `src/Support/PreferenceSchema.php`, `src/Core/App.php`, `templates/layout.php`, `src/Service/PreferenceService.php:111`, `templates/account/composing.php`;
- tests: `tests/Integration/Core/AppUserPreferencesTest.php` (the real red test), `tests/Unit/Preferences/PreferenceSchemaTest.php`, a focused composer-shell integration test, `tests/browser/` composer spec with inline axe (+ `tests/browser/package.json` evidence script), evidence images + README;
- `COMPOSER.md` (changelog + §5 table row); carryover-ledger notes for the deferrals.

No migration, route, or feature-flag change is expected. Review follow-ups may reuse the existing submission-idempotency ledger and preference service; adapter-facing behavior remains duck-typed through canonical Markdown methods.
