<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F8 - secrets/challenges/tokens/PII/private content never reach a
 * log line.
 */
final class LogRedactorTest extends TestCase
{
    public function test_sensitive_keys_are_redacted(): void
    {
        $out = LogRedactor::redact([
            'password' => 'hunter2',
            'current_password' => 'hunter2',
            'api_token' => 'abc',
            'client_secret' => 'abc',
            'challenge' => 'abc',
            'credential_id' => 'abc',
            'authorization' => 'Bearer abc',
            'cookie' => 'rb_session=abc',
            'signature' => 'abc',
            'private_key' => 'abc',
            'recovery_code' => 'abc',
            'totp_secret' => 'abc',
            'email' => 'a@b.test',
            'body' => 'private post text',
            'content' => 'private post text',
            'ciphertext' => 'abc',
        ]);
        foreach ($out as $key => $value) {
            self::assertSame(LogRedactor::REDACTED, $value, $key);
        }
    }

    public function test_short_ambiguous_keys_use_boundary_matching(): void
    {
        $out = LogRedactor::redact([
            'ip' => '203.0.113.9',
            'user_ip' => '203.0.113.9',
            'ip_hash' => 'abc',
            'tag' => 'gcm-tag-bytes',
            'otp' => '123456',
            'description' => 'ship it',
            'tags' => ['help', 'question'],
            'zip' => '49001',
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['ip']);
        self::assertSame(LogRedactor::REDACTED, $out['user_ip']);
        self::assertSame(LogRedactor::REDACTED, $out['ip_hash']);
        self::assertSame(LogRedactor::REDACTED, $out['tag']);
        self::assertSame(LogRedactor::REDACTED, $out['otp']);
        self::assertSame('ship it', $out['description']);
        self::assertSame(['help', 'question'], $out['tags']);
        self::assertSame('49001', $out['zip']);
    }

    public function test_sensitive_value_shapes_are_redacted_under_innocent_keys(): void
    {
        $out = LogRedactor::redact([
            'note' => 'rbt_' . str_repeat('a1', 24),
            'ref' => 'svcsec_' . str_repeat('b2', 16),
            'header' => 'Bearer rbt_deadbeef',
            'ok' => 'plain value',
            'digest' => hash('sha256', 'x'),
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['note']);
        self::assertSame(LogRedactor::REDACTED, $out['ref']);
        self::assertSame(LogRedactor::REDACTED, $out['header']);
        self::assertSame('plain value', $out['ok']);
        self::assertSame(hash('sha256', 'x'), $out['digest']);
    }

    public function test_redaction_is_recursive_and_type_safe(): void
    {
        $out = LogRedactor::redact([
            'meta' => ['inner' => ['password' => 'x', 'thread_id' => 42]],
            'token' => 12345,
            'count' => 7,
        ]);
        self::assertSame(LogRedactor::REDACTED, $out['meta']['inner']['password']);
        self::assertSame(42, $out['meta']['inner']['thread_id']);
        self::assertSame(LogRedactor::REDACTED, $out['token']);
        self::assertSame(7, $out['count']);
    }
}
