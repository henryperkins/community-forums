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

    public function test_move_board_up_swaps_rendered_order_and_audits(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'alpha', 'name' => 'AlphaBoard']); // pos 0
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'beta', 'name' => 'BetaBoard']);   // pos 1
        $this->get('/admin/structure');

        $res = $this->post('/admin/boards/' . $b2['id'] . '/move', ['dir' => 'up']);
        $this->assertRedirectContains($res, '/admin/structure');

        self::assertSame(0, (int) $this->boards()->find((int) $b2['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b1['id'])['position']);

        $body = $this->get('/admin/structure')->body();
        self::assertLessThan(strpos($body, 'AlphaBoard'), strpos($body, 'BetaBoard'), 'Beta now renders before Alpha');
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'reorder_boards'"));
    }

    public function test_move_top_board_up_is_safe_noop_over_http(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'top', 'name' => 'TopBoard']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'bot', 'name' => 'BotBoard']);
        $this->get('/admin/structure');

        $res = $this->post('/admin/boards/' . $b1['id'] . '/move', ['dir' => 'up']);
        $this->assertRedirectContains($res, '/admin/structure');
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_move_with_unknown_direction_is_422_and_order_unchanged(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'dir-one', 'name' => 'DirOne']); // pos 0
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'dir-two', 'name' => 'DirTwo']); // pos 1
        $this->get('/admin/structure');

        // Unvalidated direction used to fall through to "down" and really move
        // the board (round-2 audit finding 6).
        $res = $this->post('/admin/boards/' . $b1['id'] . '/move', ['dir' => 'sideways']);
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_move_category_with_unknown_direction_is_422(): void
    {
        $this->actingAs($this->admin);
        $this->makeCategory('SecondCat');
        $this->get('/admin/structure');
        $this->assertStatus(422, $this->post('/admin/categories/' . $this->categoryId . '/move', ['dir' => 'diagonal']));
    }

    public function test_bulk_reorder_with_foreign_id_is_422_and_order_unchanged(): void
    {
        $this->actingAs($this->admin);
        $b1 = $this->makeBoard($this->categoryId, ['slug' => 'one', 'name' => 'OneBoard']);
        $b2 = $this->makeBoard($this->categoryId, ['slug' => 'two', 'name' => 'TwoBoard']);
        $this->get('/admin/structure');

        $res = $this->post('/admin/structure/reorder', [
            'scope' => 'board',
            'category_id' => $this->categoryId,
            'ids' => [(int) $b2['id'], 999999],
        ]);
        $this->assertStatus(422, $res);
        self::assertSame(0, (int) $this->boards()->find((int) $b1['id'])['position']);
        self::assertSame(1, (int) $this->boards()->find((int) $b2['id'])['position']);
    }

    public function test_structure_mutations_require_admin(): void
    {
        $b = $this->makeBoard($this->categoryId, ['slug' => 'guard', 'name' => 'GuardBoard']);

        $this->actingAs($this->user);
        $this->get('/');
        $this->assertStatus(403, $this->post('/admin/boards/' . $b['id'] . '/move', ['dir' => 'up']));
        $this->assertStatus(403, $this->post('/admin/boards/' . $b['id'] . '/archive'));

        $this->logoutClient();
        $this->get('/');
        $this->assertRedirectContains(
            $this->post('/admin/structure/reorder', ['scope' => 'category', 'ids' => [(int) $this->categoryId]]),
            '/login',
        );
    }
}
