<?php

declare(strict_types=1);

namespace App\Hook;

use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Security\WebhookEvents;
use Throwable;

/**
 * Code-only first-party hook registry.
 *
 * This intentionally does not load plugins, execute third-party PHP, persist
 * manifests, or expose lifecycle state. It is a synchronous in-process seam for
 * core-owned listeners only.
 */
final class FirstPartyHookRegistry
{
    /** @var array<string,array<string,callable(HookEvent):void>> */
    private array $listeners = [];

    /** @var array<string,array<string,callable(mixed,array<string,mixed>):mixed>> */
    private array $filters = [];

    /** @var array<string,array<string,bool>> */
    private array $disabledListeners = [];

    /** @var array<string,array<string,bool>> */
    private array $disabledFilters = [];

    private mixed $flags;

    /**
     * @param FeatureFlags|callable(string):bool $flags
     * @param null|callable(string):void $logger
     */
    public function __construct(
        FeatureFlags|callable $flags,
        private mixed $logger = null,
    ) {
        $this->flags = $flags;
    }

    /** @param callable(HookEvent):void $listener */
    public function on(string $event, string $listenerId, callable $listener): void
    {
        $this->assertKnown($event);
        $listenerId = $this->cleanId($listenerId);
        if (isset($this->listeners[$event][$listenerId])) {
            throw new ValidationException(['listener' => 'Duplicate hook listener.']);
        }
        $this->listeners[$event][$listenerId] = $listener;
    }

    /** @param array<string,mixed> $payload */
    public function emit(string $event, array $payload, ?string $eventId = null): void
    {
        $this->assertKnown($event);
        if (!$this->hooksEnabled()) {
            return;
        }

        $hookEvent = new HookEvent($event, $eventId ?? bin2hex(random_bytes(16)), $payload);
        foreach ($this->listeners[$event] ?? [] as $listenerId => $listener) {
            if (isset($this->disabledListeners[$event][$listenerId])) {
                continue;
            }
            try {
                $listener($hookEvent);
            } catch (Throwable $e) {
                $this->disabledListeners[$event][$listenerId] = true;
                $this->logFailure('listener', $event, $listenerId, $e);
            }
        }
    }

    /** @param callable(mixed,array<string,mixed>):mixed $filter */
    public function filter(string $name, string $listenerId, callable $filter): void
    {
        $this->assertKnown($name);
        $listenerId = $this->cleanId($listenerId);
        if (isset($this->filters[$name][$listenerId])) {
            throw new ValidationException(['listener' => 'Duplicate hook filter.']);
        }
        $this->filters[$name][$listenerId] = $filter;
    }

    /** @param array<string,mixed> $context */
    public function applyFilters(string $name, mixed $value, array $context = []): mixed
    {
        $this->assertKnown($name);
        if (!$this->hooksEnabled()) {
            return $value;
        }

        $filtered = $value;
        foreach ($this->filters[$name] ?? [] as $listenerId => $filter) {
            if (isset($this->disabledFilters[$name][$listenerId])) {
                continue;
            }
            try {
                $filtered = $filter($filtered, $context);
            } catch (Throwable $e) {
                $this->disabledFilters[$name][$listenerId] = true;
                $this->logFailure('filter', $name, $listenerId, $e);
            }
        }
        return $filtered;
    }

    private function assertKnown(string $name): void
    {
        if (!WebhookEvents::isValid($name)) {
            throw new ValidationException(['event' => 'Unknown event type.']);
        }
    }

    private function cleanId(string $listenerId): string
    {
        $listenerId = trim($listenerId);
        if ($listenerId === '') {
            throw new ValidationException(['listener' => 'Listener id is required.']);
        }
        return $listenerId;
    }

    private function hooksEnabled(): bool
    {
        if ($this->flags instanceof FeatureFlags) {
            return $this->flags->enabled('first_party_hooks');
        }
        return (bool) ($this->flags)('first_party_hooks');
    }

    private function logFailure(string $kind, string $name, string $listenerId, Throwable $e): void
    {
        $line = sprintf(
            'First-party hook %s failed; hook=%s listener=%s exception=%s',
            $kind,
            $name,
            $listenerId,
            $e::class,
        );
        if (is_callable($this->logger)) {
            ($this->logger)($line);
            return;
        }
        error_log($line);
    }
}
