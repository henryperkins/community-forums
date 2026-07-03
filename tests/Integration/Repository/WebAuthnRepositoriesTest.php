<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebAuthnChallengeRepository;
use App\Repository\WebAuthnCredentialRepository;
use Tests\Support\TestCase;

final class WebAuthnRepositoriesTest extends TestCase
{
    public function test_challenge_is_consumed_exactly_once_with_matching_binding(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnChallengeRepository($this->db);
        $challenge = random_bytes(32);
        $binding = hash('sha256', 'session-a');

        $repo->mint((int) $user['id'], $binding, 'register', $challenge, 300);
        self::assertFalse($repo->consume($challenge, $binding, 'login', (int) $user['id']), 'purpose mismatch');
        self::assertFalse($repo->consume($challenge, hash('sha256', 'other'), 'register', (int) $user['id']), 'session mismatch');
        self::assertFalse($repo->consume($challenge, $binding, 'register', (int) $user['id'] + 1), 'user mismatch');
        self::assertTrue($repo->consume($challenge, $binding, 'register', (int) $user['id']));
        self::assertFalse($repo->consume($challenge, $binding, 'register', (int) $user['id']), 'replay must fail');
    }

    public function test_expired_challenges_never_consume_and_purge_deletes_stale_rows(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnChallengeRepository($this->db);
        $challenge = random_bytes(32);
        $binding = hash('sha256', 'session-b');
        $repo->mint((int) $user['id'], $binding, 'login', $challenge, -90000);
        self::assertFalse($repo->consume($challenge, $binding, 'login', (int) $user['id']));
        self::assertSame(1, $repo->purgeExpired());
    }

    public function test_credential_lifecycle_create_find_rename_use_revoke(): void
    {
        $user = $this->makeUser();
        $repo = new WebAuthnCredentialRepository($this->db);
        $rawId = random_bytes(32);
        $id = $repo->create([
            'user_id' => (int) $user['id'],
            'credential_id' => $rawId,
            'public_key' => "\xa1\x01\x02",
            'sign_count' => 0,
            'aaguid' => str_repeat("\xAA", 16),
            'transports' => 'internal',
            'is_discoverable' => 1,
            'is_backup_eligible' => 1,
            'is_backed_up' => 0,
            'nickname' => 'Laptop',
        ]);

        self::assertSame(1, $repo->countActiveForUser((int) $user['id']));
        self::assertSame($rawId, $repo->findActiveByCredentialId($rawId)['credential_id']);
        self::assertTrue($repo->rename((int) $user['id'], $id, 'Work laptop'));
        $repo->updateOnUse($id, 7);
        $row = $repo->findForUser((int) $user['id'], $id);
        self::assertIsArray($row);
        self::assertSame('Work laptop', $row['nickname']);
        self::assertSame(7, (int) $row['sign_count']);
        self::assertNotNull($row['last_used_at']);

        $repo->updateOnUse($id, 3);
        $lowered = $repo->findForUser((int) $user['id'], $id);
        self::assertIsArray($lowered);
        self::assertSame(7, (int) $lowered['sign_count'], 'counter anomalies must not lower the stored high-water mark');

        self::assertTrue($repo->revoke((int) $user['id'], $id));
        self::assertFalse($repo->revoke((int) $user['id'], $id), 'second revoke is a no-op');
        self::assertNull($repo->findActiveByCredentialId($rawId));
        self::assertSame([], $repo->activeForUser((int) $user['id']));
    }
}
