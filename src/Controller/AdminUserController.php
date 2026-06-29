<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BadgeRepository;
use App\Repository\UserRepository;
use App\Service\BadgeService;
use App\Service\TitleService;
use App\Service\UserModerationService;

/**
 * Per-user admin record (ADMIN §5.1 directory + §5.2 record screen): hosts the
 * manual badge grant/revoke and the cosmetic title override. UNGATED. Every
 * action requires an admin; the user-targeted writes route through services
 * that block a suspended admin (state beats role) and write one moderation_log
 * row each.
 */
final class AdminUserController extends Controller
{
    private const PER_PAGE = 50;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $q = trim($request->str('q'));
        $page = max(0, $request->int('page', 0));
        $rows = $this->container->get(UserRepository::class)
            ->directory($q, self::PER_PAGE, $page * self::PER_PAGE);

        return $this->view('admin/users', [
            'users' => $rows,
            'q' => $q,
            'page' => $page,
            'has_next' => count($rows) === self::PER_PAGE,
        ]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->record((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function setTitle(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(UserModerationService::class)
                ->setTitle($admin, $id, $request->str('title'));
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Title updated.');
    }

    /** @param array<string,string> $params */
    public function grantBadge(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        $reason = $request->str('reason');
        try {
            $this->container->get(BadgeService::class)
                ->grantManual($admin, $id, $request->str('slug'), $reason !== '' ? $reason : null);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Badge granted.');
    }

    /** @param array<string,string> $params */
    public function revokeBadge(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        $reason = $request->str('reason');
        try {
            $this->container->get(BadgeService::class)
                ->revokeManual($admin, $id, $request->str('slug'), $reason !== '' ? $reason : null);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Badge revoked.');
    }

    /** @param array<string,string> $params */
    public function removeSignature(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('profile_media')) {
            throw new NotFoundException('User not found.');
        }
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->container->get(UserModerationService::class)->clearSignature($admin, $id);
        return $this->redirectWithFlash('/admin/users/' . $id, 'Signature removed.');
    }

    /** Render the per-user admin record (ADMIN §5.2). */
    private function record(int $id, ?ValidationException $error = null, int $status = 200): Response
    {
        $subject = $this->requireSubject($id);
        $badges = $this->container->get(BadgeRepository::class);
        $titles = $this->container->get(TitleService::class);
        $reputation = (int) ($subject['reputation'] ?? 0);

        return $this->view('admin/user_record', [
            'subject' => $subject,
            'stored_title' => $subject['title'] ?? null,
            'effective_title' => $titles->resolve($subject['title'] ?? null, $reputation),
            'derived_title' => $titles->derive($reputation),
            'held_manual' => $badges->manualHeldByUser($id),
            'catalogue' => $badges->manualCatalogue(),
            'errors' => $error?->errors ?? [],
            'old' => $error?->old ?? [],
            'profile_media' => $this->container->get(FeatureFlags::class)->enabled('profile_media'),
        ], $status);
    }

    /** @return array<string,mixed> */
    private function requireSubject(int $id): array
    {
        $subject = $this->container->get(UserRepository::class)->find($id);
        if ($subject === null) {
            throw new NotFoundException('User not found.');
        }
        return $subject;
    }
}
