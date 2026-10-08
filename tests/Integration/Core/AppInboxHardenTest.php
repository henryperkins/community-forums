<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ThreadUserRepository;
use DOMDocument;
use DOMXPath;
use Tests\Support\TestCase;

final class AppInboxHardenTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function states(): ThreadUserRepository
    {
        return new ThreadUserRepository($this->db);
    }

    /** @return array{0:array<string,mixed>,1:array<string,mixed>,2:array<string,mixed>} */
    private function ownTopic(): array
    {
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $topic = $this->makeThread($board, $member, 'Recoverable hardening topic');
        $this->actingAs($member);
        return [$member, $board, $topic];
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        return new DOMXPath($document);
    }

    /** @return list<int> */
    private function selectedIds(string $html): array
    {
        $ids = [];
        foreach ($this->dom($html)->query('//input[@name="thread_ids[]" and @checked]') as $input) {
            $ids[] = (int) $input->getAttribute('value');
        }
        return $ids;
    }

    public function test_missing_or_malformed_snooze_intent_preserves_both_hidden_states_and_explicit_restore_still_works(): void
    {
        [$member, , $topic] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $topic['thread_id'];
        $return = '/inbox?scope=snoozed&order=newest&page=2';
        foreach ([[null, true], ['2999-01-01 00:00:00', false]] as [$until, $manual]) {
            $this->states()->setSnooze($userId, $threadId, $until, $manual);
            foreach ([[], ['until' => null], ['until' => false], ['until' => 0], ['until' => ['']]] as $body) {
                $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', $body + ['return' => $return]), $return);
                $state = $this->states()->find($userId, $threadId);
                self::assertSame($manual ? 1 : 0, (int) $state['snoozed_indefinitely']);
                self::assertSame($until, $state['snoozed_until']);
            }
            $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => '', 'return' => $return]), $return);
            $restored = $this->states()->find($userId, $threadId);
            self::assertSame(0, (int) $restored['snoozed_indefinitely']);
            self::assertNull($restored['snoozed_until']);
        }
    }

    public function test_read_intent_validation_preserves_unread_for_native_and_json_requests(): void
    {
        [$member, , $topic] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $topic['thread_id'];
        $return = '/inbox?scope=unread&order=commended&page=2';
        $this->states()->markUnread($userId, $threadId);
        foreach ([[], ['state' => null], ['state' => 'invalid'], ['state' => ['unread']], ['state' => false]] as $body) {
            $this->assertRedirect($this->post('/t/' . $threadId . '/read', $body + ['return' => $return]), $return);
            self::assertNull($this->states()->find($userId, $threadId)['last_read_post_id']);
            $json = $this->post('/t/' . $threadId . '/read', $body + ['format' => 'json']);
            $this->assertStatus(422, $json);
            self::assertSame(['ok' => false, 'error' => 'Choose Mark read or Mark unread.'], json_decode($json->body(), true));
            self::assertNull($this->states()->find($userId, $threadId)['last_read_post_id']);
        }
        foreach (['read', 'read', 'unread', 'unread'] as $intent) {
            $this->assertStatus(200, $this->post('/t/' . $threadId . '/read', ['state' => $intent, 'format' => 'json']));
            $state = $this->states()->find($userId, $threadId);
            if ($intent === 'unread') {
                self::assertNull($state['last_read_post_id']);
            } else {
                self::assertSame((int) $topic['post_id'], (int) $state['last_read_post_id']);
            }
        }
    }

    public function test_held_topic_row_actions_use_the_canonical_author_and_board_moderator_read_gate(): void
    {
        $author = $this->makeUser();
        $visitor = $this->makeUser();
        $moderator = $this->makeUser(['role' => 'moderator']);
        $board = $this->makeBoard($this->makeCategory());
        $topic = $this->makeThread($board, $author, 'Held topic existence is private');
        $threadId = (int) $topic['thread_id'];
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$threadId]);
        foreach ([$visitor, $moderator] as $unauthorized) {
            $this->actingAs($unauthorized);
            $this->assertStatus(404, $this->get('/t/' . $threadId . '-' . $topic['slug']));
            $this->assertStatus(404, $this->post('/t/' . $threadId . '/read', ['state' => 'read']));
            $this->assertStatus(404, $this->post('/t/' . $threadId . '/star'));
            self::assertNull($this->states()->find((int) $unauthorized['id'], $threadId));
        }
        (new BoardModeratorRepository($this->db))->assign((int) $board['id'], (int) $moderator['id']);
        foreach ([$author, $moderator] as $authorized) {
            $this->actingAs($authorized);
            $this->assertStatus(200, $this->get('/t/' . $threadId . '-' . $topic['slug']));
            $this->assertRedirect($this->post('/t/' . $threadId . '/read', ['state' => 'read']));
            $this->assertRedirect($this->post('/t/' . $threadId . '/star'));
            self::assertTrue($this->states()->isStarred((int) $authorized['id'], $threadId));
        }
    }

    public function test_explicit_star_intents_are_idempotent_and_missing_intent_preserves_legacy_toggle(): void
    {
        [$member, , $topic] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $topic['thread_id'];
        $return = '/inbox?scope=mine&order=commended';
        foreach (['1', '1', 1, '0', '0', 0] as $intent) {
            $this->assertRedirect($this->post('/t/' . $threadId . '/star', ['starred' => $intent, 'return' => $return]), $return);
            self::assertSame($intent === '1' || $intent === 1, $this->states()->isStarred($userId, $threadId));
        }
        $this->assertRedirect($this->post('/t/' . $threadId . '/star'));
        self::assertTrue($this->states()->isStarred($userId, $threadId));
        $this->assertRedirect($this->post('/t/' . $threadId . '/star'));
        self::assertFalse($this->states()->isStarred($userId, $threadId));
    }

    public function test_malformed_star_intents_leave_existing_state_unchanged_with_native_and_json_errors(): void
    {
        [$member, , $topic] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $topic['thread_id'];
        $return = '/inbox?scope=starred&order=newest';
        foreach ([true, false] as $existing) {
            $this->states()->setStar($userId, $threadId, $existing);
            foreach ([null, '', false, true, '2', -1, ['1']] as $intent) {
                $this->assertRedirect($this->post('/t/' . $threadId . '/star', ['starred' => $intent, 'return' => $return]), $return);
                self::assertSame($existing, $this->states()->isStarred($userId, $threadId));
                $response = $this->post('/t/' . $threadId . '/star', ['starred' => $intent, 'format' => 'json']);
                $this->assertStatus(422, $response);
                self::assertSame(['ok' => false, 'error' => 'Choose whether to star this topic.'], json_decode($response->body(), true));
                self::assertSame($existing, $this->states()->isStarred($userId, $threadId));
            }
        }
    }

    public function test_revoked_private_access_blocks_read_and_star_and_suspended_members_can_still_mark_read(): void
    {
        $owner = $this->makeAdmin();
        $member = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $topic = $this->makeThread($board, $owner, 'Private row access');
        $threadId = (int) $topic['thread_id'];
        $userId = (int) $member['id'];
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $board['id'], $userId, (int) $owner['id']);
        $this->actingAs($member);
        $this->assertRedirect($this->post('/t/' . $threadId . '/read', ['state' => 'unread']));
        $members->remove((int) $board['id'], $userId);
        $this->assertStatus(404, $this->post('/t/' . $threadId . '/read', ['state' => 'read']));
        $this->assertStatus(404, $this->post('/t/' . $threadId . '/star'));
        self::assertNull($this->states()->find($userId, $threadId)['last_read_post_id']);
        self::assertFalse($this->states()->isStarred($userId, $threadId));

        $members->add((int) $board['id'], $userId, (int) $owner['id']);
        $this->users()->setStatus($userId, 'suspended', '2999-01-01 00:00:00');
        $this->actingAs($this->users()->find($userId));
        $this->assertRedirect($this->post('/t/' . $threadId . '/read', ['state' => 'read']));
        self::assertSame((int) $topic['post_id'], (int) $this->states()->find($userId, $threadId)['last_read_post_id']);
        $this->assertStatus(403, $this->post('/t/' . $threadId . '/star'));
    }

    public function test_bulk_malformed_ids_and_actions_preserve_only_valid_current_page_checkmarks_without_writing(): void
    {
        [$member, , $topic] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $topic['thread_id'];
        foreach ([['nested'], 'bad', false, -1, '1e3', str_repeat('9', 40)] as $invalid) {
            $response = $this->post('/inbox/bulk', [
                'scope' => 'mine', 'order' => 'commended', 'action' => 'hide', 'thread_ids' => [$threadId, $invalid],
            ]);
            $this->assertStatus(422, $response);
            self::assertSame([$threadId], $this->selectedIds($response->body()));
            self::assertStringContainsString('That selection is not available in this Inbox view.', $response->body());
            self::assertStringContainsString('name="order" value="commended"', $response->body());
            self::assertSame(0, (int) ($this->states()->find($userId, $threadId)['snoozed_indefinitely'] ?? 0));
        }
        foreach ([['action' => ['hide']], ['action' => 'unknown'], ['action' => 'snooze', 'until' => ['tomorrow']]] as $action) {
            $response = $this->post('/inbox/bulk', $action + [
                'scope' => 'mine', 'order' => 'newest', 'thread_ids' => [$threadId],
            ]);
            $this->assertStatus(422, $response);
            self::assertSame([$threadId], $this->selectedIds($response->body()));
            self::assertStringContainsString('Choose a valid Inbox action.', $response->body());
            self::assertSame(0, (int) ($this->states()->find($userId, $threadId)['snoozed_indefinitely'] ?? 0));
        }
        $retry = $this->post('/inbox/bulk', [
            'scope' => 'mine', 'order' => 'newest', 'action' => 'hide', 'thread_ids' => [$threadId, $threadId],
        ]);
        $this->assertRedirect($retry, '/inbox?scope=mine&order=newest');
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);
    }

    public function test_bulk_stale_and_private_selection_recovers_without_rendering_unavailable_topic_data(): void
    {
        [$member, $board, $first] = $this->ownTopic();
        $second = $this->makeThread($board, $member, 'Still selected and available');
        $privateBoard = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $secret = $this->makeThread($privateBoard, $this->makeAdmin(), 'Secret title must stay hidden');
        $userId = (int) $member['id'];
        $firstId = (int) $first['thread_id'];
        $secondId = (int) $second['thread_id'];
        $this->states()->setSnooze($userId, $firstId, null, true);
        $response = $this->post('/inbox/bulk', [
            'scope' => 'mine', 'order' => 'newest', 'action' => 'hide',
            'thread_ids' => [$firstId, $secondId, (int) $secret['thread_id']],
        ]);
        $this->assertStatus(422, $response);
        self::assertSame([$secondId], $this->selectedIds($response->body()));
        self::assertStringNotContainsString('Secret title must stay hidden', $response->body());
        self::assertStringNotContainsString('Recoverable hardening topic', $response->body());
        self::assertSame(0, (int) ($this->states()->find($userId, $secondId)['snoozed_indefinitely'] ?? 0));
        self::assertNull($this->states()->find($userId, (int) $secret['thread_id']));
    }

    public function test_bulk_validation_keeps_the_clamped_page_and_excludes_off_page_checkmarks(): void
    {
        [$member, $board, $first] = $this->ownTopic();
        $last = $first;
        for ($i = 0; $i < 20; $i++) {
            $last = $this->makeThread($board, $member, 'Paged selection ' . $i);
        }
        $firstId = (int) $first['thread_id'];
        $response = $this->post('/inbox/bulk', [
            'scope' => 'mine', 'order' => 'newest', 'page' => 999, 'action' => 'hide',
            'thread_ids' => [$firstId, (int) $last['thread_id']],
        ]);
        $this->assertStatus(422, $response);
        self::assertSame([$firstId], $this->selectedIds($response->body()));
        self::assertStringContainsString('name="page" value="2"', $response->body());
        self::assertSame(0, (int) ($this->states()->find((int) $member['id'], $firstId)['snoozed_indefinitely'] ?? 0));
    }
}
