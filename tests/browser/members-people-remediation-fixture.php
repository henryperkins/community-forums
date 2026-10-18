<?php

declare(strict_types=1);

use App\Core\{Config, Database, Env};
use App\Repository\{BoardRepository, CategoryRepository, ProtectedOwnerRepository, RoleCapabilityRepository, RoleRepository, SettingRepository, UserPreferenceRepository, UserRepository};
use App\Security\PasswordHasher;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if ($config->get('app.env') !== 'test'
    || preg_match('/^retroboards_e2e_members_people_[a-z0-9_]+$/', (string) $config->get('db.database')) !== 1
    || !in_array($config->get('db.host'), ['localhost', '127.0.0.1'], true)
    || $config->get('db.socket') !== '' || $config->get('mail.driver') !== 'array') {
    throw new RuntimeException('Members/People fixtures require a private local test database and array mail.');
}
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$settings = new SettingRepository($db);
$command = $argv[1] ?? 'seed';
if ($command === 'seed') {
    $db->transaction(function () use ($db, $users, $settings): void {
        $settings->set('site_name', 'Members and People regression');
        $settings->set('installed_at', '2026-10-18 00:00:00');
        $settings->set('features', []);
        PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
        $hash = (new PasswordHasher())->hash('password123');
        foreach ([
            ['mp-admin', 'Bulk review admin', 'admin'],
            ['mp-target', 'Bulk review target', 'user'],
            ['mp-sameday', 'Date review target', 'user'],
            ['mp-nextday', 'Next day target', 'user'],
        ] as [$username, $display, $role]) {
            $row = $users->findByUsername($username);
            $id = $row === null ? $users->create([
                'username' => $username, 'display_name' => $display, 'email' => $username . '@retro.test',
                'password_hash' => $hash, 'role' => $role,
            ]) : (int) $row['id'];
            $db->run("UPDATE users SET status = 'active', suspended_until = NULL, onboarded_at = UTC_TIMESTAMP(), email_verified_at = UTC_TIMESTAMP() WHERE id = ?", [$id]);
            (new UserPreferenceRepository($db))->merge($id, ['theme' => 'light']);
        }
        $admin = (int) $users->findByUsername('mp-admin')['id'];
        (new ProtectedOwnerRepository($db))->designate($admin);
        $db->run('UPDATE users SET created_at = ? WHERE username = ?', ['2025-04-15 12:34:56', 'mp-sameday']);
        $db->run('UPDATE users SET created_at = ? WHERE username = ?', ['2025-04-16 00:00:00', 'mp-nextday']);
        $categories = new CategoryRepository($db);
        foreach (['Review category One', 'Review category Two'] as $name) {
            if ($db->fetchValue('SELECT id FROM categories WHERE name = ?', [$name]) === false) {
                $categories->create($name);
            }
        }
        $category = (int) $db->fetchValue('SELECT id FROM categories WHERE name = ?', ['Review category One']);
        $boards = new BoardRepository($db);
        foreach (['one', 'two'] as $suffix) {
            if ($boards->findBySlug('mp-board-' . $suffix) === null) {
                $boards->create(['category_id' => $category, 'slug' => 'mp-board-' . $suffix, 'name' => 'Review board ' . ucfirst($suffix), 'visibility' => 'public']);
            }
        }
        $roles = new RoleRepository($db);
        $role = $roles->findByKey('custom.mp_review');
        $roleId = $role === null ? $roles->create([
            'role_key' => 'custom.mp_review', 'name' => 'Members People review role',
            'description' => 'One scoped moderation capability for browser regression.', 'created_by' => $admin,
        ]) : (int) $role['id'];
        $cap = (int) $db->fetchValue('SELECT id FROM capabilities WHERE capability_key = ?', ['core.thread.lock']);
        (new RoleCapabilityRepository($db))->replaceForRole($roleId, [$cap]);
    });
} elseif ($command === 'reset-actions') {
    $target = (int) $users->findByUsername('mp-target')['id'];
    $db->run('DELETE FROM warnings WHERE user_id = ?', [$target]);
    $db->run('DELETE FROM role_assignments WHERE subject_type = ? AND subject_id = ?', ['user', $target]);
} elseif ($command === 'dark' || $command === 'light') {
    (new UserPreferenceRepository($db))->merge((int) $users->findByUsername('mp-admin')['id'], ['theme' => $command]);
} elseif ($command === 'flag-dark') {
    $settings->set('features', ['capabilities' => false]);
} elseif ($command === 'flag-default') {
    $settings->set('features', []);
} elseif ($command !== 'inspect') {
    throw new InvalidArgumentException('Unknown fixture command.');
}
echo json_encode([
    'admin_id' => (int) $users->findByUsername('mp-admin')['id'],
    'target_id' => (int) $users->findByUsername('mp-target')['id'],
    'same_day_id' => (int) $users->findByUsername('mp-sameday')['id'],
    'role_id' => (int) $db->fetchValue('SELECT id FROM roles WHERE role_key = ?', ['custom.mp_review']),
    'boards' => $db->fetchAll("SELECT id, name FROM boards WHERE slug LIKE 'mp-board-%' ORDER BY id"),
    'categories' => $db->fetchAll("SELECT id, name FROM categories WHERE name LIKE 'Review category %' ORDER BY id"),
    'target_warnings' => (int) $db->fetchValue('SELECT COUNT(*) FROM warnings WHERE user_id = ?', [(int) $users->findByUsername('mp-target')['id']]),
    'deliveries' => (int) $db->fetchValue('SELECT COUNT(*) FROM email_deliveries'),
    'mode' => (string) $config->get('capabilities.mode', 'shadow'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL;
