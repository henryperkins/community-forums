<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_e2e_registry_forms(?:_[a-z0-9]+)*$/D', (string) getenv('DB_DATABASE'))) {
    throw new RuntimeException('Select a disposable registry-forms database in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$users = new UserRepository($db);
$registries = new PackageRegistryRepository($db);
$keys = new RegistryTrustKeyRepository($db);
if (($argv[1] ?? 'state') === 'reset') {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $admin = $users->findByUsername('registry-forms-admin');
    $id = $admin === null ? $users->create([
        'username' => 'registry-forms-admin', 'email' => 'registry-forms-admin@retro.test',
        'password_hash' => (new PasswordHasher())->hash('password123'), 'role' => 'admin', 'status' => 'active',
    ]) : (int) $admin['id'];
    $db->run('UPDATE users SET onboarded_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
    $db->run('DELETE FROM sessions WHERE user_id = ?', [$id]);
    (new SettingRepository($db))->set('features', ['package_registry' => true]);
    foreach (['a', 'b'] as $name) {
        $registry = $registries->findBySourceId('forms-' . $name);
        $rid = $registry === null ? $registries->create('forms-' . $name, 'Forms ' . strtoupper($name), 'https://' . $name . '.example.test') : (int) $registry['id'];
        foreach (['root-', 'other-'] as $prefix) {
            if ($keys->findKey($rid, $prefix . $name) === null) {
                $keys->pin($rid, $prefix . $name, str_repeat($name, 32), null, null);
            }
        }
    }
}
$a = $registries->findBySourceId('forms-a');
$b = $registries->findBySourceId('forms-b');
if ($a === null || $b === null) { throw new RuntimeException('Reset the registry-forms fixture first.'); }
$snapshot = [];
foreach (['package_registries', 'registry_trust_keys', 'package_advisories', 'local_package_blocks', 'moderation_log'] as $table) {
    $snapshot[$table] = $db->fetchAll('SELECT * FROM ' . $table . ' ORDER BY id');
}
echo json_encode([
    'a' => (int) $a['id'], 'b' => (int) $b['id'],
    'key_a' => (int) $keys->findKey((int) $a['id'], 'root-a')['id'],
    'other_key_a' => (int) $keys->findKey((int) $a['id'], 'other-a')['id'],
    'key_b' => (int) $keys->findKey((int) $b['id'], 'root-b')['id'],
    'state_hash' => hash('sha256', serialize($snapshot)),
], JSON_THROW_ON_ERROR), "\n";
