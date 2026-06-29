<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\App;
use App\Core\Config;
use App\Repository\EmailDeliveryRepository;
use Tests\Support\TestCase;

final class AppAdminEmailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist so the first-run setup gate is satisfied; otherwise
        // every request (guest or non-admin) is shadowed by a /setup redirect
        // before it can reach the real auth checks. Matches the sibling admin
        // tests' pattern (e.g. AppAdminUserRecordTest, AdminAnnouncementTest).
        $this->makeAdmin();
    }

    /** Rebuild the kernel so its Mailer is a configured ArrayMailer. */
    private function useArrayMailer(): void
    {
        $cfg = new Config(array_replace_recursive($this->config->all(), ['mail' => ['driver' => 'array']]));
        $this->app = new App($cfg, $this->db, $this->rateLimiter);
    }

    public function test_index_requires_admin(): void
    {
        // Guest → redirect to login.
        $this->assertRedirectContains($this->get('/admin/email'), '/login');

        // Non-admin → 403.
        $this->actingAs($this->makeUser(['username' => 'plainuser']));
        $this->assertStatus(403, $this->get('/admin/email'));

        // Admin → 200.
        $this->logoutClient();
        $this->actingAs($this->makeAdmin(['username' => 'emailadmin']));
        $this->assertStatus(200, $this->get('/admin/email'));
    }

    public function test_unconfigured_transport_blocks_test_send_and_shows_blocked_banner(): void
    {
        // Default kernel uses SendmailMailer('') → not configured.
        $this->actingAs($this->makeAdmin(['email' => 'blocked@example.test']));

        $res = $this->post('/admin/email/test', []);
        $this->assertRedirectContains($res, '/admin/email');
        self::assertSame(0, (int) $this->db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE kind = 'test'"));

        self::assertStringContainsString('Configure your sending domain', $this->get('/admin/email')->body());
    }

    public function test_test_send_with_configured_transport_enqueues_and_marks_sent_with_audit(): void
    {
        $this->useArrayMailer();
        $this->actingAs($this->makeAdmin(['email' => 'sender@example.test']));

        $this->assertRedirectContains($this->post('/admin/email/test', []), '/admin/email');
        self::assertSame(
            'sent',
            (string) $this->db->fetchValue("SELECT status FROM email_deliveries WHERE kind = 'test' AND email = ?", ['sender@example.test']),
        );
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_test_sent'"));
    }

    public function test_suppress_then_remove_round_trip_via_http(): void
    {
        $this->actingAs($this->makeAdmin());

        $this->assertRedirectContains($this->post('/admin/email/suppressions', ['email' => 'spam@example.test']), '/admin/email');
        self::assertStringContainsString('spam@example.test', $this->get('/admin/email')->body());

        $this->assertRedirectContains($this->post('/admin/email/suppressions/remove', ['email' => 'spam@example.test']), '/admin/email');
        self::assertStringNotContainsString('spam@example.test', $this->get('/admin/email')->body());
    }

    public function test_suppress_cascades_subscription_email_channel_off(): void
    {
        $u = $this->makeUser(['email' => 'casc@example.test']);
        $board = $this->makeBoard($this->makeCategory());
        (new \App\Repository\SubscriptionRepository($this->db))->set((int) $u['id'], 'board', (int) $board['id'], true, true, 'instant');

        $this->actingAs($this->makeAdmin());
        $this->post('/admin/email/suppressions', ['email' => 'casc@example.test']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT email_enabled FROM subscriptions WHERE user_id = ?', [(int) $u['id']]));
    }

    public function test_delivery_log_filters_by_kind(): void
    {
        $deliv = new EmailDeliveryRepository($this->db);
        $deliv->enqueue(null, 'inst@example.test', 'instant', 'Hi', 'k1');
        $deliv->enqueue(null, 'dig@example.test', 'digest', 'Hi', null);

        $this->actingAs($this->makeAdmin());
        $body = $this->get('/admin/email', ['kind' => 'instant'])->body();
        self::assertStringContainsString('inst@example.test', $body);
        self::assertStringNotContainsString('dig@example.test', $body);
    }

    public function test_export_returns_csv_attachment(): void
    {
        (new EmailDeliveryRepository($this->db))->enqueue(null, 'csv@example.test', 'instant', 'Hi', 'kx');
        $this->actingAs($this->makeAdmin());

        $res = $this->get('/admin/email/export');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('text/csv', (string) $res->getHeader('content-type'));
        self::assertStringContainsString('attachment', (string) $res->getHeader('content-disposition'));
        self::assertStringContainsString('csv@example.test', $res->body());
    }

    public function test_test_send_is_rate_limited(): void
    {
        $this->useArrayMailer();
        $this->actingAs($this->makeAdmin(['email' => 'rl@example.test']));

        for ($i = 0; $i < 20; $i++) {
            self::assertContains($this->post('/admin/email/test', [])->status(), [302, 303]);
        }
        $this->assertStatus(429, $this->post('/admin/email/test', []));
    }
}
