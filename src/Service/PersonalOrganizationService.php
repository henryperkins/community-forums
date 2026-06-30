<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Security\BoardPolicy;

final class PersonalOrganizationService
{
    public function __construct(
        private Database $db,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private ThreadRepository $threads,
        private ThreadUserRepository $threadUsers,
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

    /**
     * @return array{
     *   board_folders:list<array<string,mixed>>,
     *   saved_feeds:list<array<string,mixed>>,
     *   bookmark_folders:list<array<string,mixed>>,
     *   starred_threads:list<array<string,mixed>>
     * }
     */
    public function overview(User $user, array $enabled): array
    {
        $boardFoldersOn = !empty($enabled['board_folders']);
        $savedFeedsOn = !empty($enabled['saved_feeds']);
        $bookmarkFoldersOn = !empty($enabled['bookmark_folders']);

        return [
            // Dark features must not make /settings/boards depend on later
            // migrations before an operator flips them on.
            'board_folders' => $boardFoldersOn ? $this->boardFolders($user) : [],
            'saved_feeds' => $savedFeedsOn ? $this->savedFeeds($user) : [],
            'bookmark_folders' => $bookmarkFoldersOn ? $this->bookmarkFolders($user) : [],
            'starred_threads' => $bookmarkFoldersOn ? $this->starredThreads($user) : [],
        ];
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

    public function createBookmarkFolder(User $user, string $name): int
    {
        $name = $this->name($name);
        return $this->db->insert(
            'INSERT INTO thread_bookmark_folders (user_id, name, position, created_at)
             VALUES (?, ?, COALESCE((SELECT next_pos FROM (SELECT MAX(position) + 1 AS next_pos FROM thread_bookmark_folders WHERE user_id = ?) x), 0), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE updated_at = UTC_TIMESTAMP()',
            [$user->id(), $name, $user->id()],
        );
    }

    public function addThreadToBookmarkFolder(User $user, int $folderId, int $threadId): void
    {
        $folder = $this->db->fetch('SELECT * FROM thread_bookmark_folders WHERE id = ? AND user_id = ?', [$folderId, $user->id()]);
        if ($folder === null) {
            throw new NotFoundException('Folder not found.');
        }
        $thread = $this->readableThread($user, $threadId);
        if (!$this->threadUsers->isStarred($user->id(), (int) $thread['id'])) {
            throw new ValidationException(['thread_id' => 'Star the thread before adding it to a bookmark folder.']);
        }
        $this->db->run(
            'INSERT INTO thread_bookmark_folder_threads (folder_id, thread_id, position, created_at)
             VALUES (?, ?, COALESCE((SELECT next_pos FROM (SELECT MAX(position) + 1 AS next_pos FROM thread_bookmark_folder_threads WHERE folder_id = ?) x), 0), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE position = VALUES(position)',
            [$folderId, (int) $thread['id'], $folderId],
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

    /** @return array<string,mixed> */
    private function readableThread(User $user, int $threadId): array
    {
        $thread = $this->threads->findWithBoard($threadId);
        if ($thread === null || (int) ($thread['is_deleted'] ?? 0) === 1 || (int) ($thread['is_pending'] ?? 0) === 1) {
            throw new NotFoundException('Thread not found.');
        }
        $isMember = $this->members->isMember((int) $thread['board_id'], $user->id());
        if (!$this->policy->canRead([
            'visibility' => $thread['board_visibility'],
            'id' => $thread['board_id'],
        ], $user, $isMember)) {
            throw new NotFoundException('Thread not found.');
        }
        return $thread;
    }

    /** @return list<array<string,mixed>> */
    private function boardFolders(User $user): array
    {
        $rows = $this->db->fetchAll(
            'SELECT f.id AS folder_id, f.name AS folder_name, f.position AS folder_position,
                    b.id AS board_id, b.name AS board_name, b.slug AS board_slug, b.visibility AS board_visibility
             FROM board_folders f
             LEFT JOIN board_folder_boards fb ON fb.folder_id = f.id
             LEFT JOIN boards b ON b.id = fb.board_id
             WHERE f.user_id = ?
             ORDER BY f.position ASC, f.id ASC, fb.position ASC, b.name ASC',
            [$user->id()],
        );
        $folders = [];
        foreach ($rows as $row) {
            $id = (int) $row['folder_id'];
            if (!isset($folders[$id])) {
                $folders[$id] = [
                    'id' => $id,
                    'name' => (string) $row['folder_name'],
                    'boards' => [],
                ];
            }
            if ($row['board_id'] !== null) {
                $boardId = (int) $row['board_id'];
                if ($this->canReadBoard($user, $boardId, (string) ($row['board_visibility'] ?? 'public'))) {
                    $folders[$id]['boards'][] = [
                        'id' => $boardId,
                        'name' => (string) $row['board_name'],
                        'slug' => (string) $row['board_slug'],
                    ];
                }
            }
        }
        return array_values($folders);
    }

    /** @return list<array<string,mixed>> */
    private function savedFeeds(User $user): array
    {
        $rows = $this->db->fetchAll(
            'SELECT id, name, filter_json, digest_enabled, position
             FROM saved_feed_filters
             WHERE user_id = ?
             ORDER BY position ASC, id ASC',
            [$user->id()],
        );
        foreach ($rows as &$row) {
            $filter = json_decode((string) ($row['filter_json'] ?? '{}'), true);
            $filter = is_array($filter) ? $filter : [];
            $boardIds = [];
            foreach (($filter['board_ids'] ?? []) as $boardId) {
                $boardId = (int) $boardId;
                if ($boardId <= 0) {
                    continue;
                }
                $board = $this->boards->find($boardId);
                if ($board === null || !$this->canReadBoard($user, $boardId, (string) ($board['visibility'] ?? 'public'))) {
                    continue;
                }
                $boardIds[$boardId] = $boardId;
            }
            $filter['board_ids'] = array_values($boardIds);
            $row['filter'] = $filter;
        }
        unset($row);
        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function bookmarkFolders(User $user): array
    {
        $rows = $this->db->fetchAll(
            'SELECT f.id AS folder_id, f.name AS folder_name, f.position AS folder_position,
                    t.id AS thread_id, t.title AS thread_title, t.slug AS thread_slug,
                    t.is_deleted AS thread_is_deleted, t.is_pending AS thread_is_pending,
                    b.id AS board_id, b.name AS board_name, b.slug AS board_slug, b.visibility AS board_visibility,
                    COALESCE(tu.is_starred, 0) AS is_starred
             FROM thread_bookmark_folders f
             LEFT JOIN thread_bookmark_folder_threads ft ON ft.folder_id = f.id
             LEFT JOIN threads t ON t.id = ft.thread_id
             LEFT JOIN boards b ON b.id = t.board_id
             LEFT JOIN thread_user tu ON tu.thread_id = t.id AND tu.user_id = ?
             WHERE f.user_id = ?
             ORDER BY f.position ASC, f.id ASC, ft.position ASC, COALESCE(t.last_post_at, t.created_at) DESC',
            [$user->id(), $user->id()],
        );
        $folders = [];
        $staleMemberships = [];
        foreach ($rows as $row) {
            $id = (int) $row['folder_id'];
            if (!isset($folders[$id])) {
                $folders[$id] = [
                    'id' => $id,
                    'name' => (string) $row['folder_name'],
                    'threads' => [],
                ];
            }
            if ($row['thread_id'] !== null) {
                $threadId = (int) $row['thread_id'];
                if ((int) ($row['is_starred'] ?? 0) !== 1) {
                    $staleMemberships[$id . ':' . $threadId] = [$id, $threadId];
                    continue;
                }

                $boardId = (int) $row['board_id'];
                if ((int) ($row['thread_is_deleted'] ?? 0) === 1
                    || (int) ($row['thread_is_pending'] ?? 0) === 1
                    || !$this->canReadBoard($user, $boardId, (string) ($row['board_visibility'] ?? 'public'))
                ) {
                    continue;
                }

                $folders[$id]['threads'][] = [
                    'id' => $threadId,
                    'title' => (string) $row['thread_title'],
                    'slug' => (string) $row['thread_slug'],
                    'board_name' => (string) $row['board_name'],
                    'board_slug' => (string) $row['board_slug'],
                ];
            }
        }

        // Bookmark-folder membership is defined only for currently-starred
        // threads; drop stale links once the user has unstarred a topic.
        foreach ($staleMemberships as [$folderId, $threadId]) {
            $this->db->run(
                'DELETE FROM thread_bookmark_folder_threads WHERE folder_id = ? AND thread_id = ?',
                [$folderId, $threadId],
            );
        }

        return array_values($folders);
    }

    /** @return list<array<string,mixed>> */
    private function starredThreads(User $user): array
    {
        $rows = $this->db->fetchAll(
            'SELECT t.id AS thread_id, t.title AS thread_title, t.slug AS thread_slug,
                    b.id AS board_id, b.name AS board_name, b.slug AS board_slug, b.visibility AS board_visibility
             FROM thread_user tu
             JOIN threads t ON t.id = tu.thread_id
             JOIN boards b ON b.id = t.board_id
             WHERE tu.user_id = ?
               AND tu.is_starred = 1
               AND t.is_deleted = 0
               AND t.is_pending = 0
             ORDER BY COALESCE(t.last_post_at, t.created_at) DESC, t.id DESC',
            [$user->id()],
        );

        $threads = [];
        foreach ($rows as $row) {
            $boardId = (int) $row['board_id'];
            $isMember = $this->members->isMember($boardId, $user->id());
            if (!$this->policy->canRead([
                'visibility' => $row['board_visibility'],
                'id' => $boardId,
            ], $user, $isMember)) {
                continue;
            }
            $threads[] = [
                'id' => (int) $row['thread_id'],
                'title' => (string) $row['thread_title'],
                'slug' => (string) $row['thread_slug'],
                'board_name' => (string) $row['board_name'],
                'board_slug' => (string) $row['board_slug'],
            ];
        }

        return $threads;
    }

    private function canReadBoard(User $user, int $boardId, string $visibility): bool
    {
        if ($boardId <= 0) {
            return false;
        }
        $isMember = $this->members->isMember($boardId, $user->id());
        return $this->policy->canRead(['visibility' => $visibility, 'id' => $boardId], $user, $isMember);
    }
}
