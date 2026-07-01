# Phase 4 Tags, Feeds, Reputation Graduation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Graduate `tags`, `expanded_feeds`, and `reputation_ledger` from default-dark Phase 4 Gate A features to default-on, reversible runtime features, and import the Imladris design-system bundle as the reference for the activated surfaces.

**Architecture:** Runtime behavior stays behind the existing `FeatureFlags` gates; only defaults and acceptance evidence move. The Imladris zip is imported as a source/reference artifact and documented against the live PHP templates rather than overwriting current app assets.

**Tech Stack:** PHP 8.2+, PHPUnit integration tests, vanilla PHP templates, MySQL/MariaDB, Playwright browser evidence, Markdown docs.

---

### Task 1: Graduation Tests

**Files:**
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php`

- [ ] **Step 1: Write failing tests**

Add assertions that `tags`, `expanded_feeds`, and `reputation_ledger` are enabled by default, while `group_dms`, `badge_rules`, `community_memory`, and `content_references` remain dark. Add default-on/disable tests for each activated feature:

```php
self::assertTrue($flags->enabled('tags'));
self::assertTrue($flags->enabled('expanded_feeds'));
self::assertTrue($flags->enabled('reputation_ledger'));
self::assertFalse($flags->enabled('group_dms'));
self::assertFalse($flags->enabled('badge_rules'));
self::assertFalse($flags->enabled('community_memory'));
self::assertFalse($flags->enabled('content_references'));
```

- [ ] **Step 2: Verify RED**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php
```

Expected: failure because the three graduated flags still default to `false`.

### Task 2: Runtime Defaults

**Files:**
- Modify: `src/Core/FeatureFlags.php`
- Test: `tests/Integration/Core/AppFeatureFlagTest.php`

- [ ] **Step 1: Implement minimal code**

Change only the three defaults and their comments:

```php
'tags' => true,
'expanded_feeds' => true,
'reputation_ledger' => true,
```

- [ ] **Step 2: Verify GREEN**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php
```

Expected: all tests in the file pass.

### Task 3: Design-System Import and Mapping

**Files:**
- Create: `docs/design-system/imladris/` from `imladris-design-system.zip`
- Create/modify: `docs/design-system/imladris/ACTIVATED_FEATURES.md`

- [ ] **Step 1: Import the zip as reference**

Run:

```bash
mkdir -p docs/design-system/imladris
unzip -q -o imladris-design-system.zip -d docs/design-system/imladris
```

- [ ] **Step 2: Document activated feature mapping**

Create `docs/design-system/imladris/ACTIVATED_FEATURES.md` mapping:

```markdown
| Feature flag | Runtime surface | Imladris reference |
|---|---|---|
| `tags` | `/tags`, `/tags/{slug}`, `/admin/tags`, thread tag forms | `ui_kits/reading`, `ui_kits/admin`, `feature-ui/tags` |
| `expanded_feeds` | `/feed?view=latest`, board follows, tag follows | `ui_kits/reading` Feed surfaces |
| `reputation_ledger` | `/leaderboard?window=week|month`, `board_id` filter | `ui_kits/retroboards/Leaderboard.jsx` |
```

### Task 4: Closeout Docs and Runbook

**Files:**
- Modify: `docs/evidence/deploy-dark-features.md`
- Modify: `PHASE_4_STATUS.md`
- Modify: `docs/evidence/phase4-gate-a.md`
- Modify: `README.md`
- Create: `docs/runbooks/phase4-tags-feeds-reputation.md`

- [ ] **Step 1: Update evidence state**

Record the three flags as graduated default-on on 2026-07-01, reversible via the `features` setting, with focused tests, Imladris mapping, and runbook references.

- [ ] **Step 2: Add rollback runbook**

Document disable/re-enable commands for `tags`, `expanded_feeds`, and `reputation_ledger`, expected dark behavior, and verification commands.

### Task 5: Verification

**Files:**
- No source edits unless verification exposes a defect.

- [ ] **Step 1: Focused tests**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppPhase4GateATest.php tests/Integration/Core/AppFollowFeedTest.php tests/Integration/Core/AppLeaderboardTest.php
```

- [ ] **Step 2: Full suite**

Run:

```bash
composer test
```

- [ ] **Step 3: Browser/a11y evidence when time permits**

Run:

```bash
cd tests/browser && npm run evidence
cd tests/browser && npm run a11y
```

Expected: all checks pass, or any failure is documented with the exact blocker.
