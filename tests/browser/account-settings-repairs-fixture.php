<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\SessionRepository;
use App\Repository\SubscriptionRepository;
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
        'mfa_login_challenges', 'board_folders', 'saved_feed_filters', 'user_profile_fields', 'sessions', 'subscriptions', 'email_deliveries'] as $table) {
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
        case 'delivery-on':
            $threadId = (int) $db->fetchValue("SELECT t.id FROM threads t JOIN boards b ON b.id = t.board_id WHERE b.slug = 'settings-repair-board' ORDER BY t.id LIMIT 1");
            (new SubscriptionRepository($db))->set($uid, 'thread', $threadId, true, true, 'daily');
            $db->run("UPDATE users SET digest_hour = 9, timezone = 'UTC' WHERE id = ?", [$uid]);
            (new UserPreferenceRepository($db))->merge($uid, ['pause_all_email' => false]);
            break;
        case 'restrict-suspended':
        case 'restrict-banned':
        case 'restrict-deactivated':
        case 'restrict-pending_deletion':
            $status = substr($command, strlen('restrict-'));
            $db->run('UPDATE users SET status = ?, suspended_until = ? WHERE id = ?', [
                $status, $status === 'suspended' ? '2030-01-01 00:00:00' : null, $uid,
            ]);
            if ($status === 'pending_deletion') {
                (new \App\Repository\AccountDeletionRepository($db))->create($uid, $uid, '2030-01-01 00:00:00', 'browser_fixture');
            }
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
        case 'email-ops':
            $threadId = (int) $db->fetchValue("SELECT t.id FROM threads t JOIN boards b ON b.id = t.board_id WHERE b.slug = 'settings-repair-board' ORDER BY t.id LIMIT 1");
            (new SubscriptionRepository($db))->set($uid, 'thread', $threadId, true, true, 'daily');
            $db->run("UPDATE users SET digest_hour = 0, timezone = 'UTC' WHERE id = ?", [$uid]);
            $posting = new \App\Service\PostingService(
                $db, new \App\Repository\ThreadRepository($db), new \App\Repository\PostRepository($db),
                new BoardRepository($db), $users, new \App\Support\Markdown(new \App\Support\HtmlSanitizer()),
                new \App\Security\WriteGate(), new \App\Security\BoardPolicy(), $config,
            );
            $admin = $users->findByUsername('admin');
            $postId = $posting->reply($users->findEntity((int) $admin['id']), $threadId, [
                'body' => 'A captured digest retry can deliver this eligible update.',
            ]);
            $payload = [
                'version' => 1,
                'window_start_utc' => gmdate('Y-m-d H:i:s', time() - 3600),
                'window_end_utc' => gmdate('Y-m-d H:i:s'),
                'max_post_id' => $postId,
                'sources' => ['subscriptions' => [['target_type' => 'thread', 'target_id' => $threadId]], 'saved_feeds' => []],
            ];
            $deliveries = new \App\Repository\EmailDeliveryRepository($db);
            foreach ([
                'Replayable digest' => ['failed', 'Captured transport failed', $payload],
                'Suppressed digest' => ['suppressed', 'email_paused', $payload],
                'Invalid digest' => ['failed', 'invalid_digest_payload', ['version' => 99]],
                'Legacy digest' => ['failed', 'unreplayable_legacy_digest', null],
            ] as $subject => [$status, $reason, $body]) {
                $id = $deliveries->enqueue($uid, 'settings-repair@retro.test', 'digest', $subject, null, $body);
                $db->run('UPDATE email_deliveries SET status = ?, error = ?, attempt_count = 5 WHERE id = ?', [$status, $reason, $id]);
            }
            break;
        case 'inspect':
            break;
        default:
            throw new RuntimeException('Unknown account fixture command.');
    }
    $user = $users->find($uid);
    $boardId = (int) $db->fetchValue("SELECT id FROM boards WHERE slug = 'settings-repair-board'");
}
$subscription = $db->fetch('SELECT id, target_id, frequency, email_enabled, in_app_enabled FROM subscriptions WHERE user_id = ? ORDER BY id LIMIT 1', [$uid]);
echo json_encode([
    'id' => (int) $user['id'], 'board_id' => $boardId,
    'status' => $user['status'], 'display_name' => $user['display_name'], 'bio' => $user['bio'],
    'avatar_path' => $user['avatar_path'], 'has_password' => $user['password_hash'] !== null,
    'digest_hour' => $user['digest_hour'] === null ? null : (int) $user['digest_hour'],
    'pause_all_email' => !empty((new UserPreferenceRepository($db))->get($uid)['pause_all_email']),
    'subscription' => $subscription,
    'deliveries' => $db->fetchAll('SELECT id, subject, status, error, attempt_count, sent_at, message_id FROM email_deliveries WHERE user_id = ? ORDER BY id', [$uid]),
], JSON_THROW_ON_ERROR) . "\n";
