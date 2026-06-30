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

    public function test_phase5_foundation_flags_default_dark(): void
    {
        // The Phase 5 ecosystem/identity/governance subsystems deploy dark until
        // their Milestone-0 trust approvals + acceptance evidence land
        // (PHASE_5_PLAN §2/§13). The 0049–0053 foundation migrations are additive
        // and inert; no behavior may turn on merely because the tables exist.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $phase5 = [
            // Gate A
            'package_registry', 'package_themes', 'capabilities', 'passkeys',
            'provider_registry', 'invitations', 'service_secrets', 'api_tokens', 'webhooks', 'first_party_hooks',
            // Gate B (reserved)
            'server_extensions', 'governance', 'service_principals', 'verified_links',
        ];
        foreach ($phase5 as $flag) {
            self::assertFalse($flags->enabled($flag), "$flag should deploy dark by default");
        }
        self::assertArrayHasKey('service_secrets', $flags->all(), 'service_secrets must be a declared flag, not an unknown-key false');
        self::assertArrayHasKey('api_tokens', $flags->all(), 'api_tokens must be a declared flag');
        self::assertArrayHasKey('webhooks', $flags->all(), 'webhooks must be a declared flag');
        self::assertArrayHasKey('first_party_hooks', $flags->all(), 'first_party_hooks must be a declared flag');

        // The override seam still works per-flag without affecting its neighbours.
        $this->setFlags(['capabilities' => true]);
        $overridden = new FeatureFlags(new SettingRepository($this->db));
        self::assertTrue($overridden->enabled('capabilities'));
        self::assertFalse($overridden->enabled('passkeys'), 'enabling one Phase 5 flag must not enable others');
    }

    public function test_appeals_and_account_lifecycle_carryovers_default_dark(): void
    {
        // ADR 0007 (appeals) + ADR 0006 (account lifecycle/export/delete) ship as
        // deploy-dark carryovers: their routes must be offline by default until
        // browser/a11y/runbook acceptance evidence is attached.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        foreach (['appeals', 'account_lifecycle'] as $flag) {
            self::assertFalse($flags->enabled($flag), "$flag should deploy dark by default");
            self::assertArrayHasKey($flag, $flags->all(), "$flag must be a declared flag, not an unknown-key false");
        }

        $member = $this->makeUser(['username' => 'darkcarryovermember']);
        $this->actingAs($member);

        // Appeals member + staff routes are 404 while the flag is dark.
        $this->assertStatus(404, $this->get('/appeals'));
        $this->assertStatus(404, $this->post('/appeals/posts/1', ['reason' => 'x']));
        $this->assertStatus(404, $this->get('/mod/appeals'));

        // Account lifecycle/export/delete routes are 404 while the flag is dark…
        $this->assertStatus(404, $this->post('/settings/account/export'));
        $this->assertStatus(404, $this->get('/settings/account/lifecycle'));
        $this->assertStatus(404, $this->post('/settings/account/deactivate', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/account/reactivate'));
        $this->assertStatus(404, $this->post('/settings/account/delete/request', ['current_password' => 'x']));
        $this->assertStatus(404, $this->post('/settings/account/delete/cancel'));

        // …but core profile editing is NOT part of the dark slice and stays up.
        self::assertNotSame(404, $this->get('/settings/account')->status(), 'core profile editing must stay available');

        // The override seam still re-enables each carryover independently.
        $this->setFlags(['account_lifecycle' => true]);
        self::assertNotSame(404, $this->get('/settings/account/lifecycle')->status());
        $this->assertStatus(404, $this->get('/appeals'), 'enabling account_lifecycle must not enable appeals');
    }

    public function test_group_dms_flag_gates_group_creation_and_management(): void
    {
        // group_dms defaults dark; the legacy `dms` flag defaults on. Group DMs
        // must NOT ship live just because dms is on (PR #17 regression guard).
        $owner = $this->makeUser(['username' => 'gdmowner']);
        $this->makeUser(['username' => 'gdmbob']);
        $this->makeUser(['username' => 'gdmcarol']);
        // Give the owner a post so they clear the new-account DM anti-spam throttle.
        $this->makeThread($this->makeBoard($this->makeCategory()), $owner, 'Hi', 'establishing a post.');
        $this->actingAs($owner);

        // A 1:1 direct message still works while group_dms is dark.
        $direct = $this->post('/messages', ['to' => 'gdmbob', 'body' => 'hello there']);
        self::assertLessThan(400, $direct->status(), '1:1 DM must stay available while group_dms is dark');

        // A group create (extra recipient + title) is refused server-side and
        // creates no group conversation.
        $this->assertStatus(422, $this->post('/messages', ['to' => 'gdmbob, gdmcarol', 'title' => 'Room', 'body' => 'hi all']));
        self::assertSame(
            0,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM conversations WHERE kind = 'group'"),
            'no group conversation may be created while group_dms is dark',
        );

        // Group-management routes 404 (the flag gate fires before any lookup).
        $this->assertStatus(404, $this->post('/messages/1/members', ['username' => 'gdmcarol']));
        $this->assertStatus(404, $this->post('/messages/1/members/remove', ['user_id' => 1]));
        $this->assertStatus(404, $this->post('/messages/1/rename', ['title' => 'x']));
        $this->assertStatus(404, $this->post('/messages/1/transfer', ['user_id' => 1]));

        // Enabling the flag lets a group be created.
        $this->setFlags(['group_dms' => true]);
        $ok = $this->post('/messages', ['to' => 'gdmbob, gdmcarol', 'title' => 'Room', 'body' => 'hi again']);
        self::assertLessThan(400, $ok->status(), 'group creation should succeed once group_dms is on');
        self::assertSame(
            1,
            (int) $this->db->fetchValue("SELECT COUNT(*) FROM conversations WHERE kind = 'group'"),
            'enabling group_dms permits group creation',
        );
    }

    public function test_tags_flag_gates_public_and_admin_tag_routes(): void
    {
        $admin = $this->makeAdmin(['username' => 'flagtagsadmin']);
        $this->setFlags(['tags' => false]);

        $this->assertStatus(404, $this->get('/tags'));
        $this->assertStatus(404, $this->get('/tags/anything'));

        $this->actingAs($admin);
        $this->assertStatus(404, $this->get('/admin/tags'));
        $this->assertStatus(404, $this->post('/admin/tags', ['name' => 'Hidden']));
        $this->assertStatus(404, $this->post('/admin/tags/1', ['name' => 'Hidden', 'slug' => 'hidden']));
        $this->assertStatus(404, $this->post('/admin/tags/1/merge', ['target_id' => 2]));
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

    public function test_announcements_flag_and_rate_limit_are_declared(): void
    {
        // The announcements subsystem is a Phase-2 surface: declared + default ON.
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('announcements', $flags->all(), 'announcements must be a declared flag, not an unknown-key false');
        self::assertTrue($flags->enabled('announcements'), 'announcements defaults on (Phase-2 convention)');

        // The broadcast cap needs a real policy (RateLimitService no-ops on unknown names).
        $limits = (array) $this->config->get('rate_limits', []);
        self::assertArrayHasKey('announce', $limits);
        self::assertCount(2, (array) $limits['announce']);
    }

    public function test_announcements_flag_takes_admin_routes_dark(): void
    {
        $admin = $this->makeAdmin(['username' => 'annflagroutes']);
        $this->actingAs($admin);

        // Reachable while the flag is on (default).
        self::assertNotSame(404, $this->get('/admin/announcements')->status());

        // 404 once the flag is off — the GET form and the POST both go dark.
        $this->setFlags(['announcements' => false]);
        $this->assertStatus(404, $this->get('/admin/announcements'));
        $this->assertStatus(404, $this->post('/admin/announcements', ['message' => 'Hidden']));

        // The home page still serves while the flag is off.
        $this->assertStatus(200, $this->get('/'));
    }

    public function test_email_flag_gates_admin_email_routes(): void
    {
        // The email-ops dashboard is gated by the `email` flag (declared, default ON).
        $flags = new FeatureFlags(new SettingRepository($this->db));
        self::assertArrayHasKey('email', $flags->all(), 'email must be a declared flag, not an unknown-key false');

        $this->actingAs($this->makeAdmin(['username' => 'flagemailadmin']));

        // Flag on (default): the dashboard is reachable.
        self::assertNotSame(404, $this->get('/admin/email')->status());

        // Flag off: every route 404s (the gate fires right after requireAdmin).
        $this->setFlags(['email' => false]);
        $this->assertStatus(404, $this->get('/admin/email'));
        $this->assertStatus(404, $this->get('/admin/email/export'));
        $this->assertStatus(404, $this->post('/admin/email/test', []));
        $this->assertStatus(404, $this->post('/admin/email/suppressions', ['email' => 'x@example.test']));
        $this->assertStatus(404, $this->post('/admin/email/suppressions/remove', ['email' => 'x@example.test']));
    }
}
