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
    private const ROLES = ['user', 'moderator', 'admin'];
    private const STATUSES = ['active', 'suspended', 'banned', 'deactivated'];
    private const SORTS = ['username', 'role', 'status', 'created_at', 'last_seen', 'post_count', 'reputation'];
    private const LAST_SEEN = ['', '1', '7', '30', '90', 'never'];
    private const BULK_ACTIONS = ['warn', 'suspend'];
    private const BULK_MAX = 50;

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->directoryView($request);
    }

    /**
     * Step 1 of bulk moderation (ADMIN §5.1 bulk-selectable + §3.2 "each still
     * audited individually"): validate the selection and show a confirmation
     * page with the shared reason (and, for suspend, the shared expiry) before
     * anything is written.
     *
     * @param array<string,string> $params
     */
    public function bulkConfirm(Request $request, array $params): Response
    {
        $this->requireAdmin();

        $action = (string) $request->post('bulk_action', '');
        $ids = $this->selectedIds($request);
        if (!in_array($action, self::BULK_ACTIONS, true)) {
            return $this->directoryView($request, 'Choose a bulk action to apply.', 422);
        }
        if ($ids === []) {
            return $this->directoryView($request, 'Select at least one member first.', 422);
        }

        return $this->bulkConfirmView($action, $ids);
    }

    /**
     * Step 2 of bulk moderation: apply the action to every selected member,
     * one audited service call each. Per-member refusals (an admin target,
     * yourself) are skipped and reported; a shared-input validation failure
     * (empty reason, malformed expiry) aborts before any member is written and
     * re-renders the confirmation at 422 with the typed input preserved.
     *
     * @param array<string,string> $params
     */
    public function bulkApply(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();

        $action = (string) $request->post('bulk_action', '');
        $ids = $this->selectedIds($request);
        if (!in_array($action, self::BULK_ACTIONS, true) || $ids === []) {
            return $this->directoryView($request, 'The bulk selection is no longer valid — start again.', 422);
        }

        $reason = $request->str('reason');
        $until = trim($request->str('until'));

        try {
            $result = $this->container->get(UserModerationService::class)
                ->bulkApply($admin, $action, $ids, $reason, $until !== '' ? $until : null);
        } catch (ValidationException $e) {
            // Shared-input failure (reason/until) aborted before any member
            // was written — re-render the confirmation with the typed input.
            return $this->bulkConfirmView($action, $ids, $e->errors, ['reason' => $reason, 'until' => $until], 422);
        }

        $verb = $action === 'suspend' ? 'Suspended' : 'Warned';
        $message = $verb . ' ' . $result['done'] . ' member' . ($result['done'] === 1 ? '' : 's') . '.';
        if ($result['skipped'] !== []) {
            $message .= ' Skipped: ' . implode('; ', $result['skipped']);
        }
        return $this->redirectWithFlash('/admin/users', $message);
    }

    /**
     * Read + normalize the directory filter GET params against allowlists so the
     * view can safely repopulate controls and build shareable URLs.
     *
     * @return array<string,string>
     */
    private function readFilters(Request $request): array
    {
        $role = $request->str('role');
        $status = $request->str('status');
        $lastSeen = $request->str('last_seen');
        $sort = $request->str('sort');
        $direction = strtolower($request->str('direction')) === 'asc' ? 'asc' : 'desc';
        $minPosts = trim($request->str('min_posts'));
        $maxPosts = trim($request->str('max_posts'));

        return [
            'q' => trim($request->str('q')),
            'role' => in_array($role, self::ROLES, true) ? $role : '',
            'status' => in_array($status, self::STATUSES, true) ? $status : '',
            'joined_from' => trim($request->str('joined_from')),
            'joined_to' => trim($request->str('joined_to')),
            'last_seen' => in_array($lastSeen, self::LAST_SEEN, true) ? $lastSeen : '',
            'min_posts' => ctype_digit($minPosts) ? $minPosts : '',
            'max_posts' => ctype_digit($maxPosts) ? $maxPosts : '',
            'sort' => in_array($sort, self::SORTS, true) ? $sort : 'created_at',
            'direction' => $direction,
        ];
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireAdmin();
        return $this->record((int) ($params['id'] ?? 0));
    }

    /**
     * One-shot audited PII disclosure (ADMIN §5.5: "PII access is gated and
     * logged" — the POST is the access event). The revealed values render on
     * this response only and are never stored client-side.
     *
     * @param array<string,string> $params
     */
    public function revealPii(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id);
        $pii = $this->container->get(UserModerationService::class)->revealPii($admin, $id);
        return $this->record($id, pii: $pii);
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
            return $this->record($id, $e, 422, 'badge_grant', ['slug' => $request->str('slug'), 'reason' => $reason]);
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
            return $this->record($id, $e, 422, 'badge_revoke', ['slug' => $request->str('slug'), 'reason' => $reason]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Badge revoked.');
    }

    /** @param array<string,string> $params */
    public function removeAvatar(Request $request, array $params): Response
    {
        $this->requireProfileMedia();
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->container->get(UserModerationService::class)->clearAvatar($admin, $id);
        return $this->redirectWithFlash('/admin/users/' . $id, 'Avatar removed.');
    }

    /** @param array<string,string> $params */
    public function removeSignature(Request $request, array $params): Response
    {
        $this->requireProfileMedia();
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->container->get(UserModerationService::class)->clearSignature($admin, $id);
        return $this->redirectWithFlash('/admin/users/' . $id, 'Signature removed.');
    }

    /** @param array<string,string> $params */
    public function warn(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        try {
            $this->container->get(UserModerationService::class)
                ->warn($admin, $id, $request->str('reason'), $request->int('board_id', 0) ?: null);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'warn', ['reason' => $request->str('reason')]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Warning recorded.');
    }

    /** @param array<string,string> $params */
    public function note(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        try {
            $this->container->get(UserModerationService::class)
                ->addNote($admin, $id, $request->str('body'));
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'note', ['body' => $request->str('body')]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Note added.');
    }

    /** @param array<string,string> $params */
    public function suspend(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        $until = trim($request->str('until'));
        try {
            $this->container->get(UserModerationService::class)
                ->suspend($admin, $id, $until !== '' ? $until : null, $request->str('reason'));
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'suspend', ['reason' => $request->str('reason'), 'until' => $until]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'User suspended.');
    }

    /** @param array<string,string> $params */
    public function ban(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $subject = $this->requireSubject($id); // 404 before any write
        // Typed-username confirmation, enforced server-side (parity with the
        // structure deletes): banning is the console's most consequential
        // one-form action and must not be a single accidental click.
        if (trim((string) $request->post('confirm_username', '')) !== (string) $subject['username']) {
            return $this->record($id, new ValidationException(
                ['confirm_username' => 'Type the member\'s username exactly to confirm the ban.'],
            ), 422, 'ban', ['reason' => $request->str('reason')]);
        }
        try {
            $this->container->get(UserModerationService::class)
                ->ban($admin, $id, $request->str('reason'));
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'ban', ['reason' => $request->str('reason')]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'User banned.');
    }

    /** @param array<string,string> $params */
    public function lift(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        try {
            $this->container->get(UserModerationService::class)->lift($admin, $id);
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'lift');
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Account restriction lifted.');
    }

    /**
     * In-app role change (ADMIN §5.2, TM-PE-07). UNGATED like the rest of this
     * controller — the route is flag-independent of `capabilities`. Unlike
     * suspend/ban, an admin may target themselves or another admin (that's the
     * point: demoting the last protected owner must be reachable and refused by
     * the service, not hidden by the controller).
     *
     * @param array<string,string> $params
     */
    public function changeRole(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        $this->requireSubject($id); // 404 before any write
        try {
            $this->container->get(UserModerationService::class)->changeRole(
                $admin,
                // Raw, not str(): reauth must verify the password exactly as
                // stored/typed. str() trims, which every other reauth site
                // avoids and which would reject a legitimate space-edged
                // password (review V8).
                (string) $request->post('current_password', ''),
                $id,
                $request->str('role'),
            );
        } catch (ValidationException $e) {
            return $this->record($id, $e, 422, 'change_role', ['role' => $request->str('role')]);
        }
        return $this->redirectWithFlash('/admin/users/' . $id, 'Role updated.');
    }

    /**
     * Render the directory (shared by GET and the bulk 422 re-renders).
     */
    private function directoryView(Request $request, ?string $bulkError = null, int $status = 200): Response
    {
        $model = $this->container->get(UserModerationService::class)->directoryModel(
            $this->readFilters($request),
            max(0, $request->int('page', 0)),
            self::PER_PAGE,
        );

        return $this->view('admin/users', $model + ['bulk_error' => $bulkError], $status);
    }

    /**
     * Render the bulk confirmation page.
     *
     * @param list<int> $ids
     * @param array<string,string> $errors
     * @param array<string,string> $old
     */
    private function bulkConfirmView(string $action, array $ids, array $errors = [], array $old = [], int $status = 200): Response
    {
        $subjects = $this->container->get(UserModerationService::class)->bulkPlan($action, $ids);

        return $this->view('admin/users_bulk_confirm', [
            'action' => $action,
            'subjects' => $subjects,
            'errors' => $errors,
            'old' => $old,
        ], $status);
    }

    /** @return list<int> up to BULK_MAX unique positive ids */
    private function selectedIds(Request $request): array
    {
        $raw = $request->post('selected', []);
        if (!is_array($raw)) {
            return [];
        }
        $ids = [];
        foreach ($raw as $value) {
            $id = (int) $value;
            if ($id > 0 && !in_array($id, $ids, true)) {
                $ids[] = $id;
            }
            if (count($ids) >= self::BULK_MAX) {
                break;
            }
        }
        return $ids;
    }

    /**
     * Render the per-user admin record (ADMIN §5.2).
     *
     * @param array<string,string> $old typed input to re-render (anti-draft-loss);
     *        preferred over the exception payload since service guards such as
     *        requireReason() throw without capturing the submitted values.
     * @param array{email:string,session_ips:array<int,string>,post_ips:array<int,string>}|null $pii
     */
    private function record(
        int $id,
        ?ValidationException $error = null,
        int $status = 200,
        ?string $errorContext = null,
        array $old = [],
        ?array $pii = null,
    ): Response {
        $admin = $this->requireAdmin();
        $subject = $this->requireSubject($id);
        $badges = $this->container->get(BadgeRepository::class);
        $titles = $this->container->get(TitleService::class);
        $moderation = $this->container->get(UserModerationService::class);
        $reputation = (int) ($subject['reputation'] ?? 0);

        // Mirror UserModerationService::requireGovernable(): admins cannot
        // suspend/ban themselves or another admin. The service still enforces
        // this; the flag only decides whether to render the controls.
        $canGovern = (int) $subject['id'] !== $admin->id()
            && (($subject['role'] ?? 'user') !== 'admin');

        return $this->view('admin/user_record', [
            'subject' => $subject,
            'stored_title' => $subject['title'] ?? null,
            'effective_title' => $titles->resolve($subject['title'] ?? null, $reputation),
            'derived_title' => $titles->derive($reputation),
            'held_manual' => $badges->manualHeldByUser($id),
            'catalogue' => $badges->manualCatalogue(),
            'history' => $moderation->history($id),
            'can_govern' => $canGovern,
            'is_self' => (int) $subject['id'] === $admin->id(),
            'error_context' => $errorContext,
            'errors' => $error?->errors ?? [],
            'old' => $old !== [] ? $old : ($error?->old ?? []),
            'pii' => $pii,
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

    private function requireProfileMedia(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('profile_media')) {
            throw new NotFoundException('User not found.');
        }
    }
}
