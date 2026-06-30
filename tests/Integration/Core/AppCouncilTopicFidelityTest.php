<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use Tests\Support\TestCase;

/**
 * Council-topic (thread) visual-fidelity pass against the Imladris UI kit
 * (ui_kits/retroboards Conversation + components/forum Post / JoinBar).
 *
 * These assert OBSERVABLE render behaviour, not the presence of static markup:
 *  - the per-post identity column carries the author's "regard" (commends)
 *    plinth, drawn from the real users.reputation;
 *  - that plinth is suppressed for an anonymous post, so masking a byline never
 *    leaks the real author's reputation (a deanonymisation channel);
 *  - the guest join-bar speaks the council lexicon ("counsel", emphasised);
 *  - the breadcrumb resolves to a two-hop Inbox / #board back-trail.
 */
final class AppCouncilTopicFidelityTest extends TestCase
{
    private int $cat;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'siteadmin']);
        $this->cat = $this->makeCategory();
    }

    private function setReputation(int $userId, int $rep): void
    {
        $this->db->run('UPDATE users SET reputation = ? WHERE id = ?', [$rep, $userId]);
    }

    public function test_post_avatar_shows_the_author_regard_plinth(): void
    {
        $board = $this->makeBoard($this->cat, ['slug' => 'commons']);
        $author = $this->makeUser(['username' => 'erestor', 'display_name' => 'Erestor']);
        $this->setReputation((int) $author['id'], 4242);
        $t = $this->makeThread($board, $author, 'Who changed what', 'The diff is small.');

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $resp);
        // The regard plinth renders, carrying the author's real reputation and the
        // "Commends" lapidary label.
        $this->assertSeeText($resp, 'regard-block');
        $this->assertSeeText($resp, '4,242');
        $this->assertSeeText($resp, 'Commends');
    }

    public function test_anonymous_post_does_not_leak_the_author_regard(): void
    {
        $board = $this->makeBoard($this->cat, ['slug' => 'anon', 'allow_anonymous' => 1]);
        $author = $this->makeUser(['username' => 'galadriel', 'display_name' => 'Galadriel Real']);
        $this->setReputation((int) $author['id'], 7777);
        $t = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'], 'title' => 'Masked counsel', 'body' => 'secret', 'is_anonymous' => '1',
        ]);

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, 'Anonymous');
        // No regard plinth at all (the only post is anonymous) and the real
        // author's reputation never appears on the page.
        $this->assertDontSeeText($resp, 'regard-block');
        $this->assertDontSeeText($resp, '7,777');
    }

    public function test_guest_join_bar_uses_the_council_lexicon(): void
    {
        $board = $this->makeBoard($this->cat, ['slug' => 'hall']);
        $author = $this->makeUser(['username' => 'mithrandir']);
        $t = $this->makeThread($board, $author, 'Open topic', 'Body.');

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $resp);
        $this->assertSeeText($resp, "You're browsing as a guest");
        $this->assertSeeText($resp, '<em>log in to add your counsel.</em>');
    }

    public function test_breadcrumb_resolves_to_an_inbox_and_board_back_trail(): void
    {
        $board = $this->makeBoard($this->cat, ['slug' => 'audit-trails', 'name' => 'audit-trails']);
        $author = $this->makeUser(['username' => 'cirdan']);
        $t = $this->makeThread($board, $author, 'A topic', 'Body.');

        $resp = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);

        $this->assertStatus(200, $resp);
        // First hop is the Inbox; second hop is the board channel.
        $this->assertSeeText($resp, 'Inbox</a>');
        $this->assertSeeText($resp, 'breadcrumb-board');
    }
}
