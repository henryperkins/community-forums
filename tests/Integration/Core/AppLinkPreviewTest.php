<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Config;
use App\Core\EgressBlockedException;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\LinkPreviewRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Security\BoardAuthority;
use App\Security\EgressGuard;
use App\Security\WriteGate;
use App\Service\LinkPreviewService;
use Tests\Support\TestCase;

final class AppLinkPreviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_public_post_queues_and_renders_sanitized_preview_metadata(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('link_preview_allowed_hosts', ['preview.example.test']);

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewpub', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'previewer']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Preview topic',
            'body' => 'See http://preview.example.test/story',
        ]));
        $thread = $this->db->fetch('SELECT id, slug FROM threads WHERE title = ?', ['Preview topic']);
        self::assertNotNull($thread);
        $preview = $this->db->fetch('SELECT * FROM link_previews WHERE source_type = ? LIMIT 1', ['post']);
        self::assertNotNull($preview);
        self::assertSame('queued', $preview['status']);

        $this->previewService()->storeFetchedMetadata(
            (int) $preview['id'],
            'http://preview.example.test/story',
            200,
            '<html><head><title>Clean title<script>alert(1)</script></title><meta name="description" content="Useful description"></head></html>',
        );

        $page = $this->get('/t/' . (int) $thread['id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Clean title');
        $this->assertSeeText($page, 'Useful description');
        self::assertStringNotContainsString('<script>', $page->body());
    }

    public function test_public_post_queues_every_distinct_preview_url(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('link_preview_allowed_hosts', ['preview.example.test']);

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewmulti', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'previewmulti']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Preview multi topic',
            'body' => 'First http://preview.example.test/one and second https://preview.example.test/two.',
        ]));

        $urls = $this->db->fetchAll('SELECT url FROM link_previews ORDER BY id ASC');
        self::assertSame([
            ['url' => 'http://preview.example.test/one'],
            ['url' => 'https://preview.example.test/two'],
        ], $urls);
    }

    public function test_private_board_posts_do_not_queue_outbound_previews(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, [
            'slug' => 'previewpriv',
            'visibility' => 'private',
            'link_previews_enabled' => 1,
        ]);
        $author = $this->makeUser(['username' => 'privatepreview']);
        $this->db->run(
            'INSERT INTO board_members (board_id, user_id, added_by, created_at) VALUES (?, ?, NULL, UTC_TIMESTAMP())',
            [(int) $board['id'], (int) $author['id']],
        );
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Private preview topic',
            'body' => 'See http://preview.example.test/secret',
        ]));

        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM link_previews'));
    }

    public function test_board_opt_in_is_required_before_anything_is_queued(): void
    {
        // DECISIONS §6 #5: previews are opt-in per board. A public board that
        // never opted in must queue nothing even with the flag on by default and
        // the host allowlisted — the flag makes the subsystem available, the
        // board makes it active.
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewoptout']);
        $author = $this->makeUser(['username' => 'optoutposter']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Opted-out topic',
            'body' => 'See http://preview.example.test/story',
        ]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM link_previews'));

        // Opting the board in makes the very next post queue.
        $this->db->run('UPDATE boards SET link_previews_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $thread = $this->db->fetch('SELECT id, slug FROM threads WHERE title = ?', ['Opted-out topic']);
        self::assertNotNull($thread);
        $this->assertRedirect($this->post('/t/' . (int) $thread['id'] . '/reply', [
            'body' => 'And also http://preview.example.test/second',
        ]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM link_previews'));
    }

    public function test_queued_row_is_held_when_its_board_opts_out_and_drains_when_it_returns(): void
    {
        // The fetch path re-checks eligibility: a backlog queued while the board
        // was on must not still reach the network after the operator switched it
        // off. A board opt-out is reversible, so the row must stay `queued` and
        // drain by itself when the board comes back — marking it `blocked` would
        // strand the backlog behind a per-row console refresh.
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewrevoke', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'revokeposter']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Revoked topic',
            'body' => 'See http://preview.example.test/story',
        ]));
        $id = (int) $this->db->fetchValue('SELECT id FROM link_previews LIMIT 1');

        $this->db->run('UPDATE boards SET link_previews_enabled = 0 WHERE id = ?', [(int) $board['id']]);

        $stats = $this->previewService()->fetchQueued(5);

        self::assertSame(['fetched' => 0, 'blocked' => 0, 'failed' => 0, 'skipped' => 1], $stats);
        $row = $this->db->fetch('SELECT status, error FROM link_previews WHERE id = ?', [$id]);
        self::assertNotNull($row);
        self::assertSame('queued', $row['status'], 'a reversible opt-out must not retire the row');
        self::assertNull($row['error']);

        // Deleting the post, by contrast, is permanent — that row is retired.
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [(int) $this->db->fetchValue('SELECT source_id FROM link_previews WHERE id = ?', [$id])]);
        $stats = $this->previewService()->fetchQueued(5);
        self::assertSame(['fetched' => 0, 'blocked' => 1, 'failed' => 0, 'skipped' => 0], $stats);
        self::assertSame('blocked', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));
    }

    public function test_rolling_the_flag_back_stops_the_worker_without_touching_the_queue(): void
    {
        // The cron worker builds LinkPreviewService directly, so it is not covered
        // by the route gates. Rollback has to stop it there too, or a rolled-back
        // install keeps fetching from allowlisted hosts on every worker pass —
        // exactly what the operator was trying to stop. Nothing is retired: the
        // rows are still queued and drain when the flag comes back.
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        (new SettingRepository($this->db))->set('features', ['link_previews' => false]);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewrollback', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'rollbackworker']);
        $thread = $this->makeThread($board, $author, 'Rollback worker topic', 'Body without links.');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1',
            [$thread['thread_id']],
        );
        $id = $this->seedPreview('http://preview.example.test/rollback', $postId);

        $stats = $this->previewService()->fetchQueued(5);

        self::assertSame(['fetched' => 0, 'blocked' => 0, 'failed' => 0, 'skipped' => 1], $stats);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));

        // …and a write while rolled back queues nothing new.
        $this->actingAs($author);
        $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/reply', [
            'body' => 'Another http://preview.example.test/second',
        ]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM link_previews'));
    }

    public function test_re_saving_a_post_does_not_recount_an_existing_card_as_newly_queued(): void
    {
        // queueFromBody() reports how many rows it actually created or revived.
        // An already-fetched row is neither, so a plain re-save must report zero
        // rather than counting the standing card again.
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewrecount', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'recountauthor']);
        $thread = $this->makeThread($board, $author, 'Recount topic', 'Body without links.');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1',
            [$thread['thread_id']],
        );

        $body = 'See http://preview.example.test/recount';
        $service = $this->previewService();
        self::assertSame(1, $service->queueFromBody('post', $postId, $body), 'first save creates the row');
        self::assertSame(0, $service->queueFromBody('post', $postId, $body), 'a re-save creates nothing');

        $service->storeFetchedMetadata(
            (int) $this->db->fetchValue('SELECT id FROM link_previews LIMIT 1'),
            'http://preview.example.test/recount',
            200,
            '<html><head><title>Recount</title></head></html>',
        );
        self::assertSame(0, $service->queueFromBody('post', $postId, $body), 'a fetched card is not newly queued');
        self::assertSame('fetched', (string) $this->db->fetchValue('SELECT status FROM link_previews LIMIT 1'));
    }

    public function test_kill_switch_leaves_the_queue_untouched(): void
    {
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        (new SettingRepository($this->db))->set('link_preview_kill_switch', true);
        $id = $this->seedPreview('http://preview.example.test/killed');

        $stats = $this->previewService()->fetchQueued(5);

        self::assertSame(['fetched' => 0, 'blocked' => 0, 'failed' => 0, 'skipped' => 1], $stats);
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));
    }

    public function test_author_can_remove_a_preview_and_the_removal_survives_an_edit(): void
    {
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewauthor', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'removingauthor']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Author removal topic',
            'body' => 'See http://preview.example.test/story',
        ]));
        $thread = $this->db->fetch('SELECT id, slug FROM threads WHERE title = ?', ['Author removal topic']);
        self::assertNotNull($thread);
        $preview = $this->db->fetch('SELECT * FROM link_previews LIMIT 1');
        self::assertNotNull($preview);
        $postId = (int) $preview['source_id'];
        $this->previewService()->storeFetchedMetadata(
            (int) $preview['id'],
            'http://preview.example.test/story',
            200,
            '<html><head><title>Unfurled title</title></head></html>',
        );

        $url = '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
        $this->assertSeeText($this->get($url), 'Unfurled title');

        $this->assertRedirect($this->post('/posts/' . $postId . '/previews/' . (int) $preview['id'] . '/remove'));

        $row = $this->db->fetch('SELECT status, title, removed_by FROM link_previews WHERE id = ?', [(int) $preview['id']]);
        self::assertSame('removed', $row['status']);
        self::assertNull($row['title']);
        self::assertSame((int) $author['id'], (int) $row['removed_by']);

        $page = $this->get($url);
        self::assertStringNotContainsString('Unfurled title', $page->body());
        $this->assertSeeText($page, 'Link preview removed from this post.');

        // Editing the post re-runs the queue upsert; a removed card must not
        // come back through it.
        $this->assertRedirect($this->post('/posts/' . $postId . '/edit', [
            'body' => 'Still see http://preview.example.test/story',
        ]));
        self::assertSame('removed', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [(int) $preview['id']]));

        // …and the author can put it back.
        $this->assertRedirect($this->post('/posts/' . $postId . '/previews/' . (int) $preview['id'] . '/restore'));
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [(int) $preview['id']]));
    }

    public function test_another_member_can_neither_see_nor_remove_someone_elses_preview(): void
    {
        (new SettingRepository($this->db))->set('link_preview_allowed_hosts', ['preview.example.test']);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewstranger', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'previewowner']);
        $this->actingAs($author);
        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Stranger topic',
            'body' => 'See http://preview.example.test/story',
        ]));
        $thread = $this->db->fetch('SELECT id, slug FROM threads WHERE title = ?', ['Stranger topic']);
        self::assertNotNull($thread);
        $preview = $this->db->fetch('SELECT * FROM link_previews LIMIT 1');
        self::assertNotNull($preview);
        $this->previewService()->storeFetchedMetadata(
            (int) $preview['id'],
            'http://preview.example.test/story',
            200,
            '<html><head><title>Someone elses card</title></head></html>',
        );

        $this->actingAs($this->makeUser(['username' => 'previewstranger']));

        $page = $this->get('/t/' . (int) $thread['id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Someone elses card');
        self::assertStringNotContainsString('Remove preview', $page->body());

        $this->assertStatus(403, $this->post(
            '/posts/' . (int) $preview['source_id'] . '/previews/' . (int) $preview['id'] . '/remove',
        ));
        self::assertSame('fetched', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [(int) $preview['id']]));
    }

    public function test_operator_refresh_refuses_to_override_an_author_removal(): void
    {
        $author = $this->makeUser(['username' => 'stickyremover']);
        $id = $this->seedPreview('http://preview.example.test/sticky');
        (new LinkPreviewRepository($this->db))->markRemoved($id, (int) $author['id']);

        $this->actingAs($this->makeAdmin(['username' => 'overreachadmin']));
        $response = $this->post('/admin/link-previews/' . $id . '/refresh', ['return' => '/admin/link-previews']);

        $this->assertRedirect($response);
        self::assertSame('removed', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));

        $this->expectException(ValidationException::class);
        $this->previewService()->refresh($id);
    }

    public function test_preview_validation_requires_allowlist_and_blocks_private_resolutions(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('link_preview_allowed_hosts', ['internal.example.test']);

        $blocked = new LinkPreviewService(
            $this->db,
            new LinkPreviewRepository($this->db),
            new PostRepository($this->db),
            $settings,
            $this->config,
            new EgressGuard(true, [], static fn (string $host): array => ['127.0.0.1']),
            new WriteGate(),
            new FeatureFlags(new SettingRepository($this->db)),
        );

        $this->expectException(EgressBlockedException::class);
        $blocked->validateFetchUrl('http://internal.example.test/card');
    }

    public function test_fetch_uses_the_guard_resolved_ip_for_the_actual_curl_connection(): void
    {
        [$server, $port, $dir] = $this->startPreviewServer();
        try {
            $settings = new SettingRepository($this->db);
            $settings->set('link_preview_allowed_hosts', ['preview-pin.test']);

            $cat = $this->makeCategory();
            $board = $this->makeBoard($cat, ['slug' => 'previewpin', 'link_previews_enabled' => 1]);
            $author = $this->makeUser(['username' => 'previewpinner']);
            $thread = $this->makeThread($board, $author, 'Pin topic', 'Body without links.');
            $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1', [$thread['thread_id']]);

            $url = 'http://preview-pin.test:' . $port . '/story';
            $id = $this->db->insert(
                "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, created_at)
                 VALUES ('post', ?, ?, ?, 'queued', UTC_TIMESTAMP())",
                [$postId, $url, hash('sha256', $url)],
            );

            $service = new LinkPreviewService(
                $this->db,
                new LinkPreviewRepository($this->db),
                new PostRepository($this->db),
                $settings,
                new Config(array_replace_recursive($this->config->all(), [
                    'link_previews' => ['allow_http' => true, 'timeout_seconds' => 1, 'max_bytes' => 4096],
                ])),
                new EgressGuard(true, ['127.0.0.1/32'], static fn (string $host): array => ['127.0.0.1']),
                new WriteGate(),
                new FeatureFlags(new SettingRepository($this->db)),
            );

            $stats = $service->fetchQueued(1);

            self::assertSame(['fetched' => 1, 'blocked' => 0, 'failed' => 0, 'skipped' => 0], $stats);
            $row = $this->db->fetch('SELECT status, title FROM link_previews WHERE id = ?', [$id]);
            self::assertNotNull($row);
            self::assertSame('fetched', $row['status']);
            self::assertSame('Pinned Preview OK', $row['title']);
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink($dir . '/router.php');
            @rmdir($dir);
        }
    }

    public function test_admin_can_purge_and_refresh_preview_rows(): void
    {
        $admin = $this->makeAdmin(['username' => 'previewadmin']);
        $this->actingAs($admin);

        $id = $this->db->insert(
            "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, title, created_at)
             VALUES ('post', 1, 'http://preview.example.test/a', ?, 'fetched', 'Title', UTC_TIMESTAMP())",
            [hash('sha256', 'http://preview.example.test/a')],
        );

        $this->assertRedirect($this->post('/admin/link-previews/' . $id . '/purge'));
        self::assertSame('purged', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));
        self::assertNull($this->db->fetchValue('SELECT title FROM link_previews WHERE id = ?', [$id]));

        $this->assertRedirect($this->post('/admin/link-previews/' . $id . '/refresh'));
        self::assertSame('queued', (string) $this->db->fetchValue('SELECT status FROM link_previews WHERE id = ?', [$id]));

        // Both operator actions are on the record, anchored to the post they
        // belong to rather than the preview row.
        $actions = $this->db->fetchAll(
            "SELECT action FROM moderation_log WHERE action LIKE 'link_preview_%' ORDER BY id ASC",
        );
        self::assertSame([['action' => 'link_preview_purge'], ['action' => 'link_preview_refresh']], $actions);
    }

    public function test_admin_console_reports_the_gates_and_drives_the_allowlist_and_board_opt_in(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'consoleboard', 'name' => 'Console Board']);
        $this->actingAs($this->makeAdmin(['username' => 'consoleadmin']));

        // Nothing configured: the console names both remaining steps.
        $page = $this->get('/admin/link-previews');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'No hosts are allowlisted');
        $this->assertSeeText($page, 'No public board has opted in');

        $this->assertRedirect($this->post('/admin/link-previews/settings', [
            'allowed_hosts' => "Preview.Example.test\n*.docs.example.test",
            'kill_switch' => '0',
        ]));
        self::assertSame(
            ['preview.example.test', '*.docs.example.test'],
            (new SettingRepository($this->db))->get('link_preview_allowed_hosts'),
        );

        $this->assertRedirect($this->post('/admin/link-previews/boards/' . (int) $board['id'], [
            'enabled' => '1',
            'return' => '/admin/link-previews',
        ]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT link_previews_enabled FROM boards WHERE id = ?', [(int) $board['id']]));

        $page = $this->get('/admin/link-previews');
        $this->assertStatus(200, $page);
        self::assertStringNotContainsString('Nothing is being fetched right now', $page->body());

        // The board opt-in is audited so a later "who turned this on" is answerable.
        self::assertSame(
            'link_preview_board_enable',
            (string) $this->db->fetchValue("SELECT action FROM moderation_log WHERE target_type = 'board' AND action LIKE 'link_preview_%' ORDER BY id DESC LIMIT 1"),
        );
    }

    public function test_console_rejects_a_malformed_allowlist_without_losing_the_typed_value(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'badhostadmin']));

        $response = $this->post('/admin/link-previews/settings', [
            'allowed_hosts' => "good.example.test\nhttps://not-a-host/path",
            'kill_switch' => '0',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Not a hostname or *.wildcard pattern');
        self::assertStringContainsString('good.example.test', $response->body());
        self::assertFalse((new SettingRepository($this->db))->has('link_preview_allowed_hosts'));
    }

    public function test_console_and_member_routes_disappear_when_an_operator_rolls_the_flag_back(): void
    {
        (new SettingRepository($this->db))->set('features', ['link_previews' => false]);
        $author = $this->makeUser(['username' => 'rolledbackauthor']);
        $id = $this->seedPreview('http://preview.example.test/rolled');

        $this->actingAs($this->makeAdmin(['username' => 'rolledbackadmin']));
        $this->assertStatus(404, $this->get('/admin/link-previews'));
        $this->assertStatus(404, $this->post('/admin/link-previews/' . $id . '/purge'));

        $this->actingAs($author);
        $this->assertStatus(404, $this->post('/posts/1/previews/' . $id . '/remove'));
    }

    public function test_a_suspended_author_cannot_remove_a_preview(): void
    {
        // State beats role: suspension closes every write path, including this one.
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewsuspend', 'link_previews_enabled' => 1]);
        $author = $this->makeUser(['username' => 'suspendedauthor']);
        $thread = $this->makeThread($board, $author, 'Suspended topic', 'Body without links.');
        $postId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? ORDER BY id ASC LIMIT 1',
            [$thread['thread_id']],
        );
        $id = $this->seedPreview('http://preview.example.test/suspended', $postId);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY) WHERE id = ?",
            [(int) $author['id']],
        );

        $suspended = $this->db->fetch('SELECT * FROM users WHERE id = ?', [(int) $author['id']]);
        self::assertNotNull($suspended);

        $this->expectException(ForbiddenException::class);
        $this->previewService()->remove($this->userEntity($suspended), $id);
    }

    private function seedPreview(string $url, int $sourceId = 1): int
    {
        return $this->db->insert(
            "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, created_at)
             VALUES ('post', ?, ?, ?, 'queued', UTC_TIMESTAMP())",
            [$sourceId, $url, hash('sha256', $url)],
        );
    }

    private function previewService(): LinkPreviewService
    {
        return new LinkPreviewService(
            $this->db,
            new LinkPreviewRepository($this->db),
            new PostRepository($this->db),
            new SettingRepository($this->db),
            new Config(array_replace_recursive($this->config->all(), [
                'link_previews' => ['allow_http' => true],
            ])),
            new EgressGuard(true, [], static fn (string $host): array => ['93.184.216.34']),
            new WriteGate(),
            new FeatureFlags(new SettingRepository($this->db)),
            new BoardAuthority(new WriteGate(), new BoardModeratorRepository($this->db), new BoardRepository($this->db)),
        );
    }

    /** @return array{0:resource,1:int,2:string} */
    private function startPreviewServer(): array
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        self::assertIsResource($socket, $errstr);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);
        $port = (int) substr(strrchr($name, ':') ?: ':0', 1);
        self::assertGreaterThan(0, $port);

        $dir = sys_get_temp_dir() . '/rb-preview-' . bin2hex(random_bytes(6));
        mkdir($dir, 0700, true);
        $router = $dir . '/router.php';
        file_put_contents($router, <<<'PHP'
<?php
header('Content-Type: text/html; charset=UTF-8');
echo '<html><head><title>Pinned Preview OK</title><meta name="description" content="resolved through guard"></head><body>ok</body></html>';
PHP);

        $nullDevice = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
        $server = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $router],
            [['pipe', 'r'], ['file', $nullDevice, 'w'], ['file', $nullDevice, 'w']],
            $pipes,
            $dir,
        );
        self::assertIsResource($server);
        fclose($pipes[0]);

        for ($i = 0; $i < 40; $i++) {
            $probe = @file_get_contents('http://127.0.0.1:' . $port . '/ready');
            if ($probe !== false) {
                return [$server, $port, $dir];
            }
            usleep(50_000);
        }

        proc_terminate($server);
        proc_close($server);
        @unlink($router);
        @rmdir($dir);
        self::fail('Preview test server did not become ready.');
    }
}
