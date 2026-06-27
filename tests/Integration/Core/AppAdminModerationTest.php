<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * P3-05 operator controls: board approval holds, the enforced anti-abuse posture
 * (mode + admin-managed blocked words), and registration mode. Closes the
 * "admin controls absent / registration_mode is a dead setting" Gate A findings.
 */
final class AppAdminModerationTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->categoryId = $this->makeCategory('General');
    }

    private function settings(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    public function test_admin_can_set_board_require_approval_and_it_holds_posts(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin/structure'); // seed CSRF

        $this->post('/admin/boards', [
            'category_id' => $this->categoryId,
            'name' => 'Moderated',
            'slug' => 'moderated',
            'visibility' => 'public',
            'require_approval' => '1',
        ]);
        $board = $this->boards()->findBySlug('moderated');
        self::assertSame(1, (int) $board['require_approval']);

        // The edit form reflects the current state.
        $edit = $this->get('/admin/boards/' . $board['id'] . '/edit');
        $this->assertSeeText($edit, 'name="require_approval" value="1" checked');

        // A normal member's new thread is held pending approval.
        $poster = $this->makeUser(['username' => 'poster']);
        $t = $this->makeThread($board, $poster, 'Held thread');
        self::assertSame(1, (int) $this->db->fetchValue('SELECT is_pending FROM threads WHERE id = ?', [$t['thread_id']]));

        // Toggling it off persists.
        $this->post('/admin/boards/' . $board['id'], [
            'category_id' => $this->categoryId,
            'name' => 'Moderated',
            'slug' => 'moderated',
            'visibility' => 'public',
            // require_approval unchecked → off
        ]);
        self::assertSame(0, (int) $this->boards()->findBySlug('moderated')['require_approval']);
    }

    public function test_admin_can_set_antiabuse_mode_and_blocked_words(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin'); // seed CSRF

        $res = $this->post('/admin/settings', [
            'registration_mode' => 'open',
            'antiabuse_mode' => 'block',
            'antiabuse_blocked_words' => "spammyword\nBadWord, evil",
        ]);
        $this->assertRedirect($res, '/admin');

        self::assertSame('block', $this->settings()->getString('antiabuse_mode'));
        $words = (array) $this->settings()->get('antiabuse_blocked_words', []);
        self::assertContains('spammyword', $words);
        self::assertContains('BadWord', $words); // original case preserved
        self::assertContains('evil', $words);    // comma-separated parsed

        // An invalid mode falls back to the safe default rather than persisting junk.
        $this->post('/admin/settings', ['antiabuse_mode' => 'nuke', 'registration_mode' => 'open']);
        self::assertSame('observe', $this->settings()->getString('antiabuse_mode'));

        // The dashboard renders the saved blocked words back into the textarea.
        $this->post('/admin/settings', ['antiabuse_mode' => 'flag', 'registration_mode' => 'open', 'antiabuse_blocked_words' => 'visibleword']);
        $this->assertSeeText($this->get('/admin'), 'visibleword');
    }

    public function test_registration_mode_closed_blocks_signups(): void
    {
        // Admin closes registration.
        $this->actingAs($this->admin);
        $this->get('/admin');
        $this->post('/admin/settings', ['registration_mode' => 'closed', 'antiabuse_mode' => 'observe']);
        self::assertSame('closed', $this->settings()->getString('registration_mode'));
        $this->logoutClient();

        // The form shows the closed notice and a sign-up attempt is rejected.
        $this->assertSeeText($this->get('/register'), 'sign-ups are currently closed');
        $blocked = $this->post('/register', [
            'username' => 'wouldbe',
            'email' => 'wouldbe@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertStatus(403, $blocked);
        self::assertNull($this->users()->findByUsername('wouldbe'));
    }

    public function test_registration_reopens_and_signups_work_again(): void
    {
        $this->settings()->set('registration_mode', 'closed');
        // Reopen via the admin form.
        $this->actingAs($this->admin);
        $this->get('/admin');
        $this->post('/admin/settings', ['registration_mode' => 'open', 'antiabuse_mode' => 'observe']);
        $this->logoutClient();

        $this->get('/register');
        $ok = $this->post('/register', [
            'username' => 'fresh',
            'email' => 'fresh@example.test',
            'password' => 'password123',
            'password_confirm' => 'password123',
        ]);
        $this->assertRedirect($ok, '/');
        self::assertNotNull($this->users()->findByUsername('fresh'));
    }

    public function test_non_admin_cannot_change_moderation_settings(): void
    {
        $user = $this->makeUser(['username' => 'nobody']);
        $this->actingAs($user);
        $this->get('/'); // seed CSRF
        $this->assertStatus(403, $this->post('/admin/settings', ['registration_mode' => 'closed']));
        // The setting is unchanged (still the install default / unset).
        self::assertNotSame('closed', $this->settings()->getString('registration_mode', 'open'));
    }
}
