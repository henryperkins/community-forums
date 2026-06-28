<?php

declare(strict_types=1);

namespace Tests\Integration\Api;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminApiTokenTest extends TestCase
{
    private function enable(): void
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => true]);
    }

    public function test_admin_mints_a_token_shown_once(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'tokadmin', 'password' => 'password123']));

        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'password123', 'expires_in_days' => '',
        ]);
        // The plaintext is shown ONCE, directly in the mint response (200, not a redirect —
        // the token must never travel through the cookie-backed Flash).
        $this->assertStatus(200, $res);
        self::assertStringContainsString('rbt_', $res->body());

        // A later GET does not show it again (nothing persisted it — no cookie, no DB plaintext).
        self::assertStringNotContainsString('rbt_', $this->get('/admin/api-tokens')->body());

        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
    }

    public function test_mint_requires_correct_reauth(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));

        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'WRONG',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('CI', $res->body(), 'the typed name is preserved');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'), 'no token on bad reauth');
    }

    public function test_routes_are_404_when_flag_dark(): void
    {
        (new SettingRepository($this->db))->set('features', ['api_tokens' => false]);
        $this->actingAs($this->makeAdmin());
        $this->assertStatus(404, $this->get('/admin/api-tokens'));
    }

    public function test_suspended_admin_cannot_mint(): void
    {
        $this->enable();
        $admin = $this->makeUser(['role' => 'admin', 'status' => 'suspended', 'password' => 'password123']);
        $this->actingAs($admin);
        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'password123',
        ]);
        $this->assertStatus(403, $res); // WriteGate -> ForbiddenException
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
    }
}
