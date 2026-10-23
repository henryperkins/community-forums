<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

final class AppSettingsEnhancementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_both_replay_controls_are_hidden_until_javascript_enhances_them(): void
    {
        $this->actingAs($this->makeUser());
        $page = $this->get('/settings/account');
        $this->assertStatus(200, $page);

        $document = new \DOMDocument();
        @$document->loadHTML($page->body());
        $dom = new \DOMXPath($document);

        self::assertSame(2, $dom->query('//button[@data-tour-replay]')->length);
        self::assertSame(2, $dom->query('//button[@data-tour-replay and @hidden]')->length);
    }

    public function test_connections_explanation_tracks_password_availability(): void
    {
        $id = $this->users()->create([
            'username' => 'connectioncopy', 'email' => 'connectioncopy@example.test',
            'password_hash' => null, 'display_name' => null, 'role' => 'user', 'status' => 'active',
        ]);
        $this->actingAs($this->users()->find($id));

        $passwordless = $this->get('/settings/connections');
        $this->assertStatus(200, $passwordless);
        self::assertStringContainsString('Set a password below to add email and password sign-in.', $passwordless->body());
        self::assertStringNotContainsString('You can also sign in with your email and password.', $passwordless->body());
        self::assertStringContainsString('action="/settings/connections/set-password"', $passwordless->body());

        $refused = $this->post('/settings/connections/set-password', [
            'new_password' => 'short', 'new_password_confirm' => 'short',
        ]);
        $this->assertStatus(422, $refused);
        self::assertStringContainsString('Set a password below to add email and password sign-in.', $refused->body());
        self::assertStringNotContainsString('You can also sign in with your email and password.', $refused->body());

        $this->assertRedirect($this->post('/settings/connections/set-password', [
            'new_password' => 'connection-password', 'new_password_confirm' => 'connection-password',
        ]), '/settings/connections');

        $withPassword = $this->get('/settings/connections');
        $this->assertStatus(200, $withPassword);
        self::assertStringContainsString('You can also sign in with your email and password.', $withPassword->body());
        self::assertStringNotContainsString('Set a password below to add email and password sign-in.', $withPassword->body());
        self::assertStringNotContainsString('action="/settings/connections/set-password"', $withPassword->body());
    }
}
