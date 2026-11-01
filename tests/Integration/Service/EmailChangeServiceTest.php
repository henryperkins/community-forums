<?php

declare(strict_types=1);

namespace Tests\Integration\Service;

use App\Core\Config;
use App\Core\App;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\ValidationException;
use App\Mail\ArrayMailer;
use App\Mail\MailException;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\VerificationRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Security\WebAuthn\RelyingParty;
use App\Service\EmailChangeService;
use App\Service\EmailDomainVerifier;
use App\Service\PasskeyService;
use App\Service\OAuth\NormalizedIdentity;
use App\Support\Base64Url;
use Tests\Support\Phase5\WebAuthnHarness;
use Tests\Support\TestCase;

final class EmailChangeServiceTest extends TestCase
{
    private ArrayMailer $mailer;
    private SettingRepository $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mailer = new ArrayMailer();
        $this->settings = new SettingRepository($this->db);
        $this->settings->set('features', ['email_change' => true]);
        $this->config = new Config(array_replace_recursive($this->config->all(), [
            'app' => ['key' => 'email-change-test-key', 'url' => 'https://forum.example.test', 'webauthn_rp_id' => ''],
            'auth' => ['email_change_ttl' => 3600],
        ]));
    }

    private function service(?Mailer $mailer = null, ?Config $config = null, ?EmailDomainVerifier $domain = null, ?PasskeyService $passkeys = null): EmailChangeService
    {
        return new EmailChangeService(
            $this->db, $this->users(), new VerificationRepository($this->db), new SessionRepository($this->db),
            new EmailDeliveryRepository($this->db), new ModerationLogRepository($this->db), $mailer ?? $this->mailer,
            $config ?? $this->config, new ReauthGate(new PasswordHasher()), new WriteGate(),
            new FeatureFlags($this->settings), new OAuthIdentityRepository($this->db), $passkeys, $domain, $this->settings,
        );
    }

    private function token(int $message = -1): string
    {
        $mail = $this->mailer->sent[$message < 0 ? count($this->mailer->sent) - 1 : $message];
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $mail['text'], $match));
        return $match[1];
    }

    private function invalid(callable $action, string $candidate = 'next@example.test'): ValidationException
    {
        try {
            $action();
            self::fail('The email change must reject this request.');
        } catch (ValidationException $error) {
            self::assertSame(['new_email' => $candidate], $error->old);
            return $error;
        }
    }

    public function testPrepareValidatesWithoutIssuingOrChangingContact(): void
    {
        $user = $this->makeUser();
        $service = $this->service();
        self::assertSame('next@example.test', $service->prepare($this->userEntity($user), ' next@example.test '));
        self::assertNull($service->pending($this->userEntity($user)));
        self::assertSame($user['email'], $this->users()->find((int) $user['id'])['email']);
        self::assertSame(0, $this->mailer->count());
        $this->invalid(fn () => $service->prepare($this->userEntity($user), 'invalid'), 'invalid');
        $this->invalid(fn () => $service->prepare($this->userEntity($user), $user['email']), $user['email']);
    }

    public function testIssueStoresOnlyBoundHashAndLeavesOldContactActive(): void
    {
        $user = $this->makeUser();
        $actor = $this->userEntity($user);
        $service = $this->service();
        $service->issue($actor, ' next@example.test ', 'password123', null, 'session-binding');
        $token = $this->token();
        $row = $this->db->fetch('SELECT * FROM verifications WHERE user_id = ? AND type = ?', [$actor->id(), 'email_change']);
        self::assertNotNull($row);
        self::assertNotSame($token, $row['token_hash']);
        self::assertNotSame(hash('sha256', $token), $row['token_hash']);
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $row['token_hash']);
        self::assertSame('next@example.test', $row['new_email']);
        self::assertEqualsWithDelta(time() + 3600, strtotime($row['expires_at'] . ' UTC'), 3);
        self::assertSame($user['email'], $this->users()->find($actor->id())['email']);
        self::assertSame('next@example.test', $this->mailer->sent[0]['to']);
        self::assertStringContainsString('/settings/account/email/confirm?token=', $this->mailer->sent[0]['text']);
        self::assertSame('next@example.test', $service->pending($actor)['new_email']);
        self::assertSame((int) $row['id'], (int) $service->findConfirmation($actor, $token)['id']);
    }

    public function testLookupIsReadOnlyAndOnlyTheOwnerCanFindOrConfirm(): void
    {
        $owner = $this->userEntity($this->makeUser());
        $other = $this->userEntity($this->makeUser());
        $service = $this->service();
        $service->issue($owner, 'next@example.test', 'password123', null, 'binding');
        $token = $this->token();
        self::assertNull($service->findConfirmation($other, $token));
        self::assertNull($service->pending($other));
        self::assertNotNull($service->findConfirmation($owner, $token));
        self::assertNotNull($service->findConfirmation($owner, $token));
        self::assertNull($this->db->fetchValue('SELECT used_at FROM verifications WHERE user_id = ?', [$owner->id()]));
        $this->invalid(fn () => $service->confirm($other, $token, null), '');
        self::assertSame($owner->email(), $this->users()->find($owner->id())['email']);
    }

    public function testConfirmationAtomicallySwitchesContactRetiresLinksRevokesOtherSessionsAndQueuesPrivateNotice(): void
    {
        $actor = $this->userEntity($this->makeUser(['email' => 'old@example.test']));
        $other = $this->makeUser();
        $sessions = new SessionRepository($this->db);
        foreach ([['current', $actor->id()], ['other', $actor->id()], ['foreign', (int) $other['id']]] as [$id, $userId]) {
            $sessions->create(['id' => hash('sha256', $id), 'user_id' => $userId, 'csrf_secret' => 'secret', 'user_agent' => null, 'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600)]);
        }
        $verifications = new VerificationRepository($this->db);
        foreach (['email_verify', 'password_reset'] as $type) {
            $verifications->create($actor->id(), $type, hash('sha256', $type), '2099-01-01 00:00:00');
        }
        $deliveries = new EmailDeliveryRepository($this->db);
        $ordinary = $deliveries->enqueue($actor->id(), $actor->email(), 'system', 'announcement', null, ['type' => 'announcement', 'message' => 'Private']);
        $instant = $deliveries->enqueue($actor->id(), $actor->email(), 'instant', null);
        $digest = $deliveries->enqueue($actor->id(), $actor->email(), 'digest', null);
        $priorSecurity = $deliveries->enqueue($actor->id(), $actor->email(), 'system', null, 'prior-security', ['type' => 'email_changed']);
        $test = $deliveries->enqueue($actor->id(), $actor->email(), 'test', null);
        $service = $this->service();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $token = $this->token();
        $verification = $service->findConfirmation($actor, $token);
        self::assertSame('next@example.test', $service->confirm($actor, $token, hash('sha256', 'current')));
        $updated = $this->users()->find($actor->id());
        self::assertSame('next@example.test', $updated['email']);
        self::assertNotNull($updated['email_verified_at']);
        self::assertNotNull($sessions->findActive(hash('sha256', 'current')));
        self::assertNull($sessions->findActive(hash('sha256', 'other')));
        self::assertNotNull($sessions->findActive(hash('sha256', 'foreign')));
        self::assertNull($service->pending($actor));
        self::assertNull($verifications->findValid(hash('sha256', 'email_verify'), 'email_verify'));
        self::assertNull($verifications->findValid(hash('sha256', 'password_reset'), 'password_reset'));
        self::assertSame('suppressed', $deliveries->find($ordinary)['status']);
        self::assertSame('suppressed', $deliveries->find($instant)['status']);
        self::assertSame('suppressed', $deliveries->find($digest)['status']);
        self::assertSame('queued', $deliveries->find($priorSecurity)['status']);
        self::assertSame('queued', $deliveries->find($test)['status']);
        $notice = $this->db->fetch('SELECT * FROM email_deliveries WHERE idempotency_key = ?', ['email-change:' . $verification['id']]);
        self::assertSame('old@example.test', $notice['email']);
        self::assertSame('email_changed', json_decode($notice['payload'], true)['type']);
        self::assertStringContainsString((string) $verification['id'], $notice['idempotency_key']);
        self::assertStringNotContainsString('next@example.test', $notice['payload']);
        self::assertStringNotContainsString($token, $notice['payload']);
        $audit = (new ModerationLogRepository($this->db))->recentForTarget('user', $actor->id());
        self::assertCount(1, $audit);
        self::assertSame('email_changed', $audit[0]['action']);
        $auditJson = json_encode($audit);
        self::assertStringNotContainsString('old@example.test', $auditJson);
        self::assertStringNotContainsString('next@example.test', $auditJson);
        self::assertStringNotContainsString($token, $auditJson);
        self::assertStringNotContainsString('password123', $auditJson);
        $this->invalid(fn () => $service->confirm($actor, $token, null), '');
    }

    public function testConfirmationNeverKeepsAnExpiredOrForeignSession(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $sessions = new SessionRepository($this->db);
        $id = hash('sha256', 'other-active');
        $sessions->create(['id' => $id, 'user_id' => $actor->id(), 'csrf_secret' => 'secret', 'user_agent' => null, 'expires_at' => '2099-01-01 00:00:00']);
        $service = $this->service();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $service->confirm($actor, $this->token(), hash('sha256', 'missing-session'));
        self::assertNull($sessions->findActive($id));
    }

    public function testWrongOrMissingPasswordCannotIssueAndNeverReturnsFactorsAsOldInput(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->service();
        foreach ([null, 'wrong-password'] as $password) {
            $error = $this->invalid(fn () => $service->issue($actor, 'next@example.test', $password, 'private-assertion', 'binding'));
            self::assertArrayHasKey('current_password', $error->errors);
            self::assertNull($service->pending($actor));
        }
        self::assertSame(0, $this->mailer->count());
    }

    public function testStaleActorPasswordCannotReauthenticateAfterPasswordChanges(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $this->users()->updatePassword($actor->id(), (new PasswordHasher())->hash('changed-password'));
        $service = $this->service();
        $this->invalid(fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        self::assertSame(0, $this->mailer->count());
        $service->issue($actor, 'next@example.test', 'changed-password', null, 'binding');
        self::assertNotNull($service->findConfirmation($actor, $this->token()));
    }

    public function testOAuthOnlyActorMustReauthenticateAnExactlyLinkedIdentityWithoutIdentityWrites(): void
    {
        $user = $this->makeUser();
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$user['id']]);
        $actor = $this->users()->findEntity((int) $user['id']);
        $identities = new OAuthIdentityRepository($this->db);
        $identities->create(['user_id' => $actor->id(), 'provider' => 'github', 'provider_user_id' => 'linked-subject', 'email' => 'private@provider.test']);
        $before = $identities->forUser($actor->id());
        $service = $this->service();
        $this->invalid(fn () => $service->issue($actor, 'next@example.test', null, null, 'binding'));
        $identity = new NormalizedIdentity('github', 'linked-subject', 'different@provider.test', false, null, null);
        $service->issue($actor, 'next@example.test', null, null, 'binding', $identity);
        self::assertNotNull($service->findConfirmation($actor, $this->token()));
        self::assertSame($before, $identities->forUser($actor->id()));
    }

    public function testPasskeyRequiresFreshOwnerAndSessionBoundAssertionAndCannotReplay(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $rp = new RelyingParty((string) $this->config->get('app.url'), null, 'testing');
        $harness = new WebAuthnHarness($rp->rpId(), $rp->origin());
        $credential = $harness->createCredential();
        (new WebAuthnCredentialRepository($this->db))->create([
            'user_id' => $actor->id(), 'credential_id' => $credential['credentialId'], 'public_key' => $credential['coseKey'],
            'sign_count' => 0, 'aaguid' => null, 'transports' => 'internal', 'is_discoverable' => 0,
            'is_backup_eligible' => 0, 'is_backed_up' => 0, 'nickname' => null,
        ]);
        $this->db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$actor->id()]);
        $app = new App($this->config, $this->db, $this->rateLimiter);
        $container = (new \ReflectionMethod($app, 'buildContainer'))->invoke($app, new Request('GET', '/'));
        $container->instance(Mailer::class, $this->mailer);
        $passkeys = $container->get(PasskeyService::class);
        $fresh = $this->users()->findEntity($actor->id());
        $options = $passkeys->beginStepUp($fresh, 'correct-session');
        $assertion = $harness->assertionPayload($credential, (string) Base64Url::decode($options['challenge']), 1);
        $service = $this->service(passkeys: $passkeys);
        $this->invalid(fn () => $service->issue($actor, 'next@example.test', null, $assertion, 'other-session'));
        self::assertNull($service->pending($actor));
        $service->issue($actor, 'next@example.test', null, $assertion, 'correct-session');
        $token = $this->token();
        self::assertNotNull($service->findConfirmation($actor, $token));
        $this->invalid(fn () => $service->issue($actor, 'replacement@example.test', null, $assertion, 'correct-session'), 'replacement@example.test');
        self::assertNotNull($service->findConfirmation($actor, $token));
        self::assertSame(1, $this->mailer->count());
    }

    public function testOAuthReauthenticationRejectsAnotherOwnerSubjectProviderOrRegistryConfiguration(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $other = $this->makeUser();
        $repo = new OAuthIdentityRepository($this->db);
        $repo->create(['user_id' => $actor->id(), 'provider' => 'github', 'provider_user_id' => 'owner']);
        $repo->create(['user_id' => (int) $other['id'], 'provider' => 'github', 'provider_user_id' => 'other']);
        $before = $this->db->fetchAll('SELECT * FROM oauth_identities ORDER BY id');
        $service = $this->service();
        foreach ([['github', 'other', null], ['github', 'unlinked', null], ['google', 'owner', null], ['github', 'owner', 99]] as [$provider, $subject, $configId]) {
            $identity = new NormalizedIdentity($provider, $subject, $actor->email(), true, null, null, $configId);
            $this->invalid(fn () => $service->issue($actor, 'next@example.test', null, null, 'binding', $identity));
        }
        self::assertSame(0, $this->mailer->count());
        self::assertSame($before, $this->db->fetchAll('SELECT * FROM oauth_identities ORDER BY id'));
    }

    public function testReplacementAndCancellationRetireEarlierLinks(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->service();
        $service->issue($actor, 'first@example.test', 'password123', null, 'binding');
        $first = $this->token();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $second = $this->token();
        self::assertNull($service->findConfirmation($actor, $first));
        self::assertNotNull($service->findConfirmation($actor, $second));
        $service->cancel($actor);
        self::assertNull($service->pending($actor));
        self::assertNull($service->findConfirmation($actor, $second));
        self::assertSame($actor->email(), $this->users()->find($actor->id())['email']);
    }

    public function testExpiryAndChangedCredentialsOrContactInvalidateIssuedTokens(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->service();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $expired = $this->token();
        $this->db->run('UPDATE verifications SET expires_at = UTC_TIMESTAMP() WHERE user_id = ?', [$actor->id()]);
        self::assertNull($service->findConfirmation($actor, $expired));
        $this->invalid(fn () => $service->confirm($actor, $expired, null), '');
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $passwordBound = $this->token();
        $this->users()->updatePassword($actor->id(), (new PasswordHasher())->hash('changed-password'));
        self::assertNull($service->findConfirmation($actor, $passwordBound));
        $this->invalid(fn () => $service->confirm($actor, $passwordBound, null), '');
        $service->issue($actor, 'next@example.test', 'changed-password', null, 'binding');
        $addressBound = $this->token();
        $this->db->run('UPDATE users SET email = ? WHERE id = ?', ['changed@example.test', $actor->id()]);
        self::assertNull($service->findConfirmation($actor, $addressBound));
        foreach (['', 'bad-token', str_repeat('a', 63), str_repeat('a', 65)] as $malformed) {
            self::assertNull($service->findConfirmation($actor, $malformed));
        }
    }

    public function testCollisionAtIssueOrConfirmationUsesGenericUnavailableErrorAndPreservesOldContact(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $service = $this->service();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $token = $this->token();
        $this->makeUser(['email' => 'next@example.test', 'username' => 'secret_other_owner']);
        $issueError = $this->invalid(fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        $confirmError = $this->invalid(fn () => $service->confirm($actor, $token, null));
        self::assertSame($issueError->errors, $confirmError->errors);
        self::assertStringNotContainsString('secret_other_owner', $confirmError->first());
        self::assertSame($actor->email(), $this->users()->find($actor->id())['email']);
        self::assertNotNull($service->findConfirmation($actor, $token));
    }

    public function testUniqueIndexRaceRollsBackConsumedTokenAndEveryTransitionEffectAtARealCommitBoundary(): void
    {
        // Leave no harness writes to be committed. This test owns every fixture
        // it commits and inspects the result from a separate DB connection.
        $this->pdo->rollBack();
        $this->pdo->beginTransaction();
        $this->withoutHarnessTransaction(function (): void {
            $hadFeatures = $this->settings->has('features');
            $originalFeatures = $this->settings->get('features');
            $this->settings->set('features', ['email_change' => true]);
            $actor = $this->userEntity($this->makeUser());
            $trigger = 'rb_email_change_race_' . $actor->id();
            try {
                $service = $this->service();
                $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
                $token = $this->token();
                // Insert a competing unique address only after validation, while
                // the verification is consumed. The later user UPDATE must hit
                // the real unique index, and its entire transaction must unwind.
                $this->pdo->exec("CREATE TRIGGER {$trigger} BEFORE UPDATE ON verifications FOR EACH ROW
                    BEGIN
                        IF OLD.user_id = {$actor->id()} AND OLD.type = 'email_change' AND OLD.used_at IS NULL AND NEW.used_at IS NOT NULL THEN
                            INSERT INTO users (username, email, password_hash, display_name, role, status, created_at)
                            VALUES ('emailrace{$actor->id()}', 'next@example.test', NULL, NULL, 'user', 'active', UTC_TIMESTAMP());
                        END IF;
                    END");
                $this->invalid(fn () => $service->confirm($actor, $token, null));
                $observer = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
                self::assertSame($actor->email(), $observer->fetchValue('SELECT email FROM users WHERE id = ?', [$actor->id()]));
                self::assertNull($observer->fetchValue('SELECT used_at FROM verifications WHERE user_id = ?', [$actor->id()]));
                self::assertSame(0, (int) $observer->fetchValue('SELECT COUNT(*) FROM email_deliveries WHERE user_id = ?', [$actor->id()]));
                self::assertSame(0, (int) $observer->fetchValue("SELECT COUNT(*) FROM moderation_log WHERE action = 'email_changed' AND actor_id = ?", [$actor->id()]));
                self::assertNotNull($service->findConfirmation($actor, $token));
                $this->pdo->exec('DROP TRIGGER ' . $trigger);
                self::assertSame('next@example.test', $service->confirm($actor, $token, null));
            } finally {
                $this->pdo->exec('DROP TRIGGER IF EXISTS ' . $trigger);
                $this->db->run('DELETE FROM moderation_log WHERE actor_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM email_deliveries WHERE user_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM verifications WHERE user_id = ?', [$actor->id()]);
                $this->db->run('DELETE FROM users WHERE id = ?', [$actor->id()]);
                if ($hadFeatures) {
                    $this->settings->set('features', $originalFeatures);
                } else {
                    $this->db->run("DELETE FROM settings WHERE `key` = 'features'");
                }
            }
        });
    }

    public function testUnconfiguredOrBlockedMailerRejectsPreparationAndDoesNotPretendToSend(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $unconfigured = new class implements Mailer {
            public function isConfigured(): bool { return false; }
            public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string { throw new \LogicException('must not send'); }
        };
        $error = $this->invalid(fn () => $this->service($unconfigured)->prepare($actor, 'next@example.test'));
        self::assertStringContainsString('email', strtolower($error->first()));
        $this->invalid(fn () => $this->service($unconfigured)->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        $this->settings->set('email_require_verified_domain', true);
        $domain = new EmailDomainVerifier($this->config, $this->settings, new EmailDomainStatusRepository($this->db));
        $this->invalid(fn () => $this->service(domain: $domain)->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        self::assertSame(0, $this->mailer->count());
        self::assertNull($this->service()->pending($actor));
    }

    public function testSendFailureRetiresOnlyTheFailedIssueAndPreservesNewerReplacement(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $mailer = new class($this->mailer) implements Mailer {
            public ?\Closure $duringSend = null;
            public function __construct(private ArrayMailer $capture) {}
            public function isConfigured(): bool { return true; }
            public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string {
                if ($this->duringSend !== null) {
                    $callback = $this->duringSend;
                    $this->duringSend = null;
                    $callback();
                    throw new MailException('failure containing sensitive provider details');
                }
                return $this->capture->send($to, $subject, $textBody, $htmlBody);
            }
        };
        $service = $this->service($mailer);
        $mailer->duringSend = fn () => $service->issue($actor, 'newer@example.test', 'password123', null, 'binding');
        $error = $this->invalid(fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        self::assertStringNotContainsString('sensitive provider details', $error->first());
        self::assertSame('newer@example.test', $service->pending($actor)['new_email']);
        self::assertNotNull($service->findConfirmation($actor, $this->token()));
        self::assertSame($actor->email(), $this->users()->find($actor->id())['email']);
    }

    public function testTransportFailureLeavesNoUsableUnseenLink(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $this->mailer->failNext = true;
        $service = $this->service();
        $this->invalid(fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'));
        self::assertNull($service->pending($actor));
        self::assertSame($actor->email(), $this->users()->find($actor->id())['email']);
    }

    public function testStaleRestrictedActorCannotIssueCancelOrConfirm(): void
    {
        $actor = $this->userEntity($this->makeAdmin());
        $service = $this->service();
        $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
        $token = $this->token();
        $this->users()->setStatus($actor->id(), 'banned');
        foreach ([fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'), fn () => $service->cancel($actor), fn () => $service->confirm($actor, $token, null)] as $action) {
            try { $action(); self::fail('Fresh account state must prohibit every mutation.'); }
            catch (ForbiddenException $error) { self::assertSame(403, $error->statusCode()); }
        }
        self::assertSame($actor->email(), $this->users()->find($actor->id())['email']);
        self::assertSame(1, $this->mailer->count());
    }

    public function testDisabledFeatureRejectsServiceMutationsAndReads(): void
    {
        $actor = $this->userEntity($this->makeUser());
        $this->settings->set('features', ['email_change' => false]);
        $service = $this->service();
        foreach ([fn () => $service->prepare($actor, 'next@example.test'), fn () => $service->issue($actor, 'next@example.test', 'password123', null, 'binding'), fn () => $service->pending($actor), fn () => $service->findConfirmation($actor, str_repeat('a', 64)), fn () => $service->confirm($actor, str_repeat('a', 64), null), fn () => $service->cancel($actor)] as $action) {
            try { $action(); self::fail('Email changes must remain unavailable when disabled.'); }
            catch (NotFoundException $error) { self::assertSame(404, $error->statusCode()); }
        }
        self::assertSame(0, $this->mailer->count());
    }

    public function testTtlIsClampedAtBothSecurityBoundaries(): void
    {
        $actor = $this->userEntity($this->makeUser());
        foreach ([-1 => 300, 999999 => 86400] as $configured => $seconds) {
            $config = new Config(array_replace_recursive($this->config->all(), ['auth' => ['email_change_ttl' => $configured]]));
            $service = $this->service(config: $config);
            $service->issue($actor, 'next@example.test', 'password123', null, 'binding');
            self::assertEqualsWithDelta(time() + $seconds, strtotime($service->pending($actor)['expires_at'] . ' UTC'), 3);
        }
    }
}
