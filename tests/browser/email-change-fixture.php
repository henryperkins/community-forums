<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Repository\SettingRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Service\EmailChangeService;

// Never use this mutating fixture against a member or shared development DB.
if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_e2e_emailchange_[a-z0-9_]+$/D', (string) getenv('DB_DATABASE'))) {
    throw new RuntimeException('Select the disposable email-change browser database in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$settings = new SettingRepository($db);
$command = $argv[1] ?? 'state';
$user = $users->findByUsername('email-change');

if ($command === 'reset') {
    $ratePath = (string) $config->get('paths.ratelimit');
    if (!str_starts_with($ratePath, '/')) { $ratePath = $root . '/' . $ratePath; }
    if ($ratePath !== $root . '/storage/ratelimit-e2e-emailchange') {
        throw new RuntimeException('Use this fixture\'s isolated rate-limit path.');
    }
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $hash = (new PasswordHasher())->hash('password123');
    foreach (['email-change' => 'email-change@retro.test', 'email-foreign' => 'email-foreign@retro.test'] as $username => $email) {
        $row = $users->findByUsername($username);
        $id = $row === null ? $users->create([
            'username' => $username, 'email' => $email, 'password_hash' => $hash,
            'display_name' => 'Email evidence member', 'role' => 'user', 'status' => 'active',
        ]) : (int) $row['id'];
        $db->run("UPDATE users SET email = ?, password_hash = ?, status = 'active', suspended_until = NULL,
            email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP(), created_at = '2020-01-01 00:00:00' WHERE id = ?", [$email, $hash, $id]);
        foreach (['verifications', 'sessions', 'webauthn_challenges', 'webauthn_credentials', 'email_deliveries', 'oauth_identities'] as $table) {
            $db->run('DELETE FROM ' . $table . ' WHERE user_id = ?', [$id]);
        }
        (new UserPreferenceRepository($db))->merge($id, ['theme' => 'light', 'density' => 'comfortable', 'pause_all_email' => false]);
    }
    $features = $settings->get('features', []);
    $features = is_array($features) ? $features : [];
    $features['email_change'] = true;
    $features['passkeys'] = true;
    $features['oauth'] = true;
    $settings->set('features', $features);
    $settings->set('email_require_verified_domain', false);
    $user = $users->findByUsername('email-change');
    foreach (glob($ratePath . '/*.json') ?: [] as $file) {
        if (is_file($file) && !is_link($file)) { unlink($file); }
    }
} elseif ($user === null) {
    throw new RuntimeException('Reset the email-change fixture first.');
}

$extra = [];
if ($command === 'issue' || $command === 'foreign') {
    $recipient = $command === 'foreign' ? $users->findByUsername('email-foreign') : $user;
    $mail = new ArrayMailer();
    $app = new App($config, $db);
    $container = (new ReflectionMethod($app, 'buildContainer'))->invoke($app, new Request('GET', '/settings/account/email'));
    $container->instance(Mailer::class, $mail);
    $container->get(EmailChangeService::class)->issue(
        $users->findEntity((int) $recipient['id']), $argv[2] ?? 'changed@retro.test', 'password123', null, 'browser-fixture',
    );
    if (preg_match('/[?&]token=([a-f0-9]{64})/', $mail->sent[0]['text'], $match) !== 1) {
        throw new RuntimeException('The real service did not capture a verification link.');
    }
    // Private process output only. This is synthetic capture, not an inbox receipt.
    $extra = ['token' => $match[1]];
} elseif ($command === 'expire') {
    $db->run("UPDATE verifications SET expires_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 SECOND) WHERE user_id = ? AND type = 'email_change' AND used_at IS NULL", [$user['id']]);
} elseif ($command === 'dark') {
    $features = $settings->get('features', []);
    $features['email_change'] = false;
    $settings->set('features', $features);
} elseif ($command === 'passwordless') {
    $db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$user['id']]);
} elseif ($command === 'provider') {
    $identities = new OAuthIdentityRepository($db);
    if (!$identities->existsForUserProvider((int) $user['id'], 'google')) {
        $identities->create(['user_id' => (int) $user['id'], 'provider' => 'google', 'provider_user_id' => 'email-change-browser-owned-google']);
    }
} elseif (!in_array($command, ['reset', 'state'], true)) {
    throw new RuntimeException('Unknown email-change fixture command.');
}

$user = $users->find((int) $user['id']);
$pending = $db->fetch("SELECT new_email, expires_at FROM verifications WHERE user_id = ? AND type = 'email_change' AND used_at IS NULL AND expires_at > UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1", [$user['id']]);
echo json_encode($extra + [
    'email' => $user['email'], 'verified' => $user['email_verified_at'] !== null, 'pending' => $pending,
    'active_sessions' => (int) $db->fetchValue('SELECT COUNT(*) FROM sessions WHERE user_id = ? AND revoked_at IS NULL AND expires_at > UTC_TIMESTAMP()', [$user['id']]),
    'security_notices' => (int) $db->fetchValue("SELECT COUNT(*) FROM email_deliveries WHERE user_id = ? AND kind = 'system' AND email = 'email-change@retro.test'", [$user['id']]),
], JSON_THROW_ON_ERROR) . PHP_EOL;
