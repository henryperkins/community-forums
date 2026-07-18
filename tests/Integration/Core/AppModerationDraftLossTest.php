<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

/**
 * Anti-draft-loss coverage for the remaining flash-redirect sites fixed in the
 * 2026-07-18 remediation: thread split, role clone, appeal resolution, the
 * announcement rate limit, the honest email requeue, and the reauthed webhook
 * delete.
 */
final class AppModerationDraftLossTest extends TestCase
{
    public function test_split_failure_rerenders_thread_with_title_and_selection(): void
    {
        $admin = $this->makeAdmin(['password' => 'password123']);
        $category = $this->makeCategory('Split');
        $board = $this->makeBoard($category);
        $author = $this->makeUser();
        $made = $this->makeThread($board, $author, 'Splittable', 'Opening.');
        $threadId = (int) $made['thread_id'];

        $this->actingAs($admin);
        // No replies selected → the service refuses; the thread re-renders at
        // 422 with the typed title preserved and the dialog open.
        $res = $this->post('/mod/t/' . $threadId . '/split', [
            'post_ids' => [],
            'title' => 'My carefully typed new topic title',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'My carefully typed new topic title');
    }

    public function test_role_clone_failure_preserves_the_typed_name(): void
    {
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        (new SettingRepository($this->db))->set('features', ['capabilities' => true]);
        $roleId = (int) $this->db->fetchValue('SELECT id FROM roles ORDER BY id LIMIT 1');
        self::assertGreaterThan(0, $roleId, 'expected a seeded system role');

        $res = $this->post('/admin/roles/' . $roleId . '/clone', [
            'name' => 'Deputy wardens (carefully named)',
            'current_password' => 'WRONG',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'Deputy wardens (carefully named)');
    }

    public function test_appeal_resolution_failure_preserves_outcome_and_note(): void
    {
        // Build an appealable action: admin deletes the member's post, the
        // member appeals it, then a resolution with an invalid outcome fails.
        $admin = $this->makeAdmin(['password' => 'password123']);
        $category = $this->makeCategory('Appeals');
        $board = $this->makeBoard($category);
        $member = $this->makeUser(['password' => 'password123']);
        $made = $this->makeThread($board, $member, 'Appealed', 'Opening post.');
        // Appeal a deleted REPLY: removing the opening post retracts the whole
        // topic instead of marking the post deleted, which is not appealable
        // via /appeals/posts (matches AppModerationAppealsTest seeding).
        $postId = $this->posting()->reply($this->userEntity($member), $made['thread_id'], ['body' => 'Removed body.']);

        $this->actingAs($admin);
        $this->post('/posts/' . $postId . '/delete', ['reason' => 'Removed for review']);

        $this->actingAs($member);
        $this->post('/appeals/posts/' . $postId, ['reason' => 'This was on topic.']);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM moderation_appeals'));
        $appealId = (int) $this->db->fetchValue('SELECT id FROM moderation_appeals ORDER BY id LIMIT 1');

        $this->actingAs($admin);
        $res = $this->post('/mod/appeals/' . $appealId . '/resolve', [
            'outcome' => 'not-a-real-outcome',
            'note' => 'A considered resolution note.',
        ]);

        $this->assertStatus(422, $res);
        $this->assertSeeText($res, 'A considered resolution note.');
        $this->assertSeeText($res, 'Choose a valid appeal outcome.');
    }

    public function test_announcement_rate_limit_rerenders_with_message_preserved(): void
    {
        $this->actingAs($this->makeAdmin(['password' => 'password123']));

        $status = 0;
        $last = null;
        // Publish until the announce policy trips; the 429 must re-render the
        // form with the typed message, never the kernel error page.
        for ($i = 0; $i < 30; $i++) {
            $last = $this->post('/admin/announcements', [
                'message' => 'Maintenance window announcement draft',
                'dismissible' => '1',
            ]);
            $status = $last->status();
            if ($status === 429) {
                break;
            }
            $this->assertRedirect($last);
        }

        self::assertSame(429, $status, 'expected the announce rate limit to trip');
        $this->assertSeeText($last, 'Maintenance window announcement draft');
    }

    public function test_requeue_of_a_non_failed_delivery_reports_the_noop(): void
    {
        // PR #44 spec §4: the old test requeued id 999999 and proved only the
        // missing-row path. A REAL delivery in a non-failed state must no-op
        // with its row untouched.
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $deliveries = new \App\Repository\EmailDeliveryRepository($this->db);
        $id = $deliveries->enqueue(null, 'sent-already@example.test', 'system', 'Done', null);
        $this->db->run("UPDATE email_deliveries SET status = 'sent', attempt_count = 1 WHERE id = ?", [$id]);

        $res = $this->post('/admin/email/deliveries/' . $id . '/requeue');

        $this->assertRedirectContains($res, '/admin/email');
        $flash = urldecode(implode(' ', $res->cookieHeaders()));
        self::assertStringContainsString('not in a failed state', $flash);
        $row = $this->db->fetch('SELECT status, attempt_count FROM email_deliveries WHERE id = ?', [$id]);
        self::assertSame('sent', (string) $row['status']);
        self::assertSame(1, (int) $row['attempt_count']);
    }

    public function test_webhook_delete_requires_reauth(): void
    {
        (new SettingRepository($this->db))->set('features', ['webhooks' => true, 'service_secrets' => true]);
        $this->actingAs($this->makeAdmin(['password' => 'password123']));
        $this->post('/admin/webhooks', [
            'name' => 'Doomed hook',
            'url' => 'https://example.test/hook',
            'events' => ['ping'],
            'current_password' => 'password123',
        ]);
        $id = (int) $this->db->fetchValue('SELECT id FROM webhooks WHERE name = ?', ['Doomed hook']);
        self::assertGreaterThan(0, $id);

        $wrong = $this->post('/admin/webhooks/' . $id . '/delete', ['current_password' => 'WRONG']);
        $this->assertStatus(422, $wrong);
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks WHERE id = ?', [$id]));

        $right = $this->post('/admin/webhooks/' . $id . '/delete', ['current_password' => 'password123']);
        $this->assertRedirectContains($right, '/admin/webhooks');
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM webhooks WHERE id = ?', [$id]));
    }
}
