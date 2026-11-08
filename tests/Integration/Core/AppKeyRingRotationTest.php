<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Controller\UnsubscribeController;
use App\Core\App;
use App\Core\Config;
use App\Core\FeatureFlags;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\DigestActivityRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserPreferenceRepository;
use App\Security\AppKey;
use App\Security\AppKeyRing;
use App\Security\SecretBox;
use App\Security\Session;
use App\Security\Totp;
use App\Security\WebAuthn\RelyingParty;
use App\Service\DigestService;
use App\Service\EmailChangeService;
use App\Service\EmailPreferenceService;
use App\Service\NotificationVisibilityService;
use App\Service\OAuth\HttpClient;
use App\Service\SecretVault;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Support\Base64Url;
use App\Worker\DailyDigestWorker;
use App\Worker\NotificationEmailWorker;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Phase5\WebAuthnHarness;
use Tests\Support\ScriptedOAuthHttpClient;
use Tests\Support\TestCase;

/**
 * APP_KEY rotation through the key ring (ADR 0059), end to end through the kernel.
 *
 * Stages, all with dummy keys:
 * - S0: K1 is the only key.
 * - S1a: K1 still active; K2 staged as a previous key that may verify, so every
 *   runtime reads K2 before any runtime writes with it.
 * - S1 (planned): K2 active; K1 a previous key that may still verify.
 * - S1 (compromise): K2 active; K1 decrypt-only, so nothing signed with it verifies.
 * - S3: K1 retired from the ring.
 */
final class AppKeyRingRotationTest extends TestCase
{
    private const AUTHENTICATOR_UNAVAILABLE = 'Authenticator codes cannot be checked for this account right now.';
    private const CANONICAL = 'abababababababababababababababababababababababababababababababab';

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->useRing(self::k1());
    }

    public function test_migration_0087_adds_nullable_key_id_columns_and_reapplies_as_a_no_op(): void
    {
        foreach ([
            ['user_totp_credentials', 'secret_key_id'],
            ['service_secret_versions', 'key_id'],
            ['user_recovery_codes', 'hmac_key_id'],
            ['verifications', 'hmac_key_id'],
            ['email_deliveries', 'unsubscribe_key_id'],
        ] as [$table, $column]) {
            $row = $this->db->fetch(
                'SELECT COLUMN_TYPE AS column_type, IS_NULLABLE AS is_nullable FROM information_schema.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [$table, $column],
            );
            self::assertNotNull($row, "missing {$table}.{$column}");
            self::assertSame('char(16)', strtolower((string) $row['column_type']));
            self::assertSame('YES', $row['is_nullable'], 'NULL means written before the key ring: key unknown');
        }
        self::assertSame(['unsubscribe_key_id', 'sent_at'], array_column($this->db->fetchAll(
            "SELECT COLUMN_NAME FROM information_schema.STATISTICS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_deliveries' AND INDEX_NAME = 'idx_deliv_unsub_key'
              ORDER BY SEQ_IN_INDEX",
        ), 'COLUMN_NAME'));

        // Every ALTER is information_schema-guarded, so a second up() issues no DDL.
        // DDL would implicitly commit this test's transaction, publishing the
        // setUp() admin to other connections.
        $admins = (int) $this->db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        (require dirname(__DIR__, 3) . '/database/migrations/0087_app_key_attribution.php')->up($this->pdo);
        self::assertTrue($this->pdo->inTransaction());
        self::assertSame($admins - 1, (int) (new Database($GLOBALS['__RB_TEST_DBCONFIG']))->fetchValue(
            "SELECT COUNT(*) FROM users WHERE role = 'admin'",
        ), 'the setUp() admin is still uncommitted');
    }

    public function test_planned_rotation_keeps_every_record_working_until_k1_is_retired(): void
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => true, 'email_change' => true]);
        $k1 = self::key(self::k1());
        $k2 = self::key(self::k2());

        // S0. One member's records predate migration 0087 (no key ids), one
        // member has an unused legacy batch, and one member's records carry K1's ids.
        $legacy = $this->makeUser();
        [$legacySecret, $legacyCodes] = $this->enroll($legacy);
        $this->forgetAttribution($legacy);
        $unproven = $this->makeUser();
        $this->enroll($unproven);
        $this->forgetAttribution($unproven);
        $member = $this->makeUser();
        [$memberSecret, $memberCodes] = $this->enroll($member);
        self::assertSame($k1->encryptionId, $this->totpKeyId($member));
        self::assertSame([$k1->hmacId], $this->recoveryKeyIds($member));

        $ref = $this->vault()->store('identity_provider', null, 'rotation fixture', 'client-secret-v1');
        self::assertSame([$k1->encryptionId], $this->vaultKeyIds($ref));
        $this->db->run(
            'UPDATE service_secret_versions v JOIN service_secrets s ON s.id = v.secret_id SET v.key_id = NULL WHERE s.secret_ref = ?',
            [$ref],
        );

        $changer = $this->makeUser();
        $this->actingAs($changer);
        $changeToken = $this->issueEmailChange($changer, 'changed-' . bin2hex(random_bytes(4)) . '@example.test');
        self::assertSame($k1->hmacId, $this->db->fetchValue(
            "SELECT hmac_key_id FROM verifications WHERE user_id = ? AND type = 'email_change'",
            [(int) $changer['id']],
        ));
        $k1Link = $this->unsubscribeQuery((string) $member['email'], self::k1());

        // S1a: K2 verifies and decrypts everywhere, but nothing is written with it yet.
        $this->useRing(self::k1(), [1 => self::k2()], $k2->hmacId);
        $staged = $this->makeUser();
        $this->enroll($staged);
        self::assertSame($k1->encryptionId, $this->totpKeyId($staged), 'S1a still encrypts with K1');
        self::assertSame([$k1->hmacId], $this->recoveryKeyIds($staged), 'S1a still keys recovery codes with K1');
        $earlyK2 = (new SecretBox(self::k2()))->encrypt('written by a runtime already on S1');
        self::assertSame('written by a runtime already on S1', $this->box()->decrypt(
            $earlyK2['ciphertext'], $earlyK2['nonce'], $earlyK2['tag'], $earlyK2['key_id'],
        ));
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $this->unsubscribeQuery((string) $member['email'], self::k2())));

        // Planned S1: K2 writes; K1 still decrypts and verifies.
        $this->useRing(self::k2(), [1 => self::k1()], $k1->hmacId);
        $this->assertRedirect($this->signInWithAuthenticator($legacy, $legacySecret), '/inbox');
        $this->assertRedirect($this->signInWithAuthenticator($member, $memberSecret), '/inbox');
        $this->assertRedirect($this->signInWithCode($legacy, $legacyCodes[0]), '/inbox');
        self::assertSame([$k1->hmacId], $this->recoveryKeyIds($legacy), 'a verified legacy code proves the key of its whole batch');
        self::assertSame([null], $this->recoveryKeyIds($unproven), 'an unused legacy batch keeps unknown attribution');
        $this->assertRedirect($this->signInWithCode($member, $memberCodes[0]), '/inbox');

        self::assertSame('client-secret-v1', $this->vault()->reveal($ref));
        $this->vault()->rotate($ref, 'client-secret-v2');
        self::assertSame(['client-secret-v2', 'client-secret-v1'], $this->vault()->usableSecrets($ref));
        self::assertSame([$k2->encryptionId, null], $this->vaultKeyIds($ref));

        $this->actingAs($changer);
        $this->assertStatus(200, $this->get('/settings/account/email/confirm', ['token' => $changeToken]));
        $this->assertRedirect($this->post('/settings/account/email/confirm', ['token' => $changeToken]), '/settings/account');
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $k1Link));

        // Move the member onto K2: re-encrypt the unchanged secret (as key:reencrypt
        // will) and regenerate recovery codes. Let the old vault version's grace end.
        $this->rewrapTotpSecret($member, $memberSecret, self::k2());
        self::assertSame($k2->encryptionId, $this->totpKeyId($member));
        $this->actingAs($member);
        $rotated = $this->post('/settings/security/totp/recovery/rotate', ['current_password' => 'password123']);
        $this->assertStatus(200, $rotated);
        $memberK2Codes = $this->recoveryCodesIn($rotated);
        self::assertCount(10, $memberK2Codes);
        self::assertSame([$k2->hmacId], $this->recoveryKeyIds($member));
        $this->db->run(
            "UPDATE service_secret_versions v JOIN service_secrets s ON s.id = v.secret_id
                SET v.retire_after = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND)
              WHERE s.secret_ref = ? AND v.state = 'retired'",
            [$ref],
        );

        // S3: K1 retired. Everything moved to K2 keeps working.
        $this->useRing(self::k2());
        $this->assertRedirect($this->signInWithAuthenticator($member, $memberSecret), '/inbox');
        $this->assertRedirect($this->signInWithCode($member, $memberK2Codes[0]), '/inbox');
        self::assertSame(['client-secret-v2'], $this->vault()->usableSecrets($ref));

        // What still needs K1 now fails closed through the normal denial paths.
        $refused = $this->signInWithAuthenticator($legacy, $legacySecret);
        $this->assertStatus(422, $refused);
        $this->assertSeeText($refused, self::AUTHENTICATOR_UNAVAILABLE);
        $this->assertStatus(422, $this->signInWithCode($legacy, $legacyCodes[1]));
        $this->logoutClient();
        $this->assertStatus(404, $this->get('/unsubscribe', $k1Link));
    }

    public function test_s1a_and_s1_runtimes_running_side_by_side_accept_each_others_writes(): void
    {
        (new SettingRepository($this->db))->set('features', ['email_change' => true]);
        $k1 = self::key(self::k1());
        $k2 = self::key(self::k2());
        $s1a = fn () => $this->useRing(self::k1(), [1 => self::k2()], $k2->hmacId);
        $s1 = fn () => $this->useRing(self::k2(), [1 => self::k1()], $k1->hmacId);

        $s1a();
        $early = $this->makeUser();
        [$earlySecret, $earlyCodes] = $this->enroll($early);
        self::assertSame($k1->encryptionId, $this->totpKeyId($early));
        $earlyLink = $this->unsubscribeQuery((string) $early['email'], self::k1());

        $s1();
        $late = $this->makeUser();
        [$lateSecret, $lateCodes] = $this->enroll($late);
        self::assertSame($k2->encryptionId, $this->totpKeyId($late));
        self::assertSame([$k2->hmacId], $this->recoveryKeyIds($late));
        $lateLink = $this->unsubscribeQuery((string) $late['email'], self::k2());
        $this->actingAs($late);
        $lateChange = $this->issueEmailChange($late, 'late-' . bin2hex(random_bytes(4)) . '@example.test');

        foreach ([$s1a, $s1] as $runtime) {
            $runtime();
            $this->assertRedirect($this->signInWithAuthenticator($early, $earlySecret), '/inbox');
            $this->assertRedirect($this->signInWithAuthenticator($late, $lateSecret), '/inbox');
            $this->logoutClient();
            $this->assertStatus(200, $this->get('/unsubscribe', $earlyLink));
            $this->assertStatus(200, $this->get('/unsubscribe', $lateLink));
        }

        $s1a();
        $this->assertRedirect($this->signInWithCode($late, $lateCodes[0]), '/inbox');
        $this->actingAs($late);
        $this->assertRedirect($this->post('/settings/account/email/confirm', ['token' => $lateChange]), '/settings/account');
        $s1();
        $this->assertRedirect($this->signInWithCode($early, $earlyCodes[0]), '/inbox');
    }

    public function test_pre_keyring_code_reads_these_writes_only_while_the_key_is_unchanged(): void
    {
        // Rolling this release out is itself a mixed-version window: older
        // instances read with APP_KEY alone and ignore the key id columns.
        $member = $this->makeUser();
        [$secret, $codes] = $this->enroll($member);
        $row = $this->db->fetch(
            'SELECT secret_ciphertext, secret_nonce, secret_tag FROM user_totp_credentials WHERE user_id = ?',
            [(int) $member['id']],
        );
        self::assertSame($secret, openssl_decrypt(
            (string) $row['secret_ciphertext'], 'aes-256-gcm', self::preKeyringEncryptionKey(self::k1()),
            OPENSSL_RAW_DATA, (string) $row['secret_nonce'], (string) $row['secret_tag'],
        ), 'pre-keyring code opens a secret written by the key ring under the same APP_KEY');
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM user_recovery_codes WHERE user_id = ? AND code_hash = ?',
            [(int) $member['id'], hash_hmac('sha256', str_replace('-', '', $codes[0]), self::k1())],
        ), 'recovery code hashes keep the pre-keyring HMAC derivation');

        // Pre-keyring enrollment restarts rewrite the ciphertext and leave
        // secret_key_id as it was. Under the same key the stale id is still right.
        $restarted = (new Totp())->generateSecret();
        $this->preKeyringSavePendingTotp($member, $restarted, self::k1());
        self::assertSame(self::key(self::k1())->encryptionId, $this->totpKeyId($member));
        $this->db->run('UPDATE user_totp_credentials SET enabled_at = UTC_TIMESTAMP(), verified_at = UTC_TIMESTAMP() WHERE user_id = ?', [(int) $member['id']]);
        $this->assertRedirect($this->signInWithAuthenticator($member, $restarted), '/inbox');

        // Under a different APP_KEY the same pre-keyring write leaves an id that
        // names the wrong key, so the key ring refuses the secret. This is why
        // pre-keyring code must not run after K2 is in use (the rollback floor).
        $this->preKeyringSavePendingTotp($member, $restarted, self::k2());
        $this->db->run('UPDATE user_totp_credentials SET enabled_at = UTC_TIMESTAMP(), verified_at = UTC_TIMESTAMP() WHERE user_id = ?', [(int) $member['id']]);
        $this->useRing(self::k2(), [1 => self::k1()], self::key(self::k1())->hmacId);
        $refused = $this->signInWithAuthenticator($member, $restarted);
        $this->assertStatus(422, $refused);
        $this->assertSeeText($refused, self::AUTHENTICATOR_UNAVAILABLE);
    }

    public function test_a_compromised_key_still_decrypts_but_never_verifies(): void
    {
        (new SettingRepository($this->db))->set('features', ['service_secrets' => true]);
        $planned = str_repeat('3c', 32);

        $exposed = $this->makeUser();
        [$exposedSecret, $exposedCodes] = $this->enroll($exposed);
        $ref = $this->vault()->store('identity_provider', null, 'exposed fixture', 'exposed-plaintext');
        $exposedLink = $this->unsubscribeQuery((string) $exposed['email'], self::k1());

        $this->useRing($planned);
        $overlap = $this->makeUser();
        [, $overlapCodes] = $this->enroll($overlap);
        $overlapLink = $this->unsubscribeQuery((string) $overlap['email'], $planned);

        // K1 is decrypt-only. Mistakes in the verify list (the raw key pasted in,
        // an id no configured key has) are ignored: they can withhold, never grant.
        $this->useRing(self::k2(), [1 => $planned, 2 => self::k1()], implode(',', [
            self::key($planned)->hmacId, self::k1(), 'ffffffffffffffff',
        ]));

        self::assertSame('exposed-plaintext', $this->vault()->reveal($ref));
        $this->assertRedirect($this->signInWithAuthenticator($exposed, $exposedSecret), '/inbox');
        $this->assertStatus(422, $this->signInWithCode($exposed, $exposedCodes[0]));
        $this->assertRedirect($this->signInWithCode($overlap, $overlapCodes[0]), '/inbox');
        $this->logoutClient();
        $this->assertStatus(404, $this->get('/unsubscribe', $exposedLink));
        $this->assertStatus(200, $this->get('/unsubscribe', $overlapLink));
    }

    public function test_a_previous_key_link_opts_out_but_only_the_active_key_or_the_member_resubscribes(): void
    {
        $member = $this->makeUser();
        $email = (string) $member['email'];
        $k1Link = $this->unsubscribeQuery($email, self::k1());
        $k2Link = $this->unsubscribeQuery($email, self::k2());
        $this->useRing(self::k2(), [1 => self::k1()], self::key(self::k1())->hmacId);
        $suppressions = new EmailSuppressionRepository($this->db);

        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $k1Link));
        $done = $this->post('/unsubscribe', $k1Link);
        $this->assertStatus(200, $done);
        self::assertSame('unsubscribe', $suppressions->reasonFor($email));
        $this->assertDontSeeText($done, 'action="/resubscribe"');
        $this->assertSeeText($done, '<a href="/settings/notifications">Sign in</a> to turn notification emails back on.');
        $this->assertStatus(404, $this->post('/resubscribe', $k1Link));
        self::assertTrue($suppressions->isSuppressed($email));
        $this->assertRedirectContains($this->post('/settings/notifications/email/resubscribe'), '/login');
        self::assertTrue($suppressions->isSuppressed($email));

        $this->actingAs($member);
        $settings = $this->get('/settings/notifications');
        $this->assertSeeText($settings, 'Notification emails are off');
        $this->assertSeeText($settings, 'action="/settings/notifications/email/resubscribe"');
        $this->assertRedirect($this->post('/settings/notifications/email/resubscribe'), '/settings/notifications');
        self::assertFalse($suppressions->isSuppressed($email));
        $this->assertDontSeeText($this->get('/settings/notifications'), 'Notification emails are off');

        // Bounces and complaints are the operator's to clear, not the member's.
        $suppressions->suppress($email, 'bounce');
        $this->assertDontSeeText($this->get('/settings/notifications'), 'Notification emails are off');
        $this->post('/settings/notifications/email/resubscribe');
        self::assertSame('bounce', $suppressions->reasonFor($email));
        $suppressions->unsuppress($email);

        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $k2Link));
        $activeDone = $this->post('/unsubscribe', $k2Link);
        $this->assertSeeText($activeDone, 'action="/resubscribe"');
        $this->assertStatus(200, $this->post('/resubscribe', $k2Link));
        self::assertFalse($suppressions->isSuppressed($email));
    }

    public function test_an_oauth_state_cookie_signed_with_k1_completes_only_while_k1_may_verify(): void
    {
        $http = new ScriptedOAuthHttpClient();
        $this->useRing(self::k1(), [], '', $http);
        [$state, $nonce] = $this->startGoogleSignIn();
        $k1Cookies = $this->cookies;

        // Planned S1: a sign-in that straddles the switch still completes.
        $this->useRing(self::k2(), [1 => self::k1()], self::key(self::k1())->hmacId, $http);
        $http->replace('https://oauth2.googleapis.com/token', ['id_token' => self::googleIdToken('rotation-sub', 'oauth-rotation@example.test', $nonce)]);
        $this->assertRedirect($this->get('/auth/google/callback', ['code' => 'code-1', 'state' => $state]), '/inbox');
        self::assertNotNull($this->users()->findByEmail('oauth-rotation@example.test'));

        // Compromise S1: K1 is decrypt-only, so the same cookie is refused before
        // any token exchange.
        $this->logoutClient();
        $this->cookies = $k1Cookies;
        $this->useRing(self::k2(), [1 => self::k1()], '', $http);
        $exchanges = count($http->calls);
        $this->assertRedirect($this->get('/auth/google/callback', ['code' => 'code-1', 'state' => $state]), '/login');
        self::assertSame($exchanges, count($http->calls), 'an unverifiable state cookie never reaches the token endpoint');
        $this->assertSeeText($this->get('/login'), 'That sign-in attempt expired or was invalid. Please try again.');
    }

    public function test_messages_record_the_key_that_signed_their_unsubscribe_link_when_actually_sent(): void
    {
        $k1 = self::key(self::k1());
        $k2 = self::key(self::k2());
        $recipient = $this->makeUser();
        $deliveries = new EmailDeliveryRepository($this->db);
        $notice = ['type' => 'announcement', 'message' => 'Rotation rehearsal notice.'];
        $sentUnderK1 = $deliveries->enqueue((int) $recipient['id'], (string) $recipient['email'], 'system', 'Sent under K1', 'rotation-a-' . bin2hex(random_bytes(4)), $notice);
        $retried = $deliveries->enqueue((int) $recipient['id'], (string) $recipient['email'], 'system', 'Retried after the switch', 'rotation-b-' . bin2hex(random_bytes(4)), $notice);

        // S0: one message is sent; the other's first attempt fails in the transport.
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 HOUR) WHERE id = ?', [$retried]);
        $k1Mail = new ArrayMailer();
        self::assertSame(1, $this->emailWorker($k1Mail)->run(10)['sent']);
        self::assertSame($k1->hmacId, $this->unsubscribeKeyId($sentUnderK1));
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$retried]);
        self::assertSame(1, $this->emailWorker($this->failingMailer())->run(10)['retrying']);
        self::assertNull($this->unsubscribeKeyId($retried), 'only a message handed to the transport records a key');

        // Planned S1: the retry is signed with K2 at send time and recorded as K2.
        $this->useRing(self::k2(), [1 => self::k1()], $k1->hmacId);
        $this->db->run('UPDATE email_deliveries SET next_attempt_at = NULL WHERE id = ?', [$retried]);
        $k2Mail = new ArrayMailer();
        self::assertSame(1, $this->emailWorker($k2Mail)->run(10)['sent']);
        self::assertSame($k2->hmacId, $this->unsubscribeKeyId($retried));

        // So the retried message's link survives K1's retirement; the K1 one does not.
        $this->useRing(self::k2());
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $this->linkQuery($k2Mail->sent[0]['text'])));
        $this->assertStatus(404, $this->get('/unsubscribe', $this->linkQuery($k1Mail->sent[0]['text'])));
    }

    public function test_a_digest_records_the_active_key_that_signed_its_link(): void
    {
        $k2 = self::key(self::k2());
        $this->useRing(self::k2(), [1 => self::k1()], self::key(self::k1())->hmacId);
        $author = $this->makeUser();
        $recipient = $this->makeUser();
        $this->db->run("UPDATE users SET timezone = 'UTC', digest_hour = 9 WHERE id = ?", [(int) $recipient['id']]);
        $thread = $this->makeThread($this->makeBoard($this->makeCategory()), $author, 'Digest topic', 'OP.');
        $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Fresh reply.']);
        (new SubscriptionRepository($this->db))->set((int) $recipient['id'], 'thread', $thread['thread_id'], true, true, 'daily');
        $this->db->run("UPDATE posts SET created_at = '2026-06-26 08:00:00' WHERE thread_id = ?", [$thread['thread_id']]);

        $mail = new ArrayMailer();
        self::assertSame(1, $this->digestWorker($mail)->run('2026-06-26 09:30:00')['sent']);
        self::assertSame($k2->hmacId, $this->db->fetchValue(
            "SELECT unsubscribe_key_id FROM email_deliveries WHERE kind = 'digest' AND user_id = ?",
            [(int) $recipient['id']],
        ));
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $this->linkQuery($mail->sent[0]['text'])));
    }

    /** @return array<string,array{0:string}> spellings that share CANONICAL's encryption key */
    public static function historicalSpellings(): array
    {
        return [
            'uppercase hex' => [strtoupper(self::CANONICAL)],
            'hex digits past the first 64' => [self::CANONICAL . 'deadbeef'],
            'trailing newline' => [self::CANONICAL . "\n"],
            'surrounding spaces' => ['  ' . self::CANONICAL . '  '],
        ];
    }

    #[DataProvider('historicalSpellings')]
    public function test_a_historical_key_spelling_keeps_working_from_a_normalized_ring(string $historical): void
    {
        $this->useRing($historical);
        $member = $this->makeUser();
        [$secret, $codes] = $this->enroll($member);
        $link = $this->unsubscribeQuery((string) $member['email'], $historical);

        // Same encryption key, different HMAC key: keep the historical string,
        // byte for byte, as a previous key permitted to verify.
        self::assertSame(self::key(self::CANONICAL)->encryptionId, self::key($historical)->encryptionId);
        self::assertNotSame(self::key(self::CANONICAL)->hmacId, self::key($historical)->hmacId);
        $this->useRing(self::CANONICAL, [1 => $historical], self::key($historical)->hmacId);
        $this->assertRedirect($this->signInWithAuthenticator($member, $secret), '/inbox');
        $this->assertRedirect($this->signInWithCode($member, $codes[0]), '/inbox');
        $this->logoutClient();
        $this->assertStatus(200, $this->get('/unsubscribe', $link));

        // Without that entry the shared encryption key still opens the secret,
        // but codes and links keyed by the historical string no longer verify.
        $this->useRing(self::CANONICAL);
        $this->assertRedirect($this->signInWithAuthenticator($member, $secret), '/inbox');
        $this->assertStatus(422, $this->signInWithCode($member, $codes[1]));
        $this->logoutClient();
        $this->assertStatus(404, $this->get('/unsubscribe', $link));
    }

    public function test_passkey_login_decoys_change_with_the_key_while_real_credential_ids_do_not(): void
    {
        (new SettingRepository($this->db))->set('features', ['passkeys' => true]);
        $rp = new RelyingParty((string) $this->config->get('app.url', 'http://localhost:8000'), null, 'testing');
        $harness = new WebAuthnHarness($rp->rpId(), $rp->origin());
        $member = $this->makeUser();
        $this->actingAs($member);
        $challenge = $this->post('/settings/security/passkeys/challenge', ['current_password' => 'password123']);
        $this->assertStatus(200, $challenge);
        $credential = $harness->createCredential();
        $this->assertStatus(200, $this->post('/settings/security/passkeys', [
            'credential' => $harness->registrationPayload($credential, (string) Base64Url::decode(json_decode($challenge->body(), true)['options']['challenge'])),
        ]));
        $this->logoutClient();

        $beforeMember = $this->passkeyAllowIds((string) $member['email']);
        $beforeUnknown = $this->passkeyAllowIds('nobody-rotation@example.test');
        self::assertSame($beforeMember, $this->passkeyAllowIds((string) $member['email']), 'stable across requests under one key');

        $this->useRing(self::k2(), [1 => self::k1()], self::key(self::k1())->hmacId);
        $afterMember = $this->passkeyAllowIds((string) $member['email']);
        $afterUnknown = $this->passkeyAllowIds('nobody-rotation@example.test');

        self::assertCount(8, $afterMember);
        self::assertCount(8, $afterUnknown);
        // Comparing responses from before and after a key change separates real
        // credential IDs from decoys, revealing how many passkeys an account has.
        self::assertSame([Base64Url::encode($credential['credentialId'])], array_values(array_intersect($beforeMember, $afterMember)));
        self::assertSame([], array_values(array_intersect($beforeUnknown, $afterUnknown)));
    }

    public function test_thread_intelligence_latch_follows_the_active_key(): void
    {
        $settings = fn (string $appKey): ThreadIntelligenceSettings => new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            ThreadIntelligenceConfig::fromArray(['model' => 'gpt-5.6-luna', 'reasoning_effort' => 'low', 'api_key' => 'sk-rotation']),
            $appKey,
            'sk-rotation',
            $this->db,
        );
        $settings(self::k1())->blockProvider('authentication', new DateTimeImmutable('2026-11-08 00:00:00', new DateTimeZone('UTC')));

        self::assertTrue($settings(self::k1())->providerHealth()['blocked']);
        self::assertFalse($settings(self::k2())->providerHealth()['blocked'], 'a new key no longer matches the keyed fingerprint');
        self::assertTrue($settings(self::k1())->providerHealth()['blocked'], 'the stored latch itself is unchanged');
    }

    private static function k1(): string
    {
        return str_repeat('1a', 32);
    }

    private static function k2(): string
    {
        return str_repeat('2b', 32);
    }

    private static function key(string $raw): AppKey
    {
        return AppKey::fromRaw($raw, 'test', false, false);
    }

    /** SecretBox's key derivation before the key ring, for pre-keyring reader checks. */
    private static function preKeyringEncryptionKey(string $raw): string
    {
        $raw = trim($raw);
        if (ctype_xdigit($raw) && strlen($raw) >= 64) {
            return (string) hex2bin(substr($raw, 0, 64));
        }
        return hash('sha256', $raw, true);
    }

    /** @param array<int,string> $previous */
    private function useRing(string $active, array $previous = [], string $verifyIds = '', ?HttpClient $http = null): void
    {
        $items = $this->config->all();
        $items['app']['key'] = $active;
        $items['app']['previous_keys'] = $previous;
        $items['app']['previous_key_verify_ids'] = $verifyIds;
        if ($http !== null) {
            $items['oauth']['google'] = ['client_id' => 'rotation-client', 'client_secret' => 'rotation-secret'];
        }
        $this->config = new Config($items);
        $this->app = new App($this->config, $this->db, $this->rateLimiter, $http);
    }

    private function box(): SecretBox
    {
        return new SecretBox(AppKeyRing::fromConfig($this->config));
    }

    private function vault(): SecretVault
    {
        return new SecretVault(
            $this->db,
            new ServiceSecretRepository($this->db),
            $this->box(),
            new ModerationLogRepository($this->db),
            new FeatureFlags(new SettingRepository($this->db)),
            $this->config,
        );
    }

    /** @return list<?string> newest version first */
    private function vaultKeyIds(string $ref): array
    {
        return array_column($this->db->fetchAll(
            'SELECT v.key_id FROM service_secret_versions v JOIN service_secrets s ON s.id = v.secret_id
              WHERE s.secret_ref = ? ORDER BY v.version DESC',
            [$ref],
        ), 'key_id');
    }

    /** @param array<string,mixed> $user @return array{0:string,1:list<string>} */
    private function enroll(array $user): array
    {
        $this->actingAs($user);
        $start = $this->post('/settings/security/totp/enroll', ['current_password' => 'password123']);
        $this->assertStatus(200, $start);
        $secret = $this->authenticatorSecret($start);
        $confirm = $this->post('/settings/security/totp/confirm', [
            'current_password' => 'password123',
            'totp_code' => (new Totp())->code($secret),
        ]);
        $this->assertStatus(200, $confirm);
        $codes = $this->recoveryCodesIn($confirm);
        self::assertCount(10, $codes);
        $this->logoutClient();
        return [$secret, $codes];
    }

    /** Make a member's two-factor rows look as they did before migration 0087. */
    private function forgetAttribution(array $user): void
    {
        $this->db->run('UPDATE user_totp_credentials SET secret_key_id = NULL WHERE user_id = ?', [(int) $user['id']]);
        $this->db->run('UPDATE user_recovery_codes SET hmac_key_id = NULL WHERE user_id = ?', [(int) $user['id']]);
    }

    private function totpKeyId(array $user): ?string
    {
        $id = $this->db->fetchValue('SELECT secret_key_id FROM user_totp_credentials WHERE user_id = ?', [(int) $user['id']]);
        return is_string($id) ? $id : null;
    }

    /** @return list<?string> distinct key ids across the member's recovery codes */
    private function recoveryKeyIds(array $user): array
    {
        return array_values(array_unique(array_column(
            $this->db->fetchAll('SELECT hmac_key_id FROM user_recovery_codes WHERE user_id = ?', [(int) $user['id']]),
            'hmac_key_id',
        ), SORT_REGULAR));
    }

    /** Re-encrypt the unchanged secret under another key with that key's id, as a re-encryption does. */
    private function rewrapTotpSecret(array $user, string $secret, string $key): void
    {
        $sealed = (new SecretBox($key))->encrypt($secret);
        $this->db->run(
            'UPDATE user_totp_credentials SET secret_ciphertext = ?, secret_nonce = ?, secret_tag = ?, secret_key_id = ? WHERE user_id = ?',
            [$sealed['ciphertext'], $sealed['nonce'], $sealed['tag'], $sealed['key_id'], (int) $user['id']],
        );
    }

    /** MfaRepository::savePendingTotp as it was before the key ring: no secret_key_id. */
    private function preKeyringSavePendingTotp(array $user, string $secret, string $appKey): void
    {
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($secret, 'aes-256-gcm', self::preKeyringEncryptionKey($appKey), OPENSSL_RAW_DATA, $nonce, $tag);
        $this->db->run(
            'INSERT INTO user_totp_credentials
                (user_id, secret_ciphertext, secret_nonce, secret_tag, enabled_at, verified_at, disabled_at, last_used_step, created_at, updated_at)
             VALUES (?, ?, ?, ?, NULL, NULL, NULL, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE secret_ciphertext = VALUES(secret_ciphertext), secret_nonce = VALUES(secret_nonce),
                secret_tag = VALUES(secret_tag), enabled_at = NULL, verified_at = NULL, disabled_at = NULL,
                last_used_step = NULL, updated_at = UTC_TIMESTAMP()',
            [(int) $user['id'], $ciphertext, $nonce, $tag],
        );
    }

    /**
     * Two-factor sign-in with the current authenticator code. Replay protection
     * would refuse a second use of one 30-second step within this fast test, so
     * the recorded step is cleared first; AppMfaTest covers replay itself.
     */
    private function signInWithAuthenticator(array $user, string $secret): Response
    {
        $this->db->run('UPDATE user_totp_credentials SET last_used_step = NULL WHERE user_id = ?', [(int) $user['id']]);
        return $this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => (new Totp())->code($secret)]);
    }

    private function signInWithCode(array $user, string $code): Response
    {
        return $this->post('/login/mfa', ['mfa_token' => $this->beginChallenge($user), 'code' => $code]);
    }

    private function beginChallenge(array $user): string
    {
        $this->logoutClient();
        $this->get('/login');
        $password = $this->post('/login', ['email' => $user['email'], 'password' => 'password123']);
        $this->assertStatus(200, $password);
        self::assertSame(1, preg_match('/name="mfa_token" value="([a-f0-9]+)"/', $password->body(), $m));
        return $m[1];
    }

    /** @return array<string,string> query of the member's signed unsubscribe link */
    private function unsubscribeQuery(string $email, string $key): array
    {
        parse_str((string) parse_url(UnsubscribeController::link('https://forum.example.test', $email, $key), PHP_URL_QUERY), $query);
        return $query;
    }

    /** @return array<string,string> query of the unsubscribe link in a sent message */
    private function linkQuery(string $text): array
    {
        self::assertSame(1, preg_match('~/unsubscribe\?(\S+)~', $text, $m));
        parse_str($m[1], $query);
        return $query;
    }

    private function unsubscribeKeyId(int $deliveryId): ?string
    {
        $id = $this->db->fetchValue('SELECT unsubscribe_key_id FROM email_deliveries WHERE id = ?', [$deliveryId]);
        return is_string($id) ? $id : null;
    }

    /** Issue through the real service and read the token from the captured mail. */
    private function issueEmailChange(array $user, string $newEmail): string
    {
        $request = new Request('GET', '/settings/account/email', [], [], $this->cookies);
        $container = (new \ReflectionMethod($this->app, 'buildContainer'))->invoke($this->app, $request);
        $container->get(Session::class)->start($request);
        $mail = new ArrayMailer();
        $container->instance(Mailer::class, $mail);
        $container->get(EmailChangeService::class)->issue($this->userEntity($user), $newEmail, 'password123', null, 'rotation-binding');
        self::assertSame(1, preg_match('/[?&]token=([a-f0-9]{64})/', $mail->sent[0]['text'], $match));
        return $match[1];
    }

    private function emailWorker(Mailer $mailer): NotificationEmailWorker
    {
        $settings = new SettingRepository($this->db);
        $visibility = new NotificationVisibilityService($this->db);
        return new NotificationEmailWorker(
            new EmailDeliveryRepository($this->db),
            new EmailSuppressionRepository($this->db),
            new PostRepository($this->db),
            $this->users(),
            $mailer,
            $this->config,
            $settings,
            null,
            new EmailPreferenceService(new UserPreferenceRepository($this->db)),
            $visibility,
            new DigestService(new DigestActivityRepository($this->db), $visibility, $this->config, $settings),
            AppKeyRing::fromConfig($this->config),
        );
    }

    private function digestWorker(Mailer $mailer): DailyDigestWorker
    {
        $deliveries = new EmailDeliveryRepository($this->db);
        $suppression = new EmailSuppressionRepository($this->db);
        $settings = new SettingRepository($this->db);
        $preferences = new EmailPreferenceService(new UserPreferenceRepository($this->db));
        $visibility = new NotificationVisibilityService($this->db);
        $digests = new DigestService(new DigestActivityRepository($this->db), $visibility, $this->config, $settings);
        return new DailyDigestWorker($this->db, $deliveries, $suppression, $mailer, $this->config,
            $settings, null, $preferences, $visibility, $digests, $this->emailWorker($mailer));
    }

    private function failingMailer(): Mailer
    {
        return new class implements Mailer {
            public function send(string $to, string $subject, string $textBody, ?string $htmlBody = null): string
            {
                throw new \RuntimeException('transport unavailable');
            }

            public function isConfigured(): bool
            {
                return true;
            }
        };
    }

    /** @return array{0:string,1:string} state and nonce from the provider redirect */
    private function startGoogleSignIn(): array
    {
        $this->logoutClient();
        $redirect = $this->get('/auth/google/redirect');
        self::assertSame(302, $redirect->status());
        self::assertStringStartsWith('https://accounts.google.com/', (string) $redirect->getHeader('Location'));
        parse_str((string) parse_url((string) $redirect->getHeader('Location'), PHP_URL_QUERY), $query);
        return [(string) $query['state'], (string) $query['nonce']];
    }

    private static function googleIdToken(string $sub, string $email, string $nonce): string
    {
        $part = static fn (array $data): string => Base64Url::encode((string) json_encode($data));
        return $part(['alg' => 'RS256', 'typ' => 'JWT']) . '.'
            . $part(['sub' => $sub, 'email' => $email, 'email_verified' => true, 'name' => 'Rotation Member', 'nonce' => $nonce])
            . '.signature';
    }

    /** @return list<string> */
    private function passkeyAllowIds(string $email): array
    {
        $this->get('/login');
        $response = $this->post('/login/passkey/challenge', ['email' => $email]);
        $this->assertStatus(200, $response);
        return array_column(json_decode($response->body(), true)['options']['allowCredentials'], 'id');
    }

    private function authenticatorSecret(Response $response): string
    {
        $document = new \DOMDocument();
        @$document->loadHTML($response->body());
        $input = (new \DOMXPath($document))->query('//label[span[normalize-space(.) = "Authenticator secret"]]/input[@readonly]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $input);
        return $input->getAttribute('value');
    }

    /** @return list<string> */
    private function recoveryCodesIn(Response $response): array
    {
        preg_match_all('/<code>([A-F0-9]{5}-[A-F0-9]{5})<\/code>/', $response->body(), $m);
        return $m[1];
    }
}
