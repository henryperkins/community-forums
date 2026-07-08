<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use App\Repository\BoardMemberRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Service\AuthService;
use App\Service\InvitationService;
use Tests\Support\TestCase;

/**
 * HTTP surface of the invitation lifecycle (P5-13 / Inc 9): the admin console
 * (issue show-once / list / revoke — TM-IN-06/07) and, further down, the
 * public redemption flow through /invite/{token} + /register across the
 * open/invite/closed × flag matrix (TM-IN-01/05).
 */
final class AppInvitationsTest extends TestCase
{
    private function enableInvitations(): void
    {
        (new SettingRepository($this->db))->set('features', ['invitations' => true]);
    }

    /** Rebuild the kernel with an overridden rate-limit policy (TestCase config-rebuild pattern). */
    private function withRateLimit(string $policy, int $max, int $decay): void
    {
        $items = $this->config->all();
        $items['rate_limits'][$policy] = [$max, $decay];
        $this->app = new App(new Config($items), $this->db, $this->rateLimiter);
    }

    // ---- console authorization (TM-IN-07 first half) -----------------------

    public function test_console_requires_admin(): void
    {
        $this->enableInvitations();
        $this->makeAdmin(); // an admin must exist or the setup gate intercepts the guest request
        $member = $this->makeUser();
        $moderator = $this->makeUser(['role' => 'moderator']);

        $this->assertRedirectContains($this->get('/admin/invitations'), '/login');

        $this->actingAs($member);
        $this->assertStatus(403, $this->get('/admin/invitations'));
        $this->get('/'); // seed CSRF for the POST
        $this->assertStatus(403, $this->post('/admin/invitations', []));
        $this->logoutClient();

        $this->actingAs($moderator);
        $this->assertStatus(403, $this->get('/admin/invitations'));
        $this->get('/');
        $this->assertStatus(403, $this->post('/admin/invitations', []));
    }

    public function test_console_is_404_while_the_flag_is_dark(): void
    {
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/invitations'));
        $this->get('/');
        $this->assertStatus(404, $this->post('/admin/invitations', []));
    }

    // ---- issuance (TM-IN-06) -----------------------------------------------

    public function test_create_shows_raw_token_exactly_once_and_never_persists_it(): void
    {
        $this->enableInvitations();
        $this->actingAs($this->makeAdmin());
        $this->get('/admin/invitations');

        $created = $this->post('/admin/invitations', ['max_uses' => '2', 'expires_in_days' => '7']);
        $this->assertStatus(200, $created);
        self::assertSame(1, preg_match('~/invite/([0-9a-f]{64})~', $created->body(), $m), 'the create response must show the invite URL once');
        $token = $m[1];
        self::assertStringContainsString('will not be shown again', $created->body());

        // A later GET renders the list without the secret (show-once).
        $list = $this->get('/admin/invitations');
        self::assertStringNotContainsString($token, $list->body());

        // At rest: only the sha256 exists — no column and no audit row carries the raw token (TM-IN-06).
        $row = $this->db->fetch('SELECT * FROM invitations WHERE token_hash = ?', [hash('sha256', $token)]);
        self::assertNotNull($row);
        foreach ($row as $column => $value) {
            if (is_string($value)) {
                self::assertStringNotContainsString($token, $value, "raw token leaked into invitations.$column");
            }
        }
        $logs = $this->db->fetchAll("SELECT before_json, after_json FROM moderation_log WHERE target_type = 'invitation'", []);
        self::assertNotSame([], $logs);
        foreach ($logs as $log) {
            self::assertStringNotContainsString($token, (string) ($log['before_json'] ?? ''));
            self::assertStringNotContainsString($token, (string) ($log['after_json'] ?? ''));
        }
    }

    public function test_create_validation_rerenders_422_with_the_typed_values(): void
    {
        $this->enableInvitations();
        $this->actingAs($this->makeAdmin());
        $this->get('/admin/invitations');

        $res = $this->post('/admin/invitations', ['email' => 'both@example.test', 'domain' => 'example.test']);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('not both', $res->body());
        self::assertStringContainsString('both@example.test', $res->body());
        self::assertStringContainsString('example.test', $res->body());
    }

    public function test_revoke_marks_the_row_and_audits(): void
    {
        $this->enableInvitations();
        $this->actingAs($this->makeAdmin());
        $this->get('/admin/invitations');
        $this->post('/admin/invitations', []);
        $id = (int) $this->db->fetchValue('SELECT id FROM invitations ORDER BY id DESC LIMIT 1', []);

        $res = $this->post('/admin/invitations/' . $id . '/revoke', []);
        $this->assertRedirect($res, '/admin/invitations');

        self::assertNotNull($this->db->fetch('SELECT revoked_at FROM invitations WHERE id = ?', [$id])['revoked_at']);
        $this->assertSeeText($this->get('/admin/invitations'), 'Revoked');
        self::assertSame(1, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log WHERE target_type = 'invitation' AND target_id = ? AND action = 'invitation_revoked'",
            [$id],
        ));
    }

    public function test_issuance_is_rate_limited(): void
    {
        // TM-IN-07 second half: burst issuance trips the invite_create policy.
        $this->enableInvitations();
        $this->withRateLimit('invite_create', 2, 3600);
        $this->actingAs($this->makeAdmin());
        $this->get('/admin/invitations');

        $this->assertStatus(200, $this->post('/admin/invitations', []));
        $this->assertStatus(200, $this->post('/admin/invitations', []));
        $blocked = $this->post('/admin/invitations', []);
        $this->assertStatus(429, $blocked);
        self::assertStringContainsString('Too many invitations', $blocked->body());
    }

    public function test_console_responses_carry_noindex(): void
    {
        $this->enableInvitations();
        $this->actingAs($this->makeAdmin());

        self::assertSame('noindex', $this->get('/admin/invitations')->getHeader('x-robots-tag'));
        $this->post('/admin/invitations', []);
        $created = $this->post('/admin/invitations', []);
        self::assertSame('noindex', $created->getHeader('x-robots-tag'));
    }

    // ---- public redemption flow (TM-IN-01/05) ------------------------------

    /** Issue an invitation directly at the service layer (console flow is covered above). */
    private function issueInvitation(array $input = []): array
    {
        $adminRow = $this->makeAdmin();
        $admin = $this->users()->findEntity((int) $adminRow['id']);
        self::assertNotNull($admin);
        $service = new InvitationService(
            $this->db,
            new InvitationRepository($this->db),
            new AuthService(new UserRepository($this->db), new PasswordHasher(), $this->config),
            $this->boards(),
            new BoardMemberRepository($this->db),
            new ModerationLogRepository($this->db),
        );
        return $service->create($admin, $input);
    }

    private function setMode(string $mode): void
    {
        (new SettingRepository($this->db))->set('registration_mode', $mode);
    }

    /** @return array<string,string> */
    private function registerFields(string $handle, string $email): array
    {
        return [
            'username' => $handle,
            'email' => $email,
            'password' => 'password123',
            'password_confirm' => 'password123',
        ];
    }

    public function test_invite_landing_redirects_to_register_and_is_noindex(): void
    {
        $this->enableInvitations();
        $invite = $this->issueInvitation();

        $res = $this->get('/invite/' . $invite['token']);
        $this->assertRedirect($res, '/register?invite=' . $invite['token']);
        self::assertSame('noindex', $res->getHeader('x-robots-tag'));
    }

    public function test_register_get_shows_a_uniform_banner_for_every_invalid_reason(): void
    {
        $this->enableInvitations();
        $this->makeAdmin();

        $expired = $this->issueInvitation();
        $this->db->run("UPDATE invitations SET expires_at = '2020-01-01 00:00:00' WHERE id = ?", [$expired['id']]);
        $revoked = $this->issueInvitation();
        $this->db->run('UPDATE invitations SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$revoked['id']]);
        $exhausted = $this->issueInvitation();
        $this->db->run('UPDATE invitations SET used_count = max_uses WHERE id = ?', [$exhausted['id']]);

        $tokens = [str_repeat('0', 64), $expired['token'], $revoked['token'], $exhausted['token']];
        foreach ($tokens as $token) {
            $body = $this->get('/register', ['invite' => $token])->body();
            self::assertStringContainsString(InvitationService::INVALID_MESSAGE, $body);
            // Uniform: the page must not reveal WHY the token is invalid (TM-IN-01).
            self::assertStringNotContainsString('expired', $body);
            self::assertStringNotContainsString('revoked', $body);
            self::assertStringNotContainsString('exhausted', $body);
        }

        $valid = $this->issueInvitation();
        $body = $this->get('/register', ['invite' => $valid['token']])->body();
        self::assertStringContainsString('been invited', $body);
        self::assertStringContainsString($valid['token'], $body); // hidden field round-trips the token
    }

    public function test_invite_probing_is_rate_limited_at_register_and_landing_stays_a_pure_redirect(): void
    {
        // TM-IN-01: enumeration probes trip the invite_redeem policy at the
        // verdict endpoint (/register); the landing link does no token work
        // and stays a friendly redirect even for an exhausted client.
        $this->enableInvitations();
        $this->makeAdmin();
        $this->withRateLimit('invite_redeem', 2, 900);

        $this->get('/register', ['invite' => str_repeat('a', 64)]);
        $this->get('/register', ['invite' => str_repeat('b', 64)]);
        $blocked = $this->get('/register', ['invite' => str_repeat('c', 64)]);
        $this->assertStatus(429, $blocked);

        $landing = $this->get('/invite/' . str_repeat('d', 64));
        $this->assertRedirect($landing, '/register?invite=' . str_repeat('d', 64));
        self::assertSame('noindex', $landing->getHeader('x-robots-tag'));
    }

    public function test_rate_limited_post_is_uniform_and_keeps_the_draft(): void
    {
        // After the invite_redeem window trips, the 429 body must not reveal
        // token validity (no preview) and must keep the typed draft + token.
        $this->enableInvitations();
        $this->setMode('invite');
        $valid = $this->issueInvitation();
        $this->withRateLimit('invite_redeem', 1, 900);
        $this->get('/register'); // seed CSRF without charging the policy

        $first = $this->post('/register', $this->registerFields('probe1', 'probe1@example.test') + ['invite' => str_repeat('e', 64)]);
        $this->assertStatus(422, $first); // consumed the single unit

        foreach (['valid' => $valid['token'], 'invalid' => str_repeat('f', 64)] as $kind => $token) {
            $res = $this->post('/register', $this->registerFields('limited' . $kind, $kind . '@example.test') + ['invite' => $token]);
            $this->assertStatus(429, $res);
            self::assertStringContainsString('Too many invitation attempts', $res->body(), $kind);
            self::assertStringContainsString('limited' . $kind, $res->body(), "draft lost for $kind token");
            self::assertStringContainsString($token, $res->body(), "token dropped for $kind");
            self::assertStringContainsString('name="username"', $res->body(), "form suppressed for $kind token");
            self::assertStringNotContainsString('been invited', $res->body(), "validity leaked for $kind token");
        }
    }

    public function test_dead_token_is_dropped_so_the_retry_can_succeed(): void
    {
        // Open mode: a 422 for the token's own death must keep the draft but
        // drop the dead token, so resubmitting the re-rendered form works.
        $this->enableInvitations();
        $this->setMode('open');
        $expired = $this->issueInvitation();
        $this->db->run("UPDATE invitations SET expires_at = '2020-01-01 00:00:00' WHERE id = ?", [$expired['id']]);
        $this->get('/register');

        $res = $this->post('/register', $this->registerFields('retryer', 'retryer@example.test') + ['invite' => $expired['token']]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('retryer', $res->body());
        self::assertStringContainsString('name="username"', $res->body());
        self::assertStringNotContainsString($expired['token'], $res->body(), 'a dead token must not be re-embedded');

        $retry = $this->post('/register', $this->registerFields('retryer', 'retryer@example.test'));
        $this->assertRedirect($retry, '/');
    }

    public function test_invite_mode_error_rerenders_keep_the_typed_draft(): void
    {
        $this->enableInvitations();
        $this->setMode('invite');
        $invite = $this->issueInvitation(['max_uses' => '5']);
        $this->get('/register', ['invite' => $invite['token']]);

        // Field error: draft + still-valid token + form all survive.
        $res = $this->post('/register', ['username' => 'keepme', 'email' => 'keepme@example.test', 'password' => 'x', 'password_confirm' => 'x', 'invite' => $invite['token']]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('keepme', $res->body());
        self::assertStringContainsString($invite['token'], $res->body());
        self::assertStringContainsString('name="username"', $res->body());

        // Token-less POST carrying a draft: the 403 must still render the draft
        // (anti-draft-loss) rather than suppressing the form.
        $this->get('/register');
        $res = $this->post('/register', $this->registerFields('draftful', 'draftful@example.test'));
        $this->assertStatus(403, $res);
        self::assertStringContainsString('invitation', $res->body());
        self::assertStringContainsString('draftful', $res->body());
        self::assertStringContainsString('name="username"', $res->body());
    }

    public function test_rate_limited_get_keeps_the_token_and_stays_noindex(): void
    {
        // A 429 on GET /register?invite=… must not silently degrade the next
        // submit into a plain grant-less signup, and must stay out of indexes.
        $this->enableInvitations();
        $this->setMode('open');
        $invite = $this->issueInvitation();
        $this->withRateLimit('invite_redeem', 1, 900);

        $this->get('/register', ['invite' => $invite['token']]);
        $blocked = $this->get('/register', ['invite' => $invite['token']]);
        $this->assertStatus(429, $blocked);
        self::assertStringContainsString($invite['token'], $blocked->body(), 'the grant must not be silently dropped');
        self::assertSame('noindex', $blocked->getHeader('x-robots-tag'));
    }

    public function test_token_bearing_renders_stay_noindex_while_dark(): void
    {
        $this->setMode('open'); // features.invitations stays dark
        $this->makeAdmin();
        $res = $this->get('/register', ['invite' => str_repeat('a', 64)]);
        $this->assertStatus(200, $res);
        self::assertSame('noindex', $res->getHeader('x-robots-tag'));
    }

    public function test_array_shaped_invite_parameters_are_treated_as_absent(): void
    {
        $this->enableInvitations();
        $this->setMode('open');
        $this->makeAdmin();

        // Strict PHPUnit turns the 'Array to string conversion' E_WARNING red.
        $this->assertStatus(200, $this->get('/register', ['invite' => ['x']]));

        $this->get('/register');
        $res = $this->post('/register', $this->registerFields('arrayproof', 'arrayproof@example.test') + ['invite' => ['x', 'y']]);
        $this->assertRedirect($res, '/'); // malformed carrier = no token; plain signup
    }

    public function test_full_invite_registration_flow_in_invite_mode(): void
    {
        $this->enableInvitations();
        $this->setMode('invite');
        $board = $this->makeBoard($this->makeCategory(), []);
        $invite = $this->issueInvitation(['onboarding_board_id' => (string) $board['id']]);

        $landing = $this->get('/invite/' . $invite['token']);
        $this->assertRedirect($landing, '/register?invite=' . $invite['token']);

        $form = $this->get('/register', ['invite' => $invite['token']]);
        self::assertStringContainsString('been invited', $form->body());

        $res = $this->post('/register', $this->registerFields('invitee9', 'invitee9@example.test') + ['invite' => $invite['token']]);
        $this->assertRedirect($res, '/');
        $this->assertSeeText($this->get('/'), 'invitee9'); // session established

        $user = $this->users()->findByUsername('invitee9');
        self::assertNotNull($user);
        self::assertTrue((new BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $user['id']));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$invite['id']]));
    }

    public function test_invite_mode_blocks_missing_and_invalid_tokens(): void
    {
        $this->enableInvitations();
        $this->setMode('invite');
        $this->makeAdmin();
        $this->get('/register');

        $missing = $this->post('/register', $this->registerFields('noinvite', 'noinvite@example.test'));
        $this->assertStatus(403, $missing);
        self::assertStringContainsString('invitation', $missing->body());

        $invalid = $this->post('/register', $this->registerFields('badinvite', 'badinvite@example.test') + ['invite' => str_repeat('f', 64)]);
        $this->assertStatus(422, $invalid);
        self::assertStringContainsString(InvitationService::INVALID_MESSAGE, $invalid->body());

        self::assertFalse($this->users()->emailExists('noinvite@example.test'));
        self::assertFalse($this->users()->emailExists('badinvite@example.test'));
    }

    public function test_closed_mode_blocks_even_valid_invitations(): void
    {
        $this->enableInvitations();
        $this->setMode('closed');
        $invite = $this->issueInvitation();

        $form = $this->get('/register', ['invite' => $invite['token']]);
        self::assertStringContainsString('currently closed', $form->body());
        self::assertStringNotContainsString('been invited', $form->body());

        $this->get('/register');
        $res = $this->post('/register', $this->registerFields('closedinvite', 'closedinvite@example.test') + ['invite' => $invite['token']]);
        $this->assertStatus(403, $res);
        self::assertFalse($this->users()->emailExists('closedinvite@example.test'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$invite['id']]));
    }

    public function test_invite_mode_with_a_dark_flag_fails_closed(): void
    {
        // features.invitations stays dark; a planted valid invitation must not
        // reopen registration (owner decision 2026-07-08: fail closed).
        $this->setMode('invite');
        $invite = $this->issueInvitation();

        $form = $this->get('/register');
        self::assertStringContainsString('currently closed', $form->body());

        $res = $this->post('/register', $this->registerFields('darkinvite', 'darkinvite@example.test') + ['invite' => $invite['token']]);
        $this->assertStatus(403, $res);
        self::assertFalse($this->users()->emailExists('darkinvite@example.test'));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT used_count FROM invitations WHERE id = ?', [$invite['id']]));
    }

    public function test_open_mode_honors_valid_invites_and_errors_on_invalid_ones(): void
    {
        $this->enableInvitations();
        $this->setMode('open');
        $board = $this->makeBoard($this->makeCategory(), []);
        $invite = $this->issueInvitation(['onboarding_board_id' => (string) $board['id']]);
        $this->get('/register');

        // A present valid invite is honored (board grant + consumption).
        $ok = $this->post('/register', $this->registerFields('openinvite', 'openinvite@example.test') + ['invite' => $invite['token']]);
        $this->assertRedirect($ok, '/');
        $user = $this->users()->findByUsername('openinvite');
        self::assertNotNull($user);
        self::assertTrue((new BoardMemberRepository($this->db))->isMember((int) $board['id'], (int) $user['id']));

        // A present-but-invalid invite errors rather than silently degrading
        // to a plain signup (the redeemer expected a grant they would not get).
        $this->logoutClient();
        $this->get('/register');
        $bad = $this->post('/register', $this->registerFields('openbad', 'openbad@example.test') + ['invite' => str_repeat('e', 64)]);
        $this->assertStatus(422, $bad);
        self::assertFalse($this->users()->emailExists('openbad@example.test'));
    }

    public function test_forged_post_grant_fields_are_ignored(): void
    {
        // TM-IN-05 (request half): forged role/grant fields in the redemption
        // POST yield ordinary membership only.
        $this->enableInvitations();
        $this->setMode('invite');
        $otherBoard = $this->makeBoard($this->makeCategory(), []);
        $invite = $this->issueInvitation(); // no board grant, no role
        $this->get('/register');

        $res = $this->post('/register', $this->registerFields('forger', 'forger@example.test') + [
            'invite' => $invite['token'],
            'role' => 'admin',
            'onboarding_role_id' => '1',
            'onboarding_board_id' => (string) $otherBoard['id'],
        ]);
        $this->assertRedirect($res, '/');

        $user = $this->users()->findByUsername('forger');
        self::assertNotNull($user);
        self::assertSame('user', (string) $user['role']);
        self::assertFalse((new BoardMemberRepository($this->db))->isMember((int) $otherBoard['id'], (int) $user['id']));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM role_assignments WHERE subject_type = 'user' AND subject_id = ?",
            [(int) $user['id']],
        ));
    }

    public function test_validation_failure_preserves_draft_and_invite_token(): void
    {
        $this->enableInvitations();
        $this->setMode('invite');
        $invite = $this->issueInvitation(['max_uses' => '5']);
        $this->get('/register', ['invite' => $invite['token']]);

        $res = $this->post('/register', [
            'username' => 'draftkeeper',
            'email' => 'draftkeeper@example.test',
            'password' => 'nope',
            'password_confirm' => 'nope',
            'invite' => $invite['token'],
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('draftkeeper', $res->body());
        self::assertStringContainsString($invite['token'], $res->body()); // hidden field keeps the link alive
    }
}
