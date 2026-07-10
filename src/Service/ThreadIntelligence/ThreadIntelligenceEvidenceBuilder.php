<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use InvalidArgumentException;
use JsonException;

/** Builds one stable, bounded local evidence plan and its iterative requests. */
final class ThreadIntelligenceEvidenceBuilder
{
    /**
     * Fixed conservative allowance for source-controlled instructions, strict
     * schema/envelope, and JSON structure. The configured output ceiling is
     * reserved separately because any prior validated output may become the
     * next window's carry-forward. Variable evidence is estimated at one token
     * per UTF-8 byte; because a tokenizer token cannot represent less than one
     * input byte, this safely overestimates ordinary model tokenization without
     * provider coupling.
     */
    private const FIXED_REQUEST_TOKEN_ALLOWANCE = 4_096;
    private const MAX_WINDOWS = 4;

    private const RECONCILE_TRIGGERS = [
        ThreadIntelligenceQueue::TRIGGER_BOARD_VISIBILITY_CHANGED,
        ThreadIntelligenceQueue::TRIGGER_RECONCILE,
    ];

    public function __construct(
        private readonly Database $db,
        private readonly ThreadIntelligenceCandidateFinder $candidates,
        private readonly ThreadIntelligenceConfig $config,
    ) {
    }

    /** @param array<string,mixed> $job */
    public function build(int $threadId, array $job): ThreadIntelligenceEvidencePack
    {
        $thread = $this->db->fetch(
            'SELECT t.id, t.title, t.is_deleted, t.is_pending, b.visibility
             FROM threads t
             JOIN boards b ON b.id = t.board_id
             WHERE t.id = ?',
            [$threadId],
        );
        if ($thread === null) {
            throw new InvalidArgumentException('thread intelligence evidence requires an existing thread');
        }

        $posts = $this->db->fetchAll(
            'SELECT id, user_id, body, is_anonymous, is_deleted, is_pending,
                    created_at, edited_at, deleted_at
             FROM posts
             WHERE thread_id = ?
             ORDER BY created_at ASC, id ASC',
            [$threadId],
        );
        $eligiblePosts = array_values(array_filter(
            $posts,
            static fn (array $post): bool => (int) $post['is_deleted'] === 0 && (int) $post['is_pending'] === 0,
        ));

        $baseline = $this->baseline($threadId);
        $candidateList = $this->candidates->find($threadId);
        $snapshotHash = $this->snapshotHash($thread, $posts, $baseline, $candidateList);

        $checkpoint = $this->positiveIntOrNull($job['last_processed_post_id'] ?? null);
        $lastGeneratedAt = $this->storedTimeOrNull($job['last_generated_at'] ?? null);
        $fullReconcile = $checkpoint === null
            || (int) ($job['reconcile_required'] ?? 0) === 1
            || in_array((string) ($job['trigger_code'] ?? ''), self::RECONCILE_TRIGGERS, true)
            || $this->olderCitedSourceChanged($baseline, $posts, $checkpoint, $lastGeneratedAt)
            || $this->nextPublicationIsTenthAiVersion($threadId);

        $selectedRows = $fullReconcile
            ? $eligiblePosts
            : array_values(array_filter(
                $eligiblePosts,
                fn (array $post): bool => (int) $post['id'] > (int) $checkpoint
                    || $this->changedAfter((string) ($post['edited_at'] ?? ''), $lastGeneratedAt),
            ));

        [$slices, $estimates] = $this->slice($thread, $baseline, $candidateList, $selectedRows);
        $sourcePostIds = array_map(static fn (array $post): int => (int) $post['id'], $selectedRows);
        if ($baseline !== null) {
            $sourcePostIds = [...$sourcePostIds, ...$baseline->sourcePostIds];
        }
        $sourcePostIds = array_values(array_unique($sourcePostIds));
        sort($sourcePostIds);

        $eligibleIds = array_map(static fn (array $post): int => (int) $post['id'], $eligiblePosts);
        $lastPostId = $eligibleIds === [] ? 0 : max($eligibleIds);
        $requestEligible = (string) $thread['visibility'] === 'public'
            && (int) $thread['is_deleted'] === 0
            && (int) $thread['is_pending'] === 0;

        return new ThreadIntelligenceEvidencePack(
            threadId: $threadId,
            threadTitle: (string) $thread['title'],
            baseline: $baseline,
            sourcePostIds: $sourcePostIds,
            candidates: $candidateList,
            snapshotHash: $snapshotHash,
            lastPostId: $lastPostId,
            fullReconcile: $fullReconcile,
            boardVisibility: $requestEligible ? 'public' : 'ineligible',
            slices: $slices,
            estimatedInputTokens: $estimates,
        );
    }

    public function requestForWindow(
        ThreadIntelligenceEvidencePack $pack,
        int $windowIndex,
        ?ValidatedThreadIntelligenceOutput $carryForward,
    ): ThreadIntelligenceRequest {
        return $pack->request($windowIndex, $carryForward);
    }

    private function baseline(int $threadId): ?ThreadIntelligenceBaseline
    {
        $row = $this->db->fetch(
            "SELECT id, version, body
             FROM thread_summaries
             WHERE thread_id = ? AND status = 'published'
             ORDER BY version DESC, id DESC
             LIMIT 1",
            [$threadId],
        );
        if ($row === null) {
            return null;
        }
        $sourceIds = array_map(
            static fn (array $source): int => (int) $source['post_id'],
            $this->db->fetchAll(
                'SELECT post_id FROM thread_summary_sources WHERE summary_id = ? ORDER BY post_id ASC',
                [(int) $row['id']],
            ),
        );
        return new ThreadIntelligenceBaseline(
            (int) $row['id'],
            (int) $row['version'],
            (string) $row['body'],
            $sourceIds,
        );
    }

    /**
     * @param array<string,mixed> $thread
     * @param list<array<string,mixed>> $posts
     * @param list<ThreadIntelligenceRelatedCandidate> $candidates
     */
    private function snapshotHash(
        array $thread,
        array $posts,
        ?ThreadIntelligenceBaseline $baseline,
        array $candidates,
    ): string {
        $canonical = [
            'thread' => [
                'id' => (int) $thread['id'],
                'title_hash' => hash('sha256', (string) $thread['title']),
                'is_deleted' => (int) $thread['is_deleted'],
                'is_pending' => (int) $thread['is_pending'],
                'board_visibility' => (string) $thread['visibility'],
            ],
            'sources' => array_map(static fn (array $post): array => [
                'id' => (int) $post['id'],
                'eligible' => (int) $post['is_deleted'] === 0 && (int) $post['is_pending'] === 0,
                'body_hash' => hash('sha256', (string) $post['body']),
                'is_anonymous' => (int) $post['is_anonymous'],
                'is_deleted' => (int) $post['is_deleted'],
                'is_pending' => (int) $post['is_pending'],
                'created_at' => (string) $post['created_at'],
                'edited_at' => $post['edited_at'] === null ? null : (string) $post['edited_at'],
                'deleted_at' => $post['deleted_at'] === null ? null : (string) $post['deleted_at'],
            ], $posts),
            'baseline' => $baseline === null ? null : [
                'id' => $baseline->summaryId,
                'version' => $baseline->version,
                'body_hash' => hash('sha256', $baseline->markdown),
                'source_post_ids' => $baseline->sourcePostIds,
            ],
            'candidates' => array_map(static fn (ThreadIntelligenceRelatedCandidate $candidate): array => [
                'thread_id' => $candidate->threadId,
                'title_hash' => hash('sha256', $candidate->title),
                'excerpt_hash' => hash('sha256', $candidate->excerpt),
                'shared_tags' => $candidate->sharedTags,
                'shared_tag_count' => $candidate->sharedTagCount,
                'relevance' => sprintf('%.12F', $candidate->relevance),
                'rank' => $candidate->rank,
                'last_activity_at' => $candidate->lastActivityAtUtc,
            ], $candidates),
        ];

        try {
            $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::VALIDATION_FAILED);
        }
        return hash('sha256', $encoded);
    }

    /**
     * @param list<array<string,mixed>> $posts
     */
    private function olderCitedSourceChanged(
        ?ThreadIntelligenceBaseline $baseline,
        array $posts,
        ?int $checkpoint,
        ?string $lastGeneratedAt,
    ): bool {
        if ($baseline === null || $checkpoint === null) {
            return false;
        }
        $byId = [];
        foreach ($posts as $post) {
            $byId[(int) $post['id']] = $post;
        }
        foreach ($baseline->sourcePostIds as $sourceId) {
            if ($sourceId > $checkpoint) {
                continue;
            }
            $post = $byId[$sourceId] ?? null;
            if ($post === null || (int) $post['is_deleted'] === 1 || (int) $post['is_pending'] === 1) {
                return true;
            }
            if ($this->changedAfter((string) ($post['edited_at'] ?? ''), $lastGeneratedAt)) {
                return true;
            }
        }
        return false;
    }

    private function nextPublicationIsTenthAiVersion(int $threadId): bool
    {
        $count = (int) $this->db->fetchValue(
            "SELECT COUNT(*) FROM thread_summaries WHERE thread_id = ? AND kind = 'ai'",
            [$threadId],
        );
        return $count > 0 && ($count + 1) % 10 === 0;
    }

    /**
     * @param array<string,mixed> $thread
     * @param list<ThreadIntelligenceRelatedCandidate> $candidates
     * @param list<array<string,mixed>> $rows
     * @return array{0:list<list<ThreadIntelligenceEvidencePost>>,1:list<int>}
     */
    private function slice(
        array $thread,
        ?ThreadIntelligenceBaseline $baseline,
        array $candidates,
        array $rows,
    ): array {
        $baseEstimate = self::FIXED_REQUEST_TOKEN_ALLOWANCE
            + $this->config->maxOutputTokens()
            + $this->encodedBytes(['thread_id' => (int) $thread['id'], 'title' => (string) $thread['title']])
            + $this->encodedBytes($baseline === null ? null : [
                'summary_id' => $baseline->summaryId,
                'version' => $baseline->version,
                'markdown' => $baseline->markdown,
                'source_post_ids' => $baseline->sourcePostIds,
            ])
            + $this->encodedBytes(array_map(static fn (ThreadIntelligenceRelatedCandidate $candidate): array => [
                'thread_id' => $candidate->threadId,
                'title' => $candidate->title,
                'excerpt' => $candidate->excerpt,
                'shared_tags' => $candidate->sharedTags,
                'shared_tag_count' => $candidate->sharedTagCount,
                'relevance' => $candidate->relevance,
                'rank' => $candidate->rank,
                'last_activity_at' => $candidate->lastActivityAtUtc,
            ], $candidates));
        $ceiling = $this->config->maxInputTokens();
        if ($baseEstimate > $ceiling) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE);
        }

        $rowSlices = [];
        $rowEstimates = [];
        $current = [];
        $currentEstimate = $baseEstimate;
        foreach ($rows as $row) {
            $cost = $this->postEstimate($row);
            if ($baseEstimate + $cost > $ceiling) {
                throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE);
            }
            if ($current !== [] && $currentEstimate + $cost > $ceiling) {
                $rowSlices[] = $current;
                $rowEstimates[] = $currentEstimate;
                if (count($rowSlices) >= self::MAX_WINDOWS) {
                    throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE);
                }
                $current = [];
                $currentEstimate = $baseEstimate;
            }
            $current[] = $row;
            $currentEstimate += $cost;
        }
        if ($current !== [] || $rowSlices === []) {
            $rowSlices[] = $current;
            $rowEstimates[] = $currentEstimate;
        }
        if (count($rowSlices) > self::MAX_WINDOWS) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::EVIDENCE_TOO_LARGE);
        }

        $slices = array_map(fn (array $slice): array => $this->evidencePosts($slice), $rowSlices);
        return [$slices, $rowEstimates];
    }

    /** @param list<array<string,mixed>> $rows @return list<ThreadIntelligenceEvidencePost> */
    private function evidencePosts(array $rows): array
    {
        $speakers = [];
        $nextSpeaker = 1;
        $posts = [];
        foreach ($rows as $row) {
            // Never correlate an anonymous post with the same account's named
            // contribution inside a request-local speaker map.
            $speakerKey = (int) $row['is_anonymous'] === 1
                ? 'anonymous-post-' . (int) $row['id']
                : 'account-' . (int) $row['user_id'];
            if (!isset($speakers[$speakerKey])) {
                $speakers[$speakerKey] = 'speaker-' . $nextSpeaker++;
            }
            $posts[] = new ThreadIntelligenceEvidencePost(
                (int) $row['id'],
                str_replace(' ', 'T', (string) $row['created_at']) . 'Z',
                $speakers[$speakerKey],
                (string) $row['body'],
            );
        }
        return $posts;
    }

    /** @param array<string,mixed> $row */
    private function postEstimate(array $row): int
    {
        return 32 + $this->encodedBytes([
            'id' => (int) $row['id'],
            'at' => str_replace(' ', 'T', (string) $row['created_at']) . 'Z',
            'speaker' => 'speaker-999999',
            'body' => (string) $row['body'],
        ]);
    }

    private function encodedBytes(mixed $value): int
    {
        try {
            return strlen(json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        } catch (JsonException) {
            throw new ThreadIntelligenceProviderException(ThreadIntelligenceFailureCode::VALIDATION_FAILED);
        }
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }
        $parsed = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $parsed === false ? null : (int) $parsed;
    }

    private function storedTimeOrNull(mixed $value): ?string
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        if (preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $value) !== 1) {
            throw new InvalidArgumentException('stored evidence checkpoint time is invalid');
        }
        return $value;
    }

    private function changedAfter(string $editedAt, ?string $lastGeneratedAt): bool
    {
        return $editedAt !== '' && $lastGeneratedAt !== null && strcmp($editedAt, $lastGeneratedAt) > 0;
    }
}
