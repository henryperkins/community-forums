<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppCustomEmojiGiphyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_custom_emoji_is_available_by_default_and_operator_rollback_regates_admin_routes(): void
    {
        $admin = $this->makeAdmin(['username' => 'emoji_default_admin']);
        $this->actingAs($admin);

        $dashboard = $this->get('/admin');
        $this->assertStatus(200, $dashboard);
        self::assertStringContainsString('Custom emoji', $dashboard->body());
        self::assertStringContainsString('name="shortcode"', $dashboard->body());

        $this->assertRedirect($this->post('/admin/custom-emoji', [
            'shortcode' => 'party',
            'name' => 'Party',
            'image_path' => '/emoji/party.webp',
            'mime' => 'image/webp',
        ]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM custom_emoji'));

        (new SettingRepository($this->db))->set('features', ['custom_emoji' => false]);
        $disabledDashboard = $this->get('/admin');
        $this->assertStatus(200, $disabledDashboard);
        self::assertStringNotContainsString('name="shortcode"', $disabledDashboard->body());

        $res = $this->post('/admin/custom-emoji', [
            'shortcode' => 'wave',
            'name' => 'Wave',
            'image_path' => '/emoji/wave.webp',
            'mime' => 'image/webp',
        ]);
        $this->assertStatus(404, $res);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM custom_emoji'));
    }

    public function test_invalid_emoji_input_rerenders_dashboard_422_with_typed_values(): void
    {
        // Anti-draft-loss (round-2 audit finding 8a): the failure used to
        // redirect to /admin and drop every typed field.
        (new SettingRepository($this->db))->set('features', ['custom_emoji' => true]);
        $this->actingAs($this->makeAdmin(['username' => 'emoji_form_admin']));

        $res = $this->post('/admin/custom-emoji', [
            'shortcode' => 'X!',
            'name' => 'Typed Name',
            'image_path' => '/emoji/typed.webp',
            'mime' => 'image/webp',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('Use 2-40 lowercase letters', $res->body());
        self::assertMatchesRegularExpression('/name="name"[^>]*value="Typed Name"/', $res->body());
        self::assertMatchesRegularExpression('~name="image_path"[^>]*value="/emoji/typed\.webp"~', $res->body());
        // The errored input is wired to its error line like every other admin form.
        self::assertMatchesRegularExpression('/name="shortcode"[^>]*aria-describedby="err-emoji-shortcode"/', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM custom_emoji'));
    }

    public function test_duplicate_shortcode_says_replaced_instead_of_silently_upserting(): void
    {
        // Round-2 audit finding 9: replacing an existing emoji flashed the same
        // "saved" copy as a fresh create.
        (new SettingRepository($this->db))->set('features', ['custom_emoji' => true]);
        $this->actingAs($this->makeAdmin(['username' => 'emoji_dupe_admin']));

        $this->assertRedirect($this->post('/admin/custom-emoji', [
            'shortcode' => 'dupe', 'name' => 'First', 'image_path' => '/emoji/first.webp', 'mime' => 'image/webp',
        ]));
        $this->assertRedirect($this->post('/admin/custom-emoji', [
            'shortcode' => 'dupe', 'name' => 'Second', 'image_path' => '/emoji/second.webp', 'mime' => 'image/webp',
        ]));

        self::assertStringContainsString('replaced', $this->get('/admin')->body());
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM custom_emoji'));
        self::assertSame('Second', (string) $this->db->fetchValue("SELECT name FROM custom_emoji WHERE shortcode = 'dupe'"));
    }

    public function test_custom_emoji_renders_through_markdown_and_can_be_used_as_reaction(): void
    {
        (new SettingRepository($this->db))->set('features', ['custom_emoji' => true]);
        $admin = $this->makeAdmin(['username' => 'emoji_admin']);
        $this->actingAs($admin);

        $this->assertRedirect($this->post('/admin/custom-emoji', [
            'shortcode' => ':party:',
            'name' => 'Party',
            'image_path' => '/emoji/party.webp',
            'mime' => 'image/webp',
            'allow_reactions' => '1',
        ]));

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'emoji']);
        $author = $this->makeUser(['username' => 'emoji_author']);
        $this->actingAs($author);
        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Emoji topic',
            'body' => 'Hello :party: `:party:`',
        ]));
        $postId = (int) $this->db->fetchValue("SELECT id FROM posts WHERE body LIKE 'Hello%'");
        $page = $this->get('/t/' . (int) $this->db->fetchValue('SELECT thread_id FROM posts WHERE id = ?', [$postId]) . '-emoji-topic');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('<img src="/emoji/party.webp" alt=":party:" loading="lazy" class="custom-emoji">', $page->body());
        self::assertStringContainsString('<code>:party:</code>', $page->body());

        $reactor = $this->makeUser(['username' => 'emoji_reactor']);
        $this->actingAs($reactor);
        $this->assertRedirect($this->post('/posts/' . $postId . '/react', ['emoji' => ':party:']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM reactions WHERE emoji = ':party:'"));
    }

    public function test_custom_emoji_replaces_multiple_shortcodes_in_one_text_node(): void
    {
        (new SettingRepository($this->db))->set('features', ['custom_emoji' => true]);
        $admin = $this->makeAdmin(['username' => 'emoji_multi_admin']);
        $this->actingAs($admin);

        foreach (['party' => 'Party', 'wave' => 'Wave'] as $shortcode => $name) {
            $this->assertRedirect($this->post('/admin/custom-emoji', [
                'shortcode' => $shortcode,
                'name' => $name,
                'image_path' => '/emoji/' . $shortcode . '.webp',
                'mime' => 'image/webp',
            ]));
        }

        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'emoji-multi']);
        $author = $this->makeUser(['username' => 'emoji_multi_author']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Emoji multi topic',
            'body' => 'Hello :party: and :wave:',
        ]));

        $page = $this->get('/t/' . (int) $this->db->fetchValue("SELECT id FROM threads WHERE slug = 'emoji-multi-topic'") . '-emoji-multi-topic');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('alt=":party:"', $page->body());
        self::assertStringContainsString('alt=":wave:"', $page->body());
    }

    public function test_giphy_config_is_public_key_only_when_slash_giphy_enabled(): void
    {
        $this->assertStatus(404, $this->get('/composer/giphy-config'));

        $settings = new SettingRepository($this->db);
        $settings->set('features', ['slash_giphy' => true]);
        $settings->set('giphy_public_key', 'public-test-key');
        $settings->set('giphy_rating', 'pg-13');

        $res = $this->get('/composer/giphy-config');
        $this->assertStatus(200, $res);
        $json = json_decode($res->body(), true);
        self::assertTrue($json['ok']);
        self::assertSame('public-test-key', $json['public_key']);
        self::assertSame('pg-13', $json['rating']);
        self::assertSame('Powered by GIPHY', $json['attribution']);
        self::assertFalse($json['server_proxy']);
    }

    public function test_custom_emoji_rollback_removes_slash_insert(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('giphy_public_key', 'public-test-key');

        $enabled = $this->get('/composer/giphy-config');
        $this->assertStatus(200, $enabled);
        $enabledJson = json_decode($enabled->body(), true);
        self::assertContains('custom_emoji', $enabledJson['allowed_inserts']);

        $settings->set('features', ['custom_emoji' => false]);
        $rolledBack = $this->get('/composer/giphy-config');
        $this->assertStatus(200, $rolledBack);
        $rolledBackJson = json_decode($rolledBack->body(), true);
        self::assertContains('giphy', $rolledBackJson['allowed_inserts']);
        self::assertNotContains('custom_emoji', $rolledBackJson['allowed_inserts']);
    }

    public function test_giphy_csp_sources_are_added_only_when_slash_giphy_is_configured(): void
    {
        $dark = (string) $this->get('/')->getHeader('content-security-policy');
        self::assertStringNotContainsString('api.giphy.com', $dark);
        self::assertStringNotContainsString('*.giphy.com', $dark);

        $settings = new SettingRepository($this->db);
        $settings->set('features', ['slash_giphy' => true]);
        $settings->set('giphy_public_key', 'public-test-key');

        $enabled = (string) $this->get('/')->getHeader('content-security-policy');
        self::assertStringContainsString("connect-src 'self' https://api.giphy.com", $enabled);
        self::assertStringContainsString("img-src 'self' data: https://*.giphy.com", $enabled);
    }

    public function test_slash_giphy_is_default_on_and_operator_rollback_regates_route_and_csp(): void
    {
        // Graduated to default-on (GA 2026-07-02): with a provider key configured
        // the picker config is live and the CSP is relaxed without any override.
        $settings = new SettingRepository($this->db);
        $settings->set('giphy_public_key', 'public-test-key');

        $this->assertStatus(200, $this->get('/composer/giphy-config'));
        self::assertStringContainsString('api.giphy.com', (string) $this->get('/')->getHeader('content-security-policy'));

        // Operator rollback: disabling the flag re-gates the route (404) and drops
        // the GIPHY CSP sources even though the key remains configured.
        $settings->set('features', ['slash_giphy' => false]);
        $this->assertStatus(404, $this->get('/composer/giphy-config'));
        self::assertStringNotContainsString('api.giphy.com', (string) $this->get('/')->getHeader('content-security-policy'));
    }
}
