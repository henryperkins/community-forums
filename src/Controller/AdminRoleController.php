<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Security\AuthorityGate;
use App\Security\CapabilityCatalog;
use App\Service\PermissionSimulatorService;
use App\Service\RoleAssignmentService;
use App\Service\RoleService;

/**
 * Role definition editor for the Phase 5 capability model (flag-gated by capabilities).
 */
final class AdminRoleController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('capabilities')) {
            throw new NotFoundException();
        }
    }

    /** @return array<string,array{scope:string,risk:string,delegable:bool,protected:bool,description:string,consent:?string}> */
    private function delegableCatalogue(): array
    {
        return array_filter(
            CapabilityCatalog::all(),
            static fn (array $meta): bool => $meta['delegable'] && !$meta['protected'],
        );
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->rolesView();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $service = $this->container->get(RoleService::class);

        try {
            $service->create(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('name'),
                $request->str('description') !== '' ? $request->str('description') : null,
                $this->inputCapabilities($request),
            );
            return $this->noindex($this->redirectWithFlash('/admin/roles', 'Role created.'));
        } catch (ValidationException $e) {
            return $this->rolesView($e->errors, $this->oldDefinition($request, $e), 422);
        }
    }

    /** @param array<string,string> $params */
    public function simulator(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        $capability = (string) $request->query('capability', '');
        $result = null;
        if ($capability !== '') {
            $boardId = (int) $request->query('board_id', 0);
            $at = (string) $request->query('at', '');
            $result = $this->container->get(PermissionSimulatorService::class)->simulate(
                $admin,
                (string) $request->query('actor', 'guest'),
                $capability,
                $boardId > 0 ? $boardId : null,
                $at === '' ? null : $at,
            );
        }

        return $this->noindex($this->view('admin/role_simulator', [
            'catalogue' => CapabilityCatalog::all(),
            'result' => $result,
            'q' => [
                'actor' => (string) $request->query('actor', ''),
                'capability' => $capability,
                'board_id' => (string) $request->query('board_id', ''),
                'at' => (string) $request->query('at', ''),
            ],
        ]));
    }

    /** @param array<string,string> $params */
    public function edit(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->roleEditView((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $roleId = (int) ($params['id'] ?? 0);
        $service = $this->container->get(RoleService::class);

        try {
            $service->update(
                $admin,
                (string) $request->post('current_password', ''),
                $roleId,
                $request->str('name'),
                $request->str('description') !== '' ? $request->str('description') : null,
                $this->inputCapabilities($request),
            );
            return $this->noindex($this->redirectWithFlash('/admin/roles', 'Role updated.'));
        } catch (ValidationException $e) {
            return $this->roleEditView($roleId, $e->errors, $this->oldDefinition($request, $e), 422);
        }
    }

    /** @param array<string,string> $params */
    public function clone(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $roleId = (int) ($params['id'] ?? 0);

        try {
            $this->container->get(RoleService::class)->clone(
                $admin,
                (string) $request->post('current_password', ''),
                $roleId,
                $request->str('name'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/roles', 'Role cloned as an editable custom role.'));
        } catch (ValidationException $e) {
            $this->flash()->add('Clone failed: ' . implode(' ', array_map('strval', $e->errors)));
            return $this->noindex($this->redirect('/admin/roles/' . $roleId));
        }
    }

    /**
     * P5-09 no-JS grant. Custom roles only, scoped + time-boxed, reauth'd
     * (re-broadening authority) — RoleAssignmentService enforces the
     * grantor-ceiling anti-privilege-escalation check.
     *
     * @param array<string,string> $params
     */
    public function assign(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $roleId = (int) ($params['id'] ?? 0);

        try {
            $this->container->get(RoleAssignmentService::class)->grant(
                $admin,
                (string) $request->post('current_password', ''),
                $roleId,
                $request->str('username'),
                $request->str('scope_type'),
                $request->str('scope_id') !== '' ? (int) $request->str('scope_id') : null,
                $request->str('starts_at') !== '' ? $request->str('starts_at') : null,
                $request->str('ends_at') !== '' ? $request->str('ends_at') : null,
                $request->str('reason') !== '' ? $request->str('reason') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Role assigned.'));
        } catch (ValidationException $e) {
            return $this->roleEditView($roleId, $e->errors, [
                'assignment' => [
                    'username' => $request->str('username'), 'scope_type' => $request->str('scope_type'),
                    'scope_id' => $request->str('scope_id'), 'starts_at' => $request->str('starts_at'),
                    'ends_at' => $request->str('ends_at'), 'reason' => $request->str('reason'),
                ],
            ], 422);
        }
    }

    /**
     * Revoke is narrowing-only (emergency speed) — no reauth, and no typed
     * state worth preserving on failure, so a flash+redirect is enough.
     *
     * @param array<string,string> $params
     */
    public function revokeAssignment(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->container->get(RoleAssignmentRepository::class)->find($id);
        $roleId = $row === null ? 0 : (int) $row['role_id'];

        try {
            $this->container->get(RoleAssignmentService::class)->revoke($admin, $id, $request->str('reason') !== '' ? $request->str('reason') : null);
            return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Assignment revoked.'));
        } catch (ValidationException $e) {
            $this->flash()->add('Revoke failed: ' . implode(' ', array_map('strval', $e->errors)));
            return $this->noindex($this->redirect('/admin/roles' . ($roleId > 0 ? '/' . $roleId : '')));
        }
    }

    /**
     * Renew is re-broadening authority (a fresh expiry), so unlike revoke it
     * reauths and — on failure — re-renders 422 preserving the attempted
     * ends_at rather than flashing it away. The lookup uses the plain find()
     * (FOR UPDATE stays inside the service); a missing row is a genuine 404,
     * not a flag-gate 404.
     *
     * @param array<string,string> $params
     */
    public function renewAssignment(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        $row = $this->container->get(RoleAssignmentRepository::class)->find($id);
        if ($row === null) {
            throw new NotFoundException('Assignment not found.');
        }
        $roleId = (int) $row['role_id'];

        try {
            $this->container->get(RoleAssignmentService::class)->renew(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $request->str('ends_at'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/roles/' . $roleId, 'Assignment renewed.'));
        } catch (ValidationException $e) {
            return $this->roleEditView($roleId, $e->errors, [
                'renew_assignment_id' => $id,
                'renew' => ['ends_at' => $request->str('ends_at')],
            ], 422);
        }
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function rolesView(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->noindex($this->view('admin/roles', [
            'rows' => $this->container->get(RoleService::class)->listWithMeta(),
            'catalogue' => $this->delegableCatalogue(),
            'mode' => $this->container->get(AuthorityGate::class)->mode(),
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function roleEditView(int $roleId, array $errors = [], array $old = [], int $status = 200): Response
    {
        $row = $this->roleRow($roleId);
        if ($row === null) {
            throw new NotFoundException('Role not found.');
        }

        return $this->noindex($this->view('admin/role_edit', [
            'row' => $row,
            'current_keys' => $this->container->get(RoleCapabilityRepository::class)->keysForRole($roleId),
            'catalogue' => $this->delegableCatalogue(),
            'assignments' => $this->container->get(RoleAssignmentService::class)->listForRole($roleId),
            'boards' => $this->container->get(BoardRepository::class)->allOrdered(),
            'categories' => $this->container->get(CategoryRepository::class)->all(),
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }

    /** @return array{role:array<string,mixed>,capability_count:int,impact:int}|null */
    private function roleRow(int $roleId): ?array
    {
        foreach ($this->container->get(RoleService::class)->listWithMeta() as $row) {
            if ((int) $row['role']['id'] === $roleId) {
                return $row;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function inputCapabilities(Request $request): array
    {
        $capabilities = $request->post('capabilities', []);
        if (!is_array($capabilities)) {
            return [];
        }

        return array_values(array_map('strval', $capabilities));
    }

    /** @return array<string,mixed> */
    private function oldDefinition(Request $request, ValidationException $e): array
    {
        return $e->old + [
            'name' => $request->str('name'),
            'description' => $request->str('description'),
            'capabilities' => $this->inputCapabilities($request),
        ];
    }
}
