<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Feature-flag rollback safety (PHASE_2_PLAN §12). Every Phase 2 subsystem is
 * gated so an operator can "deploy dark" or roll a feature back via the `features`
 * setting without a data change. Disabling a flag must take its routes offline
 * (404) while the core forum keeps serving — and re-enabling restores it.
 */
final class AppFeatureFlagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_phase4_gate_a_flags_default_dark(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        foreach (['topic_workflow', 'group_dms', 'tags', 'expanded_feeds', 'reputation_ledger', 'badge_rules', 'community_memory'] as $flag) {
            self::assertFalse($flags->enabled($flag), "$flag should deploy dark by default");
        }

        $this->setFlags(['tags' => true]);
        $overridden = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($overridden->enabled('tags'));
        self::assertTrue($overridden->enabled('community'));
    }

    public function test_disabling_a_flag_takes_its_get_routes_offline_but_keeps_core_up(): void
    {
        $cases = [
            'notifications' => '/notifications',
            'search' => '/search',
            'dms' => '/messages',
            'community' => '/feed',
            'presence' => '/presence',
            'drafts' => '/drafts',
            'moderation_queue' => '/mod/reports',
            // /settings/connections is gated by the flag itself (the /auth/*
            // routes additionally require a configured provider, absent in tests).
            'oauth' => '/settings/connections',
        ];

        foreach ($cases as $flag => $path) {
            // Default (flag on): the route is NOT a 404 (200, redirect, or 405 —
            // anything but "feature absent").
            $on = $this->get($path);
            self::assertNotSame(404, $on->status(), "$path should be reachable while '$flag' is on");

            // Flag off: the route 404s, and the home page still works.
            $this->setFlags([$flag => false]);
            self::assertStatus(404, $this->get($path));
            $this->assertStatus(200, $this->get('/'));

            // Re-enable for the next case.
            $this->setFlags([$flag => true]);
        }
    }

    public function test_community_flag_gates_all_community_routes(): void
    {
        $this->setFlags(['community' => false]);
        $this->assertStatus(404, $this->get('/feed'));
        $this->assertStatus(404, $this->get('/leaderboard'));

        // A follow POST is also gated (404 before any write).
        $target = $this->makeUser(['username' => 'flagtarget']);
        $actor = $this->makeUser(['username' => 'flagactor']);
        $this->actingAs($actor);
        $this->assertStatus(404, $this->post('/u/flagtarget/follow'));
    }

    public function test_engagement_flag_gates_reaction_and_star_writes(): void
    {
        $board = $this->makeBoard($this->makeCategory());
        $user = $this->makeUser(['username' => 'flaguser']);
        $t = $this->makeThread($board, $user, 'Flagged');

        $this->actingAs($user);
        $this->setFlags(['engagement' => false]);
        $this->assertStatus(404, $this->post('/t/' . $t['thread_id'] . '/star'));
    }

    public function test_oauth_off_hides_connections(): void
    {
        // /settings/connections is gated purely by the oauth flag. (The /auth/*
        // provider routes 404 in tests regardless of the flag because no provider
        // is configured, so they can't prove flag gating here.)
        $user = $this->makeUser(['username' => 'flagoauth']);
        $this->actingAs($user);

        // Reachable (redirect to login or 200) while oauth is on…
        self::assertNotSame(404, $this->get('/settings/connections')->status());
        // …and 404 once the flag is off.
        $this->setFlags(['oauth' => false]);
        $this->assertStatus(404, $this->get('/settings/connections'));
    }
}
