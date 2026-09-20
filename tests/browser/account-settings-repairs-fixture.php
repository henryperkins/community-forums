<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\SessionRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
Env::load(dirname(__DIR__, 2) . '/.env');
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_unified_e2e(?:_[a-z0-9]+)*$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Account repair fixtures require the disposable unified evidence database.');
}
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$command = $argv[1] ?? 'reset';
$user = $users->findByUsername('settings-repair');
if ($user === null && $command !== 'reset') {
    throw new RuntimeException('Run the account repair fixture reset first.');
}
if ($command === 'reset') {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $hash = (new PasswordHasher())->hash('password123');
    $uid = $user === null ? $users->create([
        'username' => 'settings-repair', 'email' => 'settings-repair@retro.test',
        'password_hash' => $hash, 'display_name' => 'Settings member', 'role' => 'user', 'status' => 'active',
    ]) : (int) $user['id'];
    $db->run("UPDATE users SET status = 'active', suspended_until = NULL, password_hash = ?,
        display_name = 'Settings member', bio = 'Stored biography', avatar_path = NULL, avatar_source = 'monogram',
        location = NULL, website = NULL, pronouns = NULL, signature = NULL,
        email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP(), created_at = '2020-01-01 00:00:00',
        timezone = 'UTC', digest_hour = NULL, last_daily_digest_at = NULL WHERE id = ?", [$hash, $uid]);
    foreach (['account_deletion_requests', 'bans', 'user_totp_credentials', 'user_recovery_codes',
        'mfa_login_challenges', 'board_folders', 'saved_feed_filters', 'user_profile_fields', 'sessions'] as $table) {
        $db->run('DELETE FROM ' . $table . ' WHERE user_id = ?', [$uid]);
    }
    (new UserPreferenceRepository($db))->merge($uid, ['theme' => 'light', 'pause_all_email' => false]);
    $boards = new BoardRepository($db);
    $board = $boards->findBySlug('settings-repair-board');
    $boardId = $board === null ? $boards->create([
        'category_id' => (new CategoryRepository($db))->create('Settings evidence'),
        'slug' => 'settings-repair-board', 'name' => 'Settings evidence board', 'visibility' => 'public',
    ]) : (int) $board['id'];
    $db->run("UPDATE boards SET visibility = 'public' WHERE id = ?", [$boardId]);
    if (!$db->fetchValue('SELECT id FROM threads WHERE board_id = ?', [$boardId])) {
        $posting = new \App\Service\PostingService(
            $db, new \App\Repository\ThreadRepository($db), new \App\Repository\PostRepository($db),
            $boards, $users, new \App\Support\Markdown(new \App\Support\HtmlSanitizer()),
            new \App\Security\WriteGate(), new \App\Security\BoardPolicy(), $config,
        );
        $posting->createThread($users->findEntity($uid), [
            'board_id' => $boardId, 'title' => 'Settings evidence topic',
            'body' => 'A saved feed should keep this topic scoped to its selected board.',
        ]);
    }
    $user = $users->find($uid);
} else {
    $uid = (int) $user['id'];
    switch ($command) {
        case 'suspend':
            $db->run("UPDATE users SET status = 'suspended', suspended_until = '2030-01-01 00:00:00' WHERE id = ?", [$uid]);
            break;
        case 'passwordless':
            $db->run('UPDATE users SET password_hash = NULL WHERE id = ?', [$uid]);
            break;
        case 'expire-suspension':
            $db->run("UPDATE users SET suspended_until = '2020-01-01 00:00:00' WHERE id = ?", [$uid]);
            $db->run("UPDATE bans SET expires_at = '2020-01-01 00:00:00' WHERE user_id = ? AND scope = 'site' AND type = 'post'", [$uid]);
            break;
        case 'dark':
            (new UserPreferenceRepository($db))->merge($uid, ['theme' => 'dark']);
            break;
        case 'other-sessions':
            $sessions = new SessionRepository($db);
            foreach ([
                'chrome' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/149.0.0.0 Safari/537.36',
                'unknown' => '<script>unknown-browser</script>',
            ] as $label => $agent) {
                $id = hash('sha256', 'settings-repair-' . $uid . '-' . $label);
                $db->run('DELETE FROM sessions WHERE id = ? AND user_id = ?', [$id, $uid]);
                $sessions->create([
                    'id' => $id, 'user_id' => $uid, 'csrf_secret' => bin2hex(random_bytes(32)),
                    'user_agent' => $agent, 'ip' => '192.0.2.10',
                    'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
                ]);
            }
            break;
        case 'private-board':
            $db->run("UPDATE boards SET visibility = 'private' WHERE slug = 'settings-repair-board'");
            break;
        case 'inspect':
            break;
        default:
            throw new RuntimeException('Unknown account fixture command.');
    }
    $user = $users->find($uid);
    $boardId = (int) $db->fetchValue("SELECT id FROM boards WHERE slug = 'settings-repair-board'");
}
echo json_encode([
    'id' => (int) $user['id'], 'board_id' => $boardId,
    'status' => $user['status'], 'display_name' => $user['display_name'], 'bio' => $user['bio'],
    'avatar_path' => $user['avatar_path'], 'has_password' => $user['password_hash'] !== null,
], JSON_THROW_ON_ERROR) . "\n";
