<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Service\AuthService;
use App\Service\InvitationService;
use Tests\Support\TestCase;

/**
 * Invitation lifecycle rules (P5-13 / Inc 9). Covers issuance validation,
 * token entropy + hash-only storage (TM-IN-06's at-rest half), audit rows,
 * revoke idempotency, uniform preview (TM-IN-01's uniformity half), and — in
 * the redemption section — the TM-IN-02/03/04/05 negative fixtures.
 *
 * NOTE on transactions: the harness wraps each test in ONE rollback
 * transaction with no savepoints, so `Database::transaction()` joins it and
 * an inner throw does NOT undo rows here. Redemption is ordered
 * validate → bind → consume → register precisely so the pre-consume failure
 * tests can assert state inside the harness transaction; the real
 * commit/rollback atomicity test uses the own-transaction pattern
 * (PackageInstallBudgetTest precedent).
 */
final class InvitationServiceTest extends TestCase
{
    private function service(): InvitationService
    {
        return new InvitationService(
            $this->db,
            new InvitationRepository($this->db),
            new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config),
            new BoardRepository($this->db),
            new BoardMemberRepository($this->db),
            new ModerationLogRepository($this->db),
        );
    }

    private function adminEntity(): User
    {
        $admin = $this->makeAdmin();
        $entity = $this->users()->findEntity((int) $admin['id']);
        self::assertNotNull($entity);
        return $entity;
    }

    /** @return array<string,mixed> */
    private function row(int $id): array
    {
        $row = $this->db->fetch('SELECT * FROM invitations WHERE id = ?', [$id]);
        self::assertNotNull($row);
        return $row;
    }

    // ---- issuance ----------------------------------------------------------

    public function test_create_returns_64_hex_token_and_stores_only_its_sha256(): void
    {
        $result = $this->service()->create($this->adminEntity(), []);

        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $result['token']);

        $row = $this->row($result['id']);
        self::assertSame(hash('sha256', $result['token']), $row['token_hash']);
        foreach ($row as $column => $value) {
            if (is_string($value)) {
                self::assertStringNotContainsString($result['token'], $value, "raw token leaked into invitations.$column");
            }
        }
    }

    public function test_tokens_are_unique_across_creates(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();
        $tokens = [];
        for ($i = 0; $i < 5; $i++) {
            $tokens[] = $service->create($admin, [])['token'];
        }
        self::assertCount(5, array_unique($tokens));
    }

    public function test_create_validates_bindings_bounds_and_board(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();

        try {
            $service->create($admin, ['email' => 'a@example.test', 'domain' => 'example.test']);
            self::fail('email+domain together must be rejected');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('domain', $e->errors);
        }

        try {
            $service->create($admin, ['email' => 'not-an-email']);
            self::fail('bad email must be rejected');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('email', $e->errors);
        }

        try {
            $service->create($admin, ['domain' => 'not a domain']);
            self::fail('bad domain must be rejected');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('domain', $e->errors);
        }

        foreach (['0', '101', 'abc'] as $bad) {
            try {
                $service->create($admin, ['max_uses' => $bad]);
                self::fail("max_uses=$bad must be rejected");
            } catch (ValidationException $e) {
                self::assertArrayHasKey('max_uses', $e->errors);
            }
        }

        foreach (['0', '366'] as $bad) {
            try {
                $service->create($admin, ['expires_in_days' => $bad]);
                self::fail("expires_in_days=$bad must be rejected");
            } catch (ValidationException $e) {
                self::assertArrayHasKey('expires_in_days', $e->errors);
            }
        }

        try {
            $service->create($admin, ['onboarding_board_id' => '999999']);
            self::fail('nonexistent board must be rejected');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('onboarding_board_id', $e->errors);
        }
    }

    public function test_create_defaults_expiry_to_fourteen_days_and_never_stores_a_role(): void
    {
        // onboarding_role_id is deliberately not part of the issuance surface
        // (no approved onboarding-role policy — decision #36); a forged input
        // key is ignored, not stored.
        $result = $this->service()->create($this->adminEntity(), ['onboarding_role_id' => '1']);
        $row = $this->row($result['id']);

        self::assertNull($row['onboarding_role_id']);
        self::assertSame(1, (int) $row['max_uses']);
        self::assertNotNull($row['expires_at']);
        self::assertGreaterThan(gmdate('Y-m-d H:i:s', time() + 13 * 86400), (string) $row['expires_at']);
        self::assertLessThan(gmdate('Y-m-d H:i:s', time() + 15 * 86400), (string) $row['expires_at']);
    }

    public function test_create_and_revoke_write_audit_rows_without_the_raw_token(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();
        $result = $service->create($admin, ['email' => 'invited@example.test', 'max_uses' => '3']);
        $service->revoke($admin, $result['id']);

        $entries = $this->db->fetchAll(
            "SELECT action, after_json FROM moderation_log WHERE target_type = 'invitation' AND target_id = ? ORDER BY id",
            [$result['id']],
        );
        self::assertSame(['invitation_created', 'invitation_revoked'], array_column($entries, 'action'));
        foreach ($entries as $entry) {
            self::assertStringNotContainsString($result['token'], (string) ($entry['after_json'] ?? ''), 'raw token leaked into the audit trail');
        }
    }

    public function test_revoke_is_idempotent_and_audits_only_the_transition(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();
        $id = $service->create($admin, [])['id'];

        $service->revoke($admin, $id);
        $service->revoke($admin, $id); // second call: no error, no second audit row

        self::assertNotNull($this->row($id)['revoked_at']);
        $count = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'invitation' AND target_id = ? AND action = 'invitation_revoked'",
            [$id],
        );
        self::assertSame(1, $count);
    }

    // ---- list + preview ----------------------------------------------------

    public function test_list_derives_status_for_every_lifecycle_state(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();

        $active = $service->create($admin, [])['id'];
        $revoked = $service->create($admin, [])['id'];
        $service->revoke($admin, $revoked);
        $expired = $service->create($admin, [])['id'];
        $this->db->run("UPDATE invitations SET expires_at = '2020-01-01 00:00:00' WHERE id = ?", [$expired]);
        $exhausted = $service->create($admin, [])['id'];
        $this->db->run('UPDATE invitations SET used_count = max_uses WHERE id = ?', [$exhausted]);

        $statuses = [];
        foreach ($service->list() as $row) {
            $statuses[(int) $row['id']] = $row['status'];
        }
        self::assertSame('active', $statuses[$active]);
        self::assertSame('revoked', $statuses[$revoked]);
        self::assertSame('expired', $statuses[$expired]);
        self::assertSame('exhausted', $statuses[$exhausted]);
    }

    // ---- redemption (TM-IN-02..05) ----------------------------------------

    /** @return array<string,string> */
    private function registerInput(string $handle, string $email): array
    {
        return [
            'username' => $handle,
            'email' => $email,
            'password' => 'password123',
            'password_confirm' => 'password123',
        ];
    }

    public function test_redeem_creates_member_records_redemption_and_grants_board(): void
    {
        $admin = $this->adminEntity();
        $board = $this->makeBoard($this->makeCategory(), []);
        $service = $this->service();
        $invite = $service->create($admin, ['onboarding_board_id' => (string) $board['id'], 'max_uses' => '2']);

        $user = $service->redeem($invite['token'], $this->registerInput('invitee1', 'invitee1@example.test'), '203.0.113.9');

        self::assertSame('user', (string) $this->users()->find($user->id())['role']);
        self::assertSame(1, (int) $this->row($invite['id'])['used_count']);
        self::assertTrue((new BoardMemberRepository($this->db))->isMember((int) $board['id'], $user->id()));

        $redemption = $this->db->fetch('SELECT * FROM invitation_redemptions WHERE invitation_id = ?', [$invite['id']]);
        self::assertNotNull($redemption);
        self::assertSame($user->id(), (int) $redemption['user_id']);
        self::assertSame(inet_pton('203.0.113.9'), $redemption['ip']);

        $audit = $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'invitation' AND target_id = ? AND action = 'invitation_redeemed' AND actor_id = ?",
            [$invite['id'], $user->id()],
        );
        self::assertSame(1, (int) $audit);
    }

    public function test_redeem_rejects_bound_email_mismatch_before_consuming_a_use(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();
        $invite = $service->create($admin, ['email' => 'right@example.test']);

        try {
            $service->redeem($invite['token'], $this->registerInput('wrongmail', 'wrong@example.test'), null);
            self::fail('mismatched bound email must be rejected');
        } catch (ValidationException $e) {
            self::assertArrayHasKey('email', $e->errors);
            self::assertSame('wrongmail', $e->old['username'] ?? null, 'typed draft must survive the failure');
        }
        // Binding is checked BEFORE the use is consumed (assertable inside the
        // harness transaction — no rollback involved).
        self::assertSame(0, (int) $this->row($invite['id'])['used_count']);
        self::assertFalse($this->users()->emailExists('wrong@example.test'));
    }

    public function test_redeem_rejects_bound_domain_mismatch_including_subdomains(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();
        $invite = $service->create($admin, ['domain' => 'example.com', 'max_uses' => '5']);

        foreach (['a@other.com', 'a@sub.example.com'] as $bad) {
            try {
                $service->redeem($invite['token'], $this->registerInput('d' . md5($bad), $bad), null);
                self::fail("$bad must be rejected by the domain binding");
            } catch (ValidationException $e) {
                self::assertArrayHasKey('email', $e->errors);
            }
        }
        self::assertSame(0, (int) $this->row($invite['id'])['used_count']);

        // Case-insensitive exact match is accepted.
        $user = $service->redeem($invite['token'], $this->registerInput('dominvitee', 'ok@EXAMPLE.com'), null);
        self::assertSame('user', (string) $this->users()->find($user->id())['role']);
    }

    public function test_redeem_expired_revoked_exhausted_yield_uniform_error_and_no_account(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();

        $expired = $service->create($admin, []);
        $this->db->run("UPDATE invitations SET expires_at = '2020-01-01 00:00:00' WHERE id = ?", [$expired['id']]);
        $revoked = $service->create($admin, []);
        $service->revoke($admin, $revoked['id']);
        $exhausted = $service->create($admin, []);
        $this->db->run('UPDATE invitations SET used_count = max_uses WHERE id = ?', [$exhausted['id']]);

        $messages = [];
        foreach (['expired' => $expired, 'revoked' => $revoked, 'exhausted' => $exhausted] as $state => $invite) {
            try {
                $service->redeem($invite['token'], $this->registerInput('u' . $state, $state . '@example.test'), null);
                self::fail("$state token must not redeem");
            } catch (ValidationException $e) {
                $messages[] = $e->errors['invite'] ?? '(missing)';
            }
            self::assertFalse($this->users()->emailExists($state . '@example.test'), "no account for $state token");
        }
        // Uniform: every invalid reason produces the identical message (TM-IN-01/03).
        self::assertSame([InvitationService::INVALID_MESSAGE], array_values(array_unique($messages)));
    }

    public function test_redeem_never_applies_a_planted_onboarding_role(): void
    {
        // TM-IN-05 (stored half): even a DB-planted onboarding_role_id — which
        // the console can no longer issue — must not grant a role. Redemption
        // yields ordinary membership only.
        $admin = $this->adminEntity();
        $service = $this->service();
        $invite = $service->create($admin, []);
        $roleId = $this->db->insert(
            "INSERT INTO roles (role_key, name, kind) VALUES ('custom.planted-privileged', 'Planted Privileged', 'custom')",
            [],
        );
        $this->db->run('UPDATE invitations SET onboarding_role_id = ? WHERE id = ?', [$roleId, $invite['id']]);

        $user = $service->redeem($invite['token'], $this->registerInput('plantedrole', 'plantedrole@example.test'), null);

        self::assertSame('user', (string) $this->users()->find($user->id())['role']);
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM role_assignments WHERE subject_type = 'user' AND subject_id = ?",
            [$user->id()],
        ));
    }

    public function test_single_use_token_admits_exactly_one_account_with_real_transactions(): void
    {
        // TM-IN-02: with REAL commit/rollback semantics (the harness rollback
        // transaction is suspended — PackageInstallBudgetTest precedent), a
        // single-use token admits exactly one account, and a registration
        // validation failure rolls its consumed use back.
        $this->pdo->rollBack();

        $adminId = null;
        $userAId = null;
        $inviteIds = [];
        try {
            $admin = $this->adminEntity();
            $adminId = $admin->id();
            $service = $this->service();

            $single = $service->create($admin, []);
            $inviteIds[] = $single['id'];

            $userA = $service->redeem($single['token'], $this->registerInput('raceone', 'one@race.test'), null);
            $userAId = $userA->id();
            self::assertTrue($this->users()->emailExists('one@race.test'));

            try {
                $service->redeem($single['token'], $this->registerInput('racetwo', 'two@race.test'), null);
                self::fail('second redemption of a single-use token must fail');
            } catch (ValidationException $e) {
                self::assertSame(InvitationService::INVALID_MESSAGE, $e->errors['invite'] ?? null);
            }
            self::assertFalse($this->users()->emailExists('two@race.test'), 'the losing redemption must leave no account behind');
            self::assertSame(1, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$single['id']]));
            self::assertSame(1, (new InvitationRepository($this->db))->redemptionCount($single['id']));

            // A post-consume registration failure must restore the use.
            $fresh = $service->create($admin, []);
            $inviteIds[] = $fresh['id'];
            try {
                $service->redeem($fresh['token'], ['username' => 'shortpw', 'email' => 'short@race.test', 'password' => 'nope', 'password_confirm' => 'nope'], null);
                self::fail('registration validation failure expected');
            } catch (ValidationException $e) {
                self::assertArrayHasKey('password', $e->errors);
            }
            self::assertSame(0, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$fresh['id']]), 'rollback must restore the consumed use');
        } finally {
            // Manual cleanup — this work COMMITTED. Order respects FKs.
            if ($inviteIds !== []) {
                $in = implode(',', array_map('intval', $inviteIds));
                $this->db->run("DELETE FROM moderation_log WHERE target_type = 'invitation' AND target_id IN ($in)", []);
                $this->db->run("DELETE FROM invitations WHERE id IN ($in)", []);
            }
            foreach ([$userAId, $adminId] as $uid) {
                if ($uid !== null) {
                    $this->db->run('DELETE FROM users WHERE id = ?', [$uid]);
                }
            }
            $this->pdo->beginTransaction();
        }
    }

    public function test_preview_is_uniformly_null_for_every_invalid_reason(): void
    {
        $admin = $this->adminEntity();
        $service = $this->service();

        self::assertNull($service->preview(str_repeat('0', 64)), 'unknown token');
        self::assertNull($service->preview('not-a-token'), 'malformed token');

        $revoked = $service->create($admin, []);
        $service->revoke($admin, $revoked['id']);
        self::assertNull($service->preview($revoked['token']), 'revoked token');

        $expired = $service->create($admin, []);
        $this->db->run("UPDATE invitations SET expires_at = '2020-01-01 00:00:00' WHERE id = ?", [$expired['id']]);
        self::assertNull($service->preview($expired['token']), 'expired token');

        $exhausted = $service->create($admin, []);
        $this->db->run('UPDATE invitations SET used_count = max_uses WHERE id = ?', [$exhausted['id']]);
        self::assertNull($service->preview($exhausted['token']), 'exhausted token');

        $valid = $service->create($admin, []);
        $row = $service->preview($valid['token']);
        self::assertNotNull($row);
        self::assertSame($valid['id'], (int) $row['id']);
    }
}
