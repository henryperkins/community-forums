<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\TagRepository;
use Tests\Support\TestCase;

final class AppTagAdminTest extends TestCase
{
    public function test_invalid_tag_create_rerenders_422_with_typed_values(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'tag_create_admin']));

        $res = $this->post('/admin/tags', [
            'name' => '',
            'slug' => 'kept-slug',
            'description' => 'Description that should survive.',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('kept-slug', $res->body());
        self::assertStringContainsString('Description that should survive.', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM tags WHERE slug = ?', ['kept-slug']));
    }

    public function test_explicit_tag_slug_over_64_chars_is_422_not_silently_truncated(): void
    {
        // Round-2 audit finding 5: a 300-char typed slug used to be cut to 64
        // with no error (name over-length already 422s — same rule for slugs).
        $this->actingAs($this->makeAdmin(['username' => 'tag_slug_admin']));

        $res = $this->post('/admin/tags', [
            'name' => 'LongSlugTag',
            'slug' => str_repeat('a', 300),
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('64 characters or fewer', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM tags WHERE name = ?', ['LongSlugTag']));
    }

    public function test_duplicate_tag_create_rerenders_422_with_typed_values(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_dup_admin']);
        $repo = new TagRepository($this->db);
        $repo->create('existing-tag', 'Existing tag', null, (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->post('/admin/tags', [
            'name' => 'Duplicate typed name',
            'slug' => 'existing-tag',
            'description' => 'Duplicate description survives.',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('Duplicate typed name', $res->body());
        self::assertStringContainsString('existing-tag', $res->body());
        self::assertStringContainsString('Duplicate description survives.', $res->body());
        self::assertStringContainsString(
            'A tag with the slug “existing-tag” already exists. Merge into it rather than adding a twin.',
            $res->body(),
        );
    }

    public function test_invalid_tag_update_rerenders_422_with_typed_row_values(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_update_admin']);
        $repo = new TagRepository($this->db);
        $tagId = $repo->create('stored-tag', 'Stored tag', 'Stored description', (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->post('/admin/tags/' . $tagId, [
            'name' => '',
            'slug' => 'typed-row-slug',
            'description' => 'Typed row description survives.',
            'visibility' => 'hidden',
            'enabled' => '1',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('typed-row-slug', $res->body());
        self::assertStringContainsString('Typed row description survives.', $res->body());
        self::assertSame('stored-tag', (string) $this->db->fetchValue('SELECT slug FROM tags WHERE id = ?', [$tagId]));
    }

    public function test_admin_catalogue_counts_every_tag_association_and_sorts_by_usage(): void
    {
        // Regression shield: joining through visible/live/tag-enabled threads
        // would understate the exact association set that an admin merge moves.
        $admin = $this->makeAdmin(['username' => 'tag_usage_admin']);
        $repo = new TagRepository($this->db);
        $highId = $repo->create('zulu-high-usage', 'Zulu high usage', null, (int) $admin['id']);
        $lowId = $repo->create('alpha-low-usage', 'Alpha low usage', null, (int) $admin['id']);
        $categoryId = $this->makeCategory('Tag usage boards');
        $publicBoard = $this->makeBoard($categoryId, ['slug' => 'tag-usage-public']);
        $hiddenBoard = $this->makeBoard($categoryId, ['slug' => 'tag-usage-hidden', 'visibility' => 'hidden']);
        $disabledBoard = $this->makeBoard($categoryId, ['slug' => 'tag-usage-disabled']);

        $live = $this->makeThread($publicBoard, $admin, 'Live association');
        $deleted = $this->makeThread($publicBoard, $admin, 'Deleted association');
        $pending = $this->makeThread($publicBoard, $admin, 'Pending association');
        $hidden = $this->makeThread($hiddenBoard, $admin, 'Hidden-board association');
        $disabled = $this->makeThread($disabledBoard, $admin, 'Tags-disabled association');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [(int) $deleted['thread_id']]);
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [(int) $pending['thread_id']]);
        $this->db->run('UPDATE boards SET tags_enabled = 0 WHERE id = ?', [(int) $disabledBoard['id']]);

        foreach ([$live, $deleted, $pending, $hidden, $disabled] as $thread) {
            $this->db->run(
                'INSERT INTO thread_tags (thread_id, tag_id, added_by, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [(int) $thread['thread_id'], $highId, (int) $admin['id']],
            );
        }
        $this->db->run(
            'INSERT INTO thread_tags (thread_id, tag_id, added_by, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
            [(int) $live['thread_id'], $lowId, (int) $admin['id']],
        );

        $rows = array_values(array_filter(
            $repo->allForAdmin(),
            static fn (array $row): bool => in_array((int) $row['id'], [$highId, $lowId], true),
        ));

        self::assertSame([$highId, $lowId], array_map(static fn (array $row): int => (int) $row['id'], $rows));
        self::assertArrayHasKey('usage_count', $rows[0]);
        self::assertSame(5, (int) $rows[0]['usage_count']);
        self::assertSame(1, (int) $rows[1]['usage_count']);
    }

    public function test_admin_catalogue_searches_name_or_slug_and_clamps_to_the_last_eight_row_page(): void
    {
        // Regression shield: ignoring q/page, searching descriptions, or using
        // a zero-based URL would leak the first page into this response.
        $admin = $this->makeAdmin(['username' => 'tag_catalogue_admin']);
        $repo = new TagRepository($this->db);
        for ($i = 1; $i <= 10; $i++) {
            $repo->create(
                sprintf('clamp-series-%02d', $i),
                sprintf('Clamp series %02d', $i),
                null,
                (int) $admin['id'],
            );
        }
        $repo->create('description-only', 'Description only', 'clamp series', (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->get('/admin/tags', ['q' => 'clamp series', 'sort' => 'name', 'page' => '999']);

        $this->assertStatus(200, $res);
        self::assertSame(
            ['Clamp series 09', 'Clamp series 10'],
            $this->catalogueRowNames($res->body()),
        );
    }

    public function test_admin_catalogue_search_matches_a_slug_when_the_name_does_not(): void
    {
        // Regression shield: the design's catalogue search explicitly covers
        // both the human label and its URL-facing slug.
        $admin = $this->makeAdmin(['username' => 'tag_slug_search_admin']);
        $repo = new TagRepository($this->db);
        $repo->create('slug-only-needle', 'Unrelated tag label', null, (int) $admin['id']);
        $repo->create('different-slug', 'Different tag label', 'slug-only-needle', (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->get('/admin/tags', ['q' => 'slug-only-needle', 'sort' => 'name']);

        $this->assertStatus(200, $res);
        self::assertSame(['Unrelated tag label'], $this->catalogueRowNames($res->body()));
    }

    public function test_admin_merge_targets_are_unfiltered_unpaged_and_include_hidden_enabled_tags(): void
    {
        // Regression shield: reusing either the public-tag query or the
        // eight-row catalogue page would make valid no-JS merge targets vanish.
        $admin = $this->makeAdmin(['username' => 'tag_merge_targets_admin']);
        $repo = new TagRepository($this->db);
        $expectedIds = [];
        for ($i = 1; $i <= 10; $i++) {
            $id = $repo->create(
                sprintf('merge-target-%02d', $i),
                sprintf('Merge target %02d', $i),
                null,
                (int) $admin['id'],
            );
            $expectedIds[] = $id;
            if ($i === 10) {
                $repo->update($id, 'merge-target-10', 'Merge target 10', null, true, 'hidden');
            }
        }
        $disabledId = $repo->create('merge-target-disabled', 'Merge target disabled', null, (int) $admin['id']);
        $repo->update($disabledId, 'merge-target-disabled', 'Merge target disabled', null, false);

        $targets = $repo->allEnabledForAdmin();

        self::assertSame(
            $expectedIds,
            array_map(static fn (array $row): int => (int) $row['id'], $targets),
        );
        self::assertSame('hidden', (string) $targets[9]['visibility']);
    }

    public function test_invalid_tag_create_preserves_catalogue_query_sort_and_page(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_create_context_admin']);
        $repo = new TagRepository($this->db);
        for ($i = 1; $i <= 10; $i++) {
            $repo->create(
                sprintf('create-context-%02d', $i),
                sprintf('Create context %02d', $i),
                null,
                (int) $admin['id'],
            );
        }
        $this->actingAs($admin);

        $res = $this->post('/admin/tags', [
            'name' => '',
            'slug' => 'typed-create-context',
            'q' => 'create context',
            'sort' => 'name',
            'page' => '2',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('typed-create-context', $res->body());
        self::assertSame(
            ['Create context 09', 'Create context 10'],
            $this->catalogueRowNames($res->body()),
        );
    }

    public function test_invalid_tag_update_preserves_catalogue_query_sort_and_page(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_update_context_admin']);
        $repo = new TagRepository($this->db);
        $ids = [];
        for ($i = 1; $i <= 10; $i++) {
            $ids[$i] = $repo->create(
                sprintf('update-context-%02d', $i),
                sprintf('Update context %02d', $i),
                null,
                (int) $admin['id'],
            );
        }
        $this->actingAs($admin);

        $res = $this->post('/admin/tags/' . $ids[9], [
            'name' => '',
            'slug' => 'typed-update-context',
            'q' => 'update context',
            'sort' => 'name',
            'page' => '2',
            'enabled' => '1',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('typed-update-context', $res->body());
        self::assertSame(['', 'Update context 10'], $this->catalogueRowNames($res->body()));
        self::assertSame(
            'update-context-09',
            (string) $this->db->fetchValue('SELECT slug FROM tags WHERE id = ?', [$ids[9]]),
        );
    }

    /** @return list<string> */
    private function catalogueRowNames(string $html): array
    {
        $matched = preg_match_all(
            '~<input\b[^>]*\bclass="[^"]*\bcontent-tag-name\b[^"]*"[^>]*\bvalue="([^"]*)"[^>]*>~',
            $html,
            $matches,
        );
        self::assertNotFalse($matched);
        return array_map(
            static fn (string $value): string => htmlspecialchars_decode($value, ENT_QUOTES),
            $matches[1],
        );
    }
}
