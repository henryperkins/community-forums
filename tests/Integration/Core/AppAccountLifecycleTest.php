<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Service\AccountLifecycleService;
use App\Repository\AccountDeletionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ServerDraftRepository;
use App\Repository\SessionRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use Tests\Support\TestCase;

final class AppAccountLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Account lifecycle/export/delete graduated to default-on (GA 2026-07-02,
        // ADR 0006). These behavioral tests deliberately run against the shipped
        // default with no features override — the default-on posture + operator
        // rollback are asserted in AppFeatureFlagTest.
    }

    private function lifecycleService(): AccountLifecycleService
    {
        return new AccountLifecycleService(
            $this->db,
            $this->users(),
            new AccountDeletionRepository($this->db),
            new SessionRepository($this->db),
            new ModerationLogRepository($this->db),
            new ServerDraftRepository($this->db),
            new ReauthGate(new PasswordHasher()),
            new WebAuthnCredentialRepository($this->db),
        );
    }

    public function test_self_service_lifecycle_cannot_clear_a_suspension(): void
    {
        $this->makeAdmin();
        $until = gmdate('Y-m-d H:i:s', time() + 7 * 86400);
        $user = $this->makeUser(['status' => 'suspended', 'suspended_until' => $until]);
        $this->actingAs($user);
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
        $this->assertStatus(422, $this->post('/settings/account/reactivate'));
        $row = $this->users()->find((int) $user['id']);
        self::assertSame('suspended', $row['status']);
        self::assertSame($until, $row['suspended_until']);
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
    }

    public function test_pending_deletion_cannot_be_bypassed_via_deactivation(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM account_deletion_requests WHERE user_id = ?', [$user['id']]));
        $stale = $this->post('/settings/account/deactivate', ['current_password' => 'password123']);
        $this->assertStatus(422, $stale);
        self::assertStringContainsString('role="alert"', $stale->body());
        self::assertStringNotContainsString('action="/settings/account/deactivate"', $stale->body());
        self::assertStringContainsString('action="/settings/account/delete/cancel"', $stale->body());
        $this->assertStatus(422, $this->post('/settings/account/reactivate'));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
    }

    public function test_suspension_cannot_be_cleared_by_requesting_then_canceling_deletion(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['status' => 'suspended']);
        $this->actingAs($user);
        $this->assertStatus(422, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->assertStatus(422, $this->post('/settings/account/delete/cancel'));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
    }

    public function test_cancel_preserves_moderation_imposed_during_deletion(): void
    {
        $admin = $this->makeAdmin();
        foreach (['suspend', 'ban'] as $action) {
            $user = $this->makeUser();
            $this->actingAs($user);
            $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
            $this->actingAs($admin);
            $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/' . $action, ['reason' => 'Independent restriction', 'until' => '2030-01-01 00:00:00']));
            $this->actingAs($this->users()->find((int) $user['id']));
            $page = $this->get('/settings/account/lifecycle');
            self::assertStringContainsString('action="/settings/account/delete/cancel"', $page->body());
            self::assertStringNotContainsString('action="/settings/account/deactivate"', $page->body());
            $this->assertStatus(303, $this->post('/settings/account/delete/cancel'));
            self::assertSame($action === 'ban' ? 'banned' : 'suspended', $this->users()->find((int) $user['id'])['status']);
            if ($action === 'suspend') {
                self::assertSame('2030-01-01 00:00:00', $this->users()->find((int) $user['id'])['suspended_until']);
            }
            $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
        }
    }

    public function test_live_site_record_blocks_recovery_despite_misleading_cached_status(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->db->run("INSERT INTO bans (user_id, scope, type, reason, created_by) VALUES (?, 'site', 'full', 'Live restriction', ?)", [$user['id'], $admin['id']]);
        $this->actingAs($user);
        $this->assertStatus(422, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
        $this->assertStatus(422, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->users()->setStatus((int) $user['id'], 'deactivated', null);
        $this->assertStatus(422, $this->post('/settings/account/reactivate'));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still blocked']));
    }

    public function test_expired_suspension_allows_lifecycle_without_erasing_timestamp(): void
    {
        $this->makeAdmin();
        $until = '2020-01-01 00:00:00';
        $user = $this->makeUser(['status' => 'suspended', 'suspended_until' => $until]);
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
        $this->assertStatus(303, $this->post('/settings/account/reactivate'));
        self::assertSame($until, $this->users()->find((int) $user['id'])['suspended_until']);
        $this->assertStatus(303, $this->post('/settings/account', ['display_name' => 'Allowed']));
    }

    public function test_lifecycle_reauth_uses_current_password_hash_not_stale_user(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser();
        $stale = \App\Domain\User::fromRow($user);
        $this->db->run('UPDATE users SET password_hash = ? WHERE id = ?', [(new PasswordHasher())->hash('new-password123'), $user['id']]);
        try {
            $this->lifecycleService()->deactivate($stale, 'password123');
            self::fail('Stale password authorized deactivation.');
        } catch (\App\Core\ValidationException $e) {
            self::assertArrayHasKey('current_password', $e->errors);
        }
        self::assertSame('active', $this->users()->find((int) $user['id'])['status']);
    }

    public function test_due_deletion_is_not_stranded_by_a_later_ban(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->actingAs($admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/ban', ['reason' => 'Later ban']));
        $this->db->run('UPDATE account_deletion_requests SET purge_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ?', [$user['id']]);
        self::assertSame(['purged' => 1], $this->lifecycleService()->purgeDue());
        self::assertSame('deleted', $this->users()->find((int) $user['id'])['status']);
        self::assertSame('purged', $this->db->fetchValue('SELECT status FROM account_deletion_requests WHERE user_id = ?', [$user['id']]));
    }

    public function test_canceled_deletion_never_purges_even_when_cached_status_is_pending(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->assertStatus(303, $this->post('/settings/account/delete/cancel'));
        $this->db->run('UPDATE account_deletion_requests SET purge_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ?', [$user['id']]);
        $this->users()->setStatus((int) $user['id'], 'pending_deletion', null);
        self::assertSame(['purged' => 0], $this->lifecycleService()->purgeDue());
        self::assertSame($user['email'], $this->users()->find((int) $user['id'])['email']);
    }

    public function test_pending_deletion_survives_timed_suspension_expiry(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        $this->actingAs($admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/suspend', ['reason' => 'During grace', 'until' => '2030-01-01 00:00:00']));
        $this->actingAs($this->users()->find((int) $user['id']));
        $live = $this->get('/settings/account/lifecycle');
        self::assertStringContainsString('action="/settings/account/delete/cancel"', $live->body());
        self::assertStringNotContainsString('action="/settings/account/deactivate"', $live->body());
        self::assertStringNotContainsString('action="/settings/account/reactivate"', $live->body());
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'During suspension']));

        // Advance only moderation clocks; the durable deletion grace is open.
        $this->expireSuspension((int) $user['id']);
        self::assertSame('pending', $this->db->fetchValue('SELECT status FROM account_deletion_requests WHERE user_id = ?', [$user['id']]));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'After suspension']));
        $expired = $this->get('/settings/account/lifecycle');
        self::assertStringContainsString('action="/settings/account/delete/cancel"', $expired->body());
        self::assertStringNotContainsString('action="/settings/account/reactivate"', $expired->body());
        $this->assertStatus(303, $this->post('/settings/account/delete/cancel'));
        $this->assertStatus(303, $this->post('/settings/account', ['display_name' => 'Explicitly recovered']));
    }

    public function test_self_deactivation_survives_timed_suspension_expiry_until_explicit_reactivation(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
        $this->actingAs($admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/suspend', ['reason' => 'During deactivation', 'until' => '2030-01-01 00:00:00']));
        $this->actingAs($this->users()->find((int) $user['id']));
        $live = $this->get('/settings/account/lifecycle');
        self::assertStringNotContainsString('action="/settings/account/reactivate"', $live->body());
        $this->assertStatus(422, $this->post('/settings/account/reactivate'));
        $this->expireSuspension((int) $user['id']);
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still self-deactivated']));
        $expired = $this->get('/settings/account/lifecycle');
        self::assertStringContainsString('action="/settings/account/reactivate"', $expired->body());
        $this->assertStatus(303, $this->post('/settings/account/reactivate'));
        $this->assertStatus(303, $this->post('/settings/account', ['display_name' => 'Explicitly reactivated']));
    }

    public function test_moderation_lift_keeps_self_deactivation_until_explicit_reactivation(): void
    {
        $admin = $this->makeAdmin();
        $user = $this->makeUser();
        $this->actingAs($user);
        $this->assertStatus(303, $this->post('/settings/account/deactivate', ['current_password' => 'password123']));
        $this->actingAs($admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/suspend', ['reason' => 'Indefinite suspension']));
        $this->actingAs($this->users()->find((int) $user['id']));
        self::assertStringNotContainsString('action="/settings/account/reactivate"', $this->get('/settings/account/lifecycle')->body());
        $this->actingAs($admin);
        $this->assertStatus(303, $this->post('/mod/u/' . $user['id'] . '/lift'));
        $this->actingAs($this->users()->find((int) $user['id']));
        $this->assertStatus(403, $this->post('/settings/account', ['display_name' => 'Still self-deactivated']));
        self::assertStringContainsString('action="/settings/account/reactivate"', $this->get('/settings/account/lifecycle')->body());
        $this->assertStatus(303, $this->post('/settings/account/reactivate'));
    }

    private function expireSuspension(int $userId): void
    {
        $this->db->run("UPDATE users SET suspended_until = '2020-01-01 00:00:00' WHERE id = ?", [$userId]);
        $this->db->run("UPDATE bans SET expires_at = '2020-01-01 00:00:00' WHERE user_id = ? AND scope = 'site' AND type = 'post'", [$userId]);
    }

    public function test_user_can_export_account_archive_without_secrets(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser([
            'username' => 'exporter',
            'email' => 'exporter@example.test',
            'display_name' => 'Export Me',
        ]);
        $this->actingAs($user);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $user, 'Exportable thread', 'Exportable body');
        $this->db->run(
            "INSERT INTO server_drafts (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
             VALUES (?, 'thread-export', 1, 'Export draft', 'Draft body', '{\"path\":\"/compose\"}', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY))",
            [(int) $user['id']],
        );

        // Export is a CSRF-protected POST: it writes an audit row, so it must not
        // be reachable (or forgeable) via a bare GET.
        $this->assertSame(405, $this->get('/settings/account/export')->status(), 'export must not be a GET');

        $response = $this->post('/settings/account/export');

        $this->assertStatus(200, $response);
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertStringContainsString('attachment; filename="retroboards-account-export.json"', (string) $response->getHeader('Content-Disposition'));
        $payload = json_decode($response->body(), true);
        self::assertSame('RetroBoards', $payload['app']);
        self::assertSame('exporter', $payload['profile']['username']);
        self::assertSame('Export Me', $payload['profile']['display_name']);
        self::assertArrayNotHasKey('password_hash', $payload['profile']);
        self::assertNotEmpty($payload['posts']);
        self::assertSame($thread['thread_id'], (int) $payload['posts'][0]['thread_id']);
        self::assertSame('Export draft', $payload['server_drafts'][0]['title']);
        self::assertSame('Draft body', $payload['server_drafts'][0]['body']);
        self::assertSame('/compose', $payload['server_drafts'][0]['metadata']['path']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_exported'"));
    }

    public function test_user_can_deactivate_and_reactivate_account(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'sleepy', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');

        $response = $this->post('/settings/account/deactivate', ['current_password' => 'password123']);
        $this->assertRedirect($response, '/settings/account/lifecycle');
        self::assertSame('deactivated', (string) $this->users()->find((int) $user['id'])['status']);

        $blocked = $this->post('/settings/account', ['display_name' => 'Should not save']);
        $this->assertStatus(403, $blocked);

        $reactivated = $this->post('/settings/account/reactivate');
        $this->assertRedirect($reactivated, '/settings/account/lifecycle');
        self::assertSame('active', (string) $this->users()->find((int) $user['id'])['status']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_deactivated'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_reactivated'"));
    }

    public function test_deletion_request_can_be_canceled_during_grace_period(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'deleter', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');

        $requested = $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->assertRedirect($requested, '/settings/account/lifecycle');
        self::assertSame('pending_deletion', (string) $this->users()->find((int) $user['id'])['status']);
        $row = $this->db->fetch('SELECT * FROM account_deletion_requests WHERE user_id = ?', [(int) $user['id']]);
        self::assertNotNull($row);
        self::assertSame('pending', (string) $row['status']);
        self::assertStringStartsWith(gmdate('Y-m-d', time() + 30 * 86400), (string) $row['purge_after']);

        $canceled = $this->post('/settings/account/delete/cancel');
        $this->assertRedirect($canceled, '/settings/account/lifecycle');
        self::assertSame('active', (string) $this->users()->find((int) $user['id'])['status']);
        self::assertSame('canceled', (string) $this->db->fetchValue('SELECT status FROM account_deletion_requests WHERE id = ?', [(int) $row['id']]));
    }

    /**
     * Slice 17. /deactivate and /delete/request both post `current_password`
     * and both re-render through lifecycleView, so an unscoped error bag lit
     * the *deactivate* form's inline error when a *deletion* was refused --
     * pointing the member at the wrong control on the page's destructive
     * section. The controller now says which action failed and the pane scopes
     * the replay to that form.
     */
    public function test_a_refused_lifecycle_action_scopes_its_error_to_its_own_form(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'scoped_lifecycle', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');

        $delete = $this->post('/settings/account/delete/request', ['current_password' => 'wrong-password']);
        $this->assertStatus(422, $delete);
        $body = $delete->body();
        self::assertStringContainsString('id="err-delete-current_password"', $body);
        self::assertStringNotContainsString('id="err-deactivate-current_password"', $body);

        $deactivate = $this->post('/settings/account/deactivate', ['current_password' => 'wrong-password']);
        $this->assertStatus(422, $deactivate);
        $other = $deactivate->body();
        self::assertStringContainsString('id="err-deactivate-current_password"', $other);
        self::assertStringNotContainsString('id="err-delete-current_password"', $other);

        // Neither refusal changed the account.
        self::assertSame('active', (string) $this->users()->find((int) $user['id'])['status']);
    }

    /**
     * The other half of that scoping. Each scoped form lives on one branch of
     * its section and lifecycleView() re-reads status/pending on every render,
     * so a state change between GET and POST re-renders the OTHER branch --
     * leaving the scoped error with no element to attach to. It must fall back
     * to the alert card, never a 422 page that says nothing failed.
     */
    public function test_a_scoped_lifecycle_error_falls_back_when_its_form_is_not_rendered(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'stale_lifecycle', 'password' => 'password123']);
        $this->actingAs($user);

        // Deletion is requested (as a second tab would have done), so the
        // delete-request form is replaced by the cancel form.
        $this->assertStatus(303, $this->post('/settings/account/delete/request', ['current_password' => 'password123']));
        self::assertSame('pending_deletion', (string) $this->users()->find((int) $user['id'])['status']);

        // The stale tab now submits the delete form that no longer renders.
        $stale = $this->post('/settings/account/delete/request', ['current_password' => 'wrong-password']);
        $this->assertStatus(422, $stale);
        $body = $stale->body();
        self::assertStringNotContainsString('id="err-delete-current_password"', $body, 'the scoped slot is not on this branch');
        $this->assertSeeText($stale, 'Your current password is incorrect.');
        self::assertStringContainsString('error-list', $body, 'the message falls back to the alert card');
    }

    public function test_final_active_admin_cannot_deactivate_or_request_deletion(): void
    {
        $admin = $this->makeAdmin(['username' => 'owner', 'password' => 'password123']);
        $this->actingAs($admin);
        $this->get('/settings/account/lifecycle');

        $deactivate = $this->post('/settings/account/deactivate', ['current_password' => 'password123']);
        $this->assertStatus(422, $deactivate);
        $this->assertSeeText($deactivate, 'Add another active admin');

        $delete = $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->assertStatus(422, $delete);
        $this->assertSeeText($delete, 'Add another active admin');
        self::assertSame('active', (string) $this->users()->find((int) $admin['id'])['status']);
    }

    public function test_due_deletion_purge_anonymizes_account_and_removes_pii(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser([
            'username' => 'purge-me',
            'email' => 'purge-me@example.test',
            'display_name' => 'Purge Me',
            'password' => 'password123',
        ]);
        $this->users()->updateProfileFull((int) $user['id'], 'Purge Me', 'private bio', 'Somewhere', 'https://example.test', 'they/them', 'signature');
        $this->db->run(
            "INSERT INTO server_drafts (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
             VALUES (?, 'thread-purge', 1, 'Purge draft', 'Draft body', '{}', UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY))",
            [(int) $user['id']],
        );
        $this->actingAs($user);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $user, 'Kept discussion', 'Keep the conversation intact');
        $this->get('/settings/account/lifecycle');
        $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->db->run(
            "UPDATE account_deletion_requests SET purge_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ?",
            [(int) $user['id']],
        );

        $result = $this->lifecycleService()->purgeDue();

        self::assertSame(['purged' => 1], $result);
        $row = $this->users()->find((int) $user['id']);
        self::assertSame('deleted', (string) $row['status']);
        self::assertSame('deleted-user-' . $user['id'], (string) $row['username']);
        self::assertSame('Deleted user', (string) $row['display_name']);
        self::assertSame('deleted-user-' . $user['id'] . '@deleted.invalid', (string) $row['email']);
        self::assertNull($row['password_hash']);
        self::assertNull($row['bio']);
        self::assertNull($row['location']);
        self::assertNull($row['website']);
        self::assertNull($row['pronouns']);
        self::assertNull($row['signature']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [(int) $user['id']]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM server_drafts WHERE user_id = ?', [(int) $user['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [(int) $thread['thread_id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_purged' AND actor_id IS NULL"));
    }

    public function test_purge_skips_request_when_account_is_no_longer_pending_deletion(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser([
            'username' => 'reactivated-before-purge',
            'email' => 'rbp@example.test',
            'password' => 'password123',
        ]);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');
        $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        // The grace window elapses…
        $this->db->run(
            "UPDATE account_deletion_requests SET purge_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ?",
            [(int) $user['id']],
        );
        // …but the account is no longer pending_deletion (a desync, or an operator
        // flipped the status back). The purge must NOT anonymize an active account.
        $this->users()->setStatus((int) $user['id'], 'active');

        $result = $this->lifecycleService()->purgeDue();

        self::assertSame(['purged' => 0], $result);
        $row = $this->users()->find((int) $user['id']);
        self::assertSame('active', (string) $row['status'], 'a non-pending_deletion account must never be anonymized');
        self::assertSame('rbp@example.test', (string) $row['email'], 'PII is preserved when status is not pending_deletion');
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_purged'"));
    }
}
