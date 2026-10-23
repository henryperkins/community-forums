<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

// Explicit throwaway DB selection is mandatory; never inherit a real .env DB.
$database = getenv('DB_DATABASE');
if (!is_string($database) || !preg_match('/^retroboards_(?:e2e|[a-z0-9_]*browser[a-z0-9_]*)$/D', $database)) {
    throw new RuntimeException('Select a throwaway browser database with DB_DATABASE.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$settings = new SettingRepository($db);
$users = new UserRepository($db);
$member = $users->findByUsername('safeguardtarget');
if ($member === null) {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $id = $users->create(['username' => 'safeguardtarget', 'display_name' => 'Evidence member', 'email' => 'safeguardtarget@retro.test', 'password_hash' => (new PasswordHasher())->hash('password123')]);
    $member = $users->find($id);
}
if (($argv[1] ?? '') === 'reset') {
    $settings->set('registration_mode', 'open');
    $settings->set('antiabuse_mode', 'hold');
    $settings->set('antiabuse_blocked_words', ['original phrase']);
    $db->run('DELETE FROM bans WHERE user_id = ?', [(int) $member['id']]);
    $db->run("UPDATE users SET status = 'active', suspended_until = NULL WHERE id = ?", [(int) $member['id']]);
    $member = $users->find((int) $member['id']);
} elseif (($argv[1] ?? '') === 'audit') {
    $admin = $users->findByUsername('admin');
    $log = new ModerationLogRepository($db);
    $log->log(['actor_id' => (int) $admin['id'], 'action' => 'change_role', 'target_type' => 'user', 'target_id' => (int) $member['id'], 'reason' => 'Safeguards browser evidence', 'before' => ['role' => 'user'], 'after' => ['role' => 'moderator']]);
    $log->log(['actor_id' => null, 'action' => 'future.action', 'target_type' => 'user', 'target_id' => 987654321, 'reason' => 'Safeguards unknown action', 'after' => ['message' => '<script>must stay text</script>']]);
}
echo json_encode([
    'id' => (int) $member['id'], 'status' => $member['status'], 'until' => $member['suspended_until'],
    'registration' => $settings->getString('registration_mode'),
    'mode' => $settings->getString('antiabuse_mode'), 'words' => $settings->get('antiabuse_blocked_words'),
], JSON_THROW_ON_ERROR);
