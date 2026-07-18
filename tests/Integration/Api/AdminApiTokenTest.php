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

    public function test_re_posting_the_same_mint_is_a_409_conflict_without_a_new_token(): void
    {
        // PR #44 spec §7: refreshing the mint POST replays the same
        // idempotency key — it must refuse (409) with no new credential and
        // no plaintext in the body, while the page stays usable.
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'tokadmin2', 'password' => 'password123']));
        $key = bin2hex(random_bytes(16));
        $body = ['name' => 'CI', 'scopes' => ['read:boards'], 'current_password' => 'password123', 'expires_in_days' => '', 'idempotency_key' => $key];

        $first = $this->post('/admin/api-tokens', $body);
        $this->assertStatus(200, $first);
        self::assertStringContainsString('rbt_', $first->body());

        $second = $this->post('/admin/api-tokens', $body);
        $this->assertStatus(409, $second);
        self::assertStringNotContainsString('rbt_', $second->body());
        $this->assertSeeText($second, 'already processed');
        $this->assertSeeText($second, 'Tokens');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'api_token_minted'"));
    }

    public function test_reauth_422_rerender_keeps_the_key_and_a_corrected_resubmit_succeeds(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['username' => 'tokadmin3', 'password' => 'password123']));
        $key = bin2hex(random_bytes(16));
        $body = ['name' => 'CI', 'scopes' => ['read:boards'], 'expires_in_days' => '', 'idempotency_key' => $key];

        $fail = $this->post('/admin/api-tokens', $body + ['current_password' => 'wrong']);
        $this->assertStatus(422, $fail);
        self::assertStringContainsString('name="idempotency_key" value="' . $key . '"', $fail->body());

        $ok = $this->post('/admin/api-tokens', $body + ['current_password' => 'password123']);
        $this->assertStatus(200, $ok);
        self::assertStringContainsString('rbt_', $ok->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM api_tokens'));
    }

    public function test_fresh_gets_render_different_keys(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $re = '/name="idempotency_key" value="([0-9a-f]{32})"/';
        preg_match($re, $this->get('/admin/api-tokens')->body(), $m1);
        preg_match($re, $this->get('/admin/api-tokens')->body(), $m2);
        self::assertNotSame([], $m1, 'the create form must carry a fresh idempotency key');
        self::assertNotSame([], $m2);
        self::assertNotSame($m1[1], $m2[1], 'two fresh GETs must not share a key');
    }

    public function test_mint_requires_correct_reauth(): void
    {
        $this->enable();
        $this->actingAs($this->makeAdmin(['password' => 'password123']));

        $res = $this->post('/admin/api-tokens', [
            'name' => 'CI',
            'scopes' => ['read:boards'],
            'expires_in_days' => '30',
            'current_password' => 'WRONG',
        ]);
        $this->assertStatus(422, $res);
        self::assertStringContainsString('CI', $res->body(), 'the typed name is preserved');
        self::assertStringContainsString('value="read:boards" checked', $res->body(), 'the selected scope is preserved');
        self::assertStringContainsString('value="30"', $res->body(), 'the typed expiry is preserved');
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
