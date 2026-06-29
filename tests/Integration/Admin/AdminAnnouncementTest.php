<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AdminAnnouncementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Seed a baseline admin so the first-run setup gate is satisfied; the
        // non-admin case below otherwise leaves the site uninitialised and the
        // POST is bounced to /setup before it can reach the 403.
        $this->makeAdmin();
    }

    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_admin_can_load_the_compose_form(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annform']));
        $resp = $this->get('/admin/announcements');
        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'name="message"');
        $this->assertSeeText($resp, 'name="broadcast"');
    }

    public function test_admin_publish_shows_banner_to_guest_and_returns_200(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annpub']));
        $resp = $this->post('/admin/announcements', ['message' => 'Read-only window at 02:00 UTC', 'dismissible' => '1']);
        $this->assertRedirectContains($resp, '/admin/announcements');

        $this->logoutClient();
        $guest = $this->get('/');
        $this->assertStatus(200, $guest);
        $this->assertSeeText($guest, 'Read-only window at 02:00 UTC');
    }

    public function test_publish_with_broadcast_notifies_members_and_appears_at_notifications(): void
    {
        $admin = $this->makeAdmin(['username' => 'annbcadmin2']);
        $reader = $this->makeUser(['username' => 'annbcreader2']);

        $this->actingAs($admin);
        $this->post('/admin/announcements', ['message' => 'All hands at noon', 'broadcast' => '1']);

        $this->actingAs($reader);
        $list = $this->get('/notifications');
        $this->assertStatus(200, $list);
        $this->assertSeeText($list, 'Announcement');
    }

    public function test_clear_deactivates_the_banner(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annclear2']));
        $this->post('/admin/announcements', ['message' => 'Temporary outage', 'dismissible' => '1']);
        $this->assertSeeText($this->get('/'), 'Temporary outage');

        $this->post('/admin/announcements', ['action' => 'clear']);
        $cleared = $this->get('/');
        $this->assertStatus(200, $cleared);
        $this->assertDontSeeText($cleared, 'Temporary outage');
    }

    public function test_empty_message_re_renders_form_at_422(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annempty2']));
        $resp = $this->post('/admin/announcements', ['message' => '   ']);
        $this->assertStatus(422, $resp);
        $this->assertSeeText($resp, 'Announcement message must be');
    }

    public function test_non_admin_post_is_forbidden(): void
    {
        $this->actingAs($this->makeUser(['username' => 'annnonadmin']));
        $this->assertStatus(403, $this->post('/admin/announcements', ['message' => 'Nope']));
    }

    public function test_missing_csrf_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'anncsrf']));
        $this->assertStatus(403, $this->post('/admin/announcements', ['message' => 'No token'], false));
    }

    public function test_publish_is_rate_limited(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annrl']));
        for ($i = 0; $i < 5; $i++) {
            $this->assertContains(
                $this->post('/admin/announcements', ['message' => 'Notice ' . $i])->status(),
                [302, 303],
            );
        }
        $this->assertStatus(429, $this->post('/admin/announcements', ['message' => 'Too many']));
    }

    public function test_flag_off_takes_routes_dark(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'annoff']));
        $this->setFlags(['announcements' => false]);
        $this->assertStatus(404, $this->get('/admin/announcements'));
        $this->assertStatus(404, $this->post('/admin/announcements', ['message' => 'Hidden']));
    }
}
