<?php

declare(strict_types=1);

namespace App\Service\ThreadIntelligence;

use App\Core\Database;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Security\BoardPolicy;
use DateTimeImmutable;
use DateTimeZone;

/** Builds the complete member-safe Living Brief model at current read policy. */
final class ThreadIntelligenceViewService
{
    private const DETERMINISTIC_REASONS = [
        'tag' => 'Shared topic tags',
        'search' => 'Similar discussion',
    ];

    public function __construct(
        private readonly Database $db,
        private readonly BoardMemberRepository $members,
        private readonly BoardPolicy $policy,
        private readonly ThreadIntelligenceEligibility $eligibility,
        private readonly ThreadIntelligenceJobRepository $jobs,
    ) {
    }

    /**
     * @return array{
     *   living_brief:?array<string,mixed>,
     *   sources:list<array<string,mixed>>,
     *   related:list<array<string,mixed>>,
     *   fallback_related:list<array<string,mixed>>,
     *   history:list<array<string,mixed>>,
     *   refresh:array<string,mixed>,
     *   automation_paused:bool
     * }
     */
    public function forThread(int $threadId, ?User $viewer): array
    {
        $job = $this->jobs->find($threadId);
        $empty = $this->emptyModel($threadId, $job);
        $thread = $this->db->fetch(
            'SELECT t.id, t.is_deleted, t.is_pending, t.board_id,
                    b.visibility AS board_visibility
             FROM threads t JOIN boards b ON b.id = t.board_id
             WHERE t.id = ?',
            [$threadId],
        );
        if ($thread === null
            || (int) $thread['is_deleted'] !== 0
            || (int) $thread['is_pending'] !== 0
            || !$this->canReadBoard((int) $thread['board_id'], (string) $thread['board_visibility'], $viewer)) {
            return $empty;
        }

        $summary = $this->db->fetch(
            'SELECT s.*, u.username AS author_username
             FROM thread_summaries s
             LEFT JOIN users u ON u.id = s.author_id
             WHERE s.thread_id = ? AND s.status = \'published\'
             ORDER BY s.version DESC, s.id DESC LIMIT 1',
            [$threadId],
        );
        $sources = [];
        $livingBrief = null;
        $activeGenerationId = null;
        $aiOverlayAllowed = false;

        if ($summary !== null) {
            $lineage = $this->lineage($summary);
            $aiSummaryId = $lineage['ai_summary_id'];
            $activeGenerationId = $aiSummaryId === null ? null : $this->publishingGenerationId($aiSummaryId);
            $sources = $this->sources((int) $summary['id'], $viewer);

            $isAi = (string) $summary['kind'] === 'ai';
            $aiCurrent = !$isAi || (
                (string) $thread['board_visibility'] === 'public'
                && $activeGenerationId !== null
                && $this->aiSourcesAreCurrent($activeGenerationId, $threadId, $sources)
            );
            if ($aiCurrent) {
                $livingBrief = $this->brief($summary, $lineage['has_ai_ancestor']);
                $aiOverlayAllowed = $activeGenerationId !== null;
            } else {
                $sources = [];
                $activeGenerationId = null;
            }
        }

        $relationships = $this->relationshipRows($threadId, $viewer);
        $related = [];
        $fallback = [];
        if ($livingBrief !== null) {
            $related = $this->selectRelated($relationships, $activeGenerationId, $aiOverlayAllowed);
        } else {
            $fallback = $this->selectFallback($relationships);
        }

        return [
            'living_brief' => $livingBrief,
            'sources' => $sources,
            'related' => $related,
            'fallback_related' => $fallback,
            'history' => $this->history($threadId),
            'refresh' => $empty['refresh'],
            'automation_paused' => (int) ($job['automation_paused'] ?? 0) === 1,
        ];
    }

    /** @param array<string,mixed>|null $job @return array<string,mixed> */
    private function emptyModel(int $threadId, ?array $job): array
    {
        $decision = $this->eligibility->forExplicitRefresh(
            $threadId,
            new DateTimeImmutable('now', new DateTimeZone('UTC')),
        );
        return [
            'living_brief' => null,
            'sources' => [],
            'related' => [],
            'fallback_related' => [],
            'history' => [],
            'refresh' => [
                'eligible' => $decision->eligible,
                'code' => $decision->code,
                'message' => $decision->message,
                'next_eligible_at' => $decision->nextEligibleAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                'next_eligible_at_utc' => $decision->nextEligibleAt?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            ],
            'automation_paused' => (int) ($job['automation_paused'] ?? 0) === 1,
        ];
    }

    private function canReadBoard(int $boardId, string $visibility, ?User $viewer): bool
    {
        $member = $viewer !== null && $this->members->isMember($boardId, $viewer->id());
        return $this->policy->canRead(['visibility' => $visibility], $viewer, $member);
    }

    /** @param array<string,mixed> $summary @return array{has_ai_ancestor:bool,ai_summary_id:?int} */
    private function lineage(array $summary): array
    {
        $seen = [];
        $current = $summary;
        $hasAi = false;
        $aiSummaryId = null;
        while (true) {
            $id = (int) $current['id'];
            if (isset($seen[$id])) {
                break;
            }
            $seen[$id] = true;
            if ((string) $current['kind'] === 'ai') {
                $hasAi = true;
                $aiSummaryId ??= $id;
            }
            $parentId = (int) ($current['parent_summary_id'] ?? 0);
            if ($parentId < 1) {
                break;
            }
            $parent = $this->db->fetch(
                'SELECT id, kind, parent_summary_id FROM thread_summaries WHERE id = ?',
                [$parentId],
            );
            if ($parent === null) {
                break;
            }
            $current = $parent;
        }
        return ['has_ai_ancestor' => $hasAi, 'ai_summary_id' => $aiSummaryId];
    }

    private function publishingGenerationId(int $summaryId): ?int
    {
        $value = $this->db->fetchValue(
            "SELECT id FROM thread_intelligence_generations
             WHERE published_summary_id = ? AND status = 'published'
             ORDER BY id DESC LIMIT 1",
            [$summaryId],
        );
        return $value === false ? null : (int) $value;
    }

    /** @return list<array<string,mixed>> */
    private function sources(int $summaryId, ?User $viewer): array
    {
        $rows = $this->db->fetchAll(
            'SELECT p.id, p.thread_id, p.created_at, p.is_deleted, p.is_pending, p.is_anonymous,
                    t.slug AS thread_slug, t.is_deleted AS thread_deleted, t.is_pending AS thread_pending,
                    b.id AS board_id, b.visibility AS board_visibility,
                    u.username AS author_username, u.display_name AS author_display_name
             FROM thread_summary_sources ss
             JOIN posts p ON p.id = ss.post_id
             JOIN threads t ON t.id = p.thread_id
             JOIN boards b ON b.id = t.board_id
             LEFT JOIN users u ON u.id = p.user_id
             WHERE ss.summary_id = ?
             ORDER BY p.id ASC',
            [$summaryId],
        );
        $safe = [];
        foreach ($rows as $row) {
            if ((int) $row['is_deleted'] !== 0
                || (int) $row['is_pending'] !== 0
                || (int) $row['thread_deleted'] !== 0
                || (int) $row['thread_pending'] !== 0
                || !$this->canReadBoard((int) $row['board_id'], (string) $row['board_visibility'], $viewer)) {
                continue;
            }
            if ((int) $row['is_anonymous'] === 1) {
                $row['author_username'] = null;
                $row['author_display_name'] = null;
            }
            $safe[] = $row;
        }
        return $safe;
    }

    /** @param list<array<string,mixed>> $sources */
    private function aiSourcesAreCurrent(int $generationId, int $threadId, array $sources): bool
    {
        $encoded = $this->db->fetchValue(
            'SELECT source_post_ids FROM thread_intelligence_generations WHERE id = ?',
            [$generationId],
        );
        if (!is_string($encoded)) {
            return false;
        }
        $expected = json_decode($encoded, true);
        if (!is_array($expected) || !array_is_list($expected)) {
            return false;
        }
        $expected = array_map('intval', $expected);
        $current = [];
        foreach ($sources as $source) {
            if ((int) $source['thread_id'] !== $threadId || (string) $source['board_visibility'] !== 'public') {
                return false;
            }
            $current[] = (int) $source['id'];
        }
        sort($expected, SORT_NUMERIC);
        sort($current, SORT_NUMERIC);
        return $expected === $current;
    }

    /** @param array<string,mixed> $summary @return array<string,mixed> */
    private function brief(array $summary, bool $hasAiAncestor): array
    {
        $isAi = (string) $summary['kind'] === 'ai';
        $username = is_string($summary['author_username'] ?? null) && $summary['author_username'] !== ''
            ? '@' . $summary['author_username']
            : 'a curator';
        if ($isAi) {
            $label = 'AI-generated living brief';
            $metadata = 'Updated automatically';
        } elseif ($hasAiAncestor) {
            $label = 'AI-generated · curator edited';
            $metadata = 'Curator edited by ' . $username;
        } else {
            $label = 'Curated summary';
            $metadata = 'Curated by ' . $username;
        }
        $published = (string) ($summary['published_at'] ?? $summary['created_at']);
        $time = new DateTimeImmutable($published, new DateTimeZone('UTC'));
        return [
            'id' => (int) $summary['id'],
            'body_html' => (string) ($summary['body_html'] ?? ''),
            'version' => (int) $summary['version'],
            'label' => $label,
            'metadata' => $metadata,
            'has_ai_lineage' => $hasAiAncestor,
            'published_at' => $time->format('Y-m-d H:i:s') . ' UTC',
            'published_at_utc' => $time->format('Y-m-d\TH:i:s\Z'),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function relationshipRows(int $threadId, ?User $viewer): array
    {
        $rows = $this->db->fetchAll(
            "SELECT rt.*, t.title, t.slug, t.board_id, t.is_deleted, t.is_pending,
                    b.visibility AS board_visibility
             FROM related_threads rt
             JOIN threads t ON t.id = rt.related_thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE rt.source_thread_id = ? AND rt.status = 'approved'
             ORDER BY rt.id DESC",
            [$threadId],
        );
        return array_values(array_filter($rows, fn (array $row): bool =>
            (int) $row['is_deleted'] === 0
            && (int) $row['is_pending'] === 0
            && $this->canReadBoard((int) $row['board_id'], (string) $row['board_visibility'], $viewer)
        ));
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function selectRelated(array $rows, ?int $generationId, bool $allowAi): array
    {
        $curated = array_values(array_filter($rows, static fn (array $row): bool => $row['source'] === 'curated'));
        $selected = $allowAi ? array_values(array_filter($rows, static fn (array $row): bool =>
            (int) ($row['ai_selected'] ?? 0) === 1
            && (int) ($row['ai_generation_id'] ?? 0) === $generationId
        )) : [];
        $deterministic = $selected === [] ? array_values(array_filter($rows, static fn (array $row): bool =>
            in_array($row['source'], ['tag', 'search'], true)
            && (int) ($row['ai_selected'] ?? 0) === 0
        )) : [];
        return $this->deduplicate(array_merge($curated, $selected, $deterministic), 3);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function selectFallback(array $rows): array
    {
        return $this->deduplicate(array_values(array_filter($rows, static fn (array $row): bool =>
            in_array($row['source'], ['tag', 'search'], true)
            && (int) ($row['ai_selected'] ?? 0) === 0
        )), 3);
    }

    /** @param list<array<string,mixed>> $rows @return list<array<string,mixed>> */
    private function deduplicate(array $rows, int $limit): array
    {
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $targetId = (int) $row['related_thread_id'];
            if (isset($seen[$targetId])) {
                continue;
            }
            $seen[$targetId] = true;
            $isAi = (int) ($row['ai_selected'] ?? 0) === 1;
            $reason = $row['source'] === 'curated'
                ? (string) ($row['reason'] ?: 'Curated by community moderators')
                : ($isAi
                    ? (string) ($row['ai_reason'] ?: 'Related discussion')
                    : self::DETERMINISTIC_REASONS[(string) $row['source']]);
            $result[] = [
                'thread_id' => $targetId,
                'title' => (string) $row['title'],
                'slug' => (string) $row['slug'],
                'reason' => $reason,
            ];
            if (count($result) === $limit) {
                break;
            }
        }
        return $result;
    }

    /** @return list<array<string,mixed>> */
    private function history(int $threadId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT s.id, s.kind, s.status, s.version, s.parent_summary_id, s.published_at, s.created_at,
                    u.username AS author_username
             FROM thread_summaries s LEFT JOIN users u ON u.id = s.author_id
             WHERE s.thread_id = ? ORDER BY s.version DESC, s.id DESC',
            [$threadId],
        );
        foreach ($rows as &$row) {
            $lineage = $this->lineage($row);
            $brief = $this->brief($row + ['body_html' => ''], $lineage['has_ai_ancestor']);
            $row['label'] = $brief['label'];
        }
        unset($row);
        return array_values($rows);
    }
}
