<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
Env::load(dirname(__DIR__, 2) . '/.env');
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
if (getenv('APP_ENV') !== 'test'
    || !preg_match('/^retroboards_unified_e2e(?:_[a-z0-9]+)*$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Notification fixtures require the disposable unified evidence database.');
}
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$repo = new NotificationRepository($db);
$command = $argv[1] ?? 'reset';
if ($command === 'board-index-ready') {
    $admin = $users->findByUsername('admin');
    if ($admin === null) { throw new RuntimeException('Seed the standard browser administrator first.'); }
    $users->setOnboarded((int) $admin['id'], true);
    (new UserPreferenceRepository($db))->merge((int) $admin['id'], ['theme' => 'light', 'density' => 'comfortable']);
    echo json_encode(['admin_id' => (int) $admin['id'], 'onboarded' => true], JSON_THROW_ON_ERROR) . "\n";
    exit;
}
$reader = $users->findByUsername('notifications-reader');
if ($command === 'reset') {
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    foreach (['reader', 'actor'] as $role) {
        $name = 'notifications-' . $role;
        $user = $users->findByUsername($name);
        if ($user === null) {
            $users->create(['username' => $name, 'email' => $name . '@retro.test', 'password_hash' => (new PasswordHasher())->hash('password123'), 'display_name' => $role === 'actor' ? str_repeat('A', 64) : 'Notification member', 'role' => 'user', 'status' => 'active']);
        }
        $db->run("UPDATE users SET email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP(), status = 'active' WHERE username = ?", [$name]);
    }
    $reader = $users->findByUsername('notifications-reader');
    $actor = $users->findByUsername('notifications-actor');
    $db->run('UPDATE users SET display_name = ? WHERE id = ?', [str_repeat('A', 64), (int) $actor['id']]);
    $uid = (int) $reader['id'];
    $repo->clear($uid);
    (new UserPreferenceRepository($db))->merge($uid, ['theme' => 'light', 'density' => 'comfortable']);
    $boards = new BoardRepository($db);
    $board = $boards->findBySlug('notifications-evidence');
    $bid = $board === null ? $boards->create(['category_id' => (new CategoryRepository($db))->create('Notification evidence'), 'slug' => 'notifications-evidence', 'name' => 'Notification evidence', 'visibility' => 'public', 'allow_anonymous' => 1]) : (int) $board['id'];
    $db->run("UPDATE boards SET visibility = 'public', allow_anonymous = 1 WHERE id = ?", [$bid]);
    $posting = new \App\Service\PostingService($db, new \App\Repository\ThreadRepository($db), new \App\Repository\PostRepository($db), $boards, $users, new \App\Support\Markdown(new \App\Support\HtmlSanitizer()), new \App\Security\WriteGate(), new \App\Security\BoardPolicy(), $config);
    foreach ([false, true] as $anonymous) {
        $suffix = $anonymous ? 'Anonymous evidence' : 'LongTitle' . str_repeat('X', 130) . '<em>safe</em>';
        $existing = $db->fetch('SELECT id FROM threads WHERE board_id = ? AND title = ? LIMIT 1', [$bid, $suffix]);
        $thread = $existing === null ? $posting->createThread($users->findEntity((int) $actor['id']), ['board_id' => $bid, 'title' => $suffix, 'body' => 'Notification rendering evidence.', 'is_anonymous' => $anonymous]) : ['thread_id' => (int) $existing['id']];
        if ($anonymous) { $anonymousId = $thread['thread_id']; } else { $threadId = $thread['thread_id']; }
    }
    $old = $repo->create(['user_id' => $uid, 'type' => 'badge']);
    $db->run("UPDATE notifications SET created_at = '2020-01-01 12:30:00' WHERE id = ?", [$old]);
    for ($i = 0; $i < 35; $i++) { $repo->markRead($uid, $repo->create(['user_id' => $uid, 'type' => 'badge'])); }
    foreach (['reply', 'new_thread', 'new_post', 'mention', 'reaction', 'solved'] as $type) {
        $repo->create(['user_id' => $uid, 'actor_id' => (int) $actor['id'], 'type' => $type, 'thread_id' => $threadId]);
    }
    $repo->create(['user_id' => $uid, 'type' => 'reply', 'thread_id' => $threadId]);
    $repo->create(['user_id' => $uid, 'actor_id' => (int) $actor['id'], 'type' => 'mention', 'thread_id' => $anonymousId]);
    $repo->create(['user_id' => $uid, 'type' => 'mod']);
    $repo->create(['user_id' => $uid, 'type' => 'follow', 'actor_id' => (int) $actor['id']]);
    $repo->create(['user_id' => $uid, 'type' => 'announcement']);
    $conversation = (new \App\Repository\ConversationRepository($db))->findOrCreateBetween($uid, (int) $actor['id']);
    $repo->create(['user_id' => $uid, 'type' => 'dm', 'actor_id' => (int) $actor['id'], 'conversation_id' => $conversation]);
} elseif ($reader === null) {
    throw new RuntimeException('Run reset first.');
} else {
    $uid = (int) $reader['id'];
    switch ($command) {
        case 'private': $db->run("UPDATE boards SET visibility = 'private' WHERE slug = 'notifications-evidence'"); break;
        case 'empty': $repo->clear($uid); break;
        case 'read': $repo->markAllRead($uid); break;
        case 'dark': (new UserPreferenceRepository($db))->merge($uid, ['theme' => 'dark']); break;
        case 'name40': $db->run('UPDATE users SET display_name = ? WHERE username = ?', [str_repeat('A', 40), 'notifications-actor']); break;
        case 'name64': $db->run('UPDATE users SET display_name = ? WHERE username = ?', [str_repeat('A', 64), 'notifications-actor']); break;
        case 'inspect': break;
        default: throw new RuntimeException('Unknown notification fixture command.');
    }
}
echo json_encode(['user_id' => (int) $reader['id'], 'unread' => $repo->unreadCount((int) $reader['id']), 'old_id' => (int) $db->fetchValue('SELECT MIN(id) FROM notifications WHERE user_id = ?', [(int) $reader['id']])], JSON_THROW_ON_ERROR) . "\n";
