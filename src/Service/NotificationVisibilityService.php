<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Domain\User;
use App\Repository\SettingRepository;
use App\Security\AuthorityGate;
use App\Security\Cap;

/** Recipient context is request-local. Workers explicitly refresh for each attempt. */
final class NotificationVisibilityService
{
    private array $scopes = [];

    public function __construct(
        private Database $db,
        private ?FeatureFlags $flags = null,
        private ?AuthorityGate $authority = null,
    ) {
        $this->flags ??= new FeatureFlags(new SettingRepository($db));
    }

    public function scope(User $viewer, bool $fresh = false): array
    {
        if (!$fresh && isset($this->scopes[$viewer->id()])) {
            return $this->scopes[$viewer->id()];
        }
        if ($fresh) {
            $this->flags->invalidate();
        }
        $features = $this->flags->all();
        $scope = [
            'user_id' => $viewer->id(),
            'is_admin' => $viewer->isAdmin(),
            'member_board_ids' => array_map('intval', array_column($this->db->fetchAll(
                'SELECT board_id FROM board_members WHERE user_id = ?', [$viewer->id()],
            ), 'board_id')),
            // Read authority must not consume WriteGate (suspended assigned moderators can read).
            'assigned_board_ids' => array_map('intval', array_column($this->db->fetchAll(
                'SELECT board_id FROM board_moderators WHERE user_id = ?', [$viewer->id()],
            ), 'board_id')),
            'features' => $features,
            // This is the site probe used by ReportService::queueModel via BoardAuthority.
            'may_review_dm_reports' => !empty($features['moderation_queue'])
                && ($this->authority ?? AuthorityGate::legacy())->allows(
                    fn (): bool => $viewer->isAdmin(), $viewer, Cap::REPORT_HANDLE, [],
                    'ModerationService::moderableBoardIds',
                ),
        ];
        return $this->scopes[$viewer->id()] = $scope;
    }

    /** Final delivery gate, after reloading both the recipient and the post. */
    public function canReadPost(User $viewer, array $post, array $scope): bool
    {
        if (empty($scope['features']['notifications']) || empty($scope['features']['email'])
            || (int) ($post['is_deleted'] ?? 0) !== 0 || (int) ($post['is_pending'] ?? 0) !== 0) {
            return false;
        }
        $threads = new ThreadReadService(
            new \App\Repository\ThreadRepository($this->db), new \App\Security\BoardPolicy(),
            new \App\Repository\BoardMemberRepository($this->db),
            new \App\Security\BoardAuthority(new \App\Security\WriteGate(),
                new \App\Repository\BoardModeratorRepository($this->db), new \App\Repository\BoardRepository($this->db), $this->authority),
        );
        try {
            $thread = $threads->loadForUser($viewer, (int) $post['thread_id']);
        } catch (\App\Core\NotFoundException) {
            return false;
        }
        return (int) ($thread['is_pending'] ?? 0) === 0
            && !(new \App\Repository\BlockRepository($this->db))->blockedEitherWay($viewer->id(), (int) $post['user_id']);
    }
}
