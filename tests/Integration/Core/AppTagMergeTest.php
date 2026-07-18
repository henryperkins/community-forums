<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * PR #44 spec §4 (7g): the tag-merge impact preview must count EVERY
 * association mergeInto() will move — the old countThreadsForTag filtered by
 * visibility/tags_enabled/is_deleted/is_pending while the merge moved all
 * thread_tags rows, so the admin was told "1 thread" and moved 4.
 */
final class AppTagMergeTest extends TestCase
{
    /** @return array{admin:array<string,mixed>,source:int,target:int} */
    private function seedMergePair(): array
    {
        $admin = $this->makeAdmin(['username' => 'tagadmin']);
        $cat = $this->makeCategory('Tagged');
        $boardOn = $this->makeBoard($cat, ['name' => 'Tags On']);
        $boardOff = $this->makeBoard($cat, ['name' => 'Tags Off']);
        $this->db->run('UPDATE boards SET tags_enabled = 0 WHERE id = ?', [(int) $boardOff['id']]);
        $author = $this->makeUser();
        $source = (int) $this->db->insert(
            "INSERT INTO tags (slug, name, visibility, is_enabled, created_at) VALUES ('mergesrc', 'Merge Source', 'public', 1, UTC_TIMESTAMP())",
        );
        $target = (int) $this->db->insert(
            "INSERT INTO tags (slug, name, visibility, is_enabled, created_at) VALUES ('mergedst', 'Merge Target', 'public', 1, UTC_TIMESTAMP())",
        );

        // Four associations, three invisible to the old preview: a visible
        // thread, a soft-deleted one, a held one, and one on a tags-off board.
        $visible = $this->makeThread($boardOn, $author, 'Visible tagged', 'Body.');
        $deleted = $this->makeThread($boardOn, $author, 'Deleted tagged', 'Body.');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [(int) $deleted['thread_id']]);
        $offBoard = $this->makeThread($boardOff, $author, 'Off-board tagged', 'Body.');
        $this->db->run('UPDATE boards SET require_approval = 1 WHERE id = ?', [(int) $boardOn['id']]);
        $pending = $this->makeThread($boardOn, $author, 'Pending tagged', 'Body.');
        $this->db->run('UPDATE boards SET require_approval = 0 WHERE id = ?', [(int) $boardOn['id']]);
        self::assertSame(
            1,
            (int) $this->db->fetchValue('SELECT is_pending FROM threads WHERE id = ?', [(int) $pending['thread_id']]),
            'fixture: the fourth thread must be held',
        );

        foreach ([$visible, $deleted, $offBoard, $pending] as $t) {
            $this->db->run(
                'INSERT INTO thread_tags (thread_id, tag_id, added_by, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [(int) $t['thread_id'], $source, (int) $admin['id']],
            );
        }
        return ['admin' => $admin, 'source' => $source, 'target' => $target];
    }

    public function test_merge_form_requires_an_enabled_destination(): void
    {
        // One enabled tag + one disabled tag used to pass the count($tags) > 1
        // gate while every destination option was filtered out — a Merge… form
        // with an empty selector. The gate must count enabled tags only.
        $admin = $this->makeAdmin(['username' => 'gateadmin']);
        $this->db->insert("INSERT INTO tags (slug, name, visibility, is_enabled, created_at) VALUES ('lonely', 'Lonely', 'public', 1, UTC_TIMESTAMP())");
        $this->db->insert("INSERT INTO tags (slug, name, visibility, is_enabled, created_at) VALUES ('retired', 'Retired', 'public', 0, UTC_TIMESTAMP())");

        $this->actingAs($admin);
        $one = $this->get('/admin/tags');
        $this->assertStatus(200, $one);
        $this->assertDontSeeText($one, 'Merge…');

        // A second enabled tag makes a real destination — the form returns.
        $this->db->run("UPDATE tags SET is_enabled = 1 WHERE slug = 'retired'");
        $two = $this->get('/admin/tags');
        $this->assertStatus(200, $two);
        $this->assertSeeText($two, 'Merge…');
    }

    public function test_merge_confirm_counts_every_association_and_merge_moves_them_all(): void
    {
        $seed = $this->seedMergePair();
        $this->actingAs($seed['admin']);

        $confirm = $this->get('/admin/tags/' . $seed['source'] . '/merge', ['target_id' => (string) $seed['target']]);
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, '4 tag association');
        $this->assertSeeText($confirm, 'includes hidden, held, and deleted threads');

        $merge = $this->post('/admin/tags/' . $seed['source'] . '/merge', ['target_id' => (string) $seed['target']]);
        $this->assertRedirectContains($merge, '/admin/tags');
        self::assertSame(4, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE tag_id = ?', [$seed['target']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE tag_id = ?', [$seed['source']]));
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM tag_aliases WHERE alias_slug = 'mergesrc' AND tag_id = ?", [$seed['target']]),
        );
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_enabled FROM tags WHERE id = ?', [$seed['source']]));
    }

    public function test_merge_refuses_a_destination_that_is_no_longer_enabled(): void
    {
        $seed = $this->seedMergePair();
        $this->db->run('UPDATE tags SET is_enabled = 0 WHERE id = ?', [$seed['target']]);
        $this->actingAs($seed['admin']);

        $res = $this->post('/admin/tags/' . $seed['source'] . '/merge', [
            'target_id' => (string) $seed['target'],
        ]);

        $this->assertRedirectContains($res, '/admin/tags');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_enabled FROM tags WHERE id = ?', [$seed['source']]));
        self::assertSame(4, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_tags WHERE tag_id = ?', [$seed['source']]));
    }
}
