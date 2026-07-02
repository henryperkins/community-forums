# WYSIWYG Composer Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the approved WYSIWYG composer direction behind `wysiwyg_composer`, with Markdown as canonical storage and Slack-style `@` and `#` references.

**Architecture:** Keep the server-rendered textarea as the submit source and fallback. Add backend suggestion/reference/mention contracts first, then extract a client-side composer bridge, then add Milkdown as an optional adapter that serializes back to the hidden textarea. The editor bundle is committed static output and is never required for the no-JS or kill-switch paths.

**Tech Stack:** PHP 8, MySQL, League CommonMark, vanilla server-rendered templates, vanilla enhanced composer JS, Milkdown `7.21.2`, Vite `8.1.3`, TypeScript `6.0.3`, PHPUnit, Playwright, axe.

---

## Scope Check

The approved design spans several subsystems: ADR/docs, backend suggestion APIs, mention rendering, content-reference schema, client bridge extraction, WYSIWYG bundling, browser evidence, and operations documentation. Keep this as one dependency-ordered program plan because the design explicitly requires the backend Markdown insertion contract before the editor bundle. Each task below is independently testable and should be committed before moving to the next task.

Do not start Tasks 7-9 before Tasks 1-6 are green. If Milkdown fails the CSP or mobile spike in Task 7, stop and record the fallback decision in the ADR instead of building a bespoke editor.

## File Structure

Create:
- `docs/adr/0013-wysiwyg-composer.md` - supersedes/amends ADR 0001 and records Milkdown-first, committed-bundle, CSP, fallback, and semantic round-trip policy.
- `database/migrations/0071_content_reference_tags.php` - widens `content_references.target_type` to include `tag`.
- `src/Service/ComposerSuggestion.php` - small immutable DTO for suggestion rows.
- `src/Service/ComposerSuggestionService.php` - read-gated, anonymity-safe suggestion service for `@` and `#`.
- `src/Support/MentionLinker.php` - DOM-based mention-link pass scoped to post, DM, and preview rendering.
- `src/client/wysiwyg/milkdown-adapter.ts` - Milkdown adapter and bridge implementation.
- `src/client/wysiwyg/index.ts` - lazy WYSIWYG entry point.
- `src/client/wysiwyg/styles.css` - committed static editor CSS source.
- `vite.config.mjs` - editor bundle build config.
- `package.json` and `package-lock.json` - root development-only editor build dependencies.
- `public/assets/wysiwyg-composer.js` - committed built WYSIWYG bundle.
- `public/assets/wysiwyg-composer.css` - committed built WYSIWYG CSS.
- `tests/Integration/Core/AppComposerSuggestTest.php` - suggestion endpoint, visibility, ranking, and anonymity tests.
- `tests/Integration/Core/AppMentionLinkRenderTest.php` - mention-link render tests.
- `tests/Unit/Composer/ComposerSuggestionServiceTest.php` - service-level ranking and response-shaping tests when no HTTP kernel is needed.
- `tests/browser/wysiwyg-composer.spec.ts` - WYSIWYG, source-mode, picker, paste, mobile, and CSP smoke coverage.
- `docs/runbooks/wysiwyg_composer.md` - rollout and rollback runbook.

Modify:
- `src/Core/FeatureFlags.php` - add `wysiwyg_composer` default false.
- `config/config.php` - add `composer_suggest` rate-limit policy.
- `src/Core/App.php` - bind suggestion service, add route, add script/style gating.
- `src/Controller/ComposerController.php` - add `suggest()` and render preview with mention links.
- `src/Support/Markdown.php` - add render options and scoped mention-link pass.
- `src/Service/PostingService.php` - render post HTML with mention links.
- `src/Service/DirectMessageService.php` - render DM HTML with mention links.
- `src/Service/ContentReferenceService.php` - extract, resolve, and card-render tag references.
- `src/Repository/UserRepository.php` - add active username prefix lookup for suggestions and mention linker.
- `src/Repository/BoardRepository.php` - add prefix lookup for readable board suggestions.
- `src/Repository/TagRepository.php` - add prefix lookup and visible topic count for tag suggestions/cards.
- `src/Repository/PostRepository.php` - add context helpers for non-anonymous participants and post suggestion metadata.
- `src/Repository/ThreadRepository.php` - add visible topic lookup helpers when `SearchService` cannot satisfy short queries.
- `templates/layout.php` - stamp `data-wysiwyg-composer`, load static WYSIWYG CSS and lazy entry only when both flags permit.
- `templates/partials/composer.php`, `templates/partials/new_thread_form.php`, `templates/compose.php`, `templates/dm/new.php`, `templates/dm/show.php`, `templates/partials/post.php` - add data context attributes and normalize composer form classes where needed.
- `public/assets/composer.js` - extract bridge adapters, make shared behavior adapter-based, add textarea pickers, lazy-load WYSIWYG adapter.
- `public/assets/app.css` - static styles for pickers, chips, source toggle, and WYSIWYG surface.
- `SCHEMA.md`, `COMPOSER.md`, `docs/evidence/deploy-dark-features.md` - document schema, composer behavior, rollout state.
- `tests/Integration/Core/AppFeatureFlagTest.php`, `tests/Integration/Core/AppComposerTest.php`, `tests/Integration/Core/AppContentReferenceTest.php`, `tests/Unit/Core/MigrationLedgerTest.php` - expand existing tests.

## Implementation Tasks

### Task 1: ADR 0013 and `wysiwyg_composer` Flag

**Files:**
- Create: `docs/adr/0013-wysiwyg-composer.md`
- Modify: `src/Core/FeatureFlags.php`
- Modify: `tests/Integration/Core/AppFeatureFlagTest.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`
- Modify: `templates/layout.php`

- [ ] **Step 1: Write failing feature-flag tests**

Add these assertions to `tests/Integration/Core/AppFeatureFlagTest.php`:

```php
public function test_wysiwyg_composer_defaults_dark_and_is_independently_reversible(): void
{
    $flags = new FeatureFlags(new SettingRepository($this->db));
    self::assertArrayHasKey('wysiwyg_composer', $flags->all());
    self::assertFalse($flags->enabled('wysiwyg_composer'));
    self::assertTrue($flags->enabled('rich_composer'));

    $this->setFlags(['wysiwyg_composer' => true]);
    $enabled = new FeatureFlags(new SettingRepository($this->db));
    self::assertTrue($enabled->enabled('wysiwyg_composer'));
    self::assertTrue($enabled->enabled('rich_composer'));

    $this->setFlags(['rich_composer' => false, 'wysiwyg_composer' => true]);
    $rolledBack = new FeatureFlags(new SettingRepository($this->db));
    self::assertFalse($rolledBack->enabled('rich_composer'));
    self::assertTrue($rolledBack->enabled('wysiwyg_composer'), 'the narrow flag may be true while the broad kill switch keeps assets dark');
}
```

Add this test to `tests/Integration/Core/AppComposerTest.php`:

```php
public function test_wysiwyg_flag_only_loads_editor_assets_when_rich_composer_is_enabled(): void
{
    $board = $this->makeBoard($this->makeCategory(), ['slug' => 'wysiwyg-assets']);
    $user = $this->makeUser(['username' => 'wysiwygassets']);
    $this->actingAs($user);

    $defaultPage = $this->get('/c/wysiwyg-assets');
    self::assertStringContainsString('/assets/composer.js', $defaultPage->body());
    self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $defaultPage->body());

    (new SettingRepository($this->db))->set('features', ['wysiwyg_composer' => true]);
    $enabledPage = $this->get('/c/wysiwyg-assets');
    self::assertStringContainsString('/assets/composer.js', $enabledPage->body());
    self::assertStringContainsString('/assets/wysiwyg-composer.css', $enabledPage->body());
    self::assertStringContainsString('data-wysiwyg-composer="1"', $enabledPage->body());

    (new SettingRepository($this->db))->set('features', ['rich_composer' => false, 'wysiwyg_composer' => true]);
    $killedPage = $this->get('/c/wysiwyg-assets');
    self::assertStringNotContainsString('/assets/composer.js', $killedPage->body());
    self::assertStringNotContainsString('/assets/wysiwyg-composer.js', $killedPage->body());
    self::assertStringNotContainsString('data-wysiwyg-composer="1"', $killedPage->body());
}
```

- [ ] **Step 2: Run focused tests and verify failure**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter wysiwyg
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter wysiwyg
```

Expected: failures because `wysiwyg_composer` is absent and layout does not stamp/load editor assets.

- [ ] **Step 3: Add the flag and layout gate**

In `src/Core/FeatureFlags.php`, add the flag immediately after `rich_composer`:

```php
'rich_composer' => true,     // shared composer toolbar + server preview (P3-02); textarea always works
'wysiwyg_composer' => false, // Milkdown WYSIWYG layer over canonical Markdown textarea; deploy-dark until evidence lands
'drafts' => true,
```

In `templates/layout.php`, compute and stamp the narrow flag only when the broad kill switch is on:

```php
<?php
$richComposerOn = !empty($features['rich_composer']);
$wysiwygComposerOn = $richComposerOn && !empty($features['wysiwyg_composer']);
?>
```

Change the `<body>` tag to include:

```php
<?= $wysiwygComposerOn ? ' data-wysiwyg-composer="1"' : '' ?>
```

Add the static CSS in `<head>` after `app.css`:

```php
<?php if ($wysiwygComposerOn): ?><link rel="stylesheet" href="/assets/wysiwyg-composer.css"><?php endif; ?>
```

Add the editor bundle after `composer.js`:

```php
<?php if ($richComposerOn): ?><script src="/assets/composer.js" defer></script><?php endif; ?>
<?php if ($wysiwygComposerOn): ?><script src="/assets/wysiwyg-composer.js" defer></script><?php endif; ?>
```

Create temporary asset stubs so the integration test can assert paths before the real build lands. Add `public/assets/wysiwyg-composer.css` with:

```css
/* WYSIWYG composer CSS is built in Task 7. */
```

Add `public/assets/wysiwyg-composer.js` with:

```js
/* WYSIWYG composer bundle is built in Task 7. */
```

- [ ] **Step 4: Draft ADR 0013**

Create `docs/adr/0013-wysiwyg-composer.md`:

```markdown
# ADR 0013 - WYSIWYG composer over canonical Markdown

**Status:** Accepted
**Date:** 2026-07-02
**Supersedes:** ADR 0001 for the enhanced editor surface only. ADR 0001 remains authoritative for the textarea fallback, server preview, and canonical Markdown storage.

## Context

The approved WYSIWYG composer design changes the enhanced editing surface from Markdown-first textarea controls to a true rich editor. Markdown remains the source of truth in `posts.body` and `dm_messages.body`; cached HTML is still produced by the server renderer and sanitizer.

The current renderer supports CommonMark core, strikethrough, autolinks, tables, task lists, and the custom `||spoiler||` extension. The current composer already provides a fully working no-JS textarea path plus toolbar, preview, drafts, uploads, slash inserts, and GIPHY.

## Decision

Adopt Milkdown first for the optional WYSIWYG layer because it is Markdown-native and aligns with ADR 0001's revisit trigger. The enhanced editor mounts only when `rich_composer` and `wysiwyg_composer` are both enabled, the browser supports the adapter, and the form is not opted out.

The underlying `<textarea name="body">` remains in the form and is the only submit source. Milkdown serializes to that textarea after user edits and before submit. Opening an edit form and submitting without user changes must not rewrite `body` solely because the serializer normalized legacy Markdown.

Editor round-trip acceptance is semantic parity against the server-rendered sanitized HTML. Byte-stable Markdown is required only for fixtures intentionally authored in the editor's canonical output form.

All editor JavaScript and CSS are committed static assets served from `/assets`. The root `package-lock.json` pins exact development dependency versions. Deployment serves static files only and does not run npm.

The strict CSP remains `script-src 'self'; style-src 'self'`. Inline scripts, inline styles, runtime `<style>` injection, parser or `setAttribute` `style=""` writes, and constructable/adopted stylesheet rule injection are not allowed by repository policy. CSSOM property writes are acceptable.

## Consequences

- Operators can roll back to the current Markdown-enhanced composer by setting `wysiwyg_composer=false`.
- Operators can disable all enhanced composer JS by setting `rich_composer=false`.
- Suggestion pickers and canonical Markdown insertion are bridge-level behavior shared by textarea and Milkdown adapters.
- Mention links are baked into the cached `body_html` at write time (as a pass that runs *after* sanitisation) and are gated by the `mentions` flag; a later username change or deactivation leaves the previously cached link text/target unchanged, consistent with the rest of the `body_html` cache.
- If Milkdown cannot pass CSP, mobile, accessibility, and semantic round-trip evidence, the implementation stops at the Markdown-enhanced path and revisits CodeMirror/ink-mde rather than shipping a bespoke editor.
```

- [ ] **Step 5: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter wysiwyg
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter wysiwyg
```

Expected: PASS.

Commit:

```bash
git add docs/adr/0013-wysiwyg-composer.md src/Core/FeatureFlags.php templates/layout.php public/assets/wysiwyg-composer.css public/assets/wysiwyg-composer.js tests/Integration/Core/AppFeatureFlagTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "docs: accept wysiwyg composer ADR"
```

### Task 2: Tag Content References

**Files:**
- Create: `database/migrations/0071_content_reference_tags.php`
- Modify: `src/Service/ContentReferenceService.php`
- Modify: `src/Repository/TagRepository.php`
- Modify: `src/Core/App.php`
- Modify: `tests/Integration/Core/AppContentReferenceTest.php`
- Modify: `tests/Unit/Core/MigrationLedgerTest.php`
- Modify: `SCHEMA.md`

- [ ] **Step 1: Write failing tests for tag references**

Add to `tests/Integration/Core/AppContentReferenceTest.php`:

```php
public function test_tag_references_are_persisted_and_rendered_when_flags_allow(): void
{
    $this->makeAdmin();
    $this->setFlags(['content_references' => true, 'tags' => true]);
    $author = $this->makeUser(['username' => 'tagrefauthor']);
    $board = $this->makeBoard($this->makeCategory('Tag References'), ['slug' => 'tag-ref-board']);
    $tagId = (new \App\Repository\TagRepository($this->db))->create('release-notes', 'Release Notes', 'Shipping notes', (int) $author['id']);

    $this->actingAs($author);
    $this->assertRedirect($this->post('/threads', [
        'board_id' => (int) $board['id'],
        'title' => 'Tag source',
        'body' => 'See [#release-notes](/tags/release-notes).',
    ]));
    $threadId = (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Tag source' LIMIT 1");
    $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId]);

    self::assertSame(1, (int) $this->db->fetchValue(
        "SELECT COUNT(*) FROM content_references WHERE source_type = 'post' AND source_id = ? AND target_type = 'tag' AND target_id = ?",
        [$postId, $tagId],
    ));

    $page = $this->get('/t/' . $threadId);
    $this->assertStatus(200, $page);
    self::assertStringContainsString('Release Notes', $page->body());
    self::assertStringContainsString('Shipping notes', $page->body());
}

public function test_tag_reference_cards_stay_dark_when_tags_flag_is_disabled(): void
{
    $this->makeAdmin();
    $this->setFlags(['content_references' => true, 'tags' => false]);
    $author = $this->makeUser(['username' => 'tagrefdark']);
    $board = $this->makeBoard($this->makeCategory('Tag Dark'), ['slug' => 'tag-dark-board']);
    (new \App\Repository\TagRepository($this->db))->create('hidden-card', 'Hidden Card', 'Hidden description', (int) $author['id']);

    $this->actingAs($author);
    $this->assertRedirect($this->post('/threads', [
        'board_id' => (int) $board['id'],
        'title' => 'Tag dark source',
        'body' => 'See [#hidden-card](/tags/hidden-card).',
    ]));

    $page = $this->get('/t/' . (int) $this->db->fetchValue("SELECT id FROM threads WHERE title = 'Tag dark source' LIMIT 1"));
    $this->assertStatus(200, $page);
    self::assertStringNotContainsString('Hidden description', $page->body());
}
```

- [ ] **Step 2: Run failing tests**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppContentReferenceTest.php --filter tag_reference
```

Expected: migration/schema error or zero rows because `target_type='tag'` is not allowed and extraction ignores `/tags/{slug}`.

- [ ] **Step 3: Add migration `0071_content_reference_tags.php`**

Create:

```php
<?php

declare(strict_types=1);

/**
 * 0071 - Allow content reference cards for public tags.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post','tag') NOT NULL
        SQL);
    }

    public function down(\PDO $pdo): void
    {
        $pdo->exec("DELETE FROM content_references WHERE target_type = 'tag'");
        $pdo->exec(<<<'SQL'
            ALTER TABLE content_references
              MODIFY target_type ENUM('board','thread','post') NOT NULL
        SQL);
    }
};
```

- [ ] **Step 4: Add tag repository helpers**

In `src/Repository/TagRepository.php`, add:

```php
/** @return array<string,mixed>|null */
public function visiblePublicBySlug(string $slug): ?array
{
    return $this->db->fetch(
        "SELECT * FROM tags WHERE slug = ? AND is_enabled = 1 AND visibility = 'public'",
        [$slug],
    );
}

public function publicThreadCount(int $tagId): int
{
    return (int) $this->db->fetchValue(
        "SELECT COUNT(*)
         FROM thread_tags tt
         JOIN threads t ON t.id = tt.thread_id AND t.is_deleted = 0 AND t.is_pending = 0
         JOIN boards b ON b.id = t.board_id AND b.tags_enabled = 1 AND b.visibility = 'public'
         WHERE tt.tag_id = ?",
        [$tagId],
    );
}
```

- [ ] **Step 5: Extend `ContentReferenceService`**

Add `TagRepository` to the constructor and binding in `src/Core/App.php`:

```php
private TagRepository $tags,
```

Extend extraction:

```php
if (preg_match_all('~(?:https?://[^\s)\]]+)?/tags/([A-Za-z0-9-]+)~', $body, $matches, PREG_SET_ORDER)) {
    foreach ($matches as $m) {
        $refs[] = ['target_type' => 'tag', 'token' => (string) $m[1]];
    }
}
```

Extend resolve:

```php
'tag' => ($row = $this->tags->visiblePublicBySlug($token)) !== null ? (int) $row['id'] : null,
```

Extend card dispatch:

```php
'tag' => $this->tagCard((int) $row['target_id']),
```

Add:

```php
/** @return array<string,mixed>|null */
private function tagCard(int $tagId): ?array
{
    $tag = $this->tags->find($tagId);
    if ($tag === null || (int) ($tag['is_enabled'] ?? 0) !== 1 || (string) ($tag['visibility'] ?? '') !== 'public') {
        return null;
    }
    $description = trim((string) ($tag['description'] ?? ''));
    $count = $this->tags->publicThreadCount($tagId);
    return [
        'type' => 'Tag',
        'title' => (string) $tag['name'],
        'url' => '/tags/' . (string) $tag['slug'],
        'meta' => $description !== '' ? $description : ($count . ' visible topic' . ($count === 1 ? '' : 's')),
    ];
}
```

In the `ContentReferenceService` container binding, pass `TagRepository`.

- [ ] **Step 6: Gate tag cards by `tags` flag**

In `src/Core/App.php`, only inject `ContentReferenceService` into `PostingService`, `DirectMessageService`, and `CommunityMemoryService` when `content_references` is enabled. Tag card rendering itself must receive `FeatureFlags` or a boolean `tagsEnabled`. Prefer constructor injection:

```php
private bool $tagsEnabled,
```

In `tagCard()`, start with:

```php
if (!$this->tagsEnabled) {
    return null;
}
```

Bind with:

```php
$c->get(FeatureFlags::class)->enabled('tags'),
```

- [ ] **Step 7: Update schema docs and run tests**

Update `SCHEMA.md` content-reference shape from `ENUM('board','thread','post')` to `ENUM('board','thread','post','tag')`, add migration `0071` to the migration ledger section, and add a changelog row:

```markdown
| v1.30 | 2026-07-02 | Added migration `0071_content_reference_tags`: widened `content_references.target_type` with `tag` so composer-inserted `/tags/{slug}` links can resolve to read-gated tag cards while `content_references` and `tags` are enabled. |
```

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppContentReferenceTest.php --filter tag_reference
./vendor/bin/phpunit tests/Unit/Core/MigrationLedgerTest.php
```

Expected: PASS.

Commit:

```bash
git add database/migrations/0071_content_reference_tags.php src/Core/App.php src/Repository/TagRepository.php src/Service/ContentReferenceService.php tests/Integration/Core/AppContentReferenceTest.php tests/Unit/Core/MigrationLedgerTest.php SCHEMA.md
git commit -m "feat: support tag content references"
```

### Task 3: Mention Link Rendering

**Files:**
- Create: `src/Support/MentionLinker.php`
- Modify: `src/Support/Markdown.php`
- Modify: `src/Service/PostingService.php`
- Modify: `src/Service/DirectMessageService.php`
- Modify: `src/Controller/ComposerController.php`
- Modify: `src/Core/App.php`
- Modify: `src/Repository/UserRepository.php`
- Create: `tests/Integration/Core/AppMentionLinkRenderTest.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`

- [ ] **Step 1: Write failing mention-link tests**

Create `tests/Integration/Core/AppMentionLinkRenderTest.php`:

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppMentionLinkRenderTest extends TestCase
{
    public function test_post_render_links_valid_mentions_outside_code_only(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mention-links']);
        $author = $this->makeUser(['username' => 'mentionauthor']);
        $this->makeUser(['username' => 'Alice']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mention links',
            'body' => 'Hello @alice, ignore `@alice` and name@example.com.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('<a href="/u/Alice" class="mention">@alice</a>', $html);
        self::assertStringContainsString('<code>@alice</code>', $html);
        self::assertStringContainsString('name@example.com', $html);
    }

    public function test_unknown_mentions_remain_plain_text(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'unknown-mention']);
        $author = $this->makeUser(['username' => 'unknownauthor']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Unknown mention',
            'body' => 'Hello @nobodyhere.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@nobodyhere', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }

    public function test_dm_and_preview_render_mentions(): void
    {
        $sender = $this->makeUser(['username' => 'dmmentioner']);
        $recipient = $this->makeUser(['username' => 'dmrecipient']);
        $this->actingAs($sender);

        $this->assertRedirect($this->post('/messages', ['to' => 'dmrecipient', 'body' => 'Hi @dmrecipient']));
        $dmHtml = (string) $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $sender['id']]);
        self::assertStringContainsString('<a href="/u/dmrecipient" class="mention">@dmrecipient</a>', $dmHtml);

        $preview = $this->post('/composer/preview', ['body' => 'Preview @dmrecipient']);
        $this->assertStatus(200, $preview);
        // Response::json() uses JSON_UNESCAPED_SLASHES, so slashes stay literal
        // while double quotes are escaped (\"). The anchor therefore appears in
        // the JSON body with plain slashes and escaped quotes.
        self::assertStringContainsString('<a href=\"/u/dmrecipient\" class=\"mention\">@dmrecipient</a>', $preview->body());
    }

    public function test_mentions_are_not_linked_when_mentions_flag_is_disabled(): void
    {
        (new SettingRepository($this->db))->set('features', ['mentions' => false]);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mentions-off']);
        $author = $this->makeUser(['username' => 'mentionsoffauthor']);
        $this->makeUser(['username' => 'Carol']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mentions off',
            'body' => 'Hello @carol.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@carol', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }
}
```

- [ ] **Step 2: Run tests and verify failure**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppMentionLinkRenderTest.php
```

Expected: fails because rendered HTML contains plain `@username`.

- [ ] **Step 3: Add case-insensitive username lookup**

In `src/Repository/UserRepository.php`, add:

```php
/**
 * @param list<string> $usernames
 * @return array<string,array{id:int,username:string}> lower(username) => row
 */
public function activeMentionTargets(array $usernames): array
{
    $usernames = array_values(array_unique(array_filter($usernames, static fn ($u): bool => is_string($u) && $u !== '')));
    if ($usernames === []) {
        return [];
    }
    $place = implode(',', array_fill(0, count($usernames), '?'));
    $rows = $this->db->fetchAll(
        "SELECT id, username FROM users WHERE LOWER(username) IN ($place) AND status = 'active'",
        array_map('strtolower', $usernames),
    );
    $out = [];
    foreach ($rows as $row) {
        $out[strtolower((string) $row['username'])] = ['id' => (int) $row['id'], 'username' => (string) $row['username']];
    }
    return $out;
}
```

- [ ] **Step 4: Add `MentionLinker`**

`MentionLinker` runs as a post-sanitiser pass and must keep its handle grammar
identical to `App\Support\MentionParser` (the `(?<![\w@])@([A-Za-z0-9_]{3,32})\b`
token, plus `code`/`pre`/`a` exclusions) so a name that notifies also links, and
vice versa. If you change one grammar, change both and cover the parity in
`tests/Unit/MentionParserTest.php`.

Create `src/Support/MentionLinker.php`:

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\UserRepository;

final class MentionLinker
{
    // $enabled is bound from FeatureFlags::enabled('mentions') so the @mention
    // surface (notifications *and* rendered links) toggles as a unit. link() is
    // always invoked on already-sanitised HTML (see Markdown::render): the
    // sanitizer strips every <a> attribute except href, so a class="mention"
    // added before sanitisation would not survive.
    public function __construct(private UserRepository $users, private bool $enabled = true)
    {
    }

    public function link(string $html): string
    {
        if (!$this->enabled || $html === '' || !str_contains($html, '@')) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$ok) {
            return $html;
        }

        $handles = [];
        $textNodes = [];
        $walk = function (\DOMNode $node) use (&$walk, &$handles, &$textNodes): void {
            if ($node instanceof \DOMText) {
                if ($this->insideExcludedNode($node)) {
                    return;
                }
                $text = $node->nodeValue ?? '';
                if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{3,32})\b/', $text, $m)) {
                    foreach ($m[1] as $handle) {
                        $handles[] = $handle;
                    }
                    $textNodes[] = $node;
                }
                return;
            }
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walk($child);
            }
        };
        $walk($doc);

        $targets = $this->users->activeMentionTargets($handles);
        if ($targets === []) {
            return $html;
        }

        foreach ($textNodes as $textNode) {
            $this->replaceTextNode($doc, $textNode, $targets);
        }

        $out = $doc->saveHTML();
        if (!is_string($out)) {
            return $html;
        }
        return preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $out) ?? $out;
    }

    private function insideExcludedNode(\DOMNode $node): bool
    {
        $parent = $node->parentNode;
        while ($parent !== null) {
            $name = strtolower($parent->nodeName);
            if ($name === 'code' || $name === 'pre' || $name === 'a') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /** @param array<string,array{id:int,username:string}> $targets */
    private function replaceTextNode(\DOMDocument $doc, \DOMText $textNode, array $targets): void
    {
        $text = $textNode->nodeValue ?? '';
        $parts = preg_split('/((?<![\w@])@[A-Za-z0-9_]{3,32}\b)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) === 1) {
            return;
        }
        $frag = $doc->createDocumentFragment();
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '@') {
                $handle = substr($part, 1);
                $target = $targets[strtolower($handle)] ?? null;
                if ($target !== null) {
                    $a = $doc->createElement('a');
                    $a->setAttribute('href', '/u/' . $target['username']);
                    $a->setAttribute('class', 'mention');
                    $a->appendChild($doc->createTextNode($part));
                    $frag->appendChild($a);
                    continue;
                }
            }
            $frag->appendChild($doc->createTextNode($part));
        }
        $textNode->parentNode?->replaceChild($frag, $textNode);
    }
}
```

- [ ] **Step 5: Scope mention linking through Markdown render options**

Change `src/Support/Markdown.php` constructor and render signature. The mention
pass MUST run **after** `HtmlSanitizer::sanitize()`. The sanitizer removes every
attribute from `<a>` except `href` (and injects `rel="nofollow ugc noopener
noreferrer"`), so a `class="mention"` added *before* sanitisation is silently
stripped — which is why the earlier draft's assertions could never pass.
`MentionLinker` only injects same-origin `/u/{username}` anchors resolved from an
active-user allowlist, so appending them after the allowlist pass is safe and
needs no re-sanitising.

```php
public function __construct(
    private HtmlSanitizer $sanitizer,
    private ?CustomEmojiService $customEmoji = null,
    private ?MentionLinker $mentionLinker = null,
) {
}

/** @param array{link_mentions?:bool} $options */
public function render(string $markdown, array $options = []): string
{
    if (trim($markdown) === '') {
        return '';
    }
    $html = $this->converter->convert($markdown)->getContent();
    $html = $this->renderEmojiShortcodes($html);
    $html = $this->sanitizer->sanitize($html);
    // Post-sanitiser pass: preserves class="mention" (which the sanitizer would
    // otherwise strip) and only ever adds same-origin /u/{username} anchors.
    if (!empty($options['link_mentions'])) {
        $html = $this->mentionLinker?->link($html) ?? $html;
    }
    return $html;
}
```

Update `src/Core/App.php` binding. `MentionLinker` receives the `mentions`
feature-flag state so rendered links follow the same on/off switch as mention
notifications (an operator who disables `mentions` gets neither):

```php
$c->bind(MentionLinker::class, fn (Container $c) => new MentionLinker(
    $c->get(UserRepository::class),
    $c->get(FeatureFlags::class)->enabled('mentions'),
));
$c->bind(Markdown::class, fn (Container $c) => new Markdown(
    $c->get(HtmlSanitizer::class),
    $c->get(FeatureFlags::class)->enabled('custom_emoji') ? $c->get(CustomEmojiService::class) : null,
    $c->get(MentionLinker::class),
));
```

- [ ] **Step 6: Render posts, DMs, and preview with mention links**

In `PostingService`, replace each write-time render call:

```php
'body_html' => $this->markdown->render($body, ['link_mentions' => true]),
```

and:

```php
$this->posts->update($postId, $body, $this->markdown->render($body, ['link_mentions' => true]), $user->id());
```

In `DirectMessageService::deliver()`:

```php
$messageId = $this->messages->create($conversationId, $sender->id(), $body, $this->markdown->render($body, ['link_mentions' => true]));
```

In `ComposerController::preview()`:

```php
$html = $this->container->get(Markdown::class)->render($body, ['link_mentions' => true]);
```

Do not change profile bios or other shared `Markdown::render()` callers.

- [ ] **Step 7: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppMentionLinkRenderTest.php
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php --filter render
./vendor/bin/phpunit tests/Unit/MentionParserTest.php
```

Expected: PASS.

Commit:

```bash
git add src/Core/App.php src/Controller/ComposerController.php src/Repository/UserRepository.php src/Service/DirectMessageService.php src/Service/PostingService.php src/Support/Markdown.php src/Support/MentionLinker.php tests/Integration/Core/AppMentionLinkRenderTest.php tests/Integration/Core/AppComposerTest.php
git commit -m "feat: link mentions in rendered composer output"
```

### Task 4: Suggestion API and Service

**Files:**
- Create: `src/Service/ComposerSuggestion.php`
- Create: `src/Service/ComposerSuggestionService.php`
- Create: `tests/Integration/Core/AppComposerSuggestTest.php`
- Create or modify: `tests/Unit/Composer/ComposerSuggestionServiceTest.php`
- Modify: `src/Controller/ComposerController.php`
- Modify: `src/Core/App.php`
- Modify: `config/config.php`
- Modify: `src/Repository/UserRepository.php`
- Modify: `src/Repository/BoardRepository.php`
- Modify: `src/Repository/TagRepository.php`
- Modify: `src/Repository/PostRepository.php`
- Modify: `src/Repository/ThreadRepository.php`

- [ ] **Step 1: Write failing endpoint tests**

Create `tests/Integration/Core/AppComposerSuggestTest.php` with these core cases.

> **Harness note:** `ComposerSuggestionService` reuses `MysqlSearchService` for
> topic/post (`#`) results, and InnoDB FULLTEXT does **not** index rows written
> inside an open transaction. So — exactly like `AppSearchTest` — this suite
> commits its fixtures (it skips the rolling-back `parent::setUp()`) and
> truncates everything in `tearDown`. Prefix-based `@`/board/tag lookups would
> pass either way; the committed harness is what lets the topic assertions see
> freshly-seeded threads. This means these tests are **not** isolated by
> transaction rollback — clean up via the shared `resetDatabase()`.

```php
<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Security\ArrayRateLimiter;
use PDO;
use Tests\Support\TestCase;

final class AppComposerSuggestTest extends TestCase
{
    protected function setUp(): void
    {
        // Deliberately NOT calling parent::setUp(): fixtures must be committed so
        // the FULLTEXT index (used for '#' topic/post suggestions) sees them.
        $this->pdo = $GLOBALS['__RB_TEST_PDO'];
        $this->config = $GLOBALS['__RB_TEST_CONFIG'];
        $this->db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->setPdo($this->pdo);
        $this->resetDatabase();
        $this->rateLimiter = new ArrayRateLimiter();
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
        $this->cookies = [];
        $this->csrfSecret = null;
    }

    protected function tearDown(): void
    {
        $this->resetDatabase();
    }

    private function resetDatabase(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        // Preserve migration-seeded reference tables (see AppSearchTest): TRUNCATE
        // auto-commits, so wiping these would leak empty seeds into later tests.
        $preserve = [
            'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
            'capabilities', 'role_capabilities',
        ];
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $t) {
            if (!in_array($t, $preserve, true)) {
                $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $t) . '`');
            }
        }
        $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function enableSuggestions(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'rich_composer' => true,
            'tags' => true,
            'content_references' => true,
        ]);
    }

    public function test_suggest_requires_auth_and_rich_composer(): void
    {
        // Guests hit requireUser() first, which redirects (302) to /login — it
        // does not 403 (mirrors how /composer/preview treats an anonymous caller).
        $this->assertRedirectContains($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']), '/login');
        $user = $this->makeUser(['username' => 'suggestauth']);
        $this->actingAs($user);
        (new SettingRepository($this->db))->set('features', ['rich_composer' => false]);
        $this->assertStatus(404, $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'a']));
    }

    public function test_user_suggestions_return_mention_markdown(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'suggestviewer']);
        $this->makeUser(['username' => 'alice', 'display_name' => 'Alice Example']);
        $this->actingAs($viewer);

        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'ali']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('user', $json['items'][0]['type']);
        self::assertSame('@alice', $json['items'][0]['token']);
        self::assertSame('@alice', $json['items'][0]['markdown']);
        self::assertSame('/u/alice', $json['items'][0]['url']);
    }

    public function test_hash_suggestions_are_read_gated_and_grouped(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'hashviewer']);
        $author = $this->makeUser(['username' => 'hashauthor']);
        $cat = $this->makeCategory('Hash Suggest');
        $public = $this->makeBoard($cat, ['slug' => 'general-suggest', 'name' => 'General Suggest']);
        $private = $this->makeBoard($cat, ['slug' => 'private-suggest', 'name' => 'Private Suggest', 'visibility' => 'private']);
        $thread = $this->makeThread($public, $author, 'Release planning topic', 'Planning body for release notes');
        (new TagRepository($this->db))->create('release-notes', 'Release Notes', 'Shipping notes', (int) $author['id']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'release']);
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        $markdown = array_column($json['items'], 'markdown');
        self::assertContains('[#release-notes](/tags/release-notes)', $markdown);
        self::assertContains('[Release planning topic](/t/' . $thread['thread_id'] . '-' . $thread['slug'] . ')', $markdown);
        self::assertNotContains('[#private-suggest](/c/private-suggest)', $markdown);

        (new BoardMemberRepository($this->db))->add((int) $private['id'], (int) $viewer['id'], null);
        $memberRes = $this->get('/composer/suggest', ['trigger' => '#', 'q' => 'private']);
        $this->assertStatus(200, $memberRes);
        self::assertStringContainsString('[#private-suggest](/c/private-suggest)', $memberRes->body());
    }

    public function test_forged_unreadable_target_id_matches_context_free_results(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'contextviewer']);
        $other = $this->makeUser(['username' => 'contextother']);
        $cat = $this->makeCategory('Context');
        $private = $this->makeBoard($cat, ['slug' => 'context-private', 'visibility' => 'private']);
        $thread = $this->makeThread($private, $other, 'Hidden context topic', 'hidden');
        $this->actingAs($viewer);

        $plain = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context']);
        $forged = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'context', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        self::assertSame($plain->body(), $forged->body());
    }

    public function test_anonymous_participation_does_not_boost_user_suggestion_rank(): void
    {
        $this->enableSuggestions();
        $viewer = $this->makeUser(['username' => 'anonrankviewer']);
        $anon = $this->makeUser(['username' => 'anonrankalice']);
        $normal = $this->makeUser(['username' => 'anonrankbob']);
        $board = $this->makeBoard($this->makeCategory('Anon Rank'), ['slug' => 'anon-rank', 'allow_anonymous' => 1]);
        $thread = $this->makeThread($board, $viewer, 'Anon ranking', 'opening');
        $this->actingAs($anon);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'secret', 'is_anonymous' => '1']);
        $this->actingAs($normal);
        $this->post('/t/' . $thread['thread_id'] . '/reply', ['body' => 'visible']);

        $this->actingAs($viewer);
        $res = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'anonrank', 'context' => 'thread', 'target_id' => (string) $thread['thread_id']]);
        $json = json_decode($res->body(), true);
        $tokens = array_column($json['items'], 'token');
        self::assertLessThan(array_search('@anonrankalice', $tokens, true), array_search('@anonrankbob', $tokens, true));
    }
}
```

- [ ] **Step 2: Run failing tests**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerSuggestTest.php
```

Expected: 404 for missing route.

- [ ] **Step 3: Add rate-limit policy**

In `config/config.php`, add:

```php
'composer_suggest' => [120, 60],
```

near `composer_preview`.

- [ ] **Step 4: Add DTO**

Create `src/Service/ComposerSuggestion.php`:

```php
<?php

declare(strict_types=1);

namespace App\Service;

final class ComposerSuggestion
{
    public function __construct(
        public readonly string $type,
        public readonly int $id,
        public readonly string $label,
        public readonly string $token,
        public readonly string $url,
        public readonly string $markdown,
        public readonly string $meta = '',
        public readonly string $group = '',
        public readonly int $rank = 0,
    ) {
    }

    /** @return array{type:string,id:int,label:string,token:string,url:string,markdown:string,meta:string,group:string} */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'label' => $this->label,
            'token' => $this->token,
            'url' => $this->url,
            'markdown' => $this->markdown,
            'meta' => $this->meta,
            'group' => $this->group,
        ];
    }
}
```

- [ ] **Step 5: Add repository helpers**

Add focused query helpers:

```php
// UserRepository
/** @return array<int,array{id:int,username:string,display_name:?string,role:string,status:string}> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT id, username, display_name, role, status
         FROM users
         WHERE status = 'active' AND (username LIKE ? OR display_name LIKE ?)
         ORDER BY username ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// BoardRepository
/** @return array<int,array<string,mixed>> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT * FROM boards
         WHERE is_archived = 0 AND (slug LIKE ? OR name LIKE ?)
         ORDER BY slug ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// TagRepository
/** @return array<int,array<string,mixed>> */
public function suggestByPrefix(string $query, int $limit): array
{
    return $this->db->fetchAll(
        "SELECT * FROM tags
         WHERE is_enabled = 1 AND visibility = 'public' AND (slug LIKE ? OR name LIKE ?)
         ORDER BY slug ASC
         LIMIT " . max(1, min(25, $limit)),
        [$query . '%', $query . '%'],
    );
}
```

```php
// PostRepository
/** @return array<int,int> user_id => rank boost */
public function nonAnonymousParticipantRanks(int $threadId): array
{
    $rows = $this->db->fetchAll(
        'SELECT user_id, MIN(created_at) AS first_at
         FROM posts
         WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 AND is_anonymous = 0
         GROUP BY user_id
         ORDER BY first_at ASC',
        [$threadId],
    );
    $rank = [];
    $boost = 300;
    foreach ($rows as $row) {
        $rank[(int) $row['user_id']] = $boost;
        $boost = max(100, $boost - 10);
    }
    return $rank;
}
```

- [ ] **Step 6: Add `ComposerSuggestionService`**

Create `src/Service/ComposerSuggestionService.php` with public API:

```php
/**
 * @return list<ComposerSuggestion>
 */
public function suggest(string $trigger, string $query, string $context, int $targetId, User $viewer): array
```

Implementation rules:
- Trim `q`, strip leading trigger, cap to 80 chars.
- For `@`, return users only with `markdown='@username'`.
- For `#`, return boards, tags, threads, and posts with canonical Markdown links.
- Context boosts apply only after `readableContext()` returns true.
- Participant boosts use `PostRepository::nonAnonymousParticipantRanks()`.
- Board suggestions filter each row through `BoardPolicy::canRead($board, $viewer, $members->isMember(...))`.
- Tags require `FeatureFlags::enabled('tags')`.
- Topic/post results reuse `SearchService::search()` when `mb_strlen($query) >= 3`; below 3 chars, return only board/tag prefix matches.
- Post suggestion metadata uses masked author text: `Anonymous` when `is_anonymous=1`, otherwise display name or username.

Use this result shape for items:

```php
new ComposerSuggestion(
    type: 'board',
    id: (int) $board['id'],
    label: '#' . (string) $board['slug'],
    token: '#' . (string) $board['slug'],
    url: '/c/' . (string) $board['slug'],
    markdown: '[#' . (string) $board['slug'] . '](/c/' . (string) $board['slug'] . ')',
    meta: (string) $board['name'],
    group: 'Boards',
    rank: 200,
);
```

- [ ] **Step 7: Add controller route**

In `src/Controller/ComposerController.php`, add:

```php
public function suggest(Request $request): Response
{
    $user = $this->requireUser();
    $flags = $this->container->get(FeatureFlags::class);
    if (!$flags->enabled('rich_composer')) {
        throw new \App\Core\NotFoundException('Not found.');
    }
    $this->container->get(RateLimitService::class)->enforce('composer_suggest', $request, $user);

    $trigger = (string) $request->query('trigger', '');
    $q = (string) $request->query('q', '');
    $context = (string) $request->query('context', '');
    $targetId = (int) $request->query('target_id', 0);

    if (!in_array($trigger, ['@', '#'], true)) {
        return Response::json(['ok' => false, 'error' => 'Unsupported trigger.'], 422);
    }

    $items = $this->container->get(ComposerSuggestionService::class)
        ->suggest($trigger, $q, $context, $targetId, $user);

    return Response::json([
        'ok' => true,
        'items' => array_map(static fn (ComposerSuggestion $item): array => $item->toArray(), $items),
    ]);
}
```

In `src/Core/App.php`, import/bind `ComposerSuggestionService` and add route:

```php
$r->get('/composer/suggest', [ComposerController::class, 'suggest']);
```

- [ ] **Step 8: Run tests and commit**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerSuggestTest.php
./vendor/bin/phpunit tests/Integration/Core/AppFeatureFlagTest.php --filter rich
```

Expected: PASS.

Commit:

```bash
git add config/config.php src/Core/App.php src/Controller/ComposerController.php src/Repository/BoardRepository.php src/Repository/PostRepository.php src/Repository/TagRepository.php src/Repository/ThreadRepository.php src/Repository/UserRepository.php src/Service/ComposerSuggestion.php src/Service/ComposerSuggestionService.php tests/Integration/Core/AppComposerSuggestTest.php tests/Unit/Composer/ComposerSuggestionServiceTest.php
git commit -m "feat: add composer suggestion API"
```

### Task 5: Composer Bridge Extraction on Textarea Adapter

**Files:**
- Modify: `public/assets/composer.js`
- Modify: `templates/partials/composer.php`
- Modify: `templates/partials/new_thread_form.php`
- Modify: `templates/compose.php`
- Modify: `templates/dm/new.php`
- Modify: `templates/dm/show.php`
- Modify: `templates/partials/post.php`
- Modify: `tests/Integration/Core/AppComposerTest.php`
- Modify: `tests/browser/server-drafts.spec.ts`
- Modify: `tests/browser/gate-a.spec.ts`

- [ ] **Step 1: Write failing integration test for form context attributes**

Add to `AppComposerTest`:

```php
public function test_composer_forms_expose_bridge_context_metadata(): void
{
    $board = $this->makeBoard($this->makeCategory(), ['slug' => 'bridge-meta']);
    $author = $this->makeUser(['username' => 'bridgemeta']);
    $recipient = $this->makeUser(['username' => 'bridgedm']);
    $thread = $this->makeThread($board, $author, 'Bridge meta', 'Opening');
    $this->actingAs($author);

    $boardPage = $this->get('/c/bridge-meta');
    self::assertStringContainsString('data-composer-context="new_thread"', $boardPage->body());
    self::assertStringContainsString('data-composer-target-id="' . (int) $board['id'] . '"', $boardPage->body());

    $threadPage = $this->get('/t/' . $thread['thread_id']);
    self::assertStringContainsString('data-composer-context="reply"', $threadPage->body());
    self::assertStringContainsString('data-composer-target-id="' . $thread['thread_id'] . '"', $threadPage->body());
    self::assertStringContainsString('data-composer-context="edit"', $threadPage->body());

    $newDm = $this->get('/messages/new');
    self::assertStringContainsString('data-composer-context="dm"', $newDm->body());
}
```

- [ ] **Step 2: Add template metadata**

For each composer form, add these data attributes:

```php
data-composer-context="reply" data-composer-target-id="<?= (int) $thread['id'] ?>"
```

Use:
- New thread forms: `context="new_thread"`, `target_id=board.id`.
- Reply forms: `context="reply"`, `target_id=thread.id`.
- DM new and reply: `context="dm"`, `target_id=conversation_id` for existing conversations and `0` for new DM.
- Post edit: `context="edit"`, `target_id=post.id`.

Normalize DM forms so shared JS finds them:

```php
class="dm-composer composer"
```

Keep `data-no-draft` on inline edit forms.

- [ ] **Step 3: Extract adapter in `composer.js`**

Add near the top of `public/assets/composer.js`:

```js
function TextareaComposerAdapter(form, ta) {
    this.form = form;
    this.ta = ta;
    this.changeHandlers = [];
    var self = this;
    ta.addEventListener('input', function () {
        self.changeHandlers.forEach(function (cb) { cb(self.getMarkdown()); });
    });
}
TextareaComposerAdapter.prototype.getMarkdown = function () { return this.ta.value; };
TextareaComposerAdapter.prototype.setMarkdown = function (markdown) {
    this.ta.value = markdown || '';
    this.ta.dispatchEvent(new Event('input', { bubbles: true }));
};
TextareaComposerAdapter.prototype.insertMarkdown = function (markdown) {
    this.replaceSelection(markdown);
};
TextareaComposerAdapter.prototype.replaceSelection = function (markdown) {
    var ta = this.ta;
    var s = ta.selectionStart || 0;
    var e = ta.selectionEnd || s;
    ta.value = ta.value.slice(0, s) + markdown + ta.value.slice(e);
    ta.selectionStart = ta.selectionEnd = s + markdown.length;
    ta.focus();
    ta.dispatchEvent(new Event('input', { bubbles: true }));
};
TextareaComposerAdapter.prototype.replacePendingUpload = function (token, markdown) {
    return replaceOnce(this.ta, token, markdown);
};
TextareaComposerAdapter.prototype.focus = function () { this.ta.focus(); };
TextareaComposerAdapter.prototype.onChange = function (callback) { this.changeHandlers.push(callback); };
TextareaComposerAdapter.prototype.setDisabled = function (disabled) { this.ta.disabled = !!disabled; };
TextareaComposerAdapter.prototype.destroy = function () {};
```

Then refactor shared functions one at a time:
- `buildPreview(form, ta)` becomes `buildPreview(form, adapter)` and reads `adapter.getMarkdown()`.
- `wireDrafts(form, ta)` becomes `wireDrafts(form, adapter)` and calls `adapter.getMarkdown()` / `adapter.setMarkdown()`.
- Upload completion calls `adapter.replacePendingUpload(placeholder, markdown)`.
- Slash insertion calls `adapter.replaceSelection()` or a textarea-only range helper exposed by the textarea adapter.

Keep the textarea object available as `adapter.ta` for functions that still need selection state until Task 6 removes those direct reads.

- [ ] **Step 4: Keep behavior green on textarea path**

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php
cd tests/browser && npx playwright test server-drafts.spec.ts -g "server drafts expose conflict"
cd tests/browser && npx playwright test gate-a.spec.ts -g "phase 4 slash"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js templates/partials/composer.php templates/partials/new_thread_form.php templates/compose.php templates/dm/new.php templates/dm/show.php templates/partials/post.php tests/Integration/Core/AppComposerTest.php tests/browser/server-drafts.spec.ts tests/browser/gate-a.spec.ts
git commit -m "refactor: introduce composer bridge"
```

### Task 6: `@` and `#` Pickers on the Textarea Adapter

**Files:**
- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `tests/browser/gate-a.spec.ts`
- Create or modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add browser tests for textarea picker flow**

Add Playwright tests with `wysiwyg_composer=false` and `rich_composer=true`:

```ts
test('textarea composer inserts @ mention from keyboard picker', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('@ali');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await expect(body).toHaveAttribute('aria-expanded', 'true');
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('@alice');
});

test('textarea # picker inserts board reference and does not steal headings', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const body = page.locator('form.composer textarea.composer-input').first();
  await body.fill('# ');
  await expect(page.locator('.composer-reference-menu')).toHaveCount(0);
  await body.fill('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(body).toHaveValue('[#general](/c/general)');
});
```

- [ ] **Step 2: Add picker CSS**

In `public/assets/app.css`, add static styles:

```css
.composer-reference-menu {
  border: 1px solid var(--border);
  background: var(--surface-raised);
  box-shadow: var(--shadow-pop);
  border-radius: 8px;
  margin-top: 6px;
  max-height: 280px;
  overflow: auto;
  padding: 4px;
}
.composer-reference-menu[hidden] { display: none; }
.composer-reference-option {
  width: 100%;
  display: grid;
  grid-template-columns: auto 1fr;
  gap: 4px 8px;
  border: 0;
  background: transparent;
  color: var(--text);
  text-align: left;
  padding: 7px 9px;
  border-radius: 6px;
}
.composer-reference-option[aria-selected="true"],
.composer-reference-option:hover {
  background: var(--surface-3);
}
.composer-reference-option .badge { grid-row: span 2; align-self: center; }
.composer-reference-meta { color: var(--text-muted); font-size: .86rem; }
@media (max-width: 640px) {
  .composer-reference-menu {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 40;
    max-height: 50vh;
    border-radius: 8px 8px 0 0;
  }
}
```

- [ ] **Step 3: Add bridge-level picker code**

In `public/assets/composer.js`, add `wireReferencePickers(form, adapter)` after slash menu code. Core rules:

```js
function referenceState(ta) {
    if (ta.selectionStart !== ta.selectionEnd) { return null; }
    var pos = ta.selectionStart;
    var before = ta.value.slice(0, pos);
    var m = before.match(/(^|[\s(])([@#])([A-Za-z0-9_-]{1,80})$/);
    if (!m) { return null; }
    var trigger = m[2];
    var query = m[3];
    if (trigger === '#' && /^\s*#{1,3}\s?$/.test(before.slice(before.lastIndexOf('\n') + 1))) {
        return null;
    }
    if (inFence(ta)) {
        return null;
    }
    return { trigger: trigger, query: query, start: pos - trigger.length - query.length, end: pos };
}
```

Use `fetch('/composer/suggest?trigger=' + encodeURIComponent(state.trigger) + '&q=' + encodeURIComponent(state.query) + '&context=' + encodeURIComponent(form.getAttribute('data-composer-context') || '') + '&target_id=' + encodeURIComponent(form.getAttribute('data-composer-target-id') || '0'))`.

Render `button.composer-reference-option[role=option]` rows with:
- badge text from `item.type`
- main label from `item.label`
- meta from `item.meta`

Keyboard contract:
- ArrowDown/ArrowUp cycles.
- Enter and Tab select active item.
- Escape closes.
- Click selects item.

Selection on textarea adapter:

```js
replaceRange(adapter.ta, state.start, state.end, item.markdown);
```

Combobox attributes must mirror slash menu:

```js
ta.setAttribute('role', 'combobox');
ta.setAttribute('aria-controls', menuId);
ta.setAttribute('aria-haspopup', 'listbox');
ta.setAttribute('aria-autocomplete', 'list');
```

Call `wireReferencePickers(form, adapter)` in `enhance()` after `wireSlashMenu()` and before `wireKeys()`.

- [ ] **Step 4: Run browser tests and commit**

Run:

```bash
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "textarea"
cd tests/browser && npx playwright test a11y.spec.ts -g "slash combobox"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js public/assets/app.css tests/browser/wysiwyg-composer.spec.ts tests/browser/gate-a.spec.ts
git commit -m "feat: add composer reference pickers"
```

### Task 7: Milkdown Build Pipeline and CSP Spike

**Files:**
- Create: `package.json`
- Create: `package-lock.json`
- Create: `vite.config.mjs`
- Create: `src/client/wysiwyg/index.ts`
- Create: `src/client/wysiwyg/milkdown-adapter.ts`
- Create: `src/client/wysiwyg/styles.css`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `public/assets/wysiwyg-composer.css`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `docs/adr/0013-wysiwyg-composer.md`

- [ ] **Step 1: Add package metadata with pinned versions**

Create root `package.json`:

```json
{
  "private": true,
  "scripts": {
    "build:wysiwyg": "vite build --config vite.config.mjs",
    "check:wysiwyg": "npm run build:wysiwyg && git diff --exit-code -- public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css"
  },
  "dependencies": {
    "@milkdown/core": "7.21.2",
    "@milkdown/plugin-history": "7.21.2",
    "@milkdown/plugin-listener": "7.21.2",
    "@milkdown/preset-commonmark": "7.21.2",
    "@milkdown/preset-gfm": "7.21.2",
    "@milkdown/prose": "7.21.2"
  },
  "devDependencies": {
    "typescript": "6.0.3",
    "vite": "8.1.3"
  }
}
```

Run:

```bash
npm install --package-lock-only
```

Expected: `package-lock.json` is created and contains the exact versions above.

- [ ] **Step 2: Add Vite static build config**

Create `vite.config.mjs`:

```js
import { defineConfig } from 'vite';

export default defineConfig({
  build: {
    emptyOutDir: false,
    outDir: 'public/assets',
    assetsDir: '.',
    rollupOptions: {
      input: 'src/client/wysiwyg/index.ts',
      output: {
        entryFileNames: 'wysiwyg-composer.js',
        assetFileNames: (assetInfo) => {
          return assetInfo.name && assetInfo.name.endsWith('.css') ? 'wysiwyg-composer.css' : '[name][extname]';
        },
      },
    },
  },
});
```

- [ ] **Step 3: Add minimal adapter entry without runtime style injection**

Create `src/client/wysiwyg/styles.css` with static classes only:

```css
.wysiwyg-composer {
  border: 1px solid var(--border);
  background: var(--surface-raised);
  border-radius: 8px;
  min-height: 9rem;
}
.wysiwyg-composer .ProseMirror {
  min-height: 9rem;
  padding: 10px 12px;
  outline: none;
}
.wysiwyg-source-toggle {
  margin-top: 8px;
}
.composer-input.is-wysiwyg-source-hidden {
  position: absolute;
  width: 1px;
  height: 1px;
  overflow: hidden;
  clip: rect(0 0 0 0);
  clip-path: inset(50%);
  white-space: nowrap;
}
```

Create `src/client/wysiwyg/index.ts`:

```ts
import './styles.css';

const w = window as unknown as {
  RetroBoardsComposer?: {
    registerWysiwygAdapter(factory: unknown): void;
  };
};

if (document.body.getAttribute('data-wysiwyg-composer') === '1' && w.RetroBoardsComposer) {
  import('./milkdown-adapter').then((module) => {
    w.RetroBoardsComposer?.registerWysiwygAdapter(module.createMilkdownComposerAdapter);
  }).catch(() => {
    // Textarea adapter remains active.
  });
}
```

Create `src/client/wysiwyg/milkdown-adapter.ts` with a stub factory first:

```ts
export function createMilkdownComposerAdapter(): null {
  return null;
}
```

- [ ] **Step 4: Build committed assets**

Run:

```bash
npm run build:wysiwyg
```

Expected: `public/assets/wysiwyg-composer.js` and `public/assets/wysiwyg-composer.css` are generated with no inline `<style>` runtime in the source code.

- [ ] **Step 5: Add CSP browser smoke**

In `tests/browser/wysiwyg-composer.spec.ts`, add:

```ts
test('wysiwyg assets load under strict CSP without violations', async ({ page }) => {
  const violations: string[] = [];
  page.on('console', (msg) => {
    const text = msg.text();
    if (/Content Security Policy|Refused to apply inline style|Refused to execute inline script/i.test(text)) {
      violations.push(text);
    }
  });
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  await expect(page.locator('form.composer textarea.composer-input').first()).toBeVisible();
  expect(violations).toEqual([]);
});
```

- [ ] **Step 6: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "strict CSP"
```

Expected: PASS.

Commit:

```bash
git add package.json package-lock.json vite.config.mjs src/client/wysiwyg public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/wysiwyg-composer.spec.ts docs/adr/0013-wysiwyg-composer.md
git commit -m "build: add wysiwyg composer asset pipeline"
```

### Task 8: Milkdown Adapter, Source Mode, and Round Trip

**Files:**
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `public/assets/composer.js`
- Modify: `public/assets/app.css`
- Modify: `tests/Unit/Composer/MarkdownRoundTripTest.php`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add round-trip fixture tests**

Extend `tests/Unit/Composer/MarkdownRoundTripTest.php` with semantic parity cases:

```php
public function test_supported_markdown_fixtures_render_semantically_after_editor_round_trip(): void
{
    $markdown = implode("\n\n", [
        '## Heading',
        '**bold** *italic* ~~strike~~ `code`',
        "> quote",
        "- [x] task\n- item",
        "| A | B |\n| - | - |\n| 1 | 2 |",
        "||spoiler||",
        "@alice",
        "[#general](/c/general)",
    ]);

    $html = (new Markdown(new HtmlSanitizer()))->render($markdown);
    self::assertStringContainsString('<table>', $html);
    self::assertStringContainsString('class="spoiler"', $html);
}
```

Client-side parse/serialize parity is covered by Playwright after the adapter lands.

- [ ] **Step 2: Expose composer registration seam**

In `public/assets/composer.js`, expose a small global after `TextareaComposerAdapter` is defined:

```js
var wysiwygFactory = null;
window.RetroBoardsComposer = {
    registerWysiwygAdapter: function (factory) {
        wysiwygFactory = factory;
        document.querySelectorAll('form.composer').forEach(function (form) {
            if (form._rbComposerEnhance) { form._rbComposerEnhance(); }
        });
    }
};
```

In `enhance(form, prefs)`, choose adapter:

```js
var adapter = new TextareaComposerAdapter(form, ta);
if (document.body.getAttribute('data-wysiwyg-composer') === '1' && wysiwygFactory && !form.hasAttribute('data-no-wysiwyg')) {
    var rich = wysiwygFactory(form, ta, adapter);
    if (rich) { adapter = rich; }
}
form._rbComposerAdapter = adapter;
```

Ensure submit forces sync:

```js
form.addEventListener('submit', function () {
    if (adapter && typeof adapter.getMarkdown === 'function') {
        ta.value = adapter.getMarkdown();
    }
});
```

- [ ] **Step 3: Implement Milkdown adapter**

In `src/client/wysiwyg/milkdown-adapter.ts`, use Milkdown CommonMark/GFM presets, listener, and history. The adapter must:
- Mount a `.wysiwyg-composer` element before the textarea.
- Hide the textarea visually with `is-wysiwyg-source-hidden`.
- Add a Source toggle button.
- Keep initial Markdown untouched until a rich edit occurs.
- On rich edits, serialize Markdown into the textarea and dispatch `input`.
- On source toggle back to rich mode, parse textarea Markdown into Milkdown.
- Return `null` on mount failure.

Keep the factory shape:

```ts
export function createMilkdownComposerAdapter(form: HTMLFormElement, textarea: HTMLTextAreaElement, fallback: any) {
  try {
    return new MilkdownComposerAdapter(form, textarea, fallback);
  } catch {
    return null;
  }
}
```

Do not import a Milkdown theme that injects styles. All styles remain in `src/client/wysiwyg/styles.css`.

- [ ] **Step 4: Add Playwright coverage**

Add tests:
- `new topic WYSIWYG compose and submit`
- `source mode edits canonical Markdown and switches back`
- `no-op edit does not rewrite body`
- `server preview matches final rendered post for supported syntax`
- mobile viewport smoke

Use DB assertions through existing test pages or seed-only routes. The no-op edit test should read the post body before and after edit and assert byte equality.

- [ ] **Step 5: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
./vendor/bin/phpunit tests/Unit/Composer/MarkdownRoundTripTest.php
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "WYSIWYG|source|no-op|preview"
```

Expected: PASS.

Commit:

```bash
git add public/assets/composer.js public/assets/app.css src/client/wysiwyg/milkdown-adapter.ts public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/Unit/Composer/MarkdownRoundTripTest.php tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat: add milkdown composer adapter"
```

### Task 9: Rich Chips and Internal URL Paste

**Files:**
- Modify: `src/client/wysiwyg/milkdown-adapter.ts`
- Modify: `src/client/wysiwyg/styles.css`
- Modify: `public/assets/wysiwyg-composer.js`
- Modify: `public/assets/wysiwyg-composer.css`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`

- [ ] **Step 1: Add browser tests for chips and paste**

Add tests:

```ts
test('wysiwyg reference selections become chips and serialize to markdown', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const editor = page.locator('.wysiwyg-composer .ProseMirror').first();
  await editor.click();
  await page.keyboard.type('#gen');
  await expect(page.locator('.composer-reference-menu[role="listbox"]')).toBeVisible();
  await page.keyboard.press('Enter');
  await expect(page.locator('.composer-chip')).toContainText('#general');
  await page.getByRole('button', { name: 'Source' }).click();
  await expect(page.locator('textarea.composer-input').first()).toHaveValue('[#general](/c/general)');
});

test('pasted internal topic url becomes canonical markdown chip', async ({ page }) => {
  await login(page, 'bob@retro.test');
  await visit(page, '/c/general');
  await page.locator('details.composer-details > summary').click();
  const firstTopic = await page.locator('a[href^="/t/"]').first().getAttribute('href');
  const editor = page.locator('.wysiwyg-composer .ProseMirror').first();
  await editor.click();
  await page.evaluate(async (text) => navigator.clipboard.writeText(`${location.origin}${text}`), firstTopic);
  await page.keyboard.press(process.platform === 'darwin' ? 'Meta+V' : 'Control+V');
  await expect(page.locator('.composer-chip')).toBeVisible();
  await page.getByRole('button', { name: 'Source' }).click();
  await expect(page.locator('textarea.composer-input').first()).toHaveValue(/\/t\/\d+-/);
});
```

- [ ] **Step 2: Add chip CSS**

In `src/client/wysiwyg/styles.css`:

```css
.composer-chip {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border: 1px solid var(--border);
  border-radius: 999px;
  background: var(--surface-3);
  color: var(--text-strong);
  padding: 1px 7px;
  line-height: 1.5;
}
.composer-chip.is-muted {
  color: var(--text-muted);
  border-style: dashed;
}
```

- [ ] **Step 3: Implement rich chip behavior**

In `MilkdownComposerAdapter`:
- On picker insertion, insert a Milkdown/ProseMirror inline node or mark that renders `.composer-chip`.
- Serialize chips to the `item.markdown` provided by the API.
- For `@`, chip text is `@username` and Markdown is raw `@username`.
- For `#` boards/tags/topics/posts, chip text is label and Markdown is the canonical link.
- Count mention chips from serialized Markdown; add `is-muted` beyond `MentionParser::MAX` equivalent count of 10 so non-notifying excess chips are visually distinct.

- [ ] **Step 4: Implement internal paste rewriting**

In the adapter paste handler:
- If pasted URL path matches `/c/{slug}`, request `#` suggestions with `q={slug}` and insert the exact board markdown when a matching URL is returned.
- If path matches `/tags/{slug}`, same tag behavior.
- If path matches `/t/{id}-{slug}` or `/t/{id}-{slug}#p{postId}`, request `#` suggestions with the topic slug/title token where possible, then fall back to canonical Markdown built from URL and visible text.
- External URLs fall through to default Milkdown paste.
- Undo should restore the raw pasted URL through the editor transaction history.

- [ ] **Step 5: Run checks and commit**

Run:

```bash
npm run build:wysiwyg
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts -g "chip|pasted"
```

Expected: PASS.

Commit:

```bash
git add src/client/wysiwyg/milkdown-adapter.ts src/client/wysiwyg/styles.css public/assets/wysiwyg-composer.js public/assets/wysiwyg-composer.css tests/browser/wysiwyg-composer.spec.ts
git commit -m "feat: add rich composer chips"
```

### Task 10: Browser Evidence, Runbook, and Documentation Closeout

**Files:**
- Create: `docs/runbooks/wysiwyg_composer.md`
- Modify: `COMPOSER.md`
- Modify: `SCHEMA.md`
- Modify: `docs/evidence/deploy-dark-features.md`
- Modify: `tests/browser/wysiwyg-composer.spec.ts`
- Modify: `tests/browser/a11y.spec.ts`
- Modify: `tests/browser/README.md`

- [ ] **Step 1: Expand browser acceptance tests**

Cover:
- new topic WYSIWYG compose and submit
- reply compose and submit
- DM compose and submit
- edit existing post
- source-mode round trip
- server preview parity
- local draft restore
- server draft conflict load-local/load-server behavior
- image paste/drop and alt text
- pending upload placeholder replacement
- `@` picker keyboard flow
- `#` picker board/tag/topic/post selections
- textarea adapter picker with `wysiwyg_composer=false`
- `#` heading trigger is ignored
- pasted internal URL becomes chip
- no-JS or kill-switch fallback
- strict-CSP smoke with no violations
- axe checks for toolbar and picker

- [ ] **Step 2: Add runbook**

Create `docs/runbooks/wysiwyg_composer.md` with:

````markdown
# Runbook - WYSIWYG Composer (`wysiwyg_composer`)

`wysiwyg_composer` gates only the Milkdown editor layer. `rich_composer=false` remains the broad kill switch and prevents all enhanced composer assets from loading.

## Enable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=true; $r->set("features",$f);'
```

## Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["wysiwyg_composer"]=false; $r->set("features",$f);'
```

Existing posts and drafts remain Markdown and need no migration.

## Emergency Disable

```bash
php -r 'require "vendor/autoload.php"; \App\Core\Env::load(getcwd()."/.env"); $c=\App\Core\Config::fromFile(getcwd()."/config/config.php"); $r=new \App\Repository\SettingRepository(new \App\Core\Database($c->get("db"))); $f=$r->get("features",[]); $f["rich_composer"]=false; $r->set("features",$f);'
```

This disables `composer.js`, the suggestion picker, and the WYSIWYG bundle. Server-rendered textarea posting remains available.

## Verify

Run:

```bash
./vendor/bin/phpunit tests/Integration/Core/AppComposerTest.php tests/Integration/Core/AppComposerSuggestTest.php tests/Integration/Core/AppMentionLinkRenderTest.php
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts -g "wysiwyg|composer"
```

## Known Limits

Markdown remains canonical. Legacy Markdown may normalize after a user edits through the rich surface, but a no-op edit must not rewrite the stored body.
````

- [ ] **Step 3: Update docs**

Update:
- `COMPOSER.md`: true WYSIWYG behavior, source mode, bridge, pickers, Markdown canonical storage, preview as final truth.
- `SCHEMA.md`: keep `0071` and `content_references.target_type='tag'` docs current.
- `docs/evidence/deploy-dark-features.md`: add `wysiwyg_composer` as deploy-dark with evidence links after browser/a11y runs.
- `tests/browser/README.md`: mention WYSIWYG evidence command.

- [ ] **Step 4: Run full verification**

Run:

```bash
composer test
npm run check:wysiwyg
cd tests/browser && npx playwright test wysiwyg-composer.spec.ts a11y.spec.ts
```

Expected: PASS.

Commit:

```bash
git add COMPOSER.md SCHEMA.md docs/evidence/deploy-dark-features.md docs/runbooks/wysiwyg_composer.md tests/browser/README.md tests/browser/wysiwyg-composer.spec.ts tests/browser/a11y.spec.ts
git commit -m "docs: document wysiwyg composer rollout"
```

## Final Verification

After all tasks:

```bash
composer test
npm run check:wysiwyg
cd tests/browser && npx playwright test
git status --short
```

Expected:
- PHPUnit full suite passes.
- WYSIWYG committed assets are reproducible.
- Playwright desktop and mobile projects pass.
- `git status --short` shows only intentional evidence screenshots if the evidence run writes new images.

## Self-Review

Spec coverage:
- ADR superseding/amending ADR 0001: Task 1.
- `wysiwyg_composer` flag and broad `rich_composer` kill switch: Task 1.
- Suggestion API, rate limit, read gates, context gates, short-query behavior, anonymous-safe ranking: Task 4.
- Tag reference enum, extraction, card rendering, feature gates: Task 2.
- Mention link rendering with shared grammar and code/pre exclusion: Task 3.
- Composer bridge and textarea adapter: Task 5.
- `@` and `#` picker on textarea bridge: Task 6.
- Milkdown build, committed artifact policy, exact dependency pins: Task 7.
- Milkdown adapter, source mode, no-op edit, semantic round trip: Task 8.
- Rich chips and internal URL paste: Task 9.
- Browser, CSP, axe, docs, runbook, rollout evidence: Task 10.

Execution notes:
- Milkdown plugin APIs may require small code-shape changes while preserving the adapter contract and tests above. If a required plugin injects runtime CSS or inline style attributes, stop Task 7 and record the fallback path in ADR 0013.
- Mention linking (Task 3) runs **after** `HtmlSanitizer::sanitize()` on purpose: the sanitizer keeps only `href` on `<a>` (and adds `rel`), so a `class="mention"` written before sanitisation would be stripped. The linker only emits same-origin `/u/{username}` anchors from an active-user allowlist, and it must keep its handle grammar in lockstep with `MentionParser`.
- Mention linking is bound to the `mentions` flag via `MentionLinker`'s `$enabled`, so links and notifications share one on/off switch.
- `AppComposerSuggestTest` (Task 4) commits fixtures and truncates in `tearDown` like `AppSearchTest`, because InnoDB FULLTEXT (used for `#` topic/post results) does not index rows inside an open transaction. An unauthenticated `/composer/suggest` returns a **302** redirect to `/login` (not 403), because `requireUser()` runs before the flag check.

Placeholder scan:
- No task depends on an unspecified endpoint, flag, table, or test file.
- Every planned new file has an exact path.
- Every migration number follows the current tree after `0070_phase5_publisher_review_security.php`.
