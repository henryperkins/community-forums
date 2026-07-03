<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\FeatureFlags;
use App\Core\SecretNotFoundException;
use App\Core\SecretRevokedException;
use App\Repository\ModerationLogRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Security\SecretBox;
use App\Service\SecretVault;
use RuntimeException;
use Tests\Support\TestCase;

/**
 * SP0 adversarial redaction corpus for the service_secrets seam (SLICE-SERVICE-SECRETS).
 * Baseline round-trips live in SecretVaultTest; this file is the leak-proof proof that
 * lands TM-SE-02 (a config read after save exposes no plaintext), TM-SE-04 (a revoked /
 * forged reference fails closed while the lifecycle is audited), and TM-SE-05 (a forced
 * vault failure surfaces only the svcsec_ reference, never plaintext).
 *
 * No production code changes: a RED here is a real disclosure defect in SecretVault -
 * debug the vault, never weaken the assertion.
 */
final class SecretVaultRedactionTest extends TestCase
{
    private const KEY_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const KEY_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private function vault(string $key = self::KEY_A, bool $enabled = true): SecretVault
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => $enabled]);
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            new SecretBox($key),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    /** @return list<string> every before|after audit blob for this secret */
    private function auditBlobs(int $secretId): array
    {
        $rows = $this->db->fetchAll(
            "SELECT before_json, after_json FROM moderation_log
             WHERE target_type = 'service_secret' AND target_id = ?",
            [$secretId],
        );
        return array_map(
            static fn (array $r): string => (string) ($r['before_json'] ?? '') . '|' . (string) ($r['after_json'] ?? ''),
            $rows,
        );
    }

    public function test_config_read_after_save_and_rotate_exposes_no_plaintext_anywhere(): void // TM-SE-02
    {
        $v = $this->vault();
        $ref = $v->store('provider', 42, 'oauth client secret', 'PLAINTEXT-V1-2a9f');
        $v->rotate($ref, 'PLAINTEXT-V2-7c31');

        $meta = $v->metadata($ref);
        $metaJson = json_encode($meta) ?: '';
        self::assertSame($ref, $meta['ref']);
        self::assertSame('oauth client secret', $meta['label']);
        self::assertStringNotContainsString('PLAINTEXT-V1-2a9f', $metaJson);
        self::assertStringNotContainsString('PLAINTEXT-V2-7c31', $metaJson);
        self::assertArrayNotHasKey('plaintext', $meta);
        self::assertArrayNotHasKey('ciphertext', $meta);

        // Sweep the entire lifecycle audit trail, destroyed rows included.
        $v->revoke($ref);
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        $this->db->run(
            "UPDATE service_secret_versions SET retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 HOUR)
             WHERE secret_id = ? AND state = 'retired'",
            [$id],
        );
        self::assertGreaterThan(0, $v->prune(100));

        $blobs = $this->auditBlobs($id);
        self::assertNotEmpty($blobs, 'store/rotate/revoke/destroy must all audit');
        foreach ($blobs as $blob) {
            self::assertStringNotContainsString('PLAINTEXT-V1-2a9f', $blob);
            self::assertStringNotContainsString('PLAINTEXT-V2-7c31', $blob);
        }
        self::assertStringContainsString($ref, implode('', $blobs), 'audit references the secret by its svcsec_ ref, not plaintext');
    }

    public function test_forced_vault_failure_yields_reference_only_never_plaintext(): void // TM-SE-05
    {
        $ref = $this->vault(self::KEY_A)->store('provider', 7, 'client secret', 'PLAINTEXT-DR-91b4');

        // Master key unavailable / rotated-away (a DR / vault-failure scenario): decrypt cannot succeed.
        $degraded = $this->vault(self::KEY_B);

        // The reference + metadata still resolve - that is ALL a failed vault may surface.
        $meta = $degraded->metadata($ref);
        self::assertSame($ref, $meta['ref']);
        self::assertStringStartsWith('svcsec_', $meta['ref']);
        self::assertStringNotContainsString('PLAINTEXT-DR-91b4', json_encode($meta) ?: '');

        try {
            $degraded->reveal($ref);
            self::fail('a wrong-key reveal must fail closed');
        } catch (RuntimeException $e) {
            self::assertStringNotContainsString('PLAINTEXT-DR-91b4', $e->getMessage());
        }
    }

    public function test_revoked_or_forged_reference_fails_closed_and_revoke_is_audited(): void // TM-SE-04
    {
        $v = $this->vault();
        $ref = $v->store('provider', 3, 'to be cut off', 'PLAINTEXT-CUT-5d2e');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);

        // Revoking is how a consumer is cut off; it is an audited action carrying no plaintext.
        $v->revoke($ref);
        $revokeAudits = $this->db->fetchAll(
            "SELECT after_json FROM moderation_log
             WHERE target_type = 'service_secret' AND target_id = ? AND action = 'service_secret_revoked'",
            [$id],
        );
        self::assertCount(1, $revokeAudits, 'revoke writes exactly one audit row');
        self::assertStringNotContainsString('PLAINTEXT-CUT-5d2e', (string) $revokeAudits[0]['after_json']);

        // A now-non-owning consumer presenting the revoked reference is denied.
        try {
            $v->reveal($ref);
            self::fail('a revoked reference must not reveal');
        } catch (SecretRevokedException $e) {
            self::assertStringNotContainsString('PLAINTEXT-CUT-5d2e', $e->getMessage());
        }

        // A forged / never-owned reference is denied without disclosing anything.
        $this->expectException(SecretNotFoundException::class);
        $v->reveal('svcsec_' . str_repeat('0', 32));
    }

    public function test_revoke_then_prune_zeroes_both_versions_ciphertext(): void // redaction / prune
    {
        $v = $this->vault();
        $ref = $v->store('generic', null, 'rotated then revoked', 'PLAINTEXT-Z1-11aa');
        $v->rotate($ref, 'PLAINTEXT-Z2-22bb');
        $id = (int) $this->db->fetchValue('SELECT id FROM service_secrets WHERE secret_ref = ?', [$ref]);
        self::assertSame(2, (int) $this->db->fetchValue('SELECT COUNT(*) FROM service_secret_versions WHERE secret_id = ?', [$id]));

        // Revoke retires every version immediately; prune must then destroy them all.
        $v->revoke($ref);
        self::assertSame(2, $v->prune(100), 'both versions become prunable on revoke');

        $rows = $this->db->fetchAll(
            'SELECT state, destroyed_at, LENGTH(ciphertext) AS cl, LENGTH(nonce) AS nl, LENGTH(tag) AS tl
             FROM service_secret_versions WHERE secret_id = ? ORDER BY version',
            [$id],
        );
        self::assertCount(2, $rows);
        foreach ($rows as $row) {
            self::assertSame('destroyed', (string) $row['state']);
            self::assertNotNull($row['destroyed_at']);
            self::assertSame(0, (int) $row['cl'], 'ciphertext zeroed');
            self::assertSame(0, (int) $row['nl'], 'nonce zeroed');
            self::assertSame(0, (int) $row['tl'], 'tag zeroed');
        }
    }
}
