# Community Inbox Theme Gap Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the approved Imladris theme gaps so authenticated members land in their Community Inbox, mobile Inbox navigation works as a real list/reading state machine, mobile chrome stays compact, and the existing reply composer is immediately reachable in a docked conversation viewport.

**Architecture:** Keep RetroBoards server-rendered and progressively enhanced. A shared authenticated-home helper supplies `/inbox` only when an auth flow has no safe explicit destination. The Inbox keeps canonical topic anchors and adds a stable reading-content wrapper plus vanilla-JS state classes for narrow screens. The canonical thread template and fetched Inbox thread share one conversation-viewport structure: a scroll region followed by the existing reply/join surface as a dock. All presentation comes from the existing Imladris tokens, icon partial, `app.css`, and `app.js`.

**Tech Stack:** PHP 8.2 controllers/templates, vanilla JavaScript, vanilla CSS, PHPUnit 11 integration tests, Playwright 1.61 Chromium checks with `@axe-core/playwright`.

## Global Constraints

- **Approved design is the source of truth:** `docs/superpowers/specs/2026-07-12-community-inbox-theme-gap-closure-design.md`.
- **No visual reinvention:** use the current Imladris tokens and the responsive state pattern already documented in `docs/design-system/imladris/ui_kits/retroboards/kit.css`; do not add colors, typefaces, radii, icon families, or decorative assets.
- **Progressive enhancement:** topic links and forms must remain fully functional with JavaScript disabled. JavaScript may enhance canonical anchors; it must not become the only navigation or posting path.
- **Strict CSP:** no inline scripts, event handlers, `style` attributes, CDN assets, React, or build-time frontend dependency.
- **Auth safety:** a supplied safe same-origin `next`, including explicit `/`, still wins. Empty, external, and protocol-relative destinations fall back to `/inbox`. Logout still returns `/`.
- **Posting contracts:** retain the reply action, CSRF field, idempotency key, draft metadata, textarea name, WYSIWYG hooks, old input, error text, anonymous-posting value, and submit semantics.
- **Responsive breakpoint:** use the production theme's `860px` breakpoint consistently. Desktop three-pane behavior remains unchanged.
- **Accessibility:** 44px mobile targets, visible Back label, focus into the opened topic, focus restoration to its list link, hidden panes removed from keyboard navigation, and motion disabled under `prefers-reduced-motion`.
- **No schema work:** no migrations, persistent UI-state table, or local-storage state for the Inbox.
- **Dirty-worktree boundary:** do not modify or stage the user's `composer.json`, `.claude/settings.local.json`, or `docs/tech-debt/` changes.
- **Execution mode:** run this plan inline with `superpowers:executing-plans`; the environment does not authorize sub-agent delegation.

---

## Task 1: Lock the authenticated-home contract with failing tests

**Files:**

- Modify: `tests/Integration/Controller/AuthControllerTest.php`
- Modify: `tests/Integration/Core/AppMfaTest.php`
- Modify: `tests/Integration/Core/AppPasskeyLoginTest.php`
- Modify: `tests/Integration/Core/AppOAuthTest.php` only if a controller-level OAuth outcome can be exercised without network access; otherwise cover the shared redirect primitive and keep the OAuth boundary change visible in review

**Required cases:**

```php
$this->assertRedirect($this->post('/login', [
    'email' => 'member@example.test',
    'password' => 'password123',
]), '/inbox');

$this->assertRedirect($this->post('/login', [
    'email' => 'member@example.test',
    'password' => 'password123',
    'next' => '/',
]), '/');

$this->assertRedirect($this->post('/login', [
    'email' => 'member@example.test',
    'password' => 'password123',
    'next' => '//evil.example',
]), '/inbox');
```

Also assert:

- registration defaults to `/inbox`;
- authenticated GET `/login` and `/register` redirect to `/inbox`;
- MFA challenge completion defaults to `/inbox` and preserves a safe explicit destination;
- successful passkey login with no `next` returns JSON `redirect: /inbox`, while the existing `/settings/security` case stays green;
- the OAuth `login`, `created`, and MFA-challenge outcome branches use the same authenticated-home primitive.

- [ ] **Step 1 — Baseline:** run `php vendor/bin/phpunit tests/Integration/Controller/AuthControllerTest.php tests/Integration/Core/AppMfaTest.php tests/Integration/Core/AppPasskeyLoginTest.php tests/Integration/Core/AppOAuthTest.php`; record the passing baseline.
- [ ] **Step 2 — Write/adjust the assertions above without changing production code.**
- [ ] **Step 3 — Run the focused command again.** Expect failures showing `/` where `/inbox` is required; confirm the existing explicit-safe-next assertion remains green.
- [ ] **Step 4 — Commit tests only:** `test(auth): define Community Inbox landing contract`.

## Task 2: Implement the shared authenticated-home default

**Files:**

- Modify: `src/Controller/Controller.php`
- Modify: `src/Controller/AuthController.php`
- Modify: `src/Controller/OAuthController.php`

**Interface:** add one shared protected primitive to the base controller so password, MFA, passkey, registration, and OAuth cannot drift:

```php
protected function authenticatedHome(): string
{
    return '/inbox';
}
```

Use it as follows:

- `AuthController::safeNext('')` and unsafe destinations return `$this->authenticatedHome()`;
- already-authenticated `/login`, login POST, MFA POST, `/register`, and registration POST use the helper;
- passkey login passes an empty string when `next` is absent so `safeNext()` selects the helper; do **not** coerce a supplied `/` to empty;
- OAuth `login`, `created`, and OAuth-to-MFA `next` use the helper;
- logout remains `redirectWithFlash('/', ...)`.

- [ ] **Step 1 — Implement the smallest controller changes above.**
- [ ] **Step 2 — PHP syntax:** `php -l src/Controller/Controller.php`; `php -l src/Controller/AuthController.php`; `php -l src/Controller/OAuthController.php`.
- [ ] **Step 3 — Run the Task 1 focused PHPUnit command.** Expect green.
- [ ] **Step 4 — Search for missed auth success defaults:** `rg -n "redirect(?:WithFlash)?\\('/'|next' => '/'|beginLoginChallenge.*'/'" src/Controller/AuthController.php src/Controller/OAuthController.php`. Review every remaining match; only logout or an explicit public destination may remain.
- [ ] **Step 5 — Commit:** `feat(auth): default signed-in journeys to Community Inbox`.

## Task 3: Define stable Inbox and conversation-viewport markup with failing tests

**Files:**

- Modify: `tests/Integration/Core/AppThreadStateTest.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`
- Modify: `tests/Integration/Core/AppThreadUxAuditTest.php` if its existing failed-reply fixture is the cleanest place to assert expansion state

**Inbox render contract:** extend `testInboxPageRendersForMember()` to require navigation controls outside the replaceable content:

```php
self::assertStringContainsString('data-inbox-back', $r->body());
self::assertStringContainsString('data-inbox-reading-content', $r->body());
self::assertStringContainsString('data-inbox-reading', $r->body());
```

**Thread render contract:** assert the canonical thread contains:

```php
self::assertStringContainsString('class="thread-conversation"', $threadPage->body());
self::assertStringContainsString('class="thread-scroll"', $threadPage->body());
self::assertStringContainsString('class="thread-dock"', $threadPage->body());
self::assertStringContainsString('class="composer reply-composer', $threadPage->body());
```

Add one invalid-reply assertion proving the server marks rejected/restored input expanded:

```php
self::assertStringContainsString('reply-composer is-expanded', $response->body());
self::assertStringContainsString('The rejected draft body', $response->body());
```

The exact class order may be made deterministic in the template so this assertion remains stable.

- [ ] **Step 1 — Baseline:** run `php vendor/bin/phpunit tests/Integration/Core/AppThreadStateTest.php tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppThreadUxAuditTest.php`; record the passing baseline.
- [ ] **Step 2 — Add the Inbox and thread/composer assertions.**
- [ ] **Step 3 — Re-run the focused command.** Expect failures for the new missing hooks/classes only.
- [ ] **Step 4 — Commit tests only:** `test(ui): define Inbox state and reply dock markup`.

## Task 4: Add the stable Inbox shell and mobile state machine

**Files:**

- Modify: `templates/inbox.php`
- Modify: `public/assets/app.js`
- Modify: `public/assets/app.css`

**Markup target:** keep the reading section stable while only its inner content is replaced:

```php
<section class="inbox-reading" data-inbox-reading tabindex="-1" aria-label="Reading pane">
    <button class="inbox-mobile-back" type="button" data-inbox-back>Back to topics</button>
    <div data-inbox-reading-content>
        <!-- existing server-rendered empty state -->
    </div>
</section>
```

**JavaScript state contract:**

```js
var readingContent = inbox.querySelector('[data-inbox-reading-content]');
var back = inbox.querySelector('[data-inbox-back]');
var selectedLink = null;

var setReadingOpen = function (open) {
    inboxList.classList.toggle('is-hidden', open);
    reading.classList.toggle('is-open', open);
};
```

Implementation details:

- preserve the empty state's HTML from `readingContent`, not the entire reading section;
- replace only `readingContent.innerHTML` after validating fetched `#main` as a topic;
- on open, set active row, open mobile state, scroll reading to top, and focus the fetched heading;
- store the selected topic anchor for focus restoration;
- Back removes `t` with `history.pushState`, calls the empty/list state, and restores focus;
- `popstate` with no `t` shows the list; with `t` reloads the matching canonical topic;
- an initial `?t=` goes through the same loader and opens the mobile reading state;
- redirected, non-OK, malformed, missing-topic, and rejected fetches navigate to the canonical topic URL;
- modified clicks retain normal browser behavior.

**CSS at `max-width: 860px`:**

```css
.inbox-list.is-hidden { display: none; }
.inbox-reading.is-open { display: block; }
.inbox-mobile-back { min-height: 44px; }
```

Keep `.inbox-mobile-back` hidden on desktop. Under no-JS, the button is inert but harmless and topic anchors still navigate canonically; optionally scope its visibility to `.has-js`.

- [ ] **Step 1 — Update `templates/inbox.php` with the stable back control/content wrapper.**
- [ ] **Step 2 — Rewrite only the Community Inbox block in `app.js` to use the stable wrapper and explicit state classes.**
- [ ] **Step 3 — Add the existing Imladris mobile state rules to `app.css` at the production breakpoint.**
- [ ] **Step 4 — Run `php vendor/bin/phpunit tests/Integration/Core/AppThreadStateTest.php`.** Expect green.
- [ ] **Step 5 — Static guards:** `rg -n "reading\\.innerHTML|data-inbox-reading-content|data-inbox-back|is-hidden|is-open" public/assets/app.js templates/inbox.php public/assets/app.css`; verify only the content wrapper receives fetched HTML.
- [ ] **Step 6 — Commit:** `feat(inbox): add responsive list and reading states`.

## Task 5: Compact mobile top bar while preserving search

**Files:**

- Modify: `templates/partials/topbar.php`
- Modify: `templates/partials/sidebar.php`
- Modify: `public/assets/app.css`
- Modify: `tests/Integration/Core/AppTest.php` or the nearest existing navigation render test

**Template changes:**

- wrap optional wordmark/moderation text in explicit label spans so narrow CSS can hide the visual text without removing the anchor or `aria-label`;
- retain every current top-bar action and counter;
- when `$features['search']` is enabled, add a `mobile-only` rail link to `/search` using the existing icon partial:

```php
<a class="rail-filter mobile-search-link" href="/search">
    <?= $this->partial('partials/icon', ['name' => 'search']) ?>
    <span>Search</span>
</a>
```

**CSS target at `max-width: 860px`:**

- `.topbar` is one `62px` row with no wrapping;
- `.topbar-search` is hidden;
- right-side controls remain 44px targets and do not shrink below usability;
- mobile-only rail search becomes visible;
- only nonessential visual labels hide at the narrowest width;
- desktop search and wordmark remain unchanged.

- [ ] **Step 1 — Add a server-render assertion for the mobile rail search link behind the existing search feature gate.**
- [ ] **Step 2 — Run the focused test and confirm it fails for the missing link.**
- [ ] **Step 3 — Update both partials and mobile CSS.**
- [ ] **Step 4 — Re-run the focused navigation test and `php vendor/bin/phpunit tests/Integration/Core/AppThreadStateTest.php`.**
- [ ] **Step 5 — Commit:** `style(nav): keep mobile chrome compact and search reachable`.

## Task 6: Build the shared thread conversation viewport and reply dock

**Files:**

- Modify: `templates/thread.php`
- Modify: `templates/partials/composer.php`
- Modify: `public/assets/app.css`
- Modify: `public/assets/app.js`

**Thread structure:** inside the existing `<article class="thread">`, add one wrapper and keep content order/semantics unchanged:

```php
<article class="thread thread-conversation">
    <div class="thread-scroll" data-thread-scroll>
        <!-- existing header, memory/workflow, poll, posts, pagination -->
    </div>
    <div class="thread-dock">
        <!-- existing locked/member/guest/suspended/forbidden reply surface -->
    </div>
</article>
```

The member form becomes:

```php
<?php $expanded = !empty($reply_errors) || !empty($reply_old['body']); ?>
<form class="composer reply-composer<?= $expanded ? ' is-expanded' : '' ?>" ...>
```

Do not move any topic content out of the scroll region except the existing reply/join surface at the bottom.

**Layout rules:**

- `.thread-conversation` uses a bounded viewport only where the app shell provides one; canonical thread pages must still work at natural document height if the viewport cannot be bounded;
- `.thread-scroll` is the only vertical scroller in the bounded state and receives bottom padding so the last post is not obscured;
- `.thread-dock` is a non-scrolling footer with a raised surface, top hairline, safe-area padding, and no overlay over focused content;
- the same markup works when fetched into the Inbox reading wrapper;
- locked/guest/write-gated join bars occupy the dock without inventing another action.

**Compact composer behavior:**

- on narrow screens, rest state shows a compact textarea/form;
- `focusin` adds `.is-expanded`; errors/old input arrive expanded from the server;
- do not auto-collapse a non-empty composer;
- the existing formatting toolbar is one horizontally scrollable row (`flex-wrap: nowrap; overflow-x: auto`);
- `.checkline` uses grid columns `auto minmax(0, 1fr)` so explanation text stays aligned with its checkbox;
- retain the existing rich-composer/WYSIWYG DOM hooks and draft behavior.

Suggested enhancement:

```js
var replyComposers = document.querySelectorAll('.reply-composer');
for (var i = 0; i < replyComposers.length; i++) {
    replyComposers[i].addEventListener('focusin', function () {
        this.classList.add('is-expanded');
    });
}
```

Because Inbox topic HTML is inserted after page load, bind this through document-level `focusin` delegation or call a reusable initializer after insertion; do not bind only the initial DOM.

- [ ] **Step 1 — Wrap existing `thread.php` content without changing its actions, conditions, or copy.**
- [ ] **Step 2 — Add deterministic reply-composer expansion classes in `composer.php`.**
- [ ] **Step 3 — Add scoped viewport/dock/compact-composer CSS and the existing reduced-motion treatment.**
- [ ] **Step 4 — Add delegated focus expansion in `app.js`.**
- [ ] **Step 5 — Run `php vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppThreadUxAuditTest.php tests/Integration/Core/AppTest.php`.** Expect green, including form actions, textarea fallback, assets, idempotency, and draft preservation.
- [ ] **Step 6 — PHP syntax:** `php -l templates/thread.php`; `php -l templates/partials/composer.php`.
- [ ] **Step 7 — Commit:** `feat(threads): dock the immediate reply surface`.

## Task 7: Add focused Playwright behavior, theme, no-JS, and axe coverage

**Files:**

- Create: `tests/browser/community-inbox-theme.spec.ts`
- Create/Update evidence only when the repository's evidence convention requires it: `docs/evidence/community-inbox-theme/`

**Test harness:** use the seeded credentials (`alice@retro.test`, password `password123`), existing desktop/mobile projects, and existing `@axe-core/playwright` filtering pattern. Keep the spec serial if it changes user theme preferences.

**Required journeys:**

1. **Authenticated default:** login without `next`; expect pathname `/inbox`.
2. **Desktop 1280x800:** click a topic; expect list and reading pane both visible, `?t=`, fetched heading focused/visible, and no horizontal overflow.
3. **Mobile 390x844:** expect initial list; click a topic; expect list hidden, reading pane and Back visible, Back restores list and focus; browser Forward reopens the topic.
4. **Direct mobile URL:** navigate to `/inbox?t=<seeded-id>`; expect the conversation state after fetch.
5. **Compact chrome:** measure `.topbar` near `62px`, one row, no page horizontal overflow; open the rail and assert the Search link is reachable.
6. **Reply dock:** on a long seeded topic, assert `#reply` is visible without scrolling the entire document, focus its editable surface, and expect `.is-expanded`; toolbar and anonymous control stay within their scroll/container bounds.
7. **No JavaScript:** login form redirects to `/inbox`, topic anchor navigates to `/t/...`, and the server-rendered reply form is usable.
8. **Parchment and twilight:** run the same layout assertions after selecting each existing theme preference; capture focused desktop/mobile screenshots for visual comparison.
9. **Accessibility:** run Axe with WCAG 2 A/AA and 2.1 A/AA tags, filtering to serious/critical, scoped to `[data-inbox]` and `.thread-dock`.

Skeleton:

```ts
import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox(?:\?|$)/);
}
```

- [ ] **Step 1 — Write the focused spec before browser-facing fixes are considered complete.**
- [ ] **Step 2 — Run against the current local seeded server:** from `tests/browser`, set `E2E_SKIP_WEBSERVER=1` and `E2E_BASE_URL=http://127.0.0.1:4173`, then run `npx playwright test community-inbox-theme.spec.ts --project=desktop --project=mobile`.
- [ ] **Step 3 — If the existing Windows PHP Sodium limitation prevents reseeding, do not change product code; use the already seeded `retroboards_e2e` server and document the environment constraint.**
- [ ] **Step 4 — Fix only confirmed behavior/layout/a11y failures and rerun until green.**
- [ ] **Step 5 — Compare screenshots at matching viewports against the accepted Imladris kit and pre-change audit, checking spacing, borders, type, overflow, and pane state—not merely screenshot existence.**
- [ ] **Step 6 — Commit:** `test(browser): cover Community Inbox responsive journey`.

## Task 8: Regression and completion verification

**Focused verification:**

- [ ] `php vendor/bin/phpunit tests/Integration/Controller/AuthControllerTest.php tests/Integration/Core/AppMfaTest.php tests/Integration/Core/AppPasskeyLoginTest.php tests/Integration/Core/AppOAuthTest.php`
- [ ] `php vendor/bin/phpunit tests/Integration/Core/AppThreadStateTest.php tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppThreadUxAuditTest.php tests/Integration/Core/AppTest.php`
- [ ] `php -l src/Controller/Controller.php`; `php -l src/Controller/AuthController.php`; `php -l src/Controller/OAuthController.php`; `php -l templates/inbox.php`; `php -l templates/partials/topbar.php`; `php -l templates/partials/sidebar.php`; `php -l templates/thread.php`; `php -l templates/partials/composer.php`
- [ ] `git diff --check`

**Full verification:**

- [ ] `composer test`
- [ ] from `tests/browser`, run the focused Playwright spec for both projects against the approved local server
- [ ] inspect `git status --short` and confirm only task files plus the user's pre-existing changes are present
- [ ] review `git diff 521844f...HEAD --` for accidental auth, route, form, or feature-flag scope expansion
- [ ] run the `superpowers:verification-before-completion` workflow before claiming success
- [ ] final commit if verification required a repair: `fix(ui): close Community Inbox verification gaps`

## Self-review

- **Spec coverage:** authenticated defaults, safe explicit `next`, responsive Inbox list/reading states, URL history/fallback, compact mobile chrome, rail search, immediate dock, compact/expanded composer, toolbar/anonymous wrapping, no-JS, both themes, focus, Axe, and full regressions all map to an implementation task. ✓
- **Progressive enhancement:** every enhanced action begins with a real anchor, button, or form; canonical routes remain the source of truth. ✓
- **State ownership:** the URL and DOM classes are the only Inbox state; no persistence or router was introduced. ✓
- **Security:** auth destination validation, CSRF, idempotency, read gates, and posting actions are preserved. ✓
- **Scope:** no Thread Intelligence behavior/defaults, admin redesign, migrations, assets, or new design language. ✓
- **Dirty tree:** unrelated user changes are explicitly excluded from edits and commits. ✓
