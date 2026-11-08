<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\EmailSuppressionRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_e2e_resubscription(?:_[a-z0-9]+)*$/D', (string) getenv('DB_DATABASE'))) {
    throw new RuntimeException('Select a disposable retroboards_e2e_resubscription database in APP_ENV=test.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$users = new UserRepository($db);
$suppressions = new EmailSuppressionRepository($db);
$prefs = new UserPreferenceRepository($db);
$command = $argv[1] ?? 'state';
$email = 'resubscription-member@retro.test';
$otherEmail = 'resubscription-other@retro.test';

if ($command === 'reset') {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    foreach ([$email => 'resubscription-member', $otherEmail => 'resubscription-other'] as $address => $username) {
        if ($users->findByEmail($address) === null) {
            $users->create(['username' => $username, 'email' => $address,
                'password_hash' => (new PasswordHasher())->hash('password123'),
                'role' => 'user', 'status' => 'active']);
        }
        $suppressions->unsuppress($address);
        $suppressions->suppress($address, 'unsubscribe');
    }
    $member = $users->findByEmail($email);
    $id = (int) $member['id'];
    $users->setStatus($id, 'active', null);
    $db->run("UPDATE users SET onboarded_at = UTC_TIMESTAMP(), timezone = 'Europe/Paris', digest_hour = 9 WHERE id = ?", [$id]);
    $db->run('DELETE FROM sessions WHERE user_id = ?', [$id]);
    $prefs->merge($id, ['pause_all_email' => true, 'theme' => 'light', 'hide_from_leaderboard' => true]);
}
$member = $users->findByEmail($email);
if ($member === null) { throw new RuntimeException('Reset the resubscription fixture first.'); }
$id = (int) $member['id'];
switch ($command) {
    case 'restrict': $users->setStatus($id, 'suspended', '2099-01-01 00:00:00'); break;
    case 'activate': $users->setStatus($id, 'active', null); break;
    case 'unsubscribe':
    case 'complaint':
        $suppressions->unsuppress($email);
        $suppressions->suppress($email, $command);
        break;
    case 'reset':
    case 'state': break;
    default: throw new RuntimeException('Unknown resubscription fixture command.');
}
$member = $users->find($id);
echo json_encode([
    'email' => $email, 'status' => $member['status'], 'suppression' => $suppressions->reasonFor($email),
    'other_suppression' => $suppressions->reasonFor($otherEmail), 'prefs' => $prefs->get($id),
    'timezone' => $member['timezone'], 'digest_hour' => $member['digest_hour'],
], JSON_THROW_ON_ERROR), "\n";
