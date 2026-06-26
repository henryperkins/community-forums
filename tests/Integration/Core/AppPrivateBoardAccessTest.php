<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Hidden/private board read gates. Private boards are admin-only in Phase 1; the
 * gate is enforced before slug-change redirects so a private board's existence
 * is never revealed.
 */
final class AppPrivateBoardAccessTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;
    /** @var array<string,mixed> */
    private array $user;
    /** @var array<string,mixed> */
    private array $privateBoard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'boss']);
        $this->user = $this->makeUser(['username' => 'member']);
        $categoryId = $this->makeCategory('Staff');
        $this->privateBoard = $this->makeBoard($categoryId, ['slug' => 'secret', 'name' => 'Secret', 'visibility' => 'private']);
    }

    public function test_only_admin_can_read_a_private_board(): void
    {
        $this->assertStatus(404, $this->get('/c/secret')); // guest

        $this->actingAs($this->user);
        $this->assertStatus(404, $this->get('/c/secret')); // normal user

        $this->logoutClient();
        $this->actingAs($this->admin);
        $this->assertStatus(200, $this->get('/c/secret')); // admin
    }

    public function test_private_thread_is_hidden_from_non_admins(): void
    {
        $thread = $this->makeThread($this->privateBoard, $this->admin, 'Secret plans');
        $url = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        $this->assertStatus(404, $this->get($url)); // guest

        $this->actingAs($this->user);
        $this->assertStatus(404, $this->get($url)); // normal user

        $this->logoutClient();
        $this->actingAs($this->admin);
        $this->assertStatus(200, $this->get($url)); // admin
    }

    public function test_private_board_not_listed_for_non_admins(): void
    {
        // Also create a public board so the home page renders normally.
        $this->makeBoard($this->makeCategory('Public'), ['slug' => 'open', 'name' => 'Open']);

        $guestHome = $this->get('/');
        $this->assertDontSeeText($guestHome, 'Secret');

        $this->actingAs($this->admin);
        $adminHome = $this->get('/');
        $this->assertSeeText($adminHome, 'Secret');
    }

    public function test_hidden_board_is_readable_by_direct_link_but_not_listed(): void
    {
        $hidden = $this->makeBoard($this->makeCategory('Misc'), ['slug' => 'lounge', 'name' => 'Lounge', 'visibility' => 'hidden']);

        // Direct link works for guests and normal users (unlike private → 404).
        $this->assertStatus(200, $this->get('/c/lounge'));
        $this->actingAs($this->user);
        $this->assertStatus(200, $this->get('/c/lounge'));

        // But it is never listed on the home index.
        $this->logoutClient();
        $this->assertDontSeeText($this->get('/'), 'Lounge');
        $this->actingAs($this->user);
        $this->assertDontSeeText($this->get('/'), 'Lounge');
        // Even the admin does not see a hidden board listed.
        $this->logoutClient();
        $this->actingAs($this->admin);
        $this->assertDontSeeText($this->get('/'), 'Lounge');
    }

    public function test_slug_redirect_respects_the_private_gate(): void
    {
        // Rename the private board's slug (admin), creating slug history.
        $this->actingAs($this->admin);
        $this->get('/admin/boards/' . $this->privateBoard['id'] . '/edit');
        $this->post('/admin/boards/' . $this->privateBoard['id'], [
            'category_id' => $this->privateBoard['category_id'],
            'name' => 'Secret',
            'slug' => 'secret2',
            'visibility' => 'private',
        ]);

        // Admin: old slug 301-redirects to the new one.
        $adminHit = $this->get('/c/secret');
        $this->assertRedirect($adminHit, '/c/secret2');

        // Non-admin: the old slug must NOT redirect (would leak existence) → 404.
        $this->logoutClient();
        $this->actingAs($this->user);
        $this->assertStatus(404, $this->get('/c/secret'));
    }
}
