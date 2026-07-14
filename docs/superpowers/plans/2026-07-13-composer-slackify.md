# Composer Shell — “The Writing Desk” Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Keep the three slice checkpoints intact even if the work is delivered in one branch.

**Goal:** Replace RetroBoards’ loose composer rows with one server-rendered, Slack-like writing shell across all eight body-input forms, then add context-aware send keys, server-backed emoji, a visible attach path, and idempotent Community Inbox enhancement without changing canonical Markdown, POST endpoints, or no-JavaScript behavior.

**Architecture:** `templates/partials/composer_shell.php` owns the only `<form>`, CSRF token, idempotency token, canonical textarea, action bar, meta slots, and upload slot for every mount. Individual templates supply trusted PHP closure slots for context fields such as board/title, recipients, and wiki reason. `public/assets/composer.js` enhances named shell slots through a duck-typed textarea/Milkdown adapter and exports an idempotent `enhanceWithin(root)` entry point; `app.js` invokes that entry point after an Inbox topic fragment is installed. Emoji remains a read-only extension of the existing suggestion endpoint, while image attachment continues to use the existing `/upload` XHR implementation.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, plain PHP templates, vanilla JavaScript, Milkdown/ProseMirror, tokenized CSS, PHPUnit, Playwright 1.61.1, and `@axe-core/playwright`.

## Global constraints

- `DECISIONS.md` wins over every other document. The approved scope is `docs/superpowers/specs/2026-07-13-composer-slackify-design.md`; `DESIGN.md` §13 requires browser evidence for this UI-visible work.
- Preserve one canonical Markdown `<textarea>` and normal CSRF-protected form POST submission. Do not add an AJAX submission path, route, migration, table, endpoint, or feature flag.
- Keep `rich_composer=false` as the broad asset kill switch and `wysiwyg_composer` as the narrower Milkdown switch.
- Preserve every existing form `action`, field name, validation path, 422 anti-draft-loss value, draft key, rate limit, and sanitizer path.
- Convert exactly the eight body-input forms named below. Forms that merely use `class="composer"` but have no `.composer-input` remain untouched no-ops.
- Server-render an idempotency field in every converted form; keep `stampIdempotency()` only as a stale-fragment fallback.
- No inline `<script>`, `<style>`, or style attributes. JavaScript-created SVG nodes and CSS pseudo-element tooltips must use static, source-controlled data.
- The anonymity checkbox remains a real labeled control. Its mount-specific consequence text is always in the DOM and connected with `aria-describedby`.
- Maintain the existing menu-before-send keydown precedence, textarea fallback, server preview endpoint, local/server draft keys, upload placeholder tokens, and Quote insertion contract.
- Use only existing design tokens. Touch targets are at least 44×44 CSS pixels on coarse pointers, and the 390×844 layout must not scroll horizontally.
- Preserve unrelated dirty-worktree content, including the deleted bundle/patch files and untracked `.agents/`, `AGENTS.md`, `docs/tech-debt/`, and `output/` paths visible when this plan was written.
- Do not update `SCHEMA.md`: this work has no schema change. Check `USER.md` §4.5 for consistency, but do not edit it unless the final copy actually conflicts.

## Slice and commit boundaries

1. **Container core:** Tasks 1–4. Stop for review after the shell, all eight mounts, core controls, and the full Enter matrix are green.
2. **Emoji surface:** Tasks 5–6. Stop for review after the catalog/provider, inline `:` suggestions, and dialog/grid picker are green.
3. **Inbox re-enhancement + attach:** Task 7. Stop for review after file-picker upload and guarded Inbox enhancement are green.
4. **Closeout:** Task 8 records evidence, documentation, explicit deferrals, and the full-suite result.

Each task gets its own development commit. Each slice is reviewed and shipped only as the aggregate diff of its task commits; in particular, do not deploy or cherry-pick Task 1's default flip without Task 4's context-aware key contract because the approved container-core slice is intentionally interdependent.

## File structure

### Create

- `templates/partials/composer_shell.php` — the sole server-rendered shell/form implementation.
- `src/Support/EmojiCatalog.php` — curated server-side Unicode emoji data and search.
- `tests/Integration/Core/AppComposerShellTest.php` — all-eight-mount markup and no-JS contract.
- `tests/Unit/Composer/EmojiCatalogTest.php` — catalog bounds, uniqueness, and shape.
- `tests/browser/composer-shell.spec.ts` — shell, keyboard, emoji, attach, Inbox, no-JS, reduced-motion, axe, and named screenshot evidence.
- `docs/adr/0020-composer-shell-follow-ups.md` — explicit carryover ledger for the four approved deferrals.
- `docs/evidence/browser/{desktop,mobile}/82-composer-emoji.png` — new generated evidence.

### Modify

- Mounts: `templates/partials/composer.php`, `templates/partials/new_thread_form.php`, `templates/compose.php`, `templates/dm/show.php`, `templates/dm/new.php`, `templates/partials/dm_compose_fields.php`, `templates/partials/dm_list.php`, and the post-edit/wiki-edit portions of `templates/partials/post_toolbar.php`.
- Preferences/copy: `src/Support/PreferenceSchema.php`, `src/Service/PreferenceService.php`, `src/Core/App.php`, `templates/layout.php`, and `templates/account/composing.php`.
- Composer runtime: `public/assets/composer.js`, `public/assets/app.js`, and `public/assets/app.css`.
- WYSIWYG source and committed output: `src/client/wysiwyg/milkdown-adapter.ts`, `public/assets/wysiwyg-composer.js`, and, only if the deterministic build changes it, `public/assets/wysiwyg-composer.css`.
- Emoji endpoint/wiring: `src/Controller/ComposerController.php`, `src/Service/ComposerSuggestionService.php`, and the `ComposerSuggestionService` binding in `src/Core/App.php`.
- PHPUnit: `tests/Integration/Core/AppComposerTest.php`, `tests/Integration/Core/AppComposerSuggestTest.php`, `tests/Integration/Core/AppUserPreferencesTest.php`, and `tests/Unit/Preferences/PreferenceSchemaTest.php`.
- Browser regressions/evidence: `tests/browser/wysiwyg-composer.spec.ts`, `tests/browser/community-inbox-theme.spec.ts`, `tests/browser/gate-a.spec.ts`, `tests/browser/package.json`, `tests/browser/README.md`, `docs/evidence/browser/README.md`, and refreshed `17-composer-upload.png`, `26-slash-menu.png`, and `80-thread-study.png` at desktop/mobile widths.
- Product record: `COMPOSER.md`.

---

## Slice 1 — Container core

### Task 1: Flip only the never-saved Enter-to-send default

**Files:**

- Modify: `tests/Unit/Preferences/PreferenceSchemaTest.php`
- Modify: `tests/Integration/Core/AppUserPreferencesTest.php`
- Modify: `src/Support/PreferenceSchema.php`
- Modify: `src/Service/PreferenceService.php`
- Modify: `src/Core/App.php`
- Modify: `templates/layout.php`
- Modify: `templates/account/composing.php`

**Contract:** A user with no stored composing section resolves `enter_to_send=true`. A user who saves the checkbox off stores and continues to resolve explicit `false`. Do not bump `PreferenceSchema::VERSION`; the distinction is missing key versus stored boolean.

- [ ] **Step 1: Make the real integration test red**

Split the current `test_composing_preferences_are_stamped_on_the_page_body()` into explicit default and persisted-off cases:

```php
public function test_fresh_user_gets_the_slack_like_composing_defaults(): void
{
    $user = $this->makeUser(['username' => 'freshcompose']);
    $this->actingAs($user);

    $home = $this->get('/')->body();
    self::assertStringContainsString('data-enter-to-send="1"', $home);
    self::assertStringContainsString('data-show-preview="1"', $home);
    self::assertStringContainsString('data-smart-lists="1"', $home);
}

public function test_explicit_saved_off_enter_to_send_round_trips_unchanged(): void
{
    $user = $this->makeUser(['username' => 'composeoff']);
    $this->actingAs($user);

    $this->assertRedirect($this->post('/settings/composing', [
        'show_preview' => '1',
        'smart_lists' => '1',
        // enter_to_send deliberately omitted: unchecked boxes persist false.
    ]));

    $home = $this->get('/')->body();
    self::assertStringContainsString('data-enter-to-send="0"', $home);
    self::assertStringContainsString('data-show-preview="1"', $home);
    self::assertStringContainsString('data-smart-lists="1"', $home);
}
```

Add an explicit unit assertion beside the other defaults:

```php
self::assertTrue(PreferenceSchema::defaults()['enter_to_send']);
```

- [ ] **Step 2: Run the focused red tests**

```powershell
vendor\bin\phpunit tests/Unit/Preferences/PreferenceSchemaTest.php tests/Integration/Core/AppUserPreferencesTest.php --filter "fresh_user_gets|saved_off_enter|defaults"
```

Expected: the fresh-user assertions fail because all four runtime fallback literals are still false. The saved-off case should already pass.

- [ ] **Step 3: Reconcile the four default literals**

Change only these fallbacks from `false` to `true`:

```php
// src/Support/PreferenceSchema.php
'enter_to_send' => ['type' => 'bool', 'default' => true],

// src/Service/PreferenceService.php
'enter_to_send' => (bool) ($r['enter_to_send'] ?? true),

// src/Core/App.php and templates/layout.php
['enter_to_send' => true, 'show_preview' => true, 'smart_lists' => true]
```

Update the composing settings copy to say all of the following without promising behavior not yet implemented: Enter is the desktop default; list/quote/code contexts remain editorial; `Ctrl/Cmd+Enter` always sends; `Shift+Enter` inserts a new line; touch users send with the button. Change the preview label exactly to:

```text
Start with the preview pane open (source mode)
```

- [ ] **Step 4: Re-run the preference tests**

```powershell
vendor\bin\phpunit tests/Unit/Preferences/PreferenceSchemaTest.php tests/Integration/Core/AppUserPreferencesTest.php
```

Expected: PASS with no warnings or risky tests.

- [ ] **Step 5: Commit Task 1**

```powershell
git add -- src/Support/PreferenceSchema.php src/Service/PreferenceService.php src/Core/App.php templates/layout.php templates/account/composing.php tests/Unit/Preferences/PreferenceSchemaTest.php tests/Integration/Core/AppUserPreferencesTest.php
git commit -m "feat: make enter-to-send the composing default"
```

---

### Task 2: Introduce the shared shell and convert all eight mounts

**Files:**

- Create: `templates/partials/composer_shell.php`
- Create: `tests/Integration/Core/AppComposerShellTest.php`
- Modify: all eight mount templates listed in File structure
- Modify: `tests/Integration/Core/AppComposerTest.php`

**Shell input contract:**

```php
/**
 * Required:
 * action, context, target_id, instance_id, placeholder, maxlength,
 * body_value, submit_label.
 *
 * Optional:
 * body_name='body', form_id=null, form_class='', expanded=false,
 * body_error=null,
 * identity=null|array{display_name:string,username:string,show_avatar:bool},
 * allow_anonymous=false, anonymous_checked=false,
 * anonymous_disclosure='',
 * no_draft=false, no_wysiwyg=false, thread_composer=false,
 * wrapper_slot=null|Closure():void,
 * below_input_slot=null|Closure():void,
 * before_submit_slot=null|Closure():void.
 */
```

`context` is allow-listed to `reply|new_thread|dm|edit`. `instance_id` is a template-controlled slug used for unique textarea, checkbox, disclosure, and live-status IDs; it is not user input. Do not accept an arbitrary data-attribute map. Render only the three approved booleans (`data-no-draft`, `data-no-wysiwyg`, `data-thread-composer`) plus the required context/target values.

- [ ] **Step 1: Write the all-mount red integration suite**

Create fixtures for a board allowing anonymity, a normal thread, a wiki OP, another user, and a DM conversation. Fetch these surfaces:

| Concrete form | Page | Form discriminator |
|---|---|---|
| Reply | canonical thread | `/t/{id}/reply` + `data-thread-composer` |
| Board quick new topic | `/c/{slug}` | `/threads`, `instance_id=new-thread-board-{id}` |
| Dedicated new topic | `/compose?board={id}` | `/threads`, `instance_id=new-thread-page` |
| DM conversation reply | `/messages/{conversation}` | `/messages/{conversation}` |
| Dedicated new DM | `/messages/new` | `/messages`, `instance_id=dm-new-page` |
| DM-list quick compose | `/messages` | `/messages`, `data-no-wysiwyg` |
| Post edit | canonical thread | `/posts/{post}/edit` + `data-no-draft` |
| Wiki edit | canonical thread | `/posts/{post}/wiki/edit` + `data-no-draft` |

Use a helper that extracts a complete non-nested `<form>...</form>` by escaped action and, where actions repeat, `instance_id`. Assert for every extracted form:

- exactly one `composer composer-shell` form and exactly one `.composer-input`;
- correct `action`, `data-composer-context`, and `data-composer-target-id`;
- one `_token` and one `idempotency_key` hidden input;
- `maxlength=20000` for post contexts and `maxlength=5000` for DMs;
- one `.composer-box`, upload tray, action-start/end regions, meta draft/counter regions, and submit button;
- the mount’s accessible submit label;
- a placeholder with escaped fixture text (include a thread title containing `"<&`);
- no second submittable copy of that action/instance on the page.

Add focused assertions that edit/wiki omit `.composer-identity`, both retain `data-no-draft`, wiki reason is inside `.composer-box` after the textarea, DM quick retains `data-no-wysiwyg`, and failed reply/edit 422s preserve body/error/expanded state through the shell.

For anonymity, assert the checkbox label is `Anonymous`, the disclosure ID matches `aria-describedby`, reply wording differs from `/compose` multi-board wording, the disclosure exists both checked and unchecked, and a board with anonymity disabled renders neither checkbox nor disclosure.

- [ ] **Step 2: Run the red shell tests**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppComposerShellTest.php
```

Expected: FAIL because the shared shell and five missing server idempotency fields do not exist.

- [ ] **Step 3: Implement the shell partial**

Render this DOM contract, escaping every scalar with `$e` and invoking only callable closure slots:

```html
<form class="composer composer-shell [mount classes]" method="post" action="…"
      data-composer-context="…" data-composer-target-id="…" data-composer-instance="…">
  [CSRF]
  <input type="hidden" name="idempotency_key" value="[32 random hex chars]">
  [wrapper slot]
  <div class="composer-box">
    <div class="composer-format-slot" data-composer-format-slot></div>
    [body error]
    <textarea class="composer-input" id="…" name="body" maxlength="…" required>…</textarea>
    [below-input slot]
    <div class="composer-upload-tray" data-composer-upload-tray aria-live="polite"></div>
    <div class="composer-actions-bar">
      <div class="composer-actions-start">
        <span data-composer-actions-start-slot></span>
        [identity chip]
        [Anonymous checkbox chip]
      </div>
      <div class="composer-actions-end">
        <span data-composer-actions-end-slot></span>
        [before-submit slot]
        <button type="submit" class="btn composer-send" aria-label="[mount label]">
          <span aria-hidden="true">✒</span>
        </button>
      </div>
    </div>
  </div>
  <div class="composer-meta-row">
    <span class="composer-meta-draft" data-composer-draft-slot></span>
    [always-present anonymity disclosure when allowed]
    <span class="composer-meta-count" data-composer-counter-slot></span>
  </div>
  <div data-composer-after-box></div>
  <span class="sr-only" role="status" aria-live="polite" data-composer-submit-status></span>
</form>
```

The identity chip uses the existing monogram partial, `dir="auto"`, and the text `as {display name}`. Put cancel buttons in `before_submit_slot` so the quill send button remains the rightmost action. The server button is not disabled; empty-state disabling is enhancement-only.

- [ ] **Step 4: Convert every mount without changing endpoint strings**

Use non-static closures so `$this` remains the `View` inside wrapper/below-input slots. Apply these mount-specific values:

- Reply: retain `id="reply"`, `reply-composer thread-composer-card`, `is-expanded`, `data-thread-composer`, title placeholder `Reply to “{title}”…`, identity, and board-specific anonymity copy.
- Board quick topic: wrapper emits hidden `board_id`, title error/input, and no-JS Cancel; placeholder `Start a new topic in #{slug}…`.
- Dedicated topic: wrapper emits the board select, board/title errors, and title input; use the selected board slug for the placeholder and the multi-board anonymity disclosure.
- DM conversation: preserve `.dm-composer`, use the other participant username when present (`Message @{username}…`, otherwise `Message @recipient…`), `maxlength=5000`, and current-user identity.
- Dedicated/quick new DM: refactor `dm_compose_fields.php` to render only recipient/group-title fields through `wrapper_slot`; the shell owns body/error. On a validation re-render use the first typed recipient in the placeholder, otherwise `@recipient`. Quick compose keeps `data-no-wysiwyg` and its Cancel control.
- Every DM mount uses the accessible submit label `Send` (not a context-dependent mix of `Send message` and an unlabeled arrow).
- Post edit: `Save changes`, `data-no-draft`, no identity/anonymity.
- Wiki edit: `Save wiki edit`, `data-no-draft`, no identity, and reason input through `below_input_slot`. Do not touch wiki revert.

Do not convert delete, report, remove, summary-curation, settings bio, or signature forms.

- [ ] **Step 5: Update old markup assertions and run server regressions**

Update `AppComposerTest` assertions from exact legacy class strings to the new shell hooks while retaining the kill-switch, canonical Markdown, 422 reply/edit, bridge metadata, and no-draft tests.

```powershell
vendor\bin\phpunit tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerTest.php
```

Expected: PASS; the kill-switch response still contains the shell textarea but no enhanced assets.

- [ ] **Step 6: Commit Task 2**

```powershell
git add -- templates/partials/composer_shell.php templates/partials/composer.php templates/partials/new_thread_form.php templates/compose.php templates/dm/show.php templates/dm/new.php templates/partials/dm_compose_fields.php templates/partials/dm_list.php templates/partials/post_toolbar.php tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "refactor: unify composer shell markup"
```

---

### Task 3: Retarget core enhancement into shell slots

**Files:**

- Create: `tests/browser/composer-shell.spec.ts`
- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `tests/browser/community-inbox-theme.spec.ts`

**Runtime hooks:** `data-composer-format-slot`, `data-composer-actions-start-slot`, `data-composer-actions-end-slot`, `data-composer-draft-slot`, `data-composer-counter-slot`, `data-composer-after-box`, and the server-rendered upload tray are the only insertion targets. If a stale fragment lacks them, skip that individual enhancement without moving controls or throwing.

- [ ] **Step 1: Add red browser tests for the contained shell**

Create login/feature/preference helpers using the existing `runPhp()` pattern and import `AxeBuilder`. Add an `afterEach` database reset for any feature/preference override made by a test (especially `rich_composer=false`) so this spec can run last in the shared standard-evidence database without poisoning later work. Exercise the anatomy in both light/parchment and dark/twilight registers, then add desktop and mobile tests that assert:

- toolbar → textarea → upload tray → action bar order inside `.composer-box`;
- in Slice 1, action-start contains `Aa` and action-end contains Preview and send; no dead Attach/Emoji buttons render before their slices implement them;
- toolbar order is bold, italic, strike, inline code, quote, heading, bullet, numbered, code block, spoiler, link;
- toolbar buttons are icon-only visually but have accessible names such as `Bold (Ctrl+B)` and supplementary `aria-keyshortcuts`;
- `Aa` uses `hidden`/`aria-expanded`, defaults open, and persists `rb-composer:format-row` across reload;
- narrow width exposes bold/italic/link/bullet plus a named formatting-overflow button, with no wrap, horizontal scroll, or document overflow;
- toolbar tooltips become visible for both pointer hover and keyboard `:focus-visible` without becoming the accessible-name source;
- an empty composer has disabled send after enhancement and no visible counter;
- the textarea retains delegated auto-growth as content gains lines; the shell refactor must not break `app.js`'s existing `.composer-input` autosize listener;
- typing enables send, shows `Draft saved · Discard`, and Discard removes both local and server draft state;
- counter appears at 90%, reads the textarea limit, and a DM shows `4500 / 5000` rather than `/ 20000`;
- preview is lazy, toggled explicitly, persisted in `rb-composer:preview`, and defaults closed while Milkdown rich mode is active even if `show_preview=true`;
- opening slash/reference menus does not change `.composer-box` height; at mobile width they are fixed bottom sheets;
- 390×844 resting reply state contains only the pill/active editor and send, then expands one-way on focus without an orphaned monogram, source-mode toggle, or draft chip;
- a long title/identity fixture remains clipped rather than overflowing at 390px and at browser zoom equivalent to 200%;
- anonymity consequence text is visible before checking the labeled chip and remains present after checking;
- inline axe scans cover the shell at rest, an open suggestion popover, and the expanded mobile dock with no serious/critical violations.

Update old browser assertions that expect sentence-case toolbar text, always-open preview, horizontal toolbar scrolling, or `.checkline` anonymity markup.

- [ ] **Step 2: Run the focused red browser subset**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts --grep "anatomy|format row|counter|preview|draft|popover|compact"
Set-Location ../..
```

Expected: FAIL against the legacy insertion points and toolbar.

- [ ] **Step 3: Add slot helpers and the idempotent public entry point**

Refactor the namespace without losing WYSIWYG registration:

```javascript
function shellPart(form, selector) {
    return form.querySelector(selector);
}

function enhanceWithin(root) {
    var forms = [];
    if (root && root.matches && root.matches('form.composer')) { forms.push(root); }
    if (root && root.querySelectorAll) {
        root.querySelectorAll('form.composer').forEach(function (form) { forms.push(form); });
    }
    var prefs = composingPrefs();
    forms.forEach(function (form) { enhance(form, prefs); });
}

window.RetroBoardsComposer = {
    registerWysiwygAdapter: registerWysiwygAdapter,
    enhanceWithin: enhanceWithin
};
```

Keep `data-rb-enhanced` as the one-time guard. `registerWysiwygAdapter()` may re-run only `form._rbComposerEnhance()` for already-enhanced forms. `DOMContentLoaded` calls `enhanceWithin(document)` once, plus the existing completed-draft and Drafts-page routines.

- [ ] **Step 4: Rebuild toolbar and action controls**

Use an ordered action list, remove the Emoji stub, and add `orderedList` with `before: '\n1. '`/`prefix: '1. '`. Its active-state predicate recognizes any current numbered marker (`1.`/`2)` etc.), not only the literal inserted `1.` prefix. Create 28px engraved stroke SVGs through `createElementNS`; never inject user data through `innerHTML`. Add group separators after inline code and spoiler, with coarse-pointer hit boxes expanded to at least 44px.

On wide screens all eleven actions render. On narrow screens CSS hides nonessential primary buttons and reveals a `More formatting` (`＋`) button whose popover contains text-labeled copies of the seven nonessential commands. Both copies call the same `applyActionForAdapter()` path and maintain `aria-pressed` state.

Inject only completed controls into the named action slots:

- start in Slice 1: `Aa`; Task 6 later appends Emoji, and Task 7 inserts Attach at the far left;
- end: Preview toggle;
- keep identity/anonymity server-rendered and send rightmost.

Do not render inert placeholder buttons or a file input before their implementing slice. The empty server slot is the extension point.

Use `data-tip="Bold · Ctrl+B"`; CSS displays it on `:hover` and `:focus-visible`. The accessible name includes the shortcut, while `aria-keyshortcuts="Control+B Meta+B"` remains supplementary.

- [ ] **Step 5: Retarget counter, drafts, preview, menus, and upload tray**

- `buildCounter(form, adapter)`: read `ta.maxLength`, then numeric `data-body-max`; return without rendering when neither is positive. Mount in the counter slot, set `aria-live=polite`, hide below `Math.ceil(limit * 0.9)`, and retain `.over` above the limit.
- `buildDiscard()`: mount `Draft saved · ` plus a button whose visible text is `Discard` and accessible name remains `Discard draft`; preserve local and server delete behavior exactly.
- `buildDraftSyncPanel()`: append to `data-composer-after-box`, not `ta.parentNode`.
- `buildPreview()`: install the toggle in the end slot and pane in `data-composer-after-box`. Fetch only on first open and update only while open. Read/write `rb-composer:preview`. With no stored value, open only when `prefs.showPreview && activeAdapter.isSourceMode()`.
- `maybeUpgradeWysiwyg()`: after a successful adapter swap, let the preview controller reconcile its untouched initial state so rich mode defaults closed.
- slash/reference menus: append to `.composer-box`; factor one `.composer-suggestion-popover` positioning rule. Desktop uses absolute upward placement and internal scrolling; ≤640px uses the fixed sheet rule.
- `uploadTray()`: use the server tray and never create a second tray under the textarea.

Route `rb-composer:format-row`, `rb-composer:preview`, and later emoji-recents access through small `try/catch` storage helpers. A blocked/corrupt `localStorage` value falls back to the documented default and must not prevent enhancement.

- [ ] **Step 6: Add the submit-state controller**

Subscribe to the active adapter and keep `.composer-send.disabled` synchronized to `adapter.getMarkdown().trim() === ''`. On `submit`, first sync canonical Markdown, reject empty enhanced submits, then set `form._rbSubmitting=true`, disable only the send button, set `aria-busy=true`, add `.is-submitting`, and write `Sending…` to the polite server status slot. Never disable the canonical textarea in the submit event—disabled controls are omitted from form data. Any keyboard submit helper must return immediately when `_rbSubmitting` is true.

CSS animates the quill/spinner only outside `prefers-reduced-motion: reduce`; reduced motion uses a static glyph while retaining the same live text. Do not re-enable on a timer—the normal result is navigation and server errors are full responses.

- [ ] **Step 7: Replace legacy composer CSS with structural shell rules**

Add `.composer-box` border/radius/surface/`position:relative` and a tokenized `:focus-within` ring. Add identity ellipsis and `dir=auto` support, labeled anonymity chip/disclosure, `:has()` prominence-only polish, action/meta grids, upload-tray containment, icon tooltips, overflow menu, near-limit counter, preview panel, popover/sheet rules, and reduced-motion overrides. The 48px compact upload-chip restyle lands with the visible Attach affordance in Task 7.

Replace the mobile child-by-child hide list with structural selectors: the resting reply dock collapses the format slot, upload tray, non-send actions (including the existing Milkdown source-mode toggle), and entire meta/after-box regions; it leaves the one-line active input plus send. Preserve existing `.thread-dock` `dvh`, safe-area, and keyboard-inset rules and existing delegated one-way `.is-expanded` behavior.

- [ ] **Step 8: Run the core browser and PHP regression set**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerTest.php
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts community-inbox-theme.spec.ts --grep "anatomy|format row|counter|preview|draft|popover|compact|toolbar"
Set-Location ../..
```

Expected: PASS on desktop and mobile.

- [ ] **Step 9: Commit Task 3**

```powershell
git add -- public/assets/composer.js public/assets/app.css tests/browser/composer-shell.spec.ts tests/browser/wysiwyg-composer.spec.ts tests/browser/community-inbox-theme.spec.ts
git commit -m "feat: add contained composer controls"
```

---

### Task 4: Implement the context-aware Enter contract on both adapters

**Files:**

- Modify: `public/assets/composer.js`
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `public/assets/wysiwyg-composer.css` only if build output changes
- Modify: `tests/browser/composer-shell.spec.ts`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`

**Adapter additions:**

```typescript
enterShouldSubmit(): boolean;
isSourceMode(): boolean;
```

`enterShouldSubmit()` is observational; textarea-only list/quote continuation remains in the shared bridge so one key event is never handled twice.

- [ ] **Step 1: Add the complete red Enter matrix**

For both textarea (`wysiwyg_composer=false`) and Milkdown (`true`), test with a valid title/body target:

1. Plain Enter outside a block submits on desktop when the preference is on.
2. Plain Enter inside a bullet/numbered list creates the next item and does not submit.
3. Enter on the empty trailing item exits the list and does not submit; the following Enter submits.
4. Enter inside blockquote, fenced/code block, and inline-code context is editorial.
5. `Shift+Enter` creates a newline/hard break and never submits.
6. `Ctrl+Enter`/`Meta+Enter` submits from inside a list even when the preference is off.
7. Plain mobile/coarse-pointer Enter creates a newline and the button sends.
8. Two Enter events while the first POST is route-delayed produce one request; the button is disabled/spinning and the polite live region says `Sending…`.
9. `Esc` blurs when no menu is open; an open suggestion menu consumes Esc first.

Use request counters/route interception for the in-flight case rather than relying only on the server’s idempotency result.

- [ ] **Step 2: Run the red keyboard subset**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts --grep "Enter|send key|list authoring|in-flight|Escape"
Set-Location ../..
```

Expected: failures for modifiers, list-aware rich input, coarse pointer, and in-flight guard.

- [ ] **Step 3: Implement textarea editorial detection**

Add `TextareaComposerAdapter.prototype.enterShouldSubmit()` backed by helpers that return false when the caret is:

- inside an unordered or ordered list marker line;
- on a blockquote line;
- inside an open triple-backtick fence;
- inside an unmatched inline-code span on the current line.

Extend the existing smart continuation helper to blockquotes. Non-empty quote lines insert the next quote prefix; an empty trailing prefix is removed and exits without sending. Keep the existing list behavior: an empty marker exits without sending, and the next Enter is outside the list. If `smart_lists` is off, return editorial but allow the textarea’s native newline instead of adding a prefix.

- [ ] **Step 4: Implement Milkdown selection inspection**

In `milkdown-adapter.ts`, return false when any ProseMirror selection ancestor has a list-item name (`list_item` or `listItem`), is `blockquote`, or has `type.spec.code`; also return false when the active marks include `inlineCode` or `code`. Return true for a normal paragraph. `isSourceMode()` returns `!richMode || failed || destroyed`.

- [ ] **Step 5: Rewrite shared key precedence**

In `wireKeys()`:

1. Let already-open slash/reference/emoji menus keep first registration and `stopImmediatePropagation()`.
2. Handle formatting modifiers.
3. On `Escape`, blur the active key target.
4. Ignore composition events.
5. `Shift+Enter` and `Alt+Enter` remain editorial.
6. `Ctrl/Cmd+Enter` calls the guarded submit helper unconditionally.
7. Plain Enter on coarse pointers remains editorial.
8. Plain Enter with preference off remains editorial.
9. Ask the active adapter; if false, run textarea continuation only for the fallback target and otherwise allow native rich behavior.
10. Submit only when every preceding condition permits it and `_rbSubmitting` is false.

- [ ] **Step 6: Build and verify the committed Milkdown artifact**

```powershell
npm run build:wysiwyg
git diff -- public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css
```

Review the generated diff: it must contain the new adapter hooks and no unrelated dependency/runtime churn.

- [ ] **Step 7: Run the full Slice 1 checkpoint**

```powershell
vendor\bin\phpunit tests/Unit/Preferences/PreferenceSchemaTest.php tests/Integration/Core/AppUserPreferencesTest.php tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerTest.php
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts community-inbox-theme.spec.ts
Set-Location ../..
```

Expected: all Slice 1 PHP and desktop/mobile browser tests pass, including source and rich list authoring.

- [ ] **Step 8: Commit Task 4 and stop for Slice 1 review**

```powershell
git add -- public/assets/composer.js src/client/wysiwyg/milkdown-adapter.ts public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/composer-shell.spec.ts tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat: enforce context-aware composer send keys"
```

---

## Slice 2 — Emoji surface

### Task 5: Add the curated catalog and `:` suggestion provider

**Files:**

- Create: `src/Support/EmojiCatalog.php`
- Create: `tests/Unit/Composer/EmojiCatalogTest.php`
- Modify: `src/Controller/ComposerController.php`
- Modify: `src/Service/ComposerSuggestionService.php`
- Modify: `src/Core/App.php`
- Modify: `tests/Integration/Core/AppComposerSuggestTest.php`

**Catalog row:**

```php
array{
    emoji: string,
    name: string,
    shortcodes: list<string>,
    keywords: list<string>,
    category: string
}
```

The checked-in catalog contains 280–320 curated entries across Smileys & emotion, People & body, Animals & nature, Food & drink, Activities, Travel & places, Objects, Symbols, and Flags. It is server-only; do not emit a JavaScript dataset.

- [ ] **Step 1: Write catalog and endpoint red tests**

The unit test asserts:

- count is between 280 and 320;
- every row has a non-empty Unicode glyph/name, at least one valid `[a-z0-9_+-]{2,40}` shortcode, keywords, and an allow-listed category;
- primary shortcodes are unique;
- `+1`, `thumb`, and `party` searches produce deterministic matches.

Extend `AppComposerSuggestTest` to assert:

- `trigger=:` with `q=smil` returns a curated item whose `markdown` is Unicode;
- `q=+1` matches the `:+1:` alias;
- an enabled custom emoji returns `type=custom_emoji`, its `/emoji/...` URL, and `markdown=:shortcode:` only while `custom_emoji` is enabled;
- disabled custom rows and feature-off custom rows do not appear;
- `@`/`#` behavior remains unchanged and `trigger=!` still returns 422;
- empty `q` is permitted only for `:` and returns the category catalog for the picker.

- [ ] **Step 2: Run the red provider tests**

```powershell
vendor\bin\phpunit tests/Unit/Composer/EmojiCatalogTest.php tests/Integration/Core/AppComposerSuggestTest.php
```

Expected: catalog class missing and `:` rejected by the controller.

- [ ] **Step 3: Implement deterministic catalog search**

`EmojiCatalog::all()` returns the rows. `EmojiCatalog::search($query)` case-folds and searches shortcode, name, and keyword fields with deterministic scoring: exact shortcode, shortcode prefix, name prefix, keyword prefix, then substring; ties sort by name/primary shortcode. Preserve `+`—do not run a slug sanitizer that removes it.

- [ ] **Step 4: Extend the provider and DI wiring**

Add `:` to the controller whitelist. Inject `CustomEmojiService` into `ComposerSuggestionService` via its hand-written `App.php` binding. In `suggest()`:

```php
if ($query === '' && $trigger !== ':') {
    return [];
}

$items = match ($trigger) {
    '@' => $this->userSuggestions(...),
    '#' => $this->hashSuggestions(...),
    ':' => $this->emojiSuggestions($query),
    default => [],
};
```

Map curated rows to `ComposerSuggestion` with `type=emoji`, a stable catalog index ID, Unicode `markdown`, primary `:shortcode:` token, category group, and no remote URL. When `custom_emoji` is enabled, merge only `catalogue()` rows with `is_enabled=1`; map them to `type=custom_emoji`, real shortcode token/Markdown, image path URL, name, and group `Custom`. Dedupe by `type + token`, not URL (curated rows have empty URLs).

Return at most 20 results for typed queries. For `trigger=:` with empty query, return the full bounded catalog plus enabled custom rows so the picker can group it; the existing rate limit remains in force.

- [ ] **Step 5: Run the server emoji tests**

```powershell
vendor\bin\phpunit tests/Unit/Composer/EmojiCatalogTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppComposerTest.php
```

Expected: PASS; canonical Markdown rendering tests still map built-in shortcodes exactly as before.

- [ ] **Step 6: Commit Task 5**

```powershell
git add -- src/Support/EmojiCatalog.php src/Controller/ComposerController.php src/Service/ComposerSuggestionService.php src/Core/App.php tests/Unit/Composer/EmojiCatalogTest.php tests/Integration/Core/AppComposerSuggestTest.php
git commit -m "feat: add server-backed emoji suggestions"
```

---

### Task 6: Add `:` autocomplete and the accessible picker dialog

**Files:**

- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `tests/browser/composer-shell.spec.ts`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Create via evidence run: `docs/evidence/browser/{desktop,mobile}/82-composer-emoji.png`

**Additional adapter hooks for focus-moving insertion:**

```typescript
rememberSelection(): { start: number; end: number };
replaceRememberedSelection(mark: { start: number; end: number }, markdown: string): void;
```

The textarea adapter stores `selectionStart/selectionEnd`; Milkdown stores the current ProseMirror selection and replaces that exact range when the dialog selection is committed.

- [ ] **Step 1: Add red inline-autocomplete and picker tests**

Test both source and rich surfaces:

- `:sm` opens only after two query characters and inserts Unicode;
- `10:30`, `:p`, and code-fence/inline-code text do not open it;
- `:+1` matches and inserts the expected Unicode;
- custom selection inserts `:shortcode:`;
- Emoji opens a named `role=dialog`, focuses its labeled search field, and renders category grids;
- recents appear first, persist under `rb-composer:emoji-recents`, and cap at 24;
- left/right/up/down move cell focus two-dimensionally; Enter/Space inserts without submitting;
- Tab is trapped inside the open dialog; Esc closes and returns focus to Emoji;
- insertion occurs at the remembered source/rich caret, not at the end;
- an inline `AxeBuilder` scan of autocomplete + dialog has no serious/critical violations.

- [ ] **Step 2: Run the red emoji browser subset**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts --grep "emoji|colon|recents"
Set-Location ../..
```

Expected: FAIL because only `@`/`#` are recognized and the old toolbar stub has been removed.

- [ ] **Step 3: Widen both trigger implementations**

In `composer.js` and both `milkdown-adapter.ts` reference-state functions:

- widen `ReferenceState.trigger` from `'@' | '#'` to `'@' | '#' | ':'`;
- preserve the leading `(^|[\s(])` boundary;
- include `+` in the query charset without making `-` a range;
- keep heading suppression only for `#`;
- keep code-context suppression;
- client-side, hide `:` results until the query length is at least two.

The `10:30` regression must remain green because no accepted boundary precedes the colon.

- [ ] **Step 4: Implement the picker**

Inject Emoji into the action-start slot. On open, remember the active adapter selection, fetch `/composer/suggest?trigger=:&q=` once, focus the search box, and render recents followed by server groups. Search uses the same endpoint with a 150–250ms debounce and replaces the grid with results.

Use `role="dialog"`, `aria-modal="true"`, an accessible name `Emoji`, a labeled `type=search`, and one or more named `role=grid` containers whose cells are buttons named by emoji name. Curated cells render Unicode text; custom cells render the same-origin image URL with decorative image alt and a named button. Compute up/down movement from the rendered column count; left/right moves one cell. Trap Tab while open. Esc restores focus to the trigger.

On activation, call `replaceRememberedSelection()`, update recents uniquely at the front, truncate to 24, close, restore editor focus, and dispatch the same adapter change path used by other insertions. Endpoint errors close/fail dark and never block typing.

- [ ] **Step 5: Style autocomplete and dialog/popover**

Reuse the shared desktop upward-popover and mobile fixed-sheet primitives for inline `:` autocomplete. Style the picker separately as a tokenized popover/dialog on desktop and bottom sheet at ≤640px, with max-height/internal scrolling, fixed-size grid cells, visible focus, 44px coarse targets, and reduced-motion transitions disabled.

- [ ] **Step 6: Build, run, and capture Slice 2 evidence**

```powershell
npm run build:wysiwyg
vendor\bin\phpunit tests/Unit/Composer/EmojiCatalogTest.php tests/Integration/Core/AppComposerSuggestTest.php
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts --grep "emoji|colon|recents|axe"
Set-Location ../..
```

Add a named screenshot step in `composer-shell.spec.ts` for `82-composer-emoji.png` in both projects.

- [ ] **Step 7: Commit Task 6 and stop for Slice 2 review**

```powershell
git add -- public/assets/composer.js public/assets/app.css src/client/wysiwyg/milkdown-adapter.ts public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/composer-shell.spec.ts tests/browser/wysiwyg-composer.spec.ts docs/evidence/browser/desktop/82-composer-emoji.png docs/evidence/browser/mobile/82-composer-emoji.png
git commit -m "feat: add composer emoji picker"
```

---

## Slice 3 — Inbox re-enhancement and attach

### Task 7: Expose file-picker upload and enhance Inbox fragments safely

**Files:**

- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.js`
- Modify: `public/assets/app.css`
- Modify: `tests/browser/composer-shell.spec.ts`
- Modify: `tests/browser/community-inbox-theme.spec.ts`
- Modify: `tests/browser/gate-a.spec.ts`
- Refresh: `docs/evidence/browser/{desktop,mobile}/17-composer-upload.png`
- Refresh: `docs/evidence/browser/{desktop,mobile}/80-thread-study.png`

- [ ] **Step 1: Add red attach and Inbox tests**

Add browser coverage for:

- Attach images opens a hidden `multiple` file input whose `accept` is exactly `.png,.jpg,.jpeg,.webp,.gif`;
- choosing a fixture through Playwright’s file chooser uses `/upload`, inserts the same placeholder/final Markdown, and renders one in-box chip with 48px preview, filename/status, alt input, styled progress, Up/Down/Remove, and polite tray announcements;
- unsupported types cannot be chosen through the accept contract and existing drag/drop rejection remains unchanged;
- a dynamically loaded Inbox topic receives toolbar, draft indicator, pickers, Enter behavior, and attach exactly once;
- Study Quote inserts exactly once through the active source and rich adapters in an Inbox-loaded topic;
- Inbox submit intentionally navigates to the canonical `/t/...` route;
- with `rich_composer=false`, Inbox still loads the topic in-pane, no namespace error/fallback navigation occurs, the plain shell textarea is present, and no toolbar is present;
- a JavaScript-disabled 390×844 context logs in, follows a canonical topic link, sees the anonymity disclosure before and after checking `Anonymous`, submits one real reply through the shell, and reaches the posted reply anchor;
- inline axe scans cover the upload tray and expanded Inbox dock;
- reduced-motion mode has a static sending state and no popover/box transition animation.

- [ ] **Step 2: Run the red Slice 3 browser subset**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts community-inbox-theme.spec.ts --grep "attach|upload|Inbox|Quote|rich composer off|reduced motion"
Set-Location ../..
```

Expected: file chooser and dynamic composer enhancement cases fail.

- [ ] **Step 3: Reuse the existing upload path from a file input**

Create the hidden file input and Attach button in the start action slot. Refactor the current paste/drop loop into one `queueImageFiles(files)` function called by paste, drop, and `change`. Do not duplicate `uploadImage()`, purpose detection, placeholder token replacement, error cleanup, alt serialization, or reorder logic.

Set the exact accept list and `multiple=true`. Clear `input.value` after queueing so selecting the same file again fires `change`. Convert upload cards to compact chips in the existing server tray; retain all existing accessible controls and `aria-live=polite`. Style `<progress>` with existing accent/surface tokens for Chromium and Firefox pseudo-elements.

- [ ] **Step 4: Call `enhanceWithin` after Inbox installation**

Immediately after `readingContent.innerHTML = main.innerHTML` and `enhanceThreadViews(readingContent)`, add the exact guarded shape:

```javascript
if (window.RetroBoardsComposer && typeof window.RetroBoardsComposer.enhanceWithin === 'function') {
    window.RetroBoardsComposer.enhanceWithin(readingContent);
}
```

Do not move it outside the successful fragment branch. Do not call an unguarded namespace because `app.js` always loads and `composer.js` does not load under `rich_composer=false`.

- [ ] **Step 5: Update stale Inbox/mobile assertions and evidence journeys**

Replace `community-inbox-theme.spec.ts`’s legacy “nowrap + horizontal scroll” expectation with essential-actions + overflow/no-document-overflow. Keep its canonical/Inbox drawer, Quote, compact dock, no-JS reply, and axe regressions.

Update the existing Gate A upload capture to use the visible Attach path and new chip hooks. Ensure the thread-study capture occurs after dynamic enhancement has settled so `80-thread-study` records the new dock.

- [ ] **Step 6: Run the full Slice 3 checkpoint**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerTest.php
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts community-inbox-theme.spec.ts thread-view-study.spec.ts gate-a.spec.ts --grep "attach|upload|Inbox|Quote|rich composer off|composer|thread study"
Set-Location ../..
```

Expected: both viewport projects pass; kill-switch Inbox stays in-pane and plain.

- [ ] **Step 7: Commit Task 7 and stop for Slice 3 review**

```powershell
git add -- public/assets/composer.js public/assets/app.js public/assets/app.css tests/browser/composer-shell.spec.ts tests/browser/community-inbox-theme.spec.ts tests/browser/gate-a.spec.ts docs/evidence/browser/desktop/17-composer-upload.png docs/evidence/browser/mobile/17-composer-upload.png docs/evidence/browser/desktop/80-thread-study.png docs/evidence/browser/mobile/80-thread-study.png
git commit -m "feat: enhance inbox composer uploads"
```

---

## Closeout

### Task 8: Record product behavior, evidence, deferrals, and final verification

**Files:**

- Modify: `COMPOSER.md`
- Create: `docs/adr/0020-composer-shell-follow-ups.md`
- Modify: `tests/browser/package.json`
- Modify: `tests/browser/README.md`
- Modify: `docs/evidence/browser/README.md`
- Refresh: `docs/evidence/browser/{desktop,mobile}/26-slash-menu.png`
- Retain/verify all screenshots generated in Tasks 6–7

- [ ] **Step 1: Update the normative composer document**

Add `v0.7` to `COMPOSER.md`’s changelog covering:

- one shared shell across all four mount types/eight forms;
- engraved icon toolbar and narrow overflow superseding the prior sentence-case handoff detail;
- context-aware Enter default, always-send `Ctrl/Cmd+Enter`, touch behavior, and in-flight guard;
- explicit preview persistence/source-mode initial state;
- near-limit per-input counter;
- server-backed Unicode/custom emoji autocomplete and dialog/grid picker;
- visible image attach path and compact upload chips;
- guarded dynamic Inbox enhancement.

Update the §5 shortcut table:

- Send row: Enter outside list/quote/code on desktop; `Cmd/Ctrl+Enter` always.
- New line row: Shift+Enter; touch soft-Enter remains newline.
- Preview row: mark `Cmd/Ctrl+Shift+P` intentionally unbound because of the Firefox private-window collision; preview is toggled by its named button.

Read `USER.md` §4.5 after the edit. If it already says Enter-to-send default with button send on mobile, record no change; do not churn it.

- [ ] **Step 2: Create ADR 0020 as a deferral ledger, not a shipment claim**

Use the next ADR number and an engineering-deferral status. Record owner, destination, rationale, and risk/control for exactly:

| Follow-up | Required consequence/destination |
|---|---|
| Optimistic send/reconcile/rollback | First composer behavior follow-up; until then Inbox send performs accepted full navigation to canonical thread. |
| Global `r`/`c`/palette keys | Separate keyboard/navigation design; no global listeners in this slice. |
| `↑` edit-last-post | Separate authorization/selection behavior; current edit disclosures remain. |
| Replying-to quote chip | Study Quote Markdown insertion remains shipped; visual chip wrapper deferred. |

Do not describe these as completed, and do not move unrelated Phase 5 carryovers into the new ADR.

- [ ] **Step 3: Put the focused composer spec into the actual CI evidence command**

Add `composer-shell.spec.ts` at the end of `tests/browser/package.json`’s `evidence` script. Its feature/preference override helpers must restore the seeded baseline in `afterEach`, so the shared evidence run remains order-independent. Update both READMEs with:

- `17-composer-upload` now exercises the visible file picker/chips;
- `26-slash-menu` is floating and non-reflowing;
- `80-thread-study` includes the new reply shell;
- `82-composer-emoji` covers the dialog/grid;
- the focused spec includes inline axe, no-JS, reduced-motion, rich/source Enter, attach, and kill-switch Inbox cases.

- [ ] **Step 4: Run PHP syntax and focused tests**

```powershell
$phpFiles = @(
  'templates/partials/composer_shell.php',
  'templates/partials/composer.php',
  'templates/partials/new_thread_form.php',
  'templates/compose.php',
  'templates/dm/show.php',
  'templates/dm/new.php',
  'templates/partials/dm_compose_fields.php',
  'templates/partials/dm_list.php',
  'templates/partials/post_toolbar.php',
  'src/Support/EmojiCatalog.php',
  'src/Support/PreferenceSchema.php',
  'src/Service/PreferenceService.php',
  'src/Service/ComposerSuggestionService.php',
  'src/Controller/ComposerController.php',
  'src/Core/App.php',
  'tests/Unit/Preferences/PreferenceSchemaTest.php',
  'tests/Unit/Composer/EmojiCatalogTest.php',
  'tests/Integration/Core/AppUserPreferencesTest.php',
  'tests/Integration/Core/AppComposerShellTest.php',
  'tests/Integration/Core/AppComposerSuggestTest.php',
  'tests/Integration/Core/AppComposerTest.php'
)
foreach ($file in $phpFiles) { php -l $file; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }

vendor\bin\phpunit tests/Unit/Preferences/PreferenceSchemaTest.php tests/Unit/Composer/EmojiCatalogTest.php tests/Integration/Core/AppUserPreferencesTest.php tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppComposerTest.php
```

Expected: every syntax check and focused suite passes without warning/risky output.

- [ ] **Step 5: Verify generated assets are reproducible**

```powershell
npm run check:wysiwyg
```

Expected: Vite rebuilds the committed bundle and `git diff --exit-code` reports no generated drift.

- [ ] **Step 6: Run focused browser acceptance and standard evidence**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test composer-shell.spec.ts wysiwyg-composer.spec.ts community-inbox-theme.spec.ts thread-view-study.spec.ts
npm run evidence
Set-Location ../..
```

Expected: desktop/mobile, no-JS, axe, reduced-motion, emoji, attach, Quote, rich-off Inbox, and named screenshot runs pass. Review regenerated PNGs visually in both themes/viewport projects; do not accept a test-only selector pass when the layout clips or overflows.

- [ ] **Step 7: Run the repository completion gate**

Ensure the test database is reachable, then run:

```powershell
composer test
git diff --check
git status --short
```

Review the aggregate branch diff and confirm:

- no file under `database/migrations/` changed;
- no route registration changed;
- no `FeatureFlags::DEFAULTS` entry was added or changed;
- no submission action, Markdown sanitizer, draft key derivation, or upload whitelist changed;
- no inline script/style or user-controlled `innerHTML` was introduced;
- only the eight `.composer-input` forms use the new shell;
- unrelated pre-existing dirty files are untouched.

- [ ] **Step 8: Commit documentation/evidence closeout**

```powershell
git add -- COMPOSER.md docs/adr/0020-composer-shell-follow-ups.md tests/browser/package.json tests/browser/README.md docs/evidence/browser/README.md docs/evidence/browser/desktop/17-composer-upload.png docs/evidence/browser/mobile/17-composer-upload.png docs/evidence/browser/desktop/26-slash-menu.png docs/evidence/browser/mobile/26-slash-menu.png docs/evidence/browser/desktop/80-thread-study.png docs/evidence/browser/mobile/80-thread-study.png docs/evidence/browser/desktop/82-composer-emoji.png docs/evidence/browser/mobile/82-composer-emoji.png
git commit -m "docs: record composer shell evidence and follow-ups"
```

- [ ] **Step 9: Capture final evidence in the handoff**

Report exact PHPUnit test/assertion counts, Playwright pass/skip counts, `npm run check:wysiwyg`, `git diff --check`, and any environment-only limitation. Do not claim completion if the standard `npm run evidence` command or full `composer test` is red; a schema-only or screenshot-only result is not completion evidence.
