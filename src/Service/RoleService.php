<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\ForbiddenException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\CapabilityRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Security\CapabilityCatalog;
use App\Security\CapabilityResolver;
use App\Security\EnforcedCapabilities;
use App\Security\ReauthGate;
use App\Security\WriteGate;

/**
 * Role-definition rules for custom additive capability bundles.
 */
final class RoleService
{
    public function __construct(
        private Database $db,
        private RoleRepository $roles,
        private RoleCapabilityRepository $roleCapabilities,
        private CapabilityRepository $capabilities,
        private RoleAssignmentRepository $assignments,
        private RoleAssignmentHistoryRepository $history,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ?CapabilityResolver $resolver = null,
    ) {
    }

    /** @param list<string> $capabilityKeys */
    public function create(User $admin, string $currentPassword, string $name, ?string $description, array $capabilityKeys): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        [$name, $description, $keys, $ids] = $this->validateDefinition($name, $description, $capabilityKeys);
        $roleKey = $this->newRoleKey($name);

        $roleId = $this->insertGuarded($name, $description, $keys, function () use ($admin, $name, $description, $keys, $ids, $roleKey): int {
            $roleId = $this->roles->create([
                'role_key' => $roleKey,
                'name' => $name,
                'description' => $description,
                'created_by' => $admin->id(),
            ]);
            $this->roleCapabilities->replaceForRole($roleId, $ids);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $roleId,
                'before' => null,
                'after' => ['name' => $name, 'capabilities' => $keys],
                'reason' => 'create',
            ]);

            return $roleId;
        });
        $this->resolver?->invalidate();
        return $roleId;
    }

    /** @param list<string> $capabilityKeys */
    public function update(User $admin, string $currentPassword, int $roleId, string $name, ?string $description, array $capabilityKeys): void
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $role = $this->requireCustomRole($roleId);
        [$name, $description, $keys, $ids] = $this->validateDefinition($name, $description, $capabilityKeys, $roleId);

        $this->db->transaction(function () use ($admin, $role, $roleId, $name, $description, $keys, $ids): void {
            $beforeKeys = $this->roleCapabilities->keysForRole($roleId);
            $this->roles->updateDefinition($roleId, $name, $description);
            $this->roleCapabilities->replaceForRole($roleId, $ids);
            $this->roles->bumpVersion($roleId);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $roleId,
                'before' => ['name' => (string) $role['name'], 'capabilities' => $beforeKeys],
                'after' => ['name' => $name, 'capabilities' => $keys],
                'reason' => 'update',
            ]);
        });
        $this->resolver?->invalidate();
    }

    public function clone(User $admin, string $currentPassword, int $sourceRoleId, string $name): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);
        $source = $this->roles->find($sourceRoleId);
        if ($source === null) {
            throw new ValidationException(['role' => 'Source role not found.']);
        }

        $sourceKeys = $this->roleCapabilities->keysForRole($sourceRoleId);
        if ($sourceKeys === []) {
            throw new ValidationException(['role' => 'The source role has no capabilities to clone.']);
        }

        // Clone copies from an existing role, not a human hand-pick, so filter
        // the source's keys down to the enforceable set rather than rejecting
        // the whole clone (create/update correctly REJECT a human's non-enforced
        // pick). Every system anchor's cumulative set always carries baseline
        // keys outside the enforced set (e.g. core.board.read), so without this
        // the documented "clone one to adapt it" path would 422 for every
        // system role. See App\Security\EnforcedCapabilities.
        $enforceable = array_values(array_filter(
            $sourceKeys,
            static fn (string $k): bool => EnforcedCapabilities::has($k),
        ));
        if ($enforceable === []) {
            throw new ValidationException(['role' => 'The source role has no enforceable capabilities to clone yet.']);
        }

        [$name, , $keys, $ids] = $this->validateDefinition($name, null, $enforceable);
        $roleKey = $this->newRoleKey($name);

        $roleId = $this->insertGuarded($name, null, $keys, function () use ($admin, $source, $name, $keys, $ids, $roleKey): int {
            $roleId = $this->roles->create([
                'role_key' => $roleKey,
                'name' => $name,
                'description' => 'Clone of ' . (string) $source['name'],
                'created_by' => $admin->id(),
            ]);
            $this->roleCapabilities->replaceForRole($roleId, $ids);
            $this->history->log([
                'event' => 'role_edit',
                'actor_id' => $admin->id(),
                'role_id' => $roleId,
                'before' => null,
                'after' => ['name' => $name, 'capabilities' => $keys],
                'reason' => 'clone of ' . (string) $source['role_key'],
            ]);

            return $roleId;
        });
        $this->resolver?->invalidate();
        return $roleId;
    }

    /** @return list<array{role:array<string,mixed>,capability_count:int,impact:int}> */
    public function listWithMeta(): array
    {
        $roles = $this->roles->all();
        $roleIds = array_map(static fn (array $role): int => (int) $role['id'], $roles);
        $impacts = $this->assignments->countActiveForRoles($roleIds);
        $out = [];

        foreach ($roles as $role) {
            $roleId = (int) $role['id'];
            $out[] = [
                'role' => $role,
                'capability_count' => count($this->roleCapabilities->keysForRole($roleId)),
                'impact' => $impacts[$roleId] ?? 0,
            ];
        }

        return $out;
    }

    /** @return array<string,mixed> */
    public function requireCustomRole(int $roleId): array
    {
        $role = $this->roles->find($roleId);
        if ($role === null) {
            throw new ValidationException(['role' => 'Role not found.']);
        }
        if ((string) $role['kind'] === 'system') {
            throw new ForbiddenException('System roles are protected compatibility anchors and cannot be edited. Clone one instead.');
        }

        return $role;
    }

    /**
     * Run an insert transaction, translating a `uq_role_key` violation (a
     * concurrent create/clone that won the race past validateDefinition's
     * read-check) into the same 422 the pre-check produces. The only unique key
     * reachable inside these transactions is roles.uq_role_key: role_capabilities
     * uses INSERT IGNORE and every FK id was resolved from live rows.
     *
     * @param list<string> $keys
     * @param callable():int $tx
     */
    private function insertGuarded(string $name, ?string $description, array $keys, callable $tx): int
    {
        try {
            return $this->db->transaction($tx);
        } catch (\PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                throw new ValidationException(
                    ['name' => 'A role with this name already exists.'],
                    ['name' => $name, 'description' => (string) $description, 'capabilities' => $keys],
                );
            }
            throw $e;
        }
    }

    /**
     * @param list<string> $capabilityKeys
     * @return array{0:string,1:?string,2:list<string>,3:list<int>}
     */
    private function validateDefinition(string $name, ?string $description, array $capabilityKeys, ?int $exceptRoleId = null): array
    {
        $errors = [];
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 190) {
            $errors['name'] = 'A role name between 1 and 190 characters is required.';
        }

        $description = $description === null ? null : trim($description);
        if ($description === '') {
            $description = null;
        }
        if ($description !== null && mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 255 characters or fewer.';
        }

        $keys = array_values(array_unique(array_map('strval', $capabilityKeys)));
        if ($keys === []) {
            $errors['capabilities'] = 'Pick at least one capability.';
        }

        $catalogue = CapabilityCatalog::all();
        foreach ($keys as $key) {
            $meta = $catalogue[$key] ?? null;
            if ($meta === null) {
                $errors['capabilities'] = "Unknown capability: $key.";
                break;
            }
            if ($meta['protected'] || !$meta['delegable']) {
                $errors['capabilities'] = "$key is protected/non-delegable and can never be placed in a role.";
                break;
            }
            if (!EnforcedCapabilities::has($key)) {
                $errors['capabilities'] = "'" . $key . "' is not yet enforceable; it can be granted once its routes cut over to the resolver.";
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, [
                'name' => $name,
                'description' => (string) $description,
                'capabilities' => $keys,
            ]);
        }

        $existing = $this->roles->findByKey($this->slugKey($name));
        if ($existing !== null && (int) $existing['id'] !== $exceptRoleId) {
            throw new ValidationException(
                ['name' => 'A role with this name already exists.'],
                ['name' => $name, 'description' => (string) $description, 'capabilities' => $keys],
            );
        }

        $ids = $this->capabilities->idsByKeys($keys);
        if (count($ids) !== count($keys)) {
            throw new ValidationException(['capabilities' => 'A selected capability is missing from the seeded catalogue.']);
        }

        return [$name, $description, $keys, array_values($ids)];
    }

    private function slugKey(string $name): string
    {
        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $name), '_'));
        return 'custom.' . ($slug === '' ? 'role' : $slug);
    }

    private function newRoleKey(string $name): string
    {
        return $this->slugKey($name);
    }
}
