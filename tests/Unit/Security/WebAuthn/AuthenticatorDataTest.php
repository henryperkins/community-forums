<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\AuthenticatorData;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class AuthenticatorDataTest extends TestCase
{
    private const COSE_STUB = "\xa1\x01\x02";

    private static function bytes(int $flags, int $signCount, ?string $credId = null, string $cose = self::COSE_STUB, string $tail = ''): string
    {
        $out = hash('sha256', 'localhost', true) . chr($flags) . pack('N', $signCount);
        if ($credId !== null) {
            $out .= str_repeat("\xAA", 16) . pack('n', strlen($credId)) . $credId . $cose;
        }
        return $out . $tail;
    }

    public function test_parses_assertion_shape_and_flags(): void
    {
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x04 | 0x08 | 0x10, 42));
        self::assertSame(hash('sha256', 'localhost', true), $a->rpIdHash);
        self::assertSame(42, $a->signCount);
        self::assertTrue($a->userPresent());
        self::assertTrue($a->userVerified());
        self::assertTrue($a->backupEligible());
        self::assertTrue($a->backedUp());
        self::assertNull($a->credentialId);
        self::assertNull($a->credentialPublicKey);
    }

    public function test_parses_attested_credential_data(): void
    {
        $id = random_bytes(32);
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x40, 0, $id));
        self::assertSame($id, $a->credentialId);
        self::assertSame(str_repeat("\xAA", 16), $a->aaguid);
        self::assertSame(self::COSE_STUB, $a->credentialPublicKey);
        self::assertFalse($a->userVerified());
    }

    public function test_accepts_non_empty_short_credential_ids(): void
    {
        $id = 'x';
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x40, 0, $id));
        self::assertSame($id, $a->credentialId);
    }

    public function test_tolerates_extension_data_when_flagged(): void
    {
        $a = AuthenticatorData::parse(self::bytes(0x01 | 0x80, 7, null, self::COSE_STUB, "\xa1\x63abc\x01"));
        self::assertSame(7, $a->signCount);
    }

    public function test_rejects_truncation_trailing_bytes_and_flag_shape_mismatches(): void
    {
        try {
            AuthenticatorData::parse(substr(self::bytes(0x01, 1), 0, 36));
            self::fail('Short input must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_authenticator_data', $e->code);
        }

        try {
            AuthenticatorData::parse(self::bytes(0x01, 1, null, self::COSE_STUB, 'junk'));
            self::fail('Trailing bytes must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_authenticator_data', $e->code);
        }

        $this->expectException(WebAuthnException::class);
        AuthenticatorData::parse(hash('sha256', 'localhost', true) . chr(0x41) . pack('N', 1));
    }

    public function test_rejects_out_of_range_credential_id_length(): void
    {
        $raw = hash('sha256', 'localhost', true) . chr(0x41) . pack('N', 0)
            . str_repeat("\xAA", 16) . pack('n', 1024) . str_repeat('x', 1024) . self::COSE_STUB;
        $this->expectException(WebAuthnException::class);
        AuthenticatorData::parse($raw);
    }
}
