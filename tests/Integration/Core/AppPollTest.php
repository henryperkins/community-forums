<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardModeratorRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppPollTest extends TestCase
{
    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        (new SettingRepository($this->db))->set('features', $flags);
    }

    public function test_polls_are_available_by_default_and_can_be_disabled(): void
    {
        // polls graduated to default-on (GA 2026-06-30): with no features
        // override, the create route is live. An operator can still take it
        // offline via the features setting (the rollback path).
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'poll_default_author']);
        $board = $this->makeBoard($this->makeCategory('Poll Default'));
        $thread = $this->makeThread($board, $author, 'Poll default', 'body');
        $this->actingAs($author);

        // Available by default (no override): create succeeds → redirect, not 404.
        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Pick one?',
            'mode' => 'single',
            'options' => "A\nB",
        ]), '/t/' . $thread['thread_id']);

        // Operator rollback: disabling the flag takes the route offline (404).
        $this->setFlags(['polls' => false]);
        $this->assertStatus(404, $this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Another?',
            'mode' => 'single',
            'options' => "C\nD",
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

    public function test_poll_panel_uses_the_feature_activation_design_markup(): void
    {
        $this->makeAdmin();
        $this->setFlags(['polls' => true]);
        $author = $this->makeUser(['username' => 'poll_design_author']);
        $voter = $this->makeUser(['username' => 'poll_design_voter']);
        $board = $this->makeBoard($this->makeCategory('Poll Design'));
        $thread = $this->makeThread($board, $author, 'Poll design thread', 'body');
        $this->actingAs($author);

        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Which proposal should the council advance first?',
            'mode' => 'single',
            'options' => "Tagging overhaul\nSaved feeds",
        ]), '/t/' . $thread['thread_id']);
        $pollId = (int) $this->db->fetchValue('SELECT id FROM polls WHERE thread_id = ?', [$thread['thread_id']]);
        $firstOption = (int) $this->db->fetchValue('SELECT id FROM poll_options WHERE poll_id = ? ORDER BY position LIMIT 1', [$pollId]);

        $this->actingAs($voter);
        $before = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $before);
        self::assertStringContainsString('class="poll-card poll-panel"', $before->body());
        self::assertStringContainsString('poll-head', $before->body());
        self::assertStringContainsString('poll-option', $before->body());
        self::assertStringContainsString('Open to the council', $before->body());

        $this->assertRedirectContains($this->post('/polls/' . $pollId . '/vote', ['option_ids' => [$firstOption]]), '/t/' . $thread['thread_id']);
        $after = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        self::assertStringContainsString('poll-result', $after->body());
        self::assertStringContainsString('poll-result-bar', $after->body());
        self::assertStringContainsString('Your vote', $after->body());
    }

    public function test_removed_private_board_member_cannot_reach_poll_routes_by_direct_post(): void
    {
        $this->setFlags(['polls' => true]);
        $admin = $this->makeAdmin(['username' => 'poll_admin']);
        $author = $this->makeUser(['username' => 'poll_private_author']);
        $board = $this->makeBoard($this->makeCategory('Private Polls'), [
            'slug' => 'private-polls',
            'visibility' => 'private',
        ]);

        $members = new BoardMemberRepository($this->db);
        $members->add((int) $board['id'], (int) $author['id'], (int) $admin['id']);
        $thread = $this->makeThread($board, $author, 'Hidden poll thread', 'body');

        $this->actingAs($author);
        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Secret vote?',
            'mode' => 'single',
            'options' => "Yes\nNo",
        ]), '/t/' . $thread['thread_id']);

        $pollId = (int) $this->db->fetchValue('SELECT id FROM polls WHERE thread_id = ?', [$thread['thread_id']]);
        $yesId = (int) $this->db->fetchValue("SELECT id FROM poll_options WHERE poll_id = ? AND body = 'Yes'", [$pollId]);

        $members->remove((int) $board['id'], (int) $author['id']);

        $this->assertStatus(404, $this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Second secret vote?',
            'mode' => 'single',
            'options' => "Alpha\nBeta",
        ]));
        $this->assertStatus(404, $this->post('/polls/' . $pollId . '/vote', ['option_ids' => [$yesId]]));
        $this->assertStatus(404, $this->post('/polls/' . $pollId . '/close'));

        self::assertSame('open', (string) $this->db->fetchValue('SELECT status FROM polls WHERE id = ?', [$pollId]));
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM poll_votes WHERE poll_id = ?', [$pollId]));
    }

    public function test_poll_management_is_limited_to_the_author_or_an_in_scope_moderator(): void
    {
        $this->setFlags(['polls' => true]);
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'poll_scope_author']);
        $member = $this->makeUser(['username' => 'poll_scope_member']);
        $modInScope = $this->makeUser(['username' => 'poll_scope_mod_in']);
        $modOutOfScope = $this->makeUser(['username' => 'poll_scope_mod_out']);
        $board = $this->makeBoard($this->makeCategory('Poll Scope A'), ['slug' => 'poll-scope-a']);
        $otherBoard = $this->makeBoard($this->makeCategory('Poll Scope B'), ['slug' => 'poll-scope-b']);
        $thread = $this->makeThread($board, $author, 'Scoped poll', 'body');

        $mods = new BoardModeratorRepository($this->db);
        $mods->assign((int) $board['id'], (int) $modInScope['id']);
        $mods->assign((int) $otherBoard['id'], (int) $modOutOfScope['id']);

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Who can manage this?',
            'mode' => 'single',
            'options' => "Author\nModerator",
        ]));

        $this->actingAs($modOutOfScope);
        $this->assertStatus(403, $this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Wrong board?',
            'mode' => 'single',
            'options' => "Yes\nNo",
        ]));

        $this->actingAs($modInScope);
        $this->assertRedirectContains($this->post('/t/' . $thread['thread_id'] . '/poll', [
            'question' => 'Scoped moderation?',
            'mode' => 'single',
            'options' => "Allowed\nDenied",
        ]), '/t/' . $thread['thread_id']);

        $pollId = (int) $this->db->fetchValue('SELECT id FROM polls WHERE thread_id = ?', [$thread['thread_id']]);

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/polls/' . $pollId . '/close'));

        $this->actingAs($modOutOfScope);
        $this->assertStatus(403, $this->post('/polls/' . $pollId . '/close'));
        self::assertSame('open', (string) $this->db->fetchValue('SELECT status FROM polls WHERE id = ?', [$pollId]));

        $this->actingAs($modInScope);
        $this->assertRedirectContains($this->post('/polls/' . $pollId . '/close'), '/t/' . $thread['thread_id']);
        self::assertSame('closed', (string) $this->db->fetchValue('SELECT status FROM polls WHERE id = ?', [$pollId]));
    }
}
