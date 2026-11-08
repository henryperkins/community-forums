<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\MfaRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\SecretBox;
use App\Security\Totp;

// Mutating fixture for mfa-unreadable-secret.spec.ts. It seals the member's TOTP
// secret under a dummy key the server does not have, so it can only run against
// its own disposable database.
if (getenv('APP_ENV') !== 'test' || getenv('DB_DATABASE') !== 'retroboards_e2e_mfa_unreadable') {
    throw new RuntimeException('Select DB_DATABASE=retroboards_e2e_mfa_unreadable in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$mfa = new MfaRepository($db);
$serverKey = (string) $config->get('app.key', '');
$lostKey = str_repeat('2b', 32); // a dummy key the server is never given
if ($serverKey === '' || trim($serverKey) === $lostKey) {
    throw new RuntimeException('The server APP_KEY must be set and differ from the fixture\'s lost key.');
}
$member = $users->findByUsername('mfa-unreadable');
$command = $argv[1] ?? 'state';

if ($command === 'reset') {
    $ratePath = (string) $config->get('paths.ratelimit');
    if (!str_starts_with($ratePath, '/')) {
        $ratePath = $root . '/' . $ratePath;
    }
    if ($ratePath !== $root . '/storage/ratelimit-e2e-mfa-unreadable') {
        throw new RuntimeException('Use this fixture\'s isolated rate-limit path.');
    }
    foreach (glob($ratePath . '/*.json') ?: [] as $file) {
        if (is_file($file) && !is_link($file)) {
            unlink($file);
        }
    }
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $hash = (new PasswordHasher())->hash('password123');
    $id = $member === null ? $users->create([
        'username' => 'mfa-unreadable', 'email' => 'mfa-unreadable@retro.test', 'password_hash' => $hash,
        'display_name' => 'MFA evidence member', 'role' => 'user', 'status' => 'active',
    ]) : (int) $member['id'];
    $db->run("UPDATE users SET password_hash = ?, status = 'active', suspended_until = NULL,
        email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP() WHERE id = ?", [$hash, $id]);
    foreach (['user_recovery_codes', 'user_totp_credentials', 'mfa_login_challenges', 'sessions'] as $table) {
        $db->run('DELETE FROM ' . $table . ' WHERE user_id = ?', [$id]);
    }
    echo json_encode(['user_id' => $id]), "\n";
    exit(0);
}

if ($member === null) {
    throw new RuntimeException('Run reset first.');
}
$id = (int) $member['id'];

if ($command === 'enroll') {
    // Enabled TOTP whose secret the server cannot open, plus recovery codes the
    // server CAN verify (HMAC under its own APP_KEY, exactly as MfaService does).
    $secret = (new Totp())->generateSecret();
    $mfa->savePendingTotp($id, (new SecretBox($lostKey))->encrypt($secret));
    $mfa->enableTotp($id, 0);
    $codes = [];
    for ($i = 0; $i < 10; $i++) {
        $raw = strtoupper(bin2hex(random_bytes(5)));
        $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5, 5);
    }
    $mfa->replaceRecoveryCodes($id, array_map(
        static fn (string $c): string => hash_hmac('sha256', str_replace('-', '', $c), $serverKey),
        $codes,
    ));
    echo json_encode(['secret' => $secret, 'codes' => $codes]), "\n";
    exit(0);
}

if ($command === 'pending') {
    $db->run('DELETE FROM user_recovery_codes WHERE user_id = ?', [$id]);
    $secret = (new Totp())->generateSecret();
    $mfa->savePendingTotp($id, (new SecretBox($lostKey))->encrypt($secret));
    echo json_encode(['secret' => $secret]), "\n";
    exit(0);
}

$row = $mfa->totpForUser($id);
echo json_encode([
    'enabled' => $mfa->enabledForUser($id),
    'pending' => $row !== null && $row['enabled_at'] === null && $row['disabled_at'] === null,
    'unused_recovery_codes' => $mfa->unusedRecoveryCount($id),
]), "\n";
