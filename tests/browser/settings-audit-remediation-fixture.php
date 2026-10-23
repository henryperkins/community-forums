<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\OAuthIdentityRepository;
use App\Repository\SessionRepository;
use App\Repository\UserRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
Env::load(dirname(__DIR__, 2) . '/.env');
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_unified_e2e(?:_[a-z0-9]+)*$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Settings audit fixtures require the disposable unified evidence database.');
}
$db = new Database($config->get('db'));
$user = (new UserRepository($db))->findByUsername('settings-repair');
if ($user === null) {
    throw new RuntimeException('Run the account settings repair fixture reset first.');
}
$uid = (int) $user['id'];
$command = $argv[1] ?? 'reset';

switch ($command) {
    case 'reset':
        // The shared fixture resets this member's other state, but does not own
        // OAuth identities. Remove only the additional identities we seed.
        $db->run('DELETE FROM oauth_identities WHERE user_id = ?', [$uid]);
        break;
    case 'linked':
    case 'passwordless-linked':
        $db->run('DELETE FROM oauth_identities WHERE user_id = ?', [$uid]);
        (new OAuthIdentityRepository($db))->create([
            'user_id' => $uid,
            'provider' => 'google',
            'provider_user_id' => 'settings-audit-' . $uid,
            'email' => 'long.connected.account.for.settings.evidence@example.test',
            'email_verified' => true,
        ]);
        if ($command === 'passwordless-linked') {
            $db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$uid]);
        }
        break;
    case 'long-sessions':
        $sessions = new SessionRepository($db);
        foreach ([
            'samsung' => 'Mozilla/5.0 (Linux; Android 16; A very long self-reported device identifier) AppleWebKit/537.36 SamsungBrowser/29.0 Chrome/149.0.0.0 Mobile Safari/537.36',
            'unknown' => str_repeat('UnrecognizedBrowserDetails', 8),
        ] as $label => $agent) {
            $id = hash('sha256', 'settings-audit-' . $uid . '-' . $label);
            $db->run('DELETE FROM sessions WHERE id = ? AND user_id = ?', [$id, $uid]);
            $sessions->create([
                'id' => $id, 'user_id' => $uid, 'csrf_secret' => bin2hex(random_bytes(32)),
                'user_agent' => $agent, 'ip' => '2001:db8:1234:5678:9abc:def0:1234:5678',
                'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
            ]);
        }
        break;
    default:
        throw new RuntimeException('Unknown settings audit fixture command.');
}

echo json_encode(['id' => $uid, 'command' => $command], JSON_THROW_ON_ERROR) . "\n";
