<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\WriteGate;
use App\Support\Str;

/**
 * Minimal admin console operations: site naming, category + board CRUD with
 * delete-only-when-empty, board slug-change history (for 301 redirects), and an
 * audit row for every structural/config change.
 */
final class AdminService
{
    private const VISIBILITIES = ['public', 'hidden', 'private'];
    private const ROLES = ['user', 'moderator', 'admin'];

    public function __construct(
        private Database $db,
        private CategoryRepository $categories,
        private BoardRepository $boards,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private UserRepository $users,
        private BoardModeratorRepository $boardMods,
        private BoardMemberRepository $boardMembers,
    ) {
    }

    public function setSiteName(User $admin, string $name): void
    {
        $this->assertAdmin($admin);
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['site_name' => 'Site name must be 1–80 characters.']);
        }
        $before = $this->settings->getString('site_name', '');
        $this->db->transaction(function () use ($admin, $name, $before): void {
            $this->settings->set('site_name', $name);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'update_setting',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'site_name',
                'before' => ['site_name' => $before],
                'after' => ['site_name' => $name],
            ]);
        });
    }

    // ---- Categories -------------------------------------------------------

    /** @param array<string,mixed> $input */
    public function createCategory(User $admin, array $input): int
    {
        $this->assertAdmin($admin);
        $name = $this->validateCategoryName($input);
        $position = isset($input['position']) && is_numeric($input['position']) ? (int) $input['position'] : null;

        return $this->db->transaction(function () use ($admin, $name, $position): int {
            $id = $this->categories->create($name, $position);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'create_category',
                'target_type' => 'category',
                'target_id' => $id,
                'after' => ['name' => $name],
            ]);
            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function updateCategory(User $admin, int $id, array $input): void
    {
        $this->assertAdmin($admin);
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new NotFoundException('Category not found.');
        }
        $name = $this->validateCategoryName($input);
        $position = isset($input['position']) && is_numeric($input['position'])
            ? (int) $input['position']
            : (int) $category['position'];

        $this->db->transaction(function () use ($admin, $id, $name, $position, $category): void {
            $this->categories->update($id, $name, $position);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'update_category',
                'target_type' => 'category',
                'target_id' => $id,
                'before' => ['name' => $category['name'], 'position' => (int) $category['position']],
                'after' => ['name' => $name, 'position' => $position],
            ]);
        });
    }

    public function deleteCategory(User $admin, int $id): void
    {
        $this->assertAdmin($admin);
        $category = $this->categories->find($id);
        if ($category === null) {
            throw new NotFoundException('Category not found.');
        }
        if ($this->categories->hasBoards($id)) {
            throw new ValidationException(['category' => 'Move or delete this category’s boards before deleting it.']);
        }

        $this->db->transaction(function () use ($admin, $id, $category): void {
            $this->categories->delete($id);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'delete_category',
                'target_type' => 'category',
                'target_id' => $id,
                'before' => ['name' => $category['name']],
            ]);
        });
    }

    // ---- Boards -----------------------------------------------------------

    /** @param array<string,mixed> $input */
    public function createBoard(User $admin, array $input): int
    {
        $this->assertAdmin($admin);
        [$categoryId, $name, $slug, $description, $visibility, $role, $allowAnon] = $this->validateBoard($input, null);

        return $this->db->transaction(function () use ($admin, $categoryId, $name, $slug, $description, $visibility, $role, $allowAnon): int {
            $id = $this->boards->create([
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility,
                'post_min_role' => $role,
                'allow_anonymous' => $allowAnon,
            ]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'create_board',
                'target_type' => 'board',
                'target_id' => $id,
                'after' => ['name' => $name, 'slug' => $slug, 'visibility' => $visibility, 'allow_anonymous' => $allowAnon],
            ]);
            return $id;
        });
    }

    /** @param array<string,mixed> $input */
    public function updateBoard(User $admin, int $id, array $input): void
    {
        $this->assertAdmin($admin);
        $board = $this->boards->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        [$categoryId, $name, $slug, $description, $visibility, $role, $allowAnon] = $this->validateBoard($input, $board);

        $oldSlug = (string) $board['slug'];
        $slugChanged = $slug !== $oldSlug;

        $this->db->transaction(function () use ($admin, $id, $categoryId, $name, $slug, $description, $visibility, $role, $allowAnon, $oldSlug, $slugChanged, $board): void {
            if ($slugChanged) {
                $this->boards->recordSlugChange($id, $oldSlug);
            }
            $this->boards->update($id, [
                'category_id' => $categoryId,
                'slug' => $slug,
                'name' => $name,
                'description' => $description,
                'visibility' => $visibility,
                'post_min_role' => $role,
                'allow_anonymous' => $allowAnon,
            ]);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'update_board',
                'target_type' => 'board',
                'target_id' => $id,
                'before' => ['name' => $board['name'], 'slug' => $oldSlug, 'visibility' => $board['visibility'], 'allow_anonymous' => (int) ($board['allow_anonymous'] ?? 0)],
                'after' => ['name' => $name, 'slug' => $slug, 'visibility' => $visibility, 'allow_anonymous' => $allowAnon],
            ]);
        });
    }

    public function deleteBoard(User $admin, int $id): void
    {
        $this->assertAdmin($admin);
        $board = $this->boards->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        if ($this->boards->hasThreads($id)) {
            throw new ValidationException(['board' => 'Only empty boards can be deleted.']);
        }

        $this->db->transaction(function () use ($admin, $id, $board): void {
            $this->boards->delete($id);
            $this->log->log([
                'actor_id' => $admin->id(),
                'action' => 'delete_board',
                'target_type' => 'board',
                'target_id' => $id,
                'before' => ['name' => $board['name'], 'slug' => $board['slug']],
            ]);
        });
    }

    // ---- Board roster: moderators + members (P2-08) -----------------------

    /**
     * Assign a member as a scoped moderator of a board. The capability model is
     * board-scoped (ModerationService::canModerate), so an admin is intentionally
     * rejected here — admins already moderate every board.
     */
    public function assignModerator(User $admin, int $boardId, string $username): void
    {
        $this->assertAdmin($admin);
        $this->boardOrFail($boardId);
        $user = $this->resolveMember($username);
        $userId = (int) $user['id'];

        if (($user['role'] ?? 'user') === 'admin') {
            throw new ValidationException(['username' => '@' . $user['username'] . ' is an administrator and already moderates every board.']);
        }
        if ($this->boardMods->isModerator($boardId, $userId)) {
            throw new ValidationException(['username' => '@' . $user['username'] . ' already moderates this board.']);
        }

        // Log inside the transaction and only when a row actually changed, so the
        // audit entry is exactly-once even if two identical requests race past the
        // pre-check above (the INSERT IGNORE absorbs the loser, rowCount 0).
        $this->db->transaction(function () use ($admin, $boardId, $userId, $user): void {
            if ($this->boardMods->assign($boardId, $userId) > 0) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'assign_moderator',
                    'target_type' => 'board',
                    'target_id' => $boardId,
                    'after' => ['user_id' => $userId, 'username' => $user['username']],
                ]);
            }
        });
    }

    public function unassignModerator(User $admin, int $boardId, int $userId): void
    {
        $this->assertAdmin($admin);
        $this->boardOrFail($boardId);
        if (!$this->boardMods->isModerator($boardId, $userId)) {
            throw new ValidationException(['moderator' => 'That member does not moderate this board.']);
        }
        $user = $this->users->find($userId);

        $this->db->transaction(function () use ($admin, $boardId, $userId, $user): void {
            if ($this->boardMods->unassign($boardId, $userId) > 0) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'unassign_moderator',
                    'target_type' => 'board',
                    'target_id' => $boardId,
                    'before' => ['user_id' => $userId, 'username' => $user['username'] ?? null],
                ]);
            }
        });
    }

    /**
     * Grant a member access to a private/hidden board. On a public board this is
     * a harmless no-op for access (everyone can already read), so it is allowed
     * but the UI explains where membership actually matters.
     */
    public function addMember(User $admin, int $boardId, string $username): void
    {
        $this->assertAdmin($admin);
        $this->boardOrFail($boardId);
        $user = $this->resolveMember($username);
        $userId = (int) $user['id'];

        if ($this->boardMembers->isMember($boardId, $userId)) {
            throw new ValidationException(['username' => '@' . $user['username'] . ' is already a member of this board.']);
        }

        $this->db->transaction(function () use ($admin, $boardId, $userId, $user): void {
            if ($this->boardMembers->add($boardId, $userId, $admin->id()) > 0) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'add_member',
                    'target_type' => 'board',
                    'target_id' => $boardId,
                    'after' => ['user_id' => $userId, 'username' => $user['username']],
                ]);
            }
        });
    }

    public function removeMember(User $admin, int $boardId, int $userId): void
    {
        $this->assertAdmin($admin);
        $this->boardOrFail($boardId);
        if (!$this->boardMembers->isMember($boardId, $userId)) {
            throw new ValidationException(['member' => 'That member is not on this board.']);
        }
        $user = $this->users->find($userId);

        $this->db->transaction(function () use ($admin, $boardId, $userId, $user): void {
            if ($this->boardMembers->remove($boardId, $userId) > 0) {
                $this->log->log([
                    'actor_id' => $admin->id(),
                    'action' => 'remove_member',
                    'target_type' => 'board',
                    'target_id' => $boardId,
                    'before' => ['user_id' => $userId, 'username' => $user['username'] ?? null],
                ]);
            }
        });
    }

    /** @return array<string,mixed> the existing board row, or 404. */
    private function boardOrFail(int $boardId): array
    {
        $board = $this->boards->find($boardId);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        return $board;
    }

    /** Resolve a typed @handle to an existing account, or a friendly validation error. */
    private function resolveMember(string $username): array
    {
        $username = ltrim(trim($username), '@');
        if ($username === '') {
            throw new ValidationException(['username' => 'Enter a username.']);
        }
        $user = $this->users->findByUsername($username);
        if ($user === null) {
            throw new ValidationException(['username' => 'No member found with the username “' . $username . '”.']);
        }
        return $user;
    }

    // ---- validation -------------------------------------------------------

    /** @param array<string,mixed> $input */
    private function validateCategoryName(array $input): string
    {
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 64) {
            throw new ValidationException(['name' => 'Category name must be 1–64 characters.'], $input);
        }
        return $name;
    }

    /**
     * @param array<string,mixed> $input
     * @param array<string,mixed>|null $existing
     * @return array{0:int,1:string,2:string,3:?string,4:string,5:string}
     */
    private function validateBoard(array $input, ?array $existing): array
    {
        $errors = [];

        $categoryId = (int) ($input['category_id'] ?? 0);
        if ($categoryId <= 0 || $this->categories->find($categoryId) === null) {
            $errors['category_id'] = 'Choose a valid category.';
        }

        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = 'Board name must be 1–80 characters.';
        }

        $rawSlug = trim((string) ($input['slug'] ?? ''));
        $slug = Str::slug($rawSlug !== '' ? $rawSlug : $name, 64);

        $description = trim((string) ($input['description'] ?? ''));
        if (mb_strlen($description) > 255) {
            $errors['description'] = 'Description is too long (max 255).';
        }

        $visibility = (string) ($input['visibility'] ?? 'public');
        if (!in_array($visibility, self::VISIBILITIES, true)) {
            $errors['visibility'] = 'Invalid visibility.';
        }

        $role = (string) ($input['post_min_role'] ?? 'user');
        if (!in_array($role, self::ROLES, true)) {
            $role = 'user';
        }

        $allowAnon = !empty($input['allow_anonymous']) ? 1 : 0;

        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        // Ensure the slug is unique (and not a reserved historical slug),
        // unless it already belongs to the board being edited.
        $slug = $this->uniqueSlug($slug, $existing !== null ? (int) $existing['id'] : null);

        return [$categoryId, $name, $slug, $description !== '' ? $description : null, $visibility, $role, $allowAnon];
    }

    private function uniqueSlug(string $slug, ?int $boardId): string
    {
        $candidate = $slug;
        $n = 1;
        while ($this->slugTaken($candidate, $boardId)) {
            $n++;
            $candidate = $slug . '-' . $n;
        }
        return $candidate;
    }

    /** A slug is taken if a different live board uses it, or it is reserved in history by a different board. */
    private function slugTaken(string $slug, ?int $boardId): bool
    {
        $self = $boardId ?? -1;

        $live = $this->boards->findBySlug($slug);
        if ($live !== null && (int) $live['id'] !== $self) {
            return true;
        }

        $historical = $this->boards->currentSlugForOld($slug);
        return $historical !== null && (int) $historical['id'] !== $self;
    }

    private function assertAdmin(User $admin): void
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        $this->writeGate->assertCanWrite($admin);
    }
}
