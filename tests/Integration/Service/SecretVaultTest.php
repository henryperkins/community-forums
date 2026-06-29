<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretRevokedException;
use App\Core\SecretsDisabledException;
use App\Core\ValidationException;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use Tests\Support\TestCase;

final class SecretVaultTest extends TestCase
{
    private function vault(bool $enabled = true): SecretVault
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => $enabled]);
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox(str_repeat('a', 64)),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    public function test_store_then_reveal_round_trips(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'a label', 'PLAINTEXT-AAA-9f3');
        self::assertStringStartsWith('svcsec_', $ref);
        self::assertSame('PLAINTEXT-AAA-9f3', $v->reveal($ref));
    }

    public function test_ciphertext_at_rest_is_not_the_plaintext(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'l', 'PLAINTEXT-AAA-9f3');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $cipher = (string) $this->db->fetchValue('SELECT ciphertext FROM service_secret_versions WHERE secret_id = ?', [$id]);
        self::assertGreaterThan(0, strlen($cipher));
        self::assertStringNotContainsString('PLAINTEXT-AAA-9f3', $cipher);
    }

    public function test_unknown_ref_reveal_throws_not_found(): void
    {
        $this->expectException(SecretNotFoundException::class);
        $this->vault()->reveal('svcsec_does_not_exist');
    }

    public function test_oversize_plaintext_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $max = (int) $this->config->get('secrets.max_secret_bytes', 4096);
        $this->vault()->store('generic', null, 'big', str_repeat('x', $max + 1));
    }

    public function test_two_stores_yield_distinct_refs(): void
    {
        $v = $this->vault();
        self::assertNotSame(
            $v->store('generic', null, 'one', 's1'),
            $v->store('generic', null, 'two', 's2'),
        );
    }

    public function test_store_is_blocked_when_flag_dark(): void
    {
        $this->expectException(SecretsDisabledException::class);
        $this->vault(false)->store('generic', null, 'l', 's');
    }

    public function test_reveal_still_works_when_flag_dark(): void
    {
        $ref = $this->vault(true)->store('generic', null, 'l', 'KEEP-READABLE-BBB');
        self::assertSame('KEEP-READABLE-BBB', $this->vault(false)->reveal($ref));
    }

    public function test_rotate_reveals_new_and_keeps_old_within_grace(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rot', 'old-secret');
        $version = $v->rotate($ref, 'new-secret');
        self::assertSame(2, $version);
        self::assertSame('new-secret', $v->reveal($ref));
        self::assertSame(['new-secret', 'old-secret'], $v->usableSecrets($ref), 'current + in-grace retired, newest first');
    }

    public function test_sequential_rotations_are_deterministic_and_single_current(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rot', 'v1-secret');
        for ($i = 2; $i <= 4; $i++) {
            self::assertSame($i, $v->rotate($ref, "v{$i}-secret"));
            $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
            self::assertSame(
                1,
                (int) $this->db->fetchValue("SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ? AND state = 'current'", [$id]),
                'exactly one current version after each rotation',
            );
        }
        self::assertSame('v4-secret', $v->reveal($ref));
    }

    public function test_metadata_reports_status_without_plaintext(): void
    {
        $v = $this->vault();
        $ref = $v->store('provider', 7, 'client secret', 'SENSITIVE-CCC');
        $v->rotate($ref, 'SENSITIVE-DDD');
        $meta = $v->metadata($ref);
        self::assertSame('active', $meta['status']);
        self::assertSame(2, $meta['latest_version']);
        self::assertTrue($meta['has_live_version']);
        self::assertSame('provider', $meta['owner_type']);
        self::assertSame(7, $meta['owner_id']);
        self::assertSame('client secret', $meta['label']);
        self::assertStringNotContainsString('SENSITIVE', json_encode($meta) ?: '');
    }

    public function test_revoke_blocks_reads_and_marks_metadata(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rev', 'to-be-revoked');
        $v->revoke($ref);
        self::assertSame('revoked', $v->metadata($ref)['status']);
        $this->expectException(SecretRevokedException::class);
        $v->reveal($ref);
    }

    public function test_revoke_works_when_flag_dark(): void
    {
        $ref = $this->vault(true)->store('generic', null, 'rev', 'secret');
        $this->vault(false)->revoke($ref);
        self::assertSame('revoked', $this->vault(false)->metadata($ref)['status']);
    }

    public function test_prune_destroys_expired_retired_version_fully(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'g', 'old-secret');
        $v->rotate($ref, 'new-secret');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);

        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );

        self::assertSame(['new-secret'], $v->usableSecrets($ref));
        self::assertGreaterThan(
            0,
            (int) $this->db->fetchValue('SELECT LENGTH(ciphertext) FROM service_secret_versions WHERE secret_id = ? AND version = 1', [$id]),
        );

        self::assertSame(1, $v->prune(100));
        $row = $this->db->fetch(
            'SELECT state, destroyed_at, LENGTH(ciphertext) AS cl, LENGTH(nonce) AS nl, LENGTH(tag) AS tl
             FROM service_secret_versions WHERE secret_id = ? AND version = 1',
            [$id],
        );
        self::assertNotNull($row, 'destroyed version row is retained for audit history');
        self::assertSame('destroyed', $row['state']);
        self::assertNotNull($row['destroyed_at']);
        self::assertSame(0, (int) $row['cl']);
        self::assertSame(0, (int) $row['nl']);
        self::assertSame(0, (int) $row['tl']);
    }

    public function test_prune_is_idempotent_across_overlapping_runs(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'p', 'v1');
        $v->rotate($ref, 'v2');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );

        self::assertSame(1, $v->prune(100));
        $auditAfterFirst = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'service_secret' AND action = 'service_secret_version_destroyed' AND target_id = ?",
            [$id],
        );
        self::assertSame(0, $v->prune(100));
        self::assertSame(
            $auditAfterFirst,
            (int) $this->db->fetchValue(
                "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'service_secret' AND action = 'service_secret_version_destroyed' AND target_id = ?",
                [$id],
            ),
            'a second prune must not double-audit',
        );
    }

    public function test_no_plaintext_leaks_into_audit(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'label only', 'PLAINTEXT-AAA-9f3');
        $v->rotate($ref, 'PLAINTEXT-BBB-7c1');
        $v->revoke($ref);
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $rows = $this->db->fetchAll(
            "SELECT before_json, after_json FROM moderation_log WHERE target_type = 'service_secret' AND target_id = ?",
            [$id],
        );
        self::assertNotEmpty($rows);
        foreach ($rows as $row) {
            $blob = (string) ($row['before_json'] ?? '') . (string) ($row['after_json'] ?? '');
            self::assertStringNotContainsString('PLAINTEXT-AAA-9f3', $blob);
            self::assertStringNotContainsString('PLAINTEXT-BBB-7c1', $blob);
        }
    }

    public function test_no_plaintext_leaks_into_exception_messages(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'l', 'SECRET-IN-MSG-EEE');
        $v->revoke($ref);
        try {
            $v->reveal($ref);
            self::fail('expected SecretRevokedException');
        } catch (SecretRevokedException $e) {
            self::assertStringNotContainsString('SECRET-IN-MSG-EEE', $e->getMessage());
        }
    }

    public function test_revoke_makes_versions_immediately_prunable(): void
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rev', 'doomed-secret');
        $v->revoke($ref);
        self::assertSame(1, $v->prune(100));
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        self::assertSame(
            'destroyed',
            (string) $this->db->fetchValue("SELECT state FROM service_secret_versions WHERE secret_id = ? AND version = 1", [$id]),
        );
    }
}
