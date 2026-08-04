<?php

declare(strict_types=1);

namespace Tests\Integration\Admin;

use App\Core\App;
use App\Core\Config;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\TestCase;

final class AppAdminThreadIntelligenceTest extends TestCase
{
    /** @var array<string,mixed> */
    private array $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->makeAdmin(['username' => 'thread-intelligence-admin']);
    }

    public function test_dashboard_is_admin_only_readable_with_flags_off_and_never_discloses_credentials_or_evidence_text(): void
    {
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => false,
            'automated_context' => false,
        ]);
        $secret = 'sk-admin-surface-secret-never-render';
        $this->rebuildApp($secret);
        $seed = $this->seedThread(8);
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, published_at, created_at)
             VALUES (?, 'ai', 'published', ?, ?, 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], 'generated summary text must stay out', '<p>generated summary text must stay out</p>'],
        );
        $requestFingerprint = hash('sha256', 'request-fingerprint-never-render');
        $this->db->insert(
            "INSERT INTO thread_intelligence_generations
                (thread_id, trigger_code, status, published_summary_id, source_post_ids, candidate_thread_ids,
                 request_fingerprint, model, reasoning_effort, prompt_version, failure_code, failure_message,
                 input_tokens, output_tokens, requested_at, completed_at, published_at)
             VALUES (?, 'post_created', 'published', ?, ?, '[]', ?, 'admin-safe-model', 'low', 'prompt-v1',
                     NULL, NULL, 50, 25, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id'], $summaryId, json_encode([$seed['post_ids'][0]], JSON_THROW_ON_ERROR), $requestFingerprint],
        );
        $outcomeRegisters = [
            'requested' => 'neutral',
            'succeeded' => 'done',
            'published' => 'done',
            'retry' => 'neutral',
            'failed' => 'attention',
            'dead' => 'attention',
            'review_required' => 'attention',
            'rejected' => 'attention',
            'stale' => 'neutral',
        ];
        foreach (array_keys($outcomeRegisters) as $status) {
            if ($status === 'published') {
                continue;
            }
            $this->db->insert(
                "INSERT INTO thread_intelligence_generations
                    (thread_id, trigger_code, status, source_post_ids, candidate_thread_ids,
                     model, reasoning_effort, prompt_version, input_tokens, requested_at, completed_at)
                 VALUES (?, 'post_created', ?, '[]', '[]', 'historical-model', 'low', 'historical-prompt',
                         1200, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
                [$seed['thread_id'], $status],
            );
        }

        $this->actingAs($this->admin);
        $page = $this->get('/admin/thread-intelligence');
        $this->assertStatus(200, $page);
        $body = $page->body();
        self::assertStringContainsString('Thread Intelligence', $body);
        self::assertStringContainsString('Both product flags are off', $body);
        self::assertStringContainsString(
            '<span class="admin-tab is-disabled" aria-disabled="true" data-destination="/admin/thread-intelligence">',
            $body,
        );
        self::assertStringNotContainsString(
            '<span class="admin-tab is-active" aria-current="page">Thread Intelligence</span>',
            $body,
        );
        self::assertStringContainsString(
            'Automated context for long topics. Staff set the terms; the model proposes and local validation decides. Everything it writes is evidenced below, and the egress brake is one button away.',
            $body,
        );
        $warningPosition = strpos($body, 'class="card ti-attention"');
        $introPosition = strpos($body, 'class="ti-intro"');
        self::assertNotFalse($warningPosition);
        self::assertNotFalse($introPosition);
        self::assertLessThan($introPosition, $warningPosition, 'Needs attention must remain first in the pane.');

        foreach ([
            'product-flags' => 'Product flags',
            'provider' => 'Provider',
            'heartbeat' => 'Heartbeat',
            'generation' => 'Generation',
        ] as $statusKey => $label) {
            self::assertMatchesRegularExpression(
                '~<div[^>]*class="[^"]*ti-status-card[^"]*"[^>]*data-ti-status="' . preg_quote($statusKey, '~') . '"[^>]*>.*?'
                . preg_quote($label, '~') . '.*?</div>~s',
                $body,
            );
        }
        self::assertMatchesRegularExpression(
            '~<form[^>]*method="post"[^>]*action="/admin/thread-intelligence/generation/(?:pause|resume)"[^>]*>.*?name="_token"~s',
            $body,
        );
        self::assertMatchesRegularExpression(
            '~<form[^>]*method="post"[^>]*action="/admin/thread-intelligence/provider/retry"[^>]*>.*?name="_token"~s',
            $body,
        );

        self::assertMatchesRegularExpression('~<section[^>]*class="[^"]*ti-queue-section[^"]*"[^>]*>.*?<h2[^>]*>Queue states</h2>~s', $body);
        self::assertSame(1, preg_match('~<div class="ti-queue-grid"[^>]*>(?<queue>.*?)</div>\s*</section>~s', $body, $queueMatch));
        $queue = $queueMatch['queue'];
        $cursor = -1;
        foreach (['Idle', 'Queued', 'Running', 'Retry', 'Dead', 'Review required'] as $label) {
            $next = strpos($queue, '>' . $label . '<');
            self::assertNotFalse($next, 'Missing canonical queue state ' . $label);
            self::assertGreaterThan($cursor, $next, 'Queue-state order drifted at ' . $label);
            $cursor = $next;
        }
        self::assertMatchesRegularExpression(
            '~<strong class="ti-queue-count">0</strong>\s*<span class="ti-queue-label">Idle</span>\s*<span class="ti-queue-unit">threads</span>~s',
            $queue,
        );

        self::assertStringContainsString('class="ti-contract-grid"', $body);
        self::assertStringContainsString('class="card ti-contract-card"', $body);
        self::assertStringContainsString('class="card ti-evidence-card"', $body);
        self::assertStringContainsString('class="ti-evidence-count">9 runs</span>', $body);
        self::assertMatchesRegularExpression(
            '~<thead>\s*<tr>\s*<th scope="col">ID</th>\s*<th scope="col">When</th>\s*<th scope="col">Topic</th>\s*<th scope="col">Outcome</th>\s*<th scope="col"[^>]*>Input tokens</th>\s*<th scope="col">Contract</th>\s*<th scope="col">Evidence</th>\s*<th scope="col">Actions</th>\s*</tr>\s*</thead>~s',
            $body,
        );
        self::assertMatchesRegularExpression(
            '~<td class="ti-evidence-time"><time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z" title="[^"]+ UTC">\d{1,2} [A-Z][a-z]{2} \d{2}:\d{2}</time></td>~',
            $body,
        );
        self::assertMatchesRegularExpression('~<p class="ti-budget-reset">Resets \d{4}-\d{2}-\d{2} \d{2}:\d{2} UTC</p>~', $body);
        foreach ($outcomeRegisters as $status => $register) {
            self::assertMatchesRegularExpression(
                '~data-ti-outcome="' . preg_quote($register, '~') . '"\s+data-ti-generation-status="' . preg_quote($status, '~') . '"~',
                $body,
            );
        }

        self::assertStringContainsString('admin-safe-model', $body);
        self::assertStringContainsString('prompt-v1', $body);
        self::assertStringContainsString('Post #' . $seed['post_ids'][0], $body);
        self::assertStringNotContainsString($secret, $body);
        self::assertStringNotContainsString($requestFingerprint, $body);
        self::assertStringNotContainsString('generated summary text must stay out', $body);
        self::assertStringNotContainsString('Authorization', $body);
        self::assertStringNotContainsString('name="api_key"', $body);
        self::assertStringNotContainsString('Failed only', $body);
        self::assertStringNotContainsString('>Digest<', $body);
        self::assertStringNotContainsString('claude-sonnet-4-6', $body);
        self::assertDoesNotMatchRegularExpression('~\bago\b~i', $body);

        $this->actingAs($this->makeUser(['username' => 'not-ti-admin']));
        $this->assertStatus(403, $this->get('/admin/thread-intelligence'));
    }

    public function test_dashboard_humanizes_all_heartbeat_classifications_and_uses_absolute_times(): void
    {
        $settings = new SettingRepository($this->db);
        $settings->set('features', ['community_memory' => true, 'automated_context' => true]);
        $this->rebuildApp('sk-heartbeat-present');
        $this->actingAs($this->admin);
        $now = time();
        $healthyCompleted = $now - 60;

        $cases = [
            'never_run' => [null, 'Never run', 'No completed run'],
            'invalid' => [['invalid' => true], 'Invalid', 'Heartbeat record is invalid'],
            'running' => [$this->heartbeatRecord('running', $now - 30, null), 'Running', 'Started ' . gmdate('M j, Y \\a\\t H:i', $now - 30) . ' UTC'],
            'interrupted' => [$this->heartbeatRecord('running', $now - 1200, null), 'Interrupted', 'Started ' . gmdate('M j, Y \\a\\t H:i', $now - 1200) . ' UTC'],
            'attention' => [$this->heartbeatRecord('error', $now - 90, $now - 30), 'Needs attention', 'Last run ' . gmdate('M j, Y \\a\\t H:i', $now - 30) . ' UTC'],
            'stale' => [$this->heartbeatRecord('ok', $now - 900, $now - 600), 'Stale', 'Last run ' . gmdate('M j, Y \\a\\t H:i', $now - 600) . ' UTC'],
            'healthy' => [$this->heartbeatRecord('ok', $now - 90, $healthyCompleted), 'Healthy', 'Last run ' . gmdate('M j, Y \\a\\t H:i', $healthyCompleted) . ' UTC'],
        ];

        foreach ($cases as $classification => [$record, $label, $detail]) {
            if ($record === null) {
                $this->db->run('DELETE FROM settings WHERE `key` = ?', [ThreadIntelligenceSettings::HEARTBEAT_KEY]);
            } else {
                $settings->set(ThreadIntelligenceSettings::HEARTBEAT_KEY, $record);
            }
            $body = $this->get('/admin/thread-intelligence')->body();
            self::assertSame(
                1,
                preg_match('~<div[^>]*data-ti-status="heartbeat"[^>]*>(?<card>.*?)</div>~s', $body, $match),
                'Missing heartbeat card for ' . $classification,
            );
            self::assertStringContainsString('<strong class="ti-status-value">' . $label . '</strong>', $match['card']);
            self::assertStringContainsString('<span class="ti-status-detail">' . $detail . '</span>', $match['card']);
            self::assertDoesNotMatchRegularExpression('~\bago\b~i', $match['card']);
        }
    }

    public function test_dashboard_empty_evidence_uses_the_truthful_unfiltered_empty_state(): void
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => true, 'automated_context' => true]);
        $this->actingAs($this->admin);

        $body = $this->get('/admin/thread-intelligence')->body();

        self::assertMatchesRegularExpression(
            '~<div class="ti-evidence-empty state-empty">\s*<h3>No generation attempts</h3>\s*<p>No generation attempts have been recorded\.</p>\s*</div>~s',
            $body,
        );
        self::assertStringContainsString('class="ti-evidence-count">0 runs</span>', $body);
        self::assertStringNotContainsString('Nothing has failed', $body);
        self::assertStringNotContainsString('Failed only', $body);
    }

    public function test_pause_provider_retry_and_thread_recovery_are_csrf_protected_persistent_and_audited(): void
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => true, 'automated_context' => true]);
        $this->rebuildApp('sk-admin-actions');
        $settings = $this->settings('sk-admin-actions');
        $settings->blockProvider('invalid_model', new DateTimeImmutable('now', new DateTimeZone('UTC')));
        $seed = $this->seedThread(8);
        $this->actingAs($this->admin);

        $this->assertStatus(403, $this->post('/admin/thread-intelligence/generation/pause', [], withToken: false));
        self::assertFalse((new SettingRepository($this->db))->has(ThreadIntelligenceSettings::PAUSE_KEY));

        $this->assertRedirect($this->post('/admin/thread-intelligence/generation/pause'), '/admin/thread-intelligence');
        self::assertSame('1', (new SettingRepository($this->db))->get(ThreadIntelligenceSettings::PAUSE_KEY));
        $this->assertRedirect($this->post('/admin/thread-intelligence/generation/resume'), '/admin/thread-intelligence');
        self::assertSame('0', (new SettingRepository($this->db))->get(ThreadIntelligenceSettings::PAUSE_KEY));

        $this->assertRedirect($this->post('/admin/thread-intelligence/provider/retry'), '/admin/thread-intelligence');
        self::assertFalse($this->settings('sk-admin-actions')->providerHealth()['blocked']);

        $this->assertRedirect($this->post('/admin/thread-intelligence/threads/' . $seed['thread_id'] . '/retry'), '/admin/thread-intelligence');
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_CURATOR_REFRESH, (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['trigger_code']);
        $this->assertRedirect($this->post('/admin/thread-intelligence/threads/' . $seed['thread_id'] . '/reconcile'), '/admin/thread-intelligence');
        self::assertSame(1, (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['reconcile_required']);

        $this->assertRedirect($this->post('/admin/thread-intelligence/threads/' . $seed['thread_id'] . '/pause'), '/admin/thread-intelligence');
        self::assertSame(1, (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['automation_paused']);
        $this->assertRedirect($this->post('/admin/thread-intelligence/threads/' . $seed['thread_id'] . '/resume'), '/admin/thread-intelligence');
        self::assertSame(0, (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['automation_paused']);

        self::assertSame(7, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log
             WHERE actor_id = ? AND action LIKE 'thread_intelligence_%'",
            [(int) $this->admin['id']],
        ));
        self::assertSame(0, (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM moderation_log
             WHERE actor_id = ? AND (before_json LIKE '%sk-admin-actions%' OR after_json LIKE '%sk-admin-actions%')",
            [(int) $this->admin['id']],
        ));
    }

    public function test_dashboard_classifies_worker_attention_and_queue_warnings(): void
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => true, 'automated_context' => true]);
        $this->rebuildApp('');
        (new SettingRepository($this->db))->set(ThreadIntelligenceSettings::HEARTBEAT_KEY, [
            'run_id' => 'stale-run',
            'status' => 'running',
            'worker_label' => 'test-worker',
            'started_at' => gmdate('Y-m-d\TH:i:s\Z', time() - 1200),
            'completed_at' => null,
            'processed' => 0,
            'succeeded' => 0,
            'failed' => 0,
        ]);
        $seed = $this->seedThread(8);
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id, state, trigger_code, due_at, activity_version, created_at, updated_at)
             VALUES (?, 'review_required', 'reconcile', NULL, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id']],
        );

        $this->actingAs($this->admin);
        $page = $this->get('/admin/thread-intelligence');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('Provider credential is missing', $page->body());
        self::assertStringContainsString('Worker appears interrupted', $page->body());
        self::assertStringContainsString('1 thread requires review', $page->body());
        self::assertStringContainsString('interrupted', $page->body());
    }

    public function test_navigation_feature_rows_and_main_dashboard_link_to_thread_intelligence(): void
    {
        (new SettingRepository($this->db))->set('features', ['community_memory' => false, 'automated_context' => true]);
        $this->actingAs($this->admin);

        // The flag rows link out to the console they govern. Under ADR 0024 the
        // nav no longer also links it from here — Thread Intelligence is a tab of
        // the Settings area, and /admin/features sits in Features — so the row
        // link is the only occurrence and is the one that matters.
        $features = $this->get('/admin/features');
        $this->assertStatus(200, $features);
        self::assertStringContainsString('href="/admin/thread-intelligence"', $features->body());

        // …and it is reachable in the Settings area's own tab strip.
        $settings = $this->get('/admin/settings');
        $this->assertStatus(200, $settings);
        self::assertStringContainsString('href="/admin/thread-intelligence"', $settings->body());

        $dashboard = $this->get('/admin');
        $this->assertStatus(200, $dashboard);
        self::assertStringContainsString('Thread Intelligence', $dashboard->body());
        self::assertStringContainsString('/admin/thread-intelligence', $dashboard->body());
        // Grammar (round-2 audit finding 10): a single warning must read
        // "warning needs", never "1 ... warning need operator review."
        self::assertStringNotContainsString('warning need ', $dashboard->body());
    }

    private function rebuildApp(string $apiKey): void
    {
        $items = $this->config->all();
        $items['thread_intelligence']['api_key'] = $apiKey;
        $this->config = new Config($items);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    private function settings(string $apiKey): ThreadIntelligenceSettings
    {
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        return new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
    }

    /** @return array{run_id:string,status:string,worker_label:string,started_at:string,completed_at:?string,processed:int,succeeded:int,failed:int} */
    private function heartbeatRecord(string $status, int $startedAt, ?int $completedAt): array
    {
        return [
            'run_id' => 'presentation-contract-run',
            'status' => $status,
            'worker_label' => 'presentation-contract-worker',
            'started_at' => gmdate('Y-m-d\TH:i:s\Z', $startedAt),
            'completed_at' => $completedAt === null ? null : gmdate('Y-m-d\TH:i:s\Z', $completedAt),
            'processed' => 4,
            'succeeded' => 3,
            'failed' => 1,
        ];
    }

    /** @return array{thread_id:int,slug:string,post_ids:list<int>} */
    private function seedThread(int $postCount): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Admin evidence topic');
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']])];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Admin evidence ' . $i]);
        }
        return ['thread_id' => (int) $thread['thread_id'], 'slug' => $thread['slug'], 'post_ids' => $postIds];
    }
}
