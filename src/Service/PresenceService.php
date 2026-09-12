<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\FeatureFlags;
use App\Domain\User;
use App\Repository\BlockRepository;
use App\Repository\UserRepository;

/**
 * The ONE place presence is decided (ADR 0031).
 *
 * Presence used to be re-derived independently by four surfaces — this service
 * for the rail and the JSON, ProfileController for the profile dot, and the
 * topbar template for the viewer's own leaf — and they had four different rule
 * sets. The roster honoured the feature flag, blocks, banned accounts and the
 * window; the profile dot honoured only the toggle and the window; the topbar
 * honoured nothing at all. Every surface now asks {@see state()}, so a fifth
 * one cannot quietly ship a fifth rule set.
 *
 * The ladder is fail-dark and its ORDER is load-bearing:
 *
 *   flag → id → status → show_presence → profile_visibility → recency → blocks
 *
 * Blocks are consulted last because they can only ever downgrade an otherwise
 * visible state to offline, and asking earlier would spend a query on subjects
 * that were already invisible. profile_visibility sits above recency because it
 * is a stated wish, not a timing accident: a member who restricted their profile
 * to signed-in members must not have their name and handle published in the
 * guest-visible rail or the unauthenticated /presence JSON.
 *
 * Every window comparison in a request uses ONE captured clock ({@see now()}),
 * so the same member cannot be "online" in the roster and "away" in a state()
 * call made a few milliseconds later in the same render.
 */
final class PresenceService
{
    public const ONLINE = 'online';
    public const AWAY = 'away';
    public const OFFLINE = 'offline';

    /** @var array<string,array<string,mixed>> */
    private array $snapshots = [];

    private ?int $now = null;

    public function __construct(
        private UserRepository $users,
        private BlockRepository $blocks,
        private FeatureFlags $features,
        private PresenceConfig $config,
    ) {
    }

    /**
     * The roster a viewer may see, plus its counts.
     *
     * The viewer is INCLUDED when they are themselves eligible, and marked
     * `is_self`. Excluding them (the previous behaviour) meant a signed-in
     * member read "Online 15" at the same instant a guest read 16 — the rail
     * silently disagreed with itself depending on who was looking.
     *
     * `here` is the count the badge shows: members seen inside the online
     * window. `total` is the whole roster including away members, and is what
     * decides emptiness — a rail with five away members and nobody here is not
     * empty. `capped` says the roster hit presence.roster_max, in which case the
     * counts are a floor and the UI renders "N+".
     *
     * @return array{members:list<array{username:string,display_name:string,state:string,is_self:bool,is_staff:bool}>,here:int,away:int,total:int,capped:bool}
     */
    public function snapshot(?User $viewer, string $search = ''): array
    {
        $key = ($viewer === null ? 'guest' : 'user:' . $viewer->id()) . '|' . $search;
        if (array_key_exists($key, $this->snapshots)) {
            /** @var array{members:list<array{username:string,display_name:string,state:string,is_self:bool,is_staff:bool}>,here:int,away:int,total:int,capped:bool} */
            return $this->snapshots[$key];
        }

        $empty = ['members' => [], 'here' => 0, 'away' => 0, 'total' => 0, 'capped' => false];
        if (!$this->features->enabled('presence')) {
            return $this->snapshots[$key] = $empty;
        }

        $max = $this->config->rosterMax();
        $since = gmdate('Y-m-d H:i:s', $this->now() - $this->config->awayWindowSeconds());
        // One row beyond the cap, purely to tell "exactly $max online" apart from
        // "at least $max online" — the row itself is discarded.
        $rows = $this->users->presenceRoster($since, $max + 1, $viewer === null, $search === '' ? null : $search);
        $capped = count($rows) > $max;
        if ($capped) {
            $rows = array_slice($rows, 0, $max);
        }

        $blocked = [];
        if ($viewer !== null && $rows !== []) {
            $blocked = $this->blocks->blockedMap(
                $viewer->id(),
                array_map(static fn (array $row): int => (int) $row['id'], $rows),
            );
        }

        $members = [];
        $here = 0;
        $away = 0;
        foreach ($rows as $row) {
            $id = (int) $row['id'];
            if (isset($blocked[$id])) {
                continue;
            }
            $state = $this->state($viewer, $row, false);
            if ($state === self::OFFLINE) {
                continue;
            }
            $isSelf = $viewer !== null && $id === $viewer->id();
            $state === self::ONLINE ? $here++ : $away++;
            $members[] = [
                'username' => (string) $row['username'],
                'display_name' => ($row['display_name'] ?? '') !== ''
                    ? (string) $row['display_name']
                    : (string) $row['username'],
                'state' => $state,
                'is_self' => $isSelf,
                // Matches mask_author()'s is_staff rule exactly (helpers.php).
                // Widening this to moderators on a public, searchable roll would
                // be a disclosure decision, not a styling one.
                'is_staff' => (string) $row['role'] === 'admin',
            ];
        }

        return $this->snapshots[$key] = [
            'members' => $members,
            'here' => $here,
            'away' => $away,
            'total' => count($members),
            'capped' => $capped,
        ];
    }

    /**
     * One page of /users-online: the snapshot, filtered, sorted and sliced.
     *
     * Ordering is ALPHABETICAL here and recency-first in the rail, deliberately.
     * Offset pagination over last_seen_at — a column the heartbeat rewrites on
     * every request — duplicates and skips rows between pages as members move
     * under the cursor. A name is stable for the length of a visit.
     *
     * @return array{members:list<array{username:string,display_name:string,state:string,is_self:bool,is_staff:bool}>,here:int,away:int,total:int,shown:int,capped:bool,page:int,pages:int,filter:string,search:string}
     */
    public function directory(?User $viewer, string $filter = '', string $search = '', int $page = 1): array
    {
        $filter = in_array($filter, [self::ONLINE, self::AWAY], true) ? $filter : '';
        $search = mb_substr(trim($search), 0, 64);

        $snapshot = $this->snapshot($viewer, $search);
        $members = $snapshot['members'];
        if ($filter !== '') {
            $members = array_values(array_filter(
                $members,
                static fn (array $m): bool => $m['state'] === $filter,
            ));
        }

        usort($members, static function (array $a, array $b): int {
            $byName = strcasecmp($a['display_name'], $b['display_name']);
            // Two members may legitimately share a display name; the handle is
            // unique, so it keeps the order total and the pages stable.
            return $byName !== 0 ? $byName : strcmp($a['username'], $b['username']);
        });

        $shown = count($members);
        $perPage = $this->config->pageSize();
        $pages = max(1, (int) ceil($shown / $perPage));
        $page = max(1, min($page, $pages));

        return [
            'members' => array_slice($members, ($page - 1) * $perPage, $perPage),
            'here' => $snapshot['here'],
            'away' => $snapshot['away'],
            'total' => $snapshot['total'],
            'shown' => $shown,
            'capped' => $snapshot['capped'],
            'page' => $page,
            'pages' => $pages,
            'filter' => $filter,
            'search' => $search,
        ];
    }

    /**
     * The viewer's own presence, for the topbar leaf. A member who turned
     * presence off gets OFFLINE here too: the toggle reads "Show when I'm
     * online / A leaf marks your presence beside your name", so painting them a
     * leaf they switched off would make the control lie to the one person who
     * set it.
     */
    public function selfState(?User $viewer): string
    {
        if ($viewer === null) {
            return self::OFFLINE;
        }

        // Resolved off the viewer's OWN row, never out of the roster slice. The
        // roster is capped and ranked by recency, so on a busy forum a member
        // could rank below presence.roster_max and watch their own leaf go dark
        // while their own profile page — which asks state() directly — still
        // showed it. Two surfaces of the one ladder, disagreeing.
        //
        // Recency is not asked either: this request IS the viewer's activity, and
        // the kernel's heartbeat has already recorded it. The session's User
        // object still carries the PRE-heartbeat timestamp, so classifying from
        // it would report a member returning after a week as offline on the one
        // page load where they are provably present.
        $row = $viewer->toArray();
        $row['last_seen_at'] = gmdate('Y-m-d H:i:s', $this->now());

        return $this->state($viewer, $row, false);
    }

    /**
     * The authoritative per-member rule. $subject MUST be a full users row
     * carrying id, status, show_presence, profile_visibility and last_seen_at;
     * a missing or wrongly-typed key resolves to OFFLINE rather than guessing.
     *
     * Pass $blockedEitherWay when the caller already knows (ProfileController
     * resolves it for its own affordances, and snapshot() has batched it), or
     * null to let this method ask. Passing false asserts "already filtered".
     *
     * @param array<string,mixed> $subject
     */
    public function state(?User $viewer, array $subject, ?bool $blockedEitherWay = null): string
    {
        if (!$this->features->enabled('presence')) {
            return self::OFFLINE;
        }
        $id = (int) ($subject['id'] ?? 0);
        if ($id <= 0) {
            return self::OFFLINE;
        }
        if ((string) ($subject['status'] ?? '') === 'banned') {
            return self::OFFLINE;
        }
        if ((int) ($subject['show_presence'] ?? 0) !== 1) {
            return self::OFFLINE;
        }
        if ($viewer === null && (string) ($subject['profile_visibility'] ?? 'public') === 'members') {
            return self::OFFLINE;
        }

        $state = $this->classify($subject['last_seen_at'] ?? null);
        if ($state === self::OFFLINE) {
            return self::OFFLINE;
        }

        if ($viewer !== null && $viewer->id() !== $id) {
            $isBlocked = $blockedEitherWay ?? $this->blocks->blockedEitherWay($viewer->id(), $id);
            if ($isBlocked) {
                return self::OFFLINE;
            }
        }

        return $state;
    }

    /** Rows the board rail shows before deferring to "+N more". */
    public function railLimit(): int
    {
        return $this->config->railLimit();
    }

    private function classify(mixed $lastSeen): string
    {
        if (!is_string($lastSeen) || $lastSeen === '') {
            return self::OFFLINE;
        }
        $ts = strtotime($lastSeen . ' UTC');
        if ($ts === false) {
            return self::OFFLINE;
        }
        // last_seen_at is written with the DATABASE clock (UTC_TIMESTAMP()) and
        // read against the PHP one. A database running slightly ahead would make
        // a just-written row look like it came from the future; clamp rather than
        // let a negative age fall through to OFFLINE.
        $age = max(0, $this->now() - $ts);
        if ($age <= $this->config->onlineWindowSeconds()) {
            return self::ONLINE;
        }
        if ($age <= $this->config->awayWindowSeconds()) {
            return self::AWAY;
        }
        return self::OFFLINE;
    }

    /** One clock for the whole request — see the class docblock. */
    private function now(): int
    {
        return $this->now ??= time();
    }
}
