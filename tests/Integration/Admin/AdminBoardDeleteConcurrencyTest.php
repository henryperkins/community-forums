<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\Database;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\TestCase;

/**
 * PR #44 spec §3: board-delete validation runs INSIDE the delete transaction
 * against exclusively locked rows, so a destination that changed after the
 * preview offered it is re-judged from the locked row. Deterministic
 * two-connection choreography (committed fixtures + a second Database
 * connection standing in for the racing admin) — no lock-wait timing.
 */
#[Group('nonparallel')]
final class AdminBoardDeleteConcurrencyTest extends TestCase
{
    private bool $committedFixtures = false;

    protected function tearDown(): void
    {
        if ($this->committedFixtures) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $preserve = [
                'schema_migrations', 'badges', 'roles', 'identity_providers', 'provider_aliases',
                'capabilities', 'role_capabilities', 'theme_state',
            ];
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            foreach ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
                if (!in_array($table, $preserve, true)) {
                    $this->pdo->exec('TRUNCATE TABLE `' . str_replace('`', '', (string) $table) . '`');
                }
            }
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1');
            $this->committedFixtures = false;
        }
        parent::tearDown();
    }

    private function useCommittedFixtures(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->commit();
        }
        $this->committedFixtures = true;
    }

    public function test_destination_archived_after_the_preview_is_refused_by_the_locked_revalidation(): void
    {
        $admin = $this->makeAdmin(['username' => 'raceadmin']);
        $category = $this->makeCategory('Race');
        $src = $this->makeBoard($category, ['name' => 'Race Source']);
        $dest = $this->makeBoard($category, ['name' => 'Race Destination']);
        // A second unarchived candidate so the 422 re-render surfaces the
        // archived-destination error (with no alternative at all it would
        // truthfully switch to the "blocked" banner instead).
        $this->makeBoard($category, ['name' => 'Race Bystander']);
        $author = $this->makeUser();
        $this->makeThread($src, $author, 'Race cargo', 'Body.');
        $this->actingAs($admin);

        // The preview offers the destination while it is still unarchived.
        $confirm = $this->get('/admin/boards/' . (int) $src['id'] . '/delete');
        $this->assertStatus(200, $confirm);
        $this->assertSeeText($confirm, 'Race Destination');
        $this->useCommittedFixtures();

        // A second connection commits the archive AFTER the preview offered it.
        $adminDb = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $adminDb->run('UPDATE boards SET is_archived = 1 WHERE id = ?', [(int) $dest['id']]);

        $res = $this->post('/admin/boards/' . (int) $src['id'] . '/delete', [
            'confirm' => (string) $src['slug'],
            'move_to_board_id' => (string) (int) $dest['id'],
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'archived (read-only)');
        // Nothing moved, nothing deleted, nothing audited.
        self::assertSame(1, (int) $adminDb->fetchValue('SELECT COUNT(*) FROM boards WHERE id = ?', [(int) $src['id']]));
        self::assertSame(1, (int) $adminDb->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [(int) $src['id']]));
        self::assertSame(0, (int) $adminDb->fetchValue('SELECT COUNT(*) FROM threads WHERE board_id = ?', [(int) $dest['id']]));
        $destRow = $adminDb->fetch('SELECT thread_count, post_count, is_archived FROM boards WHERE id = ?', [(int) $dest['id']]);
        self::assertSame(0, (int) $destRow['thread_count']);
        self::assertSame(0, (int) $destRow['post_count']);
        self::assertSame(1, (int) $destRow['is_archived']);
        self::assertSame(
            0,
            (int) $adminDb->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action IN ('move_board_content', 'delete_board')"),
        );
    }
}
