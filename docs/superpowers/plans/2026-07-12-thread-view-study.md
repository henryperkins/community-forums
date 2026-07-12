# Thread View — The Study Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the RetroBoards topic page as the approved Imladris “Study” reading surface while preserving every existing server contract, capability gate, and no-JavaScript path.

**Architecture:** Keep one authoritative server-rendered copy of every form. `ThreadController` prepares display-only author-title data; focused PHP partials own status history, Topic tools, split/merge, and post actions; `thread.php` composes the quiet surface. `app.js` progressively lifts those same forms into a drawer, bottom sheet, menus, and modal, while `app.css` supplies the high-fidelity Imladris presentation.

**Tech Stack:** PHP 8.2+, MySQL/MariaDB, PHPUnit, plain PHP templates, vanilla ES5-compatible JavaScript, tokenized CSS, Playwright 1.61.1, axe-core.

## Global Constraints

- `DECISIONS.md` wins over all other specifications; the approved design is `docs/superpowers/specs/2026-07-12-thread-view-study-design.md`.
- The normative source is `docs/design-system/imladris/templates/thread-view/ThreadView.dc.html`; read its source, but do not execute or copy its runtime into production.
- Preserve strict CSP: no inline `<script>` or `<style>`, no new external runtime, and no `unsafe-inline`.
- Preserve POST + CSRF for every mutation. Do not add routes, migrations, feature flags, or client-owned write state.
- Capability booleans and `WriteGate` determine controls; never replace them with generic role checks.
- Anonymous authors remain publicly masked after the separate audited reveal action.
- Keep the real reaction set and custom emoji; do not introduce the prototype’s fictional reaction names.
- Keep the existing global top bar and shared composer engine.
- Without JavaScript, every authorized form must remain reachable exactly once.
- With JavaScript, desktop Topic tools is at most 392px wide; split/merge is at most 600px wide; the reading column is at most 860px wide.
- At 768px and below, Topic tools is a bottom sheet capped at 86dvh, split/merge becomes a full-screen sheet, and interactive targets are at least 44×44 CSS pixels.
- Use only existing Imladris tokens, font stacks, icon partials, radii, shadows, and motion tokens.
- Preserve the user’s unrelated dirty-worktree files: `dmprivatecounselregister.bundle`, `dmprivatecounselregister.patch`, `.agents/`, `.claude/settings.local.json`, `AGENTS.md`, and `docs/tech-debt/`.

## File Structure

### Create

- `templates/partials/thread_status_history.php` — one reusable read-only ledger renderer for guests and signed-in Standing.
- `templates/partials/thread_tools.php` — capability-driven watch, standing, tags, memory, and management sections.
- `templates/partials/thread_restructure.php` — the single split/merge form copy used by no-JS and the enhanced modal.
- `templates/partials/post_toolbar.php` — post toolbar, overflow menu, and enhanced/no-JS action disclosures.
- `tests/Integration/Core/AppThreadViewStudyTest.php` — server-rendered layout, privacy, role/capability, and display-model contract.
- `tests/browser/thread-view-study.spec.ts` — drawer, sheet, post toolbar, modal, quote, focus, reduced-motion, and screenshot evidence.

### Modify

- `src/Repository/PostRepository.php` — select `users.title` with thread posts.
- `src/Controller/ThreadController.php` — resolve cosmetic title labels and pass the existing data model to focused partials.
- `templates/thread.php` — quiet header, cards, stream, tool partials, and day dividers.
- `templates/partials/post.php` — quiet row structure, real title chip, and toolbar partial.
- `templates/partials/composer.php` — thread identity strip/classes only; preserve fields and action.
- `templates/partials/living_brief.php` — Study card hooks while retaining disclosure and attribution.
- `templates/partials/thread_memory_tools.php` — support embedded rendering without duplicating forms.
- `public/assets/app.js` — idempotent thread enhancement for canonical and dynamically inserted Inbox views.
- `public/assets/app.css` — Study layout, drawer/sheet/modal, posts, toolbar, composer, themes, and motion.
- `tests/Integration/Core/AppImladrisFidelityTest.php` — replace obsolete workflow/split surface assertions.
- `tests/Integration/Core/AppThreadUxAuditTest.php` — preserve moderator action assertions under the new toolbar copy.
- `tests/browser/gate-a.spec.ts` — drive workflow and split/merge through Topic tools and refresh screenshots.
- `tests/browser/a11y.spec.ts` — scan the new drawer and restructure modal.
- `tests/browser/thread-intelligence.spec.ts` — reach curator forms through Living Brief tools.
- `tests/browser/community-inbox-theme.spec.ts` — verify dynamically inserted thread controls.
- `tests/browser/package.json` — include the focused spec in the standard evidence command.
- `tests/browser/README.md` and `docs/evidence/browser/README.md` — document the new evidence surfaces.
- `docs/evidence/browser/desktop/*.png` and `docs/evidence/browser/mobile/*.png` — generated evidence only.

---

### Task 1: Author cosmetic-title display model

**Files:**
- Create: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Modify: `src/Repository/PostRepository.php:74-90`
- Modify: `src/Controller/ThreadController.php:10-33,91-99`
- Modify: `templates/partials/post.php:43-56`

**Interfaces:**
- Consumes: `TitleService::resolve(?string $override, int $reputation): string`.
- Produces: each visible post carries `author_title` from SQL and `author_title_label` from the controller; anonymous posts receive `null`.

- [ ] **Step 1: Write the failing title/privacy integration test**

Create the test class and first method:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppThreadViewStudyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_post_titles_use_the_real_title_service_and_stay_hidden_for_anonymous_posts(): void
    {
        $author = $this->makeUser([
            'username' => 'study_title_author',
        ]);
        $anonymous = $this->makeUser([
            'username' => 'study_hidden_title',
        ]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Archivist', 5, $author['id']]);
        $this->db->run('UPDATE users SET title = ?, reputation = ? WHERE id = ?', ['Secret Warden', 1000, $anonymous['id']]);
        $board = $this->makeBoard($this->makeCategory('Study Titles'), ['allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $author, 'Titles remain cosmetic', 'Opening record.');
        $this->posting()->reply($this->userEntity($anonymous), (int) $thread['thread_id'], [
            'body' => 'A masked contribution.',
            'is_anonymous' => 1,
        ]);

        $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

        $this->assertStatus(200, $page);
        self::assertStringContainsString('data-author-title="Archivist"', $page->body());
        self::assertStringNotContainsString('Secret Warden', $page->body());
        self::assertStringNotContainsString('data-author-title="Legend"', $page->body());
    }
}
```

- [ ] **Step 2: Run the test and verify the intended failure**

Run:

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php --filter test_post_titles_use_the_real_title_service_and_stay_hidden_for_anonymous_posts
```

Expected: FAIL because `data-author-title="Archivist"` is absent.

- [ ] **Step 3: Select and resolve the title without changing authority**

Extend the post query:

```php
'SELECT p.*, u.username AS author_username, u.display_name AS author_display_name, u.role AS author_role,
        u.signature AS author_signature, u.reputation AS author_reputation, u.title AS author_title
 FROM posts p
 JOIN users u ON u.id = p.user_id
 WHERE p.thread_id = :thread_id AND p.is_deleted = 0 AND p.is_pending = 0
 ORDER BY p.created_at ASC, p.id ASC
 LIMIT ' . $limit . ' OFFSET ' . $offset
```

Import `App\Service\TitleService` in `ThreadController`, then immediately after `listByThread()`:

```php
$titleService = $this->container->get(TitleService::class);
foreach ($posts as &$post) {
    $post['author_title_label'] = (int) ($post['is_anonymous'] ?? 0) === 1
        ? null
        : $titleService->resolve(
            isset($post['author_title']) ? (string) $post['author_title'] : null,
            (int) ($post['author_reputation'] ?? 0),
        );
}
unset($post);
```

Add the minimal semantic hook beside the author in `post.php`:

```php
<?php if (!$isAnon && ($p['author_title_label'] ?? null) !== null): ?>
    <span class="post-title-chip" data-author-title="<?= $e($p['author_title_label']) ?>"><?= $e($p['author_title_label']) ?></span>
<?php endif; ?>
```

- [ ] **Step 4: Run focused and repository tests**

Run:

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php
vendor\bin\phpunit tests/Integration/Core/AppImladrisFidelityTest.php --filter 'grouped|participant|conversation'
```

Expected: PASS with no warnings or risky tests.

- [ ] **Step 5: Commit Task 1 only**

```powershell
git add -- tests/Integration/Core/AppThreadViewStudyTest.php src/Repository/PostRepository.php src/Controller/ThreadController.php templates/partials/post.php
git commit -m "feat: expose cosmetic titles on thread posts"
```

---

### Task 2: Quiet header, canonical status, and basic Topic tools

**Files:**
- Modify: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Modify: `src/Controller/ThreadController.php:373-426`
- Create: `templates/partials/thread_status_history.php`
- Create: `templates/partials/thread_tools.php`
- Modify: `templates/thread.php:16-224`
- Modify: `tests/Integration/Core/AppImladrisFidelityTest.php:369-389`

**Interfaces:**
- Consumes: existing `workflow_on`, `status_labels`, `status_history`, `assignment`, `my_snooze`, `subscription`, `thread_tags`, `all_tags`, `can_edit_tags`, and `current_user` view data.
- Produces: `can_write` from the controller's existing `$canWriteUser`, `data-thread-study`, one `data-thread-status`, `data-topic-tools-open`, one `data-topic-tools`, and `data-topic-tools-section` hooks.

- [ ] **Step 1: Add failing guest/member/status tests**

Append these methods to `AppThreadViewStudyTest`:

```php
public function test_guest_keeps_the_public_ledger_without_write_tools(): void
{
    $author = $this->makeUser(['username' => 'study_guest_author']);
    $board = $this->makeBoard($this->makeCategory('Study Guest'));
    $thread = $this->makeThread($board, $author, 'The public ledger remains', 'Opening record.');

    $this->actingAs($author);
    $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/status', [
        'status' => 'needs_answer',
        'reason' => 'Awaiting counsel',
    ]));
    $this->logoutClient();

    $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    $this->assertStatus(200, $page);
    self::assertStringContainsString('data-thread-study', $page->body());
    self::assertStringContainsString('data-thread-status="needs_answer"', $page->body());
    self::assertStringContainsString('data-thread-status-history', $page->body());
    self::assertStringContainsString('Awaiting counsel', $page->body());
    self::assertStringNotContainsString('data-topic-tools-open', $page->body());
    self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/status"', $page->body());
    self::assertStringNotContainsString('class="workflow-bar', $page->body());
}

public function test_member_gets_basic_topic_tools_but_no_moderation_forms(): void
{
    $author = $this->makeUser(['username' => 'study_member_author']);
    $viewer = $this->makeUser(['username' => 'study_member_viewer']);
    $board = $this->makeBoard($this->makeCategory('Study Member'));
    $thread = $this->makeThread($board, $author, 'Member tools are scoped', 'Opening record.');

    $this->actingAs($viewer);
    $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);

    $this->assertStatus(200, $page);
    self::assertStringContainsString('data-topic-tools-open', $page->body());
    self::assertStringContainsString('data-topic-tools', $page->body());
    self::assertStringContainsString('data-topic-tools-section="watch"', $page->body());
    self::assertStringContainsString('data-topic-tools-section="standing"', $page->body());
    self::assertStringContainsString('action="/t/' . $thread['thread_id'] . '/snooze"', $page->body());
    self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/pin"', $page->body());
}

public function test_workflow_open_and_solved_each_render_one_canonical_chip(): void
{
    $op = $this->makeUser(['username' => 'study_status_op']);
    $answerer = $this->makeUser(['username' => 'study_status_answerer']);
    $board = $this->makeBoard($this->makeCategory('Study Status'));
    $thread = $this->makeThread($board, $op, 'One status at a time', 'Opening record.');

    $open = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    self::assertSame(1, substr_count($open->body(), 'data-thread-status="open"'));

    $answerId = $this->posting()->reply($this->userEntity($answerer), (int) $thread['thread_id'], ['body' => 'The answer.']);
    $this->actingAs($op);
    $this->assertRedirect($this->post('/posts/' . $answerId . '/accept'));
    $solved = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    self::assertSame(1, substr_count($solved->body(), 'data-thread-status="solved"'));
}

public function test_suspended_privileged_reader_gets_no_write_gated_thread_controls_in_default_gate_mode(): void
{
    $suspended = $this->makeAdmin([
        'username' => 'study_suspended_admin',
        'status' => 'suspended',
        'suspended_until' => '2099-01-01 00:00:00',
    ]);
    $author = $this->makeUser(['username' => 'study_active_author']);
    $board = $this->makeBoard($this->makeCategory('Study Suspension'));
    $thread = $this->makeThread($board, $author, 'State beats role', 'Opening record.');
    $answer = $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'Candidate answer.']);

    $this->actingAs($suspended);
    $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    $html = $page->body();

    self::assertStringContainsString('Your account cannot post right now.', $html);
    self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/pin"', $html);
    self::assertStringNotContainsString('action="/mod/t/' . $thread['thread_id'] . '/lock"', $html);
    self::assertStringNotContainsString('action="/posts/' . $answer . '/accept"', $html);
    self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/tags"', $html);
    self::assertStringNotContainsString('action="/t/' . $thread['thread_id'] . '/snooze"', $html);
}
```

- [ ] **Step 2: Run the new tests and verify red**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php --filter 'guest_keeps|member_gets|workflow_open|suspended_privileged'
```

Expected: FAIL because the new data hooks and Open chip do not exist.

- [ ] **Step 3: Extract the status ledger partial**

Create `thread_status_history.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($status_history)): ?>
<details class="thread-status-history" data-thread-status-history>
    <summary>Status history</summary>
    <ol class="thread-status-history-list">
        <?php foreach ($status_history as $event): ?>
            <li>
                <strong><?= $e($status_labels[$event['new_status']] ?? $event['new_status']) ?></strong>
                <?php if (!empty($event['previous_status'])): ?>
                    <span>← <?= $e($status_labels[$event['previous_status']] ?? $event['previous_status']) ?></span>
                <?php endif; ?>
                <span><?= $e($event['actor_display_name'] ?? $event['actor_username'] ?? 'system') ?> · <?= $e(human_datetime($event['created_at'])) ?></span>
                <?php if (!empty($event['reason'])): ?><em>“<?= $e($event['reason']) ?>”</em><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</details>
<?php endif; ?>
```

- [ ] **Step 4: Build the basic Topic tools partial with the existing forms**

Before the header in `thread.php`, compute the shared section visibility once. This keeps the trigger and aside in lockstep, including when features are dark or the reader cannot write:

```php
<?php
$topicToolSections = [
    'watch' => $current_user !== null && !empty($can_write) && (($notifications_on ?? false) || ($workflow_on ?? false)),
    'standing' => $current_user !== null && ($workflow_on ?? false),
    'tags' => $current_user !== null && ($tags_on ?? false) && (!empty($thread_tags) || !empty($can_edit_tags)),
    'memory' => $current_user !== null && !empty($can_curate_memory),
    'management' => $current_user !== null && !empty($can_write) && (
        !empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)
        || !empty($can_mark_solved) || !empty($can_pin) || !empty($can_lock)
        || !empty($can_create_poll) || !empty($poll['can_close']) || !empty($can_split_merge)
    ),
];
$hasTopicTools = in_array(true, $topicToolSections, true);
?>
```

Create `thread_tools.php` with one aside and native details sections. Preserve the existing route and field-name contracts exactly:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php
$showWatch = !empty($topic_tool_sections['watch']);
$showStanding = !empty($topic_tool_sections['standing']);
$showTags = !empty($topic_tool_sections['tags']);
$showMemory = !empty($topic_tool_sections['memory']);
$showManagement = !empty($topic_tool_sections['management']);
$hasTools = in_array(true, $topic_tool_sections, true);
?>
<?php if ($hasTools): ?>
<div class="topic-tools-scrim" data-topic-tools-scrim hidden></div>
<aside class="topic-tools" id="topic-tools-<?= (int) $thread['id'] ?>" data-topic-tools aria-labelledby="topic-tools-title-<?= (int) $thread['id'] ?>">
    <header class="topic-tools-head">
        <span class="topic-tools-mark" aria-hidden="true">✦</span>
        <h2 id="topic-tools-title-<?= (int) $thread['id'] ?>">Topic tools</h2>
        <button type="button" class="topic-tools-close" data-topic-tools-close hidden aria-label="Close Topic tools">×</button>
    </header>
    <div class="topic-tools-body">
        <?php if ($showWatch): ?>
        <details data-topic-tools-section="watch" open>
            <summary><span>Your watch</span><span><?= $e($subscription['frequency'] ?? 'off') ?></span></summary>
            <div class="topic-tools-section-body">
                <?php if (($notifications_on ?? false)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/subscribe">
                        <?= $this->csrfField() ?>
                        <label for="study-sub-freq-<?= (int) $thread['id'] ?>">Frequency</label>
                        <select id="study-sub-freq-<?= (int) $thread['id'] ?>" class="input" name="frequency">
                            <?php $frequency = $subscription['frequency'] ?? 'off'; ?>
                            <?php foreach (['instant' => 'Instant', 'daily' => 'Daily', 'off' => 'Off'] as $value => $label): ?>
                                <option value="<?= $e($value) ?>"<?= $frequency === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="in_app" value="1"><input type="hidden" name="email" value="1">
                        <button class="btn btn-small" type="submit">Save watch</button>
                    </form>
                <?php endif; ?>
                <?php if (($workflow_on ?? false)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/snooze">
                        <?= $this->csrfField() ?>
                        <label for="study-snooze-<?= (int) $thread['id'] ?>">Quiet until</label>
                        <select id="study-snooze-<?= (int) $thread['id'] ?>" class="input" name="until">
                            <option value="">Clear snooze</option><option value="later_today">Later today</option><option value="tomorrow">Tomorrow</option><option value="week">Next week</option>
                        </select>
                        <button class="btn btn-small" type="submit">Save snooze</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showStanding): ?>
        <details data-topic-tools-section="standing">
            <summary><span>Standing</span><span><?= $e($status_labels[$thread['status'] ?? 'open'] ?? 'Open') ?></span></summary>
            <div class="topic-tools-section-body">
                <?php if (!empty($can_write) && !empty(array_filter($can_change_statuses ?? []))): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/status">
                        <?= $this->csrfField() ?>
                        <label for="thread-status">Status</label>
                        <select id="thread-status" class="input" name="status">
                            <?php foreach ($status_labels as $value => $label): ?>
                                <?php if (!empty($can_change_statuses[$value]) || $value === ($thread['status'] ?? 'open')): ?>
                                    <option value="<?= $e($value) ?>"<?= $value === ($thread['status'] ?? 'open') ? ' selected' : '' ?>><?= $e($label) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <label for="thread-status-reason">Reason</label>
                        <input id="thread-status-reason" class="input" name="reason" maxlength="255">
                        <button class="btn btn-small" type="submit">Update status</button>
                    </form>
                <?php endif; ?>
                <?= $this->partial('partials/thread_status_history', compact('status_history', 'status_labels')) ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showTags): ?>
        <details data-topic-tools-section="tags">
            <summary><span>Tags</span><span><?= $e(implode(' · ', array_column($thread_tags ?? [], 'name'))) ?></span></summary>
            <div class="topic-tools-section-body">
                <?php foreach (($thread_tags ?? []) as $tag): ?><a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a><?php endforeach; ?>
                <?php if (!empty($can_edit_tags)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/tags">
                        <?= $this->csrfField() ?>
                        <?php $selected = array_flip(array_map(static fn (array $tag): int => (int) $tag['id'], $thread_tags ?? [])); ?>
                        <?php foreach (($all_tags ?? []) as $tag): ?>
                            <label class="checkline"><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>"<?= isset($selected[(int) $tag['id']]) ? ' checked' : '' ?>><?= $e($tag['name']) ?></label>
                        <?php endforeach; ?>
                        <button class="btn btn-small" type="submit">Save tags</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
```

- [ ] **Step 5: Recompose the quiet header and remove the old workflow/tag blocks**

In `thread.php`, add `data-thread-study`, compute one canonical status, keep tags and participants in `.thread-facts`, and render the trigger only when signed in:

```php
<?php
$status = ($workflow_on ?? false)
    ? (string) ($thread['status'] ?? 'open')
    : (($accepted_post_id ?? null) !== null ? 'solved' : null);
$statusLabel = $status !== null ? ($status_labels[$status] ?? ucwords(str_replace('_', ' ', $status))) : null;
?>
<article class="thread thread-conversation thread-study" data-thread-study>
  <div class="thread-scroll">
    <header class="thread-head thread-study-head">
      <p class="breadcrumb"><a class="breadcrumb-back" href="/"><svg class="breadcrumb-back-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>Home</a><span class="breadcrumb-sep" aria-hidden="true">/</span><a class="breadcrumb-board" href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
      <h1 class="thread-study-title">
        <?php if ((int) $thread['is_pinned'] === 1): ?><span class="thread-state-chip is-pinned">Pinned</span><?php endif; ?>
        <?php if ((int) $thread['is_locked'] === 1): ?><span class="thread-state-chip is-locked">Locked</span><?php endif; ?>
        <?php if ($status !== null): ?><span class="thread-status-chip is-<?= $e($status) ?>" data-thread-status="<?= $e($status) ?>"><?= $status === 'solved' ? '✓ ' : '' ?><?= $e($statusLabel) ?></span><?php endif; ?>
        <?= $e($thread['title']) ?>
      </h1>
      <div class="thread-facts">
        <?php
        $opAnon = null;
        foreach (($posts ?? []) as $opPost) {
            if ((int) ($opPost['is_op'] ?? 0) === 1) { $opAnon = (int) ($opPost['is_anonymous'] ?? 0) === 1; break; }
        }
        $byReplies = (int) ($thread['reply_count'] ?? 0);
        ?>
        <p class="thread-byline"><?php if ($opAnon !== null): $ba = mask_author($thread['author_display_name'] ?? null, $thread['author_username'] ?? null, 'user', $opAnon); ?>Opened by <?= $e($ba['label']) ?> · <?php endif; ?><?= $byReplies ?> repl<?= $byReplies === 1 ? 'y' : 'ies' ?><?php if (!empty($assignment)): ?> · Tended by @<?= $e($assignment['assigned_username']) ?><?php endif; ?><?php if (!empty($my_snooze)): ?> · Quiet until <?= $e(human_datetime($my_snooze)) ?><?php endif; ?></p>
        <?php foreach (($thread_tags ?? []) as $tag): ?><a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a><?php endforeach; ?>
        <?php if (($participant_count ?? 0) >= 2 && !empty($participants)): ?>
          <span class="thread-participants-label">In council</span>
          <div class="thread-participants" aria-label="Participants">
            <?php foreach ($participants as $pp): $pa = mask_author($pp['author_display_name'] ?? null, $pp['author_username'] ?? null, $pp['author_role'] ?? 'user', false); ?>
              <span class="participant" title="<?= $e($pa['label']) ?>"><?= $this->partial('partials/monogram', ['name' => $pa['mono_name'], 'username' => $pa['mono_seed']]) ?></span>
            <?php endforeach; ?>
            <?php $shownParticipants = count($participants); if ((int) ($participant_count ?? 0) > $shownParticipants): ?><span class="participant-more">+<?= (int) $participant_count - $shownParticipants ?></span><?php endif; ?>
          </div>
        <?php endif; ?>
        <?php if (($engagement ?? false) && $current_user !== null && !empty($can_write)): ?>
          <form class="inline star-form" method="post" action="/t/<?= (int) $thread['id'] ?>/star">
            <?= $this->csrfField() ?>
            <input type="hidden" name="return" value="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">
            <button class="linkbtn star-btn<?= ($is_starred ?? false) ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= ($is_starred ?? false) ? 'true' : 'false' ?>"><?= ($is_starred ?? false) ? '★ Starred' : '☆ Star' ?></button>
          </form>
        <?php endif; ?>
        <?php if ($hasTopicTools): ?><button type="button" class="topic-tools-open" data-topic-tools-open hidden aria-controls="topic-tools-<?= (int) $thread['id'] ?>" aria-expanded="false"><span aria-hidden="true">✦</span> Topic tools</button><?php endif; ?>
      </div>
      <?php if ($current_user === null): ?><?= $this->partial('partials/thread_status_history', compact('status_history', 'status_labels')) ?><?php endif; ?>
    </header>
```

Add `'can_write' => $canWriteUser` to `ThreadController`'s view payload. Delete the original `.workflow-bar`, `.wf-actions`, `.wf-history`, standalone tag bar, and tag editor. Render `partials/thread_tools` after the reading content and before `</article>`, passing all existing thread tool data plus `'topic_tool_sections' => $topicToolSections`; Task 3 completes its sections.

- [ ] **Step 6: Update fidelity assertions and run focused tests**

Replace the old `wf-bar`/`wf-btn` expectations in `AppImladrisFidelityTest` with:

```php
$this->assertSeeText($res, 'data-topic-tools');
$this->assertSeeText($res, 'data-topic-tools-section="standing"');
$this->assertSeeText($res, 'action="/t/' . (int) $thread['thread_id'] . '/status"');
$this->assertSeeText($res, 'action="/t/' . (int) $thread['thread_id'] . '/snooze"');
$this->assertDontSeeText($res, 'class="workflow-bar');
```

Run:

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php
vendor\bin\phpunit tests/Integration/Core/AppImladrisFidelityTest.php --filter 'topic_workflow|participant|grouped'
vendor\bin\phpunit tests/Integration/Core/AppThreadTagDisplayTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit Task 2**

```powershell
git add -- tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppImladrisFidelityTest.php src/Controller/ThreadController.php templates/thread.php templates/partials/thread_status_history.php templates/partials/thread_tools.php
git commit -m "feat: add quiet thread header and Topic tools"
```

---

### Task 3: Management, memory, polls, and split/merge

**Files:**
- Modify: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Modify: `templates/partials/thread_tools.php`
- Create: `templates/partials/thread_restructure.php`
- Modify: `templates/partials/thread_memory_tools.php`
- Modify: `templates/thread.php:224-383`
- Modify: `tests/Integration/Core/AppImladrisFidelityTest.php:391-412`

**Interfaces:**
- Consumes: existing capability booleans, poll model, memory model, and posts.
- Produces: one copy of each management form, `data-topic-tools-section="memory|management"`, and one `data-thread-restructure` disclosure outside the drawer.

- [ ] **Step 1: Add failing management-form placement tests**

Append:

```php
public function test_moderation_and_memory_forms_render_once_inside_scoped_tools(): void
{
    $admin = $this->makeAdmin(['username' => 'study_tools_admin']);
    $author = $this->makeUser(['username' => 'study_tools_author']);
    $board = $this->makeBoard($this->makeCategory('Study Management'));
    $this->db->run('UPDATE boards SET assignment_mode = ?, wiki_enabled = 1 WHERE id = ?', ['staff', $board['id']]);
    $thread = $this->makeThread($board, $author, 'Management stays scoped', 'Opening record.');
    $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'Movable reply.']);

    $this->actingAs($admin);
    $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    $html = $page->body();

    self::assertStringContainsString('data-topic-tools-section="memory"', $html);
    self::assertStringContainsString('data-topic-tools-section="management"', $html);
    self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/pin"'));
    self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/lock"'));
    self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/split"'));
    self::assertSame(1, substr_count($html, 'action="/mod/t/' . $thread['thread_id'] . '/merge"'));
    self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/assign"'));
    self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/poll"'));
    self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/summary"'));
    self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/summary/refresh"'));
    self::assertSame(1, substr_count($html, 'action="/t/' . $thread['thread_id'] . '/related"'));
    self::assertStringNotContainsString('class="workflow-actions', $html);
}
```

- [ ] **Step 2: Run and verify red**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php --filter moderation_and_memory
```

Expected: FAIL because management/memory sections are not yet in the partial.

- [ ] **Step 3: Make memory tools embeddable without duplicating forms**

Make only the outer disclosure conditional in `thread_memory_tools.php`: replace its opening `<details>` and `<summary>` with the first two lines below, leave the current body (resume automation, refresh, publish with `body` and `source_post_ids`, retire, restore, and add-related forms) byte-for-byte unchanged, and replace the final `</details>` with the last line below.

```php
<?php $embedded = !empty($embedded); ?>
<?php if (!$embedded): ?><details class="memory-curator-tools"><summary class="linkbtn">Curate topic memory</summary><?php endif; ?>
```

Keep the existing `.memory-curator-tools-body` element and all six gated form paths after that opening. Replace only the current final `</details>` with:

```php
<?php if (!$embedded): ?></details><?php endif; ?>
```

- [ ] **Step 4: Extract the single split/merge disclosure**

Create `thread_restructure.php` from the current forms:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($features['split_merge']) && !empty($can_split_merge)): ?>
<?php $movablePosts = array_values(array_filter($posts ?? [], static fn (array $post): bool => (int) ($post['is_op'] ?? 0) !== 1)); ?>
<div class="thread-restructure-scrim" data-thread-restructure-scrim hidden></div>
<details class="thread-restructure" data-thread-restructure>
    <summary>Split or merge topic</summary>
    <section class="thread-restructure-dialog" aria-labelledby="thread-restructure-title-<?= (int) $thread['id'] ?>">
        <header><h2 id="thread-restructure-title-<?= (int) $thread['id'] ?>">Split or merge this topic</h2><button type="button" data-thread-restructure-close hidden aria-label="Close split or merge">×</button></header>
        <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/split">
            <?= $this->csrfField() ?>
            <h3>Split replies out</h3>
            <?php foreach ($movablePosts as $post): ?>
                <?php $author = mask_author($post['author_display_name'] ?? null, $post['author_username'] ?? null, $post['author_role'] ?? 'user', (int) ($post['is_anonymous'] ?? 0) === 1); ?>
                <label class="sm-post"><input type="checkbox" name="post_ids[]" value="<?= (int) $post['id'] ?>"><span><strong><?= $e($author['label']) ?> · #<?= (int) $post['id'] ?></strong><span><?= $e(mb_strimwidth(strip_tags((string) ($post['body_html'] ?? '')), 0, 120, '…')) ?></span></span></label>
            <?php endforeach; ?>
            <label>New topic title<input class="input" name="title" maxlength="255" required></label>
            <button class="btn btn-small" type="submit"<?= $movablePosts === [] ? ' disabled' : '' ?>>Split replies out</button>
        </form>
        <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/merge">
            <?= $this->csrfField() ?>
            <h3>Merge into another topic</h3>
            <label>Target topic ID<input class="input" type="number" name="target_thread_id" min="1" required></label>
            <p>All posts move into the chosen topic. The move is logged and reversible through repair tooling.</p>
            <button class="btn btn-small" type="submit">Merge topics</button>
        </form>
    </section>
</details>
<?php endif; ?>
```

- [ ] **Step 5: Add memory and management sections to Topic tools**

In `thread_tools.php`, append these two sections inside `.topic-tools-body`:

```php
<?php if ($showMemory): ?>
<details data-topic-tools-section="memory">
    <summary><span>Living Brief</span><span aria-hidden="true">✦</span></summary>
    <div class="topic-tools-section-body">
        <?= $this->partial('partials/thread_memory_tools', compact('thread', 'living_brief', 'memory_history', 'memory_refresh', 'memory_automation_paused') + ['embedded' => true]) ?>
    </div>
</details>
<?php endif; ?>
<?php if ($showManagement): ?>
<details data-topic-tools-section="management">
    <summary><span>Topic management</span><span><?= !empty($assignment) ? '@' . $e($assignment['assigned_username']) : 'unassigned' ?></span></summary>
    <div class="topic-tools-section-body">
        <?php if (!empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)): ?>
        <form method="post" action="/t/<?= (int) $thread['id'] ?>/assign">
            <?= $this->csrfField() ?>
            <?php if (!empty($can_staff_assign)): ?>
                <label for="study-thread-assignee">Assign to</label>
                <input id="study-thread-assignee" class="input" type="text" name="assignee" maxlength="32" placeholder="username">
                <button class="btn btn-small" type="submit">Assign</button>
            <?php elseif (!empty($can_self_assign)): ?>
                <input type="hidden" name="self" value="1">
                <button class="btn btn-small" type="submit">Assign to me</button>
            <?php endif; ?>
            <?php if (!empty($assignment)): ?><button class="linkbtn muted" type="submit" name="action" value="unassign">Unassign</button><?php endif; ?>
        </form>
        <?php endif; ?>
        <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?><form method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Clear accepted answer</button></form><?php endif; ?>
        <?php if (!empty($can_pin)): ?><form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin"><?= $this->csrfField() ?><button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button></form><?php endif; ?>
        <?php if (!empty($can_lock)): ?><form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock"><?= $this->csrfField() ?><button class="linkbtn danger" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button></form><?php endif; ?>
        <?php if (!empty($poll['can_close'])): ?><form method="post" action="/polls/<?= (int) $poll['id'] ?>/close"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Close poll</button></form><?php endif; ?>
        <?php if (!empty($can_create_poll)): ?>
        <details class="poll-builder">
            <summary>Add poll</summary>
            <form class="stacked" method="post" action="/t/<?= (int) $thread['id'] ?>/poll">
                <?= $this->csrfField() ?>
                <label class="field"><span>Question</span><input class="input" type="text" name="question" maxlength="255" required></label>
                <label class="field"><span>Mode</span><select class="input" name="mode"><option value="single">Single choice</option><option value="multiple">Multiple choice</option></select></label>
                <label class="field"><span>Closes</span><select class="input" name="closes_in"><option value="never">Never</option><option value="1d">In 1 day</option><option value="3d">In 3 days</option><option value="1w">In 1 week</option></select></label>
                <label class="field"><span>Options, one per line</span><textarea class="input" name="options" rows="4" required></textarea></label>
                <button class="btn btn-small" type="submit">Create poll</button>
            </form>
        </details>
        <?php endif; ?>
        <?php if (!empty($can_split_merge)): ?><button type="button" data-thread-restructure-open hidden>Split or merge…</button><?php endif; ?>
    </div>
</details>
<?php endif; ?>
```

Render `thread_restructure.php` outside the aside. Remove the old poll builder, poll-close form, memory curator block, and split/merge block from `thread.php`. Keep the poll card and Living Brief/fallback slot in the reading flow.

- [ ] **Step 6: Update split fidelity assertions and run focused suites**

Change `AppImladrisFidelityTest` to assert `data-thread-restructure`, the two form actions, and `data-topic-tools-section="management"` instead of the old `.workflow-actions` wrapper.

Run:

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php
vendor\bin\phpunit tests/Integration/Core/AppImladrisFidelityTest.php --filter 'topic_workflow|split_merge'
vendor\bin\phpunit tests/Integration/Core/AppPollTest.php
vendor\bin\phpunit tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
```

Expected: PASS.

- [ ] **Step 7: Commit Task 3**

```powershell
git add -- tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppImladrisFidelityTest.php templates/thread.php templates/partials/thread_tools.php templates/partials/thread_restructure.php templates/partials/thread_memory_tools.php
git commit -m "feat: move topic management into Study tools"
```

---

### Task 4: Quiet post stream and capability-safe post toolbar

**Files:**
- Modify: `tests/Integration/Core/AppThreadViewStudyTest.php`
- Modify: `tests/Integration/Core/AppThreadUxAuditTest.php`
- Create: `templates/partials/post_toolbar.php`
- Modify: `templates/partials/post.php`
- Modify: `templates/thread.php:386-434`
- Modify: `templates/partials/composer.php`
- Modify: `templates/partials/living_brief.php`

**Interfaces:**
- Consumes: `can_write`, `owner`, `canModerate`, post capability booleans, real reactions, edit-old/error, title label, and composer contract.
- Produces: `data-post-toolbar`, `data-post-menu`, `data-post-disclosure-open`, `data-quote-post`, `data-copy-post`, and day-divider hooks.

- [ ] **Step 1: Add failing post-surface tests**

Append tests that assert one toolbar for a signed-in viewer, no toolbar for guests, capability-scoped moderation forms, and a day divider:

```php
public function test_post_toolbar_is_signed_in_capability_scoped_and_no_js_reachable(): void
{
    $author = $this->makeUser(['username' => 'study_toolbar_author']);
    $viewer = $this->makeUser(['username' => 'study_toolbar_viewer']);
    $board = $this->makeBoard($this->makeCategory('Study Toolbar'));
    $thread = $this->makeThread($board, $author, 'Actions remain real forms', 'Opening record.');

    $guest = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    self::assertStringNotContainsString('data-post-toolbar', $guest->body());

    $this->actingAs($viewer);
    $member = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    self::assertStringContainsString('data-post-toolbar', $member->body());
    self::assertStringContainsString('data-quote-post', $member->body());
    self::assertStringContainsString('data-copy-post', $member->body());
    self::assertStringNotContainsString('Remove (warden)', $member->body());
}

public function test_stream_inserts_a_divider_when_the_utc_calendar_day_changes(): void
{
    $author = $this->makeUser(['username' => 'study_day_author']);
    $board = $this->makeBoard($this->makeCategory('Study Days'));
    $thread = $this->makeThread($board, $author, 'Days divide the record', 'Opening record.');
    $reply = $this->posting()->reply($this->userEntity($author), (int) $thread['thread_id'], ['body' => 'A later record.']);
    $this->db->run("UPDATE posts SET created_at = '2026-07-13 09:00:00' WHERE id = ?", [$reply]);
    $this->db->run("UPDATE posts SET created_at = '2026-07-12 09:00:00' WHERE thread_id = ? AND is_op = 1", [$thread['thread_id']]);

    $page = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    self::assertStringContainsString('data-post-day="2026-07-13"', $page->body());
}
```

- [ ] **Step 2: Run and verify red**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php --filter 'post_toolbar|stream_inserts'
```

Expected: FAIL because toolbar/day hooks do not exist.

- [ ] **Step 3: Render day dividers without changing post order**

In the post loop, track the prior UTC date before rendering the partial:

```php
<?php $previousDay = null; ?>
<?php foreach ($posts as $p): ?>
    <?php $postDay = substr((string) $p['created_at'], 0, 10); ?>
    <?php if ($previousDay !== null && $postDay !== $previousDay): ?>
        <div class="post-day-divider" data-post-day="<?= $e($postDay) ?>"><span></span><time datetime="<?= $e($postDay) ?>"><?= $e(gmdate('F j, Y', strtotime($postDay . ' UTC') ?: 0)) ?></time><span></span></div>
    <?php endif; ?>
    <?php $previousDay = $postDay; ?>
```

Insert that block at the top of the current loop, immediately before `$thisAnon` is calculated. Leave the existing grouping calculation and explicit post-partial call after it; do not create a second loop.

- [ ] **Step 4: Extract enhanced toolbar triggers from no-JS disclosures**

Create `post_toolbar.php` with the existing forms. Simple actions live directly in the menu; forms that need fields keep native details below and get hidden enhanced opener buttons targeting their IDs:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if ($current_user !== null): ?>
<div class="post-toolbar" data-post-toolbar>
    <?php if (!empty($can_write) && $engagement && ($show_reactions ?? true) && $allowed !== []): ?>
        <details class="reaction-add post-toolbar-reactions">
            <summary class="post-toolbar-button" aria-label="Add a reaction">＋</summary>
            <div class="reaction-menu">
                <?php foreach ($allowed as $emoji): ?><form class="reaction-form inline" method="post" action="/posts/<?= (int) $p['id'] ?>/react"><?= $this->csrfField() ?><input type="hidden" name="emoji" value="<?= $e($emoji) ?>"><button type="submit" class="reaction"><?= $e($emoji) ?></button></form><?php endforeach; ?>
            </div>
        </details>
    <?php endif; ?>
    <button type="button" class="post-toolbar-button" data-quote-post hidden aria-label="Quote in your reply">❝</button>
    <?php if (!empty($can_write) && !empty($can_mark_solved) && empty($accepted) && (int) $p['is_op'] === 0): ?><form method="post" action="/posts/<?= (int) $p['id'] ?>/accept"><?= $this->csrfField() ?><button class="post-toolbar-button" type="submit" aria-label="Accept as answer">✓</button></form><?php endif; ?>
    <details class="post-menu" data-post-menu>
        <summary class="post-toolbar-button" aria-label="More post actions">···</summary>
        <div class="post-menu-pop">
            <a href="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?><?= $page > 1 ? '?page=' . (int) $page : '' ?>#p<?= (int) $p['id'] ?>" data-copy-post>Copy link</a>
            <?php if (!empty($can_write) && $owner): ?><button type="button" data-post-disclosure-open="post-edit-<?= (int) $p['id'] ?>" hidden>Edit</button><?php endif; ?>
            <?php if (!empty($can_write) && $owner): ?><form method="post" action="/posts/<?= (int) $p['id'] ?>/delete"><?= $this->csrfField() ?><button class="danger" type="submit"><?= (int) $p['is_op'] === 1 ? 'Delete topic' : 'Delete' ?></button></form><?php endif; ?>
            <?php if ($canModerate): ?><button type="button" data-post-disclosure-open="post-remove-<?= (int) $p['id'] ?>" hidden>Remove (warden)</button><?php endif; ?>
            <?php if ($isAnon && !empty($can_reveal_anon)): ?><form method="post" action="/mod/p/<?= (int) $p['id'] ?>/reveal"><?= $this->csrfField() ?><button type="submit">Reveal author — logged</button></form><?php endif; ?>
            <?php if (!empty($can_write) && !empty($memory_on) && !empty($can_curate_wiki) && empty($p['is_wiki'])): ?><form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki"><?= $this->csrfField() ?><button type="submit">Make wiki</button></form><?php endif; ?>
            <?php if (!empty($can_write) && !empty($features['moderation_queue']) && !$owner): ?><button type="button" data-post-disclosure-open="post-report-<?= (int) $p['id'] ?>" hidden>Report</button><?php endif; ?>
        </div>
    </details>
</div>
<?php endif; ?>
```

Move the existing owner-edit form into `<details class="post-native-disclosure" id="post-edit-{id}">`, moderator reason form into `<details class="post-native-disclosure" id="post-remove-{id}">`, and report form into `<details class="post-native-disclosure" id="post-report-{id}">`. Gate owner/edit/report/wiki disclosures with `can_write`; moderator controls already consume booleans that include `WriteGate`. Their `<summary>` elements remain visible without JavaScript; after `data-thread-enhanced="1"`, CSS hides only those summaries whose hidden toolbar opener is available. Leave wiki edit/revert as a native disclosure under its current capability gate. Remove the duplicate reaction picker and simple action forms from their old positions, but retain rendered reaction-count chips in `.reactions`.

- [ ] **Step 5: Recompose `post.php` around the toolbar partial**

Use a quiet row and keep every raw/sanitized output rule unchanged. Change the root element and its closing tag exactly:

```php
<article class="post<?= $accepted ? ' post-accepted' : '' ?><?= (int) $p['is_op'] === 1 ? ' post-op' : '' ?><?= $grouped ? ' post-grouped' : '' ?>" id="p<?= (int) $p['id'] ?>" data-post>
</article>
```

Retain the current avatar/spacer, regard, accepted flag, post head, sanitized body branch, cards, signature, and reaction-count markup between those tags. Immediately after the reaction counts, render the new partial with this explicit payload:

```php
<?= $this->partial('partials/post_toolbar', [
    'p' => $p,
    'thread' => $thread,
    'page' => $page,
    'can_write' => $can_write ?? false,
    'owner' => $owner,
    'canModerate' => $canModerate,
    'isAnon' => $isAnon,
    'accepted' => $accepted,
    'engagement' => $engagement,
    'show_reactions' => $show_reactions ?? true,
    'allowed' => $allowed,
    'can_mark_solved' => $can_mark_solved ?? false,
    'can_reveal_anon' => $can_reveal_anon ?? false,
    'memory_on' => $memory_on ?? false,
    'can_curate_wiki' => $can_curate_wiki ?? false,
    'features' => $features ?? [],
]) ?>
```

Add `'page' => $page` and `'features' => $features ?? []` to `thread.php`'s explicit post-partial payload so the toolbar can build a correct paginated permalink and gate reports.

- [ ] **Step 6: Add Study hooks without changing composer/Living Brief contracts**

In `composer.php`, add `.thread-composer-card`, a monogram strip, and `data-thread-composer`; leave every input unchanged. In `living_brief.php`, add `.study-living-brief` and `data-living-brief`, retain the lineage link and meta attribution, and add a hidden `data-topic-tools-open="memory"` curator button only when `can_curate_memory` is passed. Update `thread.php`'s Living Brief partial call from `compact(...)` to an explicit payload containing `living_brief`, `living_brief_sources`, `living_brief_related`, and `can_curate_memory` so the button and its drawer section cannot drift apart.

The composer opening and identity row become:

```php
<form method="post" action="/t/<?= (int) $thread['id'] ?>/reply" class="composer reply-composer thread-composer-card<?= $replyExpanded ? ' is-expanded' : '' ?>" id="reply" data-composer-context="reply" data-composer-target-id="<?= (int) $thread['id'] ?>" data-thread-composer>
    <?= $this->csrfField() ?>
    <input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <div class="thread-composer-identity">
        <?= $this->partial('partials/monogram', ['name' => $current_user->displayName(), 'username' => $current_user->username()]) ?>
        <p class="composer-label">Posting as <strong><?= $e($current_user->displayName()) ?></strong></p>
    </div>
```

Leave the error, textarea, anonymous checkbox, and Reply button after that row. Change the Living Brief opening tag to:

```php
<section class="living-brief study-living-brief" data-living-brief aria-labelledby="living-brief-heading">
```

Retain the current label, `/privacy#thread-intelligence` processor-disclosure link, heading, and curator-attribution metadata. Insert this button after `.living-brief-meta` and before the head closes:

```php
<?php if (!empty($can_curate_memory)): ?><button type="button" class="living-brief-curate" data-topic-tools-open="memory" hidden>Curate</button><?php endif; ?>
```

- [ ] **Step 7: Update moderator assertions and run focused suites**

In `AppThreadUxAuditTest`, assert the delete form action and `Remove (warden)` within `data-post-menu` instead of `Remove (mod)`.

Run:

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php
vendor\bin\phpunit tests/Integration/Core/AppThreadUxAuditTest.php
vendor\bin\phpunit tests/Integration/Core/AppComposerTest.php
vendor\bin\phpunit tests/Integration/Core/AppCommunityProfileTest.php
```

Expected: PASS.

- [ ] **Step 8: Commit Task 4**

```powershell
git add -- tests/Integration/Core/AppThreadViewStudyTest.php tests/Integration/Core/AppThreadUxAuditTest.php templates/thread.php templates/partials/post.php templates/partials/post_toolbar.php templates/partials/composer.php templates/partials/living_brief.php
git commit -m "feat: add Study post stream and action toolbar"
```

---

### Task 5: Progressive drawer, modal, menus, quote, copy, and keyboard inset

**Files:**
- Create: `tests/browser/thread-view-study.spec.ts`
- Modify: `public/assets/app.js:170-302,451-490`
- Modify: `tests/browser/package.json`

**Interfaces:**
- Consumes: Task 2–4 data hooks and the existing Inbox `loadThread()` insertion point.
- Produces: idempotent `enhanceThreadViews(root)`, focus-safe open/close behavior, and `--keyboard-inset`.

- [ ] **Step 1: Write failing desktop and dynamic-Inbox interaction tests**

Create the focused spec with real login and topic navigation:

```ts
import { expect, test, type Page } from '@playwright/test';

async function login(page: Page): Promise<void> {
  await page.goto('/login');
  await page.fill('input[name="email"]', 'alice@retro.test');
  await page.fill('input[name="password"]', 'password123');
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/\/inbox/);
  const skip = page.getByRole('button', { name: 'Skip' });
  if (await skip.isVisible().catch(() => false)) await skip.click();
}

async function openSeedTopic(page: Page): Promise<void> {
  await page.goto('/c/general');
  await page.getByRole('link', { name: 'Share your favourite keyboard shortcuts' }).click();
  await expect(page.locator('[data-thread-study]')).toBeVisible();
}

test('desktop Topic tools opens, accords, closes, and restores focus', async ({ page }, info) => {
  test.skip(info.project.name !== 'desktop');
  await login(page);
  await openSeedTopic(page);
  const trigger = page.getByRole('button', { name: 'Topic tools' });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  await expect(tools).toHaveAttribute('aria-modal', 'true');
  const closeTools = page.getByRole('button', { name: 'Close Topic tools' });
  await expect(closeTools).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  expect(await tools.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Tab');
  await expect(closeTools).toBeFocused();
  await tools.locator('[data-topic-tools-section="standing"] > summary').click();
  await expect(tools.locator('[data-topic-tools-section="standing"]')).toHaveAttribute('open', '');
  await expect(tools.locator('[data-topic-tools-section="watch"]')).not.toHaveAttribute('open', '');
  await page.getByRole('button', { name: 'Close Topic tools' }).click();
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();
  await trigger.click();
  await page.locator('[data-topic-tools-scrim]').click({ position: { x: 10, y: 10 } });
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();
  await trigger.click();
  await page.keyboard.press('Escape');
  await expect(tools).toBeHidden();
  await expect(trigger).toBeFocused();
});

test('split or merge modal traps dismissal and restores focus', async ({ page }, info) => {
  await login(page);
  await openSeedTopic(page);
  const topicTrigger = page.getByRole('button', { name: 'Topic tools' });
  await topicTrigger.click();
  const management = page.locator('[data-topic-tools-section="management"]');
  if (!(await management.evaluate((element) => (element as HTMLDetailsElement).open))) await management.locator(':scope > summary').click();
  await management.getByRole('button', { name: 'Split or merge' }).click();
  const dialog = page.locator('.thread-restructure-dialog');
  await expect(dialog).toBeVisible();
  await expect(dialog).toHaveAttribute('aria-modal', 'true');
  const closeRestructure = dialog.getByRole('button', { name: 'Close split or merge' });
  await expect(closeRestructure).toBeFocused();
  await page.keyboard.press('Shift+Tab');
  expect(await dialog.evaluate((element) => element.contains(document.activeElement))).toBe(true);
  await page.keyboard.press('Tab');
  await expect(closeRestructure).toBeFocused();
  const box = await dialog.boundingBox();
  expect(box).not.toBeNull();
  if (info.project.name === 'desktop') expect(box!.width).toBeLessThanOrEqual(600);
  else {
    expect(box!.width).toBeCloseTo(390, 0);
    expect(box!.height).toBeCloseTo(844, 0);
  }
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await expect(topicTrigger).toBeFocused();
  await topicTrigger.click();
  if (!(await management.evaluate((element) => (element as HTMLDetailsElement).open))) await management.locator(':scope > summary').click();
  await management.getByRole('button', { name: 'Split or merge' }).click();
  await page.locator('[data-thread-restructure-scrim]').click({ position: { x: 10, y: 10 } });
  await expect(dialog).toBeHidden();
  await expect(topicTrigger).toBeFocused();
});

test('Inbox-inserted topic gets drawer and quote enhancement', async ({ page }) => {
  await login(page);
  await page.locator('[data-inbox-list] .thread-row', { hasText: 'Share your favourite keyboard shortcuts' }).locator('a.thread-title').click();
  const reading = page.locator('[data-inbox-reading]');
  await reading.getByRole('button', { name: 'Topic tools' }).click();
  await expect(reading.locator('[data-topic-tools]')).toBeVisible();
  await reading.getByRole('button', { name: 'Close Topic tools' }).click();
  await reading.locator('[data-post]').nth(1).hover();
  await reading.locator('[data-post]').nth(1).getByRole('button', { name: 'Quote in your reply' }).click();
  await expect(reading.locator('#reply textarea[name="body"]')).toHaveValue(/^> /);
});
```

- [ ] **Step 2: Add the focused spec to `npm run evidence`, prepare DB, and verify red**

Add `thread-view-study.spec.ts` immediately before `gate-a.spec.ts` in the `evidence` script so its baseline captures run before state-changing legacy journeys. Run:

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test thread-view-study.spec.ts --project=desktop
Set-Location ../..
```

Expected: FAIL because the server-rendered triggers remain hidden and no drawer handler exists.

- [ ] **Step 3: Add idempotent initialization and call it after Inbox insertion**

Near the top of `app.js`, define:

```js
function enhanceThreadViews(scope) {
    var roots = scope.querySelectorAll ? scope.querySelectorAll('[data-thread-study]') : [];
    for (var i = 0; i < roots.length; i++) {
        var root = roots[i];
        if (root.getAttribute('data-thread-enhanced') === '1') { continue; }
        root.setAttribute('data-thread-enhanced', '1');
        var tools = root.querySelector('[data-topic-tools]');
        var openers = root.querySelectorAll('[data-topic-tools-open]');
        var opener = openers[0] || null;
        if (tools && opener) {
            tools.hidden = true;
            for (var k = 0; k < openers.length; k++) { openers[k].hidden = false; }
            var close = tools.querySelector('[data-topic-tools-close]');
            if (close) { close.hidden = false; }
        }
        var enhancedOnly = root.querySelectorAll('[data-quote-post], [data-post-disclosure-open], [data-thread-restructure-open], [data-thread-restructure-close]');
        for (var j = 0; j < enhancedOnly.length; j++) { enhancedOnly[j].hidden = false; }
    }
}
enhanceThreadViews(document);
```

Immediately after `readingContent.innerHTML = main.innerHTML;`, call `enhanceThreadViews(readingContent);`.

- [ ] **Step 4: Implement drawer state and focus containment**

Add functions that operate on the nearest thread root:

```js
var topicToolsFocus = null;
function setTopicTools(root, open, section) {
    var tools = root.querySelector('[data-topic-tools]');
    var trigger = root.querySelector('[data-topic-tools-open]');
    var scrim = root.querySelector('[data-topic-tools-scrim]');
    if (!tools || !trigger) { return; }
    if (open) {
        topicToolsFocus = document.activeElement;
        tools.hidden = false;
        tools.setAttribute('role', 'dialog');
        tools.setAttribute('aria-modal', 'true');
        trigger.setAttribute('aria-expanded', 'true');
        if (scrim) { scrim.hidden = false; }
        document.body.classList.add('topic-tools-open');
        if (section) {
            var target = tools.querySelector('[data-topic-tools-section="' + section + '"]');
            if (target) { target.open = true; }
        }
        var first = tools.querySelector('[data-topic-tools-close], summary, button, input, select, textarea, a[href]');
        if (first) { first.focus(); }
    } else {
        tools.hidden = true;
        tools.removeAttribute('role');
        tools.removeAttribute('aria-modal');
        trigger.setAttribute('aria-expanded', 'false');
        if (scrim) { scrim.hidden = true; }
        document.body.classList.remove('topic-tools-open');
        if (topicToolsFocus && document.documentElement.contains(topicToolsFocus)) { topicToolsFocus.focus(); }
        topicToolsFocus = null;
    }
}
```

Use delegated open/close/scrim handling, one-open-section accordion behavior, Escape ordering, and a Tab loop:

```js
document.addEventListener('click', function (event) {
    var target = event.target;
    if (!target.closest) { return; }
    var opener = target.closest('[data-topic-tools-open]');
    if (opener) {
        var openRoot = opener.closest('[data-thread-study]');
        if (openRoot) { setTopicTools(openRoot, true, opener.getAttribute('data-topic-tools-open') || ''); }
        return;
    }
    var closer = target.closest('[data-topic-tools-close], [data-topic-tools-scrim]');
    if (closer) {
        var closeRoot = closer.closest('[data-thread-study]');
        if (closeRoot) { setTopicTools(closeRoot, false); }
    }
});

document.addEventListener('toggle', function (event) {
    var opened = event.target;
    if (!opened.matches || !opened.matches('[data-topic-tools-section][open]')) { return; }
    var siblings = opened.parentElement.querySelectorAll('[data-topic-tools-section][open]');
    for (var i = 0; i < siblings.length; i++) {
        if (siblings[i] !== opened) { siblings[i].open = false; }
    }
}, true);

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        var menu = document.querySelector('[data-post-menu][open]');
        if (menu) { menu.open = false; event.preventDefault(); return; }
        var restructure = document.querySelector('[data-thread-restructure][open]');
        if (restructure) { setThreadRestructure(restructure.closest('[data-thread-study]'), false); event.preventDefault(); return; }
        var openTools = document.querySelector('[data-topic-tools]:not([hidden])');
        if (openTools) { setTopicTools(openTools.closest('[data-thread-study]'), false); event.preventDefault(); }
        return;
    }
    if (event.key !== 'Tab') { return; }
    var dialog = document.querySelector('[data-thread-restructure][open] .thread-restructure-dialog, [data-topic-tools]:not([hidden])');
    if (!dialog) { return; }
    var candidates = dialog.querySelectorAll('a[href], button:not([disabled]), summary, input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])');
    var focusable = Array.prototype.filter.call(candidates, function (item) { return item.getClientRects().length > 0; });
    if (focusable.length === 0) { event.preventDefault(); return; }
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (event.shiftKey && document.activeElement === first) { last.focus(); event.preventDefault(); }
    else if (!event.shiftKey && document.activeElement === last) { first.focus(); event.preventDefault(); }
});
```

- [ ] **Step 5: Implement restructure, post disclosure, quote, and copy enhancements**

Track the restructure opener independently so the modal restores focus and its real scrim can close it:

```js
var restructureFocus = null;
function setThreadRestructure(root, open) {
    var details = root.querySelector('[data-thread-restructure]');
    var dialog = details ? details.querySelector('.thread-restructure-dialog') : null;
    var scrim = root.querySelector('[data-thread-restructure-scrim]');
    if (!details || !dialog) { return; }
    if (open) {
        setTopicTools(root, false);
        restructureFocus = document.activeElement;
        details.open = true;
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        if (scrim) { scrim.hidden = false; }
        document.body.classList.add('thread-restructure-open');
        var first = dialog.querySelector('[data-thread-restructure-close], input, button, select, textarea');
        if (first) { first.focus(); }
    } else {
        details.open = false;
        dialog.removeAttribute('role');
        dialog.removeAttribute('aria-modal');
        if (scrim) { scrim.hidden = true; }
        document.body.classList.remove('thread-restructure-open');
        if (restructureFocus && document.documentElement.contains(restructureFocus)) { restructureFocus.focus(); }
        restructureFocus = null;
    }
}

document.addEventListener('click', function (event) {
    var target = event.target;
    var root = target.closest ? target.closest('[data-thread-study]') : null;
    if (!root) { return; }

    var restructureOpen = target.closest('[data-thread-restructure-open]');
    if (restructureOpen) {
        setThreadRestructure(root, true);
        return;
    }
    if (target.closest('[data-thread-restructure-close], [data-thread-restructure-scrim]')) {
        setThreadRestructure(root, false);
        return;
    }
    var disclosureOpen = target.closest('[data-post-disclosure-open]');
    if (disclosureOpen) {
        var disclosure = document.getElementById(disclosureOpen.getAttribute('data-post-disclosure-open'));
        if (disclosure) { disclosure.open = true; disclosure.querySelector('textarea, input, select, button').focus(); }
        var menu = disclosureOpen.closest('[data-post-menu]');
        if (menu) { menu.open = false; }
        return;
    }
    var quote = target.closest('[data-quote-post]');
    if (quote) {
        var post = quote.closest('[data-post]');
        var textarea = root.querySelector('#reply textarea[name="body"]');
        if (post && textarea) {
            var source = (post.querySelector('.post-body') || {}).textContent || '';
            var line = source.trim().replace(/\s+/g, ' ').slice(0, 120);
            textarea.value += (textarea.value ? '\n\n' : '') + '> ' + line + (source.trim().length > 120 ? '…' : '') + '\n\n';
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.focus();
        }
        return;
    }
    var copy = target.closest('[data-copy-post]');
    if (copy && navigator.clipboard && navigator.clipboard.writeText) {
        event.preventDefault();
        navigator.clipboard.writeText(copy.href).catch(function () { window.location.href = copy.href; });
    }
});
```

- [ ] **Step 6: Add keyboard/safe-area synchronization**

Set only a CSS variable; do not move the composer with hard-coded pixels:

```js
function syncKeyboardInset() {
    var viewport = window.visualViewport;
    var inset = viewport ? Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop) : 0;
    document.documentElement.style.setProperty('--keyboard-inset', inset + 'px');
}
if (window.visualViewport) {
    window.visualViewport.addEventListener('resize', syncKeyboardInset);
    window.visualViewport.addEventListener('scroll', syncKeyboardInset);
    syncKeyboardInset();
}
```

- [ ] **Step 7: Run focused browser tests and JavaScript syntax check**

```powershell
node --check public/assets/app.js
Set-Location tests/browser
npx playwright test thread-view-study.spec.ts --project=desktop
Set-Location ../..
```

Expected: PASS for both focused tests.

- [ ] **Step 8: Commit Task 5**

```powershell
git add -- public/assets/app.js tests/browser/thread-view-study.spec.ts tests/browser/package.json
git commit -m "feat: enhance Study thread interactions"
```

---

### Task 6: High-fidelity responsive Study styling

**Files:**
- Modify: `tests/browser/thread-view-study.spec.ts`
- Modify: `public/assets/app.css`

**Interfaces:**
- Consumes: Task 2–5 classes/data hooks and existing Imladris tokens.
- Produces: desktop drawer, mobile sheet, quiet stream, toolbar visibility, modal geometry, reduced-motion behavior, and evidence screenshots `80-thread-study`/`81-thread-tools`.

- [ ] **Step 1: Add failing geometry, touch, theme, and reduced-motion assertions**

Extend the focused spec:

```ts
test('Study layout matches desktop and mobile geometry', async ({ page }, info) => {
  await login(page);
  await openSeedTopic(page);
  const thread = page.locator('[data-thread-study]');
  const box = await thread.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.width).toBeLessThanOrEqual(860);
  await page.getByRole('button', { name: 'Topic tools' }).click();
  const tools = page.locator('[data-topic-tools]');
  const toolsBox = await tools.boundingBox();
  expect(toolsBox).not.toBeNull();
  if (info.project.name === 'desktop') {
    expect(toolsBox!.width).toBeLessThanOrEqual(392);
    const viewport = page.viewportSize();
    expect(viewport).not.toBeNull();
    expect(Math.abs((toolsBox!.x + toolsBox!.width) - viewport!.width)).toBeLessThanOrEqual(2);
  } else {
    expect(toolsBox!.width).toBeCloseTo(390, 0);
    expect(toolsBox!.height).toBeLessThanOrEqual(844 * 0.86 + 1);
    const actionBoxes = await page.locator('[data-post-toolbar] button:visible, [data-post-toolbar] summary:visible').evaluateAll((items) => items.map((item) => {
      const box = item.getBoundingClientRect();
      return { width: box.width, height: box.height };
    }));
    expect(actionBoxes.every((item) => item.width >= 44 && item.height >= 44)).toBe(true);
  }
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth)).toBe(true);
});

test('reduced motion removes Study animations', async ({ page }) => {
  await page.emulateMedia({ reducedMotion: 'reduce' });
  await login(page);
  await openSeedTopic(page);
  await page.getByRole('button', { name: 'Topic tools' }).click();
  const duration = await page.locator('[data-topic-tools]').evaluate((element) => getComputedStyle(element).animationDuration);
  expect(duration).toBe('0s');
});

test('light and dark Study surfaces retain readable semantic colors', async ({ page }) => {
  await login(page);
  await openSeedTopic(page);
  for (const theme of ['light', 'dark'] as const) {
    await page.locator('html').evaluate((element, value) => element.setAttribute('data-theme', value), theme);
    const colors = await page.locator('[data-thread-study]').evaluate((element) => {
      const style = getComputedStyle(element);
      return { foreground: style.color, background: style.backgroundColor };
    });
    expect(colors.foreground).not.toBe(colors.background);
  }
});

test('mobile composer honors a representative keyboard inset', async ({ page }, info) => {
  test.skip(info.project.name !== 'mobile');
  await login(page);
  await openSeedTopic(page);
  await page.locator('#reply textarea[name="body"]').focus();
  await page.locator('html').evaluate((element) => element.style.setProperty('--keyboard-inset', '240px'));
  const composer = page.locator('[data-thread-composer]');
  const box = await composer.boundingBox();
  expect(box).not.toBeNull();
  expect(box!.y + box!.height).toBeLessThanOrEqual(844 - 240 + 2);
});
```

Add a `shot()` helper using the existing `docs/evidence/browser/<project>` convention and capture the closed Study page as `80-thread-study.png` and open tools as `81-thread-tools.png`.

- [ ] **Step 2: Run geometry tests and verify red**

```powershell
Set-Location tests/browser
npx playwright test thread-view-study.spec.ts
Set-Location ../..
```

Expected: FAIL on drawer/sheet geometry and 44px targets before the Study CSS exists.

- [ ] **Step 3: Add the desktop Study surface and quiet post rules**

Append one clearly labeled section to `app.css` using only existing tokens:

```css
/* ══ Thread View — The Study ═════════════════════════════════════════════ */
.thread-study { width: min(100%, 860px); margin-inline: auto; }
.thread-study-head { margin: 0 0 16px; padding: 0 0 14px; border-bottom: 1px solid var(--border-hair); }
.thread-study-title { max-width: 28ch; margin: 12px 0 0; font-size: 2.15rem; line-height: 1.14; text-wrap: balance; }
.thread-status-chip, .thread-state-chip { display: inline-flex; align-items: center; vertical-align: .35em; margin-right: 8px; padding: 3px 10px; border: 1px solid var(--border-hair); border-radius: var(--radius-pill); font: 400 .6rem/1.2 var(--font-label); letter-spacing: .14em; text-transform: uppercase; white-space: nowrap; }
.thread-status-chip.is-open { color: var(--on-pending); background: var(--surface-pending); }
.thread-status-chip.is-needs_answer { color: var(--on-review); background: var(--surface-review); border-color: var(--gold-200); }
.thread-status-chip.is-solved { color: var(--on-done); background: var(--surface-done); border-color: var(--green-200); }
.thread-status-chip.is-decision_made { color: var(--green-800); background: var(--brand-subtle); border-color: var(--green-200); }
.thread-status-chip.is-archived, .thread-state-chip.is-locked { color: var(--text-muted); background: var(--surface-sunken); }
.thread-state-chip.is-pinned { color: var(--gold-700); background: var(--gold-100); border-color: var(--gold-200); }
.thread-facts { display: flex; align-items: center; flex-wrap: wrap; gap: 10px 12px; margin-top: 10px; }
.thread-facts .thread-byline { margin: 0; }
.thread-facts .thread-participants { margin-left: auto; }
.topic-tools-open, .star-btn { min-height: 32px; border: 1px solid var(--border-strong); border-radius: var(--radius-pill); padding: 0 13px; background: var(--surface-raised); }
.post-stream { gap: 0; }
.post { position: relative; display: flex; gap: 16px; margin: 6px 0; padding: 10px 14px; border: 1px solid transparent; border-radius: var(--radius-lg); background: transparent; box-shadow: none; }
.post-accepted { border-color: var(--green-200); background: var(--surface-done); }
.post-avatar, .post-avatar-spacer { width: 48px; flex-basis: 48px; }
.post-avatar > .monogram, .post-avatar > .avatar-wrap > .monogram { width: 44px; height: 44px; font-size: .95rem; }
.post-body { max-width: 66ch; font-size: 1.02rem; line-height: 1.62; }
.post-title-chip { display: inline-flex; padding: 1px 8px; border: 1px solid var(--border-hair); border-radius: var(--radius-pill); background: var(--surface-sunken); color: var(--text-muted); font: .58rem/1.4 var(--font-label); letter-spacing: .1em; text-transform: uppercase; }
.post-day-divider { display: flex; align-items: center; gap: 14px; margin: 26px 0 12px; }
.post-day-divider span { flex: 1; height: 1px; background: var(--border-hair); }
.post-day-divider time { color: var(--text-faint); font: .66rem var(--font-label); letter-spacing: .18em; text-transform: uppercase; }
.study-living-brief { border-left: 3px solid var(--rule-gold); }
.poll-card { padding: 15px 18px 13px; border-left-width: 1px; }
.thread-dock { background: linear-gradient(to top, var(--surface-page) 82%, transparent); padding-top: 14px; }
.thread-composer-identity { display: flex; align-items: center; gap: 9px; padding-bottom: 8px; border-bottom: 1px solid var(--border-hair); }
.thread-composer-identity .monogram { width: 24px; height: 24px; font-size: .58rem; }
.thread-composer-identity .composer-label { margin: 0; }
```

- [ ] **Step 4: Add enhanced drawer, modal, and toolbar rules**

```css
.topic-tools { margin: 14px 0; border: 1px solid var(--border-hair); border-radius: var(--radius-lg); background: var(--surface-raised); }
body.topic-tools-open, body.thread-restructure-open { overflow: hidden; }
[data-thread-enhanced="1"] .topic-tools:not([hidden]) { position: fixed; inset: 0 0 0 auto; z-index: 80; display: flex; flex-direction: column; width: min(392px, 92vw); margin: 0; border-width: 0 0 0 1px; border-radius: 0; background: var(--surface-raised); box-shadow: var(--shadow-xl); animation: study-slide-in 260ms var(--ease-calm); }
.topic-tools-scrim:not([hidden]) { position: fixed; inset: 0; z-index: 79; background: rgba(22,29,36,.42); backdrop-filter: blur(2px); }
.topic-tools-head { display: flex; align-items: center; gap: 10px; padding: 16px 18px 14px; border-bottom: 1px solid var(--border-hair); }
.topic-tools-head h2 { margin: 0; font-size: 1.3rem; }
.topic-tools-close { margin-left: auto; }
.topic-tools-body { flex: 1; overflow: auto; padding: 10px 14px 20px; }
.topic-tools-body > details { border-bottom: 1px solid var(--border-hair); }
.topic-tools-body > details > summary { display: flex; align-items: center; gap: 9px; min-height: 44px; padding: 8px 4px; font: .8rem var(--font-label); cursor: pointer; }
.topic-tools-body > details > summary span:last-child { margin-left: auto; color: var(--text-faint); font: .64rem var(--font-mono); }
.topic-tools-section-body { display: grid; gap: 10px; padding: 2px 4px 14px; }
.topic-tools-section-body form { display: grid; gap: 7px; margin: 0; }
.post-toolbar { position: absolute; top: -13px; right: 12px; z-index: 30; display: flex; gap: 1px; padding: 3px; border: 1px solid var(--border-soft); border-radius: var(--radius-pill); background: var(--surface-raised); box-shadow: var(--shadow-md); }
[data-thread-enhanced="1"] .post-toolbar { opacity: 0; pointer-events: none; transition: opacity var(--dur-fast) var(--ease-calm); }
[data-thread-enhanced="1"] .post:hover .post-toolbar, [data-thread-enhanced="1"] .post:focus-within .post-toolbar, [data-thread-enhanced="1"] .post:has([data-post-menu][open]) .post-toolbar { opacity: 1; pointer-events: auto; }
.post-toolbar-button { display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border: 0; border-radius: var(--radius-pill); background: transparent; color: var(--text-muted); }
.post-menu { position: relative; }
.post-menu-pop { position: absolute; top: calc(100% + 8px); right: 0; z-index: 60; min-width: 188px; padding: 5px; border: 1px solid var(--border-soft); border-radius: var(--radius-md); background: var(--surface-raised); box-shadow: var(--shadow-lg); }
[data-thread-enhanced="1"] .post-native-disclosure > summary { display: none; }
.thread-restructure-scrim:not([hidden]) { position: fixed; inset: 0; z-index: 100; background: rgba(22,29,36,.42); backdrop-filter: blur(3px); }
[data-thread-enhanced="1"] .thread-restructure > summary { display: none; }
[data-thread-enhanced="1"] .thread-restructure[open] .thread-restructure-dialog { position: fixed; top: 8vh; left: 50%; z-index: 110; width: min(600px, 92vw); max-height: 80vh; overflow: auto; transform: translateX(-50%); padding: 22px 26px 24px; border-radius: var(--radius-lg); background: var(--surface-raised); box-shadow: var(--shadow-xl); }
@keyframes study-slide-in { from { transform: translateX(104%); } to { transform: translateX(0); } }
```

- [ ] **Step 5: Add mobile, theme, keyboard, and reduced-motion rules**

```css
:root { --keyboard-inset: 0px; }
@media (max-width: 768px) {
  .thread-study-title { font-size: 1.75rem; max-width: none; }
  .thread-facts .thread-participants-label { display: none; }
  .thread-facts .thread-participants { margin-left: 0; }
  [data-thread-enhanced="1"] .topic-tools:not([hidden]) { inset: auto 0 0 0; width: 100%; max-height: 86dvh; border-width: 1px 0 0; border-radius: var(--radius-lg) var(--radius-lg) 0 0; animation-name: study-sheet-in; }
  .topic-tools-head { position: relative; padding-top: 22px; }
  .topic-tools-head::before { content: ""; position: absolute; top: 7px; left: 50%; width: 42px; height: 4px; transform: translateX(-50%); border-radius: var(--radius-pill); background: var(--border-strong); }
  .topic-tools-close, [data-thread-restructure-close] { width: 44px; height: 44px; }
  .topic-tools-section-body button, .topic-tools-section-body select, .topic-tools-section-body input:not([type="checkbox"]):not([type="radio"]), .thread-restructure-dialog button, .thread-restructure-dialog select, .thread-restructure-dialog input { min-height: 44px; }
  .post { gap: 10px; padding: 10px 4px; }
  [data-thread-enhanced="1"] .post-toolbar { position: static; opacity: 1; pointer-events: auto; margin-top: 8px; border: 0; box-shadow: none; background: transparent; }
  .post-toolbar-button, .post-toolbar > form > button, .post-toolbar > details > summary { width: 44px; height: 44px; }
  [data-thread-enhanced="1"] .thread-restructure[open] .thread-restructure-dialog { inset: 0; width: 100%; max-height: none; transform: none; border-radius: 0; padding-bottom: max(24px, env(safe-area-inset-bottom)); }
  .thread-dock { padding-bottom: max(env(safe-area-inset-bottom), var(--keyboard-inset)); }
}
@keyframes study-sheet-in { from { transform: translateY(104%); } to { transform: translateY(0); } }
@media (prefers-reduced-motion: reduce) {
  [data-thread-enhanced="1"] .topic-tools:not([hidden]),
  [data-thread-enhanced="1"] .thread-restructure[open] .thread-restructure-dialog,
  .post-toolbar { animation: none; transition: none; }
}
```

Use semantic tokens so `[data-theme="dark"]` inherits correctly; add only narrowly required dark overrides where parchment primitives would otherwise remain light.

- [ ] **Step 6: Run focused browser tests and inspect both new screenshots**

```powershell
Set-Location tests/browser
npx playwright test thread-view-study.spec.ts
Set-Location ../..
```

Expected: all desktop/mobile tests PASS and `80-thread-study.png`/`81-thread-tools.png` exist in both evidence folders. Open all four images and compare spacing, widths, chips, gold rule, accepted plate, toolbar, and sheet geometry to the committed prototype source.

- [ ] **Step 7: Commit Task 6**

```powershell
git add -- public/assets/app.css tests/browser/thread-view-study.spec.ts docs/evidence/browser/desktop/80-thread-study.png docs/evidence/browser/desktop/81-thread-tools.png docs/evidence/browser/mobile/80-thread-study.png docs/evidence/browser/mobile/81-thread-tools.png
git commit -m "style: match the Imladris Study thread view"
```

---

### Task 7: Migrate existing browser, a11y, and evidence contracts

**Files:**
- Modify: `tests/browser/gate-a.spec.ts`
- Modify: `tests/browser/a11y.spec.ts`
- Modify: `tests/browser/thread-intelligence.spec.ts`
- Modify: `tests/browser/community-inbox-theme.spec.ts`
- Modify: `tests/browser/package.json`
- Modify: `tests/browser/README.md`
- Modify: `docs/evidence/browser/README.md`

**Interfaces:**
- Consumes: final Study selectors and interactions from Tasks 2–6.
- Produces: updated CI evidence/a11y journeys and prose index.

- [ ] **Step 1: Run existing focused specs and record the expected stale-selector failures**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test gate-a.spec.ts --grep 'topic workflow|split or merge'
npx playwright test a11y.spec.ts --grep 'topic workflow|split/merge'
npx playwright test thread-intelligence.spec.ts --grep 'curator controls'
npx playwright test community-inbox-theme.spec.ts
Set-Location ../..
```

Expected before edits: failures on `.wf-bar`, `.wf-actions`, `.sm-panel`, or the old curator disclosure location.

- [ ] **Step 2: Add one shared local helper pattern per spec**

In each affected spec, use its local `Page` type and add:

```ts
async function openTopicTools(page: Page, section: 'watch' | 'standing' | 'tags' | 'memory' | 'management') {
  const trigger = page.getByRole('button', { name: 'Topic tools' });
  await trigger.click();
  const tools = page.locator('[data-topic-tools]');
  await expect(tools).toBeVisible();
  const details = tools.locator(`[data-topic-tools-section="${section}"]`);
  if (!(await details.evaluate((element) => (element as HTMLDetailsElement).open))) await details.locator(':scope > summary').click();
  return { tools, details };
}
```

Do not create a cross-spec helper module solely for this change.

- [ ] **Step 3: Update Gate A workflow and split/merge journeys**

For each POST/redirect cycle, reopen Topic tools because the server returns fresh HTML:

```ts
let standing = await openTopicTools(page, 'standing');
await standing.details.locator('select[name="status"]').selectOption('needs_answer');
await standing.details.locator('input[name="reason"]').fill('Needs a reply');
await standing.details.getByRole('button', { name: 'Update status' }).click();
await expect(page.locator('[data-thread-status="needs_answer"]')).toBeVisible();

let watch = await openTopicTools(page, 'watch');
await watch.details.locator('select[name="until"]').selectOption('tomorrow');
await watch.details.getByRole('button', { name: 'Save snooze' }).click();
await expect(page.locator('.thread-byline')).toContainText('Quiet until');
```

Use Management for assignment and `data-thread-restructure-open` for split/merge. Refresh `29-topic-workflow`, `50-split-merge-panel`, and `51-thread-merged` through the existing `shot()` helper.

- [ ] **Step 4: Update a11y and Thread Intelligence journeys**

In `a11y.spec.ts`, open Standing and scan `[data-topic-tools]`; open split/merge and scan `.thread-restructure-dialog`. In `thread-intelligence.spec.ts`, open Memory before locating refresh/publish/retire/restore controls, then refresh `77-living-brief-curator-controls`.

Keep the existing lineage link, curator attribution, sources, related cards, no-JS, last-good, and admin assertions unchanged.

- [ ] **Step 5: Extend dynamic Inbox coverage**

In `community-inbox-theme.spec.ts`, after the topic is fetched into `[data-inbox-reading]`, open and close Topic tools, hover/focus a post toolbar, and assert the thread dock remains within the reading pane. Keep the canonical no-JS reply journey unchanged.

- [ ] **Step 6: Update evidence commands and prose indexes**

Ensure `tests/browser/package.json` contains `thread-view-study.spec.ts` immediately before `gate-a.spec.ts` in `scripts.evidence`. Add `80-thread-study` and `81-thread-tools` to `tests/browser/README.md` and `docs/evidence/browser/README.md`, and state that `29`, `50`, `51`, and `77` now capture the Study locations.

- [ ] **Step 7: Run all affected browser contracts**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test thread-view-study.spec.ts community-inbox-theme.spec.ts
npx playwright test gate-a.spec.ts --grep 'topic workflow|split or merge'
Set-Location ../..
```

Expected: PASS in desktop and mobile projects.

- [ ] **Step 8: Commit Task 7**

```powershell
git add -- tests/browser/gate-a.spec.ts tests/browser/a11y.spec.ts tests/browser/thread-intelligence.spec.ts tests/browser/community-inbox-theme.spec.ts tests/browser/package.json tests/browser/README.md docs/evidence/browser/README.md docs/evidence/browser/desktop docs/evidence/browser/mobile
git commit -m "test: migrate browser evidence to Study tools"
```

---

### Task 8: Full verification and completion evidence

**Files:**
- Verify all changed files.
- Modify generated evidence only when produced by the approved Playwright commands.

**Interfaces:**
- Consumes: all prior tasks.
- Produces: fresh PHPUnit, Playwright, axe, syntax, diff, and visual evidence required by `DESIGN.md` §13.

- [ ] **Step 1: Run PHP syntax checks on every changed PHP file**

```powershell
$phpFiles = @(
  'src/Repository/PostRepository.php',
  'src/Controller/ThreadController.php',
  'templates/thread.php',
  'templates/partials/thread_status_history.php',
  'templates/partials/thread_tools.php',
  'templates/partials/thread_restructure.php',
  'templates/partials/post.php',
  'templates/partials/post_toolbar.php',
  'templates/partials/composer.php',
  'templates/partials/living_brief.php',
  'templates/partials/thread_memory_tools.php',
  'tests/Integration/Core/AppThreadViewStudyTest.php',
  'tests/Integration/Core/AppImladrisFidelityTest.php',
  'tests/Integration/Core/AppThreadUxAuditTest.php'
)
foreach ($file in $phpFiles) { php -l $file; if ($LASTEXITCODE -ne 0) { exit $LASTEXITCODE } }
node --check public/assets/app.js
```

Expected: “No syntax errors detected” for every file.

- [ ] **Step 2: Run focused PHPUnit suites**

```powershell
vendor\bin\phpunit tests/Integration/Core/AppThreadViewStudyTest.php
vendor\bin\phpunit tests/Integration/Core/AppThreadUxAuditTest.php
vendor\bin\phpunit tests/Integration/Core/AppImladrisFidelityTest.php
vendor\bin\phpunit tests/Integration/Core/AppThreadTagDisplayTest.php
vendor\bin\phpunit tests/Integration/Core/AppPollTest.php
vendor\bin\phpunit tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
```

Expected: zero failures, errors, warnings, or risky tests.

- [ ] **Step 3: Run the full PHPUnit suite**

```powershell
composer test
```

Expected: exit 0. A skipped test already documented by the repository is acceptable; new skips are not.

- [ ] **Step 4: Run focused browser and accessibility suites**

```powershell
Set-Location tests/browser
npm run prepare-db
npx playwright test thread-view-study.spec.ts community-inbox-theme.spec.ts
$env:RB_BROWSER_DARK_SURFACES = '1'
npm run prepare-db
npx playwright test a11y.spec.ts --grep 'topic workflow|split/merge'
Remove-Item Env:RB_BROWSER_DARK_SURFACES
npm run prepare-db
npx playwright test thread-intelligence.spec.ts --grep 'curator controls|no-JS|axe'
Set-Location ../..
```

Expected: zero failures across desktop and mobile.

- [ ] **Step 5: Regenerate standard browser evidence and full a11y evidence**

```powershell
Set-Location tests/browser
npm run evidence
npm run a11y
Set-Location ../..
```

Expected: both commands exit 0 and refresh the numbered screenshots, including `03-thread`, `29-topic-workflow`, `50-split-merge-panel`, `51-thread-merged`, `77-living-brief-curator-controls`, `80-thread-study`, and `81-thread-tools` for both projects where applicable.

- [ ] **Step 6: Perform visual QA against the committed source**

Open the implementation captures, not the prototype runtime. Compare them to the exact inline values in `ThreadView.dc.html` and the handoff README:

- 860px reading width and balanced title;
- status/Pinned/Locked chips and quiet fact row;
- gold-rule Living Brief and compact poll;
- 48px identity column, 44px monograms, accepted plate, day divider, reaction pills, and action toolbar;
- 392px desktop drawer, 86dvh mobile sheet, 600px modal/full-screen mobile modal;
- composer containment, safe-area inset, 44px touch targets, and no horizontal overflow;
- light/dark token fidelity and reduced-motion state.

If any P0/P1/P2 discrepancy remains, fix it under the corresponding task and rerun the affected browser test before continuing.

- [ ] **Step 7: Run final repository hygiene checks**

```powershell
node --check public/assets/app.js
git diff --check
git status --short
```

Expected: JavaScript syntax exit 0, no diff-check output, and status contains only intended implementation/evidence files plus the pre-existing unrelated user changes listed in Global Constraints.

- [ ] **Step 8: Commit final evidence-only updates if the evidence run changed them**

```powershell
git add -- docs/evidence/browser tests/browser/README.md
git commit -m "docs: refresh Study thread evidence"
```

Skip this commit only when the generated evidence is byte-identical and nothing is staged.
