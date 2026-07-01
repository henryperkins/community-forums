<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Config;
use App\Core\Telemetry;
use App\Support\LogRedactor;
use PHPUnit\Framework\TestCase;

/**
 * Foundation F8 - config-gated correlation-ID telemetry whose emitted lines
 * can never contain secrets/challenges/tokens/PII/private content.
 */
final class TelemetryRedactionTest extends TestCase
{
    /** @param list<string> $captured */
    private function telemetry(bool $enabled, array &$captured): Telemetry
    {
        return new Telemetry(
            new Config(['telemetry' => ['enabled' => $enabled]]),
            function (string $line) use (&$captured): void {
                $captured[] = $line;
            },
        );
    }

    public function test_disabled_telemetry_emits_nothing(): void
    {
        $captured = [];
        $t = $this->telemetry(false, $captured);
        self::assertFalse($t->enabled());
        $t->emit('http.request', ['path' => '/']);
        self::assertSame([], $captured);
    }

    public function test_enabled_telemetry_emits_structured_json_with_correlation_id(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $t->emit('http.request', ['method' => 'GET', 'path' => '/', 'status' => 200]);

        self::assertCount(1, $captured);
        $doc = json_decode($captured[0], true);
        self::assertIsArray($doc);
        self::assertSame('http.request', $doc['event']);
        self::assertSame($t->correlationId(), $doc['cid']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/', $doc['ts']);
        self::assertSame(200, $doc['status']);
    }

    public function test_emitted_lines_are_redacted(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $secret = 'rbt_' . str_repeat('ab', 24);
        $t->emit('api.token', ['note' => $secret, 'password' => 'hunter2', 'thread_id' => 42]);

        self::assertCount(1, $captured);
        self::assertStringNotContainsString($secret, $captured[0]);
        self::assertStringNotContainsString('hunter2', $captured[0]);
        self::assertStringContainsString(LogRedactor::REDACTED, $captured[0]);
        self::assertSame(42, json_decode($captured[0], true)['thread_id']);
    }

    public function test_correlation_id_is_stable_per_instance_and_distinct_across_instances(): void
    {
        $captured = [];
        $a = $this->telemetry(true, $captured);
        $b = $this->telemetry(true, $captured);

        self::assertSame($a->correlationId(), $a->correlationId());
        self::assertNotSame($a->correlationId(), $b->correlationId());
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $a->correlationId());
    }

    public function test_reserved_envelope_keys_win_over_context_keys(): void
    {
        $captured = [];
        $t = $this->telemetry(true, $captured);
        $t->emit('x', ['event' => 'spoofed', 'cid' => 'spoofed', 'ts' => 'spoofed']);
        $doc = json_decode($captured[0], true);
        self::assertSame('x', $doc['event']);
        self::assertSame($t->correlationId(), $doc['cid']);
        self::assertNotSame('spoofed', $doc['ts']);
    }
}
