<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
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
        return $this->view('admin/dashboard', [
            'audit' => $this->container->get(ModerationLogRepository::class)->recent(50),
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
        return $this->view('admin/board_edit', [
            'board' => $board,
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'errors' => [],
            'old' => $board,
        ]);
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
            return $this->view('admin/board_edit', [
                'board' => $board,
                'categories' => $this->container->get(CategoryRepository::class)->all(),
                'errors' => $e->errors,
                'old' => $e->old + $board,
            ], 422);
        }
        return $this->redirectWithFlash('/admin/structure', 'Board updated.');
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
