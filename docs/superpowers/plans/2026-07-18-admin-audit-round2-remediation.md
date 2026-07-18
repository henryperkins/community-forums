# Admin Console Audit Round-2 Remediation — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Remediate the 2026-07-18 round-2 admin-console UX audit: kill the last 500-class input path (over-length moderation reasons/notes), ship the spec-promised deleted-post Restore UI, actually fix F24 (email status honesty), close the minor defects (draft-loss redirects, silent emoji upsert, missing warn idempotency, bulk-selection loss, unvalidated move direction, silent slug rewrites, dead PE hook), unify the `/mod` posture and gate `/mod/approvals`, wire accessible field errors console-wide, align the admin nav with ADMIN.md §9.2, and record the one silent deferral (reports-queue bulk actions) in ADR 0023.

**Architecture:** Vanilla PHP 8.2 forum (RetroBoards). Controller → Service → Repository; validation failures throw `ValidationException(array $errors, array $old = [])` caught by controllers for 422 re-renders with preserved input (anti-draft-loss); plain-PHP templates with `$e()` escaping and strict CSP (no inline JS/CSS); in-process kernel integration tests via `Tests\Support\TestCase`.

**Tech Stack:** PHP 8.3 (dev), MySQL/MariaDB strict mode, PHPUnit 11 (strict), Playwright for browser evidence.

## Global Constraints

- Branch `admin-audit-round2-remediation`; commits only when the user asks.
- **No new migrations.** All fixes are validation/render/routing/docs. If one becomes unavoidable, next number is 0078 + SCHEMA.md hand-update.
- Reason columns are `VARCHAR(255)` utf8mb4 in `warnings`/`bans`/`moderation_log` → cap 255 **characters** (`mb_strlen`); `user_notes.body` is `TEXT` → cap 65,535 **bytes** (`strlen`).
- PHPUnit is strict: ≥1 assertion/test, no output, no warnings. Per-test isolation = one transaction rolled back (no savepoints) → assert observable HTTP behavior. Never bind LIMIT/OFFSET. UTC everywhere.
- Templates: no inline `<script>`/`<style>`; CSRF via `$this->csrfField()`; escape via `$e()`. New CSS goes in `public/assets/app.css`.
- Run tests with the session-private DB to dodge parallel-session collisions: `DB_TEST_DATABASE=retroboards_test_r2 vendor/bin/phpunit …` (add `RB_TEST_FRESH=1` after schema-affecting pulls). `composer test` hits the 300s composer timeout — call phpunit directly.
- Untouchables: `output/admin-dashboard-audit-2026-07-18/`, `.github/prompts/`, `.worktrees/`, the shared `retroboards_e2e` DB.
- `/mod` posture rule being introduced (specs are silent; ADR 0021/PR#44 precedent "404 byte-identical to missing" + ADMIN.md §9.4 "hide what a role can't do"): **browsing a staff surface with zero moderation authority → 404; attempting a staff action without authority → 403.** Recorded in ADR 0023.

---

### Task 1: Length-validate user-moderation reason + note (audit finding 1, major — the 500)

**Files:**
- Modify: `src/Service/UserModerationService.php` (`requireReason` at :775-782; `addNote` at :130-145)
- Modify: `templates/admin/user_record.php` (note textarea ~:167), `templates/mod/user.php` (note textarea ~:129) — client `maxlength` hint
- Test: `tests/Integration/Admin/AppAdminUserRecordTest.php`, `tests/Integration/Core/AppModUserPanelTest.php`

**Interfaces:**
- Consumes: existing `ValidationException(array $errors, array $old = [])`; controller catches already in place (`AdminUserController.php:225-291` via `record()`; `UserModerationController::run()` :98-99 via `panel()`).
- Produces: `requireReason()` throws on `mb_strlen($reason) > 255` with error key `reason` (old input attached); `addNote()` throws on `strlen($body) > 65535` with key `body`. All three of warn/suspend/ban route through `requireReason` (:72, :279, :320), so one guard covers every reason sink including `moderation_log.reason`.

- [ ] **Step 1: Failing tests (admin paths)** — add to `AppAdminUserRecordTest.php`, mirroring `test_suspend_with_invalid_until_is_422_and_preserves_reason` (:284-293):

```php
public function test_warn_reason_over_255_chars_is_422_and_preserves_typed_text(): void
{
    $this->actingAs($this->makeAdmin());
    $sid = (int) $this->makeUser(['username' => 'warnlong'])['id'];
    $long = str_repeat('r', 256);
    $res = $this->post('/admin/users/' . $sid . '/warn', ['reason' => $long]);
    $this->assertStatus(422, $res);
    self::assertStringContainsString($long, $res->body());
    self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE reason = ?', [$long]));
}

public function test_suspend_reason_over_255_chars_is_422_and_user_stays_active(): void
{
    $this->actingAs($this->makeAdmin());
    $sid = (int) $this->makeUser(['username' => 'susplong'])['id'];
    $long = str_repeat('s', 256);
    $res = $this->post('/admin/users/' . $sid . '/suspend', ['reason' => $long, 'until' => '']);
    $this->assertStatus(422, $res);
    self::assertStringContainsString($long, $res->body());
    self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
}

public function test_ban_reason_over_255_chars_is_422_and_user_stays_active(): void
{
    $this->actingAs($this->makeAdmin());
    $sid = (int) $this->makeUser(['username' => 'banlong'])['id'];
    $long = str_repeat('b', 256);
    $res = $this->post('/admin/users/' . $sid . '/ban', ['reason' => $long]);
    $this->assertStatus(422, $res);
    self::assertSame('active', (string) $this->db->fetchValue('SELECT status FROM users WHERE id = ?', [$sid]));
}

public function test_note_body_over_64kb_is_422_and_preserved(): void
{
    $this->actingAs($this->makeAdmin());
    $sid = (int) $this->makeUser(['username' => 'notelong'])['id'];
    $big = str_repeat('n', 65536);
    $res = $this->post('/admin/users/' . $sid . '/note', ['body' => $big]);
    $this->assertStatus(422, $res);
    self::assertStringContainsString('64 KB', $res->body());
    self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM user_notes WHERE user_id = ?', [$sid]));
}
```

- [ ] **Step 2: Failing test (mod path)** — add to `AppModUserPanelTest.php` (reuse its existing warn-form setup): moderator POSTs `/mod/u/{id}/warn` with a 256-char reason (+ valid `board_id`) → assert 422 and reason preserved.
- [ ] **Step 3: Verify RED** — `DB_TEST_DATABASE=retroboards_test_r2 vendor/bin/phpunit tests/Integration/Admin/AppAdminUserRecordTest.php`. Expected: the new tests error with HTTP 500 bodies (PDOException "Data too long"), not 422.
- [ ] **Step 4: Implement** — in `requireReason()` after the empty check (mirror `setTitle` :472-499):

```php
if (mb_strlen($reason) > 255) {
    throw new ValidationException(
        ['reason' => 'Reason must be 255 characters or fewer.'],
        ['reason' => $reason],
    );
}
```

and in `addNote()` after its empty check:

```php
if (strlen($body) > 65535) {
    throw new ValidationException(
        ['body' => 'Note must be 64 KB or smaller.'],
        ['body' => $body],
    );
}
```

- [ ] **Step 5: Client hints** — add `maxlength="65535"` to both note `<textarea>`s.
- [ ] **Step 6: Verify GREEN** — both touched test files pass; `AppModUserPanelScopeTest.php` still green.

### Task 2: Admin-warn idempotency seam (audit finding 3)

**Files:**
- Modify: `src/Controller/AdminUserController.php:225-237` (warn handler)
- Modify: `templates/admin/user_record.php:152-160` (warn form)
- Test: `tests/Integration/Admin/AppAdminUserRecordTest.php`

**Interfaces:**
- Consumes: `UserModerationService::warn(User, int, string, ?int, ?string $idempotencyKey)` (5th arg exists, admin handler currently passes 4); idempotency context `mod_warn` + `DuplicateSubmissionException` replay (model: `UserModerationController.php:100-103`); template seam model: `templates/mod/user.php:99-100`.
- Produces: admin warn double-submit → exactly one `warnings` row, second response replays the success redirect.

- [ ] **Step 1: Failing test:**

```php
public function test_admin_warn_double_submit_records_one_warning_and_replays_redirect(): void
{
    $this->actingAs($this->makeAdmin());
    $sid = (int) $this->makeUser(['username' => 'warnonce'])['id'];
    $key = bin2hex(random_bytes(16));
    $body = ['reason' => 'unique-idem-reason', 'idempotency_key' => $key];
    $first = $this->post('/admin/users/' . $sid . '/warn', $body);
    $second = $this->post('/admin/users/' . $sid . '/warn', $body);
    $this->assertRedirectContains('/admin/users/' . $sid, $first);
    $this->assertRedirectContains('/admin/users/' . $sid, $second);
    self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM warnings WHERE reason = ?', ['unique-idem-reason']));
}
```

- [ ] **Step 2: Verify RED** — second POST inserts a second row (count 2).
- [ ] **Step 3: Template** — inside the warn form after `csrfField()` (port of `mod/user.php:99-100`):

```php
<?php $warnKey = $oldv('warn', 'idempotency_key') !== '' ? $oldv('warn', 'idempotency_key') : bin2hex(random_bytes(16)); ?>
<input type="hidden" name="idempotency_key" value="<?= $e($warnKey) ?>">
```

- [ ] **Step 4: Controller** — pass `$request->str('idempotency_key') ?: null` as the 5th `warn()` arg; add `'idempotency_key' => $request->str('idempotency_key')` to the 422 old array; catch `DuplicateSubmissionException` → return the same success redirect as the normal path.
- [ ] **Step 5: Verify GREEN.**

### Task 3: Deleted-reply stubs + Restore control (audit finding 2, major)

**Files:**
- Modify: `src/Repository/PostRepository.php` (new `listByThreadWithDeleted` + `countByThreadWithDeleted` beside :95-120)
- Modify: `src/Controller/ThreadController.php` (compute `can_restore_posts`; select query variant; pass flags)
- Modify: `templates/thread.php:165-220` (loop branch; grouping treats deleted rows as boundaries)
- Create: `templates/partials/post_deleted.php`
- Modify: `public/assets/app.css` (`.post-deleted` styles)
- Test: create `tests/Integration/Core/AppDeletedPostStubTest.php`

**Interfaces:**
- Consumes: `ModerationService::canModerate($user, $boardId, Cap::POST_RESTORE)` (cap `core.post.restore`, `src/Security/Cap.php:37`); `posts.is_deleted/deleted_by/deleted_at`; existing `POST /mod/p/{id}/restore` (App.php:2375). Delete/restore counters + audit already transactional (`ModerationService.php:143-248`) — untouched.
- Produces: staff (viewers with `can_delete_posts` or `can_restore_posts`) see deleted replies as stubs with preserved content behind a disclosure and a Restore button (gated on `can_restore_posts`); members/guests see byte-identical behavior to today. OP deletes (`purgeThread`, whole-thread) are out of scope → recorded in ADR 0023.

- [ ] **Step 1: Failing tests:**

```php
public function test_staff_see_deleted_reply_stub_with_restore_and_members_do_not(): void
{
    $admin = $this->makeAdmin();
    $author = $this->makeUser();
    $board = $this->makeBoard($this->makeCategory(), ['slug' => 'stub-board']);
    $t = $this->makeThread($board, $author, 'Stub topic', 'Opening body.');
    $this->actingAs($author);
    $this->post('/t/' . $t['thread_id'] . '/reply', ['body' => 'Recoverable reply body.']);
    $pid = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 0', [$t['thread_id']]);
    $this->actingAs($admin);
    $this->post('/posts/' . $pid . '/delete', ['reason' => 'cleanup']);

    $staffView = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
    $this->assertStatus(200, $staffView);
    self::assertStringContainsString('Removed by a warden', $staffView->body());
    self::assertStringContainsString('/mod/p/' . $pid . '/restore', $staffView->body());
    self::assertStringContainsString('Recoverable reply body.', $staffView->body());

    $this->actingAs($this->makeUser(['username' => 'bystander']));
    $memberView = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
    $this->assertStatus(200, $memberView);
    self::assertStringNotContainsString('Removed by a warden', $memberView->body());
    self::assertStringNotContainsString('Recoverable reply body.', $memberView->body());
}

public function test_restore_from_stub_returns_post_to_members(): void
{
    // same seeding; as admin POST '/mod/p/' . $pid . '/restore', follow with member GET →
    // body visible again, no stub marker, reply_count back to 1 in header.
}
```

(Exact reply-POST route/body field: mirror whatever `AppModeratorScopeTest.php` uses to create a reply — reuse its seeding pattern verbatim.)
- [ ] **Step 2: Verify RED** — stub marker absent for staff (deleted rows filtered at SQL).
- [ ] **Step 3: Repository** — duplicate `listByThread`/`countByThread` minus the `is_deleted = 0` predicate (keep `is_pending = 0`); ensure the SELECT list includes `deleted_at`, `deleted_by` (add to both variants if absent).
- [ ] **Step 4: Controller** — beside `can_delete_posts` (:217-218):

```php
$canRestorePosts = $user !== null
    && $this->container->get(ModerationService::class)->canModerate($user, (int) $thread['board_id'], Cap::POST_RESTORE);
$includeDeleted = $canDeletePosts || $canRestorePosts;
```

Use `…WithDeleted` repo variants for both count and list when `$includeDeleted`; pass `'can_restore_posts' => $canRestorePosts` to the view.
- [ ] **Step 5: Template branch** — in the `thread.php` loop, before the regular partial:

```php
<?php if ((int) ($p['is_deleted'] ?? 0) === 1): ?>
    <?= $this->partial('partials/post_deleted', ['p' => $p, 'can_restore_posts' => $can_restore_posts ?? false]) ?>
    <?php continue; ?>
<?php endif; ?>
```

and exclude deleted rows from the author-grouping run (treat as boundary). New `templates/partials/post_deleted.php`:

```php
<?php
$author = mask_author($p['display_name'] ?? null, $p['username'] ?? null, $p['role'] ?? 'user', (int) ($p['is_anonymous'] ?? 0) === 1);
?>
<article class="post post-deleted" id="p<?= (int) $p['id'] ?>">
    <div class="post-main">
        <div class="post-head">
            <span class="post-author"><?= $e($author['label']) ?></span>
            <span class="badge">Removed by a warden</span>
            <?php if (!empty($p['deleted_at'])): ?><span class="post-time"><?= $e(human_datetime((string) $p['deleted_at'])) ?></span><?php endif; ?>
        </div>
        <details class="post-native-disclosure">
            <summary class="linkbtn">Show removed content</summary>
            <div class="post-body formatted-content post-deleted-body"><?= $p['body_html'] ?></div>
        </details>
        <?php if (!empty($can_restore_posts)): ?>
            <form method="post" action="/mod/p/<?= (int) $p['id'] ?>/restore" class="post-deleted-restore">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit">Restore</button>
            </form>
        <?php endif; ?>
    </div>
</article>
```

(Field names in `$p` must match the repo SELECT — verify the author join provides `display_name`/`username`/`role`; if the moderation list query lacks them, mirror `listByThread`'s join.)
- [ ] **Step 6: CSS** — `.post-deleted` on the `.pending-banner` pattern (`app.css:1318-1321`): muted border, `--surface-3` background, reduced opacity on `.post-deleted-body`.
- [ ] **Step 7: Verify GREEN**; run `AppModeratorScopeTest.php`, `AppThreadUxAuditTest.php`, `AppModerationTest.php` for regressions.

### Task 4: F24 — email status block, one line per fact

**Files:**
- Modify: `templates/admin/email.php:20-39`
- Test: extend the existing `/admin/email` coverage (locate via grep; else create `tests/Integration/Admin/AppEmailStatusCopyTest.php`)

**Interfaces:**
- Consumes: `mailer_configured` (`Mailer::isConfigured()`), `mail_from` (`config mail.from`), `domain_status` (`EmailDomainVerifier::current()`), `send_blocked` — all already in the model (`EmailOpsService.php:63-66`). Under the `array` driver `mailer_configured` is always true while `mail_from` may be empty — the exact F24 combo.
- Produces: three independent status lines (transport / from / domain); the string "Sending is configured" no longer renders when no From is set.

- [ ] **Step 1: Failing test** — as admin (email flag on), GET `/admin/email`: assert body contains `Transport:` and `From address:` and `fails closed` (default test config has no `mail.from`), and does NOT contain `Sending is configured`.
- [ ] **Step 2: Verify RED.**
- [ ] **Step 3: Implement** — replace the `:20-26` if/else with:

```php
<ul class="email-status-facts">
    <li><strong>Transport:</strong> <?= empty($mailer_configured) ? 'not configured — outbound email is skipped' : 'configured — the delivery worker drains queued mail' ?></li>
    <li><strong>From address:</strong> <?php if (($mail_from ?? '') !== ''): ?><code><?= $e($mail_from) ?></code><?php else: ?>not set — email fails closed (messages are skipped) until a From address is configured<?php endif; ?></li>
    <li><strong>Sending domain:</strong> <?php $domain = $domain_status ?? []; if (($domain['domain'] ?? '') === ''): ?>not detected — set a From address, then refresh SPF/DKIM<?php elseif (!empty($send_blocked)): ?><code><?= $e($domain['domain']) ?></code> — blocked until SPF and DKIM pass<?php elseif (!empty($domain['allowed'])): ?><code><?= $e($domain['domain']) ?></code> — verified<?php else: ?><code><?= $e($domain['domain']) ?></code> — checks not enforced (ADR 0008 opt-in)<?php endif; ?></li>
</ul>
```

Keep the `send_blocked` alert flash; align the third line's vocabulary with the domain card below it (read `email.php:37-80` when editing). Add a minimal `.email-status-facts` rule to `app.css` (no bullets, small vertical rhythm).
- [ ] **Step 4: Verify GREEN.**

### Task 5: Form-defect cluster (audit findings 4, 6, 8, 9 + dead hook)

**E1 — custom emoji: 422 re-render + honest replace flash** (`AdminCustomEmojiController.php:17-27`, `CustomEmojiService.php:22-60`, `templates/admin/dashboard.php:111-179`).
- [ ] Failing tests: (a) invalid shortcode → 422, typed name/shortcode present in body, no redirect; (b) duplicate shortcode → success flash says `replaced` (assert flash cookie/redirect then follow to `/admin` and see "replaced"); (c) fresh shortcode → "Custom emoji saved."
- [ ] Service: before the upsert, `$existing = $this->db->fetchValue('SELECT shortcode FROM custom_emoji WHERE shortcode = ?', [$shortcode]);` return bool `replaced` from `create()`.
- [ ] Controller: `ValidationException` → re-render the dashboard at 422 with `emoji_errors`/`emoji_old` (dashboard model comes from `AdminDashboardService::dashboardModel()` — reuse it; add the two keys). Success: `redirectWithFlash('/admin', $replaced ? 'Custom emoji replaced — :' . $shortcode . ': already existed.' : 'Custom emoji saved.')` (service returns the normalized shortcode too, or re-normalize in controller).
- [ ] Template: `value="<?= $e($emoji_old['shortcode'] ?? '') ?>"` etc. + `field_error($emoji_errors, 'shortcode')` lines (helper from Task 8; if Task 8 not yet merged, inline `<p class="field-error">`).

**E2 — email suppress/unsuppress 422 re-render** (`AdminEmailController.php:31-84`, `templates/admin/email.php:148-180`).
- [ ] Failing test: suppress with a syntactically invalid address → 422 + typed address preserved in the input.
- [ ] Extract `private function emailView(array $extra = [], int $status = 200): Response` building the `EmailOpsService::dashboardModel(...)` exactly as `index()` does; `index()` delegates; both catch blocks return `$this->emailView(['suppress_errors' => $e->errors, 'suppress_old' => ['email' => $request->str('email')]], 422)`.
- [ ] Template: suppress input gains `value="<?= $e($suppress_old['email'] ?? '') ?>"` + error line.

**E3 — deputy roster username preservation** (`AdminController.php:307-320`, deputy branch :315).
- [ ] Failing test: non-admin board moderator (deputy) POSTs add-member with unknown username → 422 (not 303) + typed username preserved on the re-rendered surface.
- [ ] Read `rosterDeputyExit()` + the deputy surface it points at; re-render THAT surface with `roster_error`/`roster_username` at 422 instead of redirecting (keep the admin-console isolation comment's intent — never render `board_edit` to a non-admin). If the deputy surface is a board page section, add the two vars to its render path.

**E4 — bulk-users selection preservation** (`AdminUserController.php:50-64,341-350`, `templates/admin/users.php:117-119`).
- [ ] Failing test: POST bulk with two `selected[]` ids and empty `bulk_action` → 422 and both `value="{id}"` checkboxes carry `checked`.
- [ ] `directoryView(Request $request, ?string $bulkError = null, int $status = 200, array $selectedIds = [])` → `'bulk_selected' => $selectedIds`; pass `$ids` at the "Choose a bulk action" call. Template: `<?= in_array((int) $u['id'], $bulk_selected ?? [], true) ? ' checked' : '' ?>`.

**E5 — direction whitelist** (`AdminService::moveBoard` :519-533, `moveCategory` :~500, `swap` :574-586).
- [ ] Failing tests: `dir=sideways` on board move → 422 with `reorder_error` and `sort_order` values unchanged; same for category move.
- [ ] At the top of both public methods: `if (!in_array($dir, ['up', 'down'], true)) { throw new ValidationException(['dir' => 'Direction must be "up" or "down".']); }` (controller catch at `AdminController.php:398-420` already renders 422).

**E6 — dead hook** (`templates/admin/providers.php:41`): drop the `data-sole-count` attribute (zero JS references confirmed). Covered by existing providers tests staying green.

### Task 6: Copy & slug honesty (audit findings 5, 10)

**F1 — grammar** (`AdminDashboardService.php:183-188`): build the sentence with a count variable — `$n . ' Thread Intelligence warning' . ($n === 1 ? '' : 's') . ($n === 1 ? ' needs' : ' need') . ' operator review.'` Test: seed `warning_count = 1` path if a seam exists; otherwise assert via the service model directly (unit-style on the service with a stub TI model is acceptable — but prefer the HTTP assertion if the TI fixture is cheap).

**F2 — Users card labeled truthfully** (`AdminDashboardService.php:111-116`): `'title' => 'New users today'` (count stays `new_users_today`, detail stays). Test: admin dashboard body contains `New users today`.

**F3 — humanized 429 wait** (`src/Support/helpers.php` + `RateLimitService.php:57-58`):

```php
function human_duration(int $seconds): string
{
    $seconds = max(1, $seconds);
    if ($seconds < 60) {
        return $seconds . ' second' . ($seconds === 1 ? '' : 's');
    }
    $minutes = intdiv($seconds + 59, 60); // round up — never promise a shorter wait than real
    if ($minutes < 60) {
        return 'about ' . $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    $hours = intdiv($minutes, 60);
    $rem = $minutes % 60;
    return 'about ' . $hours . ' hour' . ($hours === 1 ? '' : 's') . ($rem > 0 ? ' ' . $rem . ' minute' . ($rem === 1 ? '' : 's') : '');
}
```

`RateLimitService`: `"You're doing that too quickly. Please wait " . human_duration($wait) . " and try again."` Unit tests for the helper boundaries (59s, 60s, 3473s → "about 58 minutes", 7200s). Grep tests for the old `second(s)` copy and update expectations.

**F4 — explicit board slug conflicts are 422, derived slugs keep auto-suffix** (`AdminService::validateBoard` ~:790-848, `uniqueSlug` :850-859): when `$rawSlug !== ''` and `slugTaken(Str::slug($rawSlug, 64), $boardId)` → `$errors['slug'] = 'That slug is already in use or reserved.'` (joins the existing errors→`ValidationException` flow; `structure.php`/`board_edit.php` already render slug field-errors + old). Blank slug keeps today's derive-and-suffix. Tests: explicit taken slug on create → 422 + no board row; blank slug with duplicate name → succeeds as `name-2`; edit form same-slug-self → still saves.

**F5 — explicit tag slug over 64 chars is 422** (`TagController::validateTag` :257-260): `if ($rawSlug !== '' && mb_strlen($rawSlug) > 64) { throw new ValidationException(['slug' => 'Tag slug must be 64 characters or fewer.']); }` before the `Str::slug` call. Test: 300-char slug → 422 + field error (contrast: name >80 already 422s).

### Task 7: /mod posture unification + missing flag gate (audit finding 7)

**Files:**
- Modify: `src/Controller/ApprovalController.php` (flag gate + queue 404), `src/Service/AppealService.php:269-276` (queue 404), `src/Controller/UserModerationController.php:141-151` (+ surface variant)
- Modify: `src/Service/AdminDashboardService.php:105-110,157-161` (gate the Approval-hold pointers)
- Test: `tests/Integration/Core/AppFeatureFlagTest.php` (+ table row), new `tests/Integration/Core/AppModPostureTest.php`

**Interfaces:**
- Rule (Global Constraints): zero-authority browse → 404; unauthorized action → 403. Guests keep the 302→login behavior where `requireUser` runs first.
- `/mod/t`/`/mod/p` POST actions stay 403 (pinned by `AppFeatureFlagTest.php:336`).
- F39 safety: `App::shareViewGlobals` `moderation_access` block (App.php:643-664) and `errors/error.php:10-12` untouched.

- [ ] **Step 1: Failing tests:**

```php
public function test_mod_queue_surfaces_are_uniformly_404_for_non_staff(): void
{
    $this->actingAs($this->makeUser());
    $subject = (int) $this->makeUser()['id'];
    $this->assertStatus(404, $this->get('/mod/reports'));
    $this->assertStatus(404, $this->get('/mod/approvals'));
    $this->assertStatus(404, $this->get('/mod/appeals'));
    $this->assertStatus(404, $this->get('/mod/u/' . $subject));
}

public function test_mod_approvals_goes_dark_with_moderation_queue_off(): void
{
    // actingAs admin; setFlags(['moderation_queue' => false]);
    // assertStatus(404, GET /mod/approvals); assertStatus(404, POST /mod/approvals/post/1/approve);
    // assertStatus(200, GET '/') — core stays up.
}

public function test_mod_actions_still_403_for_non_staff_when_flag_on(): void
{
    // member POST /mod/approvals/post/{realPendingPostId}/approve → 403.
}
```

Plus add `'/mod/approvals'` under `moderation_queue` in the table-driven `test_disabling_a_flag_takes_its_get_routes_offline_but_keeps_core_up` (:874-903).
- [ ] **Step 2: Verify RED** — approvals/appeals//mod/u return 403 not 404; flag-off approvals returns 200.
- [ ] **Step 3: ApprovalController** — add `FeatureFlags` gate helper throwing `NotFoundException` when `moderation_queue` off; call it first in `queue()` + all four POST handlers; in `queue()` change the zero-authority `ForbiddenException` (:49-51) to `NotFoundException('Not found.')` — POST handlers keep `ForbiddenException`.
- [ ] **Step 4: AppealService** — `queue`'s zero-authority throw (:273-275) becomes `NotFoundException('Not found.')` (verify call sites: staff queue GET only; `resolve` keeps its own 403s).
- [ ] **Step 5: UserModerationController** — add `requireStaffSurface()` (same checks as `requireStaff` but throwing `NotFoundException`); `show()` uses it; POST `run()` keeps `requireStaff` (403).
- [ ] **Step 6: Dashboard pointers** — Approval-hold card `'href' => $reportsEnabled ? '/mod/approvals' : null` with an off-state detail mirroring the Reports card's disabled copy; attention entry gains `$reportsEnabled &&`.
- [ ] **Step 7: Verify GREEN** — plus `AppFeatureFlagTest` whole file, appeals + moderator-scope suites.

### Task 8: Shared accessible field-error helper + a11y pockets (audit finding 11)

**Files:**
- Modify: `src/Support/helpers.php` (two new helpers), `public/assets/app.css` (`.visually-hidden` if absent)
- Modify (sweep): every per-field `field-error` site listed below
- Test: new `tests/Integration/Admin/AppFieldErrorA11yTest.php`

**Helpers** (escaping inline — helpers have no `$e`):

```php
function field_error(array $errors, string $field, ?string $id = null): string
{
    $message = $errors[$field] ?? null;
    if ($message === null || $message === '') {
        return '';
    }
    $id ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    return '<p class="field-error" id="' . $esc($id) . '">' . $esc((string) $message) . '</p>';
}

function field_attrs(array $errors, string $field, ?string $id = null): string
{
    if (empty($errors[$field])) {
        return '';
    }
    $id ??= 'err-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $field);
    $esc = static fn (string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $focus = array_key_first($errors) === $field ? ' autofocus' : '';
    return ' aria-invalid="true" aria-describedby="' . $esc($id) . '"' . $focus;
}
```

Per-input wiring pattern (`field_attrs` inside the input tag, `field_error` replacing the hand-rolled `<p>/<span>`):

```php
<input class="input" type="text" name="name" value="<?= $e($old['name'] ?? '') ?>"<?= field_attrs($errors, 'name') ?> required>
<?= field_error($errors, 'name') ?>
```

- [ ] **Step 1: Failing test** — tag create with 81-char name → 422 body contains `aria-invalid="true"`, `aria-describedby="err-name"`, `id="err-name"`, `autofocus`; suspend with bad `until` → context-scoped id `err-suspend-until` wired (user_record's `$ferr`/`$oldv` closures delegate to the helpers with `'err-' . $context . '-' . $field` ids and a matching `$fattr($context, $field)` closure).
- [ ] **Step 2: RED, then implement helpers + wire the exemplar templates (tags.php, user_record.php), GREEN.**
- [ ] **Step 3: Mechanical sweep** — convert every per-field site (same pattern; context-scoped templates use explicit ids):
  - Variant A (`<p>`): `api_tokens.php`, `announcements.php`, `badge_rules.php`, `branding.php`, `invitations.php`, `providers.php`, `provider_disable.php` (:42,49 field ones), `registries.php`, `roles.php`, `role_edit.php` (context ids), `package_publisher.php`, `_package_integration.php`, `package_security.php`, `webhooks.php`, `webhook_detail.php` (context ids), `users_bulk_confirm.php`, `dashboard.php:60` + settings selects, `mod/appeals.php` (errors+old form)
  - Variant B (`<span>` → helper `<p>`): `board_edit.php`, `structure.php` (board form), `audit.php`
- [ ] **Step 4: Pockets** (all enumerated by the survey):
  - `package_publisher.php:25,26,32,39,65,66,88,100` + `_package_integration.php:112` — add `aria-label` to placeholder-only inputs (repo precedent: `board_edit.php:107`).
  - `package_publisher.php:53` + `packages.php:32` — empty `<th>` → `<th scope="col"><span class="visually-hidden">Actions</span></th>`; add `scope="col"` to sibling `<th>`s; add `.visually-hidden` utility to `app.css` if missing.
  - `provider_disable.php:29-35` — wrap table in `<div class="table-scroll" role="region" aria-label="Sole-login accounts">` (pattern: `packages.php:30`) + `scope="col"`.
  - `structure.php:16,85,99`, `board_edit.php:11`, `structure_confirm.php:25,31` — add `role="alert"` to the `flash flash-error` divs.
  - Bespoke pagers `mod/reports.php:123`, `admin/audit.php:114`, `admin/email.php:138`, `admin/users.php:159`, `feed.php:34` — `aria-label` on each `<nav class="pager">`.
  - Mod-queue row buttons: `reports.php:107-118` → `aria-label="Claim report #{id}"` / `Resolve` / `Dismiss` + `aria-label="Warn <?= $e($author['label']) ?>"` using the existing `mask_author` output (anonymity preserved); `approvals.php:42-45,65-68` → `aria-label="Approve thread '<?= $e($t['title']) ?>'"` etc., replies keyed on `thread_title`; `appeals.php:74` → `aria-label="Resolve appeal #<?= (int) $appeal['id'] ?>"`.
- [ ] **Step 5: Full-suite spot check** — every touched template has integration coverage; run the admin + mod suites.

### Task 9: Console IA — grouped nav, Moderation entries, Appeals card, orphan links (audit findings 12, 13-partial)

**Files:**
- Modify: `templates/admin/_nav.php` (grouped rewrite), `public/assets/app.css` (`.subnav-group*` styles, desktop + 390px)
- Modify: `src/Repository/ModerationAppealRepository.php` (`openCount()`), `src/Service/AdminDashboardService.php` (appeals count/card/attention)
- Modify: `templates/admin/roles.php:23-29` + `templates/admin/packages.php:14` (orphan links)
- Test: extend dashboard/nav integration tests + `AppFieldErrorA11yTest` stays green

**§9.2 mapping decision** (spec's People "Approval queue" = the deferred *registration* approvals, ADR 0021 #3 — content approval-hold belongs to Moderation, whose spec row includes "approvals"):

- Dashboard → dashboard · **Moderation** → Reports queue (`/mod/reports`, flag `moderation_queue`) · Approvals (`/mod/approvals`, flag `moderation_queue`) · Appeals (`/mod/appeals`, flag `appeals`) · Audit log · Thread Intelligence (flags_any) · **Content** → Boards & categories · Tags · **People** → Users · Roles · Badge rules · **Appearance** → Branding · Themes · **Notifications** → Email · Announcements · **Integrations** → Packages · Registry trust · Webhooks · API tokens · Extensions · **Settings** → Feature flags · Sign-in providers · Invitations

- [ ] **Step 1: Failing tests** — admin GET `/admin`: body contains `subnav-group-label` markers for `Moderation`, `Content`, `People`, links to `/mod/reports` + `/mod/appeals` inside the admin nav, and an `Appeals` dashboard card; with `appeals` flag off the Appeals nav entry renders as the disabled-span pattern (assert `Disabled until the feature flag is enabled` count grows by one).
- [ ] **Step 2: RED → rewrite `_nav.php`** as a `$groups` array preserving the exact item schema (`key/label/href/flag/flags_any`) and the current anchor/disabled-span rendering (keep `$disabledNote` copy verbatim — regression tests reference it); render:

```php
<nav class="subnav admin-subnav" aria-label="Admin navigation">
    <?php foreach ($groups as $group): ?>
        <div class="subnav-group">
            <span class="subnav-group-label"><?= $e($group['label']) ?></span>
            <?php foreach ($group['items'] as $item): ?>…existing enabled/disabled rendering, now honoring both `flag` and `flags_any`…<?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</nav>
```

- [ ] **Step 3: Appeals card** — `ModerationAppealRepository::openCount(): int` (`COUNT(*)` on the open status used by `openQueue`, :90); `AdminDashboardService`: `$appealsEnabled = $this->flags->enabled('appeals')` (flags are already injected for `$reportsEnabled`), `'open_appeals'` count, card `['title' => 'Appeals', 'count' => …, 'detail' => $appealsEnabled ? 'Open moderation appeals' : 'Appeals disabled', 'href' => $appealsEnabled ? '/mod/appeals' : null]`, attention entry when `> 0 && $appealsEnabled`.
- [ ] **Step 4: Orphan links** — `roles.php` intro `<p class="muted">`: append `Try changes in the <a href="/admin/roles/simulator">permission simulator</a>.`; `packages.php:14` intro: append `Emergency controls live in the <a href="/admin/packages/security">package security console</a>.` (pattern: `package_security.php:14`).
- [ ] **Step 5: CSS** — `.subnav-group` (flex column min-width), `.subnav-group-label` (small-caps muted), 390px media rule keeping groups stacked; verify no horizontal scroll at 390px.
- [ ] **Step 6: GREEN** + `AppFeatureFlagTest` nav-related assertions still pass.

### Task 10: Docs — ADR 0023, round-2 disposition history, stale-doc corrections

- [ ] **ADR `docs/adr/0023-admin-console-audit-round-2.md`** (mirror 0021's shape): context (round-2 audit of 2026-07-18); decisions shipped (tasks 1-9 summary); **owned deferrals**: (1) reports-queue bulk actions (ADMIN.md §3.2 fourth bullet) — needs selection UI + per-item audited bulk transaction; (2) thread-level (OP) restore surface — `purgeThread` reversal has no route/UI; (3) any a11y sweep remainder stated honestly; **recorded decision**: the `/mod` posture rule (404 surfaces / 403 actions) since the specs are silent.
- [ ] **History doc `docs/history/admin-ux-audit-round2-2026-07-18.md`**: intro naming the source audit; `| # | Finding | Disposition |` table for round-2 findings 1-13 (bare numbers, convention) with evidence pointers (test names); an **F24 correction row** (prior "Already fixed pre-remediation" disposition was wrong — fixed now, task 4); audit-premise correction note (the un-gated Approval-hold card kept a live pointer when `moderation_queue` was off — both sides now gated); environment note (private `retroboards_test_r2` DB used due to parallel-session collisions).
- [ ] **CLAUDE.md accuracy fix**: "bootstrap.php drops + re-migrates on every run" → describe the fingerprint reuse + `RB_TEST_FRESH=1` recovery (one sentence).

### Task 11: Verification (DESIGN §13)

- [ ] Full suite green: `DB_TEST_DATABASE=retroboards_test_r2 RB_TEST_FRESH=1 vendor/bin/phpunit` (compare against the pre-change baseline log in the session scratchpad).
- [ ] Browser verification on a private stack (never `retroboards_e2e`): `DB_DATABASE=retroboards_audit php -S 127.0.0.1:8012 -t public public/index.php` (schema is current — no new migrations); drive: over-length warn → 422 with preserved reason; deleted reply → stub + Restore round-trip; `/admin/email` three-fact status; grouped nav + Appeals card; `dir=sideways` rejected; 390px nav pass.
- [ ] Evidence spec `tests/browser/admin-audit-r2.spec.ts` writing `docs/evidence/browser/<project>/r2-*.png` (deleted-stub + restore, email status, grouped nav + appeals card) — wired like `admin-remediation.spec.ts` (`info.project.name` pathing); run it against a private prep DB if `prepare.sh` env-overrides allow, else record the gap honestly in the history doc.
- [ ] `/code-review` pass over the diff; fix confirmed findings; report.

## Self-Review

- **Spec coverage:** audit findings 1→Task 1, 2→Task 3, 3→Task 2, 4→Task 5/E4, 5→Task 6/F4-F5, 6→Task 5/E5, 7→Task 7, 8→Task 5/E1-E3+E6, 9→Task 5/E1, 10→Task 6/F1-F3, 11→Task 8, 12→Task 9, 13→Task 9 + ADR (bulk actions), F24→Task 4. ✔
- **Placeholder scan:** none — every step names files/lines and shows code or an exact enumerated transformation. Deputy-surface re-render (E3) and evidence-spec DB prep are flagged as read-at-implementation points with fallbacks, not TBDs. ✔
- **Type consistency:** `field_error/field_attrs` signatures match at definition and all call sites; `directoryView` keeps its existing signature with appended optional params; `warn()` 5-arg shape confirmed against the service. ✔
