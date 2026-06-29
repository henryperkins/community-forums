<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Service\AdminService;
use Tests\Support\TestCase;

final class AppAdminStructureReorderTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $user;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->user = $this->makeUser(['username' => 'plain']);
        $this->categoryId = $this->makeCategory('General');
    }

    private function adminService(): AdminService
    {
        return new AdminService(
            $this->db,
            new CategoryRepository($this->db),
            new BoardRepository($this->db),
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            new WriteGate(),
            new UserRepository($this->db),
            new BoardModeratorRepository($this->db),
            new BoardMemberRepository($this->db),
        );
    }

    public function test_reorder_boards_densely_renumbers_and_audits(): void
    {
        $admin = $this->userEntity($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'r1', 'name' => 'R1']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'r2', 'name' => 'R2']);
        $b3 = $this->makeBoard($this->categoryId, ['slug' => 'r3', 'name' => 'R3']);

        $this->adminService()->reorderBoards($admin, $this->categoryId, [(int) $b3['id'], (int) $b1['id'], (int) $b2['id']]);

        self::assertSame(0, (int) $this->boards()->find((int) $b3['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(2, (int) $this->boards()->find((int) $b2['id'])['position']);
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_boards' AND target_type = 'board'",
        ));
    }

    public function test_reorder_with_foreign_id_is_rejected_and_order_unchanged(): void
    {
        $admin = $this->userEntity($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'f1', 'name' => 'F1']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'f2', 'name' => 'F2']);

        $threw = false;
        try {
            $this->adminService()->reorderBoards($admin, $this->categoryId, [(int) $b2['id'], 999999]);
        } catch (ValidationException) {
            $threw = true;
        }
        self::assertTrue($threw, 'a foreign id must be rejected');
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_move_top_category_up_is_a_safe_noop(): void
    {
        $admin = $this->userEntity($this->admin);
        $catB = $this->makeCategory('Second');

        $this->adminService()->moveCategory($admin, $this->categoryId, 'up'); // already at top

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$this->categoryId]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$catB]));
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_categories'"));
    }

    public function test_updateBoard_appends_at_destination_when_category_changes(): void
    {
        $admin = $this->userEntity($this->admin);
        $catB = $this->makeCategory('Dest');
        $existing = $this->makeBoard($catB, ['slug' => 'dx', 'name' => 'DestExisting']); // pos 0 in catB
        $mover = $this->makeBoard($this->categoryId, ['slug' => 'mv', 'name' => 'Mover']); // pos 0 in catA

        $this->adminService()->updateBoard($admin, (int) $mover['id'], [
            'category_id' => $catB,
            'name' => 'Mover',
            'slug' => 'mv',
            'visibility' => 'public',
        ]);

        $row = $this->boards()->find((int) $mover['id']);
        self::assertSame($catB, (int) $row['category_id']);
        self::assertSame(1, (int) $row['position'], 'appended after the existing board (no collision)');
        self::assertSame(0, (int) $this->boards()->find((int) $existing['id'])['position']);
    }

    public function test_archive_and_unarchive_toggle_flag_and_audit(): void
    {
        $admin = $this->userEntity($this->admin);
        $b = $this->makeBoard($this->categoryId, ['slug' => 'svc-arc', 'name' => 'SvcArc']);

        $this->adminService()->archiveBoard($admin, (int) $b['id']);
        self::assertSame(1, (int) $this->boards()->find((int) $b['id'])['is_archived']);

        $this->adminService()->unarchiveBoard($admin, (int) $b['id']);
        self::assertSame(0, (int) $this->boards()->find((int) $b['id'])['is_archived']);

        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'archive_board' AND target_type = 'board'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'unarchive_board' AND target_type = 'board'"));
    }
}
