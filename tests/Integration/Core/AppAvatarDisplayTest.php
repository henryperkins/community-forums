<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Response;
use App\Repository\BlockRepository;
use App\Repository\FollowRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

/**
 * An uploaded avatar replaces the member's monogram wherever the app draws
 * that member (USER §5.2 fallback chain: upload → … → monogram). Only the
 * profile header used to receive `avatar_path`, so every other surface drew
 * initials for a member who had uploaded a picture.
 *
 * The avatar follows the monogram's existing rules. An anonymous post wears
 * neither (ADMIN §1.3), and the "Show avatars" reading preference hides
 * uploaded avatars wherever it already hid monograms.
 */
final class AppAvatarDisplayTest extends TestCase
{
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'avatar_site_admin']);
    }

    private function giveAvatar(array $user, string $path): void
    {
        $this->db->run(
            "UPDATE users SET avatar_path = ?, avatar_source = 'upload' WHERE id = ?",
            [$path, (int) $user['id']],
        );
    }

    private function xpath(Response $response): \DOMXPath
    {
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());
        return new \DOMXPath($document);
    }

    /** XPath for an uploaded-avatar <img> with this src, relative to a context. */
    private static function avatar(string $path): string
    {
        return 'img[contains(concat(" ", normalize-space(@class), " "), " avatar-img ")'
            . ' and contains(concat(" ", normalize-space(@class), " "), " monogram ")'
            . ' and @src="' . $path . '" and @alt="" and @aria-hidden="true"]';
    }

    public function test_the_post_stream_and_topic_header_show_the_authors_uploaded_avatar(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'avatar-stream']);
        $alice = $this->makeUser(['username' => 'avatar_alice', 'display_name' => 'Alice Avatar']);
        $bob = $this->makeUser(['username' => 'avatar_bob', 'display_name' => 'Bob Monogram']);
        $this->giveAvatar($alice, '/media/9001');
        $t = $this->makeThread($board, $alice, 'Avatar topic', 'Opening words.');
        $this->posting()->reply($this->userEntity($bob), $t['thread_id'], ['body' => 'A reply without a picture.']);

        $res = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
        $this->assertStatus(200, $res);
        $xp = $this->xpath($res);

        $op = '//article[contains(concat(" ", @class, " "), " post-op ")]/div[@class="post-avatar"]';
        self::assertSame(1, $xp->query($op . '/' . self::avatar('/media/9001'))->length, 'The OP draws the uploaded avatar.');
        self::assertSame(0, $xp->query($op . '/span[contains(@class, "monogram")]')->length, 'The avatar replaces the monogram rather than joining it.');

        $reply = '//article[contains(concat(" ", @class, " "), " post ") and not(contains(concat(" ", @class, " "), " post-op "))]/div[@class="post-avatar"]';
        self::assertSame(1, $xp->query($reply . '/span[contains(@class, "monogram")]')->length, 'A member without an upload keeps the monogram.');
        self::assertSame(0, $xp->query($reply . '/img')->length);

        self::assertSame(1, $xp->query('//ul[@class="thread-participants"]/li/' . self::avatar('/media/9001'))->length, 'The participant stack draws it too.');
    }

    public function test_an_anonymous_post_never_wears_the_real_authors_avatar(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'avatar-anon', 'allow_anonymous' => 1]);
        $alice = $this->makeUser(['username' => 'masked_alice', 'display_name' => 'Masked Alice']);
        $bob = $this->makeUser(['username' => 'named_bob']);
        $this->giveAvatar($alice, '/media/9002');
        $t = $this->posting()->createThread($this->userEntity($alice), [
            'board_id' => (int) $board['id'], 'title' => 'Masked topic', 'body' => 'Posted anonymously.', 'is_anonymous' => '1',
        ]);
        $this->posting()->reply($this->userEntity($alice), $t['thread_id'], ['body' => 'Masked reply.', 'is_anonymous' => '1']);
        $this->posting()->reply($this->userEntity($bob), $t['thread_id'], ['body' => 'A named reply.']);
        (new ThreadUserRepository($this->db))->setStar((int) $bob['id'], (int) $t['thread_id'], true);

        $thread = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
        $this->assertStatus(200, $thread);
        $this->assertSeeText($thread, 'Anonymous');
        $this->assertDontSeeText($thread, '/media/9002');

        $this->assertDontSeeText($this->get('/c/' . $board['slug']), '/media/9002');

        $this->actingAs($bob);
        $inbox = $this->get('/inbox', ['scope' => 'starred', 'order' => 'active']);
        $this->assertStatus(200, $inbox);
        $this->assertSeeText($inbox, 'Masked topic');
        $this->assertDontSeeText($inbox, '/media/9002');

        $preview = $this->get('/inbox/preview/' . $t['thread_id']);
        $this->assertStatus(200, $preview);
        $this->assertSeeText($preview, 'Masked reply.');
        $this->assertDontSeeText($preview, '/media/9002');
    }

    public function test_topic_rows_show_the_starters_avatar_on_the_board_the_tag_and_the_inbox(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'avatar-rows']);
        $this->db->run('UPDATE boards SET tags_enabled = 1 WHERE id = ?', [(int) $board['id']]);
        $alice = $this->makeUser(['username' => 'row_alice']);
        $bob = $this->makeUser(['username' => 'row_bob']);
        $this->giveAvatar($alice, '/media/9003');
        $t = $this->makeThread($board, $alice, 'Row avatar topic');
        $tags = new TagRepository($this->db);
        $tagId = $tags->create('avatar-rows', 'Avatar rows', null, (int) $this->admin['id']);
        $tags->setForThread((int) $t['thread_id'], [$tagId], (int) $this->admin['id']);
        (new ThreadUserRepository($this->db))->setStar((int) $bob['id'], (int) $t['thread_id'], true);
        $this->actingAs($bob);

        $pages = [
            'board' => ['/c/' . $board['slug'], []],
            'tag' => ['/tags/avatar-rows', []],
            'inbox' => ['/inbox', ['scope' => 'starred', 'order' => 'active']],
        ];
        foreach ($pages as $surface => [$path, $query]) {
            $res = $this->get($path, $query);
            $this->assertStatus(200, $res);
            $rows = $this->xpath($res)->query('//li[contains(concat(" ", @class, " "), " thread-row ")]/' . self::avatar('/media/9003'));
            self::assertSame(1, $rows->length, "The {$surface} row draws the starter's avatar.");
        }

        $preview = $this->xpath($this->get('/inbox/preview/' . $t['thread_id']));
        self::assertSame(1, $preview->query('//div[@class="inbox-preview-attribution"]/' . self::avatar('/media/9003'))->length);
    }

    public function test_the_shell_and_the_composer_draw_the_signed_in_members_own_avatar(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'avatar-shell']);
        $alice = $this->makeUser(['username' => 'shell_alice']);
        $this->giveAvatar($alice, '/media/9004');
        $t = $this->makeThread($board, $alice, 'Shell topic');
        $this->actingAs($alice);

        $xp = $this->xpath($this->get('/t/' . $t['thread_id'] . '-' . $t['slug']));
        self::assertSame(1, $xp->query('//summary[contains(@class, "forum-bar-user")]/span[@class="avatar-wrap"]/' . self::avatar('/media/9004'))->length);
        self::assertGreaterThanOrEqual(1, $xp->query('//span[@class="composer-identity"]/' . self::avatar('/media/9004'))->length);

        $this->actingAs($this->admin);
        $this->giveAvatar($this->admin, '/media/9104');
        $console = $this->xpath($this->get('/admin/users'));
        self::assertSame(1, $console->query('//a[@class="admin-bar-user"]/' . self::avatar('/media/9104'))->length);
    }

    public function test_the_presence_rail_and_its_poll_carry_the_avatar_and_respect_show_avatars(): void
    {
        $alice = $this->makeUser(['username' => 'rail_alice']);
        $bob = $this->makeUser(['username' => 'rail_bob']);
        $this->giveAvatar($alice, '/media/9005');
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP(), show_presence = 1 WHERE id = ?', [(int) $alice['id']]);
        $this->actingAs($bob);

        $xp = $this->xpath($this->get('/'));
        self::assertSame(1, $xp->query('//section[@data-presence and @data-presence-avatars="1"]')->length);
        self::assertSame(1, $xp->query('//li[@data-presence-row="rail_alice"]//span[@class="avatar-wrap"]/' . self::avatar('/media/9005'))->length);

        $poll = $this->get('/presence');
        $this->assertStatus(200, $poll);
        $members = array_column(json_decode($poll->body(), true)['online'], null, 'username');
        self::assertSame('/media/9005', $members['rail_alice']['avatar_path'], 'The poller rebuilds a row from this payload.');
        self::assertNull($members['rail_bob']['avatar_path']);

        // Avatars off: the rail draws bare dots, and says so to the poller.
        $this->post('/settings/preferences', ['show_signatures' => '1', 'show_reactions' => '1']);
        $xp = $this->xpath($this->get('/'));
        self::assertSame(1, $xp->query('//section[@data-presence and @data-presence-avatars="0"]')->length);
        self::assertSame(0, $xp->query('//section[@data-presence]//img')->length);
        self::assertSame(1, $xp->query('//li[@data-presence-row="rail_alice"]//span[contains(@class, "presence-dot-bare")]')->length);
    }

    public function test_show_avatars_off_hides_uploaded_avatars_where_it_hid_monograms(): void
    {
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'avatar-pref']);
        $alice = $this->makeUser(['username' => 'pref_alice']);
        $bob = $this->makeUser(['username' => 'pref_bob']);
        $this->giveAvatar($alice, '/media/9006');
        $t = $this->makeThread($board, $alice, 'Preference topic');
        $this->actingAs($bob);
        $url = '/t/' . $t['thread_id'] . '-' . $t['slug'];
        $this->assertSeeText($this->get($url), 'src="/media/9006"');

        $this->post('/settings/preferences', ['show_signatures' => '1', 'show_reactions' => '1']);
        $this->assertDontSeeText($this->get($url), 'src="/media/9006"');
        $this->assertDontSeeText($this->get('/c/' . $board['slug']), 'src="/media/9006"');
    }

    public function test_direct_messages_show_the_correspondents_avatar(): void
    {
        // An admin sender is exempt from the new-account DM throttle.
        $alice = $this->makeAdmin(['username' => 'letter_alice']);
        $bob = $this->makeUser(['username' => 'letter_bob']);
        $this->giveAvatar($alice, '/media/9007');
        $this->actingAs($alice);
        $start = $this->post('/messages', ['to' => 'letter_bob', 'body' => 'A letter with a face.']);
        $this->assertRedirectContains($start, '/messages/');
        $conversationId = (int) preg_replace('#^.*/messages/#', '', (string) $start->getHeader('location'));

        $this->actingAs($bob);
        $list = $this->xpath($this->get('/messages'));
        self::assertSame(1, $list->query('//a[contains(@class, "dm-row")]/' . self::avatar('/media/9007'))->length);

        $res = $this->get('/messages/' . $conversationId);
        $this->assertStatus(200, $res);
        $xp = $this->xpath($res);
        self::assertSame(1, $xp->query('//div[@class="dm-thread-id"]/' . self::avatar('/media/9007'))->length, 'Conversation header');
        self::assertSame(1, $xp->query('//span[@class="dm-mono-col"]/' . self::avatar('/media/9007'))->length, 'Letter author');
        self::assertSame(1, $xp->query('//div[@class="dm-rail-id"]/' . self::avatar('/media/9007'))->length, 'Details rail');
    }

    public function test_people_lists_show_the_members_avatar(): void
    {
        $alice = $this->makeUser(['username' => 'list_alice', 'display_name' => 'List Alice']);
        $bob = $this->makeUser(['username' => 'list_bob']);
        $carol = $this->makeUser(['username' => 'list_carol']);
        $this->giveAvatar($alice, '/media/9008');
        $this->db->run('UPDATE users SET reputation = 12 WHERE id = ?', [(int) $alice['id']]);
        (new FollowRepository($this->db))->follow((int) $bob['id'], (int) $alice['id']);
        (new BlockRepository($this->db))->block((int) $carol['id'], (int) $alice['id']);

        $leaders = $this->get('/leaderboard');
        $this->assertStatus(200, $leaders);
        self::assertSame(1, $this->xpath($leaders)->query('//li[contains(@class, "leaderboard-row")]/' . self::avatar('/media/9008'))->length);

        $this->actingAs($bob);
        $following = $this->get('/u/list_bob/following');
        $this->assertStatus(200, $following);
        self::assertSame(1, $this->xpath($following)->query('//li[@class="person-row"]/' . self::avatar('/media/9008'))->length);

        $this->actingAs($carol);
        $blocks = $this->get('/settings/blocks');
        $this->assertStatus(200, $blocks);
        self::assertSame(1, $this->xpath($blocks)->query('//li[@class="account-ruled-row"]/' . self::avatar('/media/9008'))->length);

        $this->actingAs($this->admin);
        $directory = $this->get('/admin/users', ['q' => 'list_alice']);
        $this->assertStatus(200, $directory);
        self::assertSame(1, $this->xpath($directory)->query('//span[@class="member-directory-member-monogram"]/' . self::avatar('/media/9008'))->length);
    }
}
