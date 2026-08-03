<?php

declare(strict_types=1);

/**
 * Rich, deterministic profile fixture for profile-surface.spec.ts.
 *
 * The shared browser seed stays intentionally small. This add-on is idempotent
 * so Playwright's desktop and mobile projects can both request it without
 * multiplying activity or changing any production data.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BadgeRepository;
use App\Repository\BoardRepository;
use App\Repository\FollowRepository;
use App\Repository\PostRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserProfileFieldRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\PasswordHasher;
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
$hasher = new PasswordHasher();

$ensureUser = static function (string $username, string $displayName) use ($users, $hasher): int {
    $existing = $users->findByUsername($username);
    if ($existing !== null) {
        return (int) $existing['id'];
    }

    $id = $users->create([
        'username' => $username,
        'email' => $username . '@retro.test',
        'password_hash' => $hasher->hash('password123'),
        'display_name' => $displayName,
        'role' => 'user',
        'status' => 'active',
    ]);
    $users->markEmailVerified($id);
    return $id;
};

$galadrielId = $ensureUser('galadriel', 'Galadriel');
$privateId = $ensureUser('private-seat', 'Private Seat');
$emptyId = $ensureUser('empty-seat', 'Empty Seat');
$connectionIds = [
    $ensureUser('elrond', 'Elrond'),
    $ensureUser('erestor', 'Erestor'),
    $ensureUser('glorfindel', 'Glorfindel'),
    $ensureUser('lindir', 'Lindir'),
];

$db->run(
    "UPDATE users
     SET display_name = 'Galadriel', title = 'Loremaster',
         bio = ?, location = 'Lothlórien', website = 'https://galadriel.example',
         pronouns = 'she / her', reputation = 5140, profile_visibility = 'public',
         created_at = '2019-03-15 12:00:00'
     WHERE id = ?",
    [
        <<<'MARKDOWN'
Keeper of the evaluation suite and an unreasonable number of half-finished rollback drills.

## Evidence discipline

- Preserve the audit trail.
- Leave a rollback note for the next reader.

> If I ask you for evidence, it is how we keep the record whole.

Run `repair` after reconciliation.

```text
verified profile content
```
MARKDOWN,
        $galadrielId,
    ],
);
$db->run("UPDATE users SET profile_visibility = 'members' WHERE id = ?", [$privateId]);
$db->run("UPDATE users SET profile_visibility = 'public', bio = NULL, reputation = 0 WHERE id = ?", [$emptyId]);

(new UserProfileFieldRepository($db))->replaceForUser($galadrielId, [
    ['label' => 'Timezone', 'value' => 'UTC+1 · Lothlórien'],
    ['label' => 'Works on', 'value' => 'Evaluation and archive integrity'],
    ['label' => 'Ask me about', 'value' => 'Rollback drills and the lexicon'],
]);

$badges = new BadgeRepository($db);
foreach (['welcome', 'first-post', 'first-thread', 'conversation-starter'] as $slug) {
    $badges->awardBySlug($galadrielId, $slug);
}

$follows = new FollowRepository($db);
$alice = $users->findByUsername('alice');
$bob = $users->findByUsername('bob');
$carol = $users->findByUsername('carol');
$admin = $users->findByUsername('admin');
foreach (array_filter([$alice, $bob, $admin]) as $follower) {
    $follows->follow((int) $follower['id'], $galadrielId);
}
foreach ($connectionIds as $followerId) {
    $follows->follow($followerId, $galadrielId);
}
foreach (array_filter([$carol, $alice]) as $followed) {
    $follows->follow($galadrielId, (int) $followed['id']);
}
foreach (array_slice($connectionIds, 0, 3) as $followedId) {
    $follows->follow($galadrielId, $followedId);
}

$existingTopicCount = (int) $db->fetchValue(
    'SELECT COUNT(*) FROM threads WHERE user_id = ? AND is_deleted = 0',
    [$galadrielId],
);
if ($existingTopicCount >= 22) {
    fwrite(STDOUT, "Profile surface fixture already present.\n");
    exit(0);
}

$boards = new BoardRepository($db);
$general = $boards->findBySlug('general');
$feedback = $boards->findBySlug('feedback');
$announcements = $boards->findBySlug('announcements');
if ($general === null || $feedback === null || $announcements === null || $admin === null || $alice === null || $bob === null || $carol === null) {
    throw new RuntimeException('Run tests/browser/prepare.sh before the profile fixture.');
}

$posting = new PostingService(
    $db,
    new ThreadRepository($db),
    new PostRepository($db),
    $boards,
    $users,
    new Markdown(new HtmlSanitizer()),
    new WriteGate(),
    new BoardPolicy(),
    $config,
);
$galadriel = $users->findEntity($galadrielId);
$adminEntity = $users->findEntity((int) $admin['id']);
if ($galadriel === null || $adminEntity === null) {
    throw new RuntimeException('Profile fixture users could not be loaded.');
}

$topicTitles = [
    'Evaluations as ritual, not gate',
    'A rollback drill you can actually run on a Tuesday',
    'On exposing capability before we are asked',
    'The migration that could not be undone — a postmortem',
    'Naming things so the next reader is not lost',
    'What belongs in the audit trail, and what does not',
    'Quiet hours for the notification worker',
    'Reading the archive backwards',
    'Against the heroic on-call rotation',
    'Evidence, not assurance',
    'A smaller composer',
    'The welcome that actually welcomes',
    'Keeping a decision log useful',
    'The case for a reversible migration',
    'How we review a quiet failure',
    'A glossary for shared systems',
    'What the archive should remember',
    'Notes from the rollback rehearsal',
    'A humane incident handoff',
    'When a warning needs an owner',
    'The shape of a useful checklist',
    'Practice before the difficult day',
];
$topicPosts = [];
foreach ($topicTitles as $index => $title) {
    $board = match ($index % 3) {
        1 => $feedback,
        2 => $announcements,
        default => $general,
    };
    $created = $posting->createThread($galadriel, [
        'board_id' => (int) $board['id'],
        'title' => $title,
        'body' => 'A practical note for the record: make the evidence visible, keep the rollback bounded, and leave the next reader a clear path.',
    ]);
    $topicPosts[$title] = (int) $created['post_id'];
}

$exchange = $posting->createThread($adminEntity, [
    'board_id' => (int) $general['id'],
    'title' => 'Who changed what — and can you prove the rollback?',
    'body' => 'A shared thread for evidence, audit trails, and reversible operations.',
]);
for ($i = 1; $i <= 22; $i++) {
    $posting->reply($galadriel, (int) $exchange['thread_id'], [
        'body' => sprintf('Profile response %02d: keep the audit trail whole and the rollback drill runnable.', $i),
    ]);
}

$reactionUsers = [(int) $bob['id'], (int) $carol['id'], (int) $admin['id'], (int) $alice['id']];
$admired = [
    'The migration that could not be undone — a postmortem' => 4,
    'A rollback drill you can actually run on a Tuesday' => 3,
    'Against the heroic on-call rotation' => 2,
    'Evidence, not assurance' => 1,
];
foreach ($admired as $title => $count) {
    foreach (array_slice($reactionUsers, 0, $count) as $userId) {
        $db->run(
            "INSERT IGNORE INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, '👍', UTC_TIMESTAMP())",
            [$topicPosts[$title], $userId],
        );
    }
}

// Keep visual evidence byte-stable across reruns. Creation order still provides
// deterministic tie-breaking through the repositories' secondary id ordering.
$evidenceTime = '2026-08-03 10:17:00';
$db->run('UPDATE threads SET created_at = ?, last_post_at = ? WHERE user_id = ?', [$evidenceTime, $evidenceTime, $galadrielId]);
$db->run('UPDATE posts SET created_at = ? WHERE user_id = ?', [$evidenceTime, $galadrielId]);

fwrite(STDOUT, "Seeded profile surface fixture.\n");
