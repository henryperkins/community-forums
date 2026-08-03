<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$threads = new ThreadRepository($db);
$posts = new PostRepository($db);
$boards = new BoardRepository($db);
$posting = new PostingService(
    $db,
    $threads,
    $posts,
    $boards,
    $users,
    new Markdown(new HtmlSanitizer()),
    new WriteGate(),
    new BoardPolicy(),
    $config,
);

$alice = $users->findByUsername('alice');
$bob = $users->findByUsername('bob');
$carol = $users->findByUsername('carol');
$admin = $users->findByUsername('admin');
$general = $db->fetch("SELECT id FROM boards WHERE slug = 'general' LIMIT 1");
if ($alice === null || $bob === null || $carol === null || $admin === null || $general === null) {
    throw new RuntimeException('Run tests/browser/prepare.sh before the thread-content fixture.');
}

$title = 'Thread content presentation states';
$thread = $db->fetch(
    'SELECT id, slug FROM threads WHERE board_id = ? AND title = ? AND is_deleted = 0 ORDER BY id ASC LIMIT 1',
    [(int) $general['id'], $title],
);
if ($thread === null) {
    $created = $posting->createThread($users->findEntity((int) $alice['id']), [
        'board_id' => (int) $general['id'],
        'title' => $title,
        'body' => 'A deterministic thread for grouped and removed reply presentation.',
    ]);
    $thread = $db->fetch('SELECT id, slug FROM threads WHERE id = ?', [(int) $created['thread_id']]);
}
if ($thread === null) {
    throw new RuntimeException('Could not create the thread-content fixture.');
}
$threadId = (int) $thread['id'];

/** @param array<string,mixed> $user */
$ensureReply = static function (array $user, string $body) use ($db, $posting, $users, $threadId): int {
    $existing = $db->fetch(
        'SELECT id FROM posts WHERE thread_id = ? AND body = ? ORDER BY id ASC LIMIT 1',
        [$threadId, $body],
    );
    if ($existing !== null) {
        return (int) $existing['id'];
    }
    return $posting->reply($users->findEntity((int) $user['id']), $threadId, ['body' => $body]);
};

$removedId = $ensureReply($bob, 'This reply is intentionally removed for presentation evidence.');
$firstGroupedId = $ensureReply($carol, 'First half of a consecutive same-author reply.');
$secondGroupedId = $ensureReply($carol, 'Second half of a consecutive same-author reply.');

$removed = $posts->findWithContext($removedId);
if ($removed === null) {
    throw new RuntimeException('Could not load the removable fixture post.');
}
if ((int) $removed['is_deleted'] === 0) {
    $db->transaction(function () use ($posts, $posting, $removed, $removedId, $admin): void {
        if ($posts->softDelete($removedId, (int) $admin['id']) > 0) {
            $posting->applyDeletionCounters($removed);
        }
    });
}

$opening = $db->fetch('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$threadId]);
if ($opening === null) {
    throw new RuntimeException('Could not load the fixture opening post.');
}
$timestamps = [
    (int) $opening['id'] => '2026-08-03 12:00:00',
    $removedId => '2026-08-03 12:01:00',
    $firstGroupedId => '2026-08-03 12:02:00',
    $secondGroupedId => '2026-08-03 12:03:00',
];
foreach ($timestamps as $postId => $createdAt) {
    $db->run('UPDATE posts SET created_at = ? WHERE id = ?', [$createdAt, $postId]);
}
$db->run(
    "UPDATE posts SET deleted_at = '2026-08-03 12:01:30' WHERE id = ?",
    [$removedId],
);
$db->run(
    "UPDATE threads SET created_at = '2026-08-03 12:00:00', last_post_at = '2026-08-03 12:03:00', last_post_id = ? WHERE id = ?",
    [$secondGroupedId, $threadId],
);

fwrite(STDOUT, '/t/' . $threadId . '-' . $thread['slug'] . PHP_EOL);
