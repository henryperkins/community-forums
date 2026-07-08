<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Config;
use App\Repository\SettingRepository;
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
}
