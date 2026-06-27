<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Service\AdminService;

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
        return $this->view('admin/dashboard', [
            'audit' => $this->container->get(ModerationLogRepository::class)->recent(50),
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
        return $this->view('admin/structure', [
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'boards_by_category' => $this->boardsByCategory(),
        ]);
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
        return $this->run(
            fn () => $this->container->get(AdminService::class)->createCategory($admin, $request->allInput()),
            '/admin/structure',
            'Category created.',
        );
    }

    /** @param array<string,string> $params */
    public function updateCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->updateCategory($admin, $id, $request->allInput()),
            '/admin/structure',
            'Category updated.',
        );
    }

    /** @param array<string,string> $params */
    public function deleteCategory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->deleteCategory($admin, $id),
            '/admin/structure',
            'Category deleted.',
        );
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
        return $this->run(
            fn () => $this->container->get(AdminService::class)->createBoard($admin, $request->allInput()),
            '/admin/structure',
            'Board created.',
        );
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

    /** @param array<string,string> $params */
    public function assignModerator(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->assignModerator($admin, $id, $request->str('username')),
            '/admin/boards/' . $id . '/edit',
            'Moderator assigned.',
        );
    }

    /** @param array<string,string> $params */
    public function unassignModerator(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->unassignModerator($admin, $id, $request->int('user_id')),
            '/admin/boards/' . $id . '/edit',
            'Moderator removed.',
        );
    }

    /** @param array<string,string> $params */
    public function addMember(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->addMember($admin, $id, $request->str('username')),
            '/admin/boards/' . $id . '/edit',
            'Member added.',
        );
    }

    /** @param array<string,string> $params */
    public function removeMember(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->removeMember($admin, $id, $request->int('user_id')),
            '/admin/boards/' . $id . '/edit',
            'Member removed.',
        );
    }

    /** @param array<string,string> $params */
    public function deleteBoard(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        return $this->run(
            fn () => $this->container->get(AdminService::class)->deleteBoard($admin, $id),
            '/admin/structure',
            'Board deleted.',
        );
    }

    /**
     * Render the board edit screen with its moderator + member rosters. Shared by
     * the GET form and the POST 422 re-render so both always show the rosters.
     *
     * @param array<string,mixed> $board
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function boardEditView(array $board, array $errors, array $old, int $status = 200): Response
    {
        $id = (int) $board['id'];
        return $this->view('admin/board_edit', [
            'board' => $board,
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'errors' => $errors,
            'old' => $old,
            'moderators' => $this->container->get(BoardModeratorRepository::class)->moderatorsFor($id),
            'members' => $this->container->get(BoardMemberRepository::class)->membersFor($id),
        ], $status);
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
