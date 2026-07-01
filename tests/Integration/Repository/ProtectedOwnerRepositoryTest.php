<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\ProtectedOwnerRepository;
use Tests\Support\TestCase;

final class ProtectedOwnerRepositoryTest extends TestCase
{
    public function test_designate_and_query_active_owners(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_a'])['id'];
        $b = (int) $this->makeAdmin(['username' => 'owner_b'])['id'];

        self::assertFalse($repo->hasAnyActiveOwner());
        self::assertFalse($repo->isActiveOwner($a));

        self::assertTrue($repo->designate($a, null));
        self::assertTrue($repo->hasAnyActiveOwner());
        self::assertTrue($repo->isActiveOwner($a));
        self::assertFalse($repo->isActiveOwner($b));

        // With only A designated, excluding A leaves zero other active owners.
        self::assertSame(0, $repo->activeOwnerCountExcluding($a));

        $repo->designate($b, $a);
        self::assertSame(1, $repo->activeOwnerCountExcluding($a));
    }

    public function test_designate_is_idempotent_on_the_unique_user(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_dup'])['id'];

        self::assertTrue($repo->designate($a, null));
        self::assertFalse($repo->designate($a, null), 'second designation is a no-op (INSERT IGNORE)');
        self::assertSame(0, $repo->activeOwnerCountExcluding($a));
    }

    public function test_designate_or_reactivate_revives_an_inactive_owner_row(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_reactivate'])['id'];
        self::assertTrue($repo->designate($a, null));
        $this->db->run('UPDATE protected_owners SET is_active = 0 WHERE user_id = ?', [$a]);
        self::assertFalse($repo->isActiveOwner($a));

        self::assertTrue($repo->designateOrReactivate($a, null));
        self::assertTrue($repo->isActiveOwner($a));
    }

    public function test_locked_active_owner_ids_exclude_inactive_accounts(): void
    {
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_locked_live'])['id'];
        $b = (int) $this->makeAdmin(['username' => 'owner_locked_gone'])['id'];
        $repo->designate($a, null);
        $repo->designate($b, $a);
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [$b]);

        self::assertSame([$a], $repo->activeOwnerIdsForUpdate());
    }

    public function test_owner_whose_account_is_not_active_is_not_a_recoverable_owner(): void
    {
        // Regression: `protected_owners.is_active` is a write-once flag that no
        // path ever clears, and `users.status` gained deactivated/pending_deletion/
        // deleted values (migration 0059). Owner "activeness" must derive from the
        // account status, not the stale flag — otherwise a deactivated co-owner is
        // counted as a live safety net that does not exist.
        $repo = new ProtectedOwnerRepository($this->db);
        $a = (int) $this->makeAdmin(['username' => 'owner_live'])['id'];
        $b = (int) $this->makeAdmin(['username' => 'owner_gone'])['id'];
        $repo->designate($a, null);
        $repo->designate($b, $a);
        self::assertSame(1, $repo->activeOwnerCountExcluding($a));

        // B deactivates: the stale row must stop counting as a recoverable owner.
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [$b]);
        self::assertFalse($repo->isActiveOwner($b), 'a deactivated account is not an active owner');
        self::assertSame(0, $repo->activeOwnerCountExcluding($a), 'deactivated co-owner must not be counted');
        self::assertTrue($repo->hasAnyActiveOwner(), 'A is still an active owner');

        // Once A also leaves, no recoverable owner remains.
        $this->db->run("UPDATE users SET status = 'deactivated' WHERE id = ?", [$a]);
        self::assertFalse($repo->hasAnyActiveOwner(), 'no active-status owner remains');
    }
}
