<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Hook\FirstPartyHookRegistry;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Security\AuthorityGate;
use App\Security\BoardAuthority;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WriteGate;

/**
 * Report submission + triage (P2-08). Post reports are board-scoped; a moderator
 * may handle only reports for boards they moderate, an admin all. New reports
 * notify in-scope staff in-app; on resolve/dismiss, a reporter who opted in
 * ('notify_reporter') receives an outcome notification (PHASE_2_PLAN §3).
 */
final class ReportService
{
    /** Report reason vocabulary (shared by submit validation + the queue filter). */
    public const REASONS = ['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'];

    public function __construct(
        private Database $db,
        private ReportRepository $reports,
        private PostRepository $posts,
        private BoardPolicy $policy,
        private BoardModeratorRepository $boardMods,
        private NotificationRepository $notifs,
        private UserRepository $users,
        private WriteGate $writeGate,
        private ?FirstPartyHookRegistry $hooks = null,
        private ?AuthorityGate $authority = null,
        private ?BoardAuthority $boardAuthority = null,
        private ?BoardRepository $boards = null,
    ) {
    }

    private function gate(): AuthorityGate
    {
        return $this->authority ?? AuthorityGate::legacy();
    }

    /**
     * The /mod/reports read model (PR #44 spec §4): scope resolution through
     * the authority gate (core.report.handle), allowlisted filters clamped to
     * the actor's scope, rows plus the real total (has_next comes from it),
     * and the boards select. Board-scope behavior is byte-identical to the
     * controller assembly this replaces.
     *
     * @param array<string,mixed> $raw
     * @return array<string,mixed>
     */
    public function queueModel(User $user, array $raw, int $page, int $perPage = 50): array
    {
        if ($this->boardAuthority === null || $this->boards === null) {
            throw new \LogicException('Report queue dependencies are not wired.');
        }
        // Queue discovery through the gate: legacy/shadow reproduce
        // admin-or-assigned exactly; under enforce a custom deputy's grant
        // surfaces their boards.
        $scope = $this->boardAuthority->moderableBoardIds($user, Cap::REPORT_HANDLE);
        if ($scope === []) {
            throw new NotFoundException('Not found.'); // not a handler of anything
        }

        $status = (string) ($raw['status'] ?? '');
        $status = in_array($status, ['open', 'triaged'], true) ? $status : '';
        $reason = (string) ($raw['reason_code'] ?? '');
        $reason = in_array($reason, self::REASONS, true) ? $reason : '';
        $boardId = max(0, (int) ($raw['board_id'] ?? 0));
        if ($boardId > 0 && $scope !== null && !in_array($boardId, $scope, true)) {
            $boardId = 0; // the board filter can never widen visibility
        }
        $page = max(0, $page);
        $perPage = max(1, min(200, $perPage));
        $filters = ['status' => $status, 'reason_code' => $reason, 'board_id' => $boardId];

        $total = $this->reports->queueCount($scope === null, $scope ?? [], $filters);
        $boards = [];
        foreach ($this->boards->allOrdered() as $board) {
            if ($scope !== null && !in_array((int) $board['id'], $scope, true)) {
                continue;
            }
            $boards[] = ['id' => (int) $board['id'], 'name' => (string) $board['name']];
        }

        return [
            'reports' => $this->reports->queue($scope === null, $scope ?? [], $perPage, $page * $perPage, $filters),
            'reasons' => self::REASONS,
            'boards' => $boards,
            'filters' => $filters,
            'total' => $total,
            'page' => $page,
            'has_next' => ($page + 1) * $perPage < $total,
        ];
    }

    public function submitPostReport(User $reporter, int $postId, ?string $reasonCode, string $reason, bool $notifyReporter): void
    {
        $this->writeGate->assertCanWrite($reporter);
        $reasonCode = in_array($reasonCode, self::REASONS, true) ? $reasonCode : null;

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        $isMember = $this->users->isBoardMember((int) $post['board_id'], $reporter->id());
        if (!$this->policy->canRead(['visibility' => $post['board_visibility']], $reporter, $isMember)) {
            throw new NotFoundException('Post not found.');
        }

        $reportId = $this->db->transaction(function () use ($reporter, $post, $postId, $reasonCode, $reason, $notifyReporter): int {
            $reportId = $this->reports->createPostReport($reporter->id(), $postId, $reasonCode, trim($reason), $notifyReporter);
            if ($reportId === 0) {
                return 0; // dedupe: an open report already exists
            }
            $this->notifyStaff((int) $post['board_id'], (int) $post['thread_id'], $postId, $reporter->id());
            return $reportId;
        });
        if ($reportId > 0 && (string) ($post['board_visibility'] ?? 'public') === 'public') {
            $this->hooks?->emit('report.created', [
                'report_id' => $reportId,
                'post_id' => $postId,
                'thread_id' => (int) $post['thread_id'],
                'board_id' => (int) $post['board_id'],
                'reporter_id' => $reporter->id(),
                'status' => 'open',
            ], 'report:' . $reportId . ':created');
        }
    }

    public function canHandle(User $user, array $report): bool
    {
        $boardId = $this->reports->boardIdFor($report);
        return $this->gate()->allows(
            fn (): bool => $user->isAdmin() || ($boardId !== null && $this->boardMods->isModerator($boardId, $user->id())),
            $user,
            Cap::REPORT_HANDLE,
            ['board_id' => $boardId],
            'ReportService::canHandle',
        );
    }

    public function claim(User $mod, int $reportId): void
    {
        $report = $this->requireHandleable($mod, $reportId);
        $this->reports->claim((int) $report['id'], $mod->id());
    }

    public function resolve(User $mod, int $reportId): void
    {
        $this->finish($mod, $reportId, 'resolved');
    }

    public function dismiss(User $mod, int $reportId): void
    {
        $this->finish($mod, $reportId, 'dismissed');
    }

    private function finish(User $mod, int $reportId, string $status): void
    {
        $report = $this->requireHandleable($mod, $reportId);
        $changed = $this->db->transaction(function () use ($report, $mod, $status): bool {
            if ($this->reports->setStatus((int) $report['id'], $status, $mod->id()) === 0) {
                return false;
            }
            // Reporter outcome-notification (opt-in).
            if ((int) ($report['notify_reporter'] ?? 0) === 1 && $report['post_id'] !== null) {
                $threadId = $this->db->fetchValue('SELECT thread_id FROM posts WHERE id = ?', [(int) $report['post_id']]);
                $this->notifs->create([
                    'user_id' => (int) $report['reporter_id'],
                    'type' => 'mod',
                    'actor_id' => $mod->id(),
                    'thread_id' => $threadId !== false ? (int) $threadId : null,
                    'post_id' => (int) $report['post_id'],
                ]);
            }
            return true;
        });
        if ($changed) {
            $this->emitReportFinished($report, $status, $mod->id());
        }
    }

    /** @return array<string,mixed> */
    private function requireHandleable(User $mod, int $reportId): array
    {
        $this->writeGate->assertCanWrite($mod);
        $report = $this->reports->find($reportId);
        if ($report === null) {
            throw new NotFoundException('Report not found.');
        }
        if (!$this->canHandle($mod, $report)) {
            throw new ForbiddenException('You cannot handle this report.');
        }
        return $report;
    }

    /** In-app 'mod' alert to the board's moderators + admins (excluding the reporter). */
    private function notifyStaff(int $boardId, int $threadId, int $postId, int $reporterId): void
    {
        $ids = array_map(static fn (array $m): int => (int) $m['user_id'], $this->boardMods->moderatorsFor($boardId));
        $ids = array_merge($ids, $this->users->adminIds());
        $seen = [];
        foreach (array_unique($ids) as $uid) {
            if ($uid === $reporterId || isset($seen[$uid])) {
                continue;
            }
            $seen[$uid] = true;
            $this->notifs->create([
                'user_id' => $uid, 'type' => 'mod',
                'actor_id' => $reporterId, 'thread_id' => $threadId, 'post_id' => $postId,
            ]);
        }
    }

    /** @param array<string,mixed> $report */
    private function emitReportFinished(array $report, string $status, int $handledById): void
    {
        if ($report['post_id'] === null) {
            return;
        }
        $post = $this->posts->findWithContext((int) $report['post_id']);
        if ($post === null || (string) ($post['board_visibility'] ?? 'public') !== 'public') {
            return;
        }
        $reportId = (int) $report['id'];
        $this->hooks?->emit('report.resolved', [
            'report_id' => $reportId,
            'post_id' => (int) $report['post_id'],
            'thread_id' => (int) $post['thread_id'],
            'board_id' => (int) $post['board_id'],
            'reporter_id' => (int) $report['reporter_id'],
            'handled_by_id' => $handledById,
            'status' => $status,
        ], 'report:' . $reportId . ':' . $status);
    }
}
