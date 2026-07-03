<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Repository\PackagePublisherRepository;
use App\Repository\PublisherSigningKeyRepository;
use Tests\Support\TestCase;

final class PublisherSigningKeyRepositoryTest extends TestCase
{
    public function test_pin_defaults_ed25519_active_and_is_findable_by_key_id(): void
    {
        $repo = new PublisherSigningKeyRepository($this->db);
        $publisher = (new PackagePublisherRepository($this->db))->ensure('acme.tools', 'Acme Tools');

        $id = $repo->pin($publisher, 'key-1', str_repeat("\x01", 32), null, null);
        $row = $repo->find($id);

        self::assertSame('ed25519', $row['algorithm']);
        self::assertSame('active', $row['status']);
        self::assertSame($id, (int) $repo->findKey($publisher, 'key-1')['id']);
    }

    public function test_rotate_and_revoke_transition_status(): void
    {
        $repo = new PublisherSigningKeyRepository($this->db);
        $publisher = (new PackagePublisherRepository($this->db))->ensure('acme.tools', 'Acme Tools');
        $old = $repo->pin($publisher, 'key-1', str_repeat("\x01", 32), null, null);
        $new = $repo->pin($publisher, 'key-2', str_repeat("\x02", 32), null, null);

        $repo->markRotated($old);
        self::assertSame('rotated', $repo->find($old)['status']);
        self::assertNotNull($repo->find($old)['valid_until']);

        $repo->revoke($new, 'compromised');
        self::assertSame('revoked', $repo->find($new)['status']);
        self::assertSame('compromised', $repo->find($new)['revoked_reason']);

        // forPublisher lists newest first.
        self::assertSame([$new, $old], array_map(static fn (array $r): int => (int) $r['id'], $repo->forPublisher($publisher)));
    }
}
