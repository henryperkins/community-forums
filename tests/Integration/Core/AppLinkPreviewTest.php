<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Config;
use App\Core\EgressBlockedException;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Security\EgressGuard;
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
        $settings->set('features', ['link_previews' => true]);
        $settings->set('link_preview_allowed_hosts', ['preview.example.test']);

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewpub']);
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
        $settings->set('features', ['link_previews' => true]);
        $settings->set('link_preview_allowed_hosts', ['preview.example.test']);

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewmulti']);
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
        (new SettingRepository($this->db))->set('features', ['link_previews' => true]);
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'previewpriv', 'visibility' => 'private']);
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

    public function test_preview_validation_requires_allowlist_and_blocks_private_resolutions(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('link_preview_allowed_hosts', ['internal.example.test']);

        $blocked = new LinkPreviewService(
            $this->db,
            new PostRepository($this->db),
            $settings,
            $this->config,
            new EgressGuard(true, [], static fn (string $host): array => ['127.0.0.1']),
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

            $id = $this->db->insert(
                "INSERT INTO link_previews (source_type, source_id, url, url_hash, status, created_at)
                 VALUES ('post', 1, ?, ?, 'queued', UTC_TIMESTAMP())",
                ['http://preview-pin.test:' . $port . '/story', hash('sha256', 'http://preview-pin.test:' . $port . '/story')],
            );

            $service = new LinkPreviewService(
                $this->db,
                new PostRepository($this->db),
                $settings,
                new Config(array_replace_recursive($this->config->all(), [
                    'link_previews' => ['allow_http' => true, 'timeout_seconds' => 1, 'max_bytes' => 4096],
                ])),
                new EgressGuard(true, ['127.0.0.1/32'], static fn (string $host): array => ['127.0.0.1']),
            );

            $stats = $service->fetchQueued(1);

            self::assertSame(['fetched' => 1, 'blocked' => 0, 'failed' => 0, 'skipped' => 0], $stats);
            $row = $this->db->fetch('SELECT status, title FROM link_previews WHERE id = ?', [$id]);
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
        $settings = new SettingRepository($this->db);
        $settings->set('features', ['link_previews' => true]);
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
    }

    private function previewService(): LinkPreviewService
    {
        return new LinkPreviewService(
            $this->db,
            new PostRepository($this->db),
            new SettingRepository($this->db),
            new Config(array_replace_recursive($this->config->all(), [
                'link_previews' => ['allow_http' => true],
            ])),
            new EgressGuard(true, [], static fn (string $host): array => ['93.184.216.34']),
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
