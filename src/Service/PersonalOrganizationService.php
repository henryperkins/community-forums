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
use App\Security\WriteGate;
use App\Repository\SavedFeedRepository;
use App\Repository\BoardFolderRepository;
use App\Support\SavedFeedFilter;

final class PersonalOrganizationService
{
    public function __construct(
        private Database $db,
        private BoardRepository $boards,
        private BoardMemberRepository $members,
        private BoardPolicy $policy,
        private ThreadRepository $threads,
        private ThreadUserRepository $threadUsers,
        private SavedFeedRepository $feeds,
        private BoardFolderRepository $folders,
        private WriteGate $writeGate,
    ) {
    }

    public function createFolder(User $user, string $name): int
    {
        $this->writeGate->assertCanWrite($user);
        return $this->uniqueName(fn () => $this->db->transaction(fn () => $this->folders->create($user->id(), $this->name($name))), ['name' => $name]);
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
        $this->writeGate->assertCanWrite($user);
        $this->ownedFolder($user, $folderId);
        if ($boardId <= 0) { throw new ValidationException(['board_id' => 'Choose a board.'], ['folder_id' => $folderId, 'board_id' => $boardId]); }
        $board = $this->readableBoard($user, $boardId);
        $this->db->transaction(fn () => $this->folders->addBoard($folderId, (int) $board['id']));
    }

    public function renameFolder(User $user, int $id, string $name): void
    {
        $this->ownedFolder($user, $id);
        $this->writeGate->assertCanWrite($user);
        $this->uniqueName(fn () => $this->db->transaction(fn () => $this->folders->rename($user->id(), $id, $this->name($name))), ['name' => $name]);
    }

    public function deleteFolder(User $user, int $id): void
    {
        $this->ownedFolder($user, $id);
        $this->writeGate->assertCanWrite($user);
        $this->db->transaction(fn () => $this->folders->delete($user->id(), $id));
    }

    public function removeBoardFromFolder(User $user, int $folderId, int $boardId): void
    {
        $this->ownedFolder($user, $folderId);
        $this->writeGate->assertCanWrite($user);
        $this->db->transaction(fn () => $this->folders->removeBoard($folderId, $boardId));
    }

    private function ownedFolder(User $user, int $id): array
    {
        return $this->folders->findOwned($user->id(), $id) ?? throw new NotFoundException('Folder not found.');
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
        $this->writeGate->assertCanWrite($user);
        $this->validateFeedInput($input);
        return $this->uniqueName(fn () => $this->db->transaction(fn () => $this->feeds->create(
            $user->id(), $this->name((string) ($input['name'] ?? '')), $this->filterJson($user, $input), !empty($input['digest_enabled']),
        )), $input);
    }

    public function updateSavedFeed(User $user, int $id, array $input): void
    {
        $current = $this->feeds->findOwned($user->id(), $id) ?? throw new NotFoundException('Saved feed not found.');
        // Only this exact reduction bypasses the write gate and stale source access.
        $fields = array_diff(array_keys($input), ['_token', 'digest_enabled']);
        if ($fields === [] && isset($input['digest_enabled']) && in_array($input['digest_enabled'], ['0', 0], true)) {
            $this->db->transaction(fn () => $this->feeds->disableDigest($user->id(), $id));
            return;
        }
        $this->writeGate->assertCanWrite($user);
        $this->validateFeedInput($input);
        $this->uniqueName(function () use ($user, $id, $input, $current): void {
            $name = $this->name((string) ($input['name'] ?? $current['name']));
            // Full forms mark the filter present even when a multiple select has no successful control.
            $json = array_key_exists('board_id', $input) || array_key_exists('board_ids', $input) || array_key_exists('board_filter_present', $input)
                ? $this->filterJson($user, $input, (string) $current['filter_json']) : (string) $current['filter_json'];
            $this->db->transaction(fn () => $this->feeds->update($user->id(), $id, $name, $json, !empty($input['digest_enabled'])));
        }, $input);
    }

    public function deleteSavedFeed(User $user, int $id): void
    {
        $this->feeds->findOwned($user->id(), $id) ?? throw new NotFoundException('Saved feed not found.');
        $this->writeGate->assertCanWrite($user);
        $this->db->transaction(fn () => $this->feeds->delete($user->id(), $id));
    }

    private function validateFeedInput(array $input): void
    {
        $errors = [];
        if (isset($input['name']) && !is_string($input['name'])) { $errors['name'] = 'Enter a valid name.'; }
        if (isset($input['digest_enabled']) && !in_array($input['digest_enabled'], ['0', '1', 0, 1], true)) { $errors['digest_enabled'] = 'Choose a valid digest setting.'; }
        if (array_key_exists('board_filter_present', $input) && !in_array($input['board_filter_present'], ['1', 1], true)) { $errors['board_id'] = 'Choose a valid board filter.'; }
        if ($errors !== []) { throw new ValidationException($errors, $input); }
    }

    private function filterJson(User $user, array $input, ?string $original = null): string
    {
        // A blank selection is explicit all-boards; malformed IDs never mean all.
        $values = $input['board_ids'] ?? (($input['board_id'] ?? '') === '' ? [] : [$input['board_id']]);
        if (!is_array($values)) { throw new ValidationException(['board_id' => 'Choose valid boards.'], $input); }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_int($value) && !is_string($value)) || !ctype_digit((string) $value) || (int) $value <= 0) {
                throw new ValidationException(['board_id' => 'Choose valid boards.'], $input);
            }
            $ids[] = (int) $value;
        }
        $ids = array_values(array_unique($ids));
        $old = $original === null ? null : SavedFeedFilter::parse($original);
        if ($old === null || $old['board_ids'] !== $ids) {
            foreach ($ids as $id) { $this->readableBoard($user, $id); }
        }
        return json_encode(['board_ids' => $ids, 'sort' => 'latest'], JSON_THROW_ON_ERROR);
    }

    private function uniqueName(callable $operation, array $input): mixed
    {
        try { return $operation(); }
        catch (ValidationException $e) { throw new ValidationException($e->errors, $input); }
        catch (\PDOException $e) {
            if ((int) ($e->errorInfo[1] ?? 0) !== 1062) { throw $e; }
            throw new ValidationException(['name' => 'You already have an item with this name.'], $input);
        }
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
        $rows = $this->folders->forUser($user->id());
        $memberIds = array_flip($this->members->boardIdsFor($user->id()));
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
                if ($this->policy->canRead(['id' => $boardId, 'visibility' => $row['board_visibility']], $user, isset($memberIds[$boardId]))) {
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
        $rows = $this->feeds->forUser($user->id());
        $memberIds = array_flip($this->members->boardIdsFor($user->id()));
        $readable = [];
        foreach ($this->boards->allOrdered() as $board) {
            if ($this->policy->canRead($board, $user, isset($memberIds[(int) $board['id']]))) { $readable[(int) $board['id']] = true; }
        }
        foreach ($rows as &$row) {
            $row['filter'] = SavedFeedFilter::parse((string) $row['filter_json']);
            $row['readable_board_count'] = count(array_filter($row['filter']['board_ids'] ?? [], static fn (int $id): bool => isset($readable[$id])));
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
