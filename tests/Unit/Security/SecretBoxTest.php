<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\AppKeyRing;
use App\Security\SecretBox;
use App\Security\SecretUnavailableException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretBoxTest extends TestCase
{
    public function test_round_trips_under_the_same_key_and_returns_encryption_key_id(): void
    {
        $box = new SecretBox(str_repeat('1a', 32));
        $sealed = $box->encrypt('JBSWY3DPEHPK3PXP');

        self::assertMatchesRegularExpression('/\A[0-9a-f]{16}\z/', $sealed['key_id']);
        self::assertSame($box->keyRing()->active()->encryptionId, $sealed['key_id']);
        self::assertSame('JBSWY3DPEHPK3PXP', $box->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag']));
        self::assertSame('JBSWY3DPEHPK3PXP', $box->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag'], $sealed['key_id']));
    }

    public function test_previous_key_opens_both_attributed_and_legacy_records_even_when_decrypt_only(): void
    {
        $k1 = str_repeat('1a', 32);
        $k2 = str_repeat('2b', 32);
        $sealedUnderK1 = (new SecretBox($k1))->encrypt('JBSWY3DPEHPK3PXP');

        $ring = AppKeyRing::build($k2, [1 => $k1], '');
        $box = new SecretBox($ring);

        $openedAttributed = $box->open($sealedUnderK1['ciphertext'], $sealedUnderK1['nonce'], $sealedUnderK1['tag'], $sealedUnderK1['key_id']);
        self::assertSame('JBSWY3DPEHPK3PXP', $openedAttributed['plaintext']);
        self::assertSame($sealedUnderK1['key_id'], $openedAttributed['key_id']);

        $openedLegacy = $box->open($sealedUnderK1['ciphertext'], $sealedUnderK1['nonce'], $sealedUnderK1['tag'], null);
        self::assertSame('JBSWY3DPEHPK3PXP', $openedLegacy['plaintext']);
        self::assertSame($sealedUnderK1['key_id'], $openedLegacy['key_id']);
    }

    public function test_a_record_attributed_to_an_unconfigured_key_id_fails_without_trying_other_keys(): void
    {
        $k1 = str_repeat('1a', 32);
        $sealed = (new SecretBox($k1))->encrypt('JBSWY3DPEHPK3PXP');

        $this->expectException(SecretUnavailableException::class);
        $this->expectExceptionMessage('encryption key deadbeefcafebabe is not configured');
        (new SecretBox($k1))->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag'], 'deadbeefcafebabe');
    }

    public function test_a_record_sealed_under_another_key_is_unavailable(): void
    {
        $sealed = (new SecretBox(str_repeat('1a', 32)))->encrypt('JBSWY3DPEHPK3PXP');

        $this->expectException(SecretUnavailableException::class);
        $this->expectExceptionMessage('Unable to decrypt secret.');
        (new SecretBox(str_repeat('2b', 32)))->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag']);
    }

    public function test_an_altered_record_is_unavailable(): void
    {
        $box = new SecretBox(str_repeat('1a', 32));
        $sealed = $box->encrypt('JBSWY3DPEHPK3PXP');
        $sealed['ciphertext'][0] = chr(ord($sealed['ciphertext'][0]) ^ 1);

        $this->expectException(SecretUnavailableException::class);
        $box->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag']);
    }

    public function test_unavailability_is_still_a_runtime_exception_for_existing_callers(): void
    {
        self::assertTrue(is_subclass_of(SecretUnavailableException::class, RuntimeException::class));
    }

    public function test_a_missing_key_is_a_configuration_failure_not_an_unavailable_record(): void
    {
        $sealed = (new SecretBox(str_repeat('1a', 32)))->encrypt('JBSWY3DPEHPK3PXP');

        foreach (['', "  \n"] as $missing) {
            try {
                (new SecretBox($missing))->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag']);
                self::fail('A missing APP_KEY must throw.');
            } catch (RuntimeException $e) {
                self::assertNotInstanceOf(SecretUnavailableException::class, $e);
                self::assertSame('APP_KEY is required for secret encryption.', $e->getMessage());
            }
        }
    }
}
