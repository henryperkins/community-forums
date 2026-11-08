<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Database;
use App\Repository\EmailSuppressionRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserPreferenceRepository;
use App\Security\WriteGate;
use App\Service\EmailPreferenceService;
use App\Service\NotificationSettingsService;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\TestCase;

/** Interleave a real second connection immediately before the member's delete. */
final class ResubscriptionDeleteStatement extends PDOStatement
{
    protected function __construct(private \Closure $beforeDelete) {}

    public function execute(?array $params = null): bool
    {
        if (preg_match('/^DELETE FROM email_suppressions\b/i', $this->queryString)) {
            ($this->beforeDelete)();
        }
        return parent::execute($params);
    }
}

final class AppEmailResubscriptionTest extends TestCase
{
    private const PATH = '/settings/notifications/email/resubscribe';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    private function suppressions(): EmailSuppressionRepository
    {
        return new EmailSuppressionRepository($this->db);
    }

    public function test_a_member_suspended_after_sign_in_cannot_remove_their_unsubscribe(): void
    {
        $member = $this->makeUser();
        $this->actingAs($member);
        $this->suppressions()->suppress($member['email'], 'unsubscribe');
        $this->assertSeeText($this->get('/settings/notifications'), 'Turn notification emails back on');
        $this->users()->setStatus((int) $member['id'], 'suspended', '2099-01-01 00:00:00');

        $response = $this->post(self::PATH);

        $this->assertStatus(403, $response);
        $this->assertSeeText($response, 'Your account is suspended and cannot perform this action.');
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($member['email']));
        $this->assertDontSeeText($response, 'Notification emails are back on.');
        $settings = $this->get('/settings/notifications');
        $this->assertSeeText($settings, 'Notification emails are off');
        $this->assertDontSeeText($settings, 'Notification emails are back on.');
    }

    public static function restrictedStates(): array
    {
        return array_map(static fn (string $state): array => [$state], [
            'banned', 'deactivated', 'pending_deletion', 'deleted', 'suspended',
        ]);
    }

    #[DataProvider('restrictedStates')]
    public function test_other_restricted_states_keep_the_unsubscribe(string $state): void
    {
        $member = $this->makeUser();
        $this->actingAs($member);
        $this->users()->setStatus((int) $member['id'], $state, null);
        $this->suppressions()->suppress($member['email'], 'unsubscribe');

        $response = $this->post(self::PATH);

        $this->assertStatus(403, $response);
        $this->assertSeeText($response, 'cannot perform this action.');
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($member['email']));
    }

    public function test_resubscribing_changes_only_the_authenticated_members_unsubscribe(): void
    {
        $member = $this->makeUser();
        $other = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $other);
        $subscriptions = new SubscriptionRepository($this->db);
        $subscriptions->set((int) $member['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $beforeSubscription = $subscriptions->get((int) $member['id'], 'thread', $thread['thread_id']);
        $prefs = new UserPreferenceRepository($this->db);
        $prefs->merge((int) $member['id'], ['pause_all_email' => true, 'theme' => 'dark']);
        $this->db->run('UPDATE users SET timezone = ?, digest_hour = 9 WHERE id = ?', ['Europe/Paris', $member['id']]);
        $beforePrefs = $prefs->get((int) $member['id']);
        $beforeAccount = $this->users()->find((int) $member['id']);
        $this->suppressions()->suppress($member['email'], 'unsubscribe');
        $this->suppressions()->suppress($other['email'], 'unsubscribe');
        $this->actingAs($member);

        $response = $this->post(self::PATH, [
            'email' => $other['email'], 'user_id' => $other['id'],
            'pause_all_email' => '0', 'timezone' => 'UTC', 'digest_hour' => 'off',
        ]);

        $this->assertRedirect($response, '/settings/notifications');
        self::assertNull($this->suppressions()->reasonFor($member['email']));
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($other['email']));
        self::assertSame($beforePrefs, $prefs->get((int) $member['id']));
        self::assertSame($beforeSubscription, $subscriptions->get((int) $member['id'], 'thread', $thread['thread_id']));
        $afterAccount = $this->users()->find((int) $member['id']);
        self::assertSame($beforeAccount['timezone'], $afterAccount['timezone']);
        self::assertSame($beforeAccount['digest_hour'], $afterAccount['digest_hour']);
        $settings = $this->get('/settings/notifications');
        $this->assertDontSeeText($settings, 'Notification emails are off');
        $this->assertDontSeeText($settings, 'Notification emails are back on.');
        $this->assertSeeText($settings, 'Your other notification settings still apply.');
    }

    public function test_an_expired_suspension_uses_the_existing_write_policy(): void
    {
        $member = $this->makeUser(['status' => 'suspended', 'suspended_until' => '2020-01-01 00:00:00']);
        $this->actingAs($member);
        $this->suppressions()->suppress($member['email'], 'unsubscribe');
        $this->assertRedirect($this->post(self::PATH), '/settings/notifications');
        self::assertNull($this->suppressions()->reasonFor($member['email']));
    }

    public function test_operator_owned_suppressions_and_repeated_submits_do_not_claim_a_removal(): void
    {
        $member = $this->makeUser();
        $this->actingAs($member);
        foreach (['bounce', 'complaint', 'manual'] as $reason) {
            $this->suppressions()->suppress($member['email'], $reason);
            $this->assertRedirect($this->post(self::PATH), '/settings/notifications');
            self::assertSame($reason, $this->suppressions()->reasonFor($member['email']));
            $settings = $this->get('/settings/notifications');
            $this->assertDontSeeText($settings, 'Notification emails are back on.');
            $this->assertSeeText($settings, 'No unsubscribe was removed.');
            $this->suppressions()->unsuppress($member['email']);
        }
        $this->assertRedirect($this->post(self::PATH), '/settings/notifications');
        $this->assertSeeText($this->get('/settings/notifications'), 'No unsubscribe was removed.');
    }

    public function test_guest_and_missing_csrf_cannot_remove_an_unsubscribe(): void
    {
        $member = $this->makeUser();
        $this->suppressions()->suppress($member['email'], 'unsubscribe');
        $this->get('/login'); // Establish a valid guest CSRF token before testing authentication.
        $this->assertRedirectContains($this->post(self::PATH), '/login');
        $this->actingAs($member);
        $this->assertStatus(403, $this->post(self::PATH, [], false));
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($member['email']));
    }

    public function test_restricted_members_can_still_reduce_delivery_without_clearing_suppression(): void
    {
        $member = $this->makeUser();
        $this->actingAs($member);
        $this->db->run('UPDATE users SET digest_hour = 9 WHERE id = ?', [$member['id']]);
        $this->users()->setStatus((int) $member['id'], 'suspended', null);
        $this->suppressions()->suppress($member['email'], 'unsubscribe');

        $this->assertRedirect($this->post('/settings/notifications', [
            'timezone' => 'UTC', 'digest_hour' => 'off', 'pause_all_email' => '1',
        ]), '/settings/notifications');

        self::assertNull($this->users()->find((int) $member['id'])['digest_hour']);
        self::assertTrue((new EmailPreferenceService(new UserPreferenceRepository($this->db)))->pauseAllEmail((int) $member['id']));
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($member['email']));
    }

    public function test_the_service_uses_the_current_account_contact_and_state_instead_of_a_stale_viewer(): void
    {
        $member = $this->makeUser();
        $viewer = $this->userEntity($member);
        $newEmail = 'current-contact@example.test';
        $this->db->run('UPDATE users SET email = ? WHERE id = ?', [$newEmail, $viewer->id()]);
        $this->suppressions()->suppress($viewer->email(), 'unsubscribe');
        $this->suppressions()->suppress($newEmail, 'unsubscribe');
        $service = new NotificationSettingsService($this->db, $this->users(),
            new EmailPreferenceService(new UserPreferenceRepository($this->db)), new WriteGate(), $this->suppressions());

        self::assertTrue($service->resubscribeEmail($viewer));
        self::assertNull($this->suppressions()->reasonFor($newEmail));
        self::assertSame('unsubscribe', $this->suppressions()->reasonFor($viewer->email()));

        $this->suppressions()->suppress($newEmail, 'unsubscribe');
        $this->users()->setStatus($viewer->id(), 'suspended', null);
        try {
            $service->resubscribeEmail($viewer);
            self::fail('The old active viewer cannot authorize a fresh suspension.');
        } catch (\App\Core\ForbiddenException) {
            self::assertSame('unsubscribe', $this->suppressions()->reasonFor($newEmail));
        }
    }

    public static function operatorChanges(): array
    {
        return [
            'bounce, emulated' => ['bounce', true], 'complaint, emulated' => ['complaint', true],
            'bounce, native' => ['bounce', false], 'complaint, native' => ['complaint', false],
        ];
    }

    #[DataProvider('operatorChanges')]
    public function test_a_concurrent_operator_change_is_preserved_while_the_account_is_locked(string $reason, bool $emulated): void
    {
        // Commit only fixtures owned here so a second real connection can see them.
        $this->pdo->rollBack();
        $this->pdo->beginTransaction();
        $this->withoutHarnessTransaction(function () use ($reason, $emulated): void {
            $admin = $this->makeAdmin();
            $member = $this->makeUser();
            $this->actingAs($member);
            $this->suppressions()->suppress($member['email'], 'unsubscribe');
            $operator = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
            $operator->run('SET SESSION innodb_lock_wait_timeout = 1');
            $ran = false;
            $accountLocked = false;
            $originalEmulation = $this->pdo->getAttribute(PDO::ATTR_EMULATE_PREPARES);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulated);
            $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [ResubscriptionDeleteStatement::class, [
                function () use ($operator, $member, $reason, &$ran, &$accountLocked): void {
                    if ($ran) { return; }
                    $ran = true;
                    try {
                        $operator->run("UPDATE users SET status = 'suspended' WHERE id = ?", [$member['id']]);
                    } catch (PDOException $error) {
                        if (($error->errorInfo[1] ?? null) !== 1205) { throw $error; }
                        $accountLocked = true;
                    }
                    $operator->run('UPDATE email_suppressions SET reason = ? WHERE email = ?', [$reason, $member['email']]);
                },
            ]]);
            try {
                $response = $this->post(self::PATH);
                $this->assertRedirect($response, '/settings/notifications');
                self::assertTrue($ran, 'The interleave must run at the real delete boundary.');
                self::assertSame($reason, (new EmailSuppressionRepository($operator))->reasonFor($member['email']),
                    'A newly committed operator suppression must survive the member delete.');
                self::assertTrue($accountLocked, 'Authorization must hold the current account lock through removal.');
                $this->assertSeeText($this->get('/settings/notifications'), 'No unsubscribe was removed.');
            } finally {
                $this->pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class]);
                $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $originalEmulation);
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                $this->suppressions()->unsuppress($member['email']);
                $this->db->run('DELETE FROM users WHERE id IN (?, ?)', [$member['id'], $admin['id']]);
            }
        });
    }
}
