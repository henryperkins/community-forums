# Living Brief Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild the living brief's reading surface and curator tools on a topic page — remove the redundant heading, surface the paused state to members, move curator controls to the foot of the brief with one primary action, and fix two live correctness bugs.

**Architecture:** Server-rendered PHP templates plus one CSS block. No new JavaScript. Every control is a plain form or a `<details>` disclosure so the whole surface works with JS off. One new POST route (`.../summary/automation/pause`) mirroring the existing resume route. No schema change.

**Tech Stack:** PHP 8.2, hand-rolled `View`/`Router`/`Container`, MySQL/MariaDB, PHPUnit 11, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-26-living-brief-redesign-design.md`

## Global Constraints

- **No literal hex colours.** Every colour is a token from `public/assets/imladris.css`. A literal hex will not flip with `[data-theme="dark"]` and is a bug.
- **`--text-body` is an ink colour, not a size.** The size token is `--text-size-body`. `font-size: var(--text-body)` silently inherits.
- **Do not use `--surface-cool`** for anything new — it has no dark override (`imladris.css:117`). Use `--surface-sunken`.
- **Danger classes are `.btn.danger` / `.linkbtn.danger`** (`app.css:232-234`, `:273`), *not* imladris's `.btn-danger`. `app.css` is unlayered and outranks `imladris.css` regardless of order.
- **All new CSS goes in `public/assets/app.css`**, appended to the Living Brief block that ends at line 10215.
- **No inline `<script>`, no inline `style=`, no `onclick`.** CSP is `script-src 'self'; style-src 'self'` with no nonce.
- **Everything must work with JavaScript off.**
- **Copy uses "replies", not "counsels"**, and the eligibility threshold is **8**, not six.
- **Preserve the literal string `class="post-body formatted-content"`** in `living_brief.php` — `tests/Unit/Core/FormattedContentContractTest.php:24-26` asserts it.
- **Every new curator affordance is guarded `!empty($can_curate_memory)`** inside the partial that renders it, not only at the call site.
- Run tests with `vendor/bin/phpunit` directly (`composer test` hits Composer's 300s timeout on this machine). The worktree's test DB is `retroboards_test_lb`.

---

### Task 1: Remove the brief's own heading (§1)

**Files:**
- Modify: `templates/partials/living_brief.php:2-13`
- Modify: `public/assets/app.css:10156-10157`
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `.living-brief-head` becomes a flex row of exactly two children (`.living-brief-label`, `.living-brief-meta`). Later tasks add siblings *after* the head, never inside it.

- [ ] **Step 1: Write the failing test**

Add to `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`:

```php
    public function test_living_brief_has_no_redundant_heading_and_keeps_an_accessible_name(): void
    {
        $seed = $this->seedThread(8, 'Brief heading removal');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringNotContainsString('Where the discussion stands', $html);
        self::assertStringNotContainsString('living-brief-heading', $html);
        self::assertStringContainsString('aria-label="Living brief"', $html);
        self::assertStringContainsString('/privacy#thread-intelligence', $html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_living_brief_has_no_redundant_heading_and_keeps_an_accessible_name`
Expected: FAIL — `Where the discussion stands` is still present.

- [ ] **Step 3: Edit the template**

Replace `templates/partials/living_brief.php` lines 2-13 (from `<section …>` through `</div>`) with:

```php
<section class="living-brief study-living-brief" data-living-brief aria-label="Living brief">
    <div class="living-brief-head">
        <p class="living-brief-label">
            <?php if (!empty($living_brief['has_ai_lineage'])): ?>
                <a href="/privacy#thread-intelligence"><?= $e($living_brief['label']) ?></a>
            <?php else: ?>
                <?= $e($living_brief['label']) ?>
            <?php endif; ?>
        </p>
```

The wrapping `<div>` that opened at line 4 and closed at line 13 is removed with the `<h2>` — it existed only to stack the label above the heading. `.living-brief-meta` (previously line 14) and the curate button (line 19) now sit directly inside `.living-brief-head`.

- [ ] **Step 4: Drop the dead CSS selector**

In `public/assets/app.css`, lines 10156-10157 currently read:

```css
.living-brief-head h2,
.related-topic-fallback h2 { margin: 0; }
```

Replace both lines with:

```css
.related-topic-fallback h2 { margin: 0; }
```

Keep the `.related-topic-fallback h2` half — it is still live via `templates/thread.php:191`.

- [ ] **Step 5: Run the test and the surface suite**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`
Expected: PASS. If `test_thread_dom_order_has_one_memory_slot_no_empty_panel_and_public_disclosure` fails, it is asserting the removed `h2` — update that assertion, do not weaken the `substr_count(…) === 1` or DOM-order checks.

- [ ] **Step 6: Commit**

```bash
git add templates/partials/living_brief.php public/assets/app.css tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
git commit -m "feat(living-brief): let the topic title be the region's visual head"
```

---

### Task 2: Member-visible paused status line (§2a)

**Files:**
- Modify: `templates/thread.php:183-188` (partial payload)
- Modify: `templates/partials/living_brief.php` (new status line after the head)
- Modify: `templates/partials/thread_memory_tools.php:5-11` (remove the moved copy)
- Modify: `templates/partials/icon.php` (add a `pause` icon)
- Modify: `public/assets/app.css` (append after line 10215)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: Task 1's `.living-brief-head` shape.
- Produces: `$memory_automation_paused` (bool) is now in `living_brief.php`'s scope — Task 5 relies on it. Markup contract: exactly one `.living-brief-status` element per brief, carrying a modifier class (`.is-paused`) so §2b's last-good variant can be added later without re-layout.

- [ ] **Step 1: Write the failing test**

```php
    public function test_paused_automation_is_visible_to_members_not_only_curators(): void
    {
        $seed = $this->seedThread(8, 'Paused brief visibility');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $this->queue()->setAutomationPaused($seed['thread_id'], true, null);

        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringContainsString(
            'Automatic refresh is paused for this topic. The brief stands as published.',
            $html,
        );
        self::assertSame(1, substr_count($html, 'living-brief-status'));
    }
```

**There is no `$this->container` in `tests/Support/TestCase.php`** — the surface test hand-builds
its services in private helpers (`viewService()` at `:221`, `memory()` at `:250`). Before writing
the test, extract the queue construction that `memory()` already performs at `:256-267` into its
own private helper and have `memory()` call it:

```php
    private function queue(): ThreadIntelligenceQueue
    {
        $apiKey = 'sk-test-surface';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );

        return new ThreadIntelligenceQueue(
            $this->db,
            $jobs,
            new ThreadIntelligenceEligibility(
                $this->db,
                new FeatureFlags(new SettingRepository($this->db)),
                $config,
                $settings,
                new ThreadIntelligenceBudget($this->db, $config),
                $jobs,
            ),
        );
    }
```

Then replace `memory()`'s local `$queue = new ThreadIntelligenceQueue(…)` with `$queue = $this->queue();`. All the imports this needs are already in the file.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_paused_automation_is_visible_to_members_not_only_curators`
Expected: FAIL — the guest response contains no `living-brief-status`.

- [ ] **Step 3: Pass the flag into the partial**

In `templates/thread.php`, the `partials/living_brief` call (currently lines 183-188) becomes:

```php
            <?= $this->partial('partials/living_brief', [
                'living_brief' => $living_brief,
                'living_brief_sources' => $living_brief_sources,
                'living_brief_related' => $living_brief_related,
                'can_curate_memory' => !empty($can_write) && !empty($can_curate_memory),
                'memory_automation_paused' => $memory_automation_paused,
            ]) ?>
```

- [ ] **Step 4: Add the `pause` icon**

In `templates/partials/icon.php`, add to the `$iconStroke` map (alongside the other entries):

```php
    'pause'           => '<rect x="6" y="5" width="4" height="14" rx="1"/><rect x="14" y="5" width="4" height="14" rx="1"/>',
```

- [ ] **Step 5: Render the status line**

In `templates/partials/living_brief.php`, immediately after the closing `</div>` of `.living-brief-head` and **before** the `.post-body` div, insert:

```php
    <?php if (!empty($memory_automation_paused)): ?>
        <p class="living-brief-status is-paused">
            <span class="living-brief-status-icon" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'pause']) ?></span>
            <span>Automatic refresh is paused for this topic. The brief stands as published.</span>
        </p>
    <?php endif; ?>
```

- [ ] **Step 6: Remove the moved copy from curator tools**

In `templates/partials/thread_memory_tools.php`, delete line 6 only:

```php
            <p class="muted">Automatic refresh is paused for this topic.</p>
```

Leave the `if`/`else` structure and the resume form at lines 7-10 exactly as they are — Task 5 restructures them.

- [ ] **Step 7: Add the CSS**

Append to `public/assets/app.css` immediately after the Living Brief media query that closes at line 10215:

```css
/* Living Brief — one status line. Only ever one renders. */
.living-brief-status {
    display: flex;
    align-items: flex-start;
    gap: var(--space-2);
    margin: var(--space-3) 0 0;
    font-size: .87rem;
    line-height: 1.5;
}
.living-brief-status.is-paused {
    padding: 0;
    color: var(--text-muted);
}
.living-brief-status-icon {
    flex: 0 0 auto;
    display: inline-flex;
    margin-top: 2px;
}
.living-brief-status-icon svg { width: 13px; height: 13px; }
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ tests/Integration/Core/AppPhase4GateATest.php`
Expected: PASS.

- [ ] **Step 9: Commit**

```bash
git add templates/thread.php templates/partials/living_brief.php templates/partials/thread_memory_tools.php templates/partials/icon.php public/assets/app.css tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
git commit -m "feat(living-brief): show paused automation to members, not only curators"
```

---

### Task 3: Pause route (§3 backend)

**Files:**
- Modify: `src/Service/CommunityMemoryService.php` (add `pauseAutomation()` after `resumeAutomation()` at line 189-196)
- Modify: `src/Controller/CommunityMemoryController.php` (add `pauseAutomation()` after `resumeAutomation()` at lines 29-39)
- Modify: `src/Core/App.php:2136` (register the route)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `POST /t/{id}/summary/automation/pause`, and `CommunityMemoryService::pauseAutomation(User $actor, int $threadId): void`. Task 5 renders the form that posts to it.

- [ ] **Step 1: Write the failing test**

```php
    public function test_pause_automation_is_curator_gated_and_records_the_actor(): void
    {
        $seed = $this->seedThread(8, 'Pause automation route');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');

        $member = $this->makeUser(['username' => 'pause-member']);
        $this->actingAs($member);
        $this->post('/t/' . $seed['thread_id'] . '/summary/automation/pause', []);
        self::assertNotSame(
            1,
            (int) $this->db->fetchValue(
                'SELECT COALESCE(automation_paused, 0) FROM thread_intelligence_jobs WHERE thread_id = ?',
                [$seed['thread_id']],
            ),
        );

        $admin = $this->makeAdmin(['username' => 'pause-curator']);
        $this->actingAs($admin);
        $allowed = $this->post('/t/' . $seed['thread_id'] . '/summary/automation/pause', []);
        $this->assertStatus(302, $allowed);

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT automation_paused FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame($admin['id'], (int) $this->db->fetchValue(
            'SELECT paused_by FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_pause_automation_is_curator_gated_and_records_the_actor`
Expected: FAIL — the route 404s (no match).

- [ ] **Step 3: Add the service method**

In `src/Service/CommunityMemoryService.php`, directly after `resumeAutomation()` (which ends at line 196), add:

```php
    public function pauseAutomation(User $actor, int $threadId): void
    {
        $this->db->transaction(function () use ($actor, $threadId): void {
            $thread = $this->threads->findForUpdate($threadId);
            $this->assertCuratorForLockedThread($actor, $thread);
            $this->threadIntelligence?->setAutomationPaused($threadId, true, $actor->id());
        });
    }
```

This is an exact mirror of `resumeAutomation()`. `setAutomationPaused()` already exists at `src/Service/ThreadIntelligence/ThreadIntelligenceQueue.php:132` and handles both directions, persisting `paused_by` and `paused_at`. **Do not add a `moderation_log` write** — none of the six existing memory actions has one.

- [ ] **Step 4: Add the controller action**

In `src/Controller/CommunityMemoryController.php`, directly after `resumeAutomation()` (ends line 39), add:

```php
    /** @param array<string,string> $params */
    public function pauseAutomation(Request $request, array $params): Response
    {
        $this->requireMemory();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(CommunityMemoryService::class)->pauseAutomation($user, $threadId),
            $this->threadUrl($threadId),
            'Automatic refresh paused.',
        );
    }
```

- [ ] **Step 5: Register the route**

In `src/Core/App.php`, immediately after line 2136 (the resume route), add:

```php
        $r->post('/t/{id}/summary/automation/pause', [CommunityMemoryController::class, 'pauseAutomation']);
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ tests/Integration/Core/AppFeatureFlagTest.php`
Expected: PASS. `AppFeatureFlagTest` must still show the route dark when `community_memory` is rolled back — `requireMemory()` handles that.

- [ ] **Step 7: Commit**

```bash
git add src/Service/CommunityMemoryService.php src/Controller/CommunityMemoryController.php src/Core/App.php tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
git commit -m "feat(living-brief): add a curator-gated pause-automation route"
```

---

### Task 4: Relocate curator tools to the foot of the brief (§3, part 1)

Behaviour-preserving move. The controls keep their current shape; only their location and gating change. Task 5 restructures them.

**Files:**
- Modify: `templates/thread.php:183-188` (payload) and `:180` is untouched
- Modify: `templates/partials/living_brief.php` (render the tools partial at the foot; delete line 19)
- Modify: `templates/partials/thread_memory_tools.php:2-3, 61-62` (drop the dead `$embedded` wrapper; add the `$can_curate_memory` guard)
- Modify: `templates/partials/thread_tools.php:96-103` (replace the duplicated controls with a link)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: `$memory_automation_paused` from Task 2.
- Produces: `living_brief.php` now receives `$thread`, `$memory_history`, `$memory_refresh`. `partials/thread_memory_tools` renders inside `.living-brief` and is guarded by `!empty($can_curate_memory)` internally. Anchor id `living-brief-curator-{threadId}` exists for `thread_tools.php` to link to.

- [ ] **Step 1: Write the failing test**

```php
    public function test_curator_tools_render_inside_the_brief_and_never_for_members(): void
    {
        $seed = $this->seedThread(8, 'Curator tools relocation');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];

        $admin = $this->makeAdmin(['username' => 'tools-curator']);
        $this->actingAs($admin);
        $curatorHtml = $this->get($url)->body();
        $briefStart = strpos($curatorHtml, 'data-living-brief');
        $tools = strpos($curatorHtml, 'living-brief-curator-' . $seed['thread_id']);
        self::assertNotFalse($briefStart);
        self::assertNotFalse($tools);
        self::assertLessThan($tools, $briefStart);
        self::assertSame(1, substr_count($curatorHtml, 'action="/t/' . $seed['thread_id'] . '/summary/refresh"'));

        $member = $this->makeUser(['username' => 'tools-member']);
        $this->actingAs($member);
        $memberHtml = $this->get($url)->body();
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $memberHtml);

        $this->logoutClient();
        $guestHtml = $this->get($url)->body();
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $guestHtml);
    }
```

`actingAs()` takes an array and cannot be passed `null`; `logoutClient()` (`tests/Support/TestCase.php:266`) clears the cookie jar and is the guest mechanism.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_curator_tools_render_inside_the_brief_and_never_for_members`
Expected: FAIL — `living-brief-curator-{id}` does not exist.

- [ ] **Step 3: Extend the partial payload**

In `templates/thread.php`, the `partials/living_brief` call becomes:

```php
            <?= $this->partial('partials/living_brief', [
                'thread' => $thread,
                'living_brief' => $living_brief,
                'living_brief_sources' => $living_brief_sources,
                'living_brief_related' => $living_brief_related,
                'can_curate_memory' => !empty($can_write) && !empty($can_curate_memory),
                'memory_automation_paused' => $memory_automation_paused,
                'memory_history' => $memory_history,
                'memory_refresh' => $memory_refresh,
            ]) ?>
```

All four new keys already exist in `thread.php` scope (`:102-106`). `$memory_refresh` is `[]` when `community_memory` is off (`ThreadController.php:346`) — every read must stay `?? `-guarded.

- [ ] **Step 4: Delete the old curate button and mount the tools**

In `templates/partials/living_brief.php`, delete the `.living-brief-curate` button line entirely (it was line 19 before Task 1 shifted numbering — find it by the string `data-topic-tools-open="memory"`). The generic `data-topic-tools-open` JS branch stays; `templates/thread.php:76` still uses it.

Then, as the **last** element inside the `</section>`, after the `living-brief-related` block, add:

```php
    <?php if (!empty($can_curate_memory)): ?>
        <?= $this->partial('partials/thread_memory_tools', [
            'thread' => $thread,
            'living_brief' => $living_brief,
            'memory_history' => $memory_history ?? [],
            'memory_refresh' => $memory_refresh ?? [],
            'memory_automation_paused' => !empty($memory_automation_paused),
            'can_curate_memory' => true,
        ]) ?>
    <?php endif; ?>
```

- [ ] **Step 5: Guard and unwrap the tools partial**

In `templates/partials/thread_memory_tools.php`, replace lines 1-4 with:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php $threadId = (int) $thread['id']; ?>
<div class="living-brief-curator" id="living-brief-curator-<?= $threadId ?>">
```

and replace the final two lines (61-62, `</div>` + the `$embedded` close) with a single:

```php
</div>
```

The `<details class="memory-curator-tools">` wrapper and the `$embedded` flag are both dead — the only call site hard-coded `'embedded' => true`.

Then replace every `/t/<?= (int) $thread['id'] ?>/` in the file with `/t/<?= $threadId ?>/` for consistency.

- [ ] **Step 6: Replace the duplicate in thread_tools.php with a link**

In `templates/partials/thread_tools.php`, replace lines 96-103 with:

```php
        <?php if ($showMemory): ?>
        <details data-topic-tools-section="memory">
            <summary><span>Living Brief</span><span aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'eight-point-star']) ?></span></summary>
            <div class="topic-tools-section-body">
                <p class="muted">Curator tools for this brief sit at the foot of the brief itself.</p>
                <a class="linkbtn" href="#living-brief-curator-<?= (int) $thread['id'] ?>">Go to the brief's curator tools</a>
            </div>
        </details>
        <?php endif; ?>
```

Keeping the section preserves `$hasTools` (`:8`), so boards where memory was a curator's only tool section do not lose the whole `<aside>`.

- [ ] **Step 7: Add minimal CSS for the footer container**

Append to `public/assets/app.css`:

```css
/* Living Brief — curator footer. */
.living-brief-curator {
    display: grid;
    gap: var(--space-3);
    margin-top: var(--space-4);
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-hair);
    min-width: 0;
}
```

- [ ] **Step 8: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ tests/Integration/Core/AppPhase4GateATest.php tests/Integration/Core/AppContentReferenceTest.php`
Expected: PASS. `ThreadIntelligenceOperationsServiceTest:437-441` renders `partials/living_brief` **without** a `can_curate_memory` key — the `!empty()` guard in Step 4 is what keeps it passing.

- [ ] **Step 9: Commit**

```bash
git add templates/ public/assets/app.css tests/
git commit -m "feat(living-brief): attach curator tools to the brief they act on"
```

---

### Task 5: Restructure the curator controls (§3, part 2)

**Files:**
- Modify: `templates/partials/thread_memory_tools.php` (full rewrite of the body)
- Modify: `public/assets/app.css` (append)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: Task 3's pause route; Task 4's `.living-brief-curator` container and payload.
- Produces: the final curator markup. Version rows post `summary_id` to `/t/{id}/summary/restore`, one `<form>` each, replacing the `<select id="summary-restore">`.

- [ ] **Step 1: Write the failing test**

```php
    public function test_curator_footer_has_one_primary_action_and_row_based_restore(): void
    {
        $seed = $this->seedThread(8, 'Curator footer structure');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $admin = $this->makeAdmin(['username' => 'footer-curator']);
        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        // The <select> is gone; restore is one form per version.
        self::assertStringNotContainsString('id="summary-restore"', $html);
        self::assertStringContainsString('name="summary_id"', $html);

        // Refresh is the one filled button, not a btn-small.
        self::assertMatchesRegularExpression(
            '/<button class="btn"[^>]*type="submit"[^>]*>Refresh<\/button>/',
            $html,
        );

        // More and the retire confirm are <details>, so they work without JS.
        self::assertStringContainsString('class="lb-more"', $html);
        self::assertStringContainsString('class="lb-confirm"', $html);

        // Retire is behind a confirm step, not a bare one-click submit.
        $retirePos = strpos($html, 'action="/t/' . $seed['thread_id'] . '/summary/retire"');
        $confirmPos = strpos($html, 'class="lb-confirm"');
        self::assertNotFalse($retirePos);
        self::assertNotFalse($confirmPos);
        self::assertLessThan($retirePos, $confirmPos);

        // Pause is offered when automation is running.
        self::assertStringContainsString('action="/t/' . $seed['thread_id'] . '/summary/automation/pause"', $html);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_curator_footer_has_one_primary_action_and_row_based_restore`
Expected: FAIL — `id="summary-restore"` is still present.

- [ ] **Step 3: Rewrite the tools partial body**

Replace the entire contents of `templates/partials/thread_memory_tools.php` with:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php
$threadId = (int) $thread['id'];
$paused = !empty($memory_automation_paused);
$refresh = $memory_refresh ?? [];
$history = $memory_history ?? [];
$historyLabels = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];
?>
<div class="living-brief-curator" id="living-brief-curator-<?= $threadId ?>">
    <div class="living-brief-curator-row">
        <form class="inline-form" method="post" action="/t/<?= $threadId ?>/summary/refresh">
            <?= $this->csrfField() ?>
            <button class="btn" type="submit"<?= empty($refresh['eligible']) ? ' disabled' : '' ?>>Refresh</button>
        </form>
        <details class="lb-amend">
            <summary class="linkbtn">Amend</summary>
            <form class="composer" method="post" action="/t/<?= $threadId ?>/summary">
                <?= $this->csrfField() ?>
                <label for="summary-body-<?= $threadId ?>">Summary</label>
                <textarea id="summary-body-<?= $threadId ?>" class="composer-input" name="body" rows="4" maxlength="20000"></textarea>
                <label for="summary-sources-<?= $threadId ?>">Source post IDs</label>
                <input id="summary-sources-<?= $threadId ?>" class="input" type="text" name="source_post_ids" placeholder="1, 2, 3">
                <button class="btn btn-small" type="submit">Publish amendment</button>
            </form>
        </details>
    </div>

    <?php if (empty($refresh['eligible'])): ?>
        <p class="muted living-brief-curator-note">
            <?= $e($refresh['message'] ?? 'Refresh is not currently available.') ?>
            <?php if (!empty($refresh['next_eligible_at_utc'])): ?>
                <time datetime="<?= $e($refresh['next_eligible_at_utc']) ?>"><?= $e(($refresh['next_eligible_at'] ?? '') . ' UTC') ?></time>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <details class="lb-more">
        <summary class="linkbtn"><span class="lb-more-shut">More</span><span class="lb-more-open">Less</span></summary>
        <div class="lb-more-body">
            <?php if (!empty($history)): ?>
                <p class="lb-more-title">Earlier versions</p>
                <ul class="lb-versions">
                    <?php foreach ($history as $item): ?>
                        <li class="lb-version">
                            <span class="lb-version-v">v<?= (int) $item['version'] ?></span>
                            <span class="lb-version-who"><?= $e($item['label']) ?></span>
                            <span class="lb-version-status"><?= $e($historyLabels[$item['status']] ?? ucfirst((string) $item['status'])) ?></span>
                            <?php if (!empty($item['published_at'])): ?>
                                <time class="lb-version-when" datetime="<?= $e(gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $item['published_at'] . ' UTC'))) ?>"><?= $e(human_datetime($item['published_at'])) ?></time>
                            <?php endif; ?>
                            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/restore">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="summary_id" value="<?= (int) $item['id'] ?>">
                                <button class="linkbtn" type="submit">Restore</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form class="inline-form lb-more-related" method="post" action="/t/<?= $threadId ?>/related">
                <?= $this->csrfField() ?>
                <label class="sr-only" for="related-thread-<?= $threadId ?>">Related topic ID</label>
                <input id="related-thread-<?= $threadId ?>" class="input input-small" type="number" name="related_thread_id" min="1" placeholder="Thread ID" required>
                <label class="sr-only" for="related-reason-<?= $threadId ?>">Reason</label>
                <input id="related-reason-<?= $threadId ?>" class="input" type="text" name="reason" maxlength="255" placeholder="Reason">
                <button class="btn btn-small" type="submit">Add related topic</button>
            </form>

            <div class="lb-more-foot">
                <?php if ($paused): ?>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/automation/resume">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn" type="submit">Resume automatic refresh</button>
                    </form>
                <?php else: ?>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/automation/pause">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Pause automatic refresh</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($living_brief)): ?>
                    <details class="lb-confirm">
                        <summary class="linkbtn danger">Retire brief</summary>
                        <div class="lb-confirm-body">
                            <p>Retiring hides the brief from the topic and pauses automatic refresh. Curators can restore it from this panel.</p>
                            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/retire">
                                <?= $this->csrfField() ?>
                                <button class="btn danger" type="submit">Retire brief</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </details>
</div>
```

- [ ] **Step 4: Add the CSS**

Append to `public/assets/app.css` (replacing the stub `.living-brief-curator` rule added in Task 4 with this fuller block):

```css
/* Living Brief — curator footer. Two rows: primary actions, then the disclosure. */
.living-brief-curator {
    display: grid;
    gap: var(--space-3);
    margin-top: var(--space-4);
    padding-top: var(--space-3);
    border-top: 1px solid var(--border-hair);
    min-width: 0;
}
.living-brief-curator-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
    min-width: 0;
}
.living-brief-curator-row .lb-amend[open] { flex: 1 1 100%; }
.living-brief-curator-note { margin: 0; font-size: .87rem; }

.lb-amend > summary,
.lb-more > summary,
.lb-confirm > summary { cursor: pointer; }
.lb-more > summary { display: flex; justify-content: flex-end; }
.lb-more[open] > summary .lb-more-shut,
.lb-more:not([open]) > summary .lb-more-open { display: none; }

.lb-more-body {
    display: grid;
    gap: var(--space-3);
    margin-top: var(--space-3);
    padding: var(--space-3);
    border: 1px solid var(--border-hair);
    border-radius: var(--radius-md);
    background: var(--surface-sunken);
    min-width: 0;
}
.lb-more-title {
    margin: 0;
    color: var(--text-muted);
    font-family: var(--font-label);
    font-size: .78rem;
    letter-spacing: var(--tracking-caps);
    text-transform: uppercase;
}
.lb-versions { list-style: none; margin: 0; padding: 0; display: grid; }
.lb-version {
    display: flex;
    flex-wrap: wrap;
    align-items: baseline;
    gap: var(--space-2) var(--space-3);
    padding: var(--space-2) 0;
    border-bottom: 1px solid var(--border-hair);
    min-width: 0;
}
.lb-version:last-child { border-bottom: 0; }
.lb-version-v { color: var(--gold-ink); font-family: var(--font-mono); font-size: .78rem; }
.lb-version-status,
.lb-version-when { color: var(--text-muted); font-size: .78rem; }
.lb-version form { margin-left: auto; }

.lb-more-foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: var(--space-3);
    padding-top: var(--space-2);
    border-top: 1px solid var(--border-hair);
}
.lb-more-foot .lb-confirm { margin-left: auto; }
.lb-confirm-body {
    display: grid;
    gap: var(--space-2);
    margin-top: var(--space-2);
}
.lb-confirm-body p { margin: 0; font-size: .87rem; color: var(--text-muted); }

@media (max-width: 760px) {
    .lb-more > summary { justify-content: flex-start; }
    .lb-version form,
    .lb-more-foot .lb-confirm { margin-left: 0; }
}
```

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ tests/Integration/Core/AppPhase4GateATest.php`
Expected: PASS. `AppPhase4GateATest`'s `assertSeeText` calls may reference the old "Publish summary" / "Retire summary" / "Restore summary" strings — update those assertions to the new labels. Do **not** weaken `testSummarySourceMasksAnonymousAuthor`.

- [ ] **Step 6: Commit**

```bash
git add templates/partials/thread_memory_tools.php public/assets/app.css tests/
git commit -m "feat(living-brief): give curator tools one primary action and a confirm step"
```

---

### Task 6: Curator-gate regression tests (§5, fix 1)

No production change is expected here — this task proves the gate holds across every axis. If any assertion fails, that is a live bug to fix in this task.

**Files:**
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: Tasks 3-5.
- Produces: nothing consumed downstream.

- [ ] **Step 1: Write the test**

```php
    public function test_suspended_admin_keeps_reading_but_loses_every_curator_affordance(): void
    {
        $seed = $this->seedThread(8, 'Suspended curator gate');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $admin = $this->makeAdmin(['username' => 'suspended-curator']);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY) WHERE id = ?",
            [$admin['id']],
        );
        $this->actingAs($admin);

        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringContainsString('data-living-brief', $html);
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $html);
        self::assertStringNotContainsString('living-brief-curator-' . $seed['thread_id'], $html);
    }
```

Both columns are verified to exist: `users.status` is an enum including `'suspended'`, and
`users.suspended_until` is a nullable `datetime`. `WriteGate` reads exactly these two
(`src/Security/WriteGate.php:17, 34`).

- [ ] **Step 2: Run it**

Run: `vendor/bin/phpunit --filter test_suspended_admin_keeps_reading_but_loses_every_curator_affordance`
Expected: PASS — `$can_curate_memory` is `!empty($can_write) && !empty($can_curate_memory)` (`thread.php:187`) and `can_write` is false for a suspended account ("state beats role").

If it FAILS, that is a real authorization bug: fix it in `templates/thread.php` or `ThreadController`, then re-run.

- [ ] **Step 3: Commit**

```bash
git add tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php
git commit -m "test(living-brief): pin the curator gate across role, state and guest axes"
```

---

### Task 7: Eligibility counts accessor (§5, fix 2 — backend)

**Files:**
- Modify: `src/Service/ThreadIntelligence/ThreadIntelligenceEligibility.php` (new public method; `INITIAL_POST_THRESHOLD` at `:21`, `eligiblePostCounts()` private at `:204`)
- Modify: `src/Service/ThreadIntelligence/ThreadIntelligenceViewService.php:118` (`emptyModel()`)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: nothing from earlier tasks.
- Produces: `ThreadIntelligenceEligibility::draftEligibility(int $threadId): array` returning exactly `['eligible' => int, 'threshold' => int]`. `emptyModel()` merges both keys into the `refresh` array as `$memory_refresh['eligible_posts']` and `['initial_post_threshold']`. Task 8 renders them.

**Why there is no second count.** `eligiblePostCounts()` (`:204`) returns `['total', 'after_checkpoint']` and its `total` counts `is_deleted = 0 AND is_pending = 0` — that *is* the eligible count, OP included. The header's number is `threads.reply_count`, recomputed by `RepairService.php:85-88` with the same predicate plus `is_op = 0`. Both already exclude private, hidden and held content, so a "{N} of {M} are eligible" contrast is not derivable and would be false. Do not add one.

- [ ] **Step 1: Write the failing test**

```php
    public function test_eligibility_counts_are_exposed_for_the_empty_state(): void
    {
        $seed = $this->seedThread(4, 'Eligibility counts');
        $counts = $this->eligibility()->draftEligibility($seed['thread_id']);

        self::assertSame(8, $counts['threshold']);
        self::assertGreaterThan(0, $counts['eligible']);
        self::assertLessThan($counts['threshold'], $counts['eligible']);
    }
```

`seedThread(4, …)` seeds **4 posts including the OP** (`:284-296` starts `$postIds` with the OP
then loops `for ($i = 1; $i < $postCount; $i++)`), so `eligible` is 4 and `threads.reply_count` is 3.

There is no `$this->container`. Extract an `eligibility()` private helper the same way Task 2
extracted `queue()`, so both callers share one construction:

```php
    private function eligibility(): ThreadIntelligenceEligibility
    {
        $apiKey = 'sk-test-surface';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );

        return new ThreadIntelligenceEligibility(
            $this->db,
            new FeatureFlags(new SettingRepository($this->db)),
            $config,
            $settings,
            new ThreadIntelligenceBudget($this->db, $config),
            $jobs,
        );
    }
```

Then have `queue()` from Task 2 call `$this->eligibility()` instead of constructing its own.

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_eligibility_counts_are_exposed_for_the_empty_state`
Expected: FAIL — `Call to undefined method … draftEligibility()`.

- [ ] **Step 3: Add the accessor**

In `src/Service/ThreadIntelligence/ThreadIntelligenceEligibility.php`, add a public method that reuses the existing private counter — **do not duplicate the `is_deleted = 0 AND is_pending = 0` predicate**:

```php
    /** @return array{eligible:int,threshold:int} */
    public function draftEligibility(int $threadId): array
    {
        $counts = $this->eligiblePostCounts($threadId, null);

        return [
            'eligible' => (int) ($counts['total'] ?? 0),
            'threshold' => self::INITIAL_POST_THRESHOLD,
        ];
    }
```

The mapping `eligible => $counts['total']` is deliberate: that key counts `is_deleted = 0 AND is_pending = 0`, which is the eligibility predicate. Reuse it — **do not** re-implement the predicate here.

- [ ] **Step 4: Merge the counts into the empty view model**

In `src/Service/ThreadIntelligence/ThreadIntelligenceViewService.php`, inside `emptyModel()` (starts `:118`), merge both counts into the `refresh` array it already returns:

```php
        $counts = $this->eligibility->draftEligibility($threadId);
        // …then, where the refresh array is built:
        //   'eligible_posts' => $counts['eligible'],
        //   'initial_post_threshold' => $counts['threshold'],
```

Read `emptyModel()` in full first and add the two keys to the array it already constructs. `ThreadIntelligenceEligibility` is **already** a constructor dependency (`:28`), reachable as `$this->eligibility` — no container change is needed.

- [ ] **Step 5: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/ tests/Unit/ThreadIntelligence/`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add src/Service/ThreadIntelligence/ tests/
git commit -m "feat(thread-intelligence): expose eligibility counts for the empty brief state"
```

---

### Task 8: Curator empty state with real numbers (§5, fix 2 — surface)

**Files:**
- Create: `templates/partials/living_brief_empty.php`
- Modify: `templates/thread.php:180-198` (new branch)
- Modify: `public/assets/app.css` (append)
- Test: `tests/Integration/ThreadIntelligence/ThreadIntelligenceSurfaceTest.php`

**Interfaces:**
- Consumes: Task 7's `$memory_refresh['eligible_posts']` and `['initial_post_threshold']`; Task 5's version-row markup, reused for Restore.
- Produces: nothing consumed downstream.

- [ ] **Step 1: Write the failing test**

```php
    public function test_empty_state_explains_eligibility_to_curators_only(): void
    {
        $seed = $this->seedThread(4, 'Empty brief eligibility');

        // Guest sees nothing — this preserves the existing no-empty-panel contract.
        $this->logoutClient();
        $guestHtml = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();
        self::assertStringNotContainsString('living-brief', $guestHtml);
        self::assertStringNotContainsString('thread-memory-slot', $guestHtml);

        $admin = $this->makeAdmin(['username' => 'empty-curator']);
        $this->actingAs($admin);
        $curatorHtml = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        self::assertStringContainsString('eight eligible posts', $curatorHtml);
        self::assertStringContainsString('the opening post plus every reply', $curatorHtml);
        self::assertStringNotContainsString('counsels', $curatorHtml);
        self::assertStringNotContainsString('six eligible', $curatorHtml);
        // No invented second number, and no exclusion reason the data cannot support.
        self::assertStringNotContainsString('are eligible; the rest', $curatorHtml);
    }
```

- [ ] **Step 2: Run test to verify it fails**

Run: `vendor/bin/phpunit --filter test_empty_state_explains_eligibility_to_curators_only`
Expected: FAIL — no empty state renders.

- [ ] **Step 3: Create the empty-state partial**

Create `templates/partials/living_brief_empty.php`:

```php
<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php
$threadId = (int) $thread['id'];
$refresh = $memory_refresh ?? [];
$history = $memory_history ?? [];
$threshold = (int) ($refresh['initial_post_threshold'] ?? 8);
$eligible = (int) ($refresh['eligible_posts'] ?? 0);
$thresholdWords = [6 => 'six', 7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten'];
$thresholdLabel = $thresholdWords[$threshold] ?? (string) $threshold;
?>
<section class="living-brief-empty" aria-label="No living brief yet">
    <p class="living-brief-empty-eyebrow">No brief yet</p>
    <p class="living-brief-empty-copy">
        The archive draws a brief once a topic carries <?= $e($thresholdLabel) ?> eligible posts —
        the opening post plus every reply that is public, visible, and approved.
        This one has <?= (int) $eligible ?>.
    </p>
    <?php if (!empty($history)): ?>
        <ul class="lb-versions">
            <?php foreach ($history as $item): ?>
                <li class="lb-version">
                    <span class="lb-version-v">v<?= (int) $item['version'] ?></span>
                    <span class="lb-version-who"><?= $e($item['label']) ?></span>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/restore">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="summary_id" value="<?= (int) $item['id'] ?>">
                        <button class="btn" type="submit">Restore brief</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
```

- [ ] **Step 4: Add the branch in thread.php**

`templates/thread.php:180` currently gates the whole slot on `$living_brief !== null || $related_fallback !== []`. Widen it and add the branch. The condition becomes:

```php
    <?php $canCurateMemory = !empty($can_write) && !empty($can_curate_memory); ?>
    <?php if ($living_brief !== null || $related_fallback !== [] || $canCurateMemory): ?>
```

and inside, after the existing `$living_brief !== null` branch and before the `related_fallback` branch, add:

```php
        <?php elseif ($canCurateMemory): ?>
            <?= $this->partial('partials/living_brief_empty', [
                'thread' => $thread,
                'can_curate_memory' => true,
                'memory_history' => $memory_history,
                'memory_refresh' => $memory_refresh,
            ]) ?>
```

Order matters: the curator empty state must come **before** the `related_fallback` branch only if it should win; if a thread has related-topic fallbacks and no brief, prefer showing the fallback to a member and the empty state to a curator. Put the `$canCurateMemory` branch last so `related_fallback` keeps its current behaviour, and verify against `AppContentReferenceTest`.

- [ ] **Step 5: Add the CSS**

```css
/* Living Brief — curator-only empty state. */
.living-brief-empty {
    display: grid;
    gap: var(--space-2);
    min-width: 0;
    padding: var(--space-4);
    border: 1px dashed var(--border-hair);
    border-radius: var(--radius-lg);
    background: var(--surface-sunken);
    text-align: center;
}
.living-brief-empty-eyebrow {
    margin: 0;
    color: var(--text-muted);
    font-family: var(--font-label);
    font-size: .78rem;
    letter-spacing: var(--tracking-caps);
    text-transform: uppercase;
}
.living-brief-empty-copy {
    margin: 0 auto;
    max-width: 50ch;
    color: var(--text-muted);
    line-height: 1.6;
}
.living-brief-empty .lb-version { justify-content: center; border-bottom: 0; }
```

- [ ] **Step 6: Run the tests**

Run: `vendor/bin/phpunit tests/Integration/`
Expected: PASS, including `ThreadIntelligenceSurfaceTest::test_thread_dom_order_has_one_memory_slot_no_empty_panel_and_public_disclosure` — its empty-thread leg (`:137-140`) is a **guest**, so the curator-only gate keeps it green.

- [ ] **Step 7: Commit**

```bash
git add templates/ public/assets/app.css tests/
git commit -m "feat(living-brief): explain eligibility in the curator empty state"
```

---

### Task 9: Motion and reduced-motion

**Files:**
- Modify: `public/assets/app.css` (append)

**Interfaces:**
- Consumes: `.living-brief` and `.lb-more-body` from earlier tasks.
- Produces: nothing consumed downstream.

- [ ] **Step 1: Check the existing convention**

Run: `grep -n "prefers-reduced-motion" public/assets/app.css | head`
Read one existing block and match its shape.

- [ ] **Step 2: Add the animation**

```css
@keyframes lbFade {
    from { opacity: 0; transform: translateY(5px); }
    to { opacity: 1; transform: none; }
}
.living-brief { animation: lbFade 240ms var(--ease-calm); }
.lb-more[open] > .lb-more-body { animation: lbFade 180ms var(--ease-calm); }

@media (prefers-reduced-motion: reduce) {
    .living-brief,
    .lb-more[open] > .lb-more-body { animation: none; }
}
```

- [ ] **Step 3: Verify no regression**

Run: `vendor/bin/phpunit tests/Integration/ThreadIntelligence/`
Expected: PASS (CSS-only change).

- [ ] **Step 4: Commit**

```bash
git add public/assets/app.css
git commit -m "feat(living-brief): fade the brief and disclosure, honouring reduced motion"
```

---

### Task 10: Full suite, browser evidence, and a11y

**Files:**
- Modify: `tests/browser/thread-intelligence.spec.ts`
- Modify: `docs/evidence/browser/{desktop,mobile}/75-79-*.png` (re-capture)

- [ ] **Step 1: Run the full suite twice**

```bash
RB_TEST_FRESH=1 vendor/bin/phpunit
vendor/bin/phpunit
```
Expected: identical counts, 0 failures. Baseline before this work: 2624 tests / 19055 assertions / 1 skipped.

- [ ] **Step 2: Update the browser spec**

Read `tests/browser/thread-intelligence.spec.ts` in full. Update the DOM selectors that Tasks 1-8 changed, and extend the no-JS test to cover: the More `<details>` opening natively, the Retire confirm working without JS, and Refresh / Amend / Restore / Pause forms all submitting.

Note `tests/browser/thread-intelligence-fixture.php:252` sets the overview's `source_post_ids` to *every* post — **do not "simplify" it to a subset**; that is what keeps the fixture brief renderable (see the spec's §8).

- [ ] **Step 3: Run the browser evidence**

```bash
cd tests/browser && npm install && npx playwright install --with-deps chromium && npm run evidence
```

- [ ] **Step 4: Run axe**

```bash
cd tests/browser && npm run a11y
```
Expected: zero serious/critical. §1 removed the section's only heading, so the `aria-label` swap must be **verified here**, not assumed. `npm run a11y` sets `RB_BROWSER_DARK_SURFACES=1`.

- [ ] **Step 5: Commit**

```bash
git add tests/browser docs/evidence
git commit -m "test(living-brief): re-capture browser evidence for the redesigned brief"
```

---

### Task 11: ADR 0026 and doc updates

**Files:**
- Create: `docs/adr/0026-living-brief-redesign-deferrals.md`
- Modify: `docs/runbooks/thread_intelligence.md`

- [ ] **Step 1: Write the ADR**

Record, per CLAUDE.md's rule that deferrals are never silently dropped:

1. **Deferred §2b (last-good status)** — no last-good state exists in the member view model; building it is new server work.
2. **Deferred §4 (footnote citations)** — blocked on the source-union finding below.
3. **Decision: build the Pause route** rather than drop the control, and audit via `thread_intelligence_jobs.paused_by`/`paused_at` rather than `moderation_log`, matching its five sibling actions.
4. **Decision: the empty state is curator-only** — preserves the guest contract at `ThreadIntelligenceSurfaceTest:137-140` and matches the state's purpose as the route back after Retire.
5. **Finding (not fixed here):** `thread_intelligence_generations.source_post_ids` stores the evidence-pack union while `thread_summary_sources` stores the citation union, and `ThreadIntelligenceViewService::aiSourcesAreCurrent():226-243` compares them for set equality — so an AI brief may render only when the model cites every post in the window. Verified by code reading only. First step of any §4 slice is a worker→view integration test with a subset citation.
6. **Known follow-ups:** `--surface-cool` has no dark override (`imladris.css:117`, consumed by `.living-brief-related-card`); version history is unbounded and runs `lineage()` per row.

- [ ] **Step 2: Update the runbook**

Add the pause route and the curator-footer location to `docs/runbooks/thread_intelligence.md`.

- [ ] **Step 3: Commit**

```bash
git add docs/
git commit -m "docs: record living brief deferrals and the source-union finding in ADR 0026"
```

---

## Self-Review

**Spec coverage:** §1 → Task 1. §2a → Task 2. §3 backend → Task 3; §3 surface → Tasks 4-5. §5 fix 1 → Tasks 4 (gate) + 6 (proof). §5 fix 2 → Tasks 7-8. Motion → Task 9. Testing/evidence (spec §10) → Task 10. Governance (spec §11) → Task 11. Deferred §2b/§4 are recorded, not built — by design.

**Type consistency:** `draftEligibility()` returns `eligible|threshold` (Task 7) and is consumed as `eligible_posts|initial_post_threshold` after `emptyModel()` renames them into `$memory_refresh` (Tasks 7 Step 4, 8 Step 3) — the rename is explicit in both places. `.lb-more-shut`/`.lb-more-open` span classes match between Task 5 Step 3 and Step 4. `living-brief-curator-{threadId}` is the anchor id in Tasks 4 and 5 and the link target in Task 4 Step 6. The test helpers `queue()` (Task 2) and `eligibility()` (Task 7) are defined once each, and Task 7 says explicitly that `queue()` should delegate to `eligibility()`.

**Harness facts verified against the tree, not assumed:** there is no `$this->container` in `tests/Support/TestCase.php`; `logoutClient()` (`:266`) is the guest mechanism; `actingAs()` requires an array; `seedThread($n, …)` seeds `$n` posts *including* the OP; `users.status`/`users.suspended_until` both exist.

**Known risk carried forward:** Task 8 Step 4's branch ordering interacts with `related_fallback`; the step says to verify against `AppContentReferenceTest` rather than assume.
