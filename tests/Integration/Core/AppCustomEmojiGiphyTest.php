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

    public function test_custom_emoji_admin_is_dark_by_default(): void
    {
        $admin = $this->makeAdmin(['username' => 'emoji_dark_admin']);
        $this->actingAs($admin);

        $res = $this->post('/admin/custom-emoji', [
            'shortcode' => 'party',
            'name' => 'Party',
            'image_path' => '/emoji/party.webp',
            'mime' => 'image/webp',
        ]);

        $this->assertStatus(404, $res);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM custom_emoji'));
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
        self::assertStringContainsString('<img src="/emoji/party.webp" alt=":party:" loading="lazy">', $page->body());
        self::assertStringContainsString('<code>:party:</code>', $page->body());

        $reactor = $this->makeUser(['username' => 'emoji_reactor']);
        $this->actingAs($reactor);
        $this->assertRedirect($this->post('/posts/' . $postId . '/react', ['emoji' => ':party:']));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM reactions WHERE emoji = ':party:'"));
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
}
