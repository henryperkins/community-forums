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

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$boards = new BoardRepository($db);
$threads = new ThreadRepository($db);
$posts = new PostRepository($db);
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
$dana = $users->findByUsername('dana');
$admin = $users->findByUsername('admin');
$general = $boards->findBySlug('general');
if ($alice === null || $bob === null || $carol === null || $dana === null || $admin === null || $general === null) {
    throw new RuntimeException('Expected browser seed identities or #general board are missing.');
}

$db->run(
    'UPDATE users SET signature = ?, reputation = ?, title = ? WHERE id = ?',
    ["Questions welcome.\nBuilding durable communities.", 184, 'Archivist', (int) $alice['id']],
);
$db->run(
    'UPDATE users SET signature = ?, reputation = ?, title = ? WHERE id = ?',
    ["Slow software, careful words.\n— Bob", 37, 'Member', (int) $bob['id']],
);
$db->run('UPDATE users SET reputation = ?, title = ? WHERE id = ?', [92, 'Guide', (int) $carol['id']]);

$opBody = <<<'MD'
## A thread should feel durable

The page needs to support careful reading without making participation feel distant. This opening post deliberately mixes short and long paragraphs so we can judge rhythm, measure, and the boundary between one person’s contribution and the next.

> A durable discussion should preserve context without turning every reply into a card.

### What deserves attention

- identity should be clear but never overpower the writing;
- controls should appear when needed and remain reachable by keyboard and touch;
- signatures should read as secondary material; and
- formatted content should survive narrow screens.

Use `Ctrl+K` for search, then compare the published result with the composer preview.

```php
final class ReadingSurface
{
    public function measure(): string
    {
        return '66ch';
    }
}
```

| Surface | Primary job | Risk |
| --- | --- | --- |
| Forum index | Choose a board | Looking like another feed |
| Thread | Read and reply | Controls crowding the record |

Visit [the accessibility guidance](https://www.w3.org/WAI/fundamentals/accessibility-intro/) for useful context.
MD;

$result = $posting->createThread($users->findEntity((int) $alice['id']), [
    'board_id' => (int) $general['id'],
    'title' => 'How should a durable forum thread read?',
    'body' => $opBody,
]);
$threadId = (int) $result['thread_id'];
$opId = (int) $result['post_id'];

$bobOne = $posting->reply($users->findEntity((int) $bob['id']), $threadId, [
    'body' => "The flatter stream works for me, but the author change needs a stronger pause than an ordinary paragraph.\n\nI also want the action menu to stay quiet until I reach for it.",
]);
$bobTwo = $posting->reply($users->findEntity((int) $bob['id']), $threadId, [
    'body' => 'One follow-up: consecutive replies should still expose a reliable timestamp and permalink even when the avatar is not repeated.',
]);
$carolReply = $posting->reply($users->findEntity((int) $carol['id']), $threadId, [
    'body' => "I would keep the identity column, but make uploaded avatars and monogram fallbacks behave identically.\n\nThat gives readers continuity without requiring everyone to upload an image.",
]);
$adminReply = $posting->reply($users->findEntity((int) $admin['id']), $threadId, [
    'body' => "### Working note\n\nThis staff-authored wiki reply records the current moderation and accessibility checklist.",
]);
$deletedReply = $posting->reply($users->findEntity((int) $dana['id']), $threadId, [
    'body' => 'This reply is removed in the audit fixture so the staff-only deleted-post treatment can be inspected.',
]);
$anonymousReply = $posting->reply($users->findEntity((int) $alice['id']), $threadId, [
    'body' => 'Anonymous contribution: the masked byline must not leak title, reputation, signature, or uploaded avatar.',
    'is_anonymous' => 1,
]);

$db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-07-31 15:00:00', $opId]);
$db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-01 09:00:00', $bobOne]);
$db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-01 09:06:00', $bobTwo]);
$db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-02 10:00:00', $carolReply]);
$db->run('UPDATE posts SET created_at = ?, is_wiki = 1 WHERE id = ?', ['2026-08-02 12:00:00', $adminReply]);
$db->run(
    'UPDATE posts SET created_at = ?, is_deleted = 1, deleted_at = ?, deleted_by = ? WHERE id = ?',
    ['2026-08-02 14:00:00', '2026-08-02 14:05:00', (int) $admin['id'], $deletedReply],
);
$db->run('UPDATE posts SET created_at = ? WHERE id = ?', ['2026-08-03 08:00:00', $anonymousReply]);

$db->run(
    "UPDATE threads
     SET created_at = ?, last_post_id = ?, last_post_user_id = ?, last_post_at = ?,
         accepted_answer_post_id = ?, status = 'solved', status_changed_at = ?, status_changed_by = ?,
         reply_count = reply_count - 1
     WHERE id = ?",
    [
        '2026-07-31 15:00:00',
        $anonymousReply,
        (int) $alice['id'],
        '2026-08-03 08:00:00',
        $carolReply,
        '2026-08-02 10:05:00',
        (int) $alice['id'],
        $threadId,
    ],
);
$db->run('UPDATE boards SET post_count = post_count - 1 WHERE id = ?', [(int) $general['id']]);
$db->run('UPDATE users SET post_count = post_count - 1 WHERE id = ?', [(int) $dana['id']]);
$db->run(
    "INSERT INTO thread_status_history (thread_id, actor_id, previous_status, new_status, reason, created_at)
     VALUES (?, ?, 'open', 'solved', 'Accepted a clear answer', ?)",
    [$threadId, (int) $alice['id'], '2026-08-02 10:05:00'],
);
$db->run(
    'INSERT IGNORE INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, ?), (?, ?, ?, ?), (?, ?, ?, ?)',
    [
        $opId, (int) $bob['id'], '❤️', '2026-08-01 09:10:00',
        $opId, (int) $carol['id'], '👍', '2026-08-02 10:06:00',
        $carolReply, (int) $bob['id'], '🎉', '2026-08-02 10:07:00',
    ],
);

echo '/t/' . $threadId . '-' . $result['slug'] . PHP_EOL;

