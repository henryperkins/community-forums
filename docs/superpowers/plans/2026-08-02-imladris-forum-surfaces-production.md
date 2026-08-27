# Imladris Forum Surfaces Production Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Carry the approved Imladris Forum Index, individual-board, and canonical-thread presentation into the production PHP application while making Forum index, Forum inbox, and Messages unmistakably different surfaces.

**Architecture:** Keep production routes, controllers, authorization, CSRF forms, and progressive enhancement authoritative. Retire the obsolete board-sort preference at the schema/controller boundary, render the Index and board from existing policy-filtered data, add only route-scoped application CSS over the generated Imladris foundation, and preserve the already-shipped Thread View Study contract. Browser evidence drives the real server-rendered application and compares its screenshots beside the approved prototype captures.

**Tech Stack:** PHP 8.2, MySQL/MariaDB, PHPUnit, server-rendered PHP templates, unlayered `public/assets/app.css`, strict-CSP vanilla JavaScript, Playwright 1.61.1, Axe.

## Global Constraints

- Repository precedence remains `DECISIONS.md` → `PRODUCT_DESIGN.md` → `SCHEMA.md` and migrations → `USER.md` / `ADMIN.md` / `COMMUNITY.md` / `COMPOSER.md`.
- `/` is a calm directory of policy-listed boards; `/inbox` is the signed-in member's personal cross-board queue; `/messages` is private conversation; `/c/{slug}` is one board's topic list; `/t/{id}-{slug}` is focused reading and reply.
- The Forum Index has no topic preview, recent-topic feed, Inbox filters, composer, or board picker.
- Board order is exactly pinned first, then `last_post_at DESC`, then `id DESC`; `/c/{slug}` has no Active, Newest, Unanswered, or Most replies controls.
- The board-only identity band is `#2E4A3A` with `#FAF6EC` text and a `3px solid #C29A44` bottom rule. It must not appear on `/`, `/inbox`, `/messages`, or canonical threads.
- The canonical thread stays parchment and retains every existing real feature/capability gate, POST/CSRF form, anonymity rule, audit path, anti-draft-loss path, and no-JavaScript fallback.
- Follow renders only when the signed-in viewer has both `community` and `expanded_feeds`; New topic renders only from the server-computed `can_post` capability.
- The existing persisted `thread_sort` key becomes unknown, preserved legacy JSON and is ignored. No migration deletes it.
- The API's existing creation-time newest ordering stays distinct through `ThreadRepository::listNewestByBoard(int $boardId, int $limit, int $offset): array`.
- Shared thread-row changes use the explicit input `presentation => 'board'`; Inbox and tag callers retain their existing markup and `.thread-row a.thread-title` selector.
- Reuse existing Imladris tokens, fonts, icon partials, and focus styles. Do not edit generated `public/assets/imladris.css`, add inline script/style, ship prototype runtime code, create handcrafted SVGs, or add dependencies.
- Keep the production shell breakpoint at 860px. New Forum Index and board content may compact at 680px and 430px.
- Visible page work must pass focused and full PHPUnit, `composer verify:imladris`, WYSIWYG asset verification, Playwright/Axe, visual comparison, and `git diff --check` before completion.
- Work only in `C:\Users\htper\community-forums\.worktrees\imladris-forum-surfaces-production`; preserve the dirty main checkout and do not merge, push, publish, or deploy.

---

### Task 1: Fix board ordering and retire the obsolete preference contract

**Files:**

- Modify: `src/Repository/ThreadRepository.php:100-140`
- Modify: `src/Controller/BoardController.php:48-105`
- Modify: `src/Controller/Api/BoardsController.php:42-65`
- Modify: `src/Support/PreferenceSchema.php:25-210`
- Modify: `src/Service/PreferenceService.php:60-105`
- Modify: `templates/account/preferences.php:1-58`
- Modify: `tests/Integration/Core/AppReadingPreferencesTest.php:1-145`
- Modify: `tests/Integration/Api/ApiReadEndpointsTest.php`
- Modify: `tests/Integration/Core/AppUserPreferencesTest.php:200-235`
- Modify: `tests/Integration/Core/AppComposerShellTest.php:230-250`
- Modify: `tests/Unit/Preferences/PreferenceSchemaTest.php:1-155`
- Modify: `PRODUCT_DESIGN.md:118-139,178-190`
- Modify: `USER.md:183-198`

**Interfaces:**

- Produces: `ThreadRepository::listByBoard(int $boardId, int $limit, int $offset): array`, fixed to board activity order.
- Produces: `ThreadRepository::listNewestByBoard(int $boardId, int $limit, int $offset): array`, fixed to creation-time order for the existing public API.
- Produces: `PreferenceService::reading()` and `readingDefaults()` returning `array{show_signatures:bool,show_avatars:bool,show_reactions:bool}`.
- Preserves: `ThreadRepository` row shape, board pagination, API JSON shape, and unknown preference keys.

- [ ] **Step 1: Replace the old board-sort test with a failing fixed-order regression**

In `AppReadingPreferencesTest`, update the class comment and replace `test_thread_sort_preference_orders_the_board_list()` with a test that creates one pinned topic and three unpinned topics whose creation time, reply count, and last activity disagree. Persist each legacy value directly so the test exercises an old stored blob, not a now-removed form field:

```php
public function test_board_order_is_pinned_then_last_post_for_every_legacy_sort_value(): void
{
    $cat = $this->makeCategory();
    $board = $this->makeBoard($cat, ['slug' => 'fixed-order-board']);
    $user = $this->makeUser(['username' => 'fixed_order_reader']);
    $pinned = $this->makeThread($board, $user, 'ZZPINNED');
    $active = $this->makeThread($board, $user, 'ZZACTIVE');
    $tieHigh = $this->makeThread($board, $user, 'ZZTIEHIGH');
    $tieLow = $this->makeThread($board, $user, 'ZZTIELOW');

    $this->db->run('UPDATE threads SET is_pinned = 1, created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-01-01 00:00:00', '2024-01-01 00:00:00', 0, $pinned['thread_id']]);
    $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-01-01 00:00:00', '2024-04-01 00:00:00', 1, $active['thread_id']]);
    $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-04-01 00:00:00', '2024-03-01 00:00:00', 2, $tieHigh['thread_id']]);
    $this->db->run('UPDATE threads SET created_at = ?, last_post_at = ?, reply_count = ? WHERE id = ?', ['2024-03-01 00:00:00', '2024-03-01 00:00:00', 99, $tieLow['thread_id']]);
    $this->actingAs($user);

    foreach (['last_post', 'newest', 'replies'] as $legacySort) {
        $this->db->run(
            'INSERT INTO user_preferences (user_id, prefs, updated_at) VALUES (?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE prefs = VALUES(prefs), updated_at = UTC_TIMESTAMP()',
            [$user['id'], json_encode(['__v' => 2, 'thread_sort' => $legacySort], JSON_THROW_ON_ERROR)],
        );
        $this->assertOrder($this->get('/c/fixed-order-board')->body(), ['ZZPINNED', 'ZZACTIVE', 'ZZTIELOW', 'ZZTIEHIGH']);
    }
}
```

The literal tie order follows creation order: `ZZTIELOW` is created second and therefore has the higher id.

- [ ] **Step 2: Add failing preference-schema, settings-page, export, and API tests**

Make these observable assertions:

```php
// PreferenceSchemaTest
self::assertSame(3, PreferenceSchema::VERSION);
self::assertArrayNotHasKey('thread_sort', PreferenceSchema::fields('reading'));
$legacy = PreferenceSchema::resolve(['__v' => 2, 'thread_sort' => 'replies']);
self::assertSame('replies', $legacy['thread_sort']); // preserved unknown data

// AppUserPreferencesTest
self::assertArrayNotHasKey('thread_sort', $data['preferences']['reading']);
self::assertStringNotContainsString('Default thread sort', $this->get('/settings/preferences')->body());
```

Extend the existing API read-endpoint test with two public topics where the older topic has the newer `last_post_at`. Assert `/api/v1/boards/{id}/threads` still returns the newer `created_at` topic first. This catches accidentally reusing the board's activity order for the API.

- [ ] **Step 3: Run the focused tests and confirm RED for the intended contracts**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppReadingPreferencesTest.php tests/Integration/Core/AppUserPreferencesTest.php tests/Integration/Api/ApiReadEndpointsTest.php tests/Unit/Preferences/PreferenceSchemaTest.php
```

Expected: failures show the board still honors legacy sort, schema version is 2, and the setting/export still include `thread_sort`. The API characterization assertion is expected to remain green before and after the refactor because creation-time Newest is preserved deliberately.

- [ ] **Step 4: Split the repository methods and remove sort selection from the board controller**

Implement one private query helper so row selection stays identical while the order is explicit and never accepts user input:

```php
public function listByBoard(int $boardId, int $limit, int $offset): array
{
    return $this->listBoardRows($boardId, $limit, $offset, 't.is_pinned DESC, t.last_post_at DESC, t.id DESC');
}

public function listNewestByBoard(int $boardId, int $limit, int $offset): array
{
    return $this->listBoardRows($boardId, $limit, $offset, 't.is_pinned DESC, t.created_at DESC, t.id DESC');
}
```

Keep the SQL string private and callable only from these two constant-order methods. Change `BoardController` to call `listByBoard($boardId, $perPage, $offset)` and use reading preferences only for `show_avatars`. Change the API controller to call `listNewestByBoard(...)`.

- [ ] **Step 5: Retire `thread_sort` from managed preferences without deleting legacy JSON**

Set `PreferenceSchema::VERSION = 3`, remove `thread_sort` from the reading schema, and update the version comments. Do not add a v3 transform: once the key is no longer schema-managed, `resolve()` and `upgrade()` preserve it through the existing unknown-key path.

Change `PreferenceService::pickReading()` and its PHPDoc to:

```php
/** @return array{show_signatures:bool,show_avatars:bool,show_reactions:bool} */
private function pickReading(array $r): array
{
    return [
        'show_signatures' => (bool) ($r['show_signatures'] ?? true),
        'show_avatars' => (bool) ($r['show_avatars'] ?? true),
        'show_reactions' => (bool) ($r['show_reactions'] ?? true),
    ];
}
```

Remove `$sort` and the entire Default thread sort `<label>` from `templates/account/preferences.php`. Remove obsolete `thread_sort` fixture fields from tests that post the reading form. Keep the legacy-preservation assertion from Step 2.

- [ ] **Step 6: Reconcile the authoritative product docs**

Update `PRODUCT_DESIGN.md` so its shell and URL map name `/` the Forum Index directory, `/inbox` the personalized thread inbox, `/c/{slug}` one board's topic list, and `/t/{id}-{slug}` the canonical conversation. Replace the board sort-tab statement with the exact fixed order and state that Newest/Unanswered are Inbox filters. Update `USER.md` to remove Default thread sort/Most replies and describe fixed board order. Increment each document's version and add a dated 2026-08-02 changelog entry using its existing format.

- [ ] **Step 7: Run focused tests, then commit**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppReadingPreferencesTest.php tests/Integration/Core/AppUserPreferencesTest.php tests/Integration/Core/AppComposerShellTest.php tests/Integration/Api/ApiReadEndpointsTest.php tests/Unit/Preferences/PreferenceSchemaTest.php
git diff --check
```

Expected: all tests pass and the diff check is empty.

Commit:

```powershell
git add -- src/Repository/ThreadRepository.php src/Controller/BoardController.php src/Controller/Api/BoardsController.php src/Support/PreferenceSchema.php src/Service/PreferenceService.php templates/account/preferences.php tests/Integration/Core/AppReadingPreferencesTest.php tests/Integration/Api/ApiReadEndpointsTest.php tests/Integration/Core/AppUserPreferencesTest.php tests/Integration/Core/AppComposerShellTest.php tests/Unit/Preferences/PreferenceSchemaTest.php PRODUCT_DESIGN.md USER.md
git commit -m "feat: fix board topics to activity order"
```

---

### Task 2: Distinguish shared navigation and build the calm Forum Index

**Files:**

- Create: `tests/Integration/Core/AppForumIndexDesignTest.php`
- Modify: `templates/partials/sidebar.php:1-40`
- Modify: `templates/home.php:1-32`
- Modify: `public/assets/app.css:4573-4630`
- Modify: `tests/Integration/Core/AppCouncilTopicFidelityTest.php`

**Interfaces:**

- Consumes: existing policy-filtered `$sections`, `$site_name`, `$features`, `$request_path`, and `$current_user` view globals.
- Produces: `.forum-directory__hero`, `.forum-directory__stats`, `.forum-directory__category`, and `.forum-directory__board` markup scoped beneath `.board-index`.
- Preserves: `HomeController` query/access behavior and admin-aware empty state.

- [ ] **Step 1: Add failing Forum Index and route-navigation integration tests**

Create `AppForumIndexDesignTest` with a test that sets a public board to 7 topics/42 posts and a hidden board to 90 topics/900 posts, then requests `/` as a guest. Assert exact data hooks so unrelated page numbers cannot satisfy the test:

```php
self::assertStringContainsString('class="forum-directory__hero"', $body);
self::assertStringContainsString('>Forum index<', $body);
self::assertStringContainsString('Forum inbox', $body);
self::assertStringContainsString('personal cross-board queue', $body);
self::assertStringContainsString('data-forum-total="boards">1 board', $body);
self::assertStringContainsString('data-forum-total="topics">7 topics', $body);
self::assertStringContainsString('data-forum-total="posts">42 posts', $body);
self::assertStringContainsString('href="/c/public-design-board"', $body);
self::assertStringNotContainsString('hidden-design-board', $body);
self::assertStringNotContainsString('data-inbox-list', $body);
self::assertStringNotContainsString('composer-details', $body);
```

Add a signed-in navigation test that asserts the three exact links and explanations plus `aria-current="page"` on `/`, `/inbox`, and `/messages` respectively:

```php
self::assertStringContainsString('>Forum index<', $home);
self::assertStringContainsString('>Browse boards<', $home);
self::assertStringContainsString('>Forum inbox<', $inbox);
self::assertStringContainsString('>Your personal queue<', $inbox);
self::assertStringContainsString('>Messages<', $messages);
self::assertStringContainsString('>Private conversations<', $messages);
```

Use DOM parsing or narrowly scoped regular expressions to bind each `aria-current` assertion to its own href. Update the old `Home` breadcrumb/navigation expectation in `AppCouncilTopicFidelityTest` to `Forum index`.

- [ ] **Step 2: Run the focused tests and confirm RED**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppForumIndexDesignTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php
```

Expected: failures name the missing hero/totals/copy, old Home/Inbox labels, missing sublabels, and missing `aria-current`.

- [ ] **Step 3: Render explanatory shared navigation without changing routes or gates**

Keep the existing icon and feature-gate branches. Replace only their copy structure:

```php
<span class="rail-route-copy">
    <span class="rail-route-label">Forum index</span>
    <span class="rail-route-detail">Browse boards</span>
</span>
```

Use `Forum inbox` / `Your personal queue` and `Messages` / `Private conversations` for the other two destinations. Add `aria-current="page"` only when each existing active-route expression is true. Keep Search, Drafts, Following, Top contributors, the board rail, presence, and all feature flags unchanged.

- [ ] **Step 4: Recompose `home.php` from already-filtered sections**

Calculate totals before rendering; do not query repositories from the view:

```php
$visibleBoards = 0;
$visibleTopics = 0;
$visiblePosts = 0;
foreach ($sections as $section) {
    foreach (($section['boards'] ?? []) as $listedBoard) {
        $visibleBoards++;
        $visibleTopics += (int) ($listedBoard['thread_count'] ?? 0);
        $visiblePosts += (int) ($listedBoard['post_count'] ?? 0);
    }
}
```

Render a `.forum-directory__hero` with eyebrow, `$site_name`, the sentence “Browse the listed boards and pick one to see its topics. Use Forum inbox for your personal cross-board queue.”, and three singular/plural-aware totals with the `data-forum-total` hooks from the test.

Keep categories as `<section>` elements and rows in `<ul>`. Put each row's name, description, visibility tag, topics, and posts inside one `.forum-directory__board` anchor to the real `/c/{slug}` URL. Preserve the existing no-boards/admin-console copy.

- [ ] **Step 5: Replace the old card rules with route-scoped Imladris directory rules**

Remove the unscoped `.cat-block`, `.cat-title`, `.board-list`, `.board-row`, `.board-link`, `.board-name`, `.board-desc`, and `.board-stats` block in the reading-surface section. Translate the exact geometry at prototype `src/styles.css:486-658`: hero `max-width: 680px` and `padding: 4px 0 30px`; title `clamp(2.45rem, 4vw, 3.2rem)` at `line-height: 1.04`; stats gap `8px 22px`; category gap `38px`; row grid `minmax(0, 1fr) auto`, `min-height: 68px`, `padding: 12px 5px 12px 2px`, and top/bottom hairlines; copy grid `minmax(145px, .55fr) minmax(220px, 1fr)`; count grid `68px 68px`. Do not port the prototype-only arrow. Use no rounded card border or lift animation, and stack the copy/count grids at 680px/430px. Keep focus-visible styling from the shared foundation and add a route-scoped outline only if the full-row anchor does not inherit it.

Add narrowly scoped `.rail-route-copy`, `.rail-route-label`, and `.rail-route-detail` declarations that preserve the rail width and hide only the detail line when the existing compact/mobile rail lacks room. Do not change the 860px shell breakpoint.

- [ ] **Step 6: Run focused regression tests and commit**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppForumIndexDesignTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php tests/Integration/Core/AppPrivateBoardAccessTest.php tests/Integration/Core/AppPrivateBoardMembershipTest.php tests/Integration/Core/AppSeoVisibilityTest.php
git diff --check
```

Expected: all tests pass and no hidden/private board data appears in the guest Index.

Commit:

```powershell
git add -- tests/Integration/Core/AppForumIndexDesignTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php templates/partials/sidebar.php templates/home.php public/assets/app.css
git commit -m "feat: distinguish the production forum index"
```

---

### Task 3: Build the board identity band and explicit board topic rows

**Files:**

- Create: `tests/Integration/Core/AppBoardIdentityDesignTest.php`
- Modify: `src/Controller/BoardController.php:80-108`
- Modify: `templates/board.php:1-65`
- Modify: `templates/partials/thread_row.php:1-52`
- Modify: `public/assets/app.css:4573-4665,1529-1560`
- Modify: `public/assets/app.js:805-842`
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php:837-930`
- Create: `tests/browser/imladris-forum-surfaces.spec.ts`
- Modify: `tests/browser/playwright.config.ts:20-90`

**Interfaces:**

- Consumes: fixed board ordering from Task 1 and existing `can_post`, board counts/state, unread annotations, and composer partial.
- Produces: controller view key `can_follow_board: bool`.
- Produces: partial input `presentation: 'board'|'default'`, defaulting to `default`.
- Produces: `[data-board-identity]`, `[data-open-topic-composer]`, `[data-board-topics]`, and `.thread-row-board` hooks.
- Preserves: the single `details.composer-details` form, `.thread-row a.thread-title`, Inbox/tag default rows, CSRF, idempotency, anonymous posting, validation, pagination, archive/guest states, and mobile FAB.

- [ ] **Step 1: Add failing board identity, gating, and row-variant tests**

Create `AppBoardIdentityDesignTest` and cover a signed-in member on a populated public board. Assert:

```php
self::assertStringContainsString('aria-label="Breadcrumb"', $body);
self::assertStringContainsString('href="/">Forum index</a>', $body);
self::assertStringContainsString('data-board-identity', $body);
self::assertStringContainsString('data-board-fact="topics"', $body);
self::assertStringContainsString('data-board-fact="posts"', $body);
self::assertStringContainsString('data-board-topics', $body);
self::assertStringContainsString('>Latest activity<', $body);
self::assertStringContainsString('>Topics<', $body);
self::assertStringContainsString('Pinned first, then last post', $body);
self::assertStringContainsString("Following affects your discovery feed; it does not change this board's order.", $body);
self::assertStringContainsString('thread-row-board', $body);
self::assertSame(1, substr_count($body, 'details class="composer-details"'));
self::assertSame(1, substr_count($body, 'action="/threads"'));
$this->assertOrder($body, ['Follow board', 'New topic']);
```

Add separate tests for:

- guest: no Follow/New topic form, existing login joinbar remains;
- signed-in reader without `can_post`: no New topic trigger/form renders;
- archived board: archive wording remains and no New topic trigger/form renders;
- empty writable board: the topic section remains and says **No topics here yet.**;
- `expanded_feeds=true, community=false`: no follow form renders;
- `community=true, expanded_feeds=false`: no follow form renders;
- following state: the real POST button says **Following** and has `aria-pressed="true"`;
- default shared row rendered through an Inbox/tag route: no `thread-row-board`, while `.thread-row a.thread-title` remains.

Extend the existing feature-flag test at the current Follow coverage with both asymmetric flag combinations, using its existing board/user fixture setup.

Add the local-browser opt-in to `playwright.config.ts` before the browser test:

```ts
const browserChannel = process.env.E2E_BROWSER_CHANNEL?.trim() || undefined;
```

Add `channel: browserChannel` to the shared `use` object; an unset variable preserves CI's managed Chromium. Create the first `imladris-forum-surfaces.spec.ts` test using the existing login/tour helper shape. On desktop it requires the promoted New topic button to be visible, open the existing details, set `aria-expanded="true"`, focus the title, close on Escape, reset `aria-expanded="false"`, and restore focus. On mobile it clicks the existing FAB, proves the same details/title-focus behavior, closes on Escape, and proves focus returns to the FAB. These assertions must exist before `app.js` changes.

- [ ] **Step 2: Run the focused tests and confirm RED**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppBoardIdentityDesignTest.php tests/Integration/Core/AppFeatureFlagTest.php
Set-Location tests/browser
bash prepare.sh
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts
Remove-Item Env:E2E_BROWSER_CHANNEL
Set-Location ../..
```

Expected: PHPUnit failures name the missing identity/list hooks, missing visible order cue, current one-flag follow mismatch, and absent board row variant. Playwright fails because the promoted trigger does not exist and the FAB does not open/focus the enhanced composer.

- [ ] **Step 3: Make the Follow rendering gate match the POST route**

In `BoardController`, calculate both flags and expose one truthful gate:

```php
$features = $this->container->get(FeatureFlags::class);
$community = $features->enabled('community');
$expandedFeeds = $features->enabled('expanded_feeds');
$canFollowBoard = $user !== null && $community && $expandedFeeds;
$isFollowingBoard = $canFollowBoard
    && $this->container->get(FollowRepository::class)->isFollowingTarget($user->id(), 'board', (int) $board['id']);
```

Pass `can_follow_board` and `is_following_board`; stop making the template infer route availability from `expanded_feeds` alone. Reuse `$features` for the existing engagement check rather than resolving another feature object.

- [ ] **Step 4: Recompose `board.php` around one real composer**

Render this order inside `.board-view`:

1. semantic breadcrumb navigation back to **Forum index**;
2. `<header class="board-identity" data-board-identity>` with Board eyebrow, real name/description, `data-board-fact` spans for singular/plural topics/posts, `Public board` / `Hidden board` / `Private board`, archive state when present, Follow form, and a server-rendered button marked `hidden data-open-topic-composer aria-controls="new-topic" aria-expanded="false"` when `can_post`;
3. when Follow is available, the exact clarification “Following affects your discovery feed; it does not change this board's order.”;
4. the existing archive or guest message;
5. the single existing `<details class="composer-details" id="new-topic">` and its real form when `can_post`;
6. `<section class="board-topics" data-board-topics aria-labelledby="board-topics-heading">` with Latest activity, Topics, and Pinned first, then last post;
7. rows and pagination.

The Follow button is a real POST control using `btn btn-secondary`, `aria-pressed`, and **Follow board** / **Following** copy. The promoted New topic button uses `btn btn-accent`, the existing `plus` icon partial, and `New topic` capitalization; change the empty-list copy from “No threads here yet.” to **No topics here yet.** The hidden promoted button is inert without JavaScript; the native summary remains visible then. Pass the row input explicitly:

```php
<?= $this->partial('partials/thread_row', [
    't' => $t,
    'board' => $board,
    'show_avatars' => $show_avatars ?? true,
    'presentation' => 'board',
]) ?>
```

Keep the FAB as a canonical `#new-topic` fallback and retain every existing form field and partial.

- [ ] **Step 5: Add presentation-only branching to `thread_row.php`**

Start with:

```php
$presentation = (string) ($presentation ?? 'default');
$boardPresentation = $presentation === 'board';
$rowClasses = 'thread-row' . ($boardPresentation ? ' thread-row-board' : '');
```

For the board variant, keep chips/title/byline in `.thread-row-main`, omit only duplicated reply/time text from `.thread-meta`, and render a trailing `.thread-row-activity` with the literal reply count/label and `<time>` containing the same `human_datetime(last_post_at ?: created_at)` value. For the default variant, keep the current markup byte-for-byte apart from formatting necessary for the branch. Do not rename `thread-title`.

- [ ] **Step 6: Promote the one composer trigger with progressive-enhancement JavaScript**

Extend the existing new-topic block, rather than creating a second modal implementation:

```javascript
var promotedTrigger = document.querySelector('[data-open-topic-composer]');
var fabTrigger = document.querySelector('a.fab[href="#new-topic"]');
var topicReturnFocus = trigger;
var openTopic = function (opener) {
    topicReturnFocus = opener || trigger;
    newTopic.open = true;
};
if (promotedTrigger && trigger) {
    promotedTrigger.hidden = false;
    trigger.classList.add('js-native-topic-trigger');
    promotedTrigger.addEventListener('click', function () { openTopic(promotedTrigger); });
}
if (fabTrigger) {
    fabTrigger.addEventListener('click', function (event) {
        event.preventDefault();
        openTopic(fabTrigger);
    });
}
```

In the existing details `toggle` handler, synchronize the promoted button's `aria-expanded` with `newTopic.open`. Update `closeTopic()` to restore focus to `topicReturnFocus`. CSS hides `.has-js .js-native-topic-trigger` while leaving the summary present and usable without JS. The mobile FAB opens the same details when enhanced and remains a normal `#new-topic` anchor without JS. The existing Escape, backdrop, popover ownership, title focus, and Cancel behavior remain.

- [ ] **Step 7: Add route-scoped board styling**

Add only `.board-view .board-identity*`, `.board-view .board-topics*`, `.board-view .thread-row-board`, and `.board-view .thread-row-activity` rules. Translate prototype `src/styles.css:680-748,928-975,1877-1928`: the identity flex row uses `gap: 24px` and `padding: 22px 24px 20px`; its title is `clamp(2.25rem, 4vw, 3rem)` at `line-height: 1.05`; description/facts use `#DCE8DD`; the topics section starts at `margin-top: 32px`; board rows use an `80px` activity column and `min-height: 91px`. The identity band must compute to the exact three approved values, use the production Imladris typography/tokens for everything else, keep actions in Follow-then-New-topic order, and stack cleanly at 680px and 430px. The `#` inherits parchment inside the band; do not let the global gold hash rule override it.

Keep shared `.board-header` and default `.thread-row` rules unchanged. The board rows are quiet ruled list items, not elevated cards. Ensure the trailing activity column collapses into readable metadata on mobile and causes no horizontal scroll.

- [ ] **Step 8: Run board, composer, access, and shared-row regressions, then commit**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppBoardIdentityDesignTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppAdminArchiveTest.php tests/Integration/Core/AppAnonymousPostingTest.php tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerShellTest.php tests/Integration/Core/AppPrivateBoardAccessTest.php tests/Integration/Core/AppPrivateBoardMembershipTest.php
Set-Location tests/browser
bash prepare.sh
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts
Remove-Item Env:E2E_BROWSER_CHANNEL
Set-Location ../..
git diff --check
```

Expected: PHPUnit and the desktop/mobile composer interaction pass; the board has one composer form, focus returns to the actual opener, and Inbox/tag rows retain their default contract.

Commit:

```powershell
git add -- tests/Integration/Core/AppBoardIdentityDesignTest.php tests/Integration/Core/AppFeatureFlagTest.php tests/browser/imladris-forum-surfaces.spec.ts tests/browser/playwright.config.ts src/Controller/BoardController.php templates/board.php templates/partials/thread_row.php public/assets/app.css public/assets/app.js
git commit -m "feat: add Imladris board identity surface"
```

---

### Task 4: Align the canonical thread breadcrumb and lock the Study boundary

**Files:**

- Modify: `templates/thread.php:33-45`
- Modify: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Modify: `tests/Integration/Core/AppCouncilTopicFidelityTest.php`
- Modify: `tests/Integration/Core/AppImladrisFidelityTest.php`

**Interfaces:**

- Consumes: the shipped `thread-study` template, Topic tools, poll, Living Brief, post, pagination, composer, and Inbox-fetch contracts.
- Produces: a semantic breadcrumb labelled Forum index → board.
- Preserves: all controller data, canonicalization/read marking, server forms, capability branches, `data-thread-study`, and parchment styling.

- [ ] **Step 1: Add a failing canonical-thread boundary assertion**

In `AppThreadViewStudyTest`, add one focused test around a real public thread:

```php
self::assertStringContainsString('<nav class="breadcrumb" aria-label="Breadcrumb">', $body);
self::assertStringContainsString('href="/">', $body);
self::assertStringContainsString('Forum index</a>', $body);
self::assertStringContainsString('href="/c/' . $board['slug'] . '"', $body);
self::assertStringContainsString('data-thread-study', $body);
self::assertStringNotContainsString('data-board-identity', $body);
self::assertStringNotContainsString('board-identity', $body);
```

Keep the existing capability/form tests as the behavior proof; do not duplicate every form assertion into this test. Update the old Home label assertion in the two fidelity suites.

- [ ] **Step 2: Run the focused thread tests and confirm RED only for the breadcrumb**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php tests/Integration/Core/AppImladrisFidelityTest.php
```

Expected: the new semantic breadcrumb/Forum index assertions fail; existing Study feature assertions remain green.

- [ ] **Step 3: Change only the breadcrumb markup**

Replace the breadcrumb `<p>` with `<nav class="breadcrumb" aria-label="Breadcrumb">`, change `Home` to `Forum index`, retain the existing back icon, separator, escaped board name, and canonical board href, and close with `</nav>`. Do not add the evergreen board class or alter the topic head, poll, Living Brief, posts, Topic tools, or composer.

- [ ] **Step 4: Run the full thread contract group and commit**

Run:

```powershell
php vendor/bin/phpunit tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php tests/Integration/Core/AppImladrisFidelityTest.php tests/Integration/Core/AppThreadUxAuditTest.php tests/Integration/Core/AppThreadTagDisplayTest.php tests/Integration/Core/AppAnonymousPostingTest.php
git diff --check
```

Expected: all tests pass with every existing thread capability/form contract intact.

Commit:

```powershell
git add -- templates/thread.php tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppCouncilTopicFidelityTest.php tests/Integration/Core/AppImladrisFidelityTest.php
git commit -m "feat: align the canonical thread breadcrumb"
```

---

### Task 5: Capture production browser evidence and refresh the reviewed Imladris digest

**Files:**

- Modify: `tests/browser/imladris-forum-surfaces.spec.ts`
- Create: `docs/evidence/imladris-forum-surfaces-production.md`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/forum-index-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/board-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/thread-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/forum-index-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/board-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/desktop/thread-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/forum-index-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/board-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/thread-light.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/forum-index-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/board-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/mobile/thread-dark.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/forum-index.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/board.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/thread.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/forum-index-mobile.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/board-mobile.png`
- Create: `docs/evidence/imladris-forum-surfaces-production/comparisons/thread-mobile.png`
- Modify: `config/imladris-runtime-baseline.json`

**Interfaces:**

- Consumes: seeded `/`, `/c/general`, and “Share your favourite keyboard shortcuts” canonical thread.
- Produces: repeatable screenshots at 1266×854 desktop and 390×844 mobile, computed-style/a11y/no-JS assertions, and side-by-side approved-reference comparisons.
- Preserves: CI's bundled Chromium default; local native Chrome is opt-in through `E2E_BROWSER_CHANNEL=chrome`.

- [ ] **Step 1: Extend the focused browser contract from Task 3**

Keep Task 3's proven composer/focus cases and its opt-in `E2E_BROWSER_CHANNEL=chrome` harness. Add the route, theme, Axe, no-JavaScript, screenshot, console, and comparison coverage below without weakening those earlier assertions.

- [ ] **Step 2: Create the focused browser contract**

Extend `imladris-forum-surfaces.spec.ts` with helpers copied in shape—not imported—from the existing browser suites for login, tour dismissal, safe navigation, screenshots, Axe serious/critical filtering, and opening the seeded thread. Capture console `error`/`warning` entries and assert the list is empty at each test end.

For each project, set desktop to `{ width: 1266, height: 854 }`; leave mobile at its configured `{ width: 390, height: 844 }`. Assert:

```ts
await expect(page.locator('.forum-directory__hero')).toBeVisible();
await expect(page.getByText('personal cross-board queue')).toBeVisible();
await expect(page.locator('[data-board-identity]')).toHaveCount(0);

await expect(board).toHaveCSS('background-color', 'rgb(46, 74, 58)');
await expect(board).toHaveCSS('color', 'rgb(250, 246, 236)');
await expect(board).toHaveCSS('border-bottom-color', 'rgb(194, 154, 68)');
await expect(board).toHaveCSS('border-bottom-width', '3px');
await expect(page.getByText('Pinned first, then last post')).toBeVisible();

await expect(page.locator('[data-thread-study]')).toBeVisible();
await expect(page.locator('[data-board-identity]')).toHaveCount(0);
expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
```

Run Axe with WCAG 2 A/AA and 2.1 A/AA tags on `.board-index`, `.board-view`, and `[data-thread-study]`, failing on serious/critical violations.

- [ ] **Step 3: Cover keyboard and no-JavaScript behavior**

In the signed-in board test, Tab to the promoted New topic button, activate it, assert `aria-expanded="true"` and title-input focus, press Escape, then assert `aria-expanded="false"` and focus returned to that button. Assert the native summary has the JS-only hidden class while enhanced.

Create a new browser context with `javaScriptEnabled: false`, log in through the real form, visit `/c/general`, click `details.composer-details > summary`, and assert the details is open and the real `form[action="/threads"]` is visible. Follow the seeded topic link and assert the canonical thread renders. Do not submit or mutate the shared fixture.

- [ ] **Step 4: Capture light/dark and approved-reference comparison images**

For light and dark, set `html[data-theme]` to `light` or `dark`, disable animations, and write the named full-page screenshots. For each viewport project, read each approved prototype PNG and current production PNG with `fs.readFileSync`, embed both as base64 images in a neutral two-column `page.setContent()` contact sheet labelled “Approved prototype” and “Production,” then screenshot the six named desktop/mobile comparison PNGs. Use these approved references:

```text
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/forum-index-qa-1266x854-v2.png
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-desktop.png
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/thread-identity-desktop.png
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/forum-index-mobile-v2.png
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/board-identity-mobile.png
docs/superpowers/prototypes/2026-08-02-imladris-forum-surfaces/evidence/thread-mobile.png
```

- [ ] **Step 5: Prepare the evidence database and run the focused browser suite**

Run from `tests/browser`:

```powershell
bash prepare.sh
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts
Remove-Item Env:E2E_BROWSER_CHANNEL
```

Expected: desktop/mobile, Axe, keyboard, no-JS, exact-color, overflow, console, screenshot, and comparison tests pass. Open all six comparison PNGs plus every mobile screenshot and inspect spacing, cropping, wrapping, contrast, target size, and band containment. Fix visible production defects through a reviewed code/test commit before recording a visual pass; never edit the approved reference images.

- [ ] **Step 6: Write the evidence report with measured results**

Create `docs/evidence/imladris-forum-surfaces-production.md` with:

- branch, test date, and the implementation commit tested before the evidence-only commit;
- source spec and prototype-reference paths;
- command/output summary;
- a table for `/`, `/c/general`, and the canonical thread at both viewports/themes;
- computed board colors and 3px rule;
- keyboard/focus/no-JS/Axe/overflow/console results;
- links to current screenshots and comparison sheets;
- a concise visual-difference judgment distinguishing intentional production data/shell differences from defects;
- an explicit statement that `/inbox` body work remains outside this slice.

Every claim must quote an actual test result or inspected artifact; do not record an unrun check as passed.

- [ ] **Step 7: Refresh only the reviewed application-surface digest**

After browser QA is accepted, run from the repository root:

```powershell
php bin/build-imladris-assets.php --print-application-digest
```

Replace only `application_surface.sha256` in `config/imladris-runtime-baseline.json` with the exact 64-character stdout value. Keep `reconciled_through_commit`, `composer_contract`, roots/files/extensions, and excluded generated CSS unchanged. Then run:

```powershell
composer verify:imladris
npm run check:wysiwyg
```

Expected: runtime assets are current, 11 Imladris tests pass, and WYSIWYG generated assets are current.

- [ ] **Step 8: Run immutable final gates and commit the evidence**

Run against the final working tree:

```powershell
php vendor/bin/phpunit
composer verify:imladris
npm run check:wysiwyg
Set-Location tests/browser
bash prepare.sh
$env:E2E_BROWSER_CHANNEL='chrome'
npx playwright test imladris-forum-surfaces.spec.ts composer-shell.spec.ts thread-view-study.spec.ts
Remove-Item Env:E2E_BROWSER_CHANNEL
Set-Location ../..
git diff --check
git status --short
```

Expected: the full PHPUnit suite, Imladris verifier, WYSIWYG check, focused Forum surfaces, composer, and Thread Study browser suites all pass; diff check is empty; status contains only this plan's intended files.

Commit:

```powershell
git add -- tests/browser/imladris-forum-surfaces.spec.ts docs/evidence/imladris-forum-surfaces-production.md docs/evidence/imladris-forum-surfaces-production config/imladris-runtime-baseline.json
git commit -m "test: verify Imladris production forum surfaces"
```

After the commit, rerun the same final gate commands on the immutable commit, confirm `git status --short` is empty, and record that immutable SHA plus command results in the SDD task report before claiming completion.
