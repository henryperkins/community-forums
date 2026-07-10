<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;

/** Redacted administrator read models and audited recovery mutations. */
final class ThreadIntelligenceAdminService
{
    public function __construct(
        private readonly Database $db,
        private readonly FeatureFlags $flags,
        private readonly ThreadIntelligenceSettings $settings,
        private readonly ThreadIntelligenceOperationsService $operations,
        private readonly ThreadIntelligenceQueue $queue,
        private readonly ThreadIntelligenceJobRepository $jobs,
        private readonly ThreadIntelligenceGenerationRepository $generations,
        private readonly ModerationLogRepository $audit,
    ) {
    }

    /** @return array<string,mixed> */
    public function dashboard(int $recentLimit = 50): array
    {
        $status = $this->operations->status();
        $status['provider_label'] = 'OpenAI';
        $status['flags_corrupt'] = $this->flags->overridesCorrupt();
        $status['warnings'] = $this->warnings($status);
        $status['recent_generations'] = array_map(
            fn (array $row): array => $this->safeGeneration($row),
            $this->generations->recent(max(1, min(50, $recentLimit))),
        );
        return $status;
    }

    /** @return array<string,mixed> */
    public function overview(): array
    {
        $dashboard = $this->dashboard(1);
        return [
            'active' => (bool) $dashboard['flags']['community_memory'] || (bool) $dashboard['flags']['automated_context'],
            'warning_count' => count($dashboard['warnings']),
            'warnings' => $dashboard['warnings'],
            'queue_attention' => (int) $dashboard['queue']['dead'] + (int) $dashboard['queue']['review_required'],
            'heartbeat' => (string) $dashboard['heartbeat']['classification'],
        ];
    }

    public function setGenerationPaused(User $admin, bool $paused): void
    {
        $this->assertAdmin($admin);
        $before = $this->settings->generationPause();
        $this->db->transaction(function () use ($admin, $paused, $before): void {
            $this->settings->setGenerationPaused($paused);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $paused ? 'thread_intelligence_generation_pause' : 'thread_intelligence_generation_resume',
                'target_type' => 'setting',
                'target_id' => 0,
                'before' => ['paused' => $before['paused'], 'corrupt' => $before['corrupt']],
                'after' => ['paused' => $paused],
            ]);
        });
    }

    public function retryProviderConfiguration(User $admin): void
    {
        $this->assertAdmin($admin);
        $before = $this->settings->providerHealth();
        $this->db->transaction(function () use ($admin, $before): void {
            $this->operations->clearProviderLatch();
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'thread_intelligence_provider_retry',
                'target_type' => 'setting',
                'target_id' => 0,
                'before' => ['blocked' => $before['blocked'], 'code' => $before['code'], 'corrupt' => $before['corrupt']],
                'after' => ['blocked' => false],
            ]);
        });
    }

    public function retryThread(User $admin, int $threadId): ThreadIntelligenceQueueResult
    {
        return $this->recoverThread($admin, $threadId, false);
    }

    public function reconcileThread(User $admin, int $threadId): ThreadIntelligenceQueueResult
    {
        return $this->recoverThread($admin, $threadId, true);
    }

    public function setThreadPaused(User $admin, int $threadId, bool $paused): void
    {
        $this->assertAdmin($admin);
        if ($this->db->fetchValue('SELECT 1 FROM threads WHERE id = ?', [$threadId]) === false) {
            throw new NotFoundException('Thread not found.');
        }
        $before = $this->jobs->find($threadId);
        $this->db->transaction(function () use ($admin, $threadId, $paused, $before): void {
            if ($paused) {
                $this->queue->setAutomationPaused($threadId, true, $admin->id());
            } else {
                $this->queue->resumeAndRequeue($threadId, $admin->id());
            }
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $paused ? 'thread_intelligence_thread_pause' : 'thread_intelligence_thread_resume',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'before' => ['automation_paused' => (int) ($before['automation_paused'] ?? 0) === 1],
                'after' => ['automation_paused' => $paused],
            ]);
        });
    }

    private function recoverThread(User $admin, int $threadId, bool $reconcile): ThreadIntelligenceQueueResult
    {
        $this->assertAdmin($admin);
        return $this->db->transaction(function () use ($admin, $threadId, $reconcile): ThreadIntelligenceQueueResult {
            $result = $reconcile ? $this->operations->reconcile($threadId) : $this->operations->retry($threadId);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $reconcile ? 'thread_intelligence_thread_reconcile' : 'thread_intelligence_thread_retry',
                'target_type' => 'thread',
                'target_id' => $threadId,
                'after' => ['queued' => $result->queued, 'code' => $result->code],
            ]);
            return $result;
        });
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
    }

    /** @param array<string,mixed> $status @return list<string> */
    private function warnings(array $status): array
    {
        $warnings = [];
        if (!$status['flags']['community_memory'] && !$status['flags']['automated_context']) {
            $warnings[] = 'Both product flags are off; generation remains dark.';
        }
        if ($status['flags_corrupt']) {
            $warnings[] = 'Feature flag configuration is invalid; code defaults are in effect.';
        }
        if (!$status['credential_ready']) {
            $warnings[] = 'Provider credential is missing.';
        }
        if ($status['pause']['corrupt']) {
            $warnings[] = 'The global generation pause value is invalid and fails paused.';
        }
        if ($status['provider']['corrupt']) {
            $warnings[] = 'Provider health state is invalid and fails blocked.';
        } elseif ($status['provider']['blocked']) {
            $warnings[] = 'Provider configuration is latched after ' . ($status['provider']['code'] ?? 'an operator-safe failure') . '.';
        }
        if ($status['budget']['corrupt']) {
            $warnings[] = 'Daily budget state is invalid and generation is paused.';
        }
        $heartbeat = (string) $status['heartbeat']['classification'];
        $warnings = match ($heartbeat) {
            'interrupted' => [...$warnings, 'Worker appears interrupted.'],
            'stale' => [...$warnings, 'Worker heartbeat is stale.'],
            'attention' => [...$warnings, 'Worker reported an error and needs attention.'],
            'invalid' => [...$warnings, 'Worker heartbeat state is invalid.'],
            default => $warnings,
        };
        if ((int) $status['queue']['dead'] > 0) {
            $warnings[] = (int) $status['queue']['dead'] . ' dead thread' . ((int) $status['queue']['dead'] === 1 ? '' : 's') . ' need recovery.';
        }
        if ((int) $status['queue']['review_required'] > 0) {
            $warnings[] = (int) $status['queue']['review_required'] . ' thread' . ((int) $status['queue']['review_required'] === 1 ? '' : 's') . ' requires review.';
        }
        foreach ($status['configuration_warnings'] as $warning) {
            $warnings[] = (string) $warning;
        }
        return array_values(array_unique($warnings));
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function safeGeneration(array $row): array
    {
        $sourceIds = is_array($row['source_post_ids'] ?? null) ? array_map('intval', $row['source_post_ids']) : [];
        $candidateIds = is_array($row['candidate_thread_ids'] ?? null) ? array_map('intval', $row['candidate_thread_ids']) : [];
        $job = $this->jobs->find((int) $row['thread_id']);
        return [
            'id' => (int) $row['id'],
            'thread_id' => (int) $row['thread_id'],
            'thread_link' => $this->publicThreadLink((int) $row['thread_id']),
            'trigger_code' => (string) $row['trigger_code'],
            'status' => (string) $row['status'],
            'retry_number' => (int) $row['retry_number'],
            'window_number' => (int) $row['window_number'],
            'baseline_summary_id' => $row['baseline_summary_id'] === null ? null : (int) $row['baseline_summary_id'],
            'published_summary_id' => $row['published_summary_id'] === null ? null : (int) $row['published_summary_id'],
            'model' => $row['model'] === null ? null : (string) $row['model'],
            'reasoning_effort' => $row['reasoning_effort'] === null ? null : (string) $row['reasoning_effort'],
            'prompt_version' => $row['prompt_version'] === null ? null : (string) $row['prompt_version'],
            'failure_code' => $row['failure_code'] === null ? null : (string) $row['failure_code'],
            'failure_message' => $row['failure_message'] === null ? null : (string) $row['failure_message'],
            'requested_at' => (string) $row['requested_at'],
            'completed_at' => $row['completed_at'] === null ? null : (string) $row['completed_at'],
            'job_state' => $job['state'] ?? null,
            'thread_paused' => (int) ($job['automation_paused'] ?? 0) === 1,
            'source_links' => $this->publicPostLinks($sourceIds),
            'candidate_links' => $this->publicThreadLinks($candidateIds),
            'usage' => [
                'input_count' => $row['input_tokens'] === null ? null : (int) $row['input_tokens'],
                'output_count' => $row['output_tokens'] === null ? null : (int) $row['output_tokens'],
                'reasoning_count' => $row['reasoning_tokens'] === null ? null : (int) $row['reasoning_tokens'],
                'cached_count' => $row['cached_tokens'] === null ? null : (int) $row['cached_tokens'],
            ],
        ];
    }

    /** @return array{title:string,url:string}|null */
    private function publicThreadLink(int $threadId): ?array
    {
        $row = $this->db->fetch(
            "SELECT t.id, t.slug, t.title FROM threads t JOIN boards b ON b.id = t.board_id
             WHERE t.id = ? AND t.is_deleted = 0 AND t.is_pending = 0 AND b.visibility = 'public'",
            [$threadId],
        );
        return $row === null ? null : [
            'title' => (string) $row['title'],
            'url' => '/t/' . (int) $row['id'] . '-' . (string) $row['slug'],
        ];
    }

    /** @param list<int> $threadIds @return list<array{id:int,title:string,url:string}> */
    private function publicThreadLinks(array $threadIds): array
    {
        $links = [];
        foreach (array_values(array_unique($threadIds)) as $threadId) {
            $link = $this->publicThreadLink($threadId);
            if ($link !== null) {
                $links[] = ['id' => $threadId] + $link;
            }
        }
        return $links;
    }

    /** @param list<int> $postIds @return list<array{id:int,url:string}> */
    private function publicPostLinks(array $postIds): array
    {
        $links = [];
        foreach (array_values(array_unique($postIds)) as $postId) {
            $row = $this->db->fetch(
                "SELECT p.id, t.id AS thread_id, t.slug
                 FROM posts p JOIN threads t ON t.id = p.thread_id JOIN boards b ON b.id = t.board_id
                 WHERE p.id = ? AND p.is_deleted = 0 AND p.is_pending = 0
                   AND t.is_deleted = 0 AND t.is_pending = 0 AND b.visibility = 'public'",
                [$postId],
            );
            if ($row !== null) {
                $links[] = [
                    'id' => (int) $row['id'],
                    'url' => '/t/' . (int) $row['thread_id'] . '-' . (string) $row['slug'] . '#p' . (int) $row['id'],
                ];
            }
        }
        return $links;
    }
}
