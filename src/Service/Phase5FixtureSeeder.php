<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

/**
 * Foundation F9 — seeds a deterministic, representative Phase 5 corpus (roles,
 * scoped/temporal assignments, board moderators, a protected owner) that
 * BaselineMetricsService measures and that Increment 1 (P5-08 parity) and
 * Increment 10 (P5-16 perf) reuse. All rows are tagged `p5fix_` and the seed is
 * idempotent via a `settings` marker. Refuses production. Runtime tooling — no
 * migration (the tables exist from 0001..0050); nothing here flips a flag.
 */
final class Phase5FixtureSeeder
{
    public const FIXTURE_VERSION = 1;
    private const MARKER = 'phase5_fixture_version';

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private string $appEnv,
    ) {
    }

    public function isSeeded(): bool
    {
        return (int) $this->settings->get(self::MARKER, 0) >= self::FIXTURE_VERSION;
    }

    /**
     * @return array{users:int,boards:int,moderators:int,assignments:int,owners:int,skipped:bool}
     */
    public function seed(): array
    {
        if ($this->appEnv === 'production') {
            throw new \RuntimeException('Phase5FixtureSeeder refuses to run with app.env=production');
        }
        if ($this->isSeeded()) {
            return ['users' => 0, 'boards' => 0, 'moderators' => 0, 'assignments' => 0, 'owners' => 0, 'skipped' => true];
        }

        return $this->db->transaction(function (): array {
            $users = new UserRepository($this->db);
            $boards = new BoardRepository($this->db);
            $cats = new CategoryRepository($this->db);
            $mods = new BoardModeratorRepository($this->db);
            $owners = new ProtectedOwnerRepository($this->db);
            $hash = (new PasswordHasher())->hash('password123');

            $mk = static function (string $name, string $role, string $status) use ($users, $hash): int {
                return $users->create([
                    'username' => $name,
                    'email' => $name . '@p5fix.test',
                    'password_hash' => $hash,
                    'display_name' => null,
                    'role' => $role,
                    'status' => $status,
                ]);
            };

            $admin = $mk('p5fix_admin', 'admin', 'active');
            $mod1 = $mk('p5fix_mod1', 'moderator', 'active');
            $mk('p5fix_mod2', 'moderator', 'active');
            $mk('p5fix_user1', 'user', 'active');
            $mk('p5fix_user2', 'user', 'active');
            $mk('p5fix_user3', 'user', 'active');
            $mk('p5fix_user4', 'user', 'active');
            $mk('p5fix_susp', 'user', 'suspended');

            $catId = $cats->create('P5 Fixtures', 900);
            $bPublic = $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_public', 'name' => 'P5 Public',
                'description' => null, 'visibility' => 'public', 'post_min_role' => 'user', 'allow_anonymous' => 0,
            ]);
            $bMod = $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_mod', 'name' => 'P5 Mod-floor',
                'description' => null, 'visibility' => 'public', 'post_min_role' => 'moderator', 'allow_anonymous' => 0,
            ]);
            $boards->create([
                'category_id' => $catId, 'slug' => 'p5fix_private', 'name' => 'P5 Private',
                'description' => null, 'visibility' => 'private', 'post_min_role' => 'user', 'allow_anonymous' => 0,
            ]);

            $mods->assign($bMod, $mod1);
            $owners->designate($admin, null);

            $roleId = fn (string $key): int => (int) $this->db->fetchValue('SELECT id FROM roles WHERE role_key = ?', [$key]);
            $adminRole = $roleId('system.admin');
            $modRole = $roleId('system.moderator');

            // Four temporal cases: active-site-admin, active-board-mod, expired, future.
            $this->assign('user', $admin, $adminRole, 'site', null, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", 'NULL');
            $this->assign('user', $mod1, $modRole, 'board', $bMod, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 7 DAY)", "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 30 DAY)");
            $this->assign('user', $mod1, $modRole, 'board', $bPublic, "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 30 DAY)", "DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY)");
            $this->assign('user', $mod1, $modRole, 'board', $bPublic, "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY)", "DATE_ADD(UTC_TIMESTAMP(), INTERVAL 37 DAY)");

            $this->settings->set(self::MARKER, self::FIXTURE_VERSION);

            return ['users' => 8, 'boards' => 3, 'moderators' => 1, 'assignments' => 4, 'owners' => 1, 'skipped' => false];
        });
    }

    /**
     * Insert one role_assignments row. `$startsExpr`/`$endsExpr` are trusted SQL
     * literals (this class's own constants, never user input) so the temporal
     * spread is exact; all identifiers are bound parameters.
     */
    private function assign(string $subjectType, int $subjectId, int $roleId, string $scopeType, ?int $scopeId, string $startsExpr, string $endsExpr): void
    {
        $this->db->run(
            "INSERT INTO role_assignments
                (subject_type, subject_id, role_id, scope_type, scope_id, grantor_id, reason, starts_at, ends_at, assignment_version, created_at)
             VALUES (?, ?, ?, ?, ?, NULL, 'p5fix', $startsExpr, $endsExpr, 1, UTC_TIMESTAMP())",
            [$subjectType, $subjectId, $roleId, $scopeType, $scopeId],
        );
    }
}
