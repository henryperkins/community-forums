<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\App;
use App\Core\Config;
use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\CommunityMemoryService;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceViewService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use Tests\Support\TestCase;

final class ThreadIntelligenceSurfaceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => true,
            'automated_context' => true,
        ]);
        $this->makeAdmin(['username' => 'surface-site-admin']);
    }

    public function test_view_model_labels_lineage_masks_anonymous_sources_and_exposes_no_runtime_evidence(): void
    {
        $author = $this->makeUser(['username' => 'surface-author']);
        $admin = $this->makeAdmin(['username' => 'surface-curator']);
        $board = $this->makeBoard($this->makeCategory(), ['allow_anonymous' => 1]);
        $thread = $this->posting()->createThread($this->userEntity($author), [
            'board_id' => (int) $board['id'],
            'title' => 'AI lineage topic',
            'body' => 'Anonymous opening evidence',
            'is_anonymous' => '1',
        ]);
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        for ($i = 1; $i < 8; $i++) {
            $postIds[] = $this->posting()->reply($this->userEntity($author), $threadId, ['body' => 'Evidence ' . $i]);
        }
        [$summaryId] = $this->insertAiBrief($threadId, [$postIds[0], $postIds[1]], 'AI brief body');

        $model = $this->viewService()->forThread($threadId, null);
        self::assertSame('AI-generated living brief', $model['living_brief']['label']);
        self::assertSame('Updated automatically', $model['living_brief']['metadata']);
        self::assertSame(1, $model['living_brief']['version']);
        self::assertStringEndsWith('Z', $model['living_brief']['published_at_utc']);
        self::assertCount(2, $model['sources']);
        self::assertNull($model['sources'][0]['author_username']);
        self::assertArrayNotHasKey('model', $model);
        self::assertArrayNotHasKey('generation', $model);
        self::assertStringNotContainsString('token', json_encode($model, JSON_THROW_ON_ERROR));
        self::assertSame($summaryId, $model['living_brief']['id']);

        $this->memory()->publishSummary($this->userEntity($admin), $threadId, 'Curator edited brief', [$postIds[2]]);
        $edited = $this->viewService()->forThread($threadId, null);
        self::assertSame('AI-generated · curator edited', $edited['living_brief']['label']);
        self::assertStringContainsString('@surface-curator', $edited['living_brief']['metadata']);

        $manual = $this->seedThread(8, 'Manual-only topic');
        $this->memory()->publishSummary($this->userEntity($admin), $manual['thread_id'], 'Manual-only brief', [$manual['post_ids'][0]]);
        $manualModel = $this->viewService()->forThread($manual['thread_id'], null);
        self::assertSame('Curated summary', $manualModel['living_brief']['label']);
        self::assertStringContainsString('@surface-curator', $manualModel['living_brief']['metadata']);
    }

    public function test_ai_brief_and_overlays_fail_closed_while_deterministic_related_rows_stay_safe(): void
    {
        $seed = $this->seedThread(8, 'Fail closed topic');
        [$summaryId, $generationId] = $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Sensitive AI brief');
        $curated = $this->seedThread(1, 'Curated target');
        $selected = $this->seedThread(1, 'AI selected target');
        $deterministic = $this->seedThread(1, 'Tag fallback target');
        $deleted = $this->seedThread(1, 'Deleted target');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        $this->insertRelated($seed['thread_id'], $curated['thread_id'], 'curated', 'Curator reason');
        $this->insertRelated($seed['thread_id'], $selected['thread_id'], 'search', null, $generationId, 'AI selected reason', true);
        $this->insertRelated($seed['thread_id'], $deterministic['thread_id'], 'tag', null);
        $this->insertRelated($seed['thread_id'], $deleted['thread_id'], 'search', null);

        $model = $this->viewService()->forThread($seed['thread_id'], null);
        self::assertSame(['Curated target', 'AI selected target'], array_column($model['related'], 'title'));
        self::assertSame('Curator reason', $model['related'][0]['reason']);
        self::assertSame('AI selected reason', $model['related'][1]['reason']);

        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$seed['post_ids'][0]]);
        $suppressed = $this->viewService()->forThread($seed['thread_id'], $this->userEntity($this->makeAdmin()));
        self::assertNull($suppressed['living_brief']);
        self::assertSame([], $suppressed['related'], 'AI overlays disappear with the stale AI brief');
        self::assertSame(['Tag fallback target'], array_column($suppressed['fallback_related'], 'title'));
        self::assertSame('Shared topic tags', $suppressed['fallback_related'][0]['reason']);

        $this->db->run('UPDATE posts SET is_pending = 0 WHERE id = ?', [$seed['post_ids'][0]]);
        $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', ['private', (int) $seed['board']['id']]);
        $private = $this->viewService()->forThread($seed['thread_id'], $this->userEntity($this->makeAdmin()));
        self::assertNull($private['living_brief'], 'AI content is public-board-only even for administrators');
        self::assertSame($summaryId, (int) $this->db->fetchValue('SELECT id FROM thread_summaries WHERE id = ?', [$summaryId]));
    }

    public function test_thread_dom_order_has_one_memory_slot_no_empty_panel_and_public_disclosure(): void
    {
        $seed = $this->seedThread(8, 'Rendered living brief');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();
        $headerEnd = strpos($html, '</header>');
        $brief = strpos($html, 'data-living-brief');
        $postStream = strpos($html, 'class="post-stream"');
        self::assertNotFalse($headerEnd);
        self::assertNotFalse($brief);
        self::assertNotFalse($postStream);
        self::assertLessThan($brief, $headerEnd);
        self::assertLessThan($postStream, $brief);
        self::assertSame(1, substr_count($html, 'class="living-brief study-living-brief"'));
        self::assertStringContainsString('/privacy#thread-intelligence', $html);
        self::assertStringContainsString('Updated automatically', $html);
        self::assertMatchesRegularExpression('/<time datetime="[^"]+Z">/', $html);
        self::assertStringNotContainsString('Curate topic memory', substr($html, 0, (int) $headerEnd));

        $empty = $this->seedThread(1, 'No memory panel');
        $emptyPage = $this->get('/t/' . $empty['thread_id'] . '-' . $empty['slug']);
        self::assertStringNotContainsString('thread-memory-slot', $emptyPage->body());
        self::assertStringNotContainsString('living-brief', $emptyPage->body());

        $privacy = $this->get('/privacy');
        $this->assertStatus(200, $privacy);
        self::assertStringContainsString('id="thread-intelligence"', $privacy->body());
        self::assertStringContainsString('eligible public post text', $privacy->body());
        self::assertStringContainsString('OpenAI', $privacy->body());
        self::assertStringContainsString('Private and hidden content', $privacy->body());
        self::assertStringContainsString('account metadata', $privacy->body());
        self::assertStringContainsString('storage is disabled', $privacy->body());
        self::assertStringNotContainsString('gpt-', $privacy->body());
    }

    public function test_living_brief_read_renders_a_missing_html_cache_without_writing_it(): void
    {
        $seed = $this->seedThread(1, 'Living brief cache fallback');
        $admin = $this->makeAdmin(['username' => 'brief-cache-admin']);
        $this->memory()->publishSummary(
            $this->userEntity($admin),
            $seed['thread_id'],
            '**Rendered living brief**',
            [$seed['post_ids'][0]],
        );
        $summaryId = (int) $this->db->fetchValue(
            'SELECT id FROM thread_summaries WHERE thread_id = ? AND status = ?',
            [$seed['thread_id'], 'published'],
        );
        $this->db->run('UPDATE thread_summaries SET body_html = NULL WHERE id = ?', [$summaryId]);

        $model = $this->viewService()->forThread($seed['thread_id'], null);

        self::assertStringContainsString('<strong>Rendered living brief</strong>', $model['living_brief']['body_html']);
        self::assertNull($this->db->fetchValue('SELECT body_html FROM thread_summaries WHERE id = ?', [$summaryId]));
    }

    public function test_curator_refresh_feedback_and_retirement_resume_are_gated_and_non_bypassing(): void
    {
        $seed = $this->seedThread(8, 'Curator controls');
        $admin = $this->makeAdmin(['username' => 'refresh-admin']);
        $member = $this->makeUser(['username' => 'refresh-member']);
        $this->rebuildAppWithProvider();

        $this->actingAs($member);
        $this->assertStatus(403, $this->post('/t/' . $seed['thread_id'] . '/summary/refresh'));
        self::assertNull((new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id']));

        $this->actingAs($admin);
        $queued = $this->post('/t/' . $seed['thread_id'] . '/summary/refresh');
        $this->assertRedirect($queued, '/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        self::assertStringContainsString('Refresh queued', $page->body());

        $job = (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id']);
        self::assertSame(ThreadIntelligenceQueue::TRIGGER_CURATOR_REFRESH, $job['trigger_code']);
        $this->memory()->retireSummary($this->userEntity($admin), $seed['thread_id']);
        self::assertSame(1, (int) (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id'])['automation_paused']);

        $pauseConfig = ThreadIntelligenceConfig::fromArray(['api_key' => 'sk-test-surface']);
        (new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $pauseConfig,
            (string) $this->config->get('app.key'),
            'sk-test-surface',
            $this->db,
        ))->setGenerationPaused(true);
        $this->assertRedirect($this->post('/t/' . $seed['thread_id'] . '/summary/automation/resume'));
        $resumed = (new ThreadIntelligenceJobRepository($this->db))->find($seed['thread_id']);
        self::assertSame(0, (int) $resumed['automation_paused']);
        self::assertSame('queued', $resumed['state']);
        $decision = $this->viewService()->forThread($seed['thread_id'], $this->userEntity($admin))['refresh'];
        self::assertSame('generation_paused', $decision['code'], 'resume cannot bypass the global pause');
    }

    private function rebuildAppWithProvider(): void
    {
        $items = $this->config->all();
        $items['thread_intelligence']['api_key'] = 'sk-test-surface';
        $this->config = new Config($items);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    private function viewService(): ThreadIntelligenceViewService
    {
        $apiKey = 'sk-test-surface';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $settings = new ThreadIntelligenceSettings(
            new SettingRepository($this->db),
            $config,
            (string) $this->config->get('app.key'),
            $apiKey,
            $this->db,
        );
        return new ThreadIntelligenceViewService(
            db: $this->db,
            members: new BoardMemberRepository($this->db),
            policy: new BoardPolicy(),
            eligibility: new ThreadIntelligenceEligibility(
                $this->db,
                new FeatureFlags(new SettingRepository($this->db)),
                $config,
                $settings,
                new ThreadIntelligenceBudget($this->db, $config),
                $jobs,
            ),
            jobs: $jobs,
            markdown: new Markdown(new HtmlSanitizer()),
        );
    }

    private function memory(): CommunityMemoryService
    {
        $apiKey = 'sk-test-surface';
        $config = ThreadIntelligenceConfig::fromArray(['api_key' => $apiKey]);
        $jobs = new ThreadIntelligenceJobRepository($this->db);
        $settings = new ThreadIntelligenceSettings(new SettingRepository($this->db), $config, (string) $this->config->get('app.key'), $apiKey, $this->db);
        $queue = new ThreadIntelligenceQueue(
            $this->db,
            $jobs,
            new ThreadIntelligenceEligibility(
                $this->db,
                new FeatureFlags(new SettingRepository($this->db)),
                $config,
                $settings,
                new ThreadIntelligenceBudget($this->db, $config),
                $jobs,
            ),
        );
        return new CommunityMemoryService(
            $this->db,
            new ThreadRepository($this->db),
            new PostRepository($this->db),
            new BoardModeratorRepository($this->db),
            new BoardMemberRepository($this->db),
            new BoardPolicy(),
            new WriteGate(),
            new Markdown(new HtmlSanitizer()),
            null,
            null,
            $queue,
        );
    }

    /** @return array{thread_id:int,slug:string,board:array<string,mixed>,post_ids:list<int>} */
    private function seedThread(int $postCount, string $title): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, $title, 'Opening evidence');
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$thread['thread_id']])];
        for ($i = 1; $i < $postCount; $i++) {
            $postIds[] = $this->posting()->reply($this->userEntity($author), $thread['thread_id'], ['body' => 'Evidence reply ' . $i]);
        }
        return ['thread_id' => (int) $thread['thread_id'], 'slug' => $thread['slug'], 'board' => $board, 'post_ids' => $postIds];
    }

    /** @param list<int> $sourcePostIds @return array{int,int} */
    private function insertAiBrief(int $threadId, array $sourcePostIds, string $body): array
    {
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, reviewer_id, parent_summary_id, published_at, created_at)
             VALUES (?, 'ai', 'published', ?, ?, 1, NULL, NULL, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$threadId, $body, '<p>' . $body . '</p>'],
        );
        foreach ($sourcePostIds as $postId) {
            $this->db->run('INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)', [$summaryId, $postId]);
        }
        $generationId = $this->db->insert(
            "INSERT INTO thread_intelligence_generations
                (thread_id, trigger_code, status, published_summary_id, source_post_ids, requested_at, completed_at, published_at)
             VALUES (?, 'post_created', 'published', ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$threadId, $summaryId, json_encode($sourcePostIds, JSON_THROW_ON_ERROR)],
        );
        return [$summaryId, $generationId];
    }

    private function insertRelated(
        int $sourceThreadId,
        int $targetThreadId,
        string $source,
        ?string $reason,
        ?int $generationId = null,
        ?string $aiReason = null,
        bool $selected = false,
    ): void {
        $this->db->run(
            "INSERT INTO related_threads
                (source_thread_id, related_thread_id, relation_type, source, score, reason, status, curator_id,
                 ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
             VALUES (?, ?, 'related', ?, 1, ?, 'approved', NULL, ?, ?, ?, ?, UTC_TIMESTAMP())",
            [$sourceThreadId, $targetThreadId, $source, $reason, $generationId, $aiReason, $selected ? 1 : 0, $selected ? gmdate('Y-m-d H:i:s') : null],
        );
    }
}
