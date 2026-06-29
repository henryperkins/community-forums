<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppAnnouncementBannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // An admin must exist so the setup gate treats the app as initialized
        // and '/' renders the shell instead of redirecting to /setup.
        $this->makeAdmin(['username' => 'bannerinit']);
    }

    private function setBanner(mixed $value): void
    {
        (new SettingRepository($this->db))->set('site_announcement', $value);
    }

    public function test_active_banner_renders_in_shell_for_guest_and_member(): void
    {
        $this->setBanner(['active' => true, 'message' => 'Scheduled maintenance tonight', 'dismissible' => true, 'version' => 3]);

        $guest = $this->get('/');
        $this->assertStatus(200, $guest);
        $this->assertSeeText($guest, 'Scheduled maintenance tonight');
        $this->assertSeeText($guest, 'data-announcement-version="3"');

        $member = $this->makeUser(['username' => 'bannermember']);
        $this->actingAs($member);
        $signedIn = $this->get('/');
        $this->assertStatus(200, $signedIn);
        $this->assertSeeText($signedIn, 'Scheduled maintenance tonight');
    }

    public function test_inactive_banner_is_not_rendered(): void
    {
        $this->setBanner(['active' => false, 'version' => 1]);
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, 'site-announcement-message');
    }

    public function test_malformed_announcement_value_never_500s_the_shell(): void
    {
        // A garbled value (not an array) must default to null, not break the shell.
        $this->setBanner('not-an-array');
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, 'site-announcement-message');
    }

    public function test_message_is_html_escaped_in_the_banner(): void
    {
        $this->setBanner(['active' => true, 'message' => 'Heads up <script>x</script>', 'dismissible' => false, 'version' => 1]);
        $resp = $this->get('/');
        $this->assertStatus(200, $resp);
        $this->assertDontSeeText($resp, '<script>x</script>');
        $this->assertSeeText($resp, 'Heads up &lt;script&gt;');
    }
}
