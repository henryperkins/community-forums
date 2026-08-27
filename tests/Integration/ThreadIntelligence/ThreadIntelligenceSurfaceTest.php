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
        // The curator surface must stay out of the TOPIC head. Scope the slice from
        // `data-thread-study` to the next `</header>`: the first `</header>` in the
        // document belongs to the shell topbar (`partials/topbar.php:60`), so slicing
        // from zero would guard the wrong region. Asserted against a curator render,
        // because a guest never emits the marker at all and the check would be vacuous.
        $curator = $this->makeAdmin(['username' => 'dom-order-curator']);
        $this->actingAs($curator);
        $curatorHtml = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();
        $studyStart = strpos($curatorHtml, 'data-thread-study');
        self::assertNotFalse($studyStart);
        $topicHeadEnd = strpos($curatorHtml, '</header>', (int) $studyStart);
        self::assertNotFalse($topicHeadEnd);
        $topicHead = substr($curatorHtml, (int) $studyStart, (int) $topicHeadEnd - (int) $studyStart);
        self::assertStringContainsString('class="breadcrumb"', $topicHead, 'the slice is the topic head');
        self::assertStringContainsString('living-brief-curator', $curatorHtml);
        self::assertStringNotContainsString('living-brief-curator', $topicHead);
        $this->logoutClient();

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

    public function test_living_brief_has_no_redundant_heading_and_keeps_an_accessible_name(): void
    {
        $seed = $this->seedThread(8, 'Brief heading removal');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringNotContainsString('Where the discussion stands', $html);
        self::assertStringNotContainsString('living-brief-heading', $html);
        self::assertStringContainsString('aria-label="Living brief"', $html);
        self::assertStringContainsString('/privacy#thread-intelligence', $html);

        // Dropping the <h2> outright left the section's own <h3>Sources</h3> directly
        // under the topic <h1>, with nothing between them. The browser suite cannot
        // see that: `heading-order` carries axe's `best-practice` tag, so
        // withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa']) excludes the rule
        // outright, and its impact is `moderate`, below the serious/critical filter.
        // So the outline is pinned here instead — a screen-reader-only <h2> keeps the
        // topic title leading visually while the levels stay contiguous.
        self::assertStringContainsString('<h2 class="sr-only">Living brief</h2>', $html);
        preg_match_all('/<h([1-6])\b/', $html, $found);
        $levels = array_map('intval', $found[1]);
        $skips = [];
        foreach ($levels as $index => $level) {
            if ($index > 0 && $level > $levels[$index - 1] + 1) {
                $skips[] = 'h' . $levels[$index - 1] . ' -> h' . $level;
            }
        }
        self::assertSame([], $skips, 'the topic page outline skips a heading level');
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

    public function test_paused_automation_is_visible_to_members_not_only_curators(): void
    {
        $seed = $this->seedThread(8, 'Paused brief visibility');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $this->queue()->setAutomationPaused($seed['thread_id'], true, null);

        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringContainsString(
            'Automatic refresh is paused for this topic. The brief stands as published.',
            $html,
        );
        // Exactly one `.living-brief-status` element per brief. The character class keeps
        // the descendant `.living-brief-status-icon` from being counted as a second one.
        self::assertSame(1, preg_match_all('/class="living-brief-status[ "]/', $html));
        self::assertSame(1, substr_count($html, 'living-brief-status-icon'));
    }

    public function test_pause_automation_is_curator_gated_and_records_the_actor(): void
    {
        $seed = $this->seedThread(8, 'Pause automation route');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');

        $member = $this->makeUser(['username' => 'pause-member']);
        $this->actingAs($member);
        // The DB assertion below would also pass on a 404, a 405, or a simply absent
        // job row, so it is no proof of a gate on its own. Pin the refusal itself, the
        // way the refresh route's denial is pinned above.
        $this->assertStatus(403, $this->post('/t/' . $seed['thread_id'] . '/summary/automation/pause', []));
        self::assertNotSame(
            1,
            (int) $this->db->fetchValue(
                'SELECT COALESCE(automation_paused, 0) FROM thread_intelligence_jobs WHERE thread_id = ?',
                [$seed['thread_id']],
            ),
        );

        $admin = $this->makeAdmin(['username' => 'pause-curator']);
        $this->actingAs($admin);
        $allowed = $this->post('/t/' . $seed['thread_id'] . '/summary/automation/pause', []);
        $this->assertRedirect($allowed, '/t/' . $seed['thread_id'] . '-' . $seed['slug']);

        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT automation_paused FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame($admin['id'], (int) $this->db->fetchValue(
            'SELECT paused_by FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
    }

    public function test_curator_tools_render_inside_the_brief_and_never_for_members(): void
    {
        $seed = $this->seedThread(8, 'Curator tools relocation');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];

        $admin = $this->makeAdmin(['username' => 'tools-curator']);
        $this->actingAs($admin);
        $curatorHtml = $this->get($url)->body();
        $briefStart = strpos($curatorHtml, 'data-living-brief');
        // Match the container's own id, not the topic-tools link's `href="#..."`:
        // the aside renders before the brief, so a bare substring would find the
        // link first and invert the ordering assertion.
        $tools = strpos($curatorHtml, 'id="living-brief-curator-' . $seed['thread_id'] . '"');
        self::assertNotFalse($briefStart);
        self::assertNotFalse($tools);
        self::assertLessThan($tools, $briefStart);
        self::assertSame(1, substr_count($curatorHtml, 'action="/t/' . $seed['thread_id'] . '/summary/refresh"'));

        $member = $this->makeUser(['username' => 'tools-member']);
        $this->actingAs($member);
        $memberHtml = $this->get($url)->body();
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $memberHtml);

        $this->logoutClient();
        $guestHtml = $this->get($url)->body();
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $guestHtml);
    }

    public function test_curator_footer_has_one_primary_action_and_row_based_restore(): void
    {
        $seed = $this->seedThread(8, 'Curator footer structure');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $admin = $this->makeAdmin(['username' => 'footer-curator']);
        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        // The <select> is gone; restore is one form per version.
        self::assertStringNotContainsString('id="summary-restore"', $html);
        self::assertStringContainsString('name="summary_id"', $html);

        // Refresh is the one filled button, not a btn-small.
        self::assertMatchesRegularExpression(
            '/<button class="btn"[^>]*type="submit"[^>]*>Refresh<\/button>/',
            $html,
        );

        // More and the retire confirm are <details>, so they work without JS.
        self::assertStringContainsString('class="lb-more"', $html);
        self::assertStringContainsString('class="lb-confirm"', $html);

        // Retire is behind a confirm step, not a bare one-click submit.
        $retirePos = strpos($html, 'action="/t/' . $seed['thread_id'] . '/summary/retire"');
        $confirmPos = strpos($html, 'class="lb-confirm"');
        self::assertNotFalse($retirePos);
        self::assertNotFalse($confirmPos);
        self::assertLessThan($retirePos, $confirmPos);

        // Pause is offered when automation is running.
        self::assertStringContainsString('action="/t/' . $seed['thread_id'] . '/summary/automation/pause"', $html);
    }

    public function test_paused_curator_footer_promotes_resume_and_states_the_pause_once(): void
    {
        $seed = $this->seedThread(8, 'Paused curator footer');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $this->queue()->setAutomationPaused($seed['thread_id'], true, null);
        $admin = $this->makeAdmin(['username' => 'paused-curator']);
        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        // The brief's own member-visible line is the ONLY place the pause is stated.
        // The eligibility denial reads "…paused for this thread" (Eligibility.php:147),
        // so re-rendering the curator note shows up as a second hit on the shared prefix.
        self::assertSame(1, substr_count($html, 'Automatic refresh is paused'));

        // Resume takes the primary slot; the dead disabled Refresh is gone entirely.
        self::assertMatchesRegularExpression(
            '/<button class="btn" type="submit">Resume automatic refresh<\/button>/',
            $html,
        );
        self::assertStringNotContainsString(
            'action="/t/' . $seed['thread_id'] . '/summary/refresh"',
            $html,
        );

        // Resume renders once — promoting it must not leave a copy in the More footer —
        // and Pause is not offered on a topic that is already paused.
        self::assertSame(1, substr_count(
            $html,
            'action="/t/' . $seed['thread_id'] . '/summary/automation/resume"',
        ));
        self::assertStringNotContainsString(
            'action="/t/' . $seed['thread_id'] . '/summary/automation/pause"',
            $html,
        );
    }

    /**
     * Axis 2 of the three authorization axes (DECISIONS: "state beats role"). A
     * suspended admin keeps every read affordance and loses every write one. Proven
     * twice over: the page hides the curator markup, AND each of the seven curator
     * routes refuses the POST — hidden markup is an affordance, not a gate.
     */
    public function test_suspended_admin_keeps_reading_but_loses_every_curator_affordance(): void
    {
        $seed = $this->seedThread(8, 'Suspended curator gate');
        [$summaryId] = $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $neighbour = $this->makeThread($seed['board'], $this->makeUser(['username' => 'suspended-neighbour']), 'Neighbour topic');
        $admin = $this->makeAdmin(['username' => 'suspended-curator']);
        $this->db->run(
            "UPDATE users SET status = 'suspended', suspended_until = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY) WHERE id = ?",
            [$admin['id']],
        );
        $this->actingAs($admin);

        $page = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug']);
        $this->assertStatus(200, $page);
        $html = $page->body();

        self::assertStringContainsString('data-living-brief', $html);
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $html);
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/related"', $html);
        // Covers both the footer's own wrapper id and the topic-tools jump link to it.
        self::assertStringNotContainsString('living-brief-curator-' . $seed['thread_id'], $html);

        foreach ($this->curatorForms($seed['thread_id'], $summaryId, (int) $neighbour['thread_id']) as $label => [$path, $body]) {
            self::assertSame(
                403,
                $this->post($path, $body)->status(),
                $label . ' must refuse a suspended admin: state beats role',
            );
        }
    }

    /**
     * Axis 1 (global role, at its floor) plus the unauthenticated case — which is not
     * one of the three axes but the gate standing in front of all of them. Per-board
     * authority, the real axis 3, is pinned separately in
     * test_board_moderator_curates_their_own_board_and_no_other().
     *
     * The rendered assertions prove the affordance is hidden; the route assertions
     * prove the server refuses a hand-rolled POST, which is the part that actually
     * holds. A member is refused (403); a guest never reaches the gate at all and is
     * bounced to /login.
     */
    public function test_curator_routes_refuse_plain_members_and_guests_on_every_form(): void
    {
        $seed = $this->seedThread(8, 'Curator route gate');
        [$summaryId] = $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $neighbour = $this->makeThread($seed['board'], $this->makeUser(['username' => 'gate-neighbour']), 'Neighbour topic');
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];
        $forms = $this->curatorForms($seed['thread_id'], $summaryId, (int) $neighbour['thread_id']);

        $member = $this->makeUser(['username' => 'gate-member']);
        $this->actingAs($member);
        $memberHtml = $this->get($url)->body();
        self::assertStringContainsString('data-living-brief', $memberHtml);
        self::assertStringNotContainsString('living-brief-curator-' . $seed['thread_id'], $memberHtml);
        foreach ($forms as $label => [$path, $body]) {
            self::assertSame(403, $this->post($path, $body)->status(), $label . ' must refuse a plain member');
        }

        $this->logoutClient();
        // This GET also seeds the guest CSRF cookie, so the POSTs below carry a valid
        // token: the 302 to /login is the auth gate answering, not the CSRF gate's 403.
        $guestHtml = $this->get($url)->body();
        self::assertStringContainsString('data-living-brief', $guestHtml);
        self::assertStringNotContainsString('living-brief-curator-' . $seed['thread_id'], $guestHtml);
        foreach ($forms as $label => [$path, $body]) {
            $response = $this->post($path, $body);
            self::assertContains($response->status(), [302, 303], $label . ' must bounce a guest');
            self::assertStringContainsString(
                '/login',
                (string) $response->getHeader('location'),
                $label . ' must send a guest to the login page',
            );
        }
    }

    /**
     * Axis 3, per-board authority — the axis with the most moving parts, because
     * assertCuratorForLockedThread() resolves it through AuthorityGate::allows() with
     * Cap::MEMORY_CURATE rather than a bare role read, so a capability-rule regression
     * could open the gate with every other test in this file still green.
     *
     * Two board_moderators rows, identical in every respect except which board they
     * name, decide the same thread's brief in opposite directions. Neither actor is an
     * admin: the positive half is the only allowed case in this file that is not a site
     * admin, which is what would catch a rule that silently narrowed to admin-only.
     */
    public function test_board_moderator_curates_their_own_board_and_no_other(): void
    {
        $seed = $this->seedThread(8, 'Per-board curator authority');
        [$summaryId] = $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Rendered AI summary');
        $neighbour = $this->makeThread($seed['board'], $this->makeUser(['username' => 'board-neighbour']), 'Neighbour topic');
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];
        $forms = $this->curatorForms($seed['thread_id'], $summaryId, (int) $neighbour['thread_id']);
        $boardMods = new BoardModeratorRepository($this->db);

        // Moderator of a DIFFERENT board: global role `user`, a real board_moderators
        // row, and no authority at all over this thread.
        $elsewhere = $this->makeBoard($this->makeCategory('Elsewhere'));
        $offBoardMod = $this->makeUser(['username' => 'off-board-mod']);
        $boardMods->assign((int) $elsewhere['id'], (int) $offBoardMod['id']);
        $this->actingAs($offBoardMod);
        $offBoardHtml = $this->get($url)->body();
        self::assertStringContainsString('data-living-brief', $offBoardHtml);
        self::assertStringNotContainsString('living-brief-curator-' . $seed['thread_id'], $offBoardHtml);
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary', $offBoardHtml);
        foreach ($forms as $label => [$path, $body]) {
            self::assertSame(
                403,
                $this->post($path, $body)->status(),
                $label . ' must refuse a moderator assigned to another board',
            );
        }

        // Moderator of THIS board: the same shape of row, now naming the thread's board.
        $onBoardMod = $this->makeUser(['username' => 'on-board-mod']);
        $boardMods->assign((int) $seed['board']['id'], (int) $onBoardMod['id']);
        $this->actingAs($onBoardMod);
        $onBoardHtml = $this->get($url)->body();
        self::assertStringContainsString('id="living-brief-curator-' . $seed['thread_id'] . '"', $onBoardHtml);
        self::assertStringContainsString('action="/t/' . $seed['thread_id'] . '/summary/refresh"', $onBoardHtml);

        // Rendered affordance is not authority: make a curator route actually act, and
        // pin that it books a non-admin as the curator who did it.
        $this->assertRedirect($this->post('/t/' . $seed['thread_id'] . '/summary/automation/pause', []), $url);
        self::assertSame(1, (int) $this->db->fetchValue(
            'SELECT automation_paused FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
        self::assertSame((int) $onBoardMod['id'], (int) $this->db->fetchValue(
            'SELECT paused_by FROM thread_intelligence_jobs WHERE thread_id = ?',
            [$seed['thread_id']],
        ));
    }

    public function test_eligibility_counts_are_exposed_for_the_empty_state(): void
    {
        $seed = $this->seedThread(4, 'Eligibility counts');
        $counts = $this->eligibility()->initialPostProgress($seed['thread_id']);

        self::assertSame(8, $counts['threshold']);
        self::assertSame(4, $counts['eligible']);
        self::assertLessThan($counts['threshold'], $counts['eligible']);

        // The empty view model carries both numbers so the empty state can say
        // how far short the topic falls; `eligible` counts the OP, so it is one
        // more than the reply count the topic header shows.
        $refresh = $this->viewService()->forThread($seed['thread_id'], null)['refresh'];
        self::assertSame('initial_post_threshold', $refresh['code']);
        self::assertSame(4, $refresh['eligible_posts']);
        self::assertSame(8, $refresh['initial_post_threshold']);
        self::assertSame(3, (int) $this->db->fetchValue(
            'SELECT reply_count FROM threads WHERE id = ?',
            [$seed['thread_id']],
        ));
    }

    public function test_empty_state_explains_eligibility_to_curators_only(): void
    {
        $seed = $this->seedThread(4, 'Empty brief eligibility');
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];

        // Guest sees nothing — this preserves the existing no-empty-panel contract.
        $this->logoutClient();
        $guestHtml = $this->get($url)->body();
        self::assertStringNotContainsString('living-brief', $guestHtml);
        self::assertStringNotContainsString('thread-memory-slot', $guestHtml);

        $admin = $this->makeAdmin(['username' => 'empty-curator']);
        $this->actingAs($admin);
        $curatorPage = $this->get($url);
        $this->assertStatus(200, $curatorPage);
        $curatorHtml = $curatorPage->body();

        self::assertStringContainsString('eight eligible posts', $curatorHtml);
        self::assertStringContainsString('the opening post plus every reply', $curatorHtml);
        self::assertStringContainsString('This one has 4.', $curatorHtml);
        self::assertStringNotContainsString('counsels', $curatorHtml);
        self::assertStringNotContainsString('six eligible', $curatorHtml);
        // No invented second number, and no exclusion reason the data cannot support.
        self::assertStringNotContainsString('are eligible; the rest', $curatorHtml);

        // The empty state restores what moving the curator tools inside the brief
        // removed: before the redesign a curator could author the FIRST summary of
        // any topic and link a related one. It also carries the anchor
        // partials/thread_tools.php links to, so that link resolves with no brief.
        self::assertStringContainsString('id="living-brief-curator-' . $seed['thread_id'] . '"', $curatorHtml);
        self::assertStringContainsString('action="/t/' . $seed['thread_id'] . '/summary"', $curatorHtml);
        self::assertStringContainsString('action="/t/' . $seed['thread_id'] . '/related"', $curatorHtml);
        self::assertStringContainsString('name="source_post_ids"', $curatorHtml);
        // Nothing has ever been published here, so "yet" is true of the landmark too.
        self::assertStringContainsString('aria-label="No living brief yet"', $curatorHtml);
        // Nothing to retire when nothing is published.
        self::assertStringNotContainsString('action="/t/' . $seed['thread_id'] . '/summary/retire"', $curatorHtml);

        // A deterministic related-topic fallback must not strand the curator: the
        // empty state renders beside it, not instead of it.
        $fallbackTarget = $this->seedThread(1, 'Empty state fallback target');
        $this->insertRelated($seed['thread_id'], $fallbackTarget['thread_id'], 'tag', null);
        $withFallback = $this->get($url)->body();
        self::assertStringContainsString('related-topic-fallback', $withFallback);
        self::assertStringContainsString('living-brief-empty', $withFallback);
        self::assertStringContainsString('eight eligible posts', $withFallback);

        // A topic can lack a brief for reasons the post count cannot explain. Only
        // the `initial_post_threshold` denial earns the count sentence; every other
        // denial shows the reason the eligibility ladder actually gave.
        $offPublic = $this->seedThread(8, 'Empty brief off a public board');
        $this->db->run('UPDATE boards SET visibility = ? WHERE id = ?', ['private', (int) $offPublic['board']['id']]);
        $offPublicPage = $this->get('/t/' . $offPublic['thread_id'] . '-' . $offPublic['slug']);
        $this->assertStatus(200, $offPublicPage);
        $offPublicHtml = $offPublicPage->body();
        self::assertStringContainsString('living-brief-empty', $offPublicHtml);
        // The ladder's own wording is 'Refresh is available only for eligible public
        // threads' (ThreadIntelligenceEligibility::decide()) — schema register, no
        // terminal period. That string is pinned at source by the unit test and shared
        // with the operator console, so the panel adapts it at the render instead: this
        // app's noun is "topic", and every sentence the panel writes ends in a period.
        self::assertStringContainsString('Refresh is available only for eligible public topics.', $offPublicHtml);
        self::assertStringNotContainsString('eligible public threads', $offPublicHtml);
        self::assertStringNotContainsString('eight eligible posts', $offPublicHtml);
        self::assertStringNotContainsString('This one has 8.', $offPublicHtml);

        // Design acceptance #7: under a rolled-back `automated_context` the ladder
        // denies before it ever counts posts, so the panel must repeat the ladder's
        // reason rather than invent a post-count one. Last in this test because
        // SettingRepository::set() replaces the whole features override.
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => true,
            'automated_context' => false,
        ]);
        $rolledBack = $this->get($url)->body();
        self::assertStringContainsString('Automatic context is disabled.', $rolledBack);
        self::assertStringNotContainsString('eight eligible posts', $rolledBack);
    }

    public function test_empty_state_after_a_retirement_leads_with_restore_not_first_summary(): void
    {
        $seed = $this->seedThread(8, 'Retired brief topic');
        $admin = $this->makeAdmin(['username' => 'retire-curator']);
        $this->memory()->publishSummary($this->userEntity($admin), $seed['thread_id'], 'First curated brief', [$seed['post_ids'][0]]);
        $summaryId = (int) $this->db->fetchValue(
            'SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY id DESC LIMIT 1',
            [$seed['thread_id']],
        );
        $this->memory()->retireSummary($this->userEntity($admin), $seed['thread_id']);

        $this->actingAs($admin);
        $url = '/t/' . $seed['thread_id'] . '-' . $seed['slug'];
        $html = $this->get($url)->body();

        // A retired version is still a version: the panel must not claim the topic
        // has never carried a brief, nor offer to write its "first" summary when the
        // next publish would be v2.
        self::assertStringContainsString('No brief showing', $html);
        self::assertStringNotContainsString('No brief yet', $html);
        self::assertStringContainsString('Write a new summary', $html);
        self::assertStringNotContainsString('Write the first summary', $html);

        // Restore is the primary action in this state, not a footnote two
        // disclosures deep, and it renders exactly once.
        self::assertStringContainsString('Restore a version', $html);
        $restoreRow = 'name="summary_id" value="' . $summaryId . '"';
        self::assertSame(1, substr_count($html, $restoreRow), 'one restore form per version, not two');
        // Distinct accessible name per row: several buttons reading only "Restore"
        // are the first thing a screen-reader user meets now that the rows lead.
        self::assertStringContainsString(
            '<button class="btn" type="submit">Restore<span class="sr-only"> version 1</span></button>',
            $html,
        );
        // The landmark name must track the visible eyebrow: a region still announced
        // as "No living brief yet" contradicts the "No brief showing" above it.
        self::assertStringContainsString('aria-label="No living brief showing"', $html);
        self::assertStringNotContainsString('aria-label="No living brief yet"', $html);

        // The panel must give the REASON the brief is gone, not the side effect.
        // Retiring pauses automation, so the eligibility ladder's first denial here
        // is `automation_paused` — true, but an answer to a different question, and
        // this is the one slot where every other branch explains the absence itself.
        self::assertStringContainsString('Retiring the brief hid it from this topic and paused automatic refresh.', $html);
        self::assertStringNotContainsString('Automatic refresh is paused for this topic.</p>', $html);
        self::assertStringNotContainsString('Automatic refresh is paused for this thread', $html);

        // One primary action. Restore is promoted to the surface with the filled
        // `.btn`, so Resume — which does not undo the retirement, and which restoring
        // deliberately leaves untouched — steps down beside it rather than presenting
        // a second filled button with nothing arbitrating between them.
        self::assertStringContainsString('<button class="linkbtn" type="submit">Resume automatic refresh</button>', $html);
        self::assertStringNotContainsString('<button class="btn" type="submit">Resume automatic refresh</button>', $html);
        self::assertStringContainsString('automatic refresh stays paused until you resume it', $html);

        // Pause is gated on `!$paused` and Retire on a published brief, so in this
        // one state both children of the More footer are absent. The wrapper must go
        // with them: `.lb-more-foot` carries a top rule and padding, and an empty one
        // draws a rule under the related-topic form with nothing beneath it.
        self::assertStringNotContainsString('<div class="lb-more-foot">', $html);
        $rowAt = strpos($html, $restoreRow);
        $footerAt = strpos($html, 'id="living-brief-curator-' . $seed['thread_id'] . '"');
        self::assertNotFalse($rowAt);
        self::assertNotFalse($footerAt);
        self::assertLessThan($footerAt, $rowAt, 'the version rows sit above the curator footer');

        // With automation resumed and a provider configured the ladder allows a
        // refresh, which is the one branch that would otherwise say "the archive has
        // not drawn one yet" over a topic whose brief it drew and a curator retired.
        $this->memory()->resumeAutomation($this->userEntity($admin), $seed['thread_id']);
        $this->rebuildAppWithProvider();
        $ready = $this->get($url)->body();
        self::assertStringContainsString('restore a version below', $ready);
        self::assertStringNotContainsString('the archive has not drawn one yet', $ready);
        self::assertStringNotContainsString('publish the first summary yourself', $ready);
    }

    /**
     * Retire pauses automation, so the post-retire panel steps *Resume* down beside
     * the promoted Restore rows. Resuming does not republish the retired brief, so
     * the panel stays an empty state with versions behind it — but the footer's
     * leading slot swaps from Resume to Refresh. The step-down belongs to the slot,
     * not to whichever control happens to occupy it, or a two-click path from any
     * retirement lands a filled Refresh beside a filled Restore with nothing
     * arbitrating between them.
     */
    public function test_resumed_empty_state_keeps_one_primary_beside_the_promoted_restore(): void
    {
        $seed = $this->seedThread(8, 'Resumed after retirement');
        $admin = $this->makeAdmin(['username' => 'resume-curator']);
        $this->memory()->publishSummary($this->userEntity($admin), $seed['thread_id'], 'First curated brief', [$seed['post_ids'][0]]);
        $this->memory()->retireSummary($this->userEntity($admin), $seed['thread_id']);
        $this->memory()->resumeAutomation($this->userEntity($admin), $seed['thread_id']);
        $this->rebuildAppWithProvider();

        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        // Still the empty state with versions behind it, and the ladder allows a
        // refresh — the branch whose copy names both actions.
        self::assertStringContainsString('living-brief-empty', $html);
        self::assertStringContainsString('Restore a version', $html);
        self::assertStringContainsString('restore a version below', $html);

        // Restore keeps the one filled treatment; Refresh takes the step-down that
        // Resume took while the topic was paused.
        self::assertStringContainsString(
            '<button class="btn" type="submit">Restore<span class="sr-only"> version 1</span></button>',
            $html,
        );
        self::assertStringContainsString('<button class="linkbtn" type="submit">Refresh</button>', $html);
        self::assertStringNotContainsString('<button class="btn" type="submit">Refresh</button>', $html);
    }

    public function test_empty_state_states_the_next_refresh_time_exactly_once(): void
    {
        $seed = $this->seedThread(8, 'Hourly limited topic');
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id, state, trigger_code, last_generated_at, activity_version, created_at, updated_at)
             VALUES (?, 'idle', 'post_created', UTC_TIMESTAMP(), 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id']],
        );
        $this->rebuildAppWithProvider();
        $admin = $this->makeAdmin(['username' => 'hourly-curator']);
        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        $copyAt = strpos($html, 'class="living-brief-empty-copy"');
        self::assertNotFalse($copyAt, 'the curator empty state renders');
        $copyEnd = strpos($html, '</p>', (int) $copyAt);
        self::assertNotFalse($copyEnd);
        $copy = substr($html, (int) $copyAt, (int) $copyEnd - (int) $copyAt);

        // ThreadIntelligenceEligibility::decide() embeds a formatted time in the
        // `hourly_limit` message itself whenever the ask is explicit — and the view
        // model only ever asks explicitly. Appending the UTC <time> as well would
        // restate one instant in two timezones.
        self::assertStringContainsString('Refresh available after', $copy);
        self::assertStringNotContainsString('<time', $copy);
    }

    public function test_curator_note_states_the_next_refresh_time_exactly_once(): void
    {
        $seed = $this->seedThread(8, 'Hourly limited brief topic');
        $this->insertAiBrief($seed['thread_id'], [$seed['post_ids'][0]], 'Published brief body');
        $this->db->run(
            "INSERT INTO thread_intelligence_jobs
                (thread_id, state, trigger_code, last_generated_at, activity_version, created_at, updated_at)
             VALUES (?, 'idle', 'post_created', UTC_TIMESTAMP(), 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())",
            [$seed['thread_id']],
        );
        $this->rebuildAppWithProvider();
        $admin = $this->makeAdmin(['username' => 'hourly-brief-curator']);
        $this->actingAs($admin);
        $html = $this->get('/t/' . $seed['thread_id'] . '-' . $seed['slug'])->body();

        // The brief-present surface carries the SAME denial through a different
        // element — partials/thread_memory_tools.php's curator note — so the
        // one-instant-two-timezones restatement has to be guarded there too.
        $noteAt = strpos($html, 'class="muted living-brief-curator-note"');
        self::assertNotFalse($noteAt, 'the curator note renders beside a published brief');
        $noteEnd = strpos($html, '</p>', (int) $noteAt);
        self::assertNotFalse($noteEnd);
        $note = substr($html, (int) $noteAt, (int) $noteEnd - (int) $noteAt);
        self::assertStringContainsString('Refresh available after', $note);
        self::assertStringNotContainsString('<time', $note);

        // The version rows in the brief's More panel carry the same per-row
        // accessible name as the promoted rows in the empty state.
        self::assertStringContainsString(
            'Restore<span class="sr-only"> version 1</span>',
            $html,
        );
    }

    /**
     * The seven curator-gated forms rendered by partials/thread_memory_tools.php.
     * Payloads are chosen to REACH the authorization check rather than trip an earlier
     * branch: publishSummary rejects an empty body, republishSummary resolves the
     * summary row, and addRelated rejects a self-reference — all before assertCurator —
     * so placeholder payloads would answer 302/404 and prove nothing about the gate.
     *
     * @return array<string,array{0:string,1:array<string,mixed>}>
     */
    private function curatorForms(int $threadId, int $summaryId, int $relatedThreadId): array
    {
        return [
            'amend' => ['/t/' . $threadId . '/summary', ['body' => 'Unauthorized amendment']],
            'refresh' => ['/t/' . $threadId . '/summary/refresh', []],
            'retire' => ['/t/' . $threadId . '/summary/retire', []],
            'restore' => ['/t/' . $threadId . '/summary/restore', ['summary_id' => $summaryId]],
            'pause' => ['/t/' . $threadId . '/summary/automation/pause', []],
            'resume' => ['/t/' . $threadId . '/summary/automation/resume', []],
            'related' => [
                '/t/' . $threadId . '/related',
                ['related_thread_id' => $relatedThreadId, 'reason' => 'Unauthorized link'],
            ],
        ];
    }

    private function rebuildAppWithProvider(): void
    {
        $items = $this->config->all();
        $items['thread_intelligence']['api_key'] = 'sk-test-surface';
        $this->config = new Config($items);
        $this->app = new App($this->config, $this->db, $this->rateLimiter);
    }

    private function eligibility(): ThreadIntelligenceEligibility
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

        return new ThreadIntelligenceEligibility(
            $this->db,
            new FeatureFlags(new SettingRepository($this->db)),
            $config,
            $settings,
            new ThreadIntelligenceBudget($this->db, $config),
            $jobs,
        );
    }

    private function viewService(): ThreadIntelligenceViewService
    {
        return new ThreadIntelligenceViewService(
            db: $this->db,
            members: new BoardMemberRepository($this->db),
            policy: new BoardPolicy(),
            eligibility: $this->eligibility(),
            jobs: new ThreadIntelligenceJobRepository($this->db),
            markdown: new Markdown(new HtmlSanitizer()),
        );
    }

    private function queue(): ThreadIntelligenceQueue
    {
        return new ThreadIntelligenceQueue(
            $this->db,
            new ThreadIntelligenceJobRepository($this->db),
            $this->eligibility(),
        );
    }

    private function memory(): CommunityMemoryService
    {
        $queue = $this->queue();

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
