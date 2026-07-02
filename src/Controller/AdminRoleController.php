<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\RoleCapabilityRepository;
use App\Security\CapabilityCatalog;
use App\Service\PermissionSimulatorService;
use App\Service\RoleService;

/**
 * Deploy-dark role definition editor for the Phase 5 capability model.
 */
final class AdminRoleController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('capabilities')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
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

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function rolesView(array $errors = [], array $old = [], int $status = 200): Response
    {
        return $this->noindex($this->view('admin/roles', [
            'rows' => $this->container->get(RoleService::class)->listWithMeta(),
            'catalogue' => $this->delegableCatalogue(),
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
