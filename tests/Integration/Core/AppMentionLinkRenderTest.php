<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppMentionLinkRenderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_post_render_links_valid_mentions_outside_code_only(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mention-links']);
        $author = $this->makeUser(['username' => 'mentionauthor']);
        $this->makeUser(['username' => 'Alice']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mention links',
            'body' => 'Hello @alice, ignore `@alice` and name@example.com.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('<a href="/u/Alice" class="mention">@alice</a>', $html);
        self::assertStringContainsString('<code>@alice</code>', $html);
        self::assertStringContainsString('name@example.com', $html);
    }

    public function test_unknown_mentions_remain_plain_text(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'unknown-mention']);
        $author = $this->makeUser(['username' => 'unknownauthor']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Unknown mention',
            'body' => 'Hello @nobodyhere.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@nobodyhere', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }

    public function test_dm_and_preview_render_mentions(): void
    {
        $sender = $this->makeUser(['username' => 'dmmentioner']);
        $recipient = $this->makeUser(['username' => 'dmrecipient']);
        $this->makeThread($this->makeBoard($this->makeCategory('DM mention setup')), $sender, 'DM setup', 'establishing a post');
        $this->actingAs($sender);

        $this->assertRedirect($this->post('/messages', ['to' => 'dmrecipient', 'body' => 'Hi @dmrecipient']));
        $dmHtml = (string) $this->db->fetchValue('SELECT body_html FROM dm_messages WHERE user_id = ? ORDER BY id DESC LIMIT 1', [(int) $sender['id']]);
        self::assertStringContainsString('<a href="/u/dmrecipient" class="mention">@dmrecipient</a>', $dmHtml);

        $preview = $this->post('/composer/preview', ['body' => 'Preview @dmrecipient']);
        $this->assertStatus(200, $preview);
        // Response::json() uses JSON_UNESCAPED_SLASHES, so slashes stay literal
        // while double quotes are escaped (\"). The anchor therefore appears in
        // the JSON body with plain slashes and escaped quotes.
        self::assertStringContainsString('<a href=\"/u/dmrecipient\" class=\"mention\">@dmrecipient</a>', $preview->body());
    }

    public function test_mentions_are_not_linked_when_mentions_flag_is_disabled(): void
    {
        (new SettingRepository($this->db))->set('features', ['mentions' => false]);
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'mentions-off']);
        $author = $this->makeUser(['username' => 'mentionsoffauthor']);
        $this->makeUser(['username' => 'Carol']);
        $this->actingAs($author);

        $this->assertRedirect($this->post('/threads', [
            'board_id' => (int) $board['id'],
            'title' => 'Mentions off',
            'body' => 'Hello @carol.',
        ]));

        $html = (string) $this->db->fetchValue("SELECT body_html FROM posts WHERE user_id = ? ORDER BY id DESC LIMIT 1", [(int) $author['id']]);
        self::assertStringContainsString('@carol', $html);
        self::assertStringNotContainsString('class="mention"', $html);
    }
}
