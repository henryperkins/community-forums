<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\ValidationException;
use App\Repository\ModerationLogRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Read model for the /admin/audit screen (PR #44 spec §4): allowlists and
 * validates the filter set (dates must round-trip Y-m-d exactly — no more
 * `'banana 00:00:00'` SQL bounds), resolves the actor substring to ids so
 * ModerationLogRepository stays single-table, computes has_next from the real
 * total, and batch-enriches rows with actor handles for every consumer of the
 * moderation log (audit screen, dashboard card, staff-panel trail).
 */
final class AuditQueryService
{
    /** Actor-substring resolution cap — past it the filter is refused, never silently truncated. */
    private const ACTOR_MATCH_LIMIT = 500;

    public function __construct(
        private ModerationLogRepository $log,
        private UserRepository $users,
    ) {
    }

    /**
     * @param array<string,mixed> $raw
     * @return array{
     *   rows:array<int,array<string,mixed>>,
     *   filters:array<string,string>,
     *   total:int,
     *   page:int,
     *   per_page:int,
     *   has_next:bool,
     *   base_query:array<string,string>
     * }
     */
    public function page(array $raw, int $page, int $perPage = 50): array
    {
        $targetId = trim((string) ($raw['target_id'] ?? ''));
        $filters = [
            'actor' => trim((string) ($raw['actor'] ?? '')),
            'action' => trim((string) ($raw['action'] ?? '')),
            'target_type' => trim((string) ($raw['target_type'] ?? '')),
            'target_id' => $targetId,
            'from' => trim((string) ($raw['from'] ?? '')),
            'to' => trim((string) ($raw['to'] ?? '')),
        ];
        $errors = [];
        if ($targetId !== '' && !ctype_digit($targetId)) {
            $errors['target_id'] = 'Use a numeric target ID.';
        }
        foreach (['from', 'to'] as $key) {
            if ($filters[$key] === '') {
                continue;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $filters[$key], new DateTimeZone('UTC'));
            if ($date === false || $date->format('Y-m-d') !== $filters[$key]) {
                $errors[$key] = 'Use YYYY-MM-DD.';
            }
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $filters);
        }
        $page = max(0, $page);
        $perPage = max(1, min(200, $perPage));
        $baseQuery = array_filter($filters, static fn (string $v): bool => $v !== '');

        $repoFilters = $filters;
        unset($repoFilters['actor']);
        if ($filters['actor'] !== '') {
            // Fetch one past the cap: rows and total computed from a truncated
            // id list would silently drop matches, so a too-broad filter is
            // refused outright rather than answered incompletely.
            $actorIds = $this->users->idsMatchingName($filters['actor'], self::ACTOR_MATCH_LIMIT + 1);
            if (count($actorIds) > self::ACTOR_MATCH_LIMIT) {
                throw new ValidationException(
                    ['actor' => 'That filter matches too many accounts — add more of the name, or use the exact username.'],
                    $filters,
                );
            }
            if ($actorIds === []) {
                // No actor matches ⇒ no rows — never fall through unfiltered.
                return [
                    'rows' => [],
                    'filters' => $filters,
                    'total' => 0,
                    'page' => $page,
                    'per_page' => $perPage,
                    'has_next' => false,
                    'base_query' => $baseQuery,
                ];
            }
            $repoFilters['actor_ids'] = $actorIds;
        }

        $total = $this->log->searchCount($repoFilters);

        return [
            'rows' => $this->enrich($this->log->search($repoFilters, $perPage, $page * $perPage)),
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'has_next' => ($page + 1) * $perPage < $total,
            'base_query' => $baseQuery,
        ];
    }

    /**
     * Reattach actor_username/actor_display_name to bare moderation_log rows
     * (one batched lookup — template keys unchanged from the old JOIN shape).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public function enrich(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (($row['actor_id'] ?? null) !== null) {
                $ids[] = (int) $row['actor_id'];
            }
        }
        $handles = $this->users->handlesForIds($ids);
        foreach ($rows as &$row) {
            $actor = ($row['actor_id'] ?? null) !== null ? ($handles[(int) $row['actor_id']] ?? null) : null;
            $row['actor_username'] = $actor['username'] ?? null;
            $row['actor_display_name'] = $actor['display_name'] ?? null;
        }
        unset($row);
        return $rows;
    }
}
