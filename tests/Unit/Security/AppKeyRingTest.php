<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\AppKey;
use App\Security\AppKeyRing;
use App\Security\SecretBox;
use PHPUnit\Framework\TestCase;

final class AppKeyRingTest extends TestCase
{
    public function test_hex_keys_use_the_first_64_hex_digits_for_encryption_and_the_exact_string_for_hmac(): void
    {
        $lower = str_repeat('ab', 32);
        $upper = strtoupper($lower);
        $trailing = $lower . 'deadbeef';
        $padded = "  {$lower}\n";

        $kLower = AppKey::fromRaw($lower, 'APP_KEY', true, true);
        $kUpper = AppKey::fromRaw($upper, 'APP_PREVIOUS_KEY_1', false, true);
        $kTrailing = AppKey::fromRaw($trailing, 'APP_PREVIOUS_KEY_2', false, true);
        $kPadded = AppKey::fromRaw($padded, 'APP_PREVIOUS_KEY_3', false, true);

        // Exact legacy SecretBox derivation: trim(), then hex2bin(substr(..., 0, 64)).
        $expectedAes = hex2bin($lower);
        self::assertSame($expectedAes, $kLower->encryptionKey());
        self::assertSame($expectedAes, $kUpper->encryptionKey());
        self::assertSame($expectedAes, $kTrailing->encryptionKey());
        self::assertSame($expectedAes, $kPadded->encryptionKey());
        self::assertSame($kLower->encryptionId, $kUpper->encryptionId);
        self::assertSame($kLower->encryptionId, $kTrailing->encryptionId);
        self::assertSame($kLower->encryptionId, $kPadded->encryptionId);

        // Exact legacy HMAC derivation: raw untrimmed string, case-sensitive.
        self::assertSame(hash_hmac('sha256', 'probe', $lower), $kLower->hmac('probe'));
        self::assertSame(hash_hmac('sha256', 'probe', $upper), $kUpper->hmac('probe'));
        self::assertSame(hash_hmac('sha256', 'probe', $trailing), $kTrailing->hmac('probe'));
        self::assertSame(hash_hmac('sha256', 'probe', $padded), $kPadded->hmac('probe'));
        self::assertCount(4, array_unique([$kLower->hmacId, $kUpper->hmacId, $kTrailing->hmacId, $kPadded->hmacId]));
    }

    public function test_non_hex_or_short_keys_use_sha256_of_trimmed_value_for_encryption_and_raw_for_hmac(): void
    {
        $passphrase = "  correct-horse-battery-staple\n";
        $shortHex = str_repeat('1a', 31); // 62 hex chars (< 64)

        $kPass = AppKey::fromRaw($passphrase, 'APP_KEY', true, true);
        $kShort = AppKey::fromRaw($shortHex, 'APP_PREVIOUS_KEY_1', false, false);

        self::assertSame(hash('sha256', 'correct-horse-battery-staple', true), $kPass->encryptionKey());
        self::assertSame($passphrase, $kPass->hmacKey());
        self::assertSame(hash('sha256', $shortHex, true), $kShort->encryptionKey());
        self::assertSame($shortHex, $kShort->hmacKey());
    }

    public function test_spellings_that_share_an_encryption_key_but_differ_in_hmac_coexist_in_the_ring(): void
    {
        $lower = str_repeat('cd', 32);
        $upper = strtoupper($lower);
        $trailing = $lower . '0123';
        $newline = $lower . "\n";

        $kUpper = AppKey::fromRaw($upper, 'APP_PREVIOUS_KEY_1', false, false);
        $kTrailing = AppKey::fromRaw($trailing, 'APP_PREVIOUS_KEY_2', false, false);
        $kNewline = AppKey::fromRaw($newline, 'APP_PREVIOUS_KEY_3', false, false);

        $ring = AppKeyRing::build(
            $lower,
            [1 => $upper, 2 => $trailing, 3 => $newline],
            implode(',', [$kUpper->hmacId, $kTrailing->hmacId, $kNewline->hmacId]),
        );

        self::assertTrue($ring->isValid(), implode('; ', $ring->errors()));
        // Decryption deduplicates by encryptionId; verification keeps every distinct HMAC key.
        self::assertCount(1, $ring->decryptionKeys());
        self::assertCount(4, $ring->verificationKeys());

        // Ciphertext sealed under any of the four spellings opens under the ring.
        foreach ([$lower, $upper, $trailing, $newline] as $spelling) {
            $sealed = (new SecretBox($spelling))->encrypt('compat-secret');
            self::assertSame('compat-secret', (new SecretBox($ring))->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag'], $sealed['key_id']));
            self::assertSame('compat-secret', (new SecretBox($ring))->decrypt($sealed['ciphertext'], $sealed['nonce'], $sealed['tag'], null));
        }

        // Describe notes that the previous entries share their encryption key with APP_KEY.
        $described = $ring->describe();
        self::assertNull($described[0]['shares_encryption_key_with']);
        self::assertSame('APP_KEY', $described[1]['shares_encryption_key_with']);
        self::assertSame('APP_KEY', $described[2]['shares_encryption_key_with']);
        self::assertSame('APP_KEY', $described[3]['shares_encryption_key_with']);
    }

    public function test_previous_keys_are_decrypt_only_by_default_and_verify_permissions_are_per_key(): void
    {
        $kActive = str_repeat('11', 32);
        $kPlanned = str_repeat('22', 32);
        $kCompromised = str_repeat('33', 32);
        $plannedId = AppKey::fromRaw($kPlanned, 'APP_PREVIOUS_KEY_1', false, false)->hmacId;
        $compromisedId = AppKey::fromRaw($kCompromised, 'APP_PREVIOUS_KEY_2', false, false)->hmacId;

        $ring = AppKeyRing::build($kActive, [1 => $kPlanned, 2 => $kCompromised], $plannedId);

        self::assertTrue($ring->isValid());
        self::assertCount(3, $ring->decryptionKeys());
        self::assertSame(
            [$ring->active()->hmacId, $plannedId],
            array_map(static fn (AppKey $k): string => $k->hmacId, $ring->verificationKeys()),
        );
        self::assertNotNull($ring->forHmacId($compromisedId));
        self::assertFalse($ring->forHmacId($compromisedId)->verifies);
    }

    public function test_invalid_entries_are_rejected_without_leaking_key_material_and_never_grant_permissions(): void
    {
        $kActive = str_repeat('aa', 32);
        $kPrev = str_repeat('bb', 32);
        $rawLeak = str_repeat('ff', 32);

        $ring = AppKeyRing::build(
            $kActive,
            [
                1 => $kActive, // duplicate of APP_KEY
                2 => $kPrev,
                3 => "   \n", // blank
                4 => str_repeat('cc', 32), // unsupported slot
            ],
            implode(',', [
                $rawLeak, // accidentally pasted 64-char key instead of 16-char id
                AppKey::fromRaw($kActive, 'APP_KEY', true, true)->hmacId, // active key id
                '0123456789abcdef', // unknown key id
            ]),
        );

        self::assertFalse($ring->isValid());
        self::assertCount(6, $ring->errors());
        $joined = implode("\n", $ring->errors()) . "\n" . print_r($ring->describe(), true) . "\n" . print_r($ring->active(), true);
        self::assertStringNotContainsString($kActive, $joined);
        self::assertStringNotContainsString($kPrev, $joined);
        self::assertStringNotContainsString($rawLeak, $joined);

        // Only the valid previous key in slot 2 is retained, and only as decrypt-only.
        self::assertCount(1, $ring->previous());
        self::assertSame('APP_PREVIOUS_KEY_2', $ring->previous()[0]->source);
        self::assertFalse($ring->previous()[0]->verifies);
        self::assertCount(1, $ring->verificationKeys());
    }
}
