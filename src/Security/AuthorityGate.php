<?php

declare(strict_types=1);

namespace App\Security;

use App\Core\ForbiddenException;
use App\Core\Telemetry;
use App\Domain\User;
use App\Service\ResolverShadow;

/**
 * The single authorization decision seam for the capability cutover (Inc 6).
 * legacy  — capabilities flag OFF: return the legacy closure verbatim; the
 *           resolver is null and structurally cannot be consulted.
 * shadow  — flag ON, CAPABILITIES_MODE=shadow (default): legacy decides;
 *           ResolverShadow emits mismatch telemetry (Inc 1 behavior).
 * enforce — flag ON, CAPABILITIES_MODE=enforce: the resolver decides and
 *           FAILS CLOSED on any resolver error; the legacy closure is computed
 *           only for reverse-mismatch telemetry (§13.2 capture-state).
 */
final class AuthorityGate
{
    public const MODE_LEGACY = 'legacy';
    public const MODE_SHADOW = 'shadow';
    public const MODE_ENFORCE = 'enforce';

    public function __construct(
        private ?CapabilityResolver $resolver,
        private ?ResolverShadow $shadow,
        private ?Telemetry $telemetry,
        private string $mode,
    ) {
    }

    /** Passthrough gate for dark installs and hand-constructed test services. */
    public static function legacy(): self
    {
        return new self(null, null, null, self::MODE_LEGACY);
    }

    /**
     * Build the flag-on gate from the raw CAPABILITIES_MODE config value.
     * The config vocabulary is shadow|enforce (trim/case-insensitive) — the
     * flag-off `legacy` state is not a configurable posture. Unknown values
     * run the fail-safe shadow posture and emit `capabilities.mode_invalid`
     * so a typo'd mode is visible instead of silently observing.
     */
    public static function fromConfig(string $raw, CapabilityResolver $resolver, ResolverShadow $shadow, Telemetry $telemetry): self
    {
        if (!self::isKnownConfigMode($raw)) {
            $telemetry->emit('capabilities.mode_invalid', [
                'raw' => substr(trim($raw), 0, 32),
                'effective' => self::MODE_SHADOW,
            ]);
        }
        $mode = self::normalizeConfigMode($raw);

        return new self(
            $resolver,
            $mode === self::MODE_ENFORCE ? null : $shadow,
            $telemetry,
            $mode,
        );
    }

    public static function normalizeConfigMode(string $raw): string
    {
        return self::isKnownConfigMode($raw) ? strtolower(trim($raw)) : self::MODE_SHADOW;
    }

    public static function isKnownConfigMode(string $raw): bool
    {
        return in_array(strtolower(trim($raw)), [self::MODE_SHADOW, self::MODE_ENFORCE], true);
    }

    public function mode(): string
    {
        return $this->mode;
    }

    /**
     * @param callable():bool $legacy the site's current legacy gate expression
     * @param array{board_id?:int,owner_id?:int,user_id?:int,category_id?:int} $target
     */
    public function allows(callable $legacy, ?User $actor, string $capability, array $target, string $site): bool
    {
        if ($this->mode === self::MODE_ENFORCE) {
            if ($this->resolver === null) {
                $this->telemetry?->emit('authority.enforce_error', [
                    'site' => $site,
                    'capability' => $capability,
                    'error' => 'missing_resolver',
                ]);
                return false; // authorization fails closed
            }

            try {
                $decision = $this->resolver->can($actor, $capability, $target);
            } catch (\Throwable $e) {
                $this->telemetry?->emit('authority.enforce_error', [
                    'site' => $site,
                    'capability' => $capability,
                    'error' => $e::class,
                ]);
                return false; // authorization fails closed
            }

            try {
                $legacyAllowed = (bool) $legacy();
            } catch (\Throwable) {
                $legacyAllowed = $decision->allowed; // legacy is telemetry-only here
            }
            if ($legacyAllowed !== $decision->allowed) {
                $this->telemetry?->emit('resolver.enforce_mismatch', [
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
            if (!$decision->allowed) {
                $this->telemetry?->emit('authority.enforce_denied', [
                    'site' => $site,
                    'capability' => $capability,
                    'source' => $decision->source,
                    'reason' => $decision->reason,
                    'actor_id' => $actor?->id(),
                    'board_id' => $target['board_id'] ?? null,
                ]);
            }
            return $decision->allowed;
        }

        $allowed = (bool) $legacy();
        if ($this->mode === self::MODE_SHADOW) {
            $this->shadow?->compare($allowed, $actor, $capability, $target, $site);
        }
        return $allowed;
    }

    /** @param callable():bool $legacy */
    public function assert(callable $legacy, ?User $actor, string $capability, array $target, string $site, string $message): void
    {
        if (!$this->allows($legacy, $actor, $capability, $target, $site)) {
            throw new ForbiddenException($message);
        }
    }
}
