<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppPollTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_poll_routes_are_dark_by_default(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'poll_dark_author']);
        $board = $this->makeBoard($this->makeCategory('Poll Dark'));
        $thread = $this->makeThread($board, $author, 'Poll dark', 'body');
        $this->actingAs($author);

        $this->assertStatus(404, $this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Pick one?',
            'options' => "A\nB",
        ]));
    }

    public function test_voters_see_results_after_vote_and_non_voters_after_close(): void
    {
        $this->makeAdmin();
        $this->setFlags(['polls' => true]);
        $author = $this->makeUser(['username' => 'poll_author']);
        $voter = $this->makeUser(['username' => 'poll_voter']);
        $nonVoter = $this->makeUser(['username' => 'poll_waiter']);
        $board = $this->makeBoard($this->makeCategory('Polls'));
        $thread = $this->makeThread($board, $author, 'Poll thread', 'body');
        $this->actingAs($author);

        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Best option?',
            'mode' => 'single',
            'options' => "Alpha\nBeta",
        ]), '/t/' . $thread['thread_id']);
        $pollId = (int) $this->db->fetchValue('SELECT id FROM polls WHERE thread_id = ?', [$thread['thread_id']]);
        $alphaId = (int) $this->db->fetchValue("SELECT id FROM poll_options WHERE poll_id = ? AND body = 'Alpha'", [$pollId]);

        $this->actingAs($nonVoter);
        $before = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $before);
        self::assertStringContainsString('Best option?', $before->body());
        self::assertStringNotContainsString('1 vote', $before->body());

        $this->actingAs($voter);
        $this->assertRedirectContains($this->post('/polls/' . $pollId . '/vote', ['option_ids' => [$alphaId]]), '/t/' . $thread['thread_id']);
        $afterVote = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('Alpha', $afterVote->body());
        self::assertStringContainsString('1 vote', $afterVote->body());

        $this->actingAs($nonVoter);
        $stillHidden = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringNotContainsString('1 vote', $stillHidden->body());

        $this->actingAs($author);
        $this->assertRedirectContains($this->post('/polls/' . $pollId . '/close'), '/t/' . $thread['thread_id']);

        $this->actingAs($nonVoter);
        $afterClose = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('1 vote', $afterClose->body());
    }
}
