<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use Tests\Support\TestCase;

final class StructureOrderingRepoTest extends TestCase
{
    public function test_category_setPositions_densely_renumbers_in_submitted_order(): void
    {
        $c1 = $this->makeCategory('C1');
        $c2 = $this->makeCategory('C2');
        $c3 = $this->makeCategory('C3');

        (new CategoryRepository($this->db))->setPositions([$c3, $c1, $c2]);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c3]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c1]));
        self::assertSame(2, (int) $this->db->fetchValue('SELECT position FROM categories WHERE id = ?', [$c2]));
    }

    public function test_board_setPositions_is_scoped_to_a_category(): void
    {
        $cat = $this->makeCategory();
        $b1 = $this->makeBoard($cat, ['slug' => 'sp1']);
        $b2 = $this->makeBoard($cat, ['slug' => 'sp2']);

        (new BoardRepository($this->db))->setPositions($cat, [(int) $b2['id'], (int) $b1['id']]);

        self::assertSame(0, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b2['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b1['id']]));
    }

    public function test_board_setArchived_toggles_the_flag_without_touching_counters(): void
    {
        $cat = $this->makeCategory();
        $b = $this->makeBoard($cat, ['slug' => 'arc-prim']);
        $repo = new BoardRepository($this->db);

        $repo->setArchived((int) $b['id'], true);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_archived FROM boards WHERE id = ?', [(int) $b['id']]));

        $repo->setArchived((int) $b['id'], false);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_archived FROM boards WHERE id = ?', [(int) $b['id']]));
    }

    public function test_board_update_writes_position_when_supplied(): void
    {
        $cat = $this->makeCategory();
        $b = $this->makeBoard($cat, ['slug' => 'upd-pos', 'name' => 'UpdPos']);

        (new BoardRepository($this->db))->update((int) $b['id'], [
            'category_id' => $cat,
            'slug' => 'upd-pos',
            'name' => 'UpdPos',
            'description' => null,
            'visibility' => 'public',
            'post_min_role' => 'user',
            'position' => 7,
        ]);

        self::assertSame(7, (int) $this->db->fetchValue('SELECT position FROM boards WHERE id = ?', [(int) $b['id']]));
    }
}
