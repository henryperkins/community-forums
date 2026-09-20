<?php

declare(strict_types=1);
namespace Tests\Integration\Core;

use App\Repository\SubscriptionRepository;
use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

final class AppNotificationPreferencesRepairTest extends TestCase
{
    protected function setUp(): void { parent::setUp(); $this->makeAdmin(); }

    public function test_owned_reductions_and_atomic_escalation_for_every_restricted_state(): void
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author);
        $subs = new SubscriptionRepository($this->db);
        foreach (['suspended', 'banned', 'deactivated', 'pending_deletion'] as $state) {
            $member = $this->makeUser(['status' => $state, 'suspended_until' => '2099-01-01 00:00:00']);
            $uid = (int) $member['id'];
            $this->db->run('UPDATE users SET timezone = ?, digest_hour = 9 WHERE id = ?', ['UTC', $uid]);
            $subs->set($uid, 'thread', $thread['thread_id'], true, true, 'daily');
            $id = $subs->get($uid, 'thread', $thread['thread_id'])['id'];
            $this->actingAs($member);
            $path = '/settings/notifications/subscriptions/' . $id;
            $invalid = $this->post('/t/' . $thread['thread_id'] . '/subscribe', ['frequency' => 'weekly', 'email' => '1']);
            $this->assertStatus(422, $invalid);
            self::assertStringContainsString('value="weekly" selected', $invalid->body());
            $this->assertStatus(403, $this->post('/settings/notifications', ['timezone' => 'America/New_York', 'digest_hour' => '10', 'pause_all_email' => '1']));
            self::assertFalse((new UserPreferenceRepository($this->db))->get($uid)['pause_all_email'] ?? false);
            self::assertSame(9, (int) $this->users()->find($uid)['digest_hour']);

            $this->assertRedirect($this->post($path, ['frequency' => 'daily', 'in_app' => '1']), '/settings/notifications');
            self::assertSame(0, (int) $subs->get($uid, 'thread', $thread['thread_id'])['email_enabled']);
            $this->assertStatus(403, $this->post($path, ['frequency' => 'instant', 'email' => '1']));
            self::assertSame('daily', $subs->get($uid, 'thread', $thread['thread_id'])['frequency']);
            $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
            $subs->set($uid, 'board', (int) $board['id'], true, true, 'daily');
            $this->assertStatus(403, $this->post('/b/' . $board['id'] . '/subscribe', ['frequency' => 'instant', 'in_app' => '1']));
            self::assertSame(1, (int) $subs->get($uid, 'board', (int) $board['id'])['email_enabled']);
            $this->assertRedirect($this->post('/b/' . $board['id'] . '/subscribe', ['frequency' => 'off']), '/settings/notifications');
            self::assertSame('off', $subs->get($uid, 'board', (int) $board['id'])['frequency']);
            $subs->set($uid, 'thread', $thread['thread_id'], true, true, 'daily');
            $this->assertRedirect($this->post($path, ['frequency' => 'daily', 'email' => '1']), '/settings/notifications');
            self::assertSame(0, (int) $subs->get($uid, 'thread', $thread['thread_id'])['in_app_enabled']);
            $this->assertStatus(403, $this->post($path, ['frequency' => 'instant', 'email' => '1']));

            $this->assertRedirect($this->post('/t/' . $thread['thread_id'] . '/subscribe', ['frequency' => 'off']), '/settings/notifications');
            self::assertSame('off', $subs->get($uid, 'thread', $thread['thread_id'])['frequency']);
            $this->assertRedirect($this->post('/settings/notifications', ['timezone' => '', 'digest_hour' => '', 'pause_all_email' => '1']), '/settings/notifications');
            $this->assertStatus(403, $this->post('/settings/notifications', ['timezone' => 'UTC', 'digest_hour' => '8', 'pause_all_email' => '1']));
            $this->assertStatus(403, $this->post('/settings/notifications', ['timezone' => 'UTC', 'digest_hour' => '']));
            self::assertNull($this->users()->find($uid)['digest_hour']);
            self::assertTrue((new UserPreferenceRepository($this->db))->get($uid)['pause_all_email']);
            $this->db->run("UPDATE boards SET visibility = 'public' WHERE id = ?", [$board['id']]);
        }
    }

    public function test_settings_validation_retains_draft_and_normalizes_utc(): void
    {
        $member = $this->makeUser(); $this->actingAs($member);
        foreach (['Mars/Olympus', ['array']] as $zone) {
            $page = $this->post('/settings/notifications', ['timezone' => $zone, 'digest_hour' => '25', 'pause_all_email' => '1']);
            $this->assertStatus(422, $page);
            self::assertStringContainsString('value="25" selected', $page->body());
            self::assertNull($this->users()->find((int) $member['id'])['digest_hour']);
        }
        foreach (['-1', '24', '9abc', '1.5', ['crafted']] as $hour) {
            $this->assertStatus(422, $this->post('/settings/notifications', ['timezone' => 'UTC', 'digest_hour' => $hour]));
            self::assertNull($this->users()->find((int) $member['id'])['digest_hour']);
        }
        foreach ([null, ''] as $zone) {
            $this->assertRedirect($this->post('/settings/notifications', ['timezone' => $zone, 'digest_hour' => '0']), '/settings/notifications');
            self::assertSame('UTC', $this->users()->find((int) $member['id'])['timezone']);
        }
        $this->assertStatus(403, $this->post('/settings/notifications', ['digest_hour' => ''], false));
    }

    public function test_owned_controls_validate_all_channels_off_and_keep_thread_override(): void
    {
        $member = $this->makeUser(); $other = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory()); $thread = $this->makeThread($board, $other);
        $subs = new SubscriptionRepository($this->db);
        $subs->set((int) $member['id'], 'board', (int) $board['id'], true, true, 'instant');
        $subs->set((int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $id = $subs->get((int) $member['id'], 'thread', $thread['thread_id'])['id'];
        $this->actingAs($member);
        $path = '/settings/notifications/subscriptions/' . $id;
        $bad = $this->post($path, ['frequency' => 'weekly', 'email' => '1']);
        $this->assertStatus(422, $bad);
        $this->assertStatus(422, $this->post($path, ['frequency' => 'off', 'email' => ['crafted']]));
        self::assertSame('daily', $subs->get((int) $member['id'], 'thread', $thread['thread_id'])['frequency']);

        self::assertStringContainsString('value="weekly" selected', $bad->body());
        $this->assertRedirect($this->post($path, ['frequency' => 'daily']), '/settings/notifications');
        self::assertSame('off', $subs->effectiveForThread((int) $member['id'], $thread['thread_id'], (int) $board['id'])['frequency']);
        self::assertStringContainsString('action="' . $path . '"', $this->get('/settings/notifications')->body());
        $this->assertStatus(403, $this->post($path, ['frequency' => 'off'], false));
        $this->actingAs($other); $this->assertStatus(404, $this->post($path, ['frequency' => 'off']));
    }

    public function test_legacy_validation_keeps_drafts_on_thread_and_settings_fallbacks(): void
    {
        $member = $this->makeUser(); $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $member);
        $subs = new SubscriptionRepository($this->db);
        $subs->set((int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $this->actingAs($member);
        $threadPage = $this->post('/t/' . $thread['thread_id'] . '/subscribe', ['frequency' => 'weekly', 'email' => '1']);
        $this->assertStatus(422, $threadPage);
        self::assertStringContainsString('value="weekly" selected', $threadPage->body());
        self::assertStringContainsString('name="email" value="1" checked', $threadPage->body());
        $this->db->run("UPDATE boards SET visibility = 'private' WHERE id = ?", [$board['id']]);
        foreach (['/t/' . $thread['thread_id'] . '/subscribe', '/b/' . $board['id'] . '/subscribe'] as $path) {
            $page = $this->post($path, ['frequency' => 'weekly', 'in_app' => '1']);
            $this->assertStatus(422, $page);
            self::assertStringContainsString('value="weekly" selected', $page->body());
            self::assertStringContainsString('Notification settings', $page->body());
        }
    }
}
