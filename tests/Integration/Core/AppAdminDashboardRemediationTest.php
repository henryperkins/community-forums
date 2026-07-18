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
        $this->assertSeeText($response, 'General & registration');
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

    public function test_shared_admin_navigation_has_approved_groups_destinations_and_active_state(): void
    {
        $this->actingAs($this->admin);
        $response = $this->get('/admin/settings');
        $body = $response->body();

        $groups = [
            'Dashboard',
            'Moderation',
            'Content',
            'People',
            'Appearance',
            'Notifications',
            'Integrations',
            'Settings',
        ];
        $cursor = -1;
        foreach ($groups as $group) {
            $next = strpos($body, 'class="admin-nav-group-title">' . $group . '<');
            self::assertNotFalse($next, 'Missing navigation group ' . $group);
            self::assertGreaterThan($cursor, $next, 'Navigation group order drifted at ' . $group);
            $cursor = $next;
        }

        foreach ([
            '/admin', '/mod/reports', '/mod/approvals', '/mod/appeals', '/admin/audit', '/admin/moderation',
            '/admin/structure', '/admin/tags', '/admin/users', '/admin/roles', '/admin/invitations',
            '/admin/badge-rules', '/admin/branding', '/admin/themes', '/admin/custom-emoji',
            '/admin/email', '/admin/announcements', '/admin/packages', '/admin/registries',
            '/admin/webhooks', '/admin/api-tokens', '/admin/providers', '/admin/extensions',
            '/admin/settings', '/admin/features', '/admin/thread-intelligence',
        ] as $destination) {
            self::assertMatchesRegularExpression(
                '~(?:href|data-destination)="' . preg_quote($destination, '~') . '"~',
                $body,
                'Missing admin destination ' . $destination,
            );
        }

        self::assertMatchesRegularExpression(
            '~<a[^>]*href="/admin/settings"[^>]*class="[^"]*active[^"]*"[^>]*aria-current="page"~',
            $body,
        );
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

        $body = $this->get('/admin')->body();

        foreach (['Reports', 'Approvals', 'Appeals', 'Anti-abuse', 'Custom emoji', 'Extensions'] as $label) {
            self::assertMatchesRegularExpression(
                '~<span[^>]*aria-disabled="true"[^>]*>.*?' . preg_quote($label, '~') . '.*?Disabled until the feature flag is enabled.*?</span>~s',
                $body,
            );
        }
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
        $this->assertSeeText($site, 'General & registration');
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
        self::assertStringContainsString('href="/admin/audit">View full audit log</a>', $body);
        self::assertStringNotContainsString('action="/admin/site"', $body);
        self::assertStringNotContainsString('action="/admin/settings"', $body);
        self::assertStringNotContainsString('name="shortcode"', $body);
        self::assertStringContainsString('Scroll for Target and Reason', $body);
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
