<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminWebhookTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
    }

    private function register(): \App\Core\Response
    {
        return $this->post('/admin/webhooks', [
            'name' => 'CI hook',
            'url' => 'https://example.test/hook',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]);
    }

    public function test_register_shows_secret_once_then_hidden(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'whadmin', 'password' => 'password123']));

        $res = $this->register();
        $this->assertStatus(200, $res);
        self::assertStringContainsString('will not be shown again', $res->body());

        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);
        self::assertStringNotContainsString('will not be shown again', $this->get('/admin/webhooks/' . $id)->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_register_wrong_reauth_is_422_and_preserves_input(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $res = $this->post('/admin/webhooks', [
            'name' => 'KeepMe',
            'url' => 'https://example.test/hook',
            'events' => ['ping'],
            'current_password' => 'WRONG',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('KeepMe', $res->body());
        self::assertStringContainsString('value="ping" checked', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_routes_404_when_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/webhooks'));
    }

    public function test_suspended_admin_cannot_register(): void
    {
        $this->enable();
        $this->actingAs($this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']));
        $this->assertStatus(403, $this->register());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks'));
    }

    public function test_toggle_and_send_test_and_delete_round_trip(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->register();
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);

        $this->assertRedirectContains($this->post('/admin/webhooks/' . $id . '/test', []), '/admin/webhooks/' . $id);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM webhook_deliveries WHERE webhook_id = ? AND event_type = 'ping'", [$id]));

        // Pause/resume report which direction they went, not a generic "updated".
        $paused = $this->post('/admin/webhooks/' . $id . '/toggle', ['active' => '0']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT is_active FROM webhooks WHERE id = ?', [$id]));
        self::assertStringContainsString('paused', urldecode(implode(' ', $paused->cookieHeaders())));

        // Delete is password-reauthed (it discards delivery history + secret).
        $this->assertRedirectContains(
            $this->post('/admin/webhooks/' . $id . '/delete', ['current_password' => 'password123']),
            '/admin/webhooks',
        );
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks WHERE id = ?', [$id]));
    }

    public function test_delete_with_wrong_password_is_422_and_keeps_the_endpoint(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->register();
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);

        $this->assertStatus(422, $this->post('/admin/webhooks/' . $id . '/delete', ['current_password' => 'WRONG']));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks WHERE id = ?', [$id]));
    }

    public function test_send_test_for_unknown_webhook_redirects_without_500(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));

        $this->assertRedirectContains($this->post('/admin/webhooks/999/test', []), '/admin/webhooks');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhook_deliveries'));
    }

    public function test_send_test_event_is_rate_limited(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->register();
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['CI hook']);

        for ($i = 0; $i < 20; $i++) {
            $this->assertContains($this->post('/admin/webhooks/' . $id . '/test', [])->status(), [302, 303]);
        }
        $this->assertStatus(429, $this->post('/admin/webhooks/' . $id . '/test', []));
    }
}
