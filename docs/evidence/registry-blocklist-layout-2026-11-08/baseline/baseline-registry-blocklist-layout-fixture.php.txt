<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_e2e_blocklist_layout(?:_[a-z0-9]+)*$/D', (string) getenv('DB_DATABASE'))) {
    throw new RuntimeException('Select a disposable blocklist-layout database in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$users = new UserRepository($db);
$blocks = new LocalPackageBlockRepository($db);
if (($argv[1] ?? 'state') === 'reset') {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $admin = $users->findByUsername('blocklist-layout-admin');
    $id = $admin === null ? $users->create([
        'username' => 'blocklist-layout-admin', 'email' => 'blocklist-layout-admin@retro.test',
        'password_hash' => (new PasswordHasher())->hash('password123'), 'role' => 'admin', 'status' => 'active',
    ]) : (int) $admin['id'];
    $db->run('UPDATE users SET onboarded_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    $db->run('DELETE FROM sessions WHERE user_id = ?', [$id]);
    (new SettingRepository($db))->set('features', ['package_registry' => true]);
    $db->run('DELETE FROM local_package_blocks');
    $blocks->add(null, 'acme/legacy-widget', 'Legacy version awaiting operator review.', $id);
    $blocks->add(null, 'acme/' . str_repeat('long-package-', 13) . 'widget', 'Review: ' . str_repeat('unbroken', 16), $id);
    $blocks->add(str_repeat('b', 64), null, 'Digest-only block <kept escaped> & retained.', $id);
}
$otherState = [];
foreach (['package_registries', 'registry_trust_keys', 'package_advisories'] as $table) {
    $otherState[$table] = $db->fetchAll('SELECT * FROM ' . $table . ' ORDER BY id');
}
echo json_encode([
    'blocks' => $blocks->all(),
    'audit' => $db->fetchAll("SELECT * FROM moderation_log WHERE action = 'package_unblock' ORDER BY id"),
    'other_state_hash' => hash('sha256', serialize($otherState)),
], JSON_THROW_ON_ERROR), "\n";
