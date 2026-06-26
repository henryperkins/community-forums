<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * The first-run setup wizard driven over HTTP (no manual SQL).
 */
final class AppSetupTest extends TestCase
{
    public function test_fresh_install_routes_redirect_to_setup(): void
    {
        $this->assertRedirect($this->get('/'), '/setup');
        $this->assertRedirect($this->get('/login'), '/setup');
        $this->assertStatus(200, $this->get('/setup'));
    }

    public function test_wizard_creates_community_and_signs_admin_in(): void
    {
        $this->get('/setup');
        $response = $this->post('/setup', [
            'site_name' => 'Retro Town',
            'username' => 'founder',
            'email' => 'founder@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertRedirect($response, '/admin');

        // Admin is signed in and lands in the admin console.
        $admin = $this->get('/admin');
        $this->assertStatus(200, $admin);
        $this->assertSeeText($admin, 'Admin console');

        // Setup is now locked.
        $this->assertRedirect($this->get('/setup'), '/');

        // Community name and starter boards are live.
        $home = $this->get('/');
        $this->assertSeeText($home, 'Retro Town');
        $this->assertStatus(200, $this->get('/c/general'));
    }

    public function test_wizard_rejects_invalid_input(): void
    {
        $this->get('/setup');
        $response = $this->post('/setup', [
            'site_name' => '',
            'username' => 'founder',
            'email' => 'founder@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'community name');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM users'));
    }
}
