<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Telemetry;
use App\Domain\User;
use App\Security\CapabilityResolver;

/**
 * Fail-open shadow comparison harness. It records resolver-vs-legacy mismatch
 * telemetry without returning or enforcing the resolver decision.
 */
final class ResolverShadow
{
    public function __construct(
        private CapabilityResolver $resolver,
        private Telemetry $telemetry,
    ) {
    }

    /** @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target */
    public function compare(bool $legacyAllowed, ?User $actor, string $capability, array $target, string $site): void
    {
        try {
            $decision = $this->resolver->can($actor, $capability, $target);
            if ($decision->allowed !== $legacyAllowed) {
                $this->telemetry->emit('resolver.shadow_mismatch', [
                    'site' => $site,
                    'capability' => $capability,
                    'legacy' => $legacyAllowed,
                    'resolver' => $decision->allowed,
                    'source' => $decision->source,
                    'reason' => $decision->reason,
                    'actor_id' => $actor?->id(),
                    'board_id' => $target['board_id'] ?? null,
                ]);
            }
        } catch (\Throwable $e) {
            $this->telemetry->emit('resolver.shadow_error', [
                'site' => $site,
                'capability' => $capability,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
