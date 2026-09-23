# Messages Poll And Focus Fixes Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix the four open review findings on the messages slice so a late font load keeps the reading position, a 422 lands focus on the recipient combobox, a catch-up larger than one page drains immediately, and the participant poll has its own rate limit.

**Architecture:** The server tells the truth about a short page by reading one extra row and acknowledging only the rows it returns. The client keeps a pin (`stickToEnd`) for "the reader is following the latest message," re-bottoms from a late `document.fonts.ready` only while that pin is set and the new-messages pill is hidden, and asks `shortPoll` to run again immediately when `has_more` advanced the cursor. The poll spends a new `dm_poll` policy and answers 429 as JSON with `Retry-After`; `shortPoll` obeys that delay and keeps today's exponential backoff when the header is absent. Sends stay on the existing `dm` policy.

**Tech Stack:** PHP 8.2, PHPUnit integration tests through `App::handle`, vanilla `public/assets/app.js` (classic deferred script), Playwright in `tests/browser`, `npm run build` (`bin/build-assets.mjs`) for the hashed `public/assets/dist` copy.

**Spec:** Requirements in this document. Source review: `/tmp/grok-ubuntu/grok-review-f6d44aa3.md` (issues 1–4). That scratch file can disappear; the requirements below are the copy executors implement.

## Requirements

1. **Font callback.** `document.fonts.ready` in `public/assets/app.js` calls `bottomDm()`, which sets `scrollTop` to the bottom, zeroes `pendingMessages`, and hides `[data-dm-newpill]`. A reader who has scrolled up, or a poll that has parked them and shown the pill, must keep that scroll position and that pill. A reader who is still following the end must still be scrolled to the end after fonts finish, including when font load grew `scrollHeight` without a scroll event.
2. **422 recipient focus.** `field_attrs()` puts `autofocus` on the first invalid control. The recipient picker turns `input[name="to"]` into `type="hidden"` and copies only `aria-describedby`, `aria-invalid`, and `data-error-focus`. Focus must land on the new combobox when that original input had `autofocus` or `data-error-focus`. The hidden input must not keep `autofocus`. A body error, which autofocuses the textarea, must still focus the textarea. A closed compose dialog must not be focused or opened.
3. **`has_more`.** `ConversationReadService::poll` sets `has_more` when the page contains 50 rows, so an exact page of 50 looks truncated. The client never reads `has_more`, advances `lastId` to the 50th id, and when the reader is near the end calls `bottomDm()`. A burst larger than 50 must load the rest immediately. Only returned rows are marked read. A `has_more` response whose `last_id` does not advance must not spin.
4. **Poll rate limit.** `create` and `reply` call `throttle()` on the `dm` policy before writing. `poll` does not. Add a limit the 20-second poll and a short catch-up burst can live inside, return JSON `429` with `Retry-After` (the presence poll shape), and make `shortPoll` wait that long, including when the reader returns to the tab or returns while a request is in flight. A 429 with no `Retry-After` keeps the exponential backoff the bell and conversation polls already have. Spending the poll budget must not spend the send budget.

## Global Constraints

- PHP 8.2, MySQL/MariaDB. Never bind `LIMIT` / `OFFSET`. Never reuse a named placeholder. The poll page cap is an interpolated integer.
- `ValidationException` is not an `HttpException`. This work does not change the 422 re-render that preserves the draft.
- CSRF stays on `POST /messages/{id}/poll`. GET stays 405. No new CSRF exemption.
- `RateLimitService::enforce()` fails open on an unknown policy name. `dm_poll` must be a real entry in `config/config.php` `rate_limits`.
- `dm` stays `[20, 600]` and remains the send/create bucket. `dm_poll` is `[120, 300]`: one open conversation polls every 20 seconds (15 requests per 300 seconds per tab); 120 covers several tabs plus the client catch-up burst below. Do not point `poll` at `dm`.
- Client catch-up burst cap is 20 immediate follow-ups after the initial request (up to 1,050 messages at 50 per page). The 22nd page waits the normal 20-second interval, then another burst is allowed.
- Poll page size is 50 returned rows. The repository reads 51. The extra row is a probe: it is dropped before `markRead`, and the next poll with `after` set to the last returned id delivers it.
- `markRead` only runs when at least one row is returned, and only with that last returned id. A forged or future `after` still acknowledges nothing.
- Membership, join boundary, blocks, opt-out, `group_dms` rollback, and `WriteGate` behavior stay as they are. `poll` still calls `requireDms()` before the limiter, so a guest is redirected to login and a dark `dms` flag is 404 without spending a bucket.
- Strict CSP: no inline script or style. The focus fix is in `public/assets/app.js`.
- No-JS compose keeps the canonical `input[name="to"]` and posts it unchanged. The picker returns immediately when `window.fetch` is missing.
- Browser tests execute the hashed file from `config/assets.json`, not the source file. Every `app.js` change is followed by `npm run build` before Playwright. `/.build/` is gitignored. Commit `public/assets/app.js`, `public/assets/dist/`, and `config/assets.json`.
- PHPUnit is strict (`failOnWarning`, `failOnRisky`). Integration tests roll the database transaction back; the in-process `ArrayRateLimiter` does not. Assert HTTP status and JSON, which survive that rollback.
- Do not hand-edit files under `public/assets/dist/`.

## File map

- `src/Repository/DmMessageRepository.php` — `afterForUser()` accepts a bounded limit and interpolates it.
- `src/Service/ConversationReadService.php` — reads `POLL_PAGE + 1`, drops the probe, sets `has_more` from the probe, marks read through the last returned id.
- `src/Controller/ConversationController.php` — `poll()` spends `dm_poll` and returns a JSON 429.
- `config/config.php` — declares `dm_poll`.
- `docs/runbooks/group_dms.md` — tells operators the poll bucket is separate from sends.
- `public/assets/app.js` — `shortPoll` delay override and `Retry-After`; messages pin; `has_more` drain; combobox focus.
- `tests/Integration/Core/AppMessagesRefinementTest.php` — page-size, continuation, and limiter tests.
- `tests/browser/messages-poll-regressions.spec.ts` — font pin, focus, drain, and `Retry-After` tests. New file.
- `public/assets/dist/**` and `config/assets.json` — regenerated by `npm run build`, not edited by hand.

## Review Focus

These are the inputs a reasonable member hits that the requirements imply and that are easy to get wrong. Each one has a test in the task named here.

- Font load grows the thread while the reader never scrolled: they stay at the latest message. Task 2, `a late font load re-bottoms a reader who is still pinned`.
- The catch-up is exactly 50 messages: `has_more` is false and the conversation is fully read. Task 1, `testPollOfAnExactPageIsComplete`.
- `has_more` is true but `last_id` does not move: the client waits for the interval. Task 3, `a has_more page that does not advance the cursor waits for the interval`.
- The 422 is a body error ("Your message is too long."): focus stays on the textarea, and a closed compose dialog on `/messages` is not given focus. Task 4, `a body error keeps focus on the textarea` and `a closed compose dialog is not focused`.
- The poll bucket is exhausted: the next send still succeeds, and a 429 without `Retry-After` still backs off exponentially. Task 5, `testPollThrottleIsJsonAndDoesNotSpendTheSendBudget`, plus the existing bell test `polling pauses while hidden and backs off after throttling` and the existing conversation test `conversation poll backs off, pauses while hidden, resumes immediately and stops on 404`.
- The server asked for 30 seconds and the reader switches away and back after 1 second: the poll still waits out the 30 seconds instead of firing on return. Task 5, `a tab return inside a Retry-After wait does not poll early`.
- A test is run alone against a fresh seed, which has no conversations: it starts its own counsel instead of reading `.dm-row`. Tasks 3 and 5, `startCounsel`.

---

### Task 1: Exact poll page

**Files:**
- Modify: `src/Repository/DmMessageRepository.php` (`afterForUser`, currently the `LIMIT 50` query)
- Modify: `src/Service/ConversationReadService.php` (`poll`)
- Test: `tests/Integration/Core/AppMessagesRefinementTest.php`

**Interfaces:**
- Consumes: `DmMessageRepository::afterForUser(int $conversationId, int $userId, int $after): array` and `ConversationRepository::markRead(int $conversationId, int $userId, int $messageId): void`.
- Produces: `afterForUser(int $conversationId, int $userId, int $after, int $limit = 50): array`. Limit is clamped to `1..51` and interpolated. `ConversationReadService::poll` still returns `has_more`, `last_id`, `html`, `dm_unread`, `presence`, `other_last_read_message_id`. `has_more` is true only when a 51st row existed. `last_id` and `last_read_message_id` are the last returned id.

- [x] **Step 1: Write the failing test**

Add this method to `tests/Integration/Core/AppMessagesRefinementTest.php`, next to `testPollCapsCatchupAndNeverAcknowledgesAForgedCursor`:

```php
public function testPollOfAnExactPageIsComplete(): void
{
    [$alice, $bob, $id] = $this->pair();
    $messages = new DmMessageRepository($this->db);
    $ids = [];
    for ($i = 0; $i < 50; $i++) {
        $ids[] = $messages->create($id, (int) $bob['id'], 'Letter', '<p>Letter</p>');
    }
    $this->actingAs($alice);

    $data = json_decode($this->post('/messages/' . $id . '/poll', ['after' => 0])->body(), true, flags: JSON_THROW_ON_ERROR);

    self::assertSame(50, substr_count($data['html'], 'data-message-id='));
    self::assertFalse($data['has_more']);
    self::assertSame($ids[49], $data['last_id']);
    self::assertSame($ids[49], (int) (new ConversationRepository($this->db))->membership($id, (int) $alice['id'])['last_read_message_id']);
    self::assertSame(0, $data['dm_unread']);
}
```

- [x] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/phpunit tests/Integration/Core/AppMessagesRefinementTest.php --filter testPollOfAnExactPageIsComplete`

Expected: FAIL. `has_more` is `true` because the current code uses `count($messages) === 50`.

- [x] **Step 3: Extend the 51-message test so the probe row is delivered next**

Inside `testPollCapsCatchupAndNeverAcknowledgesAForgedCursor`, after the `PHP_INT_MAX` assertions, add:

```php
$next = json_decode($this->post('/messages/' . $id . '/poll', ['after' => $ids[49]])->body(), true, flags: JSON_THROW_ON_ERROR);
self::assertFalse($next['has_more']);
self::assertSame($ids[50], $next['last_id']);
self::assertStringContainsString('id="m' . $ids[50] . '"', $next['html']);
self::assertSame(0, $next['dm_unread']);
self::assertSame($ids[50], (int) (new ConversationRepository($this->db))->membership($id, (int) $alice['id'])['last_read_message_id']);
```

This continuation already works before the probe change. It fails if the probe row is acknowledged on the first poll or dropped from the second.

- [x] **Step 4: Read one extra row and acknowledge only the returned page**

Replace `afterForUser` in `src/Repository/DmMessageRepository.php` with:

```php
/**
 * Bounded incremental read inside the active membership interval.
 * Callers that need a "is there another page" probe pass page size + 1.
 * The ceiling is that probe (50 + 1); LIMIT is interpolated, never bound.
 *
 * @return list<array<string,mixed>>
 */
public function afterForUser(int $conversationId, int $userId, int $after, int $limit = 50): array
{
    $limit = max(1, min(51, $limit));

    return $this->db->fetchAll(
        'SELECT m.*, u.username AS author_username, u.display_name AS author_display_name
         FROM conversation_participants cp
         JOIN dm_messages m ON m.conversation_id = cp.conversation_id
            AND m.id > cp.joined_after_message_id AND m.id > ?
         JOIN users u ON u.id = m.user_id
         WHERE cp.conversation_id = ? AND cp.user_id = ? AND cp.left_at IS NULL
         ORDER BY m.id ASC LIMIT ' . $limit,
        [max(0, $after), $conversationId, $userId],
    );
}
```

> **If `fix/messages-audit` has landed first** (added 2026-09-23): that branch removes the transaction from `poll`, so an empty tick opens no BEGIN/COMMIT and writes nothing, and it reads the receipt watermark from the single participants read. Do not re-add `$this->db->transaction(...)`. Apply the probe to that shape instead: `$rows = $this->messages->afterForUser($conversationId, $viewer->id(), $after, self::POLL_PAGE + 1); $hasMore = count($rows) > self::POLL_PAGE; if ($hasMore) { array_pop($rows); }`, then call `markRead` only when `$rows !== []`, with the last returned id. `AppMessagesServerMarkupTest::testAnEmptyPollTickReadsOnceAndWritesNothing` counts statements but cannot see a transaction (tests run inside an outer one), so it will not catch a reintroduced wrapper. Only the snippet below assumes the 027d878 shape.

In `ConversationReadService`, add `private const POLL_PAGE = 50;` on the class and replace the transaction inside `poll` so the probe is popped before `markRead`:

```php
$hasMore = false;
$messages = $this->db->transaction(function () use ($viewer, $conversationId, $after, &$hasMore): array {
    $rows = $this->messages->afterForUser($conversationId, $viewer->id(), $after, self::POLL_PAGE + 1);
    $hasMore = count($rows) > self::POLL_PAGE;
    if ($hasMore) {
        array_pop($rows);
    }
    // A supplied cursor is never a read watermark; acknowledge only returned rows.
    if ($rows !== []) {
        $this->conversations->markRead($conversationId, $viewer->id(), (int) end($rows)['id']);
    }
    return $rows;
});
```

Change the returned flag from `count($messages) === 50` to the probe:

```php
'has_more' => $hasMore,
```

Leave `last_id` as the last returned id, or `after` when the page is empty.

- [x] **Step 5: Run the poll tests**

Run: `vendor/bin/phpunit tests/Integration/Core/AppMessagesRefinementTest.php --filter 'testPoll'`

Expected: PASS, including `testPollOfAnExactPageIsComplete`, `testPollCapsCatchupAndNeverAcknowledgesAForgedCursor`, `testPollReturnsOnlyNewVisibleMessagesAndMarksOnlyReturnedMessagesRead`, `testPollDoesNotRevealPrivateCounselToAnOutsiderOrAfterRollback`, and `testPollHonoursGroupJoinBoundaryAndStopsAfterLeavingOrRollback`.

- [x] **Step 6: Commit**

```bash
git add src/Repository/DmMessageRepository.php src/Service/ConversationReadService.php tests/Integration/Core/AppMessagesRefinementTest.php
git commit -m "fix(messages): treat an exact poll page as complete"
```

---

### Task 2: Keep the reading position across late fonts

**Files:**
- Modify: `public/assets/app.js` (the `bottomDm` / `document.fonts.ready` block inside the `dmStream && dmScroller && dmShell` section)
- Test: `tests/browser/messages-poll-regressions.spec.ts` (create)
- Regenerated: `public/assets/dist/**`, `config/assets.json`

**Interfaces:**
- Consumes: `[data-dm-scroll]`, `[data-dm-messages]`, `[data-dm-newpill]`, `.dm-shell[data-dm-latest="1"]`.
- Produces: a `stickToEnd` boolean in that same closure. `bottomDm()` sets it true after moving `scrollTop`. The scroll listener sets it from `atDmEnd()`. Later tasks read and update it. `document.fonts.ready` calls `bottomDm()` only when `stickToEnd` is true and the pill is hidden.

`atDmEnd()` after fonts load is the wrong signal for "the reader never moved." Growing `scrollHeight` leaves `scrollTop` where it was, so a pinned reader looks short of the end until something scrolls them. The pin remembers that they were following.

- [x] **Step 1: Write the failing browser tests**

Create `tests/browser/messages-poll-regressions.spec.ts`:

```ts
import { test, expect, type Page } from '@playwright/test';

async function login(page: Page, who: string) {
  await page.goto('/login');
  await page.locator('[name=email]').fill(`${who}@retro.test`);
  await page.locator('[name=password]').fill('password123');
  await page.locator('button[type=submit]').click();
  await page.waitForURL(url => url.pathname !== '/login');
  const skip = page.getByRole('button', { name: 'Skip', exact: true });
  if (await skip.isVisible()) await skip.click();
}

async function post(page: Page, url: string, fields: Record<string, string>) {
  const token = await page.locator('input[name=_token]').first().inputValue();
  return page.request.post(url, { form: { ...fields, _token: token }, maxRedirects: 0 });
}

// The browser seed has no conversations, so every test that needs one starts
// its own; nothing may depend on an earlier test (each must pass alone with -g).
async function startCounsel(page: Page, body = 'Open the counsel.'): Promise<string> {
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', { to: 'bob', body });
  expect(started.status()).toBe(303);
  return started.headers().location!;
}

async function openTallCounsel(page: Page): Promise<string> {
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', {
    to: 'bob',
    body: Array.from({ length: 40 }, (_, i) => `Counsel line ${i + 1}. The record stays with the people named here.`).join('\n\n'),
  });
  expect(started.status()).toBe(303);
  const route = started.headers().location!;
  await page.goto(route);
  await expect(page.locator('[data-dm-scroll]')).toBeVisible();
  return route;
}

test('a late font load leaves a reader who scrolled away where they are', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await page.addInitScript(() => {
    let resolveReady: () => void = () => {};
    const ready = new Promise<void>(resolve => { resolveReady = resolve; });
    Object.defineProperty(Document.prototype, 'fonts', {
      configurable: true,
      get: () => ({ ready }),
    });
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts = () => resolveReady();
  });
  await openTallCounsel(page);
  await page.setViewportSize({ width: 800, height: 420 });
  const scroller = page.locator('[data-dm-scroll]');
  const overflow = await scroller.evaluate(node => node.scrollHeight - node.clientHeight);
  expect(overflow).toBeGreaterThan(120);
  await scroller.evaluate(node => { node.scrollTop = 0; });
  await page.evaluate(() => (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts());
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => resolve(undefined))));
  expect(await scroller.evaluate(node => node.scrollTop)).toBe(0);
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});

test('a late font load re-bottoms a reader who is still pinned', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await page.addInitScript(() => {
    let resolveReady: () => void = () => {};
    const ready = new Promise<void>(resolve => { resolveReady = resolve; });
    Object.defineProperty(Document.prototype, 'fonts', {
      configurable: true,
      get: () => ({ ready }),
    });
    (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts = () => resolveReady();
  });
  await openTallCounsel(page);
  await page.setViewportSize({ width: 800, height: 420 });
  const scroller = page.locator('[data-dm-scroll]');
  await scroller.evaluate(node => {
    const extra = document.createElement('div');
    extra.dataset.fontGrowth = '1';
    extra.style.height = '240px';
    node.querySelector('[data-dm-messages]')!.appendChild(extra);
  });
  await page.evaluate(() => (window as unknown as { __resolveDmFonts: () => void }).__resolveDmFonts());
  await page.evaluate(() => new Promise(resolve => requestAnimationFrame(() => resolve(undefined))));
  const gap = await scroller.evaluate(node => node.scrollHeight - node.scrollTop - node.clientHeight);
  expect(gap).toBeLessThan(90);
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});
```

- [x] **Step 2: Run the tests to verify they fail**

Run: `cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop`

Expected: FAIL. The scrolled-away test sees `scrollTop` jump to the bottom. The pinned test can also fail today if the callback runs `bottomDm` before the growth node is inserted and does not run again; after the pin exists, resolving fonts after the growth node is appended is what re-bottoms. The scrolled-away failure is the gate for this step.

- [x] **Step 3: Pin the reader, and re-bottom from fonts only while pinned**

In `public/assets/app.js`, in the messages closure, add `stickToEnd` next to `pendingMessages` and teach `bottomDm` plus the scroll listener. Replace the initial `bottomDm` / `document.fonts.ready` block.

```javascript
var lastId = 0, pendingMessages = 0, stickToEnd = false;
dmStream.querySelectorAll('[data-message-id]').forEach(function (line) { lastId = Math.max(lastId, Number(line.dataset.messageId)); });
function atDmEnd() { return dmScroller.scrollHeight - dmScroller.scrollTop - dmScroller.clientHeight < 90; }
function bottomDm() {
    dmScroller.scrollTop = dmScroller.scrollHeight;
    pendingMessages = 0; dmPill.hidden = true;
    stickToEnd = true;
}
arrangeDmRuns();
dmPill.addEventListener('click', bottomDm);
dmScroller.addEventListener('scroll', function () {
    stickToEnd = atDmEnd();
    if (stickToEnd) { pendingMessages = 0; dmPill.hidden = true; }
});
if (dmShell.dataset.dmLatest === '1' && !location.hash.match(/^#m[0-9]+$/)) {
    bottomDm();
    if (document.fonts) {
        document.fonts.ready.then(function () {
            if (stickToEnd && dmPill.hidden) { bottomDm(); }
        });
    }
}
```

Keep the existing `moveDmReceipt` and `arrangeDmRuns` functions above this block. `bottomDm` sets `stickToEnd` after assigning `scrollTop`, so a scroll event fired by that assignment cannot clear the pin. A later user scroll can.

A `#m123` hash still skips the initial `bottomDm`, so `stickToEnd` stays false and the font callback does not pull the reader off the anchored message.

- [x] **Step 4: Rebuild assets and re-run the tests**

Run:

```bash
npm run build
npm run check:assets
cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop
```

Expected: `check:assets` prints `Generated assets and manifest are current.` Both new tests PASS.

- [x] **Step 5: Commit**

```bash
git add public/assets/app.js public/assets/dist config/assets.json tests/browser/messages-poll-regressions.spec.ts
git commit -m "fix(messages): keep the reading position when fonts finish late"
```

---

### Task 3: Drain `has_more` immediately

**Files:**
- Modify: `public/assets/app.js` (`shortPoll`, and the messages `shortPoll` callback)
- Modify: `tests/browser/messages-poll-regressions.spec.ts`
- Regenerated: `public/assets/dist/**`, `config/assets.json`

**Interfaces:**
- Consumes: `stickToEnd` and `bottomDm()` from Task 2. Server JSON from Task 1: `has_more: bool`, `last_id: number`, `html: string`.
- Produces: `shortPoll`'s `apply` may return `false` to stop (unchanged) or a number `>= 0` as the next delay in milliseconds. Any other return, including `undefined`, keeps `interval`. The messages callback returns `0` when `data.has_more === true`, `last_id` is greater than the previous cursor, and fewer than 20 immediate follow-ups have run in this burst.

When the reader is pinned, a partial page still calls `bottomDm()` so the next immediate response is also measured from the end. Skipping `bottomDm` on `has_more` would make the following response look scrolled-away and show the pill in front of a reader who was following.

- [x] **Step 1: Write the failing drain test**

Append to `tests/browser/messages-poll-regressions.spec.ts`:

```ts
const messageHtml = (id: number) =>
  `<div class="dm-group" data-dm-author="2" data-dm-date="2026-09-21">` +
  `<div class="dm-msgs"><div class="dm-ghead"><span class="dm-name">Bob</span>` +
  `<time datetime="2026-09-21T12:00:00Z">12:00</time></div>` +
  `<div class="dm-line" id="m${id}" data-message-id="${id}" data-created-at="2026-09-21T12:00:00Z">` +
  `<div class="dm-body formatted-content"><p>Page row ${id}</p></div></div></div></div>`;

test('a has_more page requests the next page without waiting for the interval', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages/new');
  const started = await post(page, '/messages', { to: 'bob', body: 'Open the counsel.' });
  expect(started.status()).toBe(303);
  const route = started.headers().location!;
  let calls = 0;
  const afters: string[] = [];
  await page.route('**/messages/*/poll', async route => {
    calls++;
    afters.push(new URLSearchParams(route.request().postData() || '').get('after') || '');
    if (calls === 1) {
      await route.fulfill({ json: {
        html: messageHtml(9001), last_id: 9001, has_more: true,
        presence: {}, dm_unread: 1, other_last_read_message_id: null,
      } });
      return;
    }
    await route.fulfill({ json: {
      html: messageHtml(9002), last_id: 9002, has_more: false,
      presence: {}, dm_unread: 0, other_last_read_message_id: null,
    } });
  });
  await page.goto(route);
  await expect.poll(() => calls, { timeout: 3000 }).toBe(2);
  expect(afters[1]).toBe('9001');
  await expect(page.locator('#m9001')).toBeAttached();
  await expect(page.locator('#m9002')).toBeAttached();
  await expect(page.locator('[data-dm-newpill]')).toBeHidden();
});
```

- [x] **Step 2: Run the drain test to verify it fails**

Run: `cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop -g "without waiting"`

Expected: FAIL. `calls` stays `1` until the 20-second interval. The test times out at 3 seconds.

- [x] **Step 3: Let `apply` choose the next delay**

Replace `shortPoll` in `public/assets/app.js` with this version. Task 5 adds the 429 branch on top of this function; keep the `nextDelay` behavior exactly.

```javascript
function shortPoll(url, interval, apply, options) {
    if (!window.fetch) { return; }
    var timer = null, backoff = 0, stopped = false, busy = false, resume = false, nextDelay = interval;
    function clear() {
        if (timer !== null) { window.clearTimeout(timer); timer = null; }
    }
    function schedule(delay) {
        if (!stopped && !document.hidden && timer === null) { timer = window.setTimeout(run, delay); }
    }
    function run() {
        clear();
        if (stopped || document.hidden) { return; }
        if (busy) { resume = true; return; }
        busy = true;
        nextDelay = interval;
        var init = options ? options() : {};
        init.credentials = 'same-origin';
        init.headers = Object.assign({ 'X-Requested-With': 'XMLHttpRequest' }, init.headers || {});
        fetch(typeof url === 'function' ? url() : url, init).then(function (r) {
            if (r.status === 404) { stopped = true; return null; }
            if (!r.ok) { throw new Error('poll failed'); }
            return r.json();
        }).then(function (data) {
            if (data) { backoff = 0; }
            if (!stopped && !document.hidden && data) {
                var verdict = apply(data);
                if (verdict === false) { stopped = true; }
                else if (typeof verdict === 'number' && verdict >= 0) { nextDelay = verdict; }
            }
        }).catch(function () {
            backoff = backoff === 0 ? interval : Math.min(backoff * 2, 15 * 60000);
        }).finally(function () {
            busy = false;
            var delay = backoff > 0 ? backoff : nextDelay;
            if (resume) { resume = false; schedule(0); }
            else { schedule(delay); }
        });
    }
    document.addEventListener('visibilitychange', function () {
        clear();
        if (!document.hidden) { run(); }
    });
    run();
}
```

In the messages poll callback, capture the pin before mutating the DOM, follow the end on every page of a burst, and return `0` only when the cursor advanced:

```javascript
var dmBurst = 0;
shortPoll('/messages/' + dmShell.dataset.dmConversation + '/poll', 20000, function (data) {
    var follow = stickToEnd;
    var previousId = lastId;
    var oldTop = dmScroller.scrollTop;
    if (data.html) {
        var template = document.createElement('template'); template.innerHTML = data.html;
        template.content.querySelectorAll('[data-message-id]').forEach(function (line) {
            if (document.getElementById('m' + line.dataset.messageId)) { line.remove(); }
            else { pendingMessages++; }
        });
        var incoming = template.content.querySelectorAll('[data-message-id]').length;
        if (incoming) {
            dmStream.appendChild(template.content); arrangeDmRuns(); enhanceDmCopy(dmStream);
            if (follow) { bottomDm(); }
            else {
                dmScroller.scrollTop = oldTop; dmPill.hidden = false;
                dmPill.querySelector('span').textContent = pendingMessages + ' new message' + (pendingMessages === 1 ? '' : 's');
            }
            dmStatus.textContent = incoming + ' new message' + (incoming === 1 ? '' : 's') + ' received.';
        }
    }
    lastId = Math.max(lastId, Number(data.last_id) || 0);
    var receipt = document.querySelector('[data-dm-receipt]');
    if (receipt && receipt.dataset.dmReceipt) {
        receipt.querySelector('.dm-receipt').textContent = Number(data.other_last_read_message_id) >= Number(receipt.dataset.dmReceipt) ? 'Read' : 'Delivered';
    }
    document.querySelectorAll('[data-dm-presence]').forEach(function (node) {
        var state = data.presence && data.presence[node.dataset.dmPresence] || 'offline';
        node.hidden = state === 'offline';
        node.classList.toggle('is-online', state === 'online'); node.classList.toggle('is-away', state === 'away');
        node.querySelector('[data-dm-presence-label]').textContent = state === 'online' ? 'Here now' : state === 'away' ? 'Away' : '';
    });
    var groupPresence = document.querySelector('[data-dm-group-presence]');
    if (groupPresence) {
        var here = Object.values(data.presence || {}).filter(function (state) { return state === 'online'; }).length;
        groupPresence.textContent = here ? ' · ' + here + ' here now' : '';
    }
    updateDmCount(data.dm_unread);
    localiseDmTimes(document);
    if (data.has_more === true && lastId > previousId && dmBurst < 20) {
        dmBurst += 1;
        return 0;
    }
    dmBurst = 0;
}, function () {
    var token = dmShell.querySelector('input[name="_token"]');
    return { method: 'POST', body: new URLSearchParams({ after: String(lastId), _token: token ? token.value : '' }) };
});
```

Declare `var dmBurst = 0;` once, beside `lastId`, not inside the callback.

- [x] **Step 4: Write the non-advancing cursor lock**

Append to the same spec:

```ts
test('a has_more page that does not advance the cursor waits for the interval', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    const after = new URLSearchParams(route.request().postData() || '').get('after') || '0';
    await route.fulfill({ json: {
      html: '', last_id: Number(after), has_more: true,
      presence: {}, dm_unread: 0, other_last_read_message_id: null,
    } });
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(3000);
  expect(calls).toBe(1);
  await page.clock.runFor(20050);
  await expect.poll(() => calls).toBe(2);
});
```

The test starts its own counsel through `startCounsel`, because the browser seed creates no conversations: it must pass when run alone with `-g`, not only after Task 2's tests. `page.clock` is installed after that setup request and before the conversation navigation, matching `conversation poll backs off...`.

- [x] **Step 5: Rebuild and run the poll specs**

Run:

```bash
npm run build
npm run check:assets
cd tests/browser && npx playwright test messages-poll-regressions.spec.ts messages-refinement.spec.ts --project=desktop -g "poll"
```

Expected: PASS. The new drain test reaches 2 calls within 3 seconds. The non-advancing test stays at 1 call across 3 seconds and reaches 2 after the interval. `conversation poll backs off, pauses while hidden, resumes immediately and stops on 404` still passes, because those responses omit `has_more` and `apply` returns `undefined`.

- [x] **Step 6: Commit**

```bash
git add public/assets/app.js public/assets/dist config/assets.json tests/browser/messages-poll-regressions.spec.ts
git commit -m "fix(messages): load the rest of a poll page immediately"
```

---

### Task 4: Focus the recipient combobox on a 422

**Files:**
- Modify: `public/assets/app.js` (the `[data-dm-picker]` loop, where `canonical.type` becomes `hidden`)
- Modify: `tests/browser/messages-poll-regressions.spec.ts`
- Regenerated: `public/assets/dist/**`, `config/assets.json`

**Interfaces:**
- Consumes: `field_attrs()` output on `input[name="to"]`: `autofocus` when `to` is the first error. `data-error-focus` is copied today and is honored if some other render sets it. The label's `for` matches the id the picker moves onto the combobox.
- Produces: the combobox receives `autofocus` and `.focus()` when the canonical input had `autofocus` or `data-error-focus` and its closest `details` is missing or already `open`. The canonical input no longer has `autofocus` after `type="hidden"`.

- [x] **Step 1: Write the failing focus test and the two locks**

Append to `tests/browser/messages-poll-regressions.spec.ts`:

```ts
test('an unknown recipient focuses the combobox', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  await page.locator('.dm-dialog .dm-to-input').fill('unknown_member');
  await page.locator('.dm-dialog textarea[name=body]').fill('Preserve my letter after an eligibility error.');
  await page.locator('.dm-dialog .composer-send').click();
  await expect(page.locator('.dm-compose-details')).toHaveAttribute('open', '');
  await expect(page.locator('.dm-dialog')).toContainText('No member found');
  await expect(page.locator('.dm-dialog textarea[name=body]')).toHaveValue('Preserve my letter after an eligibility error.');
  await expect(page.locator('.dm-dialog .dm-to-input')).toBeFocused();
  await expect(page.locator('.dm-dialog input[name=to]')).toHaveAttribute('type', 'hidden');
  await expect(page.locator('.dm-dialog input[name=to]')).not.toHaveAttribute('autofocus', '');
});

test('a body error keeps focus on the textarea', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await page.locator('.dm-new-btn').click();
  const to = page.locator('.dm-dialog .dm-to-input');
  await to.fill('bob');
  await to.press('Enter');
  await page.locator('.dm-dialog textarea[name=body]').evaluate(element => {
    element.removeAttribute('maxlength');
    (element as HTMLTextAreaElement).value = 'x'.repeat(5001);
  });
  await page.locator('.dm-dialog .composer-send').click();
  await expect(page.locator('.dm-dialog')).toContainText('Your message is too long.');
  await expect(page.locator('.dm-dialog textarea[name=body]')).toBeFocused();
});

test('a closed compose dialog is not focused', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page, 'alice');
  await page.goto('/messages');
  await expect(page.locator('details.dm-compose-details')).not.toHaveAttribute('open', '');
  await expect(page.locator('.dm-dialog .dm-to-input')).not.toBeFocused();
});
```

`DirectMessageService::BODY_MAX` is 5000. Removing `maxlength` in the page is what lets the test submit the 5001st character; the server then returns the body error and `field_attrs()` autofocuses the textarea.

- [x] **Step 2: Run the unknown-recipient test to verify it fails**

Run: `cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop -g "unknown recipient"`

Expected: FAIL. The combobox is not the active element. The hidden `to` input is not focusable, and nothing calls `.focus()` on `.dm-to-input`. The server-rendered `open` attribute does not fire the dialog `toggle` handler.

- [x] **Step 3: Move autofocus onto the combobox and focus it**

In the picker loop, after the existing `aria-describedby` / `aria-invalid` / `data-error-focus` copy and before `canonical.type = 'hidden'`:

```javascript
var takeFocus = canonical.hasAttribute('autofocus') || canonical.hasAttribute('data-error-focus');
if (canonical.hasAttribute('autofocus')) { canonical.removeAttribute('autofocus'); }
if (takeFocus) { input.setAttribute('autofocus', ''); }
```

After `canonical.after(field);`:

```javascript
if (takeFocus) {
    var details = input.closest('details');
    if (!details || details.open) { input.focus(); }
}
```

Do not add `autofocus` to the attribute-copy list. That list would copy it and, if the removal ran first, would miss it. The hidden input ends without `autofocus`. A body or title error does not set `takeFocus`, so the browser's autofocus on that other control stands. A closed `details` is not focused, which would pull the dialog open.

- [x] **Step 4: Rebuild and run the focus tests**

Run:

```bash
npm run build
npm run check:assets
cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop -g "recipient|body error|closed compose"
```

Expected: all three PASS.

- [x] **Step 5: Commit**

```bash
git add public/assets/app.js public/assets/dist config/assets.json tests/browser/messages-poll-regressions.spec.ts
git commit -m "fix(messages): focus the recipient combobox after a 422"
```

---

### Task 5: Rate-limit the participant poll

**Files:**
- Modify: `config/config.php` (`rate_limits`, immediately after `'dm' => [20, 600],`)
- Modify: `src/Controller/ConversationController.php` (`poll`, plus a private JSON throttle)
- Modify: `public/assets/app.js` (`shortPoll` 429 branch)
- Modify: `docs/runbooks/group_dms.md` (the Rate limits bullet)
- Test: `tests/Integration/Core/AppMessagesRefinementTest.php`
- Test: `tests/browser/messages-poll-regressions.spec.ts`
- Regenerated: `public/assets/dist/**`, `config/assets.json`

**Interfaces:**
- Consumes: `RateLimitService::enforce('dm_poll', Request, User)` and `retryAfter('dm_poll', Request, User): int`. `HttpException` on exhaustion. Task 3's `shortPoll` (`nextDelay`, `backoff`, `schedule`).
- Produces: `POST /messages/{id}/poll` 429 body `{"error":"rate_limited","retry_after":<seconds>}` with headers `Retry-After`, `Content-Type: application/json`, and `Cache-Control: private, no-store`. `shortPoll` waits `Retry-After` seconds when that header is a positive integer, otherwise `retry_after` in a JSON body, otherwise the existing exponential backoff. The wait is capped at 15 minutes, the same cap as the failure backoff.

- [x] **Step 1: Write the failing PHP tests**

Add to `tests/Integration/Core/AppMessagesRefinementTest.php`:

```php
public function testDmPollRateLimitPolicyIsDeclared(): void
{
    $config = require dirname(__DIR__, 3) . '/config/config.php';
    self::assertArrayHasKey('dm_poll', $config['rate_limits']);
    self::assertCount(2, $config['rate_limits']['dm_poll']);
    [$max, $decay] = $config['rate_limits']['dm_poll'];
    self::assertGreaterThanOrEqual(60, $max);
    self::assertSame(300, $decay);
    self::assertNotSame($config['rate_limits']['dm'], $config['rate_limits']['dm_poll']);
}

public function testPollThrottleIsJsonAndDoesNotSpendTheSendBudget(): void
{
    $items = $this->config->all();
    $items['rate_limits']['dm_poll'] = [1, 300];
    $this->config = new \App\Core\Config($items);
    $this->app = new \App\Core\App($this->config, $this->db, $this->rateLimiter);

    [$alice, $bob, $id] = $this->pair();
    $this->actingAs($alice);
    self::assertSame(200, $this->post('/messages/' . $id . '/poll', ['after' => 0])->status());

    $throttled = $this->post('/messages/' . $id . '/poll', ['after' => 0]);
    self::assertSame(429, $throttled->status());
    self::assertStringContainsString('application/json', $throttled->headers()['content-type'] ?? '');
    $payload = json_decode($throttled->body(), true, flags: JSON_THROW_ON_ERROR);
    self::assertSame('rate_limited', $payload['error']);
    self::assertGreaterThan(0, $payload['retry_after']);
    self::assertGreaterThan(0, (int) ($throttled->headers()['retry-after'] ?? 0));
    self::assertSame('private, no-store', $throttled->headers()['cache-control'] ?? null);

    $sent = $this->post('/messages/' . $id, ['body' => 'Still able to send']);
    $this->assertRedirectContains($sent, '/messages/' . $id);
}
```

`dirname(__DIR__, 3)` from `tests/Integration/Core` is the repository root, the same path `AppPresenceDirectoryTest` uses. Rebuilding `App` keeps `$this->rateLimiter`, so the second poll sees the first hit.

- [x] **Step 2: Run the PHP tests to verify they fail**

Run: `vendor/bin/phpunit tests/Integration/Core/AppMessagesRefinementTest.php --filter 'testDmPollRateLimitPolicyIsDeclared|testPollThrottleIsJsonAndDoesNotSpendTheSendBudget'`

Expected: FAIL. `dm_poll` is absent, and the second poll is 200.

- [x] **Step 3: Declare `dm_poll` and answer the poll in JSON**

In `config/config.php`, immediately after `'dm' => [20, 600],`:

```php
// One open conversation polls every 20s (15 per 300s per tab). 120 covers
// several tabs plus a 20-page catch-up burst. Sends stay on `dm`; sharing
// that bucket would exhaust a reader in a few minutes and then block replies.
'dm_poll' => [120, 300],
```

In `src/Controller/ConversationController.php`, import `App\Core\HttpException`. At the start of `poll`, after `$user = $this->requireDms();`:

```php
if ($limited = $this->throttlePoll($request, $user)) {
    return $limited;
}
```

Add this next to `throttle()`:

```php
/**
 * A 429 rendered as the kernel's HTML error page is useless to shortPoll.
 * Answer in JSON with a Retry-After the client can obey.
 */
private function throttlePoll(Request $request, User $user): ?Response
{
    $limits = $this->container->get(RateLimitService::class);
    try {
        $limits->enforce('dm_poll', $request, $user);
    } catch (HttpException) {
        $retryAfter = max(1, $limits->retryAfter('dm_poll', $request, $user));
        return Response::json(['error' => 'rate_limited', 'retry_after' => $retryAfter], 429)
            ->header('Retry-After', (string) $retryAfter)
            ->header('Cache-Control', 'private, no-store');
    }
    return null;
}
```

In `docs/runbooks/group_dms.md`, replace the Rate limits bullet with:

```markdown
- **Rate limits:** message sends share the `dm` policy (default 20 per 10
  minutes per account), the open-conversation poll uses `dm_poll` (default
  120 per 5 minutes per account), and reports use `dm_report` (default 10 per
  10 minutes) — all in `config/config.php` `rate_limits`. Tighten `dm` during
  a spam wave of sends. Tighten `dm_poll` when a client is hammering
  `POST /messages/{id}/poll`. The two buckets are independent, so tightening
  one does not slow the other.
```

Leave the escalation ladder's `rate_limits.dm` step as the send control. The poll is not how a member posts.

- [x] **Step 4: Run the PHP tests to verify they pass**

Run: `vendor/bin/phpunit tests/Integration/Core/AppMessagesRefinementTest.php --filter 'testDmPollRateLimitPolicyIsDeclared|testPollThrottleIsJsonAndDoesNotSpendTheSendBudget|testPoll'`

Expected: PASS. Also run `vendor/bin/phpunit tests/Integration/Core/AppDirectMessageTest.php --filter testHttpSendUsesCentralDmLimiter` and expect PASS, confirming the send policy is unchanged.

- [x] **Step 5: Write the failing Retry-After browser test**

Append to `tests/browser/messages-poll-regressions.spec.ts`:

```ts
test('the conversation poll waits for Retry-After on a 429', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await route.fulfill({
        status: 429,
        headers: { 'Retry-After': '30', 'Content-Type': 'application/json' },
        body: JSON.stringify({ error: 'rate_limited', retry_after: 30 }),
      });
      return;
    }
    await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
  });
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(20050);
  expect(calls).toBe(1);
  await page.clock.runFor(10000);
  await expect.poll(() => calls).toBe(2);
});

test('a tab return inside a Retry-After wait does not poll early', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  const href = await startCounsel(page);
  let calls = 0;
  await page.route('**/messages/*/poll', async route => {
    calls++;
    if (calls === 1) {
      await route.fulfill({
        status: 429,
        headers: { 'Retry-After': '30', 'Content-Type': 'application/json' },
        body: JSON.stringify({ error: 'rate_limited', retry_after: 30 }),
      });
      return;
    }
    await route.fulfill({ json: { html: '', presence: {}, dm_unread: 0, last_id: 0, has_more: false } });
  });
  const setHidden = (hidden: boolean) => page.evaluate(value => {
    Object.defineProperty(document, 'hidden', { configurable: true, value });
    document.dispatchEvent(new Event('visibilitychange'));
  }, hidden);
  await page.clock.install();
  await page.goto(href);
  await expect.poll(() => calls).toBe(1);
  await page.clock.runFor(1000);
  await setHidden(true);
  await setHidden(false);
  await page.clock.runFor(1000);
  // Real time for a stray request to reach the route before asserting its absence.
  await page.waitForTimeout(250);
  expect(calls).toBe(1);
  await page.clock.runFor(27000);
  await page.waitForTimeout(250);
  expect(calls).toBe(1);
  await page.clock.runFor(1500);
  await expect.poll(() => calls).toBe(2);
});
```

The second test is the case a plain timer test misses: the visibility handler clears the timer and polls at once, which would send the next request about 1 second after a `Retry-After: 30`.

- [x] **Step 6: Run the Retry-After test to verify it fails**

Run: `cd tests/browser && npx playwright test messages-poll-regressions.spec.ts --project=desktop -g "Retry-After"`

Expected: both FAIL. With no special 429 handling the first failure backs off by one 20-second interval, so `calls` is already 2 after `runFor(20050)`; and the tab return polls immediately, so `calls` is 2 about 1 second after the 429.

- [x] **Step 7: Honor `Retry-After` inside `shortPoll`**

Replace the `fetch(...).then` status branch inside the Task 3 `shortPoll` with this, and replace its `visibilitychange` listener with the one below. Leave `nextDelay`, the numeric `apply` verdict, the catch backoff, and `schedule` as they are.

A server-mandated wait is a deadline, not just the next timer. Task 3's `visibilitychange` handler clears the timer and polls at once, and its `finally` resumes with `schedule(0)` when a return arrived mid-request, so either path would send the next request seconds after a `Retry-After: 30`. `retryAt` survives both; only a successful response clears it. Plain failures and a 429 without a usable wait keep today's behaviour, including the one immediate fetch on return (brief decision 2).

```javascript
// Beside timer, backoff, stopped, busy, resume and nextDelay:
var retryAt = 0;
function untilRetry() { return Math.max(0, retryAt - Date.now()); }
function retryDelay(response) {
    var header = parseInt(response.headers.get('Retry-After') || '', 10);
    if (Number.isFinite(header) && header > 0) { return Math.min(header * 1000, 15 * 60000); }
    return response.json().then(function (body) {
        var fromBody = body && Number(body.retry_after);
        if (Number.isFinite(fromBody) && fromBody > 0) { return Math.min(fromBody * 1000, 15 * 60000); }
        return 0;
    }, function () { return 0; });
}
fetch(typeof url === 'function' ? url() : url, init).then(function (r) {
    if (r.status === 404) { stopped = true; return null; }
    if (r.status === 429) {
        return Promise.resolve(retryDelay(r)).then(function (wait) {
            if (wait > 0) { backoff = wait; retryAt = Date.now() + wait; }
            else { backoff = backoff === 0 ? interval : Math.min(backoff * 2, 15 * 60000); }
            return null;
        });
    }
    if (!r.ok) { throw new Error('poll failed'); }
    return r.json();
}).then(function (data) {
    if (data) { backoff = 0; retryAt = 0; }
    if (!stopped && !document.hidden && data) {
        var verdict = apply(data);
        if (verdict === false) { stopped = true; }
        else if (typeof verdict === 'number' && verdict >= 0) { nextDelay = verdict; }
    }
}).catch(function () {
    backoff = backoff === 0 ? interval : Math.min(backoff * 2, 15 * 60000);
}).finally(function () {
    busy = false;
    var delay = backoff > 0 ? backoff : nextDelay;
    // A return that arrived mid-request resumes at once, unless the server set a deadline.
    if (resume) { resume = false; schedule(untilRetry()); }
    else { schedule(delay); }
});
```

```javascript
document.addEventListener('visibilitychange', function () {
    clear();
    if (document.hidden) { return; }
    // One immediate fetch on return, unless a Retry-After deadline is still running.
    var wait = untilRetry();
    if (wait > 0) { schedule(wait); } else { run(); }
});
```

A 429 resolves to `null` instead of throwing, so the catch does not overwrite the delay just chosen. `Date.now()` follows `page.clock`, so the browser tests control the deadline too. A 429 with no numeric `Retry-After` and no JSON `retry_after` still doubles from the interval, which is what `polling pauses while hidden and backs off after throttling` asserts for the bell (60s, then 120s) and what `conversation poll backs off...` asserts for a 503.

- [x] **Step 8: Rebuild and run the poller tests**

Run:

```bash
npm run build
npm run check:assets
cd tests/browser && npx playwright test messages-poll-regressions.spec.ts messages-refinement.spec.ts notifications-unified.spec.ts --project=desktop -g "poll|Retry-After|throttl"
```

Expected: PASS, including both new `Retry-After` tests (timer and tab return), `conversation poll backs off, pauses while hidden, resumes immediately and stops on 404`, and `polling pauses while hidden and backs off after throttling`.

- [x] **Step 9: Commit**

```bash
git add config/config.php src/Controller/ConversationController.php public/assets/app.js public/assets/dist config/assets.json docs/runbooks/group_dms.md tests/Integration/Core/AppMessagesRefinementTest.php tests/browser/messages-poll-regressions.spec.ts
git commit -m "fix(messages): rate-limit the conversation poll separately from sends"
```


## Execution notes — 2026-09-23

Completed on `fix/messages-release`, based on current main `703aeb60`, after consolidating `messages-audit`, `ma-lay`, and `ma-distill`.

- The audit's transaction-free empty poll and shared participants read were preserved.
- Fonts, dock resizing and incoming pages share one reading pin. The font promise can resolve before a pending scroll event, so the implementation also checks the current offset against the last followed position. A deterministic regression resolves fonts in the same task as scrolling away.
- The catch-up cap means an initial request plus 20 immediate follow-ups; the prose above is corrected to match the original algorithm. A browser test pins the limit.
- The overlong-body test submits the form directly because the existing enhanced Send button correctly disables overlong input. This reaches the real 422 page and verifies textarea focus.
- Additional checks cover a visible new-message pill during late fonts, Retry-After from JSON, and returning while a throttled request is still in flight.
- The merger prepares the final Imladris digest for verification and applies its baseline refresh immediately after the merge on main, as ADR 0024 requires.

Final results and any pre-existing deferrals are recorded in `docs/evidence/messages-release/2026-09-23/README.md`.
