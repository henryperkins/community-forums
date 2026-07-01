<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\ProtectedOwnerRepository;
use App\Service\RepairService;
use Tests\Support\TestCase;

final class RepairProtectedOwnersTest extends TestCase
{
    public function test_designates_an_owner_when_admins_exist_but_none_designated(): void
    {
        $admin = $this->makeAdmin(['username' => 'repair_admin']);
        $repo = new ProtectedOwnerRepository($this->db);
        self::assertFalse($repo->hasAnyActiveOwner());

        $changed = (new RepairService($this->db))->repairProtectedOwners();
        self::assertSame(1, $changed);
        self::assertTrue($repo->isActiveOwner((int) $admin['id']));
    }

    public function test_is_idempotent_once_an_owner_exists(): void
    {
        $this->makeAdmin(['username' => 'repair_admin2']);
        $svc = new RepairService($this->db);
        self::assertSame(1, $svc->repairProtectedOwners());
        self::assertSame(0, $svc->repairProtectedOwners(), 'second pass is a no-op');
    }

    public function test_no_op_when_no_active_admin_exists(): void
    {
        // Non-admins present, no admin — nothing to designate.
        $this->makeUser(['username' => 'repair_plain']);
        self::assertSame(0, (new RepairService($this->db))->repairProtectedOwners());
        self::assertFalse((new ProtectedOwnerRepository($this->db))->hasAnyActiveOwner());
    }

    public function test_repair_all_includes_protected_owners(): void
    {
        $this->makeAdmin(['username' => 'repair_all_admin']);
        $out = (new RepairService($this->db))->repairAll();
        self::assertArrayHasKey('protected_owners', $out);
    }
}
