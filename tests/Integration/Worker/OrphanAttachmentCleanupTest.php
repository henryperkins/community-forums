<?php

declare(strict_types=1);

namespace Tests\Integration\Worker;

use App\Repository\AttachmentRepository;
use App\Service\AttachmentService;
use App\Worker\OrphanAttachmentCleaner;
use Tests\Support\TestCase;

/**
 * P3-04: the orphan sweep reclaims abandoned temp uploads (past TTL) and media
 * whose parent post was deleted, removing both the file and marking the row.
 */
final class OrphanAttachmentCleanupTest extends TestCase
{
    private function service(): AttachmentService
    {
        return new AttachmentService(
            new AttachmentRepository($this->db),
            (string) $this->config->get('uploads.storage_path'),
        );
    }

    public function test_abandoned_temp_upload_is_swept(): void
    {
        $user = $this->makeUser();
        $svc = $this->service();
        $repo = new AttachmentRepository($this->db);

        $tmp = tempnam(sys_get_temp_dir(), 'rbtmpimg');
        file_put_contents($tmp, $this->pngBytes());
        $att = $svc->storeUpload((int) $user['id'], ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]);
        $path = $svc->pathFor($att);
        self::assertFileExists($path);

        // Sweep with a clock far in the future so the 24h TTL has elapsed.
        $cleaner = new OrphanAttachmentCleaner($repo, $svc, 24);
        $stats = $cleaner->run(gmdate('Y-m-d H:i:s', time() + 48 * 3600));

        self::assertSame(1, $stats['temp']);
        self::assertFileDoesNotExist($path);
        self::assertSame('deleted', $repo->find((int) $att['id'])['status']);
    }

    public function test_recent_temp_upload_is_kept(): void
    {
        $user = $this->makeUser();
        $svc = $this->service();
        $tmp = tempnam(sys_get_temp_dir(), 'rbtmpimg');
        file_put_contents($tmp, $this->pngBytes());
        $att = $svc->storeUpload((int) $user['id'], ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]);

        // Sweep "now" — the upload is well within the TTL.
        $stats = (new OrphanAttachmentCleaner(new AttachmentRepository($this->db), $svc, 24))->run();
        self::assertSame(0, $stats['temp']);
        self::assertFileExists($svc->pathFor($att));
    }

    public function test_media_of_deleted_post_is_swept(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'mediadel']);
        $author = $this->makeUser();
        $svc = $this->service();
        $repo = new AttachmentRepository($this->db);

        $tmp = tempnam(sys_get_temp_dir(), 'rbtmpimg');
        file_put_contents($tmp, $this->pngBytes());
        $att = $svc->storeUpload((int) $author['id'], ['name' => 'a.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize($tmp)]);

        $thread = $this->makeThread($board, $author, 'With media', "![](/media/{$att['id']})");
        $postId = (int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ?', [$thread['thread_id']]);
        $repo->finalizeForPost((int) $author['id'], $postId, [(int) $att['id']], 'public');
        $path = $svc->pathFor($repo->find((int) $att['id']));
        self::assertFileExists($path);

        // Just soft-deleted (within the restore/appeal grace window) → RETAINED.
        $this->db->run('UPDATE posts SET is_deleted = 1, deleted_at = UTC_TIMESTAMP() WHERE id = ?', [$postId]);
        $recent = (new OrphanAttachmentCleaner($repo, $svc, 24, 30))->run();
        self::assertSame(0, $recent['deleted_parent']);
        self::assertFileExists($path);

        // Deleted long enough ago (past the 30-day grace) → reclaimed.
        $this->db->run('UPDATE posts SET deleted_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 40 * 86400), $postId]);
        $stats = (new OrphanAttachmentCleaner($repo, $svc, 24, 30))->run();
        self::assertSame(1, $stats['deleted_parent']);
        self::assertFileDoesNotExist($path);
    }
}
