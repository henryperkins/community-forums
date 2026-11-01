<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Mail\ArrayMailer;
use App\Repository\BadgeRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\BadgeService;
use App\Service\EmailChangeService;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use PDO;
use PDOStatement;
use Tests\Support\TestCase;

/** Pauses one real SELECT after fetching its snapshot, before the caller can use it. */
final class RecoveryEmailLookupStatement extends PDOStatement
{
    protected function __construct(private \Closure $afterLookup) {}

    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        if ($row !== false && $this->queryString === 'SELECT * FROM users WHERE email = ?') {
            ($this->afterLookup)();
        }
        return $row;
    }
}

final class RecoveryEmailChangeRaceTest extends TestCase
{
    private ArrayMailer $mailer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new ArrayMailer();
        (new SettingRepository($this->db))->set('features', ['email_change' => true]);
    }

    private function resets(?Database $db = null): PasswordResetService
    {
        $db ??= $this->db;
        return new PasswordResetService($db, new UserRepository($db), new VerificationRepository($db), new SessionRepository($db),
            new PasswordHasher(), $this->mailer, $this->config, new SettingRepository($db));
    }

    private function verifies(): EmailVerificationService
    {
        return new EmailVerificationService($this->db, $this->users(), new VerificationRepository($this->db),
            new BadgeService($this->db, new BadgeRepository($this->db), $this->users()), $this->mailer, $this->config,
            new SettingRepository($this->db));
    }

    private function changes(): EmailChangeService
    {
        $settings = new SettingRepository($this->db);
        return new EmailChangeService($this->db, $this->users(), new VerificationRepository($this->db), new SessionRepository($this->db),
            new EmailDeliveryRepository($this->db), new ModerationLogRepository($this->db), $this->mailer, $this->config,
            new ReauthGate(new PasswordHasher()), new WriteGate(), new FeatureFlags($settings), new OAuthIdentityRepository($this->db));
    }

    private function token(): string
    {
        $last = $this->mailer->sent[count($this->mailer->sent) - 1];
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $last['text'], $match));
        return $match[1];
    }

    private function rejected(callable $action): void
    {
        try {
            $action();
            self::fail('A stale recovery proof must not be applied.');
        } catch (ValidationException $error) {
            self::assertNotEmpty($error->errors);
            self::assertSame([], $error->old);
        }
    }

    public function testNewRecoveryAndRegistrationLinksRecordTheContactTheyProve(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $this->resets()->request($actor->email());
        self::assertSame($actor->email(), $this->resets()->findValid($this->token())['new_email']);
        $this->verifies()->issue($actor->id(), $actor->email());
        self::assertSame($actor->email(), $this->verifies()->findValid($this->token())['new_email']);
    }

    public function testRegistrationIssueRejectsAnEarlierContactSnapshot(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $changes = $this->changes();
        $changes->issue($actor, 'current@example.test', 'password123', null, 'session');
        $changes->confirm($actor, $this->token(), null);
        $beforeMail = $this->mailer->count();
        $this->verifies()->issue($actor->id(), $actor->email());
        self::assertSame($beforeMail, $this->mailer->count());
        self::assertNull((new VerificationRepository($this->db))->findOutstandingForUser($actor->id(), 'email_verify'));
        $this->verifies()->issue($actor->id(), 'current@example.test');
        self::assertSame('current@example.test', $this->mailer->sent[$beforeMail]['to']);
    }

    public function testResetRejectsAStaleLookupAfterExpiryOrAddressReplacement(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->resets();
        $service->request($actor->email());
        $verification = $service->findValid($this->token());
        $this->db->run('UPDATE verifications SET expires_at = UTC_TIMESTAMP() WHERE id = ?', [$verification['id']]);
        $this->rejected(fn () => $service->reset($verification, 'newpassword9', 'newpassword9'));
        $service->request($actor->email());
        $verification = $service->findValid($this->token());
        $this->db->run('UPDATE users SET email = ? WHERE id = ?', ['current@example.test', $actor->id()]);
        $this->rejected(fn () => $service->reset($verification, 'newpassword9', 'newpassword9'));
        self::assertTrue((new PasswordHasher())->verify('password123', $this->users()->findEntity($actor->id())->passwordHash()));
    }

    public function testRegistrationCompletionRechecksExpiryAndCurrentContact(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->verifies();
        $service->issue($actor->id(), $actor->email());
        $verification = $service->findValid($this->token());
        $this->db->run('UPDATE verifications SET expires_at = UTC_TIMESTAMP() WHERE id = ?', [$verification['id']]);
        $this->rejected(fn () => $service->verify($verification));
        $service->issue($actor->id(), $actor->email());
        $verification = $service->findValid($this->token());
        $this->db->run('UPDATE users SET email = ? WHERE id = ?', ['current@example.test', $actor->id()]);
        $this->rejected(fn () => $service->verify($verification));
        self::assertNull($this->users()->find($actor->id())['email_verified_at']);
    }

    public function testRegistrationLookupFromBeforeEmailConfirmationCannotBeAppliedAfterIt(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $registration = $this->verifies();
        $registration->issue($actor->id(), $actor->email());
        $verification = $registration->findValid($this->token());
        $changes = $this->changes();
        $changes->issue($actor, 'current@example.test', 'password123', null, 'session');
        $changes->confirm($actor, $this->token(), null);
        $this->rejected(fn () => $registration->verify($verification));
        self::assertSame('current@example.test', $this->users()->find($actor->id())['email']);
    }

    public function testLegacyNullAddressLinksRemainUsableForTheUnchangedContact(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $repo = new VerificationRepository($this->db);
        $reset = str_repeat('a', 64);
        $verify = str_repeat('b', 64);
        $repo->create($actor->id(), 'password_reset', hash('sha256', $reset), '2099-01-01 00:00:00');
        $repo->create($actor->id(), 'email_verify', hash('sha256', $verify), '2099-01-01 00:00:00');
        $this->resets()->reset($this->resets()->findValid($reset), 'newpassword9', 'newpassword9');
        $this->verifies()->verify($this->verifies()->findValid($verify));
        self::assertTrue((new PasswordHasher())->verify('newpassword9', $this->users()->findEntity($actor->id())->passwordHash()));
        self::assertNotNull($this->users()->find($actor->id())['email_verified_at']);
    }

    /** The callback owns and removes every fixture it commits. */
    private function committedActor(callable $action): void
    {
        $this->pdo->rollBack();
        $this->pdo->beginTransaction();
        $this->withoutHarnessTransaction(function () use ($action): void {
            $settings = new SettingRepository($this->db);
            $hadFeatures = $settings->has('features');
            $features = $settings->get('features');
            $settings->set('features', ['email_change' => true]);
            $actor = $this->userEntity($this->makeUser());
            try {
                $action($actor);
            } finally {
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                $this->db->run('DELETE FROM moderation_log WHERE actor_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM user_badges WHERE user_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM email_deliveries WHERE user_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM verifications WHERE user_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM users WHERE id = ?', [$actor->id()]);
                if ($hadFeatures) { $settings->set('features', $features); }
                else { $this->db->run("DELETE FROM settings WHERE `key` = 'features'"); }
            }
        });
    }

    public function testResetIssuanceCannotUseAnOldSnapshotAcrossACommittedEmailChange(): void
    {
        $this->committedActor(function (User $actor): void {
            $changes = $this->changes();
            $changes->issue($actor, 'current@example.test', 'password123', null, 'session');
            $changeToken = $this->token();
            $ran = false;
            $connection = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
            $connection->pdo()->setAttribute(PDO::ATTR_STATEMENT_CLASS, [RecoveryEmailLookupStatement::class, [function () use ($changes, $actor, $changeToken, &$ran): void {
                if (!$ran) {
                    $ran = true;
                    $changes->confirm($actor, $changeToken, null);
                }
            }]]);
            $beforeMail = $this->mailer->count();
            $this->resets($connection)->request($actor->email());
            self::assertTrue($ran, 'The real email lookup must pause at the intended race boundary.');
            self::assertSame($beforeMail, $this->mailer->count(), 'A snapshot from the old contact must not receive a newly usable recovery link.');
            self::assertNull((new VerificationRepository($this->db))->findOutstandingForUser($actor->id(), 'password_reset'));
            self::assertSame('current@example.test', $this->users()->find($actor->id())['email']);
        });
    }

    public function testResetCompletionWaitsForTheAccountBeforeConsumingThenRejectsTheConfirmedOldContactLink(): void
    {
        $this->checkCompletionInterleave('password_reset');
    }

    public function testRegistrationCompletionWaitsForTheAccountBeforeConsumingThenRejectsTheConfirmedOldContactLink(): void
    {
        $this->checkCompletionInterleave('email_verify');
    }

    private function checkCompletionInterleave(string $type): void
    {
        $this->committedActor(function (User $actor) use ($type): void {
            if ($type === 'password_reset') { $this->resets()->request($actor->email()); }
            else { $this->verifies()->issue($actor->id(), $actor->email()); }
            $recoveryToken = $this->token();
            $changes = $this->changes();
            $changes->issue($actor, 'current@example.test', 'password123', null, 'session');
            $changeToken = $this->token();
            $script = <<<'PHP'
require 'tests/bootstrap.php';
$db = new \App\Core\Database($GLOBALS['__RB_TEST_DBCONFIG']);
echo json_encode(['connection' => (int) $db->fetchValue('SELECT CONNECTION_ID()')]) . "\n";
flush();
$input = json_decode((string) fgets(STDIN), true);
try {
    $users = new \App\Repository\UserRepository($db);
    $tokens = new \App\Repository\VerificationRepository($db);
    $mailer = new \App\Mail\ArrayMailer();
    $config = $GLOBALS['__RB_TEST_CONFIG'];
    if ($input['type'] === 'password_reset') {
        $service = new \App\Service\PasswordResetService($db, $users, $tokens, new \App\Repository\SessionRepository($db), new \App\Security\PasswordHasher(), $mailer, $config);
        $service->reset($service->findValid($input['token']), 'newpassword9', 'newpassword9');
    } else {
        $service = new \App\Service\EmailVerificationService($db, $users, $tokens, new \App\Service\BadgeService($db, new \App\Repository\BadgeRepository($db), $users), $mailer, $config);
        $service->verify($service->findValid($input['token']));
    }
    echo "accepted\n";
} catch (\App\Core\ValidationException) {
    echo "rejected\n";
} catch (\Throwable $error) {
    echo 'error:' . get_class($error) . "\n";
}
PHP;
            $this->pdo->beginTransaction();
            $this->users()->findForUpdate($actor->id());
            $process = proc_open([PHP_BINARY, '-r', $script], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
            self::assertIsResource($process);
            try {
                stream_set_timeout($pipes[1], 5);
                $ready = json_decode((string) fgets($pipes[1]), true);
                self::assertIsInt($ready['connection'] ?? null);
                fwrite($pipes[0], json_encode(['token' => $recoveryToken, 'type' => $type]) . "\n");
                fflush($pipes[0]);
                $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
                $blocked = false;
                $deadline = microtime(true) + 5;
                do {
                    foreach ($observer->fetchAll('SHOW FULL PROCESSLIST') as $processRow) {
                        if ((int) $processRow['Id'] === $ready['connection'] && is_string($processRow['Info'])
                            && (str_contains($processRow['Info'], 'FOR UPDATE') || str_contains($processRow['Info'], 'UPDATE users SET'))) {
                            $blocked = true;
                            break;
                        }
                    }
                    if (!$blocked) { usleep(10000); }
                } while (!$blocked && microtime(true) < $deadline);
                self::assertTrue($blocked, 'Recovery completion must reach the account lock held by confirmation.');
                $changes->confirm($actor, $changeToken, null);
                $this->pdo->commit();
                self::assertSame('rejected', trim((string) fgets($pipes[1])), 'Recovery completion must recheck the token after waiting for email confirmation.');
                self::assertTrue((new PasswordHasher())->verify('password123', $this->users()->findEntity($actor->id())->passwordHash()));
            } finally {
                if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
                foreach ($pipes as $pipe) { fclose($pipe); }
                if (proc_get_status($process)['running']) { proc_terminate($process); }
                proc_close($process);
            }
        });
    }
}
