<?php

declare(strict_types=1);

namespace Tests\Integration\ThreadIntelligence;

use App\Repository\TagRepository;
use App\Service\ThreadIntelligence\ThreadIntelligenceBaseline;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePack;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidencePost;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceRequest;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Service\ThreadIntelligence\ValidatedThreadIntelligenceOutput;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use InvalidArgumentException;
use Tests\Support\TestCase;

final class ThreadIntelligenceEvidenceBuilderTest extends TestCase
{
    public function test_initial_pack_includes_every_eligible_post_with_request_local_pseudonyms_only(): void
    {
        $seed = $this->seedThread(10);
        $eligible = array_slice($seed['post_ids'], 0, 8);
        $this->db->run('UPDATE posts SET is_deleted = 1, deleted_at = ? WHERE id = ?', ['2026-07-10 11:00:00', $seed['post_ids'][8]]);
        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$seed['post_ids'][9]]);

        $pack = $this->builder()->build($seed['thread_id'], $this->job());
        $request = $this->builder()->requestForWindow($pack, 0, null);

        self::assertSame($seed['thread_id'], $pack->threadId());
        self::assertNull($pack->baselineSummaryId());
        self::assertSame($eligible, $pack->sourcePostIds());
        self::assertSame([], $pack->candidateThreadIds());
        self::assertMatchesRegularExpression('/\A[0-9a-f]{64}\z/', $pack->snapshotHash());
        self::assertSame(end($eligible), $pack->lastPostId());
        self::assertTrue($pack->fullReconcile());
        self::assertSame(1, $pack->windowCount());
        self::assertSame($eligible, array_column($request->posts, 'postId'));
        self::assertNull($request->baseline);
        self::assertNull($request->carryForward);
        self::assertSame($pack->snapshotHash(), $request->sourceSnapshotHash);

        $speakerByAuthor = [];
        foreach ($request->posts as $index => $post) {
            self::assertInstanceOf(ThreadIntelligenceEvidencePost::class, $post);
            self::assertMatchesRegularExpression('/\Aspeaker-\d+\z/', $post->speaker);
            self::assertMatchesRegularExpression('/\A2026-07-01T00:0\d:\d{2}Z\z/', $post->createdAtUtc);
            if ($post->postId === $seed['anonymous_post_id']) {
                continue;
            }
            $authorId = $seed['post_author_ids'][$index];
            if (isset($speakerByAuthor[$authorId])) {
                self::assertSame($speakerByAuthor[$authorId], $post->speaker);
            } else {
                $speakerByAuthor[$authorId] = $post->speaker;
            }
        }
        $anonymousIndex = array_search($seed['anonymous_post_id'], $eligible, true);
        self::assertNotFalse($anonymousIndex);
        self::assertMatchesRegularExpression('/\Aspeaker-\d+\z/', $request->posts[$anonymousIndex]->speaker);

        $properties = array_map(
            static fn (\ReflectionProperty $property): string => $property->getName(),
            (new \ReflectionClass($request->posts[0]))->getProperties(),
        );
        sort($properties);
        self::assertSame(['body', 'createdAtUtc', 'postId', 'speaker'], $properties);

        foreach ([print_r($pack, true), var_export($pack, true), serialize($pack), json_encode($pack, JSON_THROW_ON_ERROR)] as $debug) {
            self::assertStringNotContainsString('private-account-', $debug);
            self::assertStringNotContainsString('public body sentinel 1', $debug, 'packs must redact raw evidence in debug serialization');
        }
        try {
            clone $pack;
            self::fail('a cloned pack cannot inherit identity-keyed request evidence safely');
        } catch (\Error) {
        }
        try {
            unserialize(serialize($pack));
            self::fail('a metadata-only serialized pack must not be rehydrated as requestable evidence');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('cannot be deserialized', $exception->getMessage());
        }
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM thread_intelligence_generations'));
    }

    public function test_routine_refresh_keeps_the_exact_baseline_and_only_new_or_changed_older_posts(): void
    {
        $seed = $this->seedThread(10);
        $baselineId = $this->publishBaseline($seed, "Exact curator baseline.\n\n- Preserve this byte-for-byte.", 4, [$seed['post_ids'][1], $seed['post_ids'][4]]);
        $this->db->run(
            'UPDATE posts SET body = ?, edited_at = ? WHERE id = ?',
            ['Changed older uncited source.', '2026-07-10 11:00:00', $seed['post_ids'][2]],
        );
        $this->db->run('UPDATE posts SET edited_at = ? WHERE id = ?', ['2026-07-10 09:00:00', $seed['post_ids'][3]]);

        $job = $this->job([
            'last_processed_post_id' => $seed['post_ids'][7],
            'last_generated_at' => '2026-07-10 10:00:00',
            'source_snapshot_hash' => str_repeat('11', 32),
        ]);
        $builder = $this->builder();
        $pack = $builder->build($seed['thread_id'], $job);
        $request = $builder->requestForWindow($pack, 0, null);

        self::assertFalse($pack->fullReconcile());
        self::assertSame($baselineId, $pack->baselineSummaryId());
        self::assertSame(
            [$seed['post_ids'][1], $seed['post_ids'][2], $seed['post_ids'][4], $seed['post_ids'][8], $seed['post_ids'][9]],
            $pack->sourcePostIds(),
            'pack source IDs are the canonical union of baseline citations and incremental evidence',
        );
        self::assertSame(
            [$seed['post_ids'][2], $seed['post_ids'][8], $seed['post_ids'][9]],
            array_column($request->posts, 'postId'),
        );
        self::assertInstanceOf(ThreadIntelligenceBaseline::class, $request->baseline);
        self::assertSame($baselineId, $request->baseline->summaryId);
        self::assertSame(4, $request->baseline->version);
        self::assertSame("Exact curator baseline.\n\n- Preserve this byte-for-byte.", $request->baseline->markdown);
        self::assertSame([$seed['post_ids'][1], $seed['post_ids'][4]], $request->baseline->sourcePostIds);
        self::assertSame($seed['post_ids'][9], $pack->lastPostId());
    }

    public function test_older_cited_changes_and_explicit_reconcile_events_force_full_chronological_evidence(): void
    {
        $seed = $this->seedThread(10);
        $this->publishBaseline($seed, 'Published baseline.', 3, [$seed['post_ids'][1], $seed['post_ids'][4]]);
        $baseJob = $this->job([
            'last_processed_post_id' => $seed['post_ids'][7],
            'last_generated_at' => '2026-07-10 10:00:00',
            'source_snapshot_hash' => str_repeat('22', 32),
        ]);
        $builder = $this->builder();

        self::assertFalse($builder->build($seed['thread_id'], $baseJob)->fullReconcile());

        $this->db->run('UPDATE posts SET body = ?, edited_at = ? WHERE id = ?', ['Edited cited source.', '2026-07-10 11:00:00', $seed['post_ids'][1]]);
        $edited = $builder->build($seed['thread_id'], $baseJob);
        self::assertTrue($edited->fullReconcile());
        self::assertSame($seed['post_ids'], $this->allRequestPostIds($builder, $edited));

        $this->db->run('UPDATE posts SET edited_at = NULL, is_deleted = 1, deleted_at = ? WHERE id = ?', ['2026-07-10 11:30:00', $seed['post_ids'][1]]);
        $deleted = $builder->build($seed['thread_id'], $baseJob);
        self::assertTrue($deleted->fullReconcile());
        self::assertSame(
            array_values(array_diff($seed['post_ids'], [$seed['post_ids'][1]])),
            $this->allRequestPostIds($builder, $deleted),
            'deleted sources force reconciliation but are never transmitted',
        );

        $this->db->run('UPDATE posts SET is_deleted = 0, deleted_at = NULL WHERE id = ?', [$seed['post_ids'][1]]);
        foreach ([
            ThreadIntelligenceQueue::TRIGGER_BOARD_VISIBILITY_CHANGED,
            ThreadIntelligenceQueue::TRIGGER_RECONCILE,
        ] as $trigger) {
            $pack = $builder->build($seed['thread_id'], array_replace($baseJob, ['trigger_code' => $trigger, 'reconcile_required' => 1]));
            self::assertTrue($pack->fullReconcile(), $trigger);
            self::assertSame($seed['post_ids'], $this->allRequestPostIds($builder, $pack), $trigger);
        }
    }

    public function test_the_generation_that_will_publish_each_tenth_ai_version_reconciles_fully(): void
    {
        $seed = $this->seedThread(10);
        for ($version = 1; $version <= 9; $version++) {
            $status = $version === 9 ? 'published' : 'retired';
            $this->db->run(
                "INSERT INTO thread_summaries
                    (thread_id, kind, status, body, body_html, version, author_id, published_at, retired_at, created_at)
                 VALUES (?, 'ai', ?, ?, ?, ?, NULL, ?, ?, UTC_TIMESTAMP())",
                [
                    $seed['thread_id'],
                    $status,
                    'AI version ' . $version,
                    '<p>AI version ' . $version . '</p>',
                    $version,
                    $status === 'published' ? '2026-07-10 09:00:00' : null,
                    $status === 'retired' ? '2026-07-10 09:00:00' : null,
                ],
            );
        }

        $job = $this->job([
            'last_processed_post_id' => $seed['post_ids'][7],
            'last_generated_at' => '2026-07-10 10:00:00',
            'source_snapshot_hash' => str_repeat('33', 32),
        ]);
        $builder = $this->builder();
        $pack = $builder->build($seed['thread_id'], $job);

        self::assertTrue($pack->fullReconcile(), 'nine prior AI versions make this job the tenth AI publication');
        self::assertSame($seed['post_ids'], $this->allRequestPostIds($builder, $pack));
    }

    public function test_four_chronological_windows_are_allowed_and_a_fifth_returns_evidence_too_large(): void
    {
        $builder = $this->builder(7_200, 1_000);
        $fourWindowSeed = $this->seedThread(8, static fn (int $index): string => str_repeat(chr(65 + $index), 900));
        $pack = $builder->build($fourWindowSeed['thread_id'], $this->job());

        self::assertSame(4, $pack->windowCount());
        self::assertSame($fourWindowSeed['post_ids'], $this->allRequestPostIds($builder, $pack));
        for ($index = 0; $index < $pack->windowCount(); $index++) {
            self::assertLessThanOrEqual(7_200, $pack->estimatedInputTokens($index));
        }

        $fiveWindowSeed = $this->seedThread(10, static fn (int $index): string => str_repeat(chr(75 + $index), 900));
        try {
            $builder->build($fiveWindowSeed['thread_id'], $this->job());
            self::fail('safe coverage requiring a fifth provider call must be rejected');
        } catch (ThreadIntelligenceProviderException $exception) {
            self::assertSame(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE, $exception->safeCode());
            self::assertSame('evidence_too_large', $exception->getMessage());
        }
    }

    public function test_estimator_is_conservative_and_rejects_a_single_slice_naive_character_division_would_accept(): void
    {
        $seed = $this->seedThread(8, static fn (int $index): string => $index === 0
            ? 'OVERSIZE-PRIVATE-SENTINEL-' . str_repeat('z', 7_000)
            : 'short evidence');

        try {
            $this->builder(7_200, 1_000)->build($seed['thread_id'], $this->job());
            self::fail('one-byte-per-token conservative estimation must reject the oversize evidence item');
        } catch (ThreadIntelligenceProviderException $exception) {
            self::assertSame(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE, $exception->safeCode());
            self::assertStringNotContainsString('OVERSIZE-PRIVATE-SENTINEL', $exception->getMessage());
        }
    }

    public function test_estimator_reserves_the_configured_output_ceiling_for_future_carry_forward(): void
    {
        $seed = $this->seedThread(8);

        $smallCarry = $this->builder(32_000, 1_000)->build($seed['thread_id'], $this->job());
        $largeCarry = $this->builder(32_000, 5_000)->build($seed['thread_id'], $this->job());

        self::assertSame(1, $smallCarry->windowCount());
        self::assertSame(1, $largeCarry->windowCount());
        self::assertSame(
            4_000,
            $largeCarry->estimatedInputTokens(0) - $smallCarry->estimatedInputTokens(0),
            'the full possible validated output is reserved before later windows exist',
        );
    }

    public function test_window_requests_keep_one_immutable_baseline_and_replace_only_validated_carry_forward(): void
    {
        $seed = $this->seedThread(8, static fn (int $index): string => str_repeat(chr(65 + $index), 900));
        $baselineId = $this->publishBaseline(
            $seed,
            "Curator baseline.\n\n### Key points\n\n- Immutable curator text.",
            6,
            [$seed['post_ids'][0], $seed['post_ids'][1]],
        );
        $builder = $this->builder(7_800, 1_000);
        $pack = $builder->build($seed['thread_id'], $this->job());
        self::assertGreaterThanOrEqual(3, $pack->windowCount());

        $zero = $builder->requestForWindow($pack, 0, null);
        $firstOutput = $this->validatedFor($zero, 'First validated intermediate.');
        $one = $builder->requestForWindow($pack, 1, $firstOutput);
        $secondOutput = $this->validatedFor($one, 'Second validated intermediate.');
        $two = $builder->requestForWindow($pack, 2, $secondOutput);

        self::assertSame($baselineId, $zero->baseline?->summaryId);
        self::assertSame($zero->baseline, $one->baseline);
        self::assertSame($one->baseline, $two->baseline, 'every request references the exact stored baseline object');
        self::assertSame(serialize($zero->baseline), serialize($two->baseline));
        self::assertNull($zero->carryForward);
        self::assertSame('First validated intermediate.', $one->carryForward?->overview);
        self::assertSame('Second validated intermediate.', $two->carryForward?->overview);
        self::assertSame(
            "Curator baseline.\n\n### Key points\n\n- Immutable curator text.",
            $two->baseline?->markdown,
        );

        $debug = print_r($pack, true) . serialize($pack);
        self::assertStringNotContainsString('First validated intermediate.', $debug);
        self::assertStringNotContainsString('Second validated intermediate.', $debug);

        try {
            $builder->requestForWindow($pack, 1, null);
            self::fail('later windows require a validated carry-forward');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('carry-forward', $exception->getMessage());
        }
        foreach ([-1, $pack->windowCount()] as $invalidIndex) {
            try {
                $builder->requestForWindow($pack, $invalidIndex, null);
                self::fail('out-of-range windows must be rejected');
            } catch (InvalidArgumentException $exception) {
                self::assertStringContainsString('window', $exception->getMessage());
            }
        }
    }

    public function test_evidence_windows_follow_created_time_then_id_not_insertion_id(): void
    {
        $seed = $this->seedThread(8, static fn (int $index): string => str_repeat(chr(65 + $index), 900));
        $chronologicalIds = [
            $seed['post_ids'][3],
            $seed['post_ids'][1],
            $seed['post_ids'][6],
            $seed['post_ids'][0],
            $seed['post_ids'][7],
            $seed['post_ids'][2],
            $seed['post_ids'][4],
            $seed['post_ids'][5],
        ];
        foreach ($chronologicalIds as $position => $postId) {
            $this->db->run(
                'UPDATE posts SET created_at = ? WHERE id = ?',
                [sprintf('2026-07-02 00:00:%02d', $position), $postId],
            );
        }

        $builder = $this->builder(7_200, 1_000);
        $pack = $builder->build($seed['thread_id'], $this->job());

        self::assertSame(4, $pack->windowCount());
        self::assertSame($chronologicalIds, $this->allRequestPostIds($builder, $pack));
    }

    public function test_snapshot_changes_for_sources_visibility_baseline_and_candidates_but_not_accounts(): void
    {
        $seed = $this->seedThread(8);
        $baselineId = $this->publishBaseline($seed, 'Stable baseline body.', 2, [$seed['post_ids'][0]]);
        $builder = $this->builder();
        $job = $this->job();
        $original = $builder->build($seed['thread_id'], $job)->snapshotHash();

        $this->db->run(
            'UPDATE users SET username = ?, email = ?, display_name = ? WHERE id = ?',
            ['renamed-account', 'renamed-private@example.test', 'Renamed Private Display', $seed['author_ids'][0]],
        );
        self::assertSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash(), 'account/display changes are excluded');

        $originalBody = (string) $this->db->fetchValue('SELECT body FROM posts WHERE id = ?', [$seed['post_ids'][0]]);
        $this->db->run('UPDATE posts SET body = ?, edited_at = ? WHERE id = ?', ['Changed public evidence.', '2026-07-10 11:00:00', $seed['post_ids'][0]]);
        self::assertNotSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash(), 'body/hash and update time are included');
        $this->db->run('UPDATE posts SET body = ?, edited_at = NULL WHERE id = ?', [$originalBody, $seed['post_ids'][0]]);
        self::assertSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash());

        $this->db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$seed['post_ids'][0]]);
        self::assertNotSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash(), 'source eligibility/state is included');
        $this->db->run('UPDATE posts SET is_pending = 0 WHERE id = ?', [$seed['post_ids'][0]]);

        $this->db->run("UPDATE boards SET visibility = 'hidden' WHERE id = ?", [$seed['board_id']]);
        $hidden = $builder->build($seed['thread_id'], $job);
        self::assertNotSame($original, $hidden->snapshotHash(), 'board visibility is included');
        try {
            $builder->requestForWindow($hidden, 0, null);
            self::fail('a nonpublic local snapshot must never become a provider request');
        } catch (ThreadIntelligenceProviderException $exception) {
            self::assertSame(ThreadIntelligenceFailureCode::VALIDATION_FAILED, $exception->safeCode());
        }
        $this->db->run("UPDATE boards SET visibility = 'public' WHERE id = ?", [$seed['board_id']]);
        self::assertSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash());

        $this->db->run('UPDATE thread_summaries SET body = ?, version = 3 WHERE id = ?', ['Changed baseline body.', $baselineId]);
        self::assertNotSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash(), 'baseline ID/version/body hash/source IDs are included');
        $this->db->run('UPDATE thread_summaries SET body = ?, version = 2 WHERE id = ?', ['Stable baseline body.', $baselineId]);
        self::assertSame($original, $builder->build($seed['thread_id'], $job)->snapshotHash());

        $candidate = $this->makeThread($seed['board'], $seed['authors'][0], 'Local deterministic candidate', 'Candidate opener.');
        $withCandidate = $builder->build($seed['thread_id'], $job)->snapshotHash();
        self::assertNotSame($original, $withCandidate, 'candidate IDs are included');

        $tags = new TagRepository($this->db);
        $sharedTag = $tags->create('snapshot-tag', 'Snapshot tag', null, $seed['author_ids'][0]);
        $tags->setForThread($seed['thread_id'], [$sharedTag], $seed['author_ids'][0]);
        $tags->setForThread((int) $candidate['thread_id'], [$sharedTag], $seed['author_ids'][0]);
        self::assertNotSame($withCandidate, $builder->build($seed['thread_id'], $job)->snapshotHash(), 'candidate scores are included');
    }

    /** @param array<string,mixed> $overrides @return array<string,mixed> */
    private function job(array $overrides = []): array
    {
        return $overrides + [
            'thread_id' => 0,
            'trigger_code' => ThreadIntelligenceQueue::TRIGGER_POST_CREATED,
            'last_processed_post_id' => null,
            'last_generated_at' => null,
            'source_snapshot_hash' => null,
            'reconcile_required' => 0,
        ];
    }

    private function builder(int $maxInputTokens = 32_000, int $maxOutputTokens = 16_000): ThreadIntelligenceEvidenceBuilder
    {
        $config = ThreadIntelligenceConfig::fromArray([
            'max_input_tokens' => $maxInputTokens,
            'max_output_tokens' => $maxOutputTokens,
        ]);
        $finder = new ThreadIntelligenceCandidateFinder($this->db);
        return new ThreadIntelligenceEvidenceBuilder($this->db, $finder, $config);
    }

    /**
     * @param null|callable(int):string $bodyForIndex
     * @return array{
     *   thread_id:int,board_id:int,board:array<string,mixed>,authors:list<array<string,mixed>>,
     *   author_ids:list<int>,post_ids:list<int>,post_author_ids:list<int>,anonymous_post_id:int
     * }
     */
    private function seedThread(int $postCount, ?callable $bodyForIndex = null): array
    {
        $authors = [
            $this->makeUser(['email' => 'private-account-' . bin2hex(random_bytes(4)) . '@example.test']),
            $this->makeUser(),
            $this->makeUser(),
        ];
        $category = $this->makeCategory();
        $board = $this->makeBoard($category, ['allow_anonymous' => 1]);
        $body = $bodyForIndex === null ? 'public body sentinel 1' : $bodyForIndex(0);
        $thread = $this->makeThread($board, $authors[0], 'Evidence thread', $body);
        $threadId = (int) $thread['thread_id'];
        $postIds = [(int) $this->db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1', [$threadId])];
        $postAuthorIds = [(int) $authors[0]['id']];
        $anonymousPostId = 0;

        for ($index = 1; $index < $postCount; $index++) {
            $authorIndex = $index % count($authors);
            $input = [
                'body' => $bodyForIndex === null ? 'public body sentinel ' . ($index + 1) : $bodyForIndex($index),
            ];
            if ($index === 2) {
                $input['is_anonymous'] = 1;
            }
            $postId = $this->posting()->reply($this->userEntity($authors[$authorIndex]), $threadId, $input);
            $postIds[] = $postId;
            $postAuthorIds[] = (int) $authors[$authorIndex]['id'];
            if ($index === 2) {
                $anonymousPostId = $postId;
            }
        }

        foreach ($postIds as $index => $postId) {
            $this->db->run(
                'UPDATE posts SET created_at = ? WHERE id = ?',
                [sprintf('2026-07-01 00:%02d:%02d', intdiv($index, 60), $index % 60), $postId],
            );
        }
        $this->db->run('UPDATE threads SET last_post_id = ?, last_post_at = ? WHERE id = ?', [end($postIds), '2026-07-01 01:00:00', $threadId]);

        return [
            'thread_id' => $threadId,
            'board_id' => (int) $board['id'],
            'board' => $board,
            'authors' => $authors,
            'author_ids' => array_map(static fn (array $author): int => (int) $author['id'], $authors),
            'post_ids' => $postIds,
            'post_author_ids' => $postAuthorIds,
            'anonymous_post_id' => $anonymousPostId,
        ];
    }

    /** @param array<string,mixed> $seed @param list<int> $sourceIds */
    private function publishBaseline(array $seed, string $body, int $version, array $sourceIds): int
    {
        $summaryId = $this->db->insert(
            "INSERT INTO thread_summaries
                (thread_id, kind, status, body, body_html, version, author_id, published_at, created_at)
             VALUES (?, 'manual', 'published', ?, ?, ?, ?, '2026-07-10 09:00:00', '2026-07-10 09:00:00')",
            [$seed['thread_id'], $body, '<p>baseline</p>', $version, $seed['author_ids'][0]],
        );
        foreach ($sourceIds as $sourceId) {
            $this->db->run('INSERT INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)', [$summaryId, $sourceId]);
        }
        return $summaryId;
    }

    /** @return list<int> */
    private function allRequestPostIds(ThreadIntelligenceEvidenceBuilder $builder, ThreadIntelligenceEvidencePack $pack): array
    {
        $ids = [];
        $carry = null;
        for ($index = 0; $index < $pack->windowCount(); $index++) {
            $request = $builder->requestForWindow($pack, $index, $carry);
            $ids = [...$ids, ...array_column($request->posts, 'postId')];
            $carry = $this->validatedFor($request, 'Window ' . ($index + 1) . ' validated.');
        }
        return $ids;
    }

    private function validatedFor(ThreadIntelligenceRequest $request, string $overview): ValidatedThreadIntelligenceOutput
    {
        $sourceId = $request->posts[0]->postId
            ?? $request->baseline?->sourcePostIds[0]
            ?? $request->carryForward?->sourcePostIds[0]
            ?? throw new \RuntimeException('validated fixture requires one source');
        $result = new ThreadIntelligenceResult([
            'overview' => ['markdown' => $overview, 'source_post_ids' => [$sourceId]],
            'key_points' => [
                ['markdown' => 'Validated point one.', 'source_post_ids' => [$sourceId]],
                ['markdown' => 'Validated point two.', 'source_post_ids' => [$sourceId]],
            ],
            'open_questions' => [
                ['markdown' => 'Validated question one.', 'source_post_ids' => [$sourceId]],
            ],
            'related_topics' => [],
        ], 'response-fixture', 'completed', null, new ThreadIntelligenceUsage(null, null, null, null));

        return (new ThreadIntelligenceOutputValidator(new Markdown(new HtmlSanitizer())))->validate($result, $request);
    }
}
