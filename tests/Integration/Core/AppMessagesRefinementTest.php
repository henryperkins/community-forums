<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BlockRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppMessagesRefinementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function pair(): array
    {
        $alice = $this->makeUser(['username' => 'alice', 'post_count' => 1]);
        $bob = $this->makeUser(['username' => 'bob', 'post_count' => 1]);
        $this->db->run('UPDATE users SET post_count = 1 WHERE id IN (?, ?)', [$alice['id'], $bob['id']]);
        $alice = $this->users()->find((int) $alice['id']);
        $bob = $this->users()->find((int) $bob['id']);
        $repo = new ConversationRepository($this->db);
        $id = $repo->findOrCreateBetween((int) $alice['id'], (int) $bob['id']);
        return [$alice, $bob, $id];
    }

    public function testPollReturnsOnlyNewVisibleMessagesAndMarksOnlyReturnedMessagesRead(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messages = new DmMessageRepository($this->db);
        $old = $messages->create($id, (int) $bob['id'], 'Old', '<p>Old</p>');
        $new = $messages->create($id, (int) $bob['id'], 'New', '<p>New</p>');
        $this->actingAs($alice);
        $response = $this->post('/messages/' . $id . '/poll', ['after' => $old]);
        self::assertSame(200, $response->status());
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($new, $data['last_id']);
        self::assertStringContainsString('id="m' . $new . '"', $data['html']);
        self::assertStringNotContainsString('id="m' . $old . '"', $data['html']);
        self::assertSame($new, (int) (new ConversationRepository($this->db))->membership($id, (int) $alice['id'])['last_read_message_id']);
        self::assertSame(0, $data['dm_unread']);
        self::assertSame(405, $this->get('/messages/' . $id . '/poll')->status());
    }

    public function testPollDoesNotRevealPrivateCounselToAnOutsiderOrAfterRollback(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $this->actingAs($this->makeAdmin());
        self::assertSame(404, $this->post('/messages/' . $id . '/poll')->status());
        $this->actingAs($alice);
        (new SettingRepository($this->db))->set('features', ['dms' => false]);
        self::assertSame(404, $this->post('/messages/' . $id . '/poll')->status());
    }

    public function testPollHonoursGroupJoinBoundaryAndStopsAfterLeavingOrRollback(): void
    {
        [$alice, $bob] = $this->pair();
        $repo = new ConversationRepository($this->db);
        $messages = new DmMessageRepository($this->db);
        $id = $repo->createGroup((int) $bob['id'], 'Counsel', []);
        $old = $messages->create($id, (int) $bob['id'], 'Before join', '<p>Before join</p>');
        $repo->addParticipant($id, (int) $bob['id'], (int) $alice['id'], $old);
        $messages->create($id, (int) $bob['id'], 'After join', '<p>After join</p>');
        $this->actingAs($alice);
        $response = $this->post('/messages/' . $id . '/poll', ['after' => 0]);
        self::assertSame(200, $response->status());
        self::assertStringNotContainsString('Before join', $response->body());
        self::assertStringContainsString('After join', $response->body());
        (new SettingRepository($this->db))->set('features', ['group_dms' => false]);
        self::assertSame(404, $this->post('/messages/' . $id . '/poll')->status());
        (new SettingRepository($this->db))->set('features', ['group_dms' => true]);
        $repo->removeParticipant($id, (int) $bob['id'], (int) $alice['id']);
        self::assertSame(404, $this->post('/messages/' . $id . '/poll')->status());
    }

    public function testBellAndNavigationCarryUnreadConversations(): void
    {
        [$alice, $bob, $id] = $this->pair();
        (new DmMessageRepository($this->db))->create($id, (int) $bob['id'], 'Unread', '<p>Unread</p>');
        $this->actingAs($alice);
        $data = json_decode($this->get('/notifications/bell')->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1, $data['dm_unread']);
        self::assertStringContainsString('data-dm-unread-count', $this->get('/messages')->body());
        (new ConversationRepository($this->db))->setMute($id, (int) $alice['id'], true);
        $data = json_decode($this->get('/notifications/bell')->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $data['dm_unread']);
    }

    public function testIneligibleRecipientsPreserveTheBodyAndRecipientAt422(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $this->actingAs($alice);
        (new BlockRepository($this->db))->block((int) $bob['id'], (int) $alice['id']);
        foreach (['/messages', '/messages/' . $id] as $url) {
            $response = $this->post($url, ['to' => 'bob', 'body' => 'Keep my private draft']);
            self::assertSame(422, $response->status());
            self::assertStringContainsString('Keep my private draft', $response->body());
            self::assertStringContainsString('You cannot message this person.', $response->body());
        }
    }

    public function testDmPickerFiltersBlocksOptOutSuspensionAndSelfButOrdinaryMentionsRemain(): void
    {
        [$alice, $bob] = $this->pair();
        $this->makeUser(['username' => 'bobhidden', 'allow_dms' => 'none']);
        $this->db->run("UPDATE users SET allow_dms = 'none' WHERE username = 'bobhidden'");
        $this->makeUser(['username' => 'bobsuspended', 'suspended_until' => gmdate('Y-m-d H:i:s', time() + 3600)]);
        $this->makeUser(['username' => 'boballowed']);
        (new BlockRepository($this->db))->block((int) $bob['id'], (int) $alice['id']);
        $this->actingAs($alice);
        $response = $this->get('/composer/suggest', ['trigger' => '@', 'q' => 'bob', 'context' => 'dm-recipient']);
        $data = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['@boballowed'], array_column($data['items'], 'label'));
        $data = json_decode($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'alice', 'context' => 'dm-recipient'])->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame([], $data['items']);
        $data = json_decode($this->get('/composer/suggest', ['trigger' => '@', 'q' => 'bob', 'context' => 'thread'])->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertContains('@bob', array_column($data['items'], 'label'));
    }

    public function testGroupRollbackPreservesExistingGroupHistoryInTheLists(): void
    {
        [$alice, $bob] = $this->pair();
        (new ConversationRepository($this->db))->createGroup((int) $alice['id'], 'Hidden counsel', [(int) $bob['id']]);
        (new SettingRepository($this->db))->set('features', ['group_dms' => false]);
        $this->actingAs($alice);
        foreach (['/messages', '/messages/new'] as $route) {
            self::assertStringContainsString('Hidden counsel', $this->get($route)->body());
        }
    }

    public function testPollCapsCatchupAndNeverAcknowledgesAForgedCursor(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $messages = new DmMessageRepository($this->db);
        $ids = [];
        for ($i = 0; $i < 51; $i++) { $ids[] = $messages->create($id, (int) $bob['id'], 'Letter', '<p>Letter</p>'); }
        $this->actingAs($alice);
        $data = json_decode($this->post('/messages/' . $id . '/poll', ['after' => 0])->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(50, substr_count($data['html'], 'data-message-id='));
        self::assertTrue($data['has_more']);
        self::assertSame($ids[49], $data['last_id']);
        $data = json_decode($this->post('/messages/' . $id . '/poll', ['after' => PHP_INT_MAX])->body(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('', $data['html']);
        self::assertSame($ids[49], (int) (new ConversationRepository($this->db))->membership($id, (int) $alice['id'])['last_read_message_id']);
        self::assertSame(1, $data['dm_unread']);
    }

    public function testPresenceRespectsPrivacyBlocksAndFeatureRollback(): void
    {
        [$alice, $bob, $id] = $this->pair();
        $this->db->run('UPDATE users SET last_seen_at = UTC_TIMESTAMP(), show_presence = 1 WHERE id = ?', [$bob['id']]);
        $this->actingAs($alice);
        $poll = fn () => json_decode($this->post('/messages/' . $id . '/poll')->body(), true, flags: JSON_THROW_ON_ERROR)['presence'][(int) $bob['id']];
        self::assertSame('online', $poll());
        $this->db->run('UPDATE users SET show_presence = 0 WHERE id = ?', [$bob['id']]);
        self::assertSame('offline', $poll());
        $this->db->run('UPDATE users SET show_presence = 1 WHERE id = ?', [$bob['id']]);
        (new BlockRepository($this->db))->block((int) $bob['id'], (int) $alice['id']);
        self::assertSame('offline', $poll());
        (new SettingRepository($this->db))->set('features', ['presence' => false]);
        self::assertSame('offline', $poll());
    }

    public function testEligibilityErrorsPreserveDialogAndStandaloneDrafts(): void
    {
        [$alice, $bob] = $this->pair();
        $this->actingAs($alice);
        foreach (['', 'alice', 'missing_member'] as $to) {
            $response = $this->post('/messages', ['to' => $to, 'body' => 'Keep this draft', 'origin' => 'dialog']);
            self::assertSame(422, $response->status());
            self::assertStringContainsString('Keep this draft', $response->body());
            self::assertMatchesRegularExpression('/<details[^>]+class="dm-compose-details"[^>]*open/', $response->body());
        }
        $this->db->run("UPDATE users SET allow_dms = 'none' WHERE id = ?", [$bob['id']]);
        $response = $this->post('/messages', ['to' => 'bob', 'body' => 'Keep the standalone draft']);
        self::assertSame(422, $response->status());
        self::assertStringContainsString('Keep the standalone draft', $response->body());
        $this->db->run("UPDATE users SET status = 'suspended' WHERE id = ?", [$alice['id']]);
        self::assertSame(403, $this->post('/messages', ['to' => 'bob', 'body' => 'Cannot send'])->status());
    }
}
