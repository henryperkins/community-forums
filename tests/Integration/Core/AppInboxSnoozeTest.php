<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadAssignmentRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Support\InboxView;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Tests\Support\TestCase;

final class AppInboxSnoozeTest extends TestCase
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
        $thread = $this->makeThread($board, $member, 'A recoverable Inbox topic');
        $this->actingAs($member);
        return [$member, $board, $thread];
    }

    /** @return array<int,array<string,mixed>> */
    private function queue(int $userId, string $scope, bool $workflow = true): array
    {
        return $this->states()->inbox($userId, $scope, 'active', false, ThreadUserRepository::NO_CUTOVER, 100, 0, $workflow);
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        return new DOMXPath($document);
    }

    public function test_inbox_rows_and_topic_tools_share_the_same_visible_manual_timed_and_expired_state_contract(): void
    {
        [$member, , $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        foreach ([
            ['until' => null, 'manual' => false, 'hidden' => false, 'label' => null],
            ['until' => null, 'manual' => true, 'hidden' => true, 'label' => 'Until you turn it back on'],
            ['until' => '2030-01-02 03:04:05', 'manual' => false, 'hidden' => true, 'label' => 'Til Jan 2, 2030'],
            ['until' => '2000-01-01 00:00:00', 'manual' => false, 'hidden' => false, 'label' => null],
        ] as $state) {
            $this->states()->setSnooze($userId, $threadId, $state['until'], $state['manual']);
            $scope = $state['hidden'] ? 'snoozed' : 'mine';
            $inbox = $this->get('/inbox', ['scope' => $scope]);
            $topic = $this->get('/t/' . $threadId . '-' . $thread['slug']);
            foreach (['Inbox' => $inbox, 'Topic tools' => $topic] as $surface => $response) {
                $this->assertStatus(200, $response);
                $dom = $this->dom($response->body());
                $forms = $dom->query('//form[@data-inbox-visibility]');
                self::assertSame(1, $forms->length, $surface . ' has one shared visibility control.');
                $form = $forms->item(0);
                self::assertInstanceOf(DOMElement::class, $form);
                self::assertSame('post', $form->getAttribute('method'));
                self::assertSame('/t/' . $threadId . '/snooze', $form->getAttribute('action'));
                self::assertSame(1, $dom->query('./input[@name="_token" and string-length(@value) > 0]', $form)->length);
                self::assertSame(1, $dom->query('./input[@name="return" and string-length(@value) > 0]', $form)->length);
                self::assertSame(
                    $state['hidden'] ? '' : 'manual',
                    $dom->query('./input[@name="until"]', $form)->item(0)->getAttribute('value'),
                    $surface . ' posts the opposite visibility intent.',
                );
                $switch = $dom->query('./button[@role="switch"]', $form)->item(0);
                self::assertInstanceOf(DOMElement::class, $switch);
                self::assertSame($state['hidden'] ? 'false' : 'true', $switch->getAttribute('aria-checked'));
                self::assertStringContainsString('Show in Inbox', $switch->textContent);
                $descriptionId = $switch->getAttribute('aria-describedby');
                self::assertSame(1, $dom->query('//*[@id="' . $descriptionId . '"]')->length);

                $snoozeForms = $dom->query('//form[@action="/t/' . $threadId . '/snooze"]');
                self::assertSame(2, $snoozeForms->length, $surface . ' offers only the timed action and persistent switch.');
                self::assertSame(1, $dom->query('//form[@action="/t/' . $threadId . '/snooze"]/input[@name="until" and @value="tomorrow"]')->length);
                self::assertSame(0, $dom->query('//form[@action="/t/' . $threadId . '/snooze"]/input[@name="until" and (@value="monday" or @value="later_today" or @value="week")]')->length);
                if ($state['label'] !== null) {
                    self::assertStringContainsString($state['label'], $response->body(), $surface . ' identifies the hidden state.');
                } else {
                    self::assertStringNotContainsString('Til Jan 1, 2000', $response->body());
                    self::assertStringNotContainsString('Until you turn it back on', $response->body());
                }
                if ($surface === 'Topic tools') {
                    $byline = $dom->query('//p[@class="thread-byline"]')->item(0);
                    self::assertInstanceOf(DOMElement::class, $byline);
                    if ($state['label'] !== null) {
                        self::assertStringContainsString($state['label'], $byline->textContent, 'The topic byline carries the same personal hiding state.');
                    } else {
                        self::assertStringNotContainsString('Til ', $byline->textContent);
                        self::assertStringNotContainsString('Quiet until', $byline->textContent);
                        self::assertStringNotContainsString('Until you turn it back on', $byline->textContent);
                    }
                }
            }
        }
    }

    public function test_hidden_metadata_is_suppressed_from_both_controls_when_workflow_is_rolled_back(): void
    {
        [$member, , $thread] = $this->ownTopic();
        $threadId = (int) $thread['thread_id'];
        $this->states()->setSnooze((int) $member['id'], $threadId, null, true);
        (new SettingRepository($this->db))->set('features', ['topic_workflow' => false]);
        foreach ([$this->get('/inbox', ['scope' => 'mine']), $this->get('/t/' . $threadId . '-' . $thread['slug'])] as $response) {
            $this->assertStatus(200, $response);
            self::assertSame(0, $this->dom($response->body())->query('//form[@data-inbox-visibility]')->length);
            self::assertStringNotContainsString('Until you turn it back on', $response->body());
            self::assertStringNotContainsString('action="/t/' . $threadId . '/snooze"', $response->body());
        }
    }

    public function test_status_history_writes_retain_the_readers_manual_hiding(): void
    {
        [$member, , $thread] = $this->ownTopic();
        $threadId = (int) $thread['thread_id'];
        $this->states()->setSnooze((int) $member['id'], $threadId, null, true);
        $this->assertRedirect($this->post('/t/' . $threadId . '/status', ['status' => 'needs_answer', 'reason' => 'Still needs triage.']));
        self::assertSame(1, (int) $this->states()->find((int) $member['id'], $threadId)['snoozed_indefinitely']);
        $topic = $this->get('/t/' . $threadId . '-' . $thread['slug']);
        $this->assertStatus(200, $topic);
        self::assertStringContainsString('Still needs triage.', $topic->body());
        self::assertStringContainsString('Until you turn it back on', $topic->body());
        self::assertCount(1, $this->queue((int) $member['id'], 'snoozed'));
    }

    public function test_manual_hiding_is_recoverable_and_restore_preserves_read_star_and_subscription_state(): void
    {
        [$member, $board, $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        $states = $this->states();
        $states->markUnread($userId, $threadId);
        $states->setStar($userId, $threadId, true);
        $subscriptions = new SubscriptionRepository($this->db);
        $subscriptions->set($userId, 'thread', $threadId, true, true, 'instant');
        $subscription = $subscriptions->get($userId, 'thread', $threadId);

        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => 'manual', 'return' => '/inbox']));
        $state = $states->find($userId, $threadId);
        self::assertSame(1, (int) $state['snoozed_indefinitely']);
        self::assertNull($state['snoozed_until']);
        self::assertSame([], $this->queue($userId, 'mine'));
        $hidden = $this->queue($userId, 'snoozed');
        self::assertSame([$threadId], array_map('intval', array_column($hidden, 'id')));
        self::assertSame(1, (int) $hidden[0]['snoozed_indefinitely']);
        self::assertSame(0, (int) $hidden[0]['is_inbox_unread']);

        // Manual hiding never conceals the durable topic on its board.
        $boardRows = (new ThreadRepository($this->db))->listByBoard((int) $board['id'], 20, 0, $userId);
        self::assertSame([$threadId], array_map('intval', array_column($boardRows, 'id')));
        self::assertSame(1, (int) $boardRows[0]['snoozed_indefinitely']);

        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => '', 'return' => '/inbox?scope=snoozed']));
        $restored = $states->find($userId, $threadId);
        self::assertSame(0, (int) $restored['snoozed_indefinitely']);
        self::assertNull($restored['snoozed_until']);
        self::assertNull($restored['last_read_post_id']);
        self::assertSame(1, (int) $restored['is_starred']);
        self::assertSame($subscription, $subscriptions->get($userId, 'thread', $threadId));
        self::assertCount(1, $this->queue($userId, 'mine'));
        self::assertSame([], $this->queue($userId, 'snoozed'));
    }

    public function test_timed_snooze_replaces_manual_hiding_expires_and_can_be_restored_early(): void
    {
        [$member, , $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        $this->states()->setSnooze($userId, $threadId, null, true);
        $before = time();
        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => 'tomorrow']));
        $state = $this->states()->find($userId, $threadId);
        $deadline = strtotime((string) $state['snoozed_until'] . ' UTC');
        self::assertGreaterThanOrEqual($before + 86400, $deadline);
        self::assertLessThanOrEqual(time() + 86400, $deadline);
        self::assertSame(0, (int) $state['snoozed_indefinitely']);
        self::assertSame([], $this->queue($userId, 'mine'));
        self::assertCount(1, $this->queue($userId, 'snoozed'));

        $this->states()->setSnooze($userId, $threadId, '2000-01-01 00:00:00');
        self::assertCount(1, $this->queue($userId, 'mine'));
        self::assertSame([], $this->queue($userId, 'snoozed'));

        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => 'tomorrow']));
        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => '']));
        self::assertNull($this->states()->find($userId, $threadId)['snoozed_until']);
        self::assertCount(1, $this->queue($userId, 'mine'));
    }

    public function test_new_activity_and_read_or_star_actions_do_not_clear_manual_hiding(): void
    {
        [$member, $board, $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        $this->states()->setSnooze($userId, $threadId, null, true);
        $other = $this->makeUser();
        $postId = $this->posting()->reply($this->userEntity($other), $threadId, ['body' => 'New activity.']);
        $notificationId = (new NotificationRepository($this->db))->create([
            'user_id' => $userId, 'type' => 'reply', 'actor_id' => (int) $other['id'],
            'thread_id' => $threadId, 'post_id' => $postId,
        ]);
        $this->states()->markRead($userId, $threadId, $postId);
        $this->states()->setStar($userId, $threadId, true);
        $this->states()->markUnread($userId, $threadId);
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);
        self::assertSame([], $this->queue($userId, 'for_you'));
        self::assertCount(1, $this->queue($userId, 'snoozed'));
        // Inbox hiding does not acknowledge notifications or mute deliveries.
        self::assertSame(0, (int) (new NotificationRepository($this->db))->findOwned($userId, $notificationId)['is_read']);
    }

    public function test_invalid_or_retired_snooze_values_never_clear_existing_hiding(): void
    {
        [$member, , $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        foreach ([['until' => null, 'manual' => true], ['until' => '2999-01-01 00:00:00', 'manual' => false]] as $existing) {
            $this->states()->setSnooze($userId, $threadId, $existing['until'], $existing['manual']);
            foreach (['later_today', 'monday', 'week', 'unknown', ['tomorrow']] as $intent) {
                $response = $this->post('/t/' . $threadId . '/snooze', ['until' => $intent, 'return' => '/inbox?scope=snoozed']);
                $this->assertRedirectContains($response, '/inbox?scope=snoozed');
                $state = $this->states()->find($userId, $threadId);
                self::assertSame($existing['until'], $state['snoozed_until']);
                self::assertSame($existing['manual'] ? 1 : 0, (int) $state['snoozed_indefinitely']);
            }
        }
    }

    public function test_every_scope_count_and_shared_unread_count_excludes_manual_hiding(): void
    {
        [$member, $board, $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $actor = $this->makeUser();
        $threads = [$thread];
        foreach (['needs_answer', 'decision_made', 'solved'] as $status) {
            $extra = $this->makeThread($board, $member, 'State ' . $status);
            $this->db->run('UPDATE threads SET status = ? WHERE id = ?', [$status, (int) $extra['thread_id']]);
            $threads[] = $extra;
        }
        foreach ($threads as $topic) {
            $threadId = (int) $topic['thread_id'];
            $this->states()->markUnread($userId, $threadId);
            $this->states()->setStar($userId, $threadId, true);
            (new SubscriptionRepository($this->db))->set($userId, 'thread', $threadId, true, false, 'instant');
            (new ThreadAssignmentRepository($this->db))->assign($threadId, $userId, $userId);
            foreach (['mention', 'reply'] as $type) {
                (new NotificationRepository($this->db))->create([
                    'user_id' => $userId, 'type' => $type, 'actor_id' => (int) $actor['id'], 'thread_id' => $threadId,
                    'post_id' => (int) $this->db->fetchValue('SELECT last_post_id FROM threads WHERE id = ?', [$threadId]),
                ]);
            }
        }
        $before = $this->states()->countInboxScopes($userId, false, ThreadUserRepository::NO_CUTOVER);
        foreach (InboxView::SCOPES as $scope) {
            if ($scope !== 'snoozed') {
                self::assertGreaterThan(0, $before[$scope], 'Fixture must exercise ' . $scope);
            }
        }
        self::assertSame(4, $this->states()->unreadCount($userId, false, ThreadUserRepository::NO_CUTOVER));
        foreach ($threads as $topic) {
            $this->states()->setSnooze($userId, (int) $topic['thread_id'], null, true);
        }

        $counts = $this->states()->countInboxScopes($userId, false, ThreadUserRepository::NO_CUTOVER);
        foreach (InboxView::SCOPES as $scope) {
            $expected = $scope === 'snoozed' ? 4 : 0;
            self::assertSame($expected, $counts[$scope], $scope . ' aggregate');
            self::assertSame($expected, $this->states()->countInbox($userId, $scope, 'active', false, ThreadUserRepository::NO_CUTOVER), $scope . ' count');
            self::assertCount($expected, $this->queue($userId, $scope), $scope . ' rows');
        }
        self::assertSame(0, $this->states()->unreadCount($userId, false, ThreadUserRepository::NO_CUTOVER));
        self::assertSame([], $this->states()->unreadCountsByBoard($userId, false, ThreadUserRepository::NO_CUTOVER));
        self::assertSame(4, $this->states()->unreadCountForBoard($userId, (int) $board['id'], ThreadUserRepository::NO_CUTOVER));
    }

    public function test_bulk_hide_timed_snooze_and_restore_both_states_use_the_current_scope(): void
    {
        [$member, $board, $first] = $this->ownTopic();
        $second = $this->makeThread($board, $member, 'Second recoverable topic');
        $userId = (int) $member['id'];
        $ids = [(int) $first['thread_id'], (int) $second['thread_id']];
        $this->assertRedirect($this->post('/inbox/bulk', [
            'scope' => 'mine', 'order' => 'active', 'action' => 'hide', 'thread_ids' => [$ids[0]],
        ]));
        $this->assertRedirect($this->post('/inbox/bulk', [
            'scope' => 'mine', 'order' => 'active', 'action' => 'snooze', 'until' => 'tomorrow', 'thread_ids' => [$ids[1]],
        ]));
        self::assertSame(1, (int) $this->states()->find($userId, $ids[0])['snoozed_indefinitely']);
        self::assertNotNull($this->states()->find($userId, $ids[1])['snoozed_until']);
        self::assertCount(2, $this->queue($userId, 'snoozed'));
        self::assertSame([], $this->queue($userId, 'mine'));

        $this->assertRedirectContains($this->post('/inbox/bulk', [
            'scope' => 'snoozed', 'order' => 'newest', 'action' => 'restore', 'thread_ids' => $ids,
        ]), '/inbox?scope=snoozed&order=newest');
        foreach ($ids as $threadId) {
            $state = $this->states()->find($userId, $threadId);
            self::assertSame(0, (int) $state['snoozed_indefinitely']);
            self::assertNull($state['snoozed_until']);
        }
        self::assertCount(2, $this->queue($userId, 'mine'));
    }

    public function test_bulk_rejects_invalid_time_and_an_out_of_view_or_private_selection_before_any_write(): void
    {
        [$member, $board, $first] = $this->ownTopic();
        $userId = (int) $member['id'];
        $firstId = (int) $first['thread_id'];
        $other = $this->makeUser();
        $outside = $this->makeThread($board, $other, 'Outside mine');
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $secret = $this->makeThread($private, $this->makeAdmin(), 'Secret selection');
        foreach (['monday', 'invalid', ['tomorrow']] as $until) {
            $this->assertStatus(422, $this->post('/inbox/bulk', [
                'scope' => 'mine', 'action' => 'snooze', 'until' => $until, 'thread_ids' => [$firstId],
            ]));
            self::assertSame([], $this->queue($userId, 'snoozed'));
        }
        foreach ([(int) $outside['thread_id'], (int) $secret['thread_id'], 99999999] as $unavailable) {
            $this->assertStatus(422, $this->post('/inbox/bulk', [
                'scope' => 'mine', 'action' => 'hide', 'thread_ids' => [$firstId, $unavailable],
            ]));
            self::assertSame([], $this->queue($userId, 'snoozed'));
            self::assertCount(1, $this->queue($userId, 'mine'));
        }
    }

    public function test_manual_hiding_is_personal_and_revoked_private_membership_removes_recovery_and_writes(): void
    {
        $owner = $this->makeAdmin();
        $viewer = $this->makeUser();
        $private = $this->makeBoard($this->makeCategory(), ['visibility' => 'private']);
        $thread = $this->makeThread($private, $owner, 'Private recovery');
        $threadId = (int) $thread['thread_id'];
        $userId = (int) $viewer['id'];
        $members = new BoardMemberRepository($this->db);
        $members->add((int) $private['id'], $userId, (int) $owner['id']);
        $this->actingAs($viewer);
        $this->assertRedirect($this->post('/t/' . $threadId . '/snooze', ['until' => 'manual']));
        self::assertCount(1, $this->queue($userId, 'snoozed'));
        $ownerRows = (new ThreadRepository($this->db))->listByBoard((int) $private['id'], 20, 0, (int) $owner['id']);
        self::assertSame(0, (int) $ownerRows[0]['snoozed_indefinitely']);

        $members->remove((int) $private['id'], $userId);
        self::assertSame([], $this->queue($userId, 'snoozed'));
        self::assertSame(0, $this->states()->countInboxScopes($userId, false, ThreadUserRepository::NO_CUTOVER)['snoozed']);
        $this->assertStatus(404, $this->post('/t/' . $threadId . '/snooze', ['until' => '']));
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);
        $this->assertStatus(422, $this->post('/inbox/bulk', [
            'scope' => 'snoozed', 'action' => 'restore', 'thread_ids' => [$threadId],
        ]));
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);
    }

    public function test_workflow_rollback_ignores_both_hidden_states_and_rejects_their_writes(): void
    {
        [$member, $board, $manual] = $this->ownTopic();
        $timed = $this->makeThread($board, $member, 'Timed state retained across rollback');
        $userId = (int) $member['id'];
        $manualId = (int) $manual['thread_id'];
        $timedId = (int) $timed['thread_id'];
        $this->states()->setSnooze($userId, $manualId, null, true);
        $this->states()->setSnooze($userId, $timedId, '2999-01-01 00:00:00');
        (new SettingRepository($this->db))->set('features', ['topic_workflow' => false]);

        $visible = $this->queue($userId, 'mine', false);
        self::assertCount(2, $visible);
        foreach ($visible as $row) {
            self::assertSame(0, (int) $row['snoozed_indefinitely']);
            self::assertNull($row['snoozed_until']);
        }
        self::assertSame([], $this->queue($userId, 'snoozed', false));
        self::assertSame(2, $this->states()->countInboxScopes($userId, false, ThreadUserRepository::NO_CUTOVER, false)['mine']);
        self::assertSame(2, $this->states()->unreadCount($userId, false, ThreadUserRepository::NO_CUTOVER, false));
        $boardRows = (new ThreadRepository($this->db))->listByBoard((int) $board['id'], 20, 0, $userId, false);
        self::assertSame([0, 0], array_map('intval', array_column($boardRows, 'snoozed_indefinitely')));
        foreach (['manual', 'tomorrow', ''] as $intent) {
            $this->assertStatus(404, $this->post('/t/' . $manualId . '/snooze', ['until' => $intent]));
        }
        foreach (['hide', 'restore', 'snooze'] as $action) {
            $this->assertStatus(422, $this->post('/inbox/bulk', [
                'scope' => 'mine', 'action' => $action, 'until' => 'tomorrow', 'thread_ids' => [$manualId, $timedId],
            ]));
        }
        self::assertSame(1, (int) $this->states()->find($userId, $manualId)['snoozed_indefinitely']);
        self::assertSame('2999-01-01 00:00:00', $this->states()->find($userId, $timedId)['snoozed_until']);
    }

    public function test_csrf_account_state_and_pending_thread_gates_apply_to_hide_and_restore(): void
    {
        [$member, $board, $thread] = $this->ownTopic();
        $userId = (int) $member['id'];
        $threadId = (int) $thread['thread_id'];
        $this->states()->setSnooze($userId, $threadId, null, true);
        $this->assertStatus(403, $this->post('/t/' . $threadId . '/snooze', ['until' => ''], false));
        $this->assertStatus(403, $this->post('/inbox/bulk', [
            'scope' => 'snoozed', 'action' => 'restore', 'thread_ids' => [$threadId],
        ], false));
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);

        $other = $this->makeUser();
        $pending = $this->makeThread($board, $other, 'Held private topic');
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [(int) $pending['thread_id']]);
        $this->assertStatus(404, $this->post('/t/' . $pending['thread_id'] . '/snooze', ['until' => 'manual']));

        $this->users()->setStatus($userId, 'suspended', '2999-01-01 00:00:00');
        $this->actingAs($this->users()->find($userId));
        foreach (['manual', 'tomorrow', ''] as $intent) {
            $this->assertStatus(403, $this->post('/t/' . $threadId . '/snooze', ['until' => $intent]));
        }
        $this->assertStatus(403, $this->post('/inbox/bulk', [
            'scope' => 'snoozed', 'action' => 'restore', 'thread_ids' => [$threadId],
        ]));
        self::assertSame(1, (int) $this->states()->find($userId, $threadId)['snoozed_indefinitely']);
    }
}
