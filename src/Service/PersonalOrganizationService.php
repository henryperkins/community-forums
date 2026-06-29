<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Security\BoardPolicy;

final class PersonalOrganizationService
{
    public function __construct(
        private Database $db,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
    ) {
    }

    public function createFolder(User $user, string $name): int
    {
        $name = $this->name($name);
        return $this->db->insert(
            'INSERT INTO board_folders (user_id, name, position, created_at)
             VALUES (?, ?, COALESCE((SELECT next_pos FROM (SELECT MAX(position) + 1 AS next_pos FROM board_folders WHERE user_id = ?) x), 0), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP()',
            [$user->id(), $name, $user->id()],
        );
    }

    public function addBoardToFolder(User $user, int $folderId, int $boardId): void
    {
        $folder = $this->db->fetch('SELECT * FROM board_folders WHERE id = ? AND user_id = ?', [$folderId, $user->id()]);
        if ($folder === null) {
            throw new NotFoundException('Folder not found.');
        }
        $board = $this->readableBoard($user, $boardId);
        $this->db->run(
            'INSERT INTO board_folder_boards (folder_id, board_id, position, created_at)
             VALUES (?, ?, COALESCE((SELECT next_pos FROM (SELECT MAX(position) + 1 AS next_pos FROM board_folder_boards WHERE folder_id = ?) x), 0), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE position = VALUES(position)',
            [$folderId, (int) $board['id'], $folderId],
        );
    }

    /** @param array<string,mixed> $input */
    public function createSavedFeed(User $user, array $input): int
    {
        $name = $this->name((string) ($input['name'] ?? ''));
        $boardId = (int) ($input['board_id'] ?? 0);
        $boardIds = [];
        if ($boardId > 0) {
            $board = $this->readableBoard($user, $boardId);
            $boardIds[] = (int) $board['id'];
        }
        $filter = [
            'board_ids' => $boardIds,
            'sort' => 'latest',
        ];
        $json = json_encode($filter, JSON_UNESCAPED_SLASHES) ?: '{"board_ids":[],"sort":"latest"}';
        return $this->db->insert(
            'INSERT INTO saved_feed_filters (user_id, name, filter_json, digest_enabled, position, created_at)
             VALUES (?, ?, ?, ?, COALESCE((SELECT next_pos FROM (SELECT MAX(position) + 1 AS next_pos FROM saved_feed_filters WHERE user_id = ?) x), 0), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE filter_json = VALUES(filter_json), digest_enabled = VALUES(digest_enabled), updated_at = UTC_TIMESTAMP()',
            [$user->id(), $name, $json, !empty($input['digest_enabled']) ? 1 : 0, $user->id()],
        );
    }

    private function name(string $name): string
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Name must be 1 to 80 characters.']);
        }
        return $name;
    }

    /** @return array<string,mixed> */
    private function readableBoard(User $user, int $boardId): array
    {
        $board = $this->boards->find($boardId);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        $isMember = $this->members->isMember($boardId, $user->id());
        if (!$this->policy->canRead($board, $user, $isMember)) {
            throw new NotFoundException('Board not found.');
        }
        return $board;
    }
}
