<?php

declare(strict_types=1);

namespace Tests\Unit\ThreadIntelligence;

use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceLiveEvaluator;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ThreadIntelligenceLiveEvaluatorTest extends TestCase
{
    private const REQUIRED_CATEGORIES = [
        'disagreement', 'explicit_decision', 'unresolved_decision', 'support_resolution',
        'long_running_chronology', 'sarcasm', 'quoted_instructions', 'prompt_injection',
        'harmful_content', 'deleted_source', 'pending_source', 'anonymous_author',
        'stale_citation', 'candidate_id_injection', 'duplicate_candidate',
        'html_markdown_injection', 'false_consensus_pressure', 'curator_baseline',
        'multi_window_thread', 'nonpublic_board_transition',
    ];

    public function test_fixed_corpus_has_unique_ids_and_every_locked_case_category(): void
    {
        $corpus = $this->corpusDocument();
        self::assertSame('thread-intelligence-corpus-v1', $corpus['revision']);
        self::assertGreaterThanOrEqual(20, count($corpus['fixtures']));
        $ids = array_column($corpus['fixtures'], 'id');
        self::assertCount(count($ids), array_unique($ids));
        $categories = array_unique(array_column($corpus['fixtures'], 'category'));
        foreach (self::REQUIRED_CATEGORIES as $category) {
            self::assertContains($category, $categories);
        }
        foreach ($corpus['fixtures'] as $fixture) {
            self::assertIsString($fixture['title']);
            self::assertIsArray($fixture['posts']);
            self::assertIsArray($fixture['candidates']);
            self::assertIsArray($fixture['expected']['source_post_ids']);
            self::assertIsArray($fixture['expected']['candidate_thread_ids']);
            self::assertIsArray($fixture['private_sentinels']);
        }
    }

    public function test_evaluator_runs_both_efforts_and_returns_only_redacted_counts_and_rubric(): void
    {
        $fixtures = $this->corpusDocument()['fixtures'];
        $moderator = new FakeThreadIntelligenceOutputModerator();
        $evaluator = new ThreadIntelligenceLiveEvaluator(
            new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())),
            $moderator,
        );
        $providers = [];
        $result = $evaluator->evaluate(
            $fixtures,
            ['none', 'low'],
            function (string $effort) use ($fixtures, &$providers): FakeThreadIntelligenceProvider {
                $provider = new FakeThreadIntelligenceProvider();
                foreach ($fixtures as $fixture) {
                    $provider->queueResult($this->validResult($fixture, $effort));
                }
                $providers[$effort] = $provider;
                return $provider;
            },
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 4,
                'supported_claims' => 4,
                'fabricated_decision' => false,
                'quality_pass' => true,
            ],
        );

        self::assertCount(count($fixtures) * 2, $result['runs']);
        self::assertTrue($result['decision']['passed']);
        self::assertSame('none', $result['decision']['selected_effort']);
        self::assertSame(16000, $result['decision']['ceiling']);
        self::assertCount(count($fixtures), $providers['none']->requests());
        self::assertCount(count($fixtures), $providers['low']->requests());
        foreach ($result['runs'] as $run) {
            self::assertSame('completed', $run['completion_category']);
            self::assertSame('passed', $run['validation_outcome']);
            self::assertSame('passed', $run['moderation_outcome']);
            self::assertSame(4, $run['material_claims']);
            self::assertSame(4, $run['supported_claims']);
            self::assertFalse($run['fabricated_decision']);
            self::assertTrue($run['quality_pass']);
        }

        $encoded = json_encode($result, JSON_THROW_ON_ERROR);
        foreach (['"prompt":', '"raw_prompt":', '"response":', '"raw_response":', '"post_body":', '"generated_text":', '"source_post_ids":', '"candidate_thread_ids":', '"api_key":', '"response_id":'] as $forbidden) {
            self::assertStringNotContainsString($forbidden, $encoded);
        }
        foreach ($fixtures as $fixture) {
            foreach ($fixture['posts'] as $post) {
                self::assertStringNotContainsString($post['body'], $encoded);
            }
        }
    }

    public function test_none_is_not_selected_after_an_incomplete_response(): void
    {
        $fixtures = [array_slice($this->corpusDocument()['fixtures'], 0, 1)[0]];
        $evaluator = new ThreadIntelligenceLiveEvaluator(
            new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())),
            new FakeThreadIntelligenceOutputModerator(),
        );
        $result = $evaluator->evaluate(
            $fixtures,
            ['none', 'low'],
            function (string $effort) use ($fixtures): FakeThreadIntelligenceProvider {
                $provider = new FakeThreadIntelligenceProvider();
                foreach ($fixtures as $fixture) {
                    $provider->queueResult($effort === 'none'
                        ? new ThreadIntelligenceResult([], null, 'incomplete', 'max_output_tokens', new ThreadIntelligenceUsage(10, 5, 0, 0))
                        : $this->validResult($fixture, $effort));
                }
                return $provider;
            },
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 2,
                'supported_claims' => 2,
                'fabricated_decision' => false,
                'quality_pass' => true,
            ],
        );

        self::assertFalse($result['decision']['passed']);
        self::assertSame('unselected', $result['decision']['selected_effort']);
        self::assertFalse($result['decision']['none_passed']);
        self::assertTrue($result['decision']['low_passed']);
        self::assertTrue($result['decision']['needs_ceiling_rerun']);
        self::assertSame(25000, $result['decision']['ceiling']);
        self::assertSame('incomplete:max_output_tokens', $result['runs'][0]['completion_category']);
        self::assertNull($result['runs'][0]['material_claims']);
    }

    public function test_human_rubric_rejects_unknown_or_inconsistent_values(): void
    {
        $fixture = $this->corpusDocument()['fixtures'][0];
        $evaluator = new ThreadIntelligenceLiveEvaluator(
            new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())),
            new FakeThreadIntelligenceOutputModerator(),
        );
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult($this->validResult($fixture, 'low'));

        $this->expectException(InvalidArgumentException::class);
        $evaluator->evaluate(
            [$fixture],
            ['low'],
            static fn (string $effort): FakeThreadIntelligenceProvider => $provider,
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 1,
                'supported_claims' => 2,
                'fabricated_decision' => false,
                'quality_pass' => true,
                'raw_notes' => 'must not be accepted',
            ],
        );
    }

    public function test_successful_rerun_preserves_the_raised_output_ceiling(): void
    {
        $fixture = $this->corpusDocument()['fixtures'][0];
        $provider = new FakeThreadIntelligenceProvider();
        $provider->queueResult($this->validResult($fixture, 'low'));
        $result = (new ThreadIntelligenceLiveEvaluator(
            new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())),
            new FakeThreadIntelligenceOutputModerator(),
            null,
            25_000,
        ))->evaluate(
            [$fixture],
            ['low'],
            static fn (string $effort): FakeThreadIntelligenceProvider => $provider,
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 1,
                'supported_claims' => 1,
                'fabricated_decision' => false,
                'quality_pass' => true,
            ],
        );

        self::assertTrue($result['decision']['passed']);
        self::assertSame(25_000, $result['decision']['ceiling']);
    }

    public function test_none_cannot_graduate_when_low_did_not_pass_the_comparison(): void
    {
        $fixture = $this->corpusDocument()['fixtures'][0];
        $result = (new ThreadIntelligenceLiveEvaluator(
            new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())),
            new FakeThreadIntelligenceOutputModerator(),
        ))->evaluate(
            [$fixture],
            ['none', 'low'],
            function (string $effort) use ($fixture): FakeThreadIntelligenceProvider {
                $provider = new FakeThreadIntelligenceProvider();
                if ($effort === 'none') {
                    $provider->queueResult($this->validResult($fixture, $effort));
                } else {
                    $provider->queueException(new ThreadIntelligenceProviderException('transport'));
                }
                return $provider;
            },
            static fn (string $fixtureId, string $effort, $output): array => [
                'material_claims' => 1,
                'supported_claims' => 1,
                'fabricated_decision' => false,
                'quality_pass' => true,
            ],
        );

        self::assertTrue($result['decision']['none_passed']);
        self::assertFalse($result['decision']['low_passed']);
        self::assertFalse($result['decision']['passed']);
        self::assertSame('unselected', $result['decision']['selected_effort']);
    }

    /** @param array<string,mixed> $fixture */
    private function validResult(array $fixture, string $effort): ThreadIntelligenceResult
    {
        $sourceId = (int) $fixture['expected']['source_post_ids'][0];
        $related = [];
        if (($fixture['expected']['candidate_thread_ids'] ?? []) !== []) {
            $related[] = [
                'thread_id' => (int) $fixture['expected']['candidate_thread_ids'][0],
                'explanation' => 'It covers a directly related public synthetic topic.',
            ];
        }
        return new ThreadIntelligenceResult([
            'overview' => ['markdown' => 'The public discussion has a bounded current state.', 'source_post_ids' => [$sourceId]],
            'key_points' => [
                ['markdown' => 'One material point is documented.', 'source_post_ids' => [$sourceId]],
                ['markdown' => 'The cited evidence remains authoritative.', 'source_post_ids' => [$sourceId]],
            ],
            'open_questions' => [
                ['markdown' => 'One follow-up remains open.', 'source_post_ids' => [$sourceId]],
            ],
            'related_topics' => $related,
        ], 'opaque-test-id', 'completed', null, new ThreadIntelligenceUsage(
            $effort === 'none' ? 100 : 110,
            50,
            $effort === 'none' ? 0 : 10,
            5,
        ));
    }

    /** @return array{revision:string,fixtures:list<array<string,mixed>>} */
    private function corpusDocument(): array
    {
        $path = dirname(__DIR__, 2) . '/fixtures/thread-intelligence-corpus.json';
        $decoded = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);
        self::assertIsArray($decoded['fixtures'] ?? null);
        return $decoded;
    }
}
