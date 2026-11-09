<?php

declare(strict_types=1);

/**
 * Data for tests/browser/fluent-icons-complete.spec.ts (ADR 0060).
 *
 *   php tests/browser/fluent-icons-fixture.php reset     # the member, one notification of each type, a locked board
 *   php tests/browser/fluent-icons-fixture.php verify    # a fresh email-verification token for the member
 *   php tests/browser/fluent-icons-fixture.php teardown  # removes the locked board and the member's notifications
 *
 * Only the fixture's own member (fluent-notices@retro.test / password123) gets
 * notifications, so other specs' Inbox and bell counts are unchanged. The board
 * members cannot post in exists only between reset and teardown; the compose
 * rail draws it with the lock.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\ConversationRepository;
use App\Repository\NotificationRepository;
use App\Repository\UserRepository;
use App\Repository\VerificationRepository;
use App\Security\PasswordHasher;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if (!preg_match('/^retroboards_e2e(?:_[a-z0-9_]+)?$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Icon fixtures require an isolated browser database.');
}

const MEMBER = 'fluent_notices';
const LOCKED_BOARD = 'fluent-icons-locked';
const TYPES = ['reply', 'new_thread', 'new_post', 'mention', 'reaction', 'follow', 'badge', 'solved', 'dm', 'mod', 'announcement'];

$db = new Database($config->get('db'));
$users = new UserRepository($db);
$boards = new BoardRepository($db);
$command = $argv[1] ?? 'reset';

$member = $users->findByUsername(MEMBER);
$memberId = $member !== null ? (int) $member['id'] : $users->create([
    'username' => MEMBER,
    'email' => 'fluent-notices@retro.test',
    'display_name' => 'Fluent notices',
    'password_hash' => (new PasswordHasher())->hash('password123'),
]);
$db->run('UPDATE users SET onboarded_at = COALESCE(onboarded_at, UTC_TIMESTAMP()) WHERE id = ?', [$memberId]);

$locked = $boards->findBySlug(LOCKED_BOARD);
$result = ['member_id' => $memberId];

if ($command === 'teardown') {
    $db->run('DELETE FROM notifications WHERE user_id = ?', [$memberId]);
    if ($locked !== null) {
        $boards->delete((int) $locked['id']);
    }
} elseif ($command === 'verify') {
    $raw = bin2hex(random_bytes(32));
    $verifications = new VerificationRepository($db);
    $verifications->invalidateOutstanding($memberId, 'email_verify');
    $verifications->create($memberId, 'email_verify', hash('sha256', $raw), gmdate('Y-m-d H:i:s', time() + 3600), 'fluent-notices@retro.test');
    $result['token'] = $raw;
} elseif ($command === 'reset') {
    $general = $boards->findBySlug('general');
    $actor = $users->findByUsername('admin');
    $thread = $general === null ? null : $db->fetch('SELECT id FROM threads WHERE board_id = ? ORDER BY id LIMIT 1', [(int) $general['id']]);
    if ($general === null || $actor === null || $thread === null) {
        throw new RuntimeException('Run tests/browser/prepare.sh before the icon fixture.');
    }
    $actorId = (int) $actor['id'];
    $threadId = (int) $thread['id'];
    $conversation = (new ConversationRepository($db))->findOrCreateBetween($memberId, $actorId);
    $notifications = new NotificationRepository($db);
    $db->run('DELETE FROM notifications WHERE user_id = ?', [$memberId]);
    foreach (TYPES as $type) {
        $notifications->create(match ($type) {
            'badge', 'announcement' => ['user_id' => $memberId, 'type' => $type],
            'mod' => ['user_id' => $memberId, 'type' => $type, 'thread_id' => $threadId],
            'follow' => ['user_id' => $memberId, 'type' => $type, 'actor_id' => $actorId],
            'dm' => ['user_id' => $memberId, 'type' => $type, 'actor_id' => $actorId, 'conversation_id' => $conversation],
            default => ['user_id' => $memberId, 'type' => $type, 'actor_id' => $actorId, 'thread_id' => $threadId],
        });
    }
    if ($locked === null) {
        $boards->create([
            'category_id' => (int) $general['category_id'],
            'slug' => LOCKED_BOARD,
            'name' => 'Council records',
            'description' => 'Only administrators post here.',
            'post_min_role' => 'admin',
        ]);
    }
    $result['types'] = TYPES;
} else {
    throw new RuntimeException('Usage: php tests/browser/fluent-icons-fixture.php reset|verify|teardown');
}
fwrite(STDOUT, json_encode($result, JSON_THROW_ON_ERROR) . PHP_EOL);
