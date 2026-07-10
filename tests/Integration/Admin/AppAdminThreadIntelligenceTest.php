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

        $this->actingAs($this->admin);
        $page = $this->get('/admin/thread-intelligence');
        $this->assertStatus(200, $page);
        self::assertStringContainsString('Thread Intelligence', $page->body());
        self::assertStringContainsString('Both product flags are off', $page->body());
        self::assertStringContainsString('admin-safe-model', $page->body());
        self::assertStringContainsString('prompt-v1', $page->body());
        self::assertStringContainsString('Post #' . $seed['post_ids'][0], $page->body());
        self::assertStringNotContainsString($secret, $page->body());
        self::assertStringNotContainsString($requestFingerprint, $page->body());
        self::assertStringNotContainsString('generated summary text must stay out', $page->body());
        self::assertStringNotContainsString('Authorization', $page->body());
        self::assertStringNotContainsString('name="api_key"', $page->body());

        $this->actingAs($this->makeUser(['username' => 'not-ti-admin']));
        $this->assertStatus(403, $this->get('/admin/thread-intelligence'));
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

        $features = $this->get('/admin/features');
        $this->assertStatus(200, $features);
        self::assertStringContainsString('href="/admin/thread-intelligence"', $features->body());
        self::assertGreaterThanOrEqual(2, substr_count($features->body(), '/admin/thread-intelligence'));

        $dashboard = $this->get('/admin');
        $this->assertStatus(200, $dashboard);
        self::assertStringContainsString('Thread Intelligence', $dashboard->body());
        self::assertStringContainsString('/admin/thread-intelligence', $dashboard->body());
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
