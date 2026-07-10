<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use Closure;
use InvalidArgumentException;

/** Runs the bounded synthetic comparison while retaining only redacted metrics. */
final class ThreadIntelligenceLiveEvaluator
{
    private readonly ?Closure $responseIdRecorder;

    private const EFFORTS = ['none', 'low'];
    private const RUBRIC_KEYS = [
        'material_claims', 'supported_claims', 'fabricated_decision', 'quality_pass',
    ];

    public function __construct(
        private readonly ThreadIntelligenceOutputValidator $validator,
        private readonly ThreadIntelligenceOutputModerator $moderator,
        ?callable $responseIdRecorder = null,
        private readonly int $outputCeiling = 16_000,
    ) {
        if (!in_array($outputCeiling, [16_000, 25_000], true)) {
            throw new InvalidArgumentException('live evaluation output ceiling must be 16000 or 25000');
        }
        $this->responseIdRecorder = $responseIdRecorder === null
            ? null
            : Closure::fromCallable($responseIdRecorder);
    }

    /**
     * @param list<array<string,mixed>> $fixtures
     * @param list<'none'|'low'> $efforts
     * @param callable('none'|'low'): ThreadIntelligenceProvider $providerFactory
     * @param callable(string, 'none'|'low', ValidatedThreadIntelligenceOutput): array{
     *     material_claims:int,
     *     supported_claims:int,
     *     fabricated_decision:bool,
     *     quality_pass:bool
     * } $humanScorer
     * @return array{runs:list<array<string,int|float|string|bool|null>>,decision:array<string,int|string|bool>}
     */
    public function evaluate(
        array $fixtures,
        array $efforts,
        callable $providerFactory,
        callable $humanScorer,
    ): array {
        $this->validateFixtures($fixtures);
        if ($efforts === [] || !array_is_list($efforts) || count($efforts) !== count(array_unique($efforts))) {
            throw new InvalidArgumentException('evaluation efforts must be a unique nonempty list');
        }
        foreach ($efforts as $effort) {
            if (!in_array($effort, self::EFFORTS, true)) {
                throw new InvalidArgumentException('evaluation effort must be none or low');
            }
        }

        $runs = [];
        foreach ($efforts as $effort) {
            $provider = $providerFactory($effort);
            if (!$provider instanceof ThreadIntelligenceProvider) {
                throw new InvalidArgumentException('provider factory must return ThreadIntelligenceProvider');
            }
            foreach ($fixtures as $fixtureIndex => $fixture) {
                $request = $this->request($fixture, $fixtureIndex);
                $this->assertNoPrivateSentinel($fixture, $request);
                $started = hrtime(true);
                $base = $this->baseRun($fixture, $effort, $request);
                try {
                    $result = $provider->generate($request);
                } catch (ThreadIntelligenceProviderException $exception) {
                    $runs[] = $base + [
                        'completion_category' => 'provider_error:' . $exception->safeCode(),
                        'cited_source_count' => 0,
                        'selected_candidate_count' => 0,
                        'ineligible_citation_count' => 0,
                        'ineligible_candidate_count' => 0,
                        'private_transmission_count' => 0,
                        'validation_outcome' => 'not_run',
                        'moderation_outcome' => 'not_run',
                        'input_count' => null,
                        'output_count' => null,
                        'reasoning_count' => null,
                        'cached_count' => null,
                        'duration_ms' => $this->durationMs($started),
                        ...$this->emptyRubric(),
                    ];
                    continue;
                }

                $completion = $result->status === ThreadIntelligenceResult::STATUS_COMPLETED
                    ? 'completed'
                    : 'incomplete:' . ($result->incompleteReason ?? 'unknown');
                if ($result->responseId !== null && $this->responseIdRecorder !== null) {
                    ($this->responseIdRecorder)((string) $fixture['id'], $effort, $result->responseId);
                }
                $common = [
                    'completion_category' => $completion,
                    'input_count' => $result->usage->inputTokens,
                    'output_count' => $result->usage->outputTokens,
                    'reasoning_count' => $result->usage->reasoningTokens,
                    'cached_count' => $result->usage->cachedTokens,
                ];
                if ($result->status !== ThreadIntelligenceResult::STATUS_COMPLETED) {
                    $runs[] = $base + $common + [
                        'cited_source_count' => 0,
                        'selected_candidate_count' => 0,
                        'ineligible_citation_count' => 0,
                        'ineligible_candidate_count' => 0,
                        'private_transmission_count' => 0,
                        'validation_outcome' => 'not_run',
                        'moderation_outcome' => 'not_run',
                        'duration_ms' => $this->durationMs($started),
                        ...$this->emptyRubric(),
                    ];
                    continue;
                }

                try {
                    $validated = $this->validator->validate($result, $request);
                } catch (ThreadIntelligenceProviderException $exception) {
                    $runs[] = $base + $common + [
                        'cited_source_count' => 0,
                        'selected_candidate_count' => 0,
                        'ineligible_citation_count' => 0,
                        'ineligible_candidate_count' => 0,
                        'private_transmission_count' => 0,
                        'validation_outcome' => 'failed:' . $exception->safeCode(),
                        'moderation_outcome' => 'not_run',
                        'duration_ms' => $this->durationMs($started),
                        ...$this->emptyRubric(),
                    ];
                    continue;
                }

                $expectedSources = array_map('intval', $fixture['expected']['source_post_ids']);
                $expectedCandidates = array_map('intval', $fixture['expected']['candidate_thread_ids']);
                $ineligibleSources = array_values(array_diff($validated->sourcePostIds(), $expectedSources));
                $ineligibleCandidates = array_values(array_diff($validated->relatedThreadIds(), $expectedCandidates));
                try {
                    $moderation = $this->moderator->moderate($validated->moderationText());
                } catch (ThreadIntelligenceProviderException $exception) {
                    $runs[] = $base + $common + [
                        'cited_source_count' => count($validated->sourcePostIds()),
                        'selected_candidate_count' => count($validated->relatedThreadIds()),
                        'ineligible_citation_count' => count($ineligibleSources),
                        'ineligible_candidate_count' => count($ineligibleCandidates),
                        'private_transmission_count' => 0,
                        'validation_outcome' => 'passed',
                        'moderation_outcome' => 'failed:' . $exception->safeCode(),
                        'duration_ms' => $this->durationMs($started),
                        ...$this->emptyRubric(),
                    ];
                    continue;
                }
                if ($moderation->flagged) {
                    $runs[] = $base + $common + [
                        'cited_source_count' => count($validated->sourcePostIds()),
                        'selected_candidate_count' => count($validated->relatedThreadIds()),
                        'ineligible_citation_count' => count($ineligibleSources),
                        'ineligible_candidate_count' => count($ineligibleCandidates),
                        'private_transmission_count' => 0,
                        'validation_outcome' => 'passed',
                        'moderation_outcome' => 'flagged',
                        'duration_ms' => $this->durationMs($started),
                        ...$this->emptyRubric(),
                    ];
                    continue;
                }

                $durationMs = $this->durationMs($started);
                $rubric = $this->validateRubric($humanScorer((string) $fixture['id'], $effort, $validated));
                $runs[] = $base + $common + [
                    'cited_source_count' => count($validated->sourcePostIds()),
                    'selected_candidate_count' => count($validated->relatedThreadIds()),
                    'ineligible_citation_count' => count($ineligibleSources),
                    'ineligible_candidate_count' => count($ineligibleCandidates),
                    'private_transmission_count' => 0,
                    'validation_outcome' => 'passed',
                    'moderation_outcome' => 'passed',
                    'duration_ms' => $durationMs,
                    ...$rubric,
                ];
            }
        }

        return ['runs' => $runs, 'decision' => $this->decision($runs, $efforts)];
    }

    /** @param list<array<string,mixed>> $fixtures */
    public function validateFixtures(array $fixtures): void
    {
        if (!array_is_list($fixtures) || $fixtures === []) {
            throw new InvalidArgumentException('evaluation fixtures must be a nonempty list');
        }
        $ids = [];
        foreach ($fixtures as $fixture) {
            if (!is_array($fixture)
                || !is_string($fixture['id'] ?? null) || $fixture['id'] === ''
                || !is_string($fixture['category'] ?? null) || $fixture['category'] === ''
                || !is_string($fixture['title'] ?? null) || $fixture['title'] === ''
                || !is_array($fixture['posts'] ?? null) || !array_is_list($fixture['posts'])
                || !is_array($fixture['candidates'] ?? null) || !array_is_list($fixture['candidates'])
                || !is_array($fixture['expected'] ?? null)
                || !is_array($fixture['expected']['source_post_ids'] ?? null)
                || !array_is_list($fixture['expected']['source_post_ids'])
                || $fixture['expected']['source_post_ids'] === []
                || !is_array($fixture['expected']['candidate_thread_ids'] ?? null)
                || !array_is_list($fixture['expected']['candidate_thread_ids'])
                || !is_array($fixture['private_sentinels'] ?? null)
                || !array_is_list($fixture['private_sentinels'])) {
                throw new InvalidArgumentException('evaluation fixture shape is invalid');
            }
            if (isset($ids[$fixture['id']])) {
                throw new InvalidArgumentException('evaluation fixture IDs must be unique');
            }
            $ids[$fixture['id']] = true;
        }
    }

    /** @param array<string,mixed> $fixture */
    private function request(array $fixture, int $fixtureIndex): ThreadIntelligenceRequest
    {
        $posts = [];
        $postIds = [];
        foreach ($fixture['posts'] as $post) {
            if (($post['eligible'] ?? false) !== true) {
                continue;
            }
            $id = (int) ($post['post_id'] ?? 0);
            if ($id < 1 || isset($postIds[$id])) {
                throw new InvalidArgumentException('eligible fixture post IDs must be unique positive integers');
            }
            $postIds[$id] = true;
            $posts[] = new ThreadIntelligenceEvidencePost(
                $id,
                (string) ($post['created_at'] ?? ''),
                (string) ($post['speaker'] ?? ''),
                (string) ($post['body'] ?? ''),
            );
        }
        $candidates = [];
        $candidateIds = [];
        foreach ($fixture['candidates'] as $candidate) {
            if (($candidate['eligible'] ?? false) !== true) {
                continue;
            }
            $id = (int) ($candidate['thread_id'] ?? 0);
            if ($id < 1 || isset($candidateIds[$id])) {
                continue;
            }
            $candidateIds[$id] = true;
            $tags = array_values(array_filter($candidate['tags'] ?? [], 'is_string'));
            $candidates[] = new ThreadIntelligenceRelatedCandidate(
                $id,
                (string) ($candidate['title'] ?? ''),
                (string) ($candidate['excerpt'] ?? ''),
                $tags,
                count($tags),
                1.0,
                count($candidates) + 1,
                '2026-01-01T00:00:00Z',
            );
        }
        $actualPosts = array_keys($postIds);
        $actualCandidates = array_keys($candidateIds);
        $expectedPosts = array_map('intval', $fixture['expected']['source_post_ids']);
        $expectedCandidates = array_map('intval', $fixture['expected']['candidate_thread_ids']);
        sort($actualPosts, SORT_NUMERIC);
        sort($actualCandidates, SORT_NUMERIC);
        sort($expectedPosts, SORT_NUMERIC);
        sort($expectedCandidates, SORT_NUMERIC);
        if ($actualPosts !== $expectedPosts || $actualCandidates !== $expectedCandidates) {
            throw new InvalidArgumentException('fixture expected IDs must exactly match eligible supplied evidence');
        }

        $baseline = null;
        if (is_array($fixture['baseline'] ?? null)) {
            $baseline = new ThreadIntelligenceBaseline(
                isset($fixture['baseline']['summary_id']) ? (int) $fixture['baseline']['summary_id'] : null,
                isset($fixture['baseline']['version']) ? (int) $fixture['baseline']['version'] : null,
                (string) ($fixture['baseline']['markdown'] ?? ''),
                array_map('intval', $fixture['baseline']['source_post_ids'] ?? []),
            );
        }
        $snapshot = hash('sha256', json_encode([
            'id' => $fixture['id'],
            'posts' => array_map(static fn (ThreadIntelligenceEvidencePost $post): array => [$post->postId, $post->body], $posts),
            'candidates' => array_map(static fn (ThreadIntelligenceRelatedCandidate $candidate): array => [$candidate->threadId, $candidate->title, $candidate->excerpt], $candidates),
        ], JSON_THROW_ON_ERROR));

        return new ThreadIntelligenceRequest(
            900000 + $fixtureIndex + 1,
            (string) $fixture['title'],
            $baseline,
            null,
            $posts,
            $candidates,
            $snapshot,
            ThreadIntelligencePromptBuilder::VERSION,
            0,
            1,
        );
    }

    /** @param array<string,mixed> $fixture */
    private function assertNoPrivateSentinel(array $fixture, ThreadIntelligenceRequest $request): void
    {
        $transmitted = $request->threadTitle . ($request->baseline?->markdown ?? '');
        foreach ($request->posts as $post) {
            $transmitted .= $post->body;
        }
        foreach ($request->candidates as $candidate) {
            $transmitted .= $candidate->title . $candidate->excerpt . implode(' ', $candidate->sharedTags);
        }
        foreach ($fixture['private_sentinels'] as $sentinel) {
            if (!is_string($sentinel) || $sentinel === '') {
                throw new InvalidArgumentException('private sentinels must be nonempty strings');
            }
            if (str_contains($transmitted, $sentinel)) {
                throw new InvalidArgumentException('private sentinel reached the provider request recorder');
            }
        }
    }

    /** @param array<string,mixed> $fixture @return array<string,int|string> */
    private function baseRun(array $fixture, string $effort, ThreadIntelligenceRequest $request): array
    {
        $suppliedSourceIds = array_map(static fn (ThreadIntelligenceEvidencePost $post): int => $post->postId, $request->posts);
        $suppliedSourceIds = array_values(array_unique([...$suppliedSourceIds, ...($request->baseline?->sourcePostIds ?? [])]));
        return [
            'fixture_id' => (string) $fixture['id'],
            'effort' => $effort,
            'eligible_citation_count' => count($fixture['expected']['source_post_ids']),
            'supplied_citation_count' => count($suppliedSourceIds),
            'eligible_candidate_count' => count($fixture['expected']['candidate_thread_ids']),
            'supplied_candidate_count' => count($request->candidates),
        ];
    }

    /** @param array<string,mixed> $rubric @return array{material_claims:int,supported_claims:int,fabricated_decision:bool,quality_pass:bool} */
    private function validateRubric(array $rubric): array
    {
        $keys = array_keys($rubric);
        sort($keys);
        $expected = self::RUBRIC_KEYS;
        sort($expected);
        if ($keys !== $expected
            || !is_int($rubric['material_claims'] ?? null) || $rubric['material_claims'] < 0
            || !is_int($rubric['supported_claims'] ?? null) || $rubric['supported_claims'] < 0
            || $rubric['supported_claims'] > $rubric['material_claims']
            || !is_bool($rubric['fabricated_decision'] ?? null)
            || !is_bool($rubric['quality_pass'] ?? null)) {
            throw new InvalidArgumentException('human rubric must match the exact bounded JSON contract');
        }
        return $rubric;
    }

    /** @return array{material_claims:null,supported_claims:null,fabricated_decision:null,quality_pass:null} */
    private function emptyRubric(): array
    {
        return [
            'material_claims' => null,
            'supported_claims' => null,
            'fabricated_decision' => null,
            'quality_pass' => null,
        ];
    }

    /** @param list<array<string,mixed>> $runs @param list<string> $efforts @return array<string,int|string|bool> */
    private function decision(array $runs, array $efforts): array
    {
        $byEffort = [];
        foreach ($efforts as $effort) {
            $effortRuns = array_values(array_filter($runs, static fn (array $run): bool => $run['effort'] === $effort));
            $material = array_sum(array_map(static fn (array $run): int => (int) ($run['material_claims'] ?? 0), $effortRuns));
            $supported = array_sum(array_map(static fn (array $run): int => (int) ($run['supported_claims'] ?? 0), $effortRuns));
            $byEffort[$effort] = [
                'passed' => $effortRuns !== [] && !in_array(false, array_map(static fn (array $run): bool =>
                    $run['completion_category'] === 'completed'
                    && $run['validation_outcome'] === 'passed'
                    && $run['moderation_outcome'] === 'passed'
                    && (int) $run['private_transmission_count'] === 0
                    && (int) $run['ineligible_citation_count'] === 0
                    && (int) $run['ineligible_candidate_count'] === 0
                    && $run['fabricated_decision'] === false
                    && $run['quality_pass'] === true
                , $effortRuns), true) && ($material === 0 ? $supported === 0 : ($supported / $material) >= 0.9),
                'material' => $material,
                'supported' => $supported,
            ];
        }
        $nonePassed = (bool) ($byEffort['none']['passed'] ?? false);
        $lowPassed = (bool) ($byEffort['low']['passed'] ?? false);
        $needsCeiling = in_array(true, array_map(
            static fn (array $run): bool => $run['completion_category'] === 'incomplete:max_output_tokens',
            $runs,
        ), true);
        $hasNone = in_array('none', $efforts, true);
        $hasLow = in_array('low', $efforts, true);
        $noneNoRegression = $nonePassed
            && (!$hasLow || ($lowPassed && $this->supportRate($byEffort['none']) >= $this->supportRate($byEffort['low'])));
        $selected = $noneNoRegression
            ? 'none'
            : ($lowPassed ? 'low' : ((!$hasLow && $nonePassed) ? 'none' : 'unselected'));
        $passed = !$needsCeiling && $selected !== 'unselected';

        return [
            'passed' => $passed,
            'selected_effort' => $passed ? $selected : 'unselected',
            'ceiling' => $needsCeiling ? 25000 : $this->outputCeiling,
            'none_passed' => $nonePassed,
            'low_passed' => $lowPassed,
            'needs_ceiling_rerun' => $needsCeiling,
        ];
    }

    /** @param array{material:int,supported:int} $aggregate */
    private function supportRate(array $aggregate): float
    {
        return $aggregate['material'] === 0 ? 1.0 : $aggregate['supported'] / $aggregate['material'];
    }

    private function durationMs(int $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 3);
    }
}
