<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\SecretBox;
use App\Security\SecretUnavailableException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretBoxTest extends TestCase
{
    public function test_round_trips_under_the_same_key(): void
    {
        $box = new SecretBox(str_repeat('1a', 32));
        $sealed = $box->encrypt('JBSWY3DPEHPK3PXP');

        self::assertSame('JBSWY3DPEHPK3PXP', $box->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag']));
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
