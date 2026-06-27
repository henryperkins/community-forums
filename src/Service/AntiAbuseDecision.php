<?php

declare(strict_types=1);

namespace App\Service;

/**
 * The outcome of evaluating a candidate submission against the central
 * anti-abuse rules (P3-05). `natural` is what the rules implied; `action` is
 * that clamped to the operator's configured mode ceiling (observe ≤ flag ≤ hold
 * ≤ block) so enabling the system never silently escalates beyond the chosen
 * posture. Every non-trivial decision is written to the immutable audit trail
 * with a system actor, the rule, the reason, and the mode.
 */
final class AntiAbuseDecision
{
    /** @param list<string> $reasons */
    public function __construct(
        public readonly string $action,   // allow | flag | hold | block
        public readonly string $natural,  // allow | flag | hold | block (pre-clamp)
        public readonly array $reasons,
        public readonly string $mode,     // observe | flag | hold | block
        public readonly string $rule = '',
    ) {
    }

    public static function allow(): self
    {
        return new self('allow', 'allow', [], 'observe');
    }

    public function blocks(): bool
    {
        return $this->action === 'block';
    }

    public function holds(): bool
    {
        return $this->action === 'hold';
    }

    public function flagged(): bool
    {
        return $this->action === 'flag';
    }

    /** True when any rule fired, regardless of whether the mode let it act. */
    public function triggered(): bool
    {
        return $this->natural !== 'allow';
    }

    public function reasonText(): string
    {
        return implode(', ', $this->reasons);
    }
}
