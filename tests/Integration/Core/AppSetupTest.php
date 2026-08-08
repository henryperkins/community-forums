<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\ProtectedOwnerRepository;
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
        $adminId = (int) $this->db->fetchValue('SELECT id FROM users WHERE username = ?', ['founder']);
        self::assertTrue((new ProtectedOwnerRepository($this->db))->isActiveOwner($adminId));

        // Community name and starter boards are live.
        $home = $this->get('/');
        $this->assertSeeText($home, 'Retro Town');
        $this->assertStatus(200, $this->get('/c/general'));
    }

    /**
     * Resubmitting the wizard once an admin exists (a retry after a slow
     * first-run request already succeeded) used to bounce silently to `/`,
     * which is indistinguishable from "it made the account but didn't sign me
     * in" — the failure mode that sent a real operator hunting a login bug.
     */
    public function test_resubmitting_the_wizard_after_setup_explains_instead_of_bouncing_home(): void
    {
        $this->makeAdmin();

        $response = $this->post('/setup', [
            'site_name' => 'Retro Town',
            'username' => 'secondfounder',
            'email' => 'second@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);

        $this->assertRedirect($response, '/login');
        $this->assertSeeText($this->get('/login'), 'Setup has already been completed');
        self::assertSame(
            0,
            (int) $this->db->fetchValue('SELECT COUNT(*) FROM users WHERE username = ?', ['secondfounder']),
        );
    }

    /** A GET is nobody's submitted work (crawlers hit /setup), so it stays quiet. */
    public function test_visiting_the_wizard_after_setup_redirects_home_without_a_notice(): void
    {
        $this->makeAdmin();

        $this->assertRedirect($this->get('/setup'), '/');
        $this->assertDontSeeText($this->get('/'), 'Setup has already been completed');
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
