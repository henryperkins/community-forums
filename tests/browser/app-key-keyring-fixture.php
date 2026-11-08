<?php

declare(strict_types=1);

use App\Controller\UnsubscribeController;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\EmailSuppressionRepository;
use App\Repository\UserRepository;
use App\Security\AppKeyRing;
use App\Security\PasswordHasher;

// Mutating fixture for app-key-keyring.spec.ts (ADR 0059). It needs a server run
// with APP_PREVIOUS_KEY_1 permitted to verify, and only its own disposable database.
if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'retroboards_e2e_app_key_keyring') {
    throw new RuntimeException('Select DB_DATABASE=retroboards_e2e_app_key_keyring in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$suppressions = new EmailSuppressionRepository($db);
$ring = AppKeyRing::fromConfig($config);
$previous = $ring->previous()[0] ?? null;
if (!$ring->isValid() || $previous === null || !$previous->verifies) {
    throw new RuntimeException('Set APP_PREVIOUS_KEY_1 and list its HMAC id in APP_PREVIOUS_KEY_VERIFY_IDS.');
}
$email = 'keyring-member@retro.test';
$command = $argv[1] ?? 'state';

if ($command === 'reset') {
    $ratePath = (string) $config->get('paths.ratelimit');
    if (!str_starts_with($ratePath, '/')) {
        $ratePath = $root . '/' . $ratePath;
    }
    if ($ratePath !== $root . '/storage/ratelimit-e2e-app-key-keyring') {
        throw new RuntimeException('Use this fixture\'s isolated rate-limit path.');
    }
    foreach (glob($ratePath . '/*.json') ?: [] as $file) {
        if (is_file($file) && !is_link($file)) {
            unlink($file);
        }
    }
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $hash = (new PasswordHasher())->hash('password123');
    $member = $users->findByEmail($email);
    $id = $member === null ? $users->create([
        'username' => 'keyring-member', 'email' => $email, 'password_hash' => $hash,
        'display_name' => 'Key ring evidence member', 'role' => 'user', 'status' => 'active',
    ]) : (int) $member['id'];
    $db->run("UPDATE users SET password_hash = ?, status = 'active', suspended_until = NULL,
        email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP() WHERE id = ?", [$hash, $id]);
    $db->run('DELETE FROM sessions WHERE user_id = ?', [$id]);
    $suppressions->unsuppress($email);
    $path = static fn (string $key): string => (string) strstr(UnsubscribeController::link('', $email, $key), '/unsubscribe');
    echo json_encode([
        'email' => $email,
        'previous_link' => $path($previous->hmacKey()),
        'active_link' => $path($ring->active()->hmacKey()),
    ]), "\n";
    exit(0);
}

echo json_encode(['suppression' => $suppressions->reasonFor($email)]), "\n";
