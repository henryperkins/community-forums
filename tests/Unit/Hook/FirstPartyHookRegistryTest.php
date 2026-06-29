<?php

declare(strict_types=1);

namespace Tests\Unit\Hook;

use App\Core\ValidationException;
use App\Hook\FirstPartyHookRegistry;
use App\Hook\HookEvent;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class FirstPartyHookRegistryTest extends TestCase
{
    public function test_flag_dark_noops_emit_and_filters(): void
    {
        $registry = new FirstPartyHookRegistry(static fn (): bool => false);
        $called = false;
        $registry->on('topic.created', 'a', function () use (&$called): void {
            $called = true;
        });
        $registry->filter('topic.created', 'f', static fn (mixed $value): string => $value . '-changed');

        $registry->emit('topic.created', ['thread_id' => 1], 'thread:1:created');

        self::assertFalse($called);
        self::assertSame('original', $registry->applyFilters('topic.created', 'original'));
    }

    public function test_listeners_run_in_registration_order_with_event_object(): void
    {
        $registry = new FirstPartyHookRegistry(static fn (): bool => true);
        $seen = [];
        $registry->on('topic.created', 'first', function (HookEvent $event) use (&$seen): void {
            $seen[] = ['first', $event->name, $event->id, $event->data['thread_id'] ?? null];
        });
        $registry->on('topic.created', 'second', function (HookEvent $event) use (&$seen): void {
            $seen[] = ['second', $event->name, $event->id, $event->data['thread_id'] ?? null];
        });

        $registry->emit('topic.created', ['thread_id' => 5], 'thread:5:created');

        self::assertSame([
            ['first', 'topic.created', 'thread:5:created', 5],
            ['second', 'topic.created', 'thread:5:created', 5],
        ], $seen);
    }

    public function test_duplicate_listener_id_is_rejected(): void
    {
        $registry = new FirstPartyHookRegistry(static fn (): bool => true);
        $registry->on('topic.created', 'dup', static function (): void {
        });

        $this->expectException(ValidationException::class);
        $registry->on('topic.created', 'dup', static function (): void {
        });
    }

    public function test_filter_chain_runs_in_registration_order(): void
    {
        $registry = new FirstPartyHookRegistry(static fn (): bool => true);
        $registry->filter('topic.created', 'a', static fn (mixed $value): string => $value . 'a');
        $registry->filter('topic.created', 'b', static fn (mixed $value): string => $value . 'b');

        self::assertSame('start-ab', $registry->applyFilters('topic.created', 'start-'));
    }

    public function test_unknown_event_name_is_rejected(): void
    {
        $registry = new FirstPartyHookRegistry(static fn (): bool => true);

        $this->expectException(ValidationException::class);
        $registry->emit('not.real', [], 'x');
    }

    public function test_listener_failure_is_logged_redacted_and_disabled_for_lifetime(): void
    {
        $logs = [];
        $registry = new FirstPartyHookRegistry(
            static fn (): bool => true,
            static function (string $line) use (&$logs): void {
                $logs[] = $line;
            },
        );
        $attempts = 0;
        $successful = 0;
        $registry->on('topic.created', 'bad', function () use (&$attempts): void {
            $attempts++;
            throw new RuntimeException('secret-token-123');
        });
        $registry->on('topic.created', 'good', function () use (&$successful): void {
            $successful++;
        });

        $registry->emit('topic.created', ['thread_id' => 1], 'thread:1:created');
        $registry->emit('topic.created', ['thread_id' => 1], 'thread:1:created');

        self::assertSame(1, $attempts);
        self::assertSame(2, $successful);
        self::assertCount(1, $logs);
        self::assertStringContainsString('RuntimeException', $logs[0]);
        self::assertStringNotContainsString('secret-token-123', $logs[0]);
    }
}
