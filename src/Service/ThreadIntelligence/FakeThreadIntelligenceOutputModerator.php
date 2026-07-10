<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

/**
 * Deterministic test moderator: dequeues scripted safe/flagged/exception
 * outcomes; an empty queue means "safe" so moderation-agnostic tests need no
 * setup. Moderated texts are retained in process memory only.
 */
final class FakeThreadIntelligenceOutputModerator implements ThreadIntelligenceOutputModerator
{
    /** @var list<ThreadIntelligenceModerationResult|ThreadIntelligenceProviderException> */
    private array $queue = [];

    /** @var list<string> */
    private array $texts = [];

    public function queueSafe(): void
    {
        $this->queue[] = new ThreadIntelligenceModerationResult(false);
    }

    /** @param list<string> $categories */
    public function queueFlagged(array $categories = ['harassment']): void
    {
        $this->queue[] = new ThreadIntelligenceModerationResult(true, $categories);
    }

    public function queueException(ThreadIntelligenceProviderException $exception): void
    {
        $this->queue[] = $exception;
    }

    public function moderate(string $text): ThreadIntelligenceModerationResult
    {
        $this->texts[] = $text;

        if ($this->queue === []) {
            return new ThreadIntelligenceModerationResult(false);
        }
        $next = array_shift($this->queue);
        if ($next instanceof ThreadIntelligenceProviderException) {
            throw $next;
        }
        return $next;
    }

    /** @return list<string> every moderated text, in order (test memory only) */
    public function texts(): array
    {
        return $this->texts;
    }
}
