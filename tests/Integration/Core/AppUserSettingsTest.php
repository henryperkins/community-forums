<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Security\PasswordHasher;
use Tests\Support\TestCase;

final class AppUserSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_user_updates_profile_basics(): void
    {
        $user = $this->makeUser(['username' => 'jane']);
        $this->actingAs($user);
        $this->get('/settings/account');
        $response = $this->post('/settings/account', [
            'display_name' => 'Jane Q',
            'bio' => 'I like **forums**.',
            'location' => 'Springfield',
        ]);
        $this->assertRedirect($response, '/settings/account');

        $row = $this->users()->find((int) $user['id']);
        self::assertSame('Jane Q', $row['display_name']);
        self::assertSame('Springfield', $row['location']);
        self::assertStringContainsString('forums', (string) $row['bio']);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = $this->makeUser(['username' => 'phil', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/security');

        $wrong = $this->post('/settings/security', [
            'current_password' => 'incorrect',
            'new_password' => 'newpassword456',
            'new_password_confirm' => 'newpassword456',
        ]);
        $this->assertStatus(422, $wrong);
        $this->assertSeeText($wrong, 'current password is incorrect');

        $ok = $this->post('/settings/security', [
            'current_password' => 'password123',
            'new_password' => 'newpassword456',
            'new_password_confirm' => 'newpassword456',
        ]);
        $this->assertRedirect($ok, '/settings/security');

        $hash = $this->users()->find((int) $user['id'])['password_hash'];
        self::assertTrue((new PasswordHasher())->verify('newpassword456', $hash));
    }

    public function test_public_profile_shows_public_fields_but_never_email(): void
    {
        $user = $this->makeUser([
            'username' => 'publicguy',
            'display_name' => 'Public Guy',
            'email' => 'secret@example.test',
        ]);
        $this->users()->updateProfile((int) $user['id'], 'Public Guy', 'My bio here', 'Lakeside');

        $response = $this->get('/u/publicguy');
        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'Public Guy');
        $this->assertSeeText($response, '@publicguy');
        $this->assertSeeText($response, 'My bio here');
        $this->assertSeeText($response, 'Lakeside');
        $this->assertSeeText($response, 'Regard');   // Imladris reputation noun (§5.4)
        $this->assertDontSeeText($response, 'secret@example.test');
    }

    public function test_unknown_profile_is_404(): void
    {
        $this->assertStatus(404, $this->get('/u/ghost'));
    }

    public function test_guest_cannot_access_settings(): void
    {
        $this->assertRedirectContains($this->get('/settings/account'), '/login');
        $this->assertRedirectContains($this->get('/settings/security'), '/login');
        $this->assertRedirectContains($this->get('/settings'), '/login');
    }
}
