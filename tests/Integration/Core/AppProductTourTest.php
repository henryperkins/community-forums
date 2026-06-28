<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * P3-11: the onboarding tour. Completion persists server-side (cross-device),
 * replay clears it, a new signed-in user is offered the tour, and guests/no-JS
 * users are never blocked.
 */
final class AppProductTourTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_new_user_is_offered_the_tour_then_completion_persists(): void
    {
        $user = $this->makeUser(['username' => 'newbie']);
        $this->actingAs($user);

        // Fresh account → the shell asks the enhancement to run the tour.
        $home = $this->get('/');
        $this->assertSeeText($home, 'data-tour="1"');
        $this->assertSeeText($home, '/assets/tour.js');

        // Completing it persists server-side.
        $res = $this->post('/onboarding/complete');
        $this->assertRedirect($res);
        self::assertNotNull($this->users()->find((int) $user['id'])['onboarded_at']);

        // Now the shell no longer requests the tour (carries across devices).
        $this->assertDontSeeText($this->get('/'), 'data-tour="1"');
    }

    public function test_replay_clears_completion(): void
    {
        $user = $this->makeUser(['username' => 'replayer']);
        $this->actingAs($user);
        $this->post('/onboarding/complete');
        self::assertNotNull($this->users()->find((int) $user['id'])['onboarded_at']);

        $this->post('/onboarding/replay');
        self::assertNull($this->users()->find((int) $user['id'])['onboarded_at']);
        $this->assertSeeText($this->get('/'), 'data-tour="1"');
    }

    public function test_settings_renders_replay_entry_point(): void
    {
        $user = $this->makeUser(['username' => 'replaylink']);
        $this->actingAs($user);
        $this->post('/onboarding/complete');

        $settings = $this->get('/settings/account');
        $this->assertStatus(200, $settings);
        $this->assertSeeText($settings, 'data-tour-replay');
        $this->assertSeeText($settings, 'Replay tour');
    }

    public function test_guest_is_not_offered_the_tour(): void
    {
        $this->assertDontSeeText($this->get('/'), 'data-tour="1"');
    }

    public function test_complete_requires_login(): void
    {
        $res = $this->post('/onboarding/complete');
        // No session → CSRF/auth rejects it; nothing is recorded.
        self::assertNotSame(200, $res->status());
    }

    public function test_tour_endpoints_are_inert_when_the_subsystem_is_disabled(): void
    {
        (new \App\Repository\SettingRepository($this->db))->set('features', ['product_tour' => false]);
        $user = $this->makeUser(['username' => 'noTour']);
        $this->actingAs($user);

        $this->assertStatus(404, $this->post('/onboarding/complete'));
        $this->assertStatus(404, $this->post('/onboarding/replay'));
        $this->assertDontSeeText($this->get('/settings/account'), 'data-tour-replay');
        // The flag being off must not have recorded completion.
        self::assertNull($this->users()->find((int) $user['id'])['onboarded_at']);
    }
}
