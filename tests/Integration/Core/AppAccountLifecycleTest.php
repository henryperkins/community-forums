<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Service\AccountLifecycleService;
use App\Repository\AccountDeletionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SessionRepository;
use App\Security\PasswordHasher;
use Tests\Support\TestCase;

final class AppAccountLifecycleTest extends TestCase
{
    public function test_user_can_export_account_archive_without_secrets(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser([
            'username' => 'exporter',
            'email' => 'exporter@example.test',
            'display_name' => 'Export Me',
        ]);
        $this->actingAs($user);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $user, 'Exportable thread', 'Exportable body');

        $response = $this->get('/settings/account/export');

        $this->assertStatus(200, $response);
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
        self::assertStringContainsString('attachment; filename="retroboards-account-export.json"', (string) $response->getHeader('Content-Disposition'));
        $payload = json_decode($response->body(), true);
        self::assertSame('RetroBoards', $payload['app']);
        self::assertSame('exporter', $payload['profile']['username']);
        self::assertSame('Export Me', $payload['profile']['display_name']);
        self::assertArrayNotHasKey('password_hash', $payload['profile']);
        self::assertNotEmpty($payload['posts']);
        self::assertSame($thread['thread_id'], (int) $payload['posts'][0]['thread_id']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_exported'"));
    }

    public function test_user_can_deactivate_and_reactivate_account(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'sleepy', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');

        $response = $this->post('/settings/account/deactivate', ['current_password' => 'password123']);
        $this->assertRedirect($response, '/settings/account/lifecycle');
        self::assertSame('deactivated', (string) $this->users()->find((int) $user['id'])['status']);

        $blocked = $this->post('/settings/account', ['display_name' => 'Should not save']);
        $this->assertStatus(403, $blocked);

        $reactivated = $this->post('/settings/account/reactivate');
        $this->assertRedirect($reactivated, '/settings/account/lifecycle');
        self::assertSame('active', (string) $this->users()->find((int) $user['id'])['status']);
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_deactivated'"));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_reactivated'"));
    }

    public function test_deletion_request_can_be_canceled_during_grace_period(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser(['username' => 'deleter', 'password' => 'password123']);
        $this->actingAs($user);
        $this->get('/settings/account/lifecycle');

        $requested = $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->assertRedirect($requested, '/settings/account/lifecycle');
        self::assertSame('pending_deletion', (string) $this->users()->find((int) $user['id'])['status']);
        $row = $this->db->fetch('SELECT * FROM account_deletion_requests WHERE user_id = ?', [(int) $user['id']]);
        self::assertNotNull($row);
        self::assertSame('pending', (string) $row['status']);
        self::assertStringStartsWith(gmdate('Y-m-d', time() + 30 * 86400), (string) $row['purge_after']);

        $canceled = $this->post('/settings/account/delete/cancel');
        $this->assertRedirect($canceled, '/settings/account/lifecycle');
        self::assertSame('active', (string) $this->users()->find((int) $user['id'])['status']);
        self::assertSame('canceled', (string) $this->db->fetchValue('SELECT status FROM account_deletion_requests WHERE id = ?', [(int) $row['id']]));
    }

    public function test_final_active_admin_cannot_deactivate_or_request_deletion(): void
    {
        $admin = $this->makeAdmin(['username' => 'owner', 'password' => 'password123']);
        $this->actingAs($admin);
        $this->get('/settings/account/lifecycle');

        $deactivate = $this->post('/settings/account/deactivate', ['current_password' => 'password123']);
        $this->assertStatus(422, $deactivate);
        $this->assertSeeText($deactivate, 'Add another active admin');

        $delete = $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->assertStatus(422, $delete);
        $this->assertSeeText($delete, 'Add another active admin');
        self::assertSame('active', (string) $this->users()->find((int) $admin['id'])['status']);
    }

    public function test_due_deletion_purge_anonymizes_account_and_removes_pii(): void
    {
        $this->makeAdmin();
        $user = $this->makeUser([
            'username' => 'purge-me',
            'email' => 'purge-me@example.test',
            'display_name' => 'Purge Me',
            'password' => 'password123',
        ]);
        $this->users()->updateProfileFull((int) $user['id'], 'Purge Me', 'private bio', 'Somewhere', 'https://example.test', 'they/them', 'signature');
        $this->actingAs($user);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $user, 'Kept discussion', 'Keep the conversation intact');
        $this->get('/settings/account/lifecycle');
        $this->post('/settings/account/delete/request', ['current_password' => 'password123']);
        $this->db->run(
            "UPDATE account_deletion_requests SET purge_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ?",
            [(int) $user['id']],
        );

        $result = (new AccountLifecycleService(
            $this->db,
            $this->users(),
            new AccountDeletionRepository($this->db),
            new SessionRepository($this->db),
            new ModerationLogRepository($this->db),
            new PasswordHasher(),
        ))->purgeDue();

        self::assertSame(['purged' => 1], $result);
        $row = $this->users()->find((int) $user['id']);
        self::assertSame('deleted', (string) $row['status']);
        self::assertSame('deleted-user-' . $user['id'], (string) $row['username']);
        self::assertSame('Deleted user', (string) $row['display_name']);
        self::assertSame('deleted-user-' . $user['id'] . '@deleted.invalid', (string) $row['email']);
        self::assertNull($row['password_hash']);
        self::assertNull($row['bio']);
        self::assertNull($row['location']);
        self::assertNull($row['website']);
        self::assertNull($row['pronouns']);
        self::assertNull($row['signature']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM sessions WHERE user_id = ?', [(int) $user['id']]));
        self::assertSame(1, (int) $this->db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ?', [(int) $thread['thread_id']]));
        self::assertSame(1, (int) $this->db->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'account_purged' AND actor_id IS NULL"));
    }
}
