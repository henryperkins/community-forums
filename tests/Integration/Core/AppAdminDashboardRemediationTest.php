<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppAdminDashboardRemediationTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'dashboard_admin']);
    }

    private function settings(): SettingRepository
    {
        return new SettingRepository($this->db);
    }

    public function test_general_settings_page_requires_an_administrator(): void
    {
        $this->assertStatus(302, $this->get('/admin/settings'));

        $member = $this->makeUser(['username' => 'settings_member']);
        $this->actingAs($member);
        $this->assertStatus(403, $this->get('/admin/settings'));

        $this->actingAs($this->admin);
        $response = $this->get('/admin/settings');
        $this->assertStatus(200, $response);
        $this->assertSeeText($response, 'General &amp; registration');
    }

    public function test_moderation_settings_page_requires_admin_and_respects_anti_abuse_gate(): void
    {
        $this->actingAs($this->admin);
        $this->assertStatus(200, $this->get('/admin/moderation'));

        $this->settings()->set('features', ['anti_abuse' => false]);
        $this->assertStatus(404, $this->get('/admin/moderation'));
        $this->assertStatus(404, $this->post('/admin/moderation', [
            'antiabuse_mode' => 'observe',
            'antiabuse_blocked_words' => '',
        ]));
    }

    public function test_custom_emoji_page_requires_admin_and_respects_feature_gate(): void
    {
        $this->actingAs($this->admin);
        $page = $this->get('/admin/custom-emoji');
        $this->assertStatus(200, $page);
        $this->assertSeeText($page, 'Custom emoji');

        $this->settings()->set('features', ['custom_emoji' => false]);
        $this->assertStatus(404, $this->get('/admin/custom-emoji'));
    }

    public function test_obsolete_combined_settings_post_is_not_routable(): void
    {
        $this->actingAs($this->admin);
        $this->get('/admin');

        $response = $this->post('/admin/settings', [
            'registration_mode' => 'closed',
            'antiabuse_mode' => 'block',
        ]);

        $this->assertStatus(404, $response);
    }

    public function test_shared_admin_navigation_has_approved_areas_destinations_and_active_state(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/settings');
        $body = $response->body();

        // ADR 0024: the console nav is two ranks, so one page no longer carries
        // every destination. It carries the eleven areas plus the active area's
        // own tabs. Each area links its first reachable tab.
        $areas = [
            'Overview',
            'Moderation',
            'Content',
            'People',
            'Members',
            'Appearance',
            'Notifications',
            'Integrations',
            'Packages',
            'Features',
            'Settings',
        ];
        self::assertSame(
            1,
            preg_match('~<nav class="admin-tier"[^>]*>(?<tier>.*?)</nav>~s', $body, $matches),
            'Missing the admin area tier',
        );
        $tier = $matches['tier'];

        $cursor = -1;
        foreach ($areas as $area) {
            $next = strpos($tier, '>' . $area . '<');
            self::assertNotFalse($next, 'Missing navigation area ' . $area);
            self::assertGreaterThan($cursor, $next, 'Navigation area order drifted at ' . $area);
            $cursor = $next;
        }

        foreach ([
            '/admin', '/mod/reports', '/admin/structure', '/admin/roles', '/admin/users',
            '/admin/branding', '/admin/email', '/admin/api-tokens', '/admin/packages',
            '/admin/features',
        ] as $destination) {
            self::assertMatchesRegularExpression(
                '~(?:href|data-destination)="' . preg_quote($destination, '~') . '"~',
                $tier,
                'Missing area destination ' . $destination,
            );
        }

        // Settings is the active area, so it is a span and its two tabs render.
        self::assertStringContainsString('<span class="admin-tier-item is-active" aria-current="page">Settings</span>', $tier);
        self::assertStringContainsString('aria-label="Settings sections"', $body);
        self::assertStringContainsString('<span class="admin-tab is-active" aria-current="page">General &amp; registration</span>', $body);
        self::assertStringContainsString('href="/admin/thread-intelligence"', $body);
    }

    public function test_shared_navigation_explains_feature_disabled_destinations(): void
    {
        $this->settings()->set('features', [
            'moderation_queue' => false,
            'appeals' => false,
            'anti_abuse' => false,
            'custom_emoji' => false,
            'server_extensions' => false,
        ]);
        $this->actingAs($this->admin);

        $disabled = static fn (string $label): string =>
            '~<span[^>]*aria-disabled="true"[^>]*>.*?' . preg_quote($label, '~')
            . '.*?Disabled until the feature flag is enabled.*?</span>~s';

        // Rank one: an area whose every tab is dark goes disabled in the tier —
        // explained, never silently removed. Moderation has no reachable tab left.
        $overview = $this->get('/admin')->body();
        self::assertMatchesRegularExpression($disabled('Moderation'), $overview);
        self::assertStringNotContainsString('href="/mod/reports"', $overview);

        // Rank two: an area that keeps at least one reachable tab stays a live
        // link, and explains the dark tabs inside its own strip.
        $features = $this->get('/admin/features')->body();
        self::assertMatchesRegularExpression($disabled('Custom emoji'), $features);
        self::assertStringNotContainsString('href="/admin/custom-emoji"', $features);

        $packages = $this->get('/admin/packages')->body();
        self::assertMatchesRegularExpression($disabled('Extensions'), $packages);
        self::assertStringNotContainsString('href="/admin/extensions"', $packages);
    }

    public function test_site_name_write_redirects_to_owner_and_changes_only_site_name(): void
    {
        $this->settings()->set('site_name', 'Old site');
        $this->settings()->set('registration_mode', 'closed');
        $this->settings()->set('antiabuse_mode', 'block');
        $this->settings()->set('antiabuse_blocked_words', ['sentinel']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/site', ['site_name' => 'New site']);

        $this->assertRedirect($response, '/admin/settings');
        self::assertSame('New site', $this->settings()->getString('site_name'));
        self::assertSame('closed', $this->settings()->getString('registration_mode'));
        self::assertSame('block', $this->settings()->getString('antiabuse_mode'));
        self::assertSame(['sentinel'], $this->settings()->get('antiabuse_blocked_words'));
    }

    public function test_registration_write_changes_only_registration_and_has_precise_audit(): void
    {
        $this->settings()->set('site_name', 'Sentinel site');
        $this->settings()->set('registration_mode', 'closed');
        $this->settings()->set('antiabuse_mode', 'block');
        $this->settings()->set('antiabuse_blocked_words', ['sentinel']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/settings/registration', ['registration_mode' => 'invite']);

        $this->assertRedirect($response, '/admin/settings');
        self::assertSame('invite', $this->settings()->getString('registration_mode'));
        self::assertSame('Sentinel site', $this->settings()->getString('site_name'));
        self::assertSame('block', $this->settings()->getString('antiabuse_mode'));
        self::assertSame(['sentinel'], $this->settings()->get('antiabuse_blocked_words'));

        $audit = $this->db->fetch("SELECT reason, before_json, after_json FROM moderation_log WHERE reason = 'registration_mode' ORDER BY id DESC LIMIT 1");
        self::assertNotNull($audit);
        self::assertSame(['registration_mode' => 'closed'], json_decode((string) $audit['before_json'], true));
        self::assertSame(['registration_mode' => 'invite'], json_decode((string) $audit['after_json'], true));
    }

    public function test_general_settings_uses_the_design_anatomy_without_merging_form_ownership(): void
    {
        $this->settings()->set('registration_mode', 'invite');
        $this->settings()->set('features', ['invitations' => false]);
        $this->actingAs($this->admin);

        $response = $this->get('/admin/settings');
        $this->assertStatus(200, $response);
        $body = $response->body();

        self::assertStringContainsString('<h1 class="admin-title">General &amp; intelligence</h1>', $body);
        self::assertStringContainsString(
            '<span class="admin-tab is-active" aria-current="page">General &amp; registration</span>',
            $body,
        );
        self::assertStringContainsString('<div class="settings-general-grid">', $body);
        self::assertSame(2, substr_count($body, 'settings-general-card'));
        self::assertSame(2, substr_count($body, 'settings-card-intro'));
        self::assertSame(2, substr_count($body, 'settings-field-label'));
        self::assertSame(2, substr_count($body, 'settings-actions'));
        self::assertStringContainsString('<h2 id="site-name-heading">Identity</h2>', $body);
        self::assertStringContainsString(
            'The name this community goes by — in the topbar, in every email, and on the sign-in page.',
            $body,
        );
        self::assertStringContainsString('>Save name</button>', $body);
        self::assertStringNotContainsString('class="pane-intro"', $body);

        self::assertSame(
            1,
            preg_match('~<form[^>]*action="/admin/site"[^>]*>(?<form>.*?)</form>~s', $body, $siteMatch),
            'Missing the site-name owner form',
        );
        self::assertSame(
            1,
            preg_match('~<form[^>]*action="/admin/settings/registration"[^>]*>(?<form>.*?)</form>~s', $body, $registrationMatch),
            'Missing the registration owner form',
        );
        self::assertSame(1, substr_count($body, 'action="/admin/site"'));
        self::assertSame(1, substr_count($body, 'action="/admin/settings/registration"'));
        self::assertStringNotContainsString('action="/admin/settings"', $body);

        preg_match_all('~\bname="([^"]+)"~', $siteMatch['form'], $siteNames);
        preg_match_all('~\bname="([^"]+)"~', $registrationMatch['form'], $registrationNames);
        self::assertSame(['_token', 'site_name'], $siteNames[1]);
        self::assertSame(['_token', 'registration_mode'], $registrationNames[1]);
        self::assertMatchesRegularExpression(
            '~<input(?=[^>]*name="site_name")(?=[^>]*maxlength="80")(?=[^>]*aria-describedby="site-name-help")(?=[^>]*\srequired(?:\s|>))[^>]*>~',
            $siteMatch['form'],
        );
        self::assertMatchesRegularExpression(
            '~<select(?=[^>]*name="registration_mode")(?=[^>]*aria-describedby="registration-help")[^>]*>~',
            $registrationMatch['form'],
        );

        $conflict = '<p class="settings-invite-conflict" role="alert">Registration mode is “invite” but the invitations feature is off — registration is effectively closed.</p>';
        self::assertStringContainsString($conflict, $body);
        $registrationLabel = strpos($body, 'for="admin-registration-mode"');
        self::assertNotFalse($registrationLabel);
        $registrationLabelEnd = strpos($body, '</label>', $registrationLabel);
        self::assertNotFalse($registrationLabelEnd);
        $conflictPosition = strpos($body, $conflict);
        self::assertNotFalse($conflictPosition);
        self::assertGreaterThan($registrationLabelEnd, $conflictPosition);
        self::assertStringNotContainsString('Invitations feature is enabled', $body);
        self::assertStringNotContainsString('type="checkbox"', $siteMatch['form'] . $registrationMatch['form']);
    }

    public function test_general_settings_validation_draft_cannot_repaint_the_sibling_form(): void
    {
        $this->settings()->set('site_name', 'Owner site');
        $this->settings()->set('registration_mode', 'invite');
        $this->settings()->set('features', ['invitations' => false]);
        $this->actingAs($this->admin);

        $siteDraft = str_repeat('N', 81);
        $site = $this->post('/admin/site', [
            'site_name' => $siteDraft,
            'registration_mode' => 'closed',
        ]);
        $this->assertStatus(422, $site);
        self::assertStringContainsString('value="' . $siteDraft . '"', $site->body());
        self::assertMatchesRegularExpression(
            '~<input(?=[^>]*name="site_name")(?=[^>]*aria-invalid="true")(?=[^>]*autofocus)[^>]*>~',
            $site->body(),
        );
        self::assertStringContainsString('<option value="invite" selected>', $site->body());
        self::assertStringNotContainsString('<option value="closed" selected>', $site->body());
        self::assertStringContainsString('class="settings-invite-conflict" role="alert"', $site->body());
        self::assertSame('Owner site', $this->settings()->getString('site_name'));
        self::assertSame('invite', $this->settings()->getString('registration_mode'));

        $registration = $this->post('/admin/settings/registration', [
            'registration_mode' => 'banana',
            'site_name' => 'Injected sibling site',
        ]);
        $this->assertStatus(422, $registration);
        self::assertMatchesRegularExpression(
            '~<input(?=[^>]*name="site_name")(?=[^>]*value="Owner site")[^>]*>~',
            $registration->body(),
        );
        self::assertStringNotContainsString('Injected sibling site', $registration->body());
        self::assertStringContainsString('<option value="banana" selected>', $registration->body());
        self::assertMatchesRegularExpression(
            '~<select(?=[^>]*name="registration_mode")(?=[^>]*aria-invalid="true")(?=[^>]*autofocus)[^>]*>~',
            $registration->body(),
        );
        self::assertSame('Owner site', $this->settings()->getString('site_name'));
        self::assertSame('invite', $this->settings()->getString('registration_mode'));
    }

    public function test_anti_abuse_write_changes_only_anti_abuse_and_has_precise_audit(): void
    {
        $this->settings()->set('site_name', 'Sentinel site');
        $this->settings()->set('registration_mode', 'closed');
        $this->settings()->set('antiabuse_mode', 'block');
        $this->settings()->set('antiabuse_blocked_words', ['beforeword']);
        $this->actingAs($this->admin);

        $response = $this->post('/admin/moderation', [
            'antiabuse_mode' => 'flag',
            'antiabuse_blocked_words' => "AfterWord\nsecondword",
        ]);

        $this->assertRedirect($response, '/admin/moderation');
        self::assertSame('flag', $this->settings()->getString('antiabuse_mode'));
        self::assertSame(['AfterWord', 'secondword'], $this->settings()->get('antiabuse_blocked_words'));
        self::assertSame('closed', $this->settings()->getString('registration_mode'));
        self::assertSame('Sentinel site', $this->settings()->getString('site_name'));

        $audit = $this->db->fetch("SELECT reason, before_json, after_json FROM moderation_log WHERE reason = 'anti_abuse_settings' ORDER BY id DESC LIMIT 1");
        self::assertNotNull($audit);
        self::assertSame([
            'antiabuse_mode' => 'block',
            'antiabuse_blocked_words' => ['beforeword'],
        ], json_decode((string) $audit['before_json'], true));
        self::assertSame([
            'antiabuse_mode' => 'flag',
            'antiabuse_blocked_words' => ['AfterWord', 'secondword'],
        ], json_decode((string) $audit['after_json'], true));
    }

    public function test_each_settings_validation_failure_renders_its_owner_with_draft(): void
    {
        $this->actingAs($this->admin);

        $siteDraft = str_repeat('N', 81);
        $site = $this->post('/admin/site', ['site_name' => $siteDraft]);
        $this->assertStatus(422, $site);
        $this->assertSeeText($site, 'General &amp; registration');
        $this->assertSeeText($site, 'Site name must be 1–80 characters.');
        $this->assertSeeText($site, $siteDraft);

        $registration = $this->post('/admin/settings/registration', ['registration_mode' => 'banana']);
        $this->assertStatus(422, $registration);
        $this->assertSeeText($registration, 'Unknown registration mode.');
        self::assertStringContainsString('<option value="banana" selected>', $registration->body());

        $moderation = $this->post('/admin/moderation', [
            'antiabuse_mode' => 'nuke',
            'antiabuse_blocked_words' => "keepthisword\nandthistoo",
        ]);
        $this->assertStatus(422, $moderation);
        $this->assertSeeText($moderation, 'Unknown anti-abuse mode.');
        $this->assertSeeText($moderation, 'keepthisword');
        self::assertStringContainsString('<option value="nuke" selected>', $moderation->body());
    }

    public function test_custom_emoji_validation_renders_catalogue_owner_with_all_typed_fields(): void
    {
        $this->actingAs($this->admin);

        $response = $this->post('/admin/custom-emoji', [
            'shortcode' => '!',
            'name' => 'Typed emoji name',
            'image_path' => '/emoji/typed.webp',
            'mime' => 'image/webp',
            'allow_reactions' => '1',
        ]);

        $this->assertStatus(422, $response);
        $this->assertSeeText($response, 'Use 2-40 lowercase letters, numbers, underscores, plus, or hyphen.');
        self::assertStringContainsString('value="!"', $response->body());
        self::assertStringContainsString('value="Typed emoji name"', $response->body());
        self::assertStringContainsString('value="/emoji/typed.webp"', $response->body());
        self::assertStringContainsString('name="allow_reactions" value="1" checked', $response->body());
    }

    public function test_dashboard_is_operational_only_and_has_required_order_and_labels(): void
    {
        $this->actingAs($this->admin);
        $body = $this->get('/admin')->body();

        $queue = strpos($body, '>Queue health<');
        $attention = strpos($body, '>Needs attention<');
        $community = strpos($body, '>Community today<');
        $activity = strpos($body, '>Recent activity<');
        self::assertNotFalse($queue);
        self::assertNotFalse($attention);
        self::assertNotFalse($community);
        self::assertNotFalse($activity);
        self::assertLessThan($attention, $queue);
        self::assertLessThan($community, $attention);
        self::assertLessThan($activity, $community);

        self::assertStringContainsString('New users today', $body);
        self::assertStringContainsString('Active now', $body);
        self::assertStringNotContainsString('queue-card-head">Users', $body);
        self::assertStringNotContainsString('queue-card-head">Audit', $body);
        self::assertMatchesRegularExpression(
            '~href="/admin/audit">View full audit log\s*<span aria-hidden="true">→</span></a>~',
            $body,
        );
        self::assertStringNotContainsString('action="/admin/site"', $body);
        self::assertStringNotContainsString('action="/admin/settings"', $body);
        self::assertStringNotContainsString('name="shortcode"', $body);
        self::assertStringContainsString('Scroll for Target and Reason', $body);
    }

    public function test_admin_overview_body_uses_the_approved_audit_register(): void
    {
        $this->actingAs($this->admin);
        for ($i = 0; $i < 8; $i++) {
            $this->db->run(
                "INSERT INTO moderation_log
                    (actor_id, action, target_type, target_id, reason, before_json, after_json, created_at)
                 VALUES (?, ?, 'setting', ?, ?, NULL, NULL, ?)",
                [
                    (int) $this->admin['id'],
                    'overview_marker_' . $i,
                    $i + 1,
                    'overview reason ' . $i,
                    sprintf('2026-08-04 12:%02d:00', $i),
                ],
            );
        }

        $body = $this->get('/admin')->body();

        self::assertStringContainsString(
            'Start with the live queues and health signals, then review what has changed across the community.',
            $body,
        );
        self::assertStringContainsString('queue-card-head">Reports open</span>', $body);
        self::assertSame(2, substr_count($body, 'class="card activity-card"'));
        self::assertSame(6, substr_count($body, 'overview_marker_'));
        self::assertStringContainsString('overview_marker_7', $body);
        self::assertStringContainsString('overview_marker_2', $body);
        self::assertStringNotContainsString('overview_marker_1', $body);
        self::assertStringNotContainsString('overview_marker_0', $body);
        self::assertStringContainsString(
            '<td class="audit-time nowrap"><time datetime="2026-08-04T12:07:00Z">Aug 4, 2026 at 12:07 UTC</time></td>',
            $body,
        );
        self::assertStringNotContainsString('>Waiting<', $body);
        self::assertStringNotContainsString('72-hour promise', $body);
        self::assertStringNotContainsString('oldest ', $body);
    }

    public function test_dashboard_queue_cards_expose_attention_clear_and_unavailable_states(): void
    {
        $this->settings()->set('features', [
            'moderation_queue' => false,
            'appeals' => false,
            'email' => false,
            'community_memory' => false,
            'automated_context' => false,
        ]);
        $category = $this->makeCategory('Pending category');
        $board = $this->makeBoard($category, ['slug' => 'pending-board']);
        $author = $this->makeUser(['username' => 'pending_author']);
        $thread = $this->makeThread($board, $author, 'Pending dashboard thread');
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [(int) $thread['thread_id']]);
        $this->actingAs($this->admin);

        $body = $this->get('/admin')->body();

        self::assertMatchesRegularExpression('~data-queue-status="unavailable"[^>]*>.*?Reports~s', $body);
        self::assertMatchesRegularExpression('~data-queue-status="unavailable"[^>]*>.*?Approval hold~s', $body);
        self::assertMatchesRegularExpression('~data-queue-status="unavailable"[^>]*>.*?Appeals~s', $body);
        self::assertMatchesRegularExpression('~data-queue-status="unavailable"[^>]*>.*?Email failures~s', $body);
        self::assertStringNotContainsString('href="/mod/approvals"', $body);
        self::assertStringNotContainsString('href="/mod/appeals"', $body);
        self::assertStringNotContainsString('>Thread Intelligence<', $body);
    }
}
