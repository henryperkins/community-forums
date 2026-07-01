<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\ValidationException;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\LastOwnerGuard;
use Tests\Support\TestCase;

final class AppProtectedOwnerTest extends TestCase
{
    private function guard(): LastOwnerGuard
    {
        return new LastOwnerGuard(new ProtectedOwnerRepository($this->db), new UserRepository($this->db));
    }

    public function test_parity_fallback_blocks_removing_the_last_admin_when_owner_set_is_empty(): void
    {
        // No protected_owners rows -> defer to the legacy last-active-admin rule.
        $onlyAdmin = $this->userEntity($this->makeAdmin(['username' => 'solo_admin']));
        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwner($onlyAdmin, 'current_password');
    }

    public function test_parity_fallback_allows_removal_when_another_active_admin_exists(): void
    {
        $a = $this->userEntity($this->makeAdmin(['username' => 'parity_a']));
        $this->makeAdmin(['username' => 'parity_b']);
        $this->guard()->assertNotLastOwner($a, 'current_password'); // no throw
        $this->addToAssertionCount(1);
    }

    public function test_owner_set_blocks_removing_the_last_active_owner(): void
    {
        $aRow = $this->makeAdmin(['username' => 'owner_only']);
        $this->makeAdmin(['username' => 'admin_not_owner']); // an admin, but NOT a designated owner
        (new ProtectedOwnerRepository($this->db))->designate((int) $aRow['id'], null);

        // Owner set is populated and A is the sole active owner -> blocked, even
        // though another admin exists (owners, not admins, are the authority now).
        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwner($this->userEntity($aRow), 'current_password');
    }

    public function test_owner_set_allows_removal_when_another_active_owner_exists(): void
    {
        $aRow = $this->makeAdmin(['username' => 'owner_a2']);
        $bRow = $this->makeAdmin(['username' => 'owner_b2']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $aRow['id'], null);
        $repo->designate((int) $bRow['id'], (int) $aRow['id']);

        $this->guard()->assertNotLastOwner($this->userEntity($aRow), 'current_password'); // no throw
        $this->addToAssertionCount(1);
    }
}
