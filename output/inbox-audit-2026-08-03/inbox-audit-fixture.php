<?php

declare(strict_types=1);

/**
 * /inbox audit fixture. Runs AFTER tests/browser/prepare.sh against a private
 * DB (DB_DATABASE=retroboards_inbox_audit) and populates every state the
 * Community Inbox can render, plus the states it must NOT render.
 *
 * Personas:
 *   alice — rich inbox (watches, assignment, mention, star, snooze, follows)
 *   bob   — member of the private #staff-room
 *   carol — NOT a staff-room member (private-board leak probe)
 *   dana  — empty inbox (empty-state capture)
 *   admin — sees everything, incl. the hidden board
 */

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
use App\Support\MentionLinker;

$root = dirname(__DIR__, 6) . '/community-forums';
if (!is_dir($root)) {
    $root = 'C:/Users/htper/community-forums';
}
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);

$id = static function (string $u) use ($users): int {
    $r = $users->findByUsername($u);
    if ($r === null) {
        throw new RuntimeException("missing user $u — run prepare.sh first");
    }
    return (int) $r['id'];
};
$adminId = $id('admin');
$aliceId = $id('alice');
$bobId = $id('bob');
$carolId = $id('carol');
$danaId = $id('dana');

$boards = new BoardRepository($db);
$markdown = new Markdown(new HtmlSanitizer(), null, new MentionLinker($users, true));
$posting = new PostingService(
    $db,
    new ThreadRepository($db),
    new PostRepository($db),
    $boards,
    $users,
    $markdown,
    new WriteGate(),
    new BoardPolicy(),
    $config,
);
$E = static fn (int $u) => $users->findEntity($u);

$boardId = static fn (string $slug) => (int) $db->fetchValue('SELECT id FROM boards WHERE slug = ?', [$slug]);
$general = $boardId('general');
$announce = $boardId('announcements');
$staff = $boardId('staff-room');

// ── 0. Launch cutover, so "unread" is a live concept rather than dark by default.
//      (settings.value is JSON-checked, so this must go through the repository.)
(new App\Repository\SettingRepository($db))->set('engagement_cutover_at', '2020-01-01 00:00:00');

// ── 1. Hidden board + a topic inside it (must never surface for non-admins).
$warRoom = (int) ($db->fetchValue("SELECT id FROM boards WHERE slug = 'war-room'") ?: 0);
if ($warRoom === 0) {
    $cat = (int) $db->fetchValue('SELECT id FROM categories ORDER BY id LIMIT 1');
    $warRoom = $boards->create([
        'category_id' => $cat, 'slug' => 'war-room', 'name' => 'War Room',
        'description' => 'Hidden incident board.', 'visibility' => 'hidden', 'post_min_role' => 'user',
    ]);
    $hidden = $posting->createThread($E($adminId), [
        'board_id' => $warRoom,
        'title' => 'HIDDEN-BOARD-CANARY incident 4471 postmortem',
        'body' => 'Canary topic. If this string appears in a non-admin inbox the listing gate leaks.',
    ]);
    $posting->reply($E($adminId), $hidden['thread_id'], ['body' => 'Follow-up on the canary topic.']);
}

// ── 2. Private-board topic (bob is a member; carol and alice are not).
$priv = (int) ($db->fetchValue("SELECT id FROM threads WHERE title LIKE 'PRIVATE-BOARD-CANARY%'") ?: 0);
if ($priv === 0) {
    $p = $posting->createThread($E($bobId), [
        'board_id' => $staff,
        'title' => 'PRIVATE-BOARD-CANARY staffing plan for Q4',
        'body' => 'Only staff-room members should ever see this row in an inbox.',
    ]);
    $priv = (int) $p['thread_id'];
    $posting->reply($E($adminId), $priv, ['body' => 'Noted, thanks.']);
}

// ── 3. Workflow statuses across the public boards.
$setStatus = static function (int $threadId, string $status, int $by) use ($db): void {
    $db->run(
        'UPDATE threads SET status = ?, status_changed_at = UTC_TIMESTAMP(), status_changed_by = ? WHERE id = ?',
        [$status, $by, $threadId],
    );
};
$tid = static fn (string $like) => (int) $db->fetchValue('SELECT id FROM threads WHERE title LIKE ? ORDER BY id LIMIT 1', [$like]);
$welcomeT = $tid('Welcome to RetroBoards%');
$shortcutsT = $tid('Share your favourite keyboard shortcuts%');
$mobileT = $tid('Mobile layout looks great%');

$setStatus($shortcutsT, 'needs_answer', $aliceId);
$setStatus($mobileT, 'solved', $adminId);
$setStatus($welcomeT, 'decision_made', $adminId);

// A pinned + locked topic, so both chips render in the list.
$db->run('UPDATE threads SET is_pinned = 1, is_locked = 1 WHERE id = ?', [$welcomeT]);

// An `archived` status topic — a valid enum value with no bespoke chip label.
$archT = $tid('TI Fallback Reference desktop%');
$setStatus($archT, 'archived', $adminId);

// ── 4. Assignment to alice (the "Assigned to you" For You reason + Assigned tab).
$db->run(
    'INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at)
     VALUES (?, ?, ?, UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE assigned_user_id = VALUES(assigned_user_id)',
    [$mobileT, $aliceId, $adminId],
);

// ── 5. Mentions. One true positive, one substring false positive.
$mentionT = $tid('TI Living Brief desktop%');
if ((int) $db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ? AND body LIKE ?', [$mentionT, '%@alice%']) === 0) {
    $posting->reply($E($bobId), $mentionT, ['body' => 'Genuine ping for @alice — can you confirm the rollout window?']);
}
$falseT = $tid('TI Curator Lineage desktop%');
if ((int) $db->fetchValue('SELECT COUNT(*) FROM posts WHERE thread_id = ? AND body LIKE ?', [$falseT, '%alicecorp%']) === 0) {
    $posting->reply($E($carolId), $falseT, [
        'body' => 'FALSE-MENTION-CANARY: vendor contact is billing@alicecorp.example — nobody is being pinged here.',
    ]);
}

// ── 6. Star + read state for alice.
$starT = $tid('TI Fallback desktop%');
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, is_starred) VALUES (?, ?, 1)
     ON DUPLICATE KEY UPDATE is_starred = 1',
    [$aliceId, $starT],
);
// Mark one topic fully read so read/unread contrast is visible in the same list.
$readT = $tid('TI Lineage Reference desktop%');
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, last_read_post_id)
     VALUES (?, ?, (SELECT last_post_id FROM threads WHERE id = ?))
     ON DUPLICATE KEY UPDATE last_read_post_id = VALUES(last_read_post_id)',
    [$aliceId, $readT, $readT],
);

// ── 7. Snoozes: one active (hidden from normal filters), one already expired.
$snoozeT = $tid('TI Last Good desktop%');
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, snoozed_until)
     VALUES (?, ?, DATE_ADD(UTC_TIMESTAMP(), INTERVAL 7 DAY))
     ON DUPLICATE KEY UPDATE snoozed_until = VALUES(snoozed_until)',
    [$aliceId, $snoozeT],
);
$expiredT = $tid('TI Source Safety desktop%');
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, snoozed_until)
     VALUES (?, ?, DATE_SUB(UTC_TIMESTAMP(), INTERVAL 1 DAY))
     ON DUPLICATE KEY UPDATE snoozed_until = VALUES(snoozed_until)',
    [$aliceId, $expiredT],
);

// ── 8. Follows (board + tag) — the two lowest-weight For You reasons.
$db->run(
    "INSERT IGNORE INTO follows (user_id, target_type, target_id) VALUES (?, 'board', ?)",
    [$aliceId, $general],
);
$tagId = (int) ($db->fetchValue("SELECT id FROM tags WHERE slug = 'release'") ?: 0);
if ($tagId === 0) {
    $tagId = $db->insert(
        "INSERT INTO tags (slug, name, description, visibility, is_enabled, created_by)
         VALUES ('release', 'Release', 'Release coordination', 'public', 1, ?)",
        [$adminId],
    );
}
$tagT = $tid('TI Budget Last Good desktop%');
$db->run('INSERT IGNORE INTO thread_tags (thread_id, tag_id, added_by) VALUES (?, ?, ?)', [$tagT, $tagId, $adminId]);
$db->run("INSERT IGNORE INTO follows (user_id, target_type, target_id) VALUES (?, 'tag', ?)", [$aliceId, $tagId]);

// ── 9. Content that must never appear: pending, deleted.
if ($tid('PENDING-CANARY%') === 0) {
    $pendId = $db->insert(
        "INSERT INTO threads (board_id, user_id, title, slug, is_pending, status, created_at, last_post_at)
         VALUES (?, ?, 'PENDING-CANARY awaiting moderator approval', 'pending-canary', 1, 'open', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
        [$general, $carolId],
    );
    $db->run(
        "INSERT INTO posts (thread_id, user_id, body, body_html, is_op, is_pending, created_at)
         VALUES (?, ?, 'Held for approval.', '<p>Held for approval.</p>', 1, 1, UTC_TIMESTAMP())",
        [$pendId, $carolId],
    );
    // Held content must not move the board counters.
    $db->run('INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at)
              VALUES (?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE assigned_user_id = VALUES(assigned_user_id)',
        [$pendId, $aliceId, $adminId]);
}
if ($tid('DELETED-CANARY%') === 0) {
    $delId = $db->insert(
        "INSERT INTO threads (board_id, user_id, title, slug, is_deleted, status, created_at, last_post_at)
         VALUES (?, ?, 'DELETED-CANARY removed by a moderator', 'deleted-canary', 1, 'open', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
        [$general, $carolId],
    );
    $db->run("INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at)
              VALUES (?, ?, ?, UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE assigned_user_id = VALUES(assigned_user_id)",
        [$delId, $aliceId, $adminId]);
}

// ── 10. Anonymous-authored topic (masking must survive the cross-board row).
if ($tid('ANON-CANARY%') === 0) {
    $anon = $posting->createThread($E($danaId), [
        'board_id' => $general,
        'title' => 'ANON-CANARY posting this one without my name',
        'body' => 'Author identity must be masked in every inbox row.',
        'is_anonymous' => '1',
    ]);
    $db->run('UPDATE posts SET is_anonymous = 1 WHERE thread_id = ? AND is_op = 1', [$anon['thread_id']]);
    // alice watches it so it lands in her For You / Watching lists.
    $db->run(
        "INSERT IGNORE INTO subscriptions (user_id, target_type, target_id, frequency) VALUES (?, 'thread', ?, 'instant')",
        [$aliceId, (int) $anon['thread_id']],
    );
}

// ── 11. A long title, to exercise truncation/wrap in the narrow list column.
if ($tid('LONG-TITLE-CANARY%') === 0) {
    $posting->createThread($E($aliceId), [
        'board_id' => $general,
        'title' => 'LONG-TITLE-CANARY a deliberately verbose topic title that runs well past the natural width of the inbox list',
        'body' => 'Checking how the list column handles a maximal title.',
    ]);
}

// ── 12. alice replies to her own topic so "Replies to your topic" has a row,
//        and unanswered/needs-answer buckets stay distinguishable.
$ownT = $tid('LONG-TITLE-CANARY%');
if ($ownT > 0 && (int) $db->fetchValue('SELECT reply_count FROM threads WHERE id = ?', [$ownT]) === 0) {
    $posting->reply($E($bobId), $ownT, ['body' => 'Nice stress test.']);
}

echo "inbox audit fixture applied\n";
foreach ([
    'hidden board' => $warRoom,
    'private canary thread' => $priv,
    'needs_answer' => $shortcutsT,
    'solved+assigned' => $mobileT,
    'decision+pinned+locked' => $welcomeT,
    'archived status' => $archT,
    'mention (true)' => $mentionT,
    'mention (false positive)' => $falseT,
    'starred' => $starT,
    'read' => $readT,
    'snoozed (active)' => $snoozeT,
    'snoozed (expired)' => $expiredT,
    'followed tag thread' => $tagT,
] as $label => $v) {
    printf("  %-24s %d\n", $label, $v);
}
