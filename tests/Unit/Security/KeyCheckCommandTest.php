<?php

declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Security\AppKey;
use PHPUnit\Framework\TestCase;

/**
 * `php bin/console key:check` (ADR 0059): validates the key configuration and
 * prints only derived key ids and permissions. It never connects to the database.
 */
final class KeyCheckCommandTest extends TestCase
{
    private const ACTIVE = '2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b2b';
    private const PLANNED = '1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a1a';
    private const EXPOSED = '3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c3c';

    public function test_a_valid_ring_prints_ids_and_permissions_but_no_key_material(): void
    {
        $planned = AppKey::fromRaw(self::PLANNED, 'APP_PREVIOUS_KEY_1', false, false);
        $exposed = AppKey::fromRaw(self::EXPOSED, 'APP_PREVIOUS_KEY_2', false, false);
        [$exit, $out, $err] = $this->keyCheck([
            'APP_KEY' => self::ACTIVE,
            'APP_PREVIOUS_KEY_1' => self::PLANNED,
            'APP_PREVIOUS_KEY_2' => self::EXPOSED,
            'APP_PREVIOUS_KEY_VERIFY_IDS' => $planned->hmacId,
        ]);

        self::assertSame(0, $exit, $err);
        self::assertSame('', $err);
        self::assertMatchesRegularExpression('/APP_PREVIOUS_KEY_1 +previous +encryption ' . $planned->encryptionId . ' +hmac ' . $planned->hmacId . ' +decrypts and verifies/', $out);
        self::assertMatchesRegularExpression('/APP_PREVIOUS_KEY_2 +previous +encryption ' . $exposed->encryptionId . ' +hmac ' . $exposed->hmacId . ' +decrypts only/', $out);
        self::assertStringContainsString('checks configuration only, not whether stored records can be read', $out);
        foreach ([self::ACTIVE, self::PLANNED, self::EXPOSED] as $raw) {
            self::assertStringNotContainsString($raw, $out . $err);
        }
    }

    public function test_configuration_errors_exit_non_zero_without_echoing_secrets(): void
    {
        [$exit, $out, $err] = $this->keyCheck([
            'APP_KEY' => self::ACTIVE,
            'APP_PREVIOUS_KEY_1' => self::ACTIVE,
            'APP_PREVIOUS_KEY_VERIFY_IDS' => self::PLANNED,
        ]);

        self::assertSame(1, $exit);
        self::assertStringContainsString('APP_PREVIOUS_KEY_1 repeats APP_KEY byte for byte.', $err);
        self::assertStringContainsString('APP_PREVIOUS_KEY_VERIFY_IDS entry 1 is not a 16-character lowercase hex HMAC key id.', $err);
        self::assertStringNotContainsString('Key configuration is valid', $out);
        foreach ([self::ACTIVE, self::PLANNED] as $raw) {
            self::assertStringNotContainsString($raw, $out . $err);
        }
    }

    public function test_a_missing_app_key_fails(): void
    {
        foreach (['', "  \n"] as $missing) {
            [$exit, , $err] = $this->keyCheck(['APP_KEY' => $missing]);
            self::assertSame(1, $exit);
            self::assertStringContainsString('APP_KEY is not set', $err);
        }
    }

    /**
     * @param array<string,string> $env
     * @return array{0:int,1:string,2:string}
     */
    private function keyCheck(array $env): array
    {
        $root = dirname(__DIR__, 3);
        $environment = array_merge([
            'PATH' => (string) getenv('PATH'),
            'APP_ENV' => 'testing',
            'APP_KEY' => '',
            'APP_PREVIOUS_KEY_1' => '',
            'APP_PREVIOUS_KEY_2' => '',
            'APP_PREVIOUS_KEY_3' => '',
            'APP_PREVIOUS_KEY_VERIFY_IDS' => '',
            // An unreachable database proves the command never connects.
            'DB_HOST' => '127.0.0.1',
            'DB_PORT' => '1',
        ], $env);
        $process = proc_open([PHP_BINARY, $root . '/bin/console', 'key:check'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root, $environment);
        self::assertIsResource($process);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }
}
