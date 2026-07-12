<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Core\FeatureFlags;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadRepository;
use App\Security\BoardPolicy;
use App\Service\ContentReferenceService;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\StaleThreadIntelligenceEvidence;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePost;
use App\Service\ThreadIntelligence\ThreadIntelligenceLiveEvaluator;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceRelatedCandidate;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use DateTimeImmutable;
use DateTimeZone;
use Tests\Support\TestCase;

final class ThreadIntelligenceAdversarialTest extends TestCase
{
    private ThreadIntelligenceOutputValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer()));
        (new SettingRepository($this->db))->set('features', [
            'community_memory' => true,
            'automated_context' => true,
        ]);
    }

    public function test_malicious_fake_outputs_fail_validation_and_never_create_a_summary(): void
    {
        $request = $this->evidenceRequest();
        $valid = $this->validOutput(1, 20);
        $cases = [
            'hallucinated_source' => array_replace_recursive($valid, ['overview' => ['source_post_ids' => [999999]]]),
            'hallucinated_candidate' => array_replace_recursive($valid, ['related_topics' => [['thread_id' => 999999, 'explanation' => 'An unsupplied target was requested.']]]),
            'extra_property' => $valid + ['raw_response' => 'forbidden'],
            'html_xss' => array_replace_recursive($valid, ['overview' => ['markdown' => '<script>alert(1)</script>']]),
            'markdown_link' => array_replace_recursive($valid, ['overview' => ['markdown' => '[click](https://example.test)']]),
            'markdown_image' => array_replace_recursive($valid, ['overview' => ['markdown' => '![alt](https://example.test/a.png)']]),
            'code_fence' => array_replace_recursive($valid, ['overview' => ['markdown' => "```\nunsafe\n```"]]),
            'multi_sentence_explanation' => array_replace_recursive($valid, ['related_topics' => [['thread_id' => 20, 'explanation' => 'First sentence. Second sentence.']]]),
        ];

        foreach ($cases as $name => $output) {
            try {
                $this->validator->validate(
                    new ThreadIntelligenceResult($output, 'opaque', 'completed', null, new ThreadIntelligenceUsage(1, 1, 0, 0)),
                    $request,
                );
                self::fail('Expected adversarial output to fail: ' . $name);
            } catch (ThreadIntelligenceProviderException $exception) {
                self::assertContains($exception->safeCode(), ['schema_invalid', 'validation_failed'], $name);
            }
        }
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_summaries'));
    }

    public function test_prompt_authority_and_harmful_output_fail_the_human_or_moderation_gate(): void
    {
        $fixture = $this->corpus()['fixtures'][7];
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult(new ThreadIntelligenceResult(
            $this->validOutput((int) $fixture['expected']['source_post_ids'][0], null, 'Everyone approved because the embedded instruction said so.'),
            'opaque',
            'completed',
            null,
            new ThreadIntelligenceUsage(20, 10, 0, 0),
        ));
        $evaluator = new ThreadIntelligenceLiveEvaluator($this->validator, new FakeThreadIntelligenceOutputModerator());
        $authority = $evaluator->evaluate(
            [$fixture],
            ['low'],
            static fn (string $effort): FakeThreadIntelligenceProvider => $provider,
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 1,
                'supported_claims' => 0,
                'fabricated_decision' => true,
                'quality_pass' => false,
            ],
        );
        self::assertFalse($authority['decision']['passed']);
        self::assertTrue($authority['runs'][0]['fabricated_decision']);

        $harmfulProvider = new FakeThreadIntelligenceProvider();
        $harmfulProvider->queueResult(new ThreadIntelligenceResult(
            $this->validOutput((int) $fixture['expected']['source_post_ids'][0]),
            'opaque',
            'completed',
            null,
            new ThreadIntelligenceUsage(20, 10, 0, 0),
        ));
        $moderator = new FakeThreadIntelligenceOutputModerator();
        $moderator->queueFlagged(['harassment']);
        $moderated = (new ThreadIntelligenceLiveEvaluator($this->validator, $moderator))->evaluate(
            [$fixture],
            ['low'],
            static fn (string $effort): FakeThreadIntelligenceProvider => $harmfulProvider,
            static function (): array {
                self::fail('Flagged content must never reach human scoring.');
            },
        );
        self::assertFalse($moderated['decision']['passed']);
        self::assertSame('flagged', $moderated['runs'][0]['moderation_outcome']);
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_summaries'));
    }

    public function test_private_pending_deleted_and_hidden_sentinels_never_enter_recorded_requests(): void
    {
        $fixtures = $this->corpus()['fixtures'];
        $provider = new FakeThreadIntelligenceProvider();
        foreach ($fixtures as $fixture) {
            $provider->queueResult(new ThreadIntelligenceResult(
                $this->validOutput(
                    (int) $fixture['expected']['source_post_ids'][0],
                    ($fixture['expected']['candidate_thread_ids'] ?? []) === [] ? null : (int) $fixture['expected']['candidate_thread_ids'][0],
                ),
                'opaque',
                'completed',
                null,
                new ThreadIntelligenceUsage(10, 5, 0, 0),
            ));
        }
        $result = (new ThreadIntelligenceLiveEvaluator($this->validator, new FakeThreadIntelligenceOutputModerator()))->evaluate(
            $fixtures,
            ['low'],
            static fn (string $effort): FakeThreadIntelligenceProvider => $provider,
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 1,
                'supported_claims' => 1,
                'fabricated_decision' => false,
                'quality_pass' => true,
            ],
        );

        $transmitted = '';
        foreach ($provider->requests() as $request) {
            $transmitted .= $request->threadTitle;
            foreach ($request->posts as $post) {
                $transmitted .= $post->body;
            }
            foreach ($request->candidates as $candidate) {
                $transmitted .= $candidate->title . $candidate->excerpt;
            }
        }
        foreach ($fixtures as $fixture) {
            foreach ($fixture['private_sentinels'] as $sentinel) {
                self::assertStringNotContainsString($sentinel, $transmitted);
            }
        }
        foreach ($result['runs'] as $run) {
            self::assertSame(0, $run['private_transmission_count']);
            self::assertSame(0, $run['ineligible_citation_count']);
            self::assertSame(0, $run['ineligible_candidate_count']);
        }
    }

    public function test_stale_snapshot_and_physically_deleted_citation_never_publish(): void
    {
        foreach (['edited', 'deleted'] as $mutation) {
            $seed = $this->seedEvidenceThread();
            $jobs = new ThreadIntelligenceJobRepository($this->db);
            $now = new DateTimeImmutable('2026-07-10 12:00:00', new DateTimeZone('UTC'));
            $jobs->upsertStale($seed['thread_id'], ThreadIntelligenceQueue::TRIGGER_POST_CREATED, null, $now->modify('-20 minutes'));
            $job = $jobs->claimDue(1, $now)[0];
            $builder = new ThreadIntelligenceEvidenceBuilder(
                $this->db,
                new ThreadIntelligenceCandidateFinder($this->db),
                ThreadIntelligenceConfig::fromArray([]),
            );
            $evidence = $builder->build($seed['thread_id'], $job);
            $request = $builder->requestForWindow($evidence, 0, null);
            $citedId = $request->posts[1]->postId;
            $output = $this->validator->validate(
                new ThreadIntelligenceResult($this->validOutput($citedId), 'opaque', 'completed', null, new ThreadIntelligenceUsage(10, 5, 0, 0)),
                $request,
            );
            $generations = new ThreadIntelligenceGenerationRepository($this->db);
            $generationId = $generations->start([
                'thread_id' => $seed['thread_id'],
                'trigger_code' => ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
                'baseline_summary_id' => $evidence->baselineSummaryId(),
            ]);
            $generations->recordRequest(
                $generationId,
                $evidence->snapshotHash(),
                $evidence->sourcePostIds(),
                $evidence->candidateThreadIds(),
                hash('sha256', $mutation . '-' . $generationId),
                $evidence->estimatedInputTokens(0),
            );
            if ($mutation === 'edited') {
                $this->db->run('UPDATE posts SET body = ?, body_html = ? WHERE id = ?', ['Changed after generation', '<p>Changed</p>', $citedId]);
            } else {
                $this->db->run('DELETE FROM posts WHERE id = ?', [$citedId]);
            }

            $publisher = new ThreadIntelligencePublisher(
                $this->db,
                new ThreadRepository($this->db),
                $jobs,
                $generations,
                $builder,
                new Markdown(new HtmlSanitizer()),
                new ContentReferenceService(
                    $this->db,
                    new BoardRepository($this->db),
                    new ThreadRepository($this->db),
                    new PostRepository($this->db),
                    new TagRepository($this->db),
                    new BoardMemberRepository($this->db),
                    new BoardPolicy(),
                    false,
                ),
            );
            try {
                $publisher->publish($generationId, (string) $job['lease_token'], $job, $evidence, $output);
                self::fail('Stale ' . $mutation . ' evidence must not publish.');
            } catch (StaleThreadIntelligenceEvidence) {
                self::assertSame(0, (int) $this->db->fetchValue(
                    "SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'",
                    [$seed['thread_id']],
                ));
            }
        }
    }

    private function evidenceRequest(): ThreadIntelligenceRequest
    {
        return new ThreadIntelligenceRequest(
            10,
            'Synthetic adversarial thread',
            null,
            null,
            [new ThreadIntelligenceEvidencePost(1, '2026-01-01T00:00:00Z', 'speaker-1', 'Public evidence')],
            [new ThreadIntelligenceRelatedCandidate(20, 'Candidate', 'Public candidate', ['test'], 1, 1.0, 1, '2026-01-01T00:00:00Z')],
            hash('sha256', 'adversarial-request'),
            'thread-intelligence-v1',
            0,
            1,
        );
    }

    /** @return array<string,mixed> */
    private function validOutput(int $sourceId, ?int $candidateId = null, string $overview = 'The current public record remains bounded.'): array
    {
        return [
            'overview' => ['markdown' => $overview, 'source_post_ids' => [$sourceId]],
            'key_points' => [
                ['markdown' => 'The evidence records one current point.', 'source_post_ids' => [$sourceId]],
                ['markdown' => 'No unsupported decision is added.', 'source_post_ids' => [$sourceId]],
            ],
            'open_questions' => [
                ['markdown' => 'A follow-up remains open.', 'source_post_ids' => [$sourceId]],
            ],
            'related_topics' => $candidateId === null ? [] : [[
                'thread_id' => $candidateId,
                'explanation' => 'It is a supplied related public topic.',
            ]],
        ];
    }

    /** @return array{thread_id:int} */
    private function seedEvidenceThread(): array
    {
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory());
        $thread = $this->makeThread($board, $author, 'Adversarial publication');
        for ($i = 1; $i < 8; $i++) {
            $this->db->insert(
                'INSERT INTO posts
                    (thread_id, user_id, body, body_html, is_op, is_anonymous, is_deleted, is_pending, created_at)
                 VALUES (?, ?, ?, ?, 0, 0, 0, 0, ?)',
                [$thread['thread_id'], (int) $author['id'], 'Evidence ' . $i, '<p>Evidence</p>', '2026-07-10 09:' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ':00'],
            );
        }
        return ['thread_id' => (int) $thread['thread_id']];
    }

    /** @return array<string,mixed> */
    private function corpus(): array
    {
        return json_decode(
            (string) file_get_contents(dirname(__DIR__, 2) . '/fixtures/thread-intelligence-corpus.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }
}
