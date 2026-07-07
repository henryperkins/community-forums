<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\BoardPolicy;
use App\Service\AdminDashboardService;
use App\Service\AdminService;
use App\Service\CustomEmojiService;

/**
 * Minimal admin console: dashboard + audit feed, site naming, and
 * category/board management (create / edit / delete-when-empty). Admin-only;
 * non-admins get 403, guests are bounced to login.
 */
final class AdminController extends Controller
{
    /** @param array<string,string> $params */
    public function dashboard(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $settings = $this->container->get(SettingRepository::class);
        $dashboard = $this->container->get(AdminDashboardService::class)->summary();
        $customEmojiOn = $this->container->get(FeatureFlags::class)->enabled('custom_emoji');
        return $this->view('admin/dashboard', [
            'cards' => $dashboard['cards'],
            'attention' => $dashboard['attention'],
            'audit' => $dashboard['audit'],
            'custom_emoji_on' => $customEmojiOn,
            'custom_emoji' => $customEmojiOn ? $this->container->get(CustomEmojiService::class)->catalogue() : [],
            'mailer_configured' => $dashboard['mailer_configured'],
            'send_blocked' => $dashboard['send_blocked'],
            'registration_mode' => $settings->getString('registration_mode', 'open'),
            'antiabuse_mode' => $settings->getString('antiabuse_mode', 'observe'),
            'antiabuse_blocked_words' => (array) $settings->get('antiabuse_blocked_words', []),
            'registration_modes' => AdminService::REGISTRATION_MODES,
            'antiabuse_modes' => AdminService::ANTIABUSE_MODES,
        ]);
    }

    /** @param array<string,string> $params */
    public function structure(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->structureView();
    }

    /** @param array<string,string> $params */
    public function updateSite(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        return $this->run(
            fn () => $this->container->get(AdminService::class)->setSiteName($admin, $request->str('site_name')),
            '/admin',
            'Site name updated.',
        );
    }

    /** @param array<string,string> $params */
    public function updateSettings(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        return $this->run(
            fn () => $this->container->get(AdminService::class)->updateModerationSettings($admin, $request->allInput()),
            '/admin',
            'Trust & safety settings saved.',
        );
    }

    /** @param array<string,string> $params */
    public function createCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        try {
            $this->container->get(AdminService::class)->createCategory($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->structureView([
                'create_category_error' => $e->first(),
                'create_category_old' => $e->old,
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Category created.');
    }

    /** @param array<string,string> $params */
    public function updateCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(AdminService::class)->updateCategory($admin, $id, $request->allInput());
        } catch (ValidationException $e) {
            return $this->structureView([
                'update_category_id' => $id,
                'update_category_error' => $e->first(),
                'update_category_old' => $e->old,
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Category updated.');
    }

    /**
     * Confirmation page for deleting a category (no-JS friendly, shows board
     * count + whether deletion is blocked). GET only; the POST below mutates.
     *
     * @param array<string,string> $params
     */
    public function confirmDeleteCategory(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->confirmCategoryView($this->categoryOrFail((int) ($params['id'] ?? 0)));
    }

    /** @param array<string,string> $params */
    public function deleteCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $category = $this->categoryOrFail((int) ($params['id'] ?? 0));
        if (trim((string) $request->post('confirm', '')) !== (string) $category['name']) {
            return $this->confirmCategoryView($category, 'Enter the category name exactly to confirm deletion.', 422);
        }
        try {
            $this->container->get(AdminService::class)->deleteCategory($admin, (int) $category['id']);
        } catch (ValidationException $e) {
            return $this->confirmCategoryView($category, $e->first(), 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Category deleted.');
    }

    /** @param array<string,string> $params */
    public function editBoard(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $board = $this->container->get(BoardRepository::class)->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        return $this->boardEditView($board, [], $board);
    }

    /** @param array<string,string> $params */
    public function createBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        try {
            $this->container->get(AdminService::class)->createBoard($admin, $request->allInput());
        } catch (ValidationException $e) {
            return $this->structureView([
                'create_board_errors' => $e->errors,
                'create_board_old' => $e->old,
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Board created.');
    }

    /** @param array<string,string> $params */
    public function updateBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);

        try {
            $this->container->get(AdminService::class)->updateBoard($admin, $id, $request->allInput());
        } catch (ValidationException $e) {
            $board = $this->container->get(BoardRepository::class)->find($id);
            if ($board === null) {
                throw new NotFoundException('Board not found.');
            }
            return $this->boardEditView($board, $e->errors, $e->old + $board, 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Board updated.');
    }

    // ---- Board roster: moderators + members (P2-08) -----------------------
    //
    // Phase 5 Inc 6 Task 8: these four command endpoints are authorized at the
    // SERVICE layer (AdminService asserts core.board.assign_moderators /
    // core.board.manage_members via AuthorityGate, state-first, legacy closure
    // admin-only) so a custom role holding the matching key can manage a
    // board's roster once CAPABILITIES_MODE=enforce. Every other /admin action
    // — including editBoard()/the board edit GET view below — stays on
    // requireAdmin(); this cuts over the four POST commands only, not the
    // admin console.

    // The four roster commands authorize inside AdminService (state-then-
    // capability) BEFORE that service resolves the board row, so a non-admin is
    // denied with 403 whether or not the board exists. The controller must NOT
    // boardOrFail() up front: doing so 404s a missing id before authorization
    // and turns the 404-vs-403 split into a board-id existence oracle over
    // hidden/private boards (review V1). The row is resolved only once the
    // service has authorized — in the catch block (a ValidationException only
    // fires after the service's own boardOrFail passed, so it provably exists)
    // and on the success path.

    /** @param array<string,string> $params */
    public function assignModerator(Request $request, array $params): Response
    {
        $username = $request->str('username');

        return $this->rosterCommand(
            fn (User $actor, int $boardId) => $this->container->get(AdminService::class)->assignModerator($actor, $boardId, $username),
            (int) ($params['id'] ?? 0),
            'moderator',
            $username,
            'Moderator assigned.',
        );
    }

    /** @param array<string,string> $params */
    public function unassignModerator(Request $request, array $params): Response
    {
        return $this->rosterCommand(
            fn (User $actor, int $boardId) => $this->container->get(AdminService::class)->unassignModerator($actor, $boardId, $request->int('user_id')),
            (int) ($params['id'] ?? 0),
            'moderator',
            '',
            'Moderator removed.',
        );
    }

    /** @param array<string,string> $params */
    public function addMember(Request $request, array $params): Response
    {
        $username = $request->str('username');

        return $this->rosterCommand(
            fn (User $actor, int $boardId) => $this->container->get(AdminService::class)->addMember($actor, $boardId, $username),
            (int) ($params['id'] ?? 0),
            'member',
            $username,
            'Member added.',
        );
    }

    /** @param array<string,string> $params */
    public function removeMember(Request $request, array $params): Response
    {
        return $this->rosterCommand(
            fn (User $actor, int $boardId) => $this->container->get(AdminService::class)->removeMember($actor, $boardId, $request->int('user_id')),
            (int) ($params['id'] ?? 0),
            'member',
            '',
            'Member removed.',
        );
    }

    /**
     * Shared body for the four roster POST commands: run the service call
     * (which authorizes BEFORE resolving the board row — V1), translate a
     * ValidationException into the deputy redirect or the admin board-edit
     * re-render (anti-draft-loss), and confirm via rosterDone.
     *
     * @param callable(User,int):void $command
     */
    private function rosterCommand(callable $command, int $boardId, string $rosterContext, string $rosterUsername, string $done): Response
    {
        $actor = $this->requireUser();
        try {
            $command($actor, $boardId);
        } catch (ValidationException $e) {
            $board = $this->boardOrFail($boardId);
            if (!$actor->isAdmin()) {
                return $this->redirectWithFlash($this->rosterDeputyExit($actor, $board), $e->first());
            }
            return $this->boardEditView($board, $e->errors, $board, 422, $e->first(), $rosterContext, $rosterUsername);
        }
        return $this->rosterDone($actor, $this->boardOrFail($boardId), $done);
    }

    /**
     * Success response for a roster command. An admin keeps the console flow
     * (redirect back to the admin-only board edit page). A capability-holding
     * non-admin deputy — who cannot see /admin/boards/{id}/edit (still
     * requireAdmin()) — is flashed the same confirmation and sent to a page
     * they can actually view, never the admin console.
     *
     * @param array<string,mixed> $board
     */
    private function rosterDone(User $actor, array $board, string $message): Response
    {
        if ($actor->isAdmin()) {
            return $this->redirectWithFlash('/admin/boards/' . (int) $board['id'] . '/edit', $message);
        }
        return $this->redirectWithFlash($this->rosterDeputyExit($actor, $board), $message);
    }

    /**
     * Where a NON-ADMIN roster deputy lands after a command (success or a
     * validation error): the board's own page when they can read it, otherwise
     * the community home. Never the admin/board_edit console — rendering that
     * for a non-admin would leak admin-only surface (board settings, the admin
     * nav, the "Admin mode" pill). We resolve their real read access (a
     * manage_members deputy on a private board is typically NOT a member, so
     * /c/{slug} would 404 for them) and fall back to '/', which any signed-in
     * user can see, carrying the confirmation in the flash.
     *
     * @param array<string,mixed> $board
     */
    private function rosterDeputyExit(User $actor, array $board): string
    {
        $isMember = $this->container->get(BoardMemberRepository::class)->isMember((int) $board['id'], $actor->id());
        $canRead = $this->container->get(BoardPolicy::class)->canRead($board, $actor, $isMember);
        return $canRead ? '/c/' . (string) $board['slug'] : '/';
    }

    /**
     * Confirmation page for deleting a board (shows thread/post counts,
     * visibility, and whether deletion is blocked). GET only.
     *
     * @param array<string,string> $params
     */
    public function confirmDeleteBoard(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->confirmBoardView($this->boardOrFail((int) ($params['id'] ?? 0)), 'delete');
    }

    /** @param array<string,string> $params */
    public function deleteBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $board = $this->boardOrFail((int) ($params['id'] ?? 0));
        if (trim((string) $request->post('confirm', '')) !== (string) $board['slug']) {
            return $this->confirmBoardView($board, 'delete', 'Enter the board slug exactly to confirm deletion.', 422);
        }
        try {
            $this->container->get(AdminService::class)->deleteBoard($admin, (int) $board['id']);
        } catch (ValidationException $e) {
            return $this->confirmBoardView($board, 'delete', $e->first(), 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Board deleted.');
    }

    // ---- Structure ordering + archive (Phase 2) ---------------------------

    /** @param array<string,string> $params */
    public function moveCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(AdminService::class)->moveCategory($admin, $id, (string) $request->post('dir', ''));
        } catch (ValidationException $e) {
            return $this->structureView(['reorder_error' => $e->first()], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Order updated.');
    }

    /** @param array<string,string> $params */
    public function moveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(AdminService::class)->moveBoard($admin, $id, (string) $request->post('dir', ''));
        } catch (ValidationException $e) {
            return $this->structureView(['reorder_error' => $e->first()], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Order updated.');
    }

    /**
     * Bulk reorder target for the optional JS drag enhancement. On a bad id-set
     * it re-renders the structure page at 422 (no redirect) so the AJAX caller
     * sees the failure and the no-JS up/down buttons stay the working path.
     *
     * @param array<string,string> $params
     */
    public function reorder(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $scope = (string) $request->post('scope', '');
        $rawIds = $request->post('ids', []);
        $ids = is_array($rawIds) ? array_map('intval', $rawIds) : [];

        try {
            if ($scope === 'category') {
                $this->container->get(AdminService::class)->reorderCategories($admin, $ids);
            } else {
                $this->container->get(AdminService::class)->reorderBoards($admin, (int) $request->post('category_id', 0), $ids);
            }
        } catch (ValidationException $e) {
            return $this->view('admin/structure', [
                'categories' => $this->container->get(CategoryRepository::class)->all(),
                'boards_by_category' => $this->boardsByCategory(),
                'reorder_error' => $e->first(),
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Order updated.');
    }

    /**
     * Confirmation page for archiving a board (read-only impact). GET only.
     *
     * @param array<string,string> $params
     */
    public function confirmArchiveBoard(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->confirmBoardView($this->boardOrFail((int) ($params['id'] ?? 0)), 'archive');
    }

    /** @param array<string,string> $params */
    public function archiveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $board = $this->boardOrFail((int) ($params['id'] ?? 0));
        if (trim((string) $request->post('confirm', '')) !== (string) $board['slug']) {
            return $this->confirmBoardView($board, 'archive', 'Enter the board slug exactly to confirm.', 422);
        }
        $this->container->get(AdminService::class)->archiveBoard($admin, (int) $board['id']);
        return $this->redirectWithFlash('/admin/structure', 'Board archived — it is now read-only.');
    }

    /**
     * Confirmation page for unarchiving a board (posting re-enabled). GET only.
     *
     * @param array<string,string> $params
     */
    public function confirmUnarchiveBoard(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->confirmBoardView($this->boardOrFail((int) ($params['id'] ?? 0)), 'unarchive');
    }

    /** @param array<string,string> $params */
    public function unarchiveBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $board = $this->boardOrFail((int) ($params['id'] ?? 0));
        if (trim((string) $request->post('confirm', '')) !== (string) $board['slug']) {
            return $this->confirmBoardView($board, 'unarchive', 'Enter the board slug exactly to confirm.', 422);
        }
        $this->container->get(AdminService::class)->unarchiveBoard($admin, (int) $board['id']);
        return $this->redirectWithFlash('/admin/structure', 'Board restored — posting re-enabled.');
    }

    /**
     * Render the board edit screen with its moderator + member rosters. Shared by
     * the GET form, the board-settings POST 422 re-render, and the roster POST 422
     * re-renders so all three always show the rosters. `$rosterError`/`$rosterContext`
     * surface a roster failure ('moderator'|'member') next to the right form and
     * `$rosterUsername` re-fills the offending add-form input (the service throws
     * without `old`).
     *
     * @param array<string,mixed> $board
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function boardEditView(array $board, array $errors, array $old, int $status = 200, ?string $rosterError = null, ?string $rosterContext = null, string $rosterUsername = ''): Response
    {
        $id = (int) $board['id'];
        return $this->view('admin/board_edit', [
            'board' => $board,
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'errors' => $errors,
            'old' => $old,
            'moderators' => $this->container->get(BoardModeratorRepository::class)->moderatorsFor($id),
            'members' => $this->container->get(BoardMemberRepository::class)->membersFor($id),
            'roster_error' => $rosterError,
            'roster_context' => $rosterContext,
            'roster_username' => $rosterUsername,
        ], $status);
    }

    /**
     * Render the structure page. Shared by the GET view and every POST 422
     * re-render (create/update category, create board, move, reorder), which
     * pass their contextual error/old vars through `$extra`.
     *
     * @param array<string,mixed> $extra
     */
    private function structureView(array $extra = [], int $status = 200): Response
    {
        return $this->view('admin/structure', [
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'boards_by_category' => $this->boardsByCategory(),
        ] + $extra, $status);
    }

    /**
     * Confirmation screen for deleting a category: shows the board count and
     * blocks (no form) when the category still has boards.
     *
     * @param array<string,mixed> $category
     */
    private function confirmCategoryView(array $category, ?string $error = null, int $status = 200): Response
    {
        $count = count($this->container->get(BoardRepository::class)->byCategory((int) $category['id']));
        $blocked = $count > 0;
        return $this->view('admin/structure_confirm', [
            'page_title' => 'Delete category',
            'heading' => 'Delete the “' . $category['name'] . '” category?',
            'intro' => 'Deleting a category cannot be undone. Only empty categories can be deleted.',
            'impact' => [
                ['label' => 'Category', 'value' => $category['name']],
                ['label' => 'Boards in this category', 'value' => $count],
            ],
            'action' => '/admin/categories/' . (int) $category['id'] . '/delete',
            'confirm_noun' => 'category name',
            'confirm_target' => (string) $category['name'],
            'submit_label' => 'Delete category',
            'danger' => true,
            'blocked' => $blocked,
            'blocked_reason' => $blocked
                ? 'This category still has ' . $count . ' board' . ($count === 1 ? '' : 's') . '. Move or delete them before deleting the category.'
                : null,
            'error' => $error,
        ], $status);
    }

    /**
     * Confirmation screen for a board delete/archive/unarchive. Delete blocks
     * (no form) when the board still has threads; archive/unarchive are always
     * available. All three require the board slug typed to confirm.
     *
     * @param array<string,mixed> $board
     */
    private function confirmBoardView(array $board, string $kind, ?string $error = null, int $status = 200): Response
    {
        $id = (int) $board['id'];
        $threads = (int) ($board['thread_count'] ?? 0);
        $impact = [
            ['label' => 'Board', 'value' => '#' . $board['name'] . '  (/c/' . $board['slug'] . ')'],
            ['label' => 'Visibility', 'value' => ucfirst((string) $board['visibility'])],
            ['label' => 'Threads', 'value' => $threads],
            ['label' => 'Posts', 'value' => (int) ($board['post_count'] ?? 0)],
        ];

        if ($kind === 'archive') {
            $data = [
                'page_title' => 'Archive board',
                'heading' => 'Archive the “' . $board['name'] . '” board?',
                'intro' => 'Archiving makes the board read-only: its content stays visible, but nobody — including admins and board moderators — can post, reply, react, or moderate until it is unarchived. This is reversible.',
                'action' => '/admin/boards/' . $id . '/archive',
                'submit_label' => 'Archive board',
                'danger' => false,
                'blocked' => false,
                'blocked_reason' => null,
            ];
        } elseif ($kind === 'unarchive') {
            $data = [
                'page_title' => 'Unarchive board',
                'heading' => 'Unarchive the “' . $board['name'] . '” board?',
                'intro' => 'Unarchiving re-enables posting: members can create threads and reply again, and moderators can act on content.',
                'action' => '/admin/boards/' . $id . '/unarchive',
                'submit_label' => 'Unarchive board',
                'danger' => false,
                'blocked' => false,
                'blocked_reason' => null,
            ];
        } else {
            $blocked = $threads > 0;
            $data = [
                'page_title' => 'Delete board',
                'heading' => 'Delete the “' . $board['name'] . '” board?',
                'intro' => 'Deleting a board cannot be undone and removes its settings, moderators, and members. Only empty boards can be deleted.',
                'action' => '/admin/boards/' . $id . '/delete',
                'submit_label' => 'Delete board',
                'danger' => true,
                'blocked' => $blocked,
                'blocked_reason' => $blocked
                    ? 'This board still has ' . $threads . ' thread' . ($threads === 1 ? '' : 's') . '. Move or delete its content before deleting the board.'
                    : null,
            ];
        }

        return $this->view('admin/structure_confirm', $data + [
            'impact' => $impact,
            'confirm_noun' => 'board slug',
            'confirm_target' => (string) $board['slug'],
            'error' => $error,
        ], $status);
    }

    /**
     * Fetch a category by id or 404. Used by the delete confirmation flow so
     * the GET page and the POST guard share one lookup + error.
     *
     * @return array<string,mixed>
     */
    private function categoryOrFail(int $id): array
    {
        $category = $this->container->get(CategoryRepository::class)->find($id);
        if ($category === null) {
            throw new NotFoundException('Category not found.');
        }
        return $category;
    }

    /**
     * Fetch a board by id or 404. Shared by the confirmation flow and the roster
     * actions so the entity is resolved once per request.
     *
     * @return array<string,mixed>
     */
    private function boardOrFail(int $id): array
    {
        $board = $this->container->get(BoardRepository::class)->find($id);
        if ($board === null) {
            throw new NotFoundException('Board not found.');
        }
        return $board;
    }

    /**
     * Run an admin action, redirecting with a success flash, or — on a
     * validation failure — back with the first error message.
     */
    private function run(callable $action, string $redirectTo, string $success): Response
    {
        try {
            $action();
        } catch (ValidationException $e) {
            return $this->redirectWithFlash($redirectTo, $e->first());
        }
        return $this->redirectWithFlash($redirectTo, $success);
    }

    /** @return array<int,array<int,array<string,mixed>>> category_id => boards */
    private function boardsByCategory(): array
    {
        $grouped = [];
        foreach ($this->container->get(BoardRepository::class)->allOrdered() as $board) {
            $grouped[(int) $board['category_id']][] = $board;
        }
        return $grouped;
    }
}
