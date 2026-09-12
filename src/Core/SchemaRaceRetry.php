<?php

declare(strict_types=1);

namespace App\Core;

use Closure;
use PDOException;

/**
 * Retry policy for the one failure class a migration can hit on Vitess that a
 * plain MySQL never produces: the schema-propagation race
 * (docs/runbooks/deployment-cloudflare.md §3).
 *
 * vtgate applies DDL on the tablet and refreshes its own schema cache
 * asynchronously (~2s measured on PlanetScale), so a DML statement that follows
 * an ALTER in the same up() can be rejected at *planning* time with
 * "column 'x' not found" even though MySQL already has the column. A planning
 * failure never executes anything, so re-sending the identical statement after
 * a short pause is safe. The ALTER itself is never the statement that fails
 * this way, so nothing non-idempotent is ever replayed.
 *
 * The policy is inert (a single attempt) unless the server identifies itself as
 * Vitess: on MariaDB/MySQL an unknown-column error is a genuine bug and should
 * fail immediately rather than after a retry window.
 */
final class SchemaRaceRetry
{
    /** vtgate wordings seen for a stale schema cache, oldest first. */
    private const PATTERNS = [
        '/\bcolumn \S+ not found\b/i',   // planner, error 1105 (the form the runbook records)
        '/\bsymbol \S+ not found\b/i',   // semantic analyser (VT03019)
        '/\btable \S+ not found\b/i',
        '/\bUnknown column\b/i',         // MySQL-compatible wording from newer vtgate releases
    ];

    private Closure $sleep;
    private Closure $matches;

    /**
     * @param callable(int):void|null            $sleep   receives the pause in milliseconds (injectable for tests)
     * @param callable(PDOException):bool|null   $matches classifier; defaults to isSchemaRace()
     */
    public function __construct(
        private int $maxAttempts = 60,
        private int $delayMs = 250,
        ?callable $sleep = null,
        ?callable $matches = null,
    ) {
        $this->maxAttempts = max(1, $this->maxAttempts);
        $this->sleep = $sleep !== null
            ? Closure::fromCallable($sleep)
            : static function (int $ms): void {
                usleep($ms * 1000);
            };
        $this->matches = $matches !== null
            ? Closure::fromCallable($matches)
            : self::isSchemaRace(...);
    }

    /** A policy that never retries. */
    public static function none(): self
    {
        return new self(1);
    }

    /** Active on Vitess (e.g. "8.4.11-Vitess"), inert on anything else. */
    public static function forServerVersion(string $version): self
    {
        return stripos($version, 'vitess') !== false ? new self() : self::none();
    }

    public static function isSchemaRace(PDOException $e): bool
    {
        $message = $e->getMessage();
        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $message) === 1) {
                return true;
            }
        }
        return false;
    }

    public function isActive(): bool
    {
        return $this->maxAttempts > 1;
    }

    public function maxAttempts(): int
    {
        return $this->maxAttempts;
    }

    /**
     * Run $operation, re-running it after a pause while it fails with a
     * schema-race error and attempts remain. Any other failure, and the last
     * failure once attempts are exhausted, propagate unchanged.
     *
     * @template T
     * @param callable():T $operation
     * @return T
     */
    public function run(callable $operation): mixed
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return $operation();
            } catch (PDOException $e) {
                if ($attempt >= $this->maxAttempts || !($this->matches)($e)) {
                    throw $e;
                }
                ($this->sleep)($this->delayMs);
            }
        }
    }
}
