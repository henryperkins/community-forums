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
}
