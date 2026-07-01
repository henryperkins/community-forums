<?php

declare(strict_types=1);

use App\Security\CapabilityCatalog;

/**
 * 0066 · Phase 5 Foundation F3+F5 seed. Populates the empty `0050` capability
 * catalogue + role→capability map from the code-owned CapabilityCatalog, and
 * backfills `protected_owners` from existing active admins.
 *
 * SEED-ONLY (no DDL — the tables exist from 0050). Additive/forward-only and
 * idempotent (INSERT IGNORE, pattern: 0040_seed_badges). Deploy-dark: nothing
 * resolves against these rows until the `capabilities` flag + the resolver land.
 * On a fresh install `users` is empty at migrate time, so the owner backfill is
 * a no-op there; RepairService::repairProtectedOwners + setup designation cover
 * that case at runtime.
 */
return new class {
    public function up(\PDO $pdo): void
    {
        // ── Capabilities catalogue (F3) ──────────────────────────────────────
        $cap = $pdo->prepare(
            'INSERT IGNORE INTO capabilities
                (capability_key, namespace, scope_type, risk_class, is_delegable, is_protected, source, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach (CapabilityCatalog::all() as $key => $meta) {
            $namespace = explode('.', $key)[0]; // 'core'
            $cap->execute([
                $key,
                $namespace,
                $meta['scope'],
                $meta['risk'],
                $meta['delegable'] ? 1 : 0,
                $meta['protected'] ? 1 : 0,
                'core',
                $meta['description'],
            ]);
        }

        // ── Role → capability map (F3) ───────────────────────────────────────
        // Set-based per (role_key, capability_key): resolve both ids at insert.
        $map = $pdo->prepare(
            'INSERT IGNORE INTO role_capabilities (role_id, capability_id)
             SELECT r.id, c.id FROM roles r, capabilities c
             WHERE r.role_key = ? AND c.capability_key = ?',
        );
        foreach (CapabilityCatalog::roleCapabilities() as $roleKey => $capKeys) {
            foreach ($capKeys as $capKey) {
                $map->execute([$roleKey, $capKey]);
            }
        }

        // ── Protected owners backfill (F5) ───────────────────────────────────
        // Existing active admins become protected owners so decision #27's guard
        // is enforceable. No-op on a fresh (empty-users) install.
        $pdo->exec(
            "INSERT IGNORE INTO protected_owners (user_id, is_active, designated_by, designated_at, created_at)
             SELECT id, 1, NULL, UTC_TIMESTAMP(), UTC_TIMESTAMP()
             FROM users WHERE role = 'admin' AND status = 'active'",
        );
    }

    public function down(\PDO $pdo): void
    {
        // Seed-only rollback: remove the core catalogue (role_capabilities cascade
        // via FK) and the backfilled owners. 0050's down() drops the tables wholesale.
        $pdo->exec("DELETE FROM role_capabilities WHERE capability_id IN (SELECT id FROM capabilities WHERE source = 'core')");
        $pdo->exec("DELETE FROM capabilities WHERE source = 'core'");
        $pdo->exec('DELETE FROM protected_owners WHERE designated_by IS NULL');
    }
};
