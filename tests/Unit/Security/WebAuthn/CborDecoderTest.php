<?php

declare(strict_types=1);

namespace Tests\Unit\Security\WebAuthn;

use App\Security\WebAuthn\CborDecoder;
use App\Security\WebAuthn\WebAuthnException;
use PHPUnit\Framework\TestCase;

final class CborDecoderTest extends TestCase
{
    public function test_decodes_the_webauthn_subset(): void
    {
        $bytes = "\xa3" . "\x63fmt" . "\x64none" . "\x67attStmt" . "\xa0" . "\x68authData" . "\x42\xaa\xbb";
        self::assertSame(['fmt' => 'none', 'attStmt' => [], 'authData' => "\xaa\xbb"], CborDecoder::decode($bytes));
    }

    public function test_decodes_cose_style_integer_keys_and_negative_integers(): void
    {
        $bytes = "\xa3\x01\x02\x03\x26\x20\x01";
        self::assertSame([1 => 2, 3 => -7, -1 => 1], CborDecoder::decode($bytes));
    }

    public function test_decode_first_returns_the_remainder(): void
    {
        [$value, $rest] = CborDecoder::decodeFirst("\x02rest");
        self::assertSame(2, $value);
        self::assertSame('rest', $rest);
    }

    public function test_rejects_trailing_bytes_in_full_decode(): void
    {
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\x02extra");
    }

    public function test_rejects_indefinite_length_items(): void
    {
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\x5f\x41\x01\xff");
    }

    public function test_rejects_duplicate_map_keys_including_int_string_aliasing(): void
    {
        $bytes = "\xa2\x01\x00" . "\x611" . "\x00";
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode($bytes);
    }

    public function test_rejects_truncated_input_and_excess_depth(): void
    {
        try {
            CborDecoder::decode("\x42\xaa");
            self::fail('Truncated CBOR must throw');
        } catch (WebAuthnException $e) {
            self::assertSame('malformed_cbor', $e->code);
        }
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode(str_repeat("\x81", 9) . "\x01");
    }

    public function test_rejects_floats_and_tags(): void
    {
        try {
            CborDecoder::decode("\xfa\x3f\x80\x00\x00");
            self::fail('Float must throw');
        } catch (WebAuthnException) {
            $this->addToAssertionCount(1);
        }
        $this->expectException(WebAuthnException::class);
        CborDecoder::decode("\xc0\x60");
    }
}
