<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Security\BoardPolicy;
use App\Security\CapabilityCatalog;
use App\Security\CapabilityResolver;
use App\Security\WriteGate;

/**
 * Runs the old-vs-new parity corpus on the Phase 5 fixture. The oracle encodes
 * current legacy authority; the resolver must match it before enforcement.
 */
final class ResolverParityService
{
    /** @var list<string> */
    private const DUAL_PATH = ['core.thread.mark_solved', 'core.poll.manage', 'core.thread.manage_workflow'];

    /** @var list<string> */
    private const CAN_POST_GATED = ['core.thread.create', 'core.post.create', 'core.thread.tag'];

    /** @var list<string> */
    private const READ_GATED = ['core.content.react', 'core.content.report'];

    /** @var list<string> */
    private const MODERATION_KEYS = [
        'core.post.delete_any',
        'core.post.restore',
        'core.thread.lock',
        'core.thread.pin',
        'core.thread.move',
        'core.thread.split_merge',
        'core.post.reveal_author',
        'core.content.approve',
        'core.report.handle',
        'core.appeal.resolve_content',
        'core.memory.curate',
    ];

    public function __construct(
        private Database $db,
        private CapabilityResolver $resolver,
        private BoardModeratorRepository $boardModerators,
        private BoardMemberRepository $members,
        private ProtectedOwnerRepository $owners,
        private BoardPolicy $policy,
        private WriteGate $writeGate,
        private SettingRepository $settings,
    ) {
    }

    public function legacyCanModerate(?User $user, int $boardId): bool
    {
        return $user !== null
            && $this->writeGate->canWrite($user)
            && ($user->isAdmin() || $this->boardModerators->isModerator($boardId, $user->id()));
    }

    /**
     * @return array{fixture:string,tuples:int,agreed:int,mismatches:list<array<string,mixed>>}
     */
    public function run(): array
    {
        $userRows = $this->db->fetchAll("SELECT * FROM users WHERE username LIKE 'p5fix\\_%' ORDER BY id ASC");
        $boards = $this->db->fetchAll("SELECT * FROM boards WHERE slug LIKE 'p5fix\\_%' ORDER BY id ASC");
        if ($userRows === [] || $boards === []) {
            throw new \RuntimeException('Phase 5 fixture not seeded; run Phase5FixtureSeeder first.');
        }

        /** @var list<array{0:?User,1:string}> $actors */
        $actors = [[null, 'guest']];
        foreach ($userRows as $row) {
            $actors[] = [User::fromRow($row), (string) $row['username']];
        }

        $otherUserId = (int) $userRows[0]['id'];
        $tuples = 0;
        $agreed = 0;
        $mismatches = [];

        foreach (CapabilityCatalog::all() as $key => $meta) {
            foreach ($actors as [$actor, $actorLabel]) {
                foreach ($this->targetsFor($key, $meta, $actor, $boards, $otherUserId) as [$target, $targetLabel]) {
                    $tuples++;
                    $decision = $this->resolver->can($actor, $key, $target);
                    $legacy = $this->legacyAllows($key, $meta, $actor, $target);
                    if ($decision->allowed === $legacy) {
                        $agreed++;
                        continue;
                    }

                    $mismatches[] = [
                        'capability' => $key,
                        'actor' => $actorLabel,
                        'target' => $targetLabel,
                        'legacy' => $legacy,
                        'resolver' => $decision->allowed,
                        'source' => $decision->source,
                        'reason' => $decision->reason,
                    ];
                }
            }
        }

        $fixtureVersion = (int) $this->settings->get('phase5_fixture_version', 1);

        return [
            'fixture' => 'phase5_fixture_v' . max(1, $fixtureVersion),
            'tuples' => $tuples,
            'agreed' => $agreed,
            'mismatches' => $mismatches,
        ];
    }

    /**
     * @param array{scope:string,protected:bool} $meta
     * @param list<array<string,mixed>> $boards
     * @return list<array{0:array<string,mixed>,1:string}>
     */
    private function targetsFor(string $key, array $meta, ?User $actor, array $boards, int $otherUserId): array
    {
        if (in_array($key, self::DUAL_PATH, true)) {
            $out = [];
            foreach ($boards as $board) {
                $own = $actor?->id() ?? $otherUserId;
                $out[] = [['board_id' => (int) $board['id'], 'owner_id' => $own], (string) $board['slug'] . ':own-thread'];
                $out[] = [
                    ['board_id' => (int) $board['id'], 'owner_id' => $otherUserId === $own ? $own + 1000000 : $otherUserId],
                    (string) $board['slug'] . ':other-thread',
                ];
            }
            return $out;
        }

        if ($meta['scope'] === 'self') {
            $own = $actor?->id() ?? $otherUserId;
            $other = $own === $otherUserId ? $own + 1000000 : $otherUserId;
            return [
                [['user_id' => $own], 'self'],
                [['user_id' => $other], 'other-user'],
            ];
        }

        if ($meta['scope'] === 'board') {
            $out = [];
            foreach ($boards as $board) {
                $out[] = [['board_id' => (int) $board['id']], (string) $board['slug']];
            }
            return $out;
        }

        return [[[], 'site']];
    }

    /** @param array{scope:string,protected:bool} $meta @param array<string,mixed> $target */
    private function legacyAllows(string $key, array $meta, ?User $user, array $target): bool
    {
        $board = null;
        $isMember = false;
        if (isset($target['board_id'])) {
            $board = $this->db->fetch('SELECT * FROM boards WHERE id = ?', [(int) $target['board_id']]);
            $isMember = $user !== null && $board !== null && $this->members->isMember((int) $board['id'], $user->id());
        }

        $canWrite = $user !== null && $this->writeGate->canWrite($user);

        if ($meta['protected']) {
            return $user !== null && $canWrite && $this->owners->isActiveOwner($user->id());
        }

        if ($key === 'core.board.read') {
            return $board === null || $this->policy->canRead($board, $user, $isMember);
        }

        if (in_array($key, self::CAN_POST_GATED, true)) {
            return $user !== null && $canWrite && $board !== null && $this->policy->canPost($board, $user, $isMember);
        }

        if (in_array($key, self::READ_GATED, true)) {
            return $user !== null && $canWrite && ($board === null || $this->policy->canRead($board, $user, $isMember));
        }

        if (in_array($key, self::DUAL_PATH, true)) {
            $ownsTarget = $user !== null && isset($target['owner_id']) && $user->id() === (int) $target['owner_id'];
            return ($canWrite && $ownsTarget) || $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0));
        }

        if ($meta['scope'] === 'self') {
            $subject = $target['user_id'] ?? null;
            $ownSubject = $user !== null && ($subject === null || (int) $subject === $user->id());
            if ($key === 'core.account.manage_self') {
                return $user !== null && $ownSubject;
            }
            return $ownSubject && $canWrite;
        }

        if (in_array($key, self::MODERATION_KEYS, true)) {
            return $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0));
        }

        if ($key === 'core.content.view_pending') {
            return $this->legacyCanModerate($user, (int) ($target['board_id'] ?? 0))
                || ($user !== null && $canWrite && $user->isModerator());
        }

        if ($key === 'core.user.warn') {
            return $user !== null
                && $canWrite
                && ($user->isAdmin() || $this->boardModerators->boardsFor($user->id()) !== []);
        }

        return $user !== null && $canWrite && $user->isAdmin();
    }

    /** @param array{fixture:string,tuples:int,agreed:int,mismatches:list<array<string,mixed>>} $result */
    public function render(array $result, string $commit): string
    {
        $out = "# Phase 5 - Resolver Parity Corpus (Increment 1, P5-08)\n\n";
        $out .= "> Generated by `bin/console verify:resolver-parity`. Legacy oracle vs CapabilityResolver on the same fixture and commit.\n\n";
        $out .= 'Commit: `' . $commit . "`\n";
        $out .= 'Fixture: `' . $result['fixture'] . "`\n";
        $out .= 'Catalogue: ' . count(CapabilityCatalog::keys()) . " keys\n";
        $out .= 'Tuples compared: **' . $result['tuples'] . "**\n";
        $out .= 'Agreed: **' . $result['agreed'] . "**\n";
        $out .= 'Mismatches: **' . count($result['mismatches']) . "**\n\n";
        $out .= "## Coverage\n\n";
        $out .= "Every catalogued capability was compared for every fixture actor against the targets its scope calls for. ";
        $out .= "For example, `core.thread.lock` is checked per board per actor.\n\n";

        if ($result['mismatches'] === []) {
            $out .= "## Mismatches\n\nNone. **Exit-gate criterion met: zero parity mismatch for built-in roles on the critical fixtures.**\n";
            return $out;
        }

        $out .= "## Mismatches\n\n";
        $out .= "| Capability | Actor | Target | Legacy | Resolver | Source | Reason |\n";
        $out .= "|---|---|---|---|---|---|---|\n";
        foreach ($result['mismatches'] as $mismatch) {
            $out .= '| `' . $mismatch['capability'] . '` | ' . $mismatch['actor'] . ' | ' . $mismatch['target'] . ' | '
                . ($mismatch['legacy'] ? 'allow' : 'deny') . ' | '
                . ($mismatch['resolver'] ? 'allow' : 'deny') . ' | '
                . $mismatch['source'] . ' | ' . $mismatch['reason'] . " |\n";
        }

        return $out;
    }
}
