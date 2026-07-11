# Direct Messages Responsive and Composer Polish Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make RetroBoards direct messages readable at common desktop widths and reply-first on mobile while simplifying only DM formatting controls and correcting shared composer semantics.

**Architecture:** Keep the existing server-rendered PHP templates and native no-JavaScript fallbacks. Use the existing `data-route` and `data-composer-context` hooks to scope responsive CSS and formatting disclosure to DMs; refactor shared suggestion-menu state away from invalid textarea ARIA attributes without changing picker behavior.

**Tech Stack:** PHP 8.2+ templates and helpers, vanilla ES5-compatible JavaScript, token-based CSS, PHPUnit, Playwright 1.61.1, Axe Core 4.12.1.

## Global Constraints

- Preserve all DM routes, controllers, validation, draft APIs, CSRF fields, eligibility rules, rate limits, feature flags, and message persistence.
- Preserve strict CSP: no inline `<style>`, `style`, `<script>`, event handler, CDN, React, or new build step.
- Preserve progressive enhancement: every form and action remains usable without JavaScript.
- Reuse existing colors, fonts, tokens, icons, and private-counsel copy.
- Formatting disclosure is DM-only; topic, thread-reply, edit, and administrative composers retain the always-visible toolbar.
- The shared textarea semantic correction must keep slash-command and reference pickers keyboard-operable in source and WYSIWYG modes.
- WYSIWYG is default-on and must be tested in a DM reply; the list-pane compose dialog remains `data-no-wysiwyg`.
- Important DM controls expose at least a 44×44px interactive box.
- Do not modify or stage the existing unrelated files `docs/superpowers/plans/2026-07-09-phase5-gate-a-default-on.md` or `docs/superpowers/plans/2026-07-09-phase5-p5-16-closeout-evidence.md`.

---

## File Structure

- `src/Support/helpers.php`: deterministic compact UTC timestamp helper used by DM list rows.
- `templates/dm/{index,new,show}.php`: stamp the existing layout route and set the rail toggle's closed default.
- `templates/partials/dm_list.php`: semantic compact timestamp markup.
- `public/assets/composer.js`: maxlength-aware counter, DM-only formatting disclosure, and suggestion-state refactor.
- `public/assets/app.js`: all-width rail overlay state and focus restoration.
- `public/assets/app.css`: two-column DM shell, overlay rail, bounded mobile reading room, disclosure/counter styling, and touch targets.
- `COMPOSER.md`: DM-specific exception to the shared mobile toolbar rule.
- `tests/Unit/Support/HelpersTest.php`: compact timestamp behavior.
- `tests/Integration/Core/AppDirectMessageTest.php`: route, timestamp, and no-JS markup contracts.
- `tests/browser/{dm-reimagine,a11y,gate-a,wysiwyg-composer}.spec.ts`: responsive, focus, semantics, WYSIWYG, and keyboard regression coverage.

---

### Task 1: Compact timestamps and DM route contract

**Files:**
- Create: `tests/Unit/Support/HelpersTest.php`
- Modify: `tests/Integration/Core/AppDirectMessageTest.php:346-361`
- Modify: `src/Support/helpers.php:64-86`
- Modify: `templates/dm/index.php:1-3`
- Modify: `templates/dm/new.php:1-3`
- Modify: `templates/dm/show.php:11-14`
- Modify: `templates/partials/dm_list.php:70-86`

**Interfaces:**
- Produces: `compact_datetime(?string $utcDateTime): string`, returning `M j · H:i` in UTC or `''` for null, empty, or invalid input.
- Produces: `body[data-route="messages"]` on `/messages`, `/messages/new`, and `/messages/{id}`.
- Produces: `<time class="dm-time" datetime="..." aria-label="full timestamp" title="full timestamp">compact timestamp</time>`.

- [ ] **Step 1: Write the failing unit tests**

Create `tests/Unit/Support/HelpersTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    public function testCompactDatetimeUsesDeterministicUtcFormat(): void
    {
        self::assertSame('Jul 11 · 02:54', compact_datetime('2026-07-11 02:54:59'));
    }

    public function testCompactDatetimeRejectsMissingOrInvalidInput(): void
    {
        self::assertSame('', compact_datetime(null));
        self::assertSame('', compact_datetime(''));
        self::assertSame('', compact_datetime('not-a-date'));
    }
}
```

- [ ] **Step 2: Add failing integration assertions**

Extend `test_messages_index_renders_search_form_and_compose_dialog()` and add one focused route test in `AppDirectMessageTest.php`:

```php
public function test_message_routes_stamp_dm_route_and_list_uses_semantic_compact_time(): void
{
    $me = $this->established(['username' => 'route_me']);
    $other = $this->established(['username' => 'route_other', 'display_name' => 'Route Other']);
    $convId = (int) $this->dm()->start($this->userEntity($other), (int) $me['id'], 'Route proof.')['conversation_id'];
    $this->actingAs($me);

    foreach (['/messages', '/messages/new', '/messages/' . $convId] as $path) {
        $res = $this->get($path);
        $this->assertStatus(200, $res);
        self::assertStringContainsString('data-route="messages"', $res->body());
    }

    $index = $this->get('/messages');
    self::assertMatchesRegularExpression(
        '/<time class="dm-time" datetime="[^\"]+" aria-label="[^\"]+ UTC" title="[^\"]+ UTC">[A-Z][a-z]{2} \d{1,2} · \d{2}:\d{2}<\/time>/',
        $index->body(),
    );
}
```

- [ ] **Step 3: Run the focused tests and verify RED**

Run:

```bash
vendor/bin/phpunit tests/Unit/Support/HelpersTest.php tests/Integration/Core/AppDirectMessageTest.php --filter 'CompactDatetime|message_routes_stamp'
```

Expected: `compact_datetime()` is undefined and the route/time markup assertions fail.

- [ ] **Step 4: Implement `compact_datetime()`**

Add after `human_datetime()` in `src/Support/helpers.php`:

```php
if (!function_exists('compact_datetime')) {
    function compact_datetime(?string $utcDateTime): string
    {
        if ($utcDateTime === null || $utcDateTime === '') {
            return '';
        }
        $ts = strtotime($utcDateTime . ' UTC');
        return $ts === false ? '' : gmdate('M j · H:i', $ts);
    }
}
```

- [ ] **Step 5: Stamp the route block in all DM templates**

Add `$this->section('route', 'messages');` beside the title section in `templates/dm/index.php`, `templates/dm/new.php`, and `templates/dm/show.php`.

- [ ] **Step 6: Replace list timestamp markup**

Inside the conversation loop in `templates/partials/dm_list.php`, compute and render:

```php
<?php
$lastMessageAt = (string) ($c['last_message_at'] ?? '');
$lastMessageTs = $lastMessageAt !== '' ? strtotime($lastMessageAt . ' UTC') : false;
$lastMessageIso = $lastMessageTs === false ? '' : gmdate('Y-m-d\TH:i:s\Z', $lastMessageTs);
$lastMessageFull = human_datetime($lastMessageAt);
?>
<time class="dm-time"
      datetime="<?= $e($lastMessageIso) ?>"
      aria-label="<?= $e($lastMessageFull) ?>"
      title="<?= $e($lastMessageFull) ?>"><?= $e(compact_datetime($lastMessageAt)) ?></time>
```

- [ ] **Step 7: Run focused and full DM PHP tests**

Run:

```bash
vendor/bin/phpunit tests/Unit/Support/HelpersTest.php tests/Integration/Core/AppDirectMessageTest.php
```

Expected: all tests pass with no warnings or risky tests.

- [ ] **Step 8: Commit Task 1**

```bash
git add src/Support/helpers.php templates/dm/index.php templates/dm/new.php templates/dm/show.php templates/partials/dm_list.php tests/Unit/Support/HelpersTest.php tests/Integration/Core/AppDirectMessageTest.php
git commit -m "feat(dm): add compact message timestamps"
```

---

### Task 2: Correct textarea suggestion semantics

**Files:**
- Modify: `tests/browser/a11y.spec.ts:118-129,447-480`
- Modify: `tests/browser/gate-a.spec.ts:692-725`
- Modify: `tests/browser/wysiwyg-composer.spec.ts:125-150`
- Modify: `public/assets/composer.js:1090-1127,1263-1277,1417-1444`

**Interfaces:**
- Consumes: native textarea implicit `textbox` semantics.
- Produces: visible `role=listbox` popups with `role=option` children and `aria-activedescendant` keyboard state, without textarea `role=combobox` or `aria-expanded`.
- Produces: slash readiness from the existing closure `ready` and `config`; reference open state from `menu.hidden`.

- [ ] **Step 1: Add an explicit Axe rule helper and failing assertion**

Add to `tests/browser/a11y.spec.ts`:

```ts
async function expectNoComposerRoleOrAttributeViolations(page: Page): Promise<void> {
  const results = await new AxeBuilder({ page })
    .include('form.composer')
    .withRules(['aria-allowed-role', 'aria-allowed-attr'])
    .analyze();
  expect(results.violations.map((violation) => violation.id)).toEqual([]);
}
```

In the slash-picker test, after the listbox opens, replace `aria-expanded` assertions with:

```ts
await expect(body).not.toHaveAttribute('role', 'combobox');
await expect(body).not.toHaveAttribute('aria-expanded', /.+/);
await expectNoComposerRoleOrAttributeViolations(page);
```

Retain the existing visible-listbox, selected-option, active-descendant, ArrowDown, and Escape assertions.

- [ ] **Step 2: Update the contract tests to the desired semantics**

In `gate-a.spec.ts` and `wysiwyg-composer.spec.ts`, replace every textarea `aria-expanded` assertion with visible/hidden listbox assertions plus:

```ts
await expect(textarea).not.toHaveAttribute('role', 'combobox');
await expect(textarea).not.toHaveAttribute('aria-expanded', /.+/);
```

Keep `aria-activedescendant` while a list option is active and verify it is absent after Escape or selection.

- [ ] **Step 3: Run the focused browser tests and verify RED**

Prepare the isolated browser database and run:

```bash
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish bash prepare.sh)
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish E2E_PORT=8032 npx playwright test a11y.spec.ts gate-a.spec.ts wysiwyg-composer.spec.ts --project=desktop --grep 'slash|reference picker|textarea composer')
```

Expected: assertions fail because textareas still carry `role="combobox"` and `aria-expanded`; the explicit Axe scan reports `aria-allowed-role`.

- [ ] **Step 4: Refactor slash-menu state**

In `wireSlashMenu()` replace role-based readiness and expanded-state writes with:

```js
function pickerReady() { return ready && config !== null; }
function setPopupState(open) {
    if (!pickerReady()) { return; }
    if (open) { ta.setAttribute('aria-controls', menuId); }
    if (!open) { ta.removeAttribute('aria-activedescendant'); }
}
function openMenu() { menu.hidden = false; setPopupState(true); }
function hide() {
    activeState = null;
    options = [];
    activeIndex = -1;
    lastRenderKey = null;
    menu.hidden = true;
    menu.innerHTML = '';
    setPopupState(false);
}
```

When configuration loads, keep `aria-controls`, `aria-haspopup`, and `aria-autocomplete`, but delete the `role` and `aria-expanded` assignments.

- [ ] **Step 5: Refactor reference-picker state**

Replace role/expanded writes with popup ownership and `menu.hidden`:

```js
function ownsPopup() { return ta.getAttribute('aria-controls') === menuId; }
function setPopupState(open) {
    if (open) { ta.setAttribute('aria-controls', menuId); }
    if (!open && ownsPopup()) { ta.removeAttribute('aria-activedescendant'); }
}
function openMenu() {
    menu.hidden = false;
    setPopupState(true);
}
function hide() {
    var wasOpen = !menu.hidden;
    requestSeq++;
    activeState = null;
    options = [];
    activeIndex = -1;
    lastRenderKey = null;
    menu.hidden = true;
    menu.innerHTML = '';
    if (wasOpen) { setPopupState(false); }
}
```

Keep `aria-controls`, `aria-haspopup`, and `aria-autocomplete`; delete all textarea `role="combobox"` and `aria-expanded` assignments.

- [ ] **Step 6: Re-run focused browser tests and verify GREEN**

Run the Step 3 Playwright command again.

Expected: keyboard picker tests pass; explicit role/attribute Axe scan has zero violations.

- [ ] **Step 7: Commit Task 2**

```bash
git add public/assets/composer.js tests/browser/a11y.spec.ts tests/browser/gate-a.spec.ts tests/browser/wysiwyg-composer.spec.ts
git commit -m "fix(composer): use valid textarea suggestion semantics"
```

---

### Task 3: DM-only formatting disclosure and accurate counter

**Files:**
- Modify: `tests/browser/dm-reimagine.spec.ts:65-142`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `public/assets/composer.js:198-249,1635-1675`
- Modify: `public/assets/app.css:3327-3528`
- Modify: `COMPOSER.md:114-127,270-275`

**Interfaces:**
- Produces: `.composer-formatting-toggle[aria-expanded][aria-controls]` only for `form[data-composer-context="dm"]`.
- Produces: collapsed `.composer-toolbar` on DM forms; unchanged visible toolbar elsewhere.
- Produces: counter denominator from positive textarea `maxlength`, falling back to `BODY_MAX`.

- [ ] **Step 1: Add failing source-mode DM disclosure assertions**

In the first `dm-reimagine.spec.ts` test, after opening the conversation, assert:

```ts
const replyForm = alice.locator('.dm-composer');
const formatting = replyForm.getByRole('button', { name: 'Formatting' });
const toolbar = replyForm.locator('.composer-toolbar');
await expect(formatting).toHaveAttribute('aria-expanded', 'false');
await expect(toolbar).toBeHidden();
await formatting.click();
await expect(formatting).toHaveAttribute('aria-expanded', 'true');
await expect(toolbar).toBeVisible();
await formatting.click();
await expect(formatting).toBeFocused();
await expect(toolbar).toBeHidden();
await expect(replyForm.locator('.composer-count')).toHaveText('0 / 5000');
```

Open the list-pane new-message dialog and assert the same collapsed disclosure. Also visit a topic composer and assert its toolbar is visible and no Formatting button exists.

- [ ] **Step 2: Add a failing default-on WYSIWYG DM test**

Add to `wysiwyg-composer.spec.ts`:

```ts
test('default-on DM reply keeps WYSIWYG behind the DM formatting disclosure', async ({ page }) => {
  setWysiwygComposer(null);
  await login(page, 'bob@retro.test');
  await page.goto('/messages/new');
  const startForm = page.locator('form[data-composer-context="dm"]');
  await startForm.locator('input[name="to"]').fill('alice');
  await startForm.locator('.wysiwyg-composer .ProseMirror').fill('WYSIWYG DM start');
  await startForm.getByRole('button', { name: 'Send message' }).click();
  await page.waitForURL(/\/messages\/\d+/);

  const reply = page.locator('.dm-composer');
  await expect(reply.locator('.wysiwyg-composer .ProseMirror')).toBeVisible();
  await expect(reply.locator('.composer-toolbar')).toBeHidden();
  await reply.getByRole('button', { name: 'Formatting' }).click();
  await expect(reply.locator('.composer-toolbar')).toBeVisible();
  await expect(reply.getByRole('button', { name: 'Insert Bold' })).toBeVisible();
});
```

- [ ] **Step 3: Run focused Playwright tests and verify RED**

```bash
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish E2E_PORT=8032 npx playwright test dm-reimagine.spec.ts wysiwyg-composer.spec.ts --project=desktop --grep 'reading room|default-on DM reply')
```

Expected: Formatting controls are absent, DM toolbars are visible, and the counter reads `0 / 20000`.

- [ ] **Step 4: Return the toolbar and add a DM disclosure builder**

Make `buildToolbar(form, ta)` return `bar`, then add:

```js
var dmToolbarSeq = 0;
function buildDmToolbarDisclosure(form, bar) {
    if (form.getAttribute('data-composer-context') !== 'dm') { return; }
    var button = document.createElement('button');
    var id = 'dm-composer-toolbar-' + (++dmToolbarSeq);
    button.type = 'button';
    button.className = 'composer-formatting-toggle';
    button.textContent = 'Formatting';
    button.setAttribute('aria-controls', id);
    button.setAttribute('aria-expanded', 'false');
    bar.id = id;
    bar.hidden = true;
    button.addEventListener('click', function () {
        var open = button.getAttribute('aria-expanded') === 'true';
        button.setAttribute('aria-expanded', open ? 'false' : 'true');
        bar.hidden = open;
        if (open) { button.focus(); }
    });
    bar.parentNode.insertBefore(button, bar);
}
```

In the form enhancer:

```js
var toolbar = buildToolbar(form, ta);
buildDmToolbarDisclosure(form, toolbar);
```

- [ ] **Step 5: Make the counter respect `maxlength` and empty state**

Replace `buildCounter()` with:

```js
function buildCounter(ta) {
    var c = document.createElement('div');
    var configured = parseInt(ta.getAttribute('maxlength') || '', 10);
    var limit = configured > 0 ? configured : BODY_MAX;
    c.className = 'composer-count';
    function update() {
        var n = ta.value.length;
        c.textContent = n + ' / ' + limit;
        c.classList.toggle('over', n > limit);
        c.classList.toggle('is-empty', n === 0);
    }
    ta.addEventListener('input', update);
    update();
    ta.parentNode.appendChild(c);
}
```

- [ ] **Step 6: Add DM-only disclosure, counter, and draft styling**

Add to the DM composer section of `app.css`:

```css
form[data-composer-context="dm"] .composer-formatting-toggle {
    align-self: flex-start;
    min-height: 44px;
    padding: 6px 10px;
    border: 1px solid var(--border-hair);
    border-radius: var(--radius-md);
    background: transparent;
    color: var(--text-muted);
    font: inherit;
    cursor: pointer;
}
form[data-composer-context="dm"] .composer-formatting-toggle:hover,
form[data-composer-context="dm"] .composer-formatting-toggle[aria-expanded="true"] {
    background: var(--surface-sunken);
    color: var(--brand);
}
form[data-composer-context="dm"] .composer-toolbar[hidden] { display: none; }
form[data-composer-context="dm"] .composer-count.is-empty { visibility: hidden; height: 0; margin: 0; }
form[data-composer-context="dm"] .composer-discard {
    align-self: flex-start;
    min-height: 44px;
    padding: 4px 0;
    border: 0;
    background: transparent;
    box-shadow: none;
}
```

- [ ] **Step 7: Document the DM exception**

Add to `COMPOSER.md` §§4 and 13:

```md
- **Direct-message exception:** DM new-message and reply forms begin with one labelled `Formatting` disclosure. Expanding it reveals the complete shared toolbar; other composer contexts keep the responsive essential-set + overflow pattern.
```

- [ ] **Step 8: Re-run focused tests and commit**

Run the Step 3 Playwright command. Expected: source and WYSIWYG DM tests pass; topic toolbar remains unchanged.

```bash
git add public/assets/composer.js public/assets/app.css COMPOSER.md tests/browser/dm-reimagine.spec.ts tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat(dm): collapse formatting controls on demand"
```

---

### Task 4: All-width details overlay with focus restoration

**Files:**
- Modify: `tests/browser/dm-reimagine.spec.ts:65-200`
- Modify: `templates/dm/show.php:42-45`
- Modify: `public/assets/app.css:2570-2597,3034-3052,4605-4657`
- Modify: `public/assets/app.js:310-400`

**Interfaces:**
- Produces: two-column `.dm-shell.has-rail` at desktop widths.
- Produces: `#dm-rail` as the visibility source of truth at every width.
- Produces: closed initial toggle state and focus return to `[data-rail-toggle]` after Escape, scrim, or close.

- [ ] **Step 1: Repair the stale browser baseline assertion**

Replace the nonexistent-copy assertion:

```ts
await expect(alice.getByText('Beginning of your counsel')).toBeVisible();
```

with the shipped privacy cue:

```ts
await expect(alice.getByText('Private — only those named here can read')).toBeVisible();
```

Run the current DM spec against the isolated database and record that the stale-copy failure is gone before adding new expectations.

- [ ] **Step 2: Replace wide-column assertions with failing overlay assertions**

At a 1440×900 context, assert:

```ts
const rail = wide.locator('.dm-inforail');
const toggle = wide.locator('[data-rail-toggle]');
const thread = wide.locator('.dm-threadpane');
const before = await thread.boundingBox();
await expect(toggle).toHaveAttribute('aria-expanded', 'false');
await expect(rail).not.toBeInViewport();
await toggle.click();
await expect(toggle).toHaveAttribute('aria-expanded', 'true');
await expect(rail).toBeInViewport();
await expect(wide.locator('[data-rail-scrim]')).toBeVisible();
const after = await thread.boundingBox();
expect(after?.width).toBe(before?.width);
await wide.keyboard.press('Escape');
await expect(rail).not.toBeInViewport();
await expect(toggle).toBeFocused();
```

Delete persistence assertions for `rb-dm-rail-collapsed`. Retain no-JS `href="#dm-rail"` coverage.

- [ ] **Step 3: Run the DM browser spec and verify RED**

```bash
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish E2E_PORT=8032 npx playwright test dm-reimagine.spec.ts --project=desktop)
```

Expected: the rail is initially visible at 1440px, the thread width changes, and close does not restore focus.

- [ ] **Step 4: Set closed server markup**

In `templates/dm/show.php`, change the rail button to:

```php
<button type="button" class="dm-iconbtn" data-rail-toggle aria-controls="dm-rail" aria-expanded="false" aria-label="<?= $e($railLabel) ?>">
```

- [ ] **Step 5: Convert rail CSS to an all-width overlay**

Use two DM grid columns and move the current narrow drawer rules out of the `max-width:1399px` media query:

```css
.dm-shell.has-rail { grid-template-columns: minmax(280px, 360px) minmax(0, 1fr); }
.dm-inforail {
    position: fixed;
    top: var(--topbar-h);
    right: 0;
    bottom: 0;
    width: min(340px, 92vw);
    z-index: 55;
    box-shadow: var(--shadow-xl);
    transform: translateX(101%);
}
.dm-inforail:target { transform: translateX(0); }
.dm-inforail:target + .dm-rail-scrim {
    display: block;
    position: fixed;
    inset: var(--topbar-h) 0 0 0;
    z-index: 54;
    background: rgba(22, 29, 36, .42);
}
@media (prefers-reduced-motion: no-preference) {
    .dm-inforail { transition: transform 240ms var(--ease-calm); }
}
```

Delete `.rail-hidden`, the DM grid-column transition, and the 1399px-only drawer copies. Keep the ≤900px full-width rail override.

- [ ] **Step 6: Simplify rail JavaScript and restore focus**

Replace the wide/narrow/localStorage branch with:

```js
var railIsOpen = function () { return window.location.hash === '#dm-rail'; };
var openRail = function () {
    if (!railIsOpen()) { window.location.replace('#dm-rail'); }
    setRailButton(true);
};
var closeRail = function (restoreFocus) {
    if (railIsOpen()) {
        window.location.replace(window.location.pathname + window.location.search);
    }
    setRailButton(false);
    if (restoreFocus !== false) { railToggle.focus(); }
};

setRailButton(railIsOpen());
railToggle.addEventListener('click', function () {
    if (railIsOpen()) { closeRail(true); } else { openRail(); }
});
```

Have scrim, close, and Escape call `closeRail(true)`. The close-link handler must call `preventDefault()` before `closeRail(true)` so its `href="#"` fallback does not run after the enhanced handler. Remove `RAIL_KEY`, `railNarrow()`, localStorage reads/writes, resize synchronization, and the width gate on Escape. Keep hashchange synchronization and menu opener behavior.

- [ ] **Step 7: Re-run DM browser tests and commit**

Run the Step 3 command. Expected: overlay, no-width-change, no-JS, Escape, scrim, and focus assertions pass.

```bash
git add templates/dm/show.php public/assets/app.css public/assets/app.js tests/browser/dm-reimagine.spec.ts
git commit -m "feat(dm): make details an accessible overlay"
```

---

### Task 5: Bounded mobile reading room and touch targets

**Files:**
- Modify: `tests/browser/dm-reimagine.spec.ts`
- Modify: `public/assets/app.css:723-734,2641-2666,2930-2988,3226-3245,3327-3528,4646-4657`

**Interfaces:**
- Consumes: `body[data-route="messages"]` from Task 1.
- Produces: enhanced mobile body/app shell that uses natural top-bar height plus a flexed remaining viewport.
- Produces: page-level `scrollHeight <= innerHeight + 1` for the open conversation; `.dm-scroll` owns message scrolling.
- Produces: internally scrolling `/messages/new` form pane.

- [ ] **Step 1: Add failing explicit mobile-context tests**

In `dm-reimagine.spec.ts`, create 390×844 and 320×568 contexts. For an open conversation, assert:

```ts
await expect(mobile.locator('.topbar-search')).toBeHidden();
const viewportState = await mobile.evaluate(() => ({
  innerHeight,
  pageHeight: document.documentElement.scrollHeight,
  composerBottom: document.querySelector('.dm-composer')!.getBoundingClientRect().bottom,
  scrollOverflow: getComputedStyle(document.querySelector('.dm-scroll')!).overflowY,
}));
expect(viewportState.pageHeight).toBeLessThanOrEqual(viewportState.innerHeight + 1);
expect(viewportState.composerBottom).toBeLessThanOrEqual(viewportState.innerHeight + 1);
expect(viewportState.scrollOverflow).toBe('auto');
```

On `/messages/new`, assert `.dm-compose` has `overflow-y:auto`, the document does not scroll, and scrolling the pane can bring the submit button fully into view.

- [ ] **Step 2: Add failing touch-target assertions**

For `.dm-new-btn`, `.dm-back`, `.dm-iconbtn`, `.dm-dialog-close`, `.dm-dotbtn`, and `.dm-send`, collect bounding boxes and assert:

```ts
expect(box?.width, `${selector} width`).toBeGreaterThanOrEqual(44);
expect(box?.height, `${selector} height`).toBeGreaterThanOrEqual(44);
```

- [ ] **Step 3: Run the mobile DM tests and verify RED**

```bash
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish E2E_PORT=8032 npx playwright test dm-reimagine.spec.ts --project=desktop --grep 'mobile|touch|reading room')
```

Expected: top-bar search is visible, page scroll exceeds the viewport, composer is below the viewport, and several targets are below 44px.

- [ ] **Step 4: Add the enhanced mobile flex viewport**

Add under the ≤900px DM media query:

```css
@media (max-width: 900px) {
    body[data-route="messages"] .topbar-search { display: none; }

    .has-js body[data-route="messages"] {
        height: 100dvh;
        min-height: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    .has-js body[data-route="messages"] > .topbar,
    .has-js body[data-route="messages"] > .site-announcement { flex: 0 0 auto; }
    .has-js body[data-route="messages"] > .app-shell {
        flex: 1 1 auto;
        min-height: 0;
        width: 100%;
        overflow: hidden;
    }
    .has-js body[data-route="messages"] .main {
        height: 100%;
        min-height: 0;
        overflow: hidden;
        padding-bottom: 0;
    }
    .has-js body[data-route="messages"] .dm-shell,
    .has-js body[data-route="messages"] .dm-shell.has-rail {
        height: 100%;
        min-height: 0;
        margin-bottom: 0;
    }
    .has-js body[data-route="messages"] .dm-threadpane {
        height: 100%;
        min-height: 0;
        overflow: hidden;
    }
    .has-js body[data-route="messages"] .dm-compose { overflow-y: auto; min-height: 0; }
}
```

Keep these height constraints under `.has-js`; the no-JS mobile page continues to scroll normally with its in-flow sidebar.

- [ ] **Step 5: Raise DM interactive boxes to 44px**

Set `width`/`height` or `min-width`/`min-height` to 44px for `.dm-new-btn`, `.dm-back`, `.dm-iconbtn`, `.dm-dialog-close`, `.dm-dotbtn`, and `.dm-send`. Keep icon SVG sizes unchanged. Ensure the message-action gutter grows to fit the 44px button without covering body text.

- [ ] **Step 6: Re-run mobile browser tests and commit**

Run the Step 3 command at both explicit contexts. Expected: no page-level scroll, visible composer, internally scrolling new-message pane, hidden global search, and all target boxes ≥44×44.

```bash
git add public/assets/app.css tests/browser/dm-reimagine.spec.ts
git commit -m "feat(dm): keep mobile replies in the viewport"
```

---

### Task 6: Full regression, visual evidence, and cleanup verification

**Files:**
- Modify only if a regression is found: files already listed in Tasks 1-5.
- Create: `docs/evidence/dm-responsive-composer-polish/*.png` as the committed UI evidence required by `DESIGN.md` §13.

**Interfaces:**
- Verifies every requirement in `docs/superpowers/specs/2026-07-11-dm-responsive-composer-polish-design.md`.

- [ ] **Step 1: Run focused PHP tests**

```bash
vendor/bin/phpunit tests/Unit/Support/HelpersTest.php tests/Integration/Core/AppDirectMessageTest.php tests/Integration/Core/AppImladrisFidelityTest.php tests/Integration/Core/AppImladrisFidelityHighImpactTest.php
```

Expected: zero failures, warnings, risky tests, or unexpected output.

- [ ] **Step 2: Run the full PHP suite**

```bash
composer test
```

Expected: zero failures.

- [ ] **Step 3: Run the complete affected browser set**

```bash
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish bash prepare.sh)
(cd tests/browser && DB_DATABASE=retroboards_e2e_dm_polish E2E_PORT=8032 npx playwright test dm-reimagine.spec.ts a11y.spec.ts gate-a.spec.ts wysiwyg-composer.spec.ts)
```

Expected: all affected desktop/mobile projects pass; only explicitly documented unrelated skips remain.

- [ ] **Step 4: Capture and inspect fresh screenshots**

Capture the list, new-message dialog, conversation, opened rail, collapsed and expanded formatting, and mobile reply at 1440×900, 390×844, and 320×568 into `docs/evidence/dm-responsive-composer-polish/`. Inspect each saved PNG for clipping, overlays, stale loading, unreadable controls, and wrong state before accepting it. Stage the accepted evidence with the implementation; reject and recapture any invalid image.

- [ ] **Step 5: Verify dead state and scope boundaries**

Run:

```bash
rg -n "rail-hidden|rb-dm-rail-collapsed|function railNarrow|setAttribute\('role', 'combobox'\)|setAttribute\('aria-expanded'" public/assets/app.css public/assets/app.js public/assets/composer.js
git diff --check
git status --short
```

Expected:

- no rail-column state remains;
- `aria-expanded` remains only on valid controls such as the rail and Formatting buttons, never the textarea;
- no whitespace errors;
- unrelated pre-existing plan files remain unstaged and unmodified by this work.

- [ ] **Step 6: Run final rendered smoke verification**

Verify page identity, nonblank DOM, no framework overlay, no console warnings/errors, screenshot evidence, compose focus, Formatting disclosure focus, reply submission, rail focus return, and mobile drawer behavior.

- [ ] **Step 7: Commit any verification-only corrections**

If verification required source changes, add a focused regression test first, observe RED, make the minimal correction, rerun the affected and full checks, then commit only those files:

```bash
git commit -m "fix(dm): address responsive polish regression"
```

If no corrections were required, do not create an empty commit.
