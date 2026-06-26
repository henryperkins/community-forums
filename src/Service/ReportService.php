<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\BoardModeratorRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Repository\ReportRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;

/**
 * Report submission + triage (P2-08). Post reports are board-scoped; a moderator
 * may handle only reports for boards they moderate, an admin all. New reports
 * notify in-scope staff in-app; on resolve/dismiss, a reporter who opted in
 * ('notify_reporter') receives an outcome notification (PHASE_2_PLAN §3).
 */
final class ReportService
{
    public function __construct(
        private Database $db,
        private ReportRepository $reports,
        private PostRepository $posts,
        private BoardPolicy $policy,
        private BoardModeratorRepository $boardMods,
        private NotificationRepository $notifs,
        private UserRepository $users,
        private WriteGate $writeGate,
    ) {
    }

    public function submitPostReport(User $reporter, int $postId, ?string $reasonCode, string $reason, bool $notifyReporter): void
    {
        $this->writeGate->assertCanWrite($reporter);

        $post = $this->posts->findWithContext($postId);
        if ($post === null || (int) $post['is_deleted'] === 1) {
            throw new NotFoundException('Post not found.');
        }
        $isMember = $this->users->isBoardMember((int) $post['board_id'], $reporter->id());
        if (!$this->policy->canRead(['visibility' => $post['board_visibility']], $reporter, $isMember)) {
            throw new NotFoundException('Post not found.');
        }

        $this->db->transaction(function () use ($reporter, $post, $postId, $reasonCode, $reason, $notifyReporter): void {
            $reportId = $this->reports->createPostReport($reporter->id(), $postId, $reasonCode, trim($reason), $notifyReporter);
            if ($reportId === 0) {
                return; // dedupe: an open report already exists
            }
            $this->notifyStaff((int) $post['board_id'], (int) $post['thread_id'], $postId, $reporter->id());
        });
    }

    public function canHandle(User $user, array $report): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        $boardId = $this->reports->boardIdFor($report);
        return $boardId !== null && $this->boardMods->isModerator($boardId, $user->id());
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
        $this->db->transaction(function () use ($report, $mod, $status): void {
            $this->reports->setStatus((int) $report['id'], $status, $mod->id());
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
        });
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
}
