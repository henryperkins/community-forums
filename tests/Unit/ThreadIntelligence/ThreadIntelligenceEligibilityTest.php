<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\TestCase;

/**
 * Policy-level tests use the real test database so the post/checkpoint matrix
 * is exercised against the production SQL rather than a mock-only substitute.
 */
final class ThreadIntelligenceEligibilityTest extends TestCase
{
    private function now(string $time = '2026-07-10 12:00:00', string $timezone = 'UTC'): DateTimeImmutable
    {
        return new DateTimeImmutable($time, new DateTimeZone($timezone));
    }

    private function setFlags(bool $communityMemory = true, bool $automatedContext = true): void
    {
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => $communityMemory,
            'automated_context' => $automatedContext,
        ]);
    }

    /**
     * @return array{service:ThreadIntelligenceEligibility,settings:ThreadIntelligenceSettings,config:ThreadIntelligenceConfig}
     */
    private function eligibility(
        string $apiKey = 'sk-test-thread-intelligence',
        int $dailyCallLimit = 100,
        int $dailyInputTokenLimit = 1_000_000,
        int $maxInputTokens = 32_000,
    ): array {
        $config = ThreadIntelligenceConfig::fromArray([
            'api_key' => $apiKey,
            'daily_call_limit' => $dailyCallLimit,
            'daily_input_token_limit' => $dailyInputTokenLimit,
            'max_input_tokens' => $maxInputTokens,
        ]);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $service = new ThreadIntelligenceEligibility(
            $this->db,
            new FeatureFlags(new SettingRepository($this->db)),
            $config,
            $settings,
            new ThreadIntelligenceBudget($this->db, $config),
            $jobs,
        );

        return ['service' => $service, 'settings' => $settings, 'config' => $config];
    }

    /** @return array{thread_id:int,board_id:int,author_id:int,post_ids:list<int>} */
    private function seedThread(int $eligiblePosts = 8, string $visibility = 'public'): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['visibility' => 'public']);
        $thread = $this->makeThread($board, $author);
        $threadId = (int) $thread['thread_id'];
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$threadId],
        );
        $this->db->run(
            "UPDATE posts SET created_at = '2026-07-10 09:00:00' WHERE id = ?",
            [$opId],
        );

        $ids = [$opId];
        for ($i = 1; $i < $eligiblePosts; $i++) {
            $ids[] = $this->insertPost($threadId, (int) $author['id'], '2026-07-10 09:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00');
        }
        if ($visibility !== 'public') {
            $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', [$visibility, (int) $board['id']]);
        }

        return [
            'thread_id' => $threadId,
            'board_id' => (int) $board['id'],
            'author_id' => (int) $author['id'],
            'post_ids' => $ids,
        ];
    }

    private function insertPost(int $threadId, int $authorId, string $createdAt, bool $deleted = false, bool $pending = false): int
    {
        return $this->db->insert(
            'INSERT INTO posts
                (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
             VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?)',
            [$threadId, $authorId, 'Eligible public post', '<p>Eligible public post</p>', $deleted ? 1 : 0, $pending ? 1 : 0, $createdAt],
        );
    }

    /** @param array<string,mixed> $changes @return array<string,mixed> */
    private function job(array $seed, array $changes = []): array
    {
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $jobs->upsertStale($seed['thread_id'], 'post_created', null, $this->now()->modify('-1 minute'));
        if ($changes !== []) {
            $sets = [];
            $params = [];
            foreach ($changes as $column => $value) {
                $sets[] = '`' . str_replace('`', '', $column) . '` = ?';
                $params[] = $value;
            }
            $params[] = $seed['thread_id'];
            $this->db->run(
                'UPDATE thread_intelligence_jobs SET ' . implode(', ', $sets) . ' WHERE thread_id = ?',
                $params,
            );
        }
        return $jobs->find($seed['thread_id']);
    }

    public function test_both_dark_flags_fail_independently_with_exact_codes(): void
    {
        $seed = $this->seedThread();

        $this->setFlags(false, true);
        $communityOff = $this->eligibility()['service']->forEnqueue($seed['thread_id'], $this->now());
        self::assertFalse($communityOff->eligible);
        self::assertSame('community_memory_disabled', $communityOff->code);
        self::assertSame('Thread memory is disabled', $communityOff->message);

        $this->setFlags(true, false);
        $contextOff = $this->eligibility()['service']->forEnqueue($seed['thread_id'], $this->now());
        self::assertFalse($contextOff->eligible);
        self::assertSame('automated_context_disabled', $contextOff->code);
        self::assertSame('Automatic context is disabled', $contextOff->message);
    }

    public function test_missing_nonpublic_deleted_and_pending_threads_fail_closed(): void
    {
        $this->setFlags();
        $service = $this->eligibility()['service'];

        $missing = $service->forEnqueue(9_999_999, $this->now());
        self::assertSame('thread_not_found', $missing->code);
        self::assertSame('Refresh is available only for eligible public threads', $missing->message);

        foreach (['private', 'hidden'] as $visibility) {
            $seed = $this->seedThread(8, $visibility);
            self::assertSame('board_not_public', $service->forEnqueue($seed['thread_id'], $this->now())->code, $visibility);
        }

        $deleted = $this->seedThread();
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        self::assertSame('thread_deleted', $service->forEnqueue($deleted['thread_id'], $this->now())->code);

        $pending = $this->seedThread();
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pending['thread_id']]);
        self::assertSame('thread_pending', $service->forEnqueue($pending['thread_id'], $this->now())->code);
    }

    public function test_first_generation_counts_only_eight_eligible_posts_including_the_opener(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(7);
        $service = $this->eligibility()['service'];

        $this->insertPost($seed['thread_id'], $seed['author_id'], '2026-07-10 09:20:00', deleted: true);
        $this->insertPost($seed['thread_id'], $seed['author_id'], '2026-07-10 09:21:00', pending: true);

        $below = $service->forEnqueue($seed['thread_id'], $this->now());
        self::assertFalse($below->eligible);
        self::assertSame('initial_post_threshold', $below->code);

        $seed['post_ids'][] = $this->insertPost($seed['thread_id'], $seed['author_id'], '2026-07-10 09:22:00');
        $exactlyEight = $service->forEnqueue($seed['thread_id'], $this->now());
        self::assertTrue($exactlyEight->eligible);
        self::assertSame('eligible', $exactlyEight->code);
        self::assertSame('Eligible', $exactlyEight->message);
        self::assertNull($exactlyEight->nextEligibleAt);
    }

    public function test_incremental_generation_requires_five_eligible_ids_strictly_after_the_checkpoint(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(12);
        $checkpoint = $seed['post_ids'][7];
        $job = $this->job($seed, ['last_processed_post_id' => $checkpoint]);
        $service = $this->eligibility()['service'];

        self::assertSame('post_delta_threshold', $service->forGeneration($job, $this->now())->code, 'only four IDs follow the checkpoint');

        $this->insertPost($seed['thread_id'], $seed['author_id'], '2026-07-10 09:30:00', pending: true);
        self::assertSame('post_delta_threshold', $service->forGeneration($job, $this->now())->code, 'pending posts do not satisfy the delta');

        $this->insertPost($seed['thread_id'], $seed['author_id'], '2026-07-10 09:31:00');
        self::assertTrue($service->forGeneration($job, $this->now())->eligible);
    }

    public function test_reconciliation_bypasses_incremental_delta_but_not_the_initial_eight_post_floor(): void
    {
        $this->setFlags();
        $incremental = $this->seedThread(8);
        $job = $this->job($incremental, [
            'last_processed_post_id' => $incremental['post_ids'][7],
            'reconcile_required' => 1,
        ]);
        self::assertTrue($this->eligibility()['service']->forGeneration($job, $this->now())->eligible);

        $initial = $this->seedThread(7);
        $initialJob = $this->job($initial, ['reconcile_required' => 1]);
        self::assertSame(
            'initial_post_threshold',
            $this->eligibility()['service']->forGeneration($initialJob, $this->now())->code,
        );
    }

    public function test_generation_observes_the_job_due_at_quiet_window_and_exact_boundary(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $due = '2026-07-10 12:15:00';
        $job = $this->job($seed, ['due_at' => $due]);
        $service = $this->eligibility()['service'];

        $quiet = $service->forGeneration($job, $this->now('2026-07-10 12:14:59'));
        self::assertSame('quiet_window', $quiet->code);
        self::assertSame('2026-07-10T12:15:00+00:00', $quiet->nextEligibleAt?->format(DATE_ATOM));
        self::assertTrue($service->forGeneration($job, $this->now('2026-07-10 12:15:00'))->eligible);
    }

    public function test_hourly_limit_uses_only_last_successful_publication_not_attempts(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(13);
        $job = $this->job($seed, [
            'last_processed_post_id' => $seed['post_ids'][7],
            'last_generated_at' => '2026-07-10 11:30:00',
            'attempt_count' => 4,
        ]);
        $service = $this->eligibility()['service'];

        self::assertSame('hourly_limit', $service->forGeneration($job, $this->now())->code);
        self::assertTrue($service->forGeneration($job, $this->now('2026-07-10 12:30:00'))->eligible);

        $this->db->run('UPDATE thread_intelligence_jobs SET last_generated_at = NULL WHERE thread_id = ?', [$seed['thread_id']]);
        $fresh = (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id']);
        self::assertTrue($service->forGeneration($fresh, $this->now())->eligible, 'attempt_count alone is not a publication cadence signal');
    }

    public function test_global_and_per_thread_pauses_fail_closed(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $job = $this->job($seed);

        $bundle = $this->eligibility();
        $bundle['settings']->setGenerationPaused(true);
        $paused = $bundle['service']->forGeneration($job, $this->now());
        self::assertSame('generation_paused', $paused->code);
        self::assertSame('Automatic refresh is paused by the site', $paused->message);

        $bundle['settings']->setGenerationPaused(false);
        $this->db->run('UPDATE thread_intelligence_jobs SET automation_paused = 1 WHERE thread_id = ?', [$seed['thread_id']]);
        $perThread = $bundle['service']->forGeneration($job, $this->now());
        self::assertSame('automation_paused', $perThread->code, 'current DB pause state is re-read instead of trusting the claimed array');
        self::assertSame('Automatic refresh is paused for this thread', $perThread->message);

        $this->db->run('UPDATE thread_intelligence_jobs SET automation_paused = 0 WHERE thread_id = ?', [$seed['thread_id']]);
        (new SettingRepository($this->db))->set(ThreadIntelligenceSettings::PAUSE_KEY, true);
        $corrupt = $bundle['service']->forGeneration($job, $this->now());
        self::assertSame('generation_pause_invalid', $corrupt->code);
        self::assertSame('Automatic refresh is paused while site settings are checked', $corrupt->message);
    }

    public function test_credentials_provider_latch_and_budget_return_nonmutating_deferrals(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $job = $this->job($seed, ['attempt_count' => 7]);

        $missing = $this->eligibility(apiKey: '')['service']->forGeneration($job, $this->now());
        self::assertSame('credentials_missing', $missing->code);
        self::assertSame('Automatic refresh is unavailable until the provider is configured', $missing->message);

        $latchedBundle = $this->eligibility();
        $latchedBundle['settings']->blockProvider('authentication', $this->now());
        $latched = $latchedBundle['service']->forGeneration($job, $this->now());
        self::assertSame('provider_blocked', $latched->code);
        self::assertSame('Automatic refresh is paused while the provider configuration is checked', $latched->message);

        $latchedBundle['settings']->clearProviderBlock();
        (new SettingRepository($this->db))->set(ThreadIntelligenceSettings::PROVIDER_HEALTH_KEY, ['invalid' => true]);
        self::assertSame('provider_health_invalid', $latchedBundle['service']->forGeneration($job, $this->now())->code);

        $this->db->run('DELETE FROM settings WHERE `key` IN (?, ?)', [
            ThreadIntelligenceSettings::PROVIDER_HEALTH_KEY,
            ThreadIntelligenceBudget::KEY,
        ]);
        (new SettingRepository($this->db))->set(ThreadIntelligenceBudget::KEY, [
            'date' => '2026-07-10',
            'reserved_calls' => 0,
            'used_calls' => 1,
            'reserved_input_tokens' => 0,
            'used_input_tokens' => 0,
        ]);
        $budget = $this->eligibility(dailyCallLimit: 1, dailyInputTokenLimit: 1_000, maxInputTokens: 1_000)['service']
            ->forGeneration($job, $this->now());
        self::assertSame('budget_exhausted', $budget->code);
        self::assertSame('Daily refresh capacity has been reached', $budget->message);
        self::assertSame('2026-07-11T00:00:00+00:00', $budget->nextEligibleAt?->format(DATE_ATOM));

        (new SettingRepository($this->db))->set(ThreadIntelligenceBudget::KEY, [
            'date' => '2026-07-10',
            'reserved_calls' => 'corrupt',
            'used_calls' => 0,
            'reserved_input_tokens' => 0,
            'used_input_tokens' => 0,
        ]);
        $invalidBudget = $this->eligibility()['service']->forGeneration($job, $this->now());
        self::assertSame('budget_invalid', $invalidBudget->code);
        self::assertSame('Automatic refresh is paused while the site budget is checked', $invalidBudget->message);

        self::assertSame(
            7,
            (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['attempt_count'],
            'eligibility deferrals never consume a job attempt',
        );
    }

    public function test_explicit_refresh_bypasses_only_incremental_delta_and_quiet_time(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(8);
        $job = $this->job($seed, [
            'last_processed_post_id' => $seed['post_ids'][7],
            'due_at' => '2026-07-10 12:15:00',
        ]);
        $service = $this->eligibility()['service'];

        self::assertSame('post_delta_threshold', $service->forGeneration($job, $this->now())->code);
        self::assertTrue($service->forExplicitRefresh($seed['thread_id'], $this->now())->eligible);

        $belowInitial = $this->seedThread(7);
        self::assertSame('initial_post_threshold', $service->forExplicitRefresh($belowInitial['thread_id'], $this->now())->code);

        $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', ['private', $seed['board_id']]);
        self::assertSame('board_not_public', $service->forExplicitRefresh($seed['thread_id'], $this->now())->code);
    }

    public function test_explicit_refresh_does_not_bypass_flags_pauses_credentials_latch_or_budget(): void
    {
        $this->setFlags();
        $seed = $this->seedThread();
        $this->job($seed, [
            'last_processed_post_id' => $seed['post_ids'][7],
            'due_at' => '2026-07-10 12:15:00',
        ]);
        $bundle = $this->eligibility();

        $bundle['settings']->setGenerationPaused(true);
        self::assertSame('generation_paused', $bundle['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);
        $bundle['settings']->setGenerationPaused(false);

        $this->db->run('UPDATE thread_intelligence_jobs SET automation_paused = 1 WHERE thread_id = ?', [$seed['thread_id']]);
        self::assertSame('automation_paused', $bundle['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);
        $this->db->run('UPDATE thread_intelligence_jobs SET automation_paused = 0 WHERE thread_id = ?', [$seed['thread_id']]);

        self::assertSame('credentials_missing', $this->eligibility(apiKey: '')['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);

        $bundle['settings']->blockProvider('authentication', $this->now());
        self::assertSame('provider_blocked', $bundle['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);
        $bundle['settings']->clearProviderBlock();

        (new SettingRepository($this->db))->set(ThreadIntelligenceBudget::KEY, [
            'date' => '2026-07-10',
            'reserved_calls' => 0,
            'used_calls' => 1,
            'reserved_input_tokens' => 0,
            'used_input_tokens' => 0,
        ]);
        $rawBudget = $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [ThreadIntelligenceBudget::KEY]);
        $budgetService = $this->eligibility(dailyCallLimit: 1, dailyInputTokenLimit: 1_000, maxInputTokens: 1_000)['service'];
        self::assertSame('budget_exhausted', $budgetService->forExplicitRefresh($seed['thread_id'], $this->now())->code);
        self::assertSame(
            $rawBudget,
            $this->db->fetchValue('SELECT `value` FROM settings WHERE `key` = ?', [ThreadIntelligenceBudget::KEY]),
            'eligibility reads never reserve or otherwise mutate budget counters',
        );

        $this->setFlags(false, true);
        self::assertSame('community_memory_disabled', $this->eligibility()['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);
        $this->setFlags(true, false);
        self::assertSame('automated_context_disabled', $this->eligibility()['service']->forExplicitRefresh($seed['thread_id'], $this->now())->code);

        self::assertSame(
            0,
            (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['attempt_count'],
        );
    }

    public function test_explicit_hourly_feedback_uses_absolute_local_time_and_exposes_the_utc_instant(): void
    {
        $this->setFlags();
        $seed = $this->seedThread(13);
        $this->job($seed, [
            'last_processed_post_id' => $seed['post_ids'][7],
            'last_generated_at' => '2026-07-10 12:30:00',
        ]);

        $result = $this->eligibility()['service']->forExplicitRefresh(
            $seed['thread_id'],
            $this->now('2026-07-10 09:00:00', 'America/New_York'),
        );

        self::assertFalse($result->eligible);
        self::assertSame('hourly_limit', $result->code);
        self::assertSame('Refresh available after 2026-07-10 09:30:00 EDT', $result->message);
        self::assertSame('UTC', $result->nextEligibleAt?->getTimezone()->getName());
        self::assertSame('2026-07-10T13:30:00+00:00', $result->nextEligibleAt?->format(DATE_ATOM));
    }
}
