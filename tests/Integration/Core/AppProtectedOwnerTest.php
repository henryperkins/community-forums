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

    public function test_owner_set_does_not_count_a_deactivated_co_owner(): void
    {
        // Regression: with two owners designated, if one deactivates their account
        // the guard must treat the remaining owner as the last one. The stale
        // `is_active=1` row must not mask the lockout the guard exists to prevent.
        $aRow = $this->makeAdmin(['username' => 'live_owner']);
        $bRow = $this->makeAdmin(['username' => 'gone_owner']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $aRow['id'], null);
        $repo->designate((int) $bRow['id'], (int) $aRow['id']);
        // B deactivates; only A remains a recoverable owner.
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [(int) $bRow['id']]);

        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwner($this->userEntity($aRow), 'current_password');
    }

    public function test_capabilities_on_blocks_last_owner_when_co_owner_is_deactivated(): void
    {
        // The reachable fail-open: capabilities on, a third active admin (so the
        // legacy last-admin check passes), two designated owners, one of whom has
        // deactivated. Deactivating the sole remaining recoverable owner must 422.
        $this->setFlags(['account_lifecycle' => true, 'capabilities' => true]);
        $a = $this->makeAdmin(['username' => 'wired_live_owner']);
        $b = $this->makeAdmin(['username' => 'wired_gone_owner']);
        $this->makeAdmin(['username' => 'wired_spare_admin']); // keeps legacy check happy
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $a['id'], null);
        $repo->designate((int) $b['id'], (int) $a['id']);
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [(int) $b['id']]);

        $this->actingAs($a);
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    }

    public function test_capabilities_dark_leaves_account_lifecycle_behavior_unchanged(): void
    {
        // With capabilities dark (default), the guard is not wired: the legacy
        // last-admin rule alone governs, so a lone admin is blocked by the
        // existing check — proving Foundation added no new live behavior.
        $this->setFlags(['account_lifecycle' => true]); // capabilities stays dark
        $admin = $this->makeAdmin(['username' => 'dark_lone_admin']);
        $this->actingAs($admin);

        // Sole admin cannot deactivate (legacy assertNotFinalActiveAdmin fires) -> 422.
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    }

    public function test_capabilities_on_enforces_owner_invariant_on_deactivate(): void
    {
        // Two admins (legacy last-admin rule passes), but only A is a designated
        // owner. With capabilities on, LastOwnerGuard blocks A's deactivation.
        $this->setFlags(['account_lifecycle' => true, 'capabilities' => true]);
        $a = $this->makeAdmin(['username' => 'wired_owner_a']);
        $b = $this->makeAdmin(['username' => 'wired_admin_b']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $a['id'], null);

        $this->actingAs($a);
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));

        // Designate B too -> A is no longer the last owner -> allowed (redirect).
        $repo->designate((int) $b['id'], (int) $a['id']);
        $this->assertRedirect($this->post('/settings/account/deactivate', ['current_password' => 'password123']));
    }

    public function test_capabilities_on_enforces_owner_invariant_on_deletion_request(): void
    {
        $this->setFlags(['account_lifecycle' => true, 'capabilities' => true]);
        $a = $this->makeAdmin(['username' => 'delete_owner_a']);
        $b = $this->makeAdmin(['username' => 'delete_owner_b']);
        $repo = new ProtectedOwnerRepository($this->db);
        $repo->designate((int) $a['id'], null);

        $this->actingAs($a);
        $this->assertStatus(422, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));

        $repo->designate((int) $b['id'], (int) $a['id']);
        $this->assertRedirect($this->post('/settings/account/delete/request', ['current_password' => 'password123']));
    }

    public function test_locked_guard_uses_the_same_owner_invariant(): void
    {
        $aRow = $this->makeAdmin(['username' => 'locked_guard_owner']);
        $this->makeAdmin(['username' => 'locked_guard_admin']);
        (new ProtectedOwnerRepository($this->db))->designate((int) $aRow['id'], null);

        $this->expectException(ValidationException::class);
        $this->guard()->assertNotLastOwnerForUpdate($this->userEntity($aRow), 'current_password');
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }
}
