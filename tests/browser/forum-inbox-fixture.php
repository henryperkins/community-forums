<?php

declare(strict_types=1);

/**
 * The Forum inbox surface, seeded with the design's own dataset.
 *
 * templates/forum-inbox/ForumInbox.dc.html carries sixteen topics across eight
 * boards, and every personal signal the queue can express appears at least once:
 * unread, mentioned, replied-to, watched topic, watched board, followed board,
 * followed tag, starred, assigned, snoozed, pinned, locked, solved, needs
 * answer, decision. Reproducing that set is what makes a side-by-side render
 * meaningful — the transfer's own capture had one row in it, which is a queue
 * that cannot disagree with its design.
 *
 * Run after tests/browser/prepare.sh, against the same DB_DATABASE:
 *   DB_DATABASE=retroboards_e2e php tests/browser/forum-inbox-fixture.php
 *
 * Signs in as erestor@retro.test / password123.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Security\PasswordHasher;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));

$hash = (new PasswordHasher())->hash('password123');
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$at = static fn (int $hoursAgo): string => $now->modify("-{$hoursAgo} hours")->format('Y-m-d H:i:s');

/** @return int the id of an existing-or-inserted row */
$upsert = static function (Database $db, string $table, array $unique, array $values) use (&$upsert): int {
    $where = implode(' AND ', array_map(static fn (string $k): string => "$k = ?", array_keys($unique)));
    $found = $db->fetchValue("SELECT id FROM $table WHERE $where LIMIT 1", array_values($unique));
    if ($found !== false && $found !== null) {
        return (int) $found;
    }
    $row = $unique + $values;
    $cols = implode(', ', array_keys($row));
    $marks = implode(', ', array_fill(0, count($row), '?'));
    return $db->insert("INSERT INTO $table ($cols) VALUES ($marks)", array_values($row));
};

// ── people ──────────────────────────────────────────────────────────────────
$people = [
    'erestor' => ['Erestor', 'Loremaster', 'user'],
    'glorfindel' => ['Glorfindel', 'Legend', 'user'],
    'arwen' => ['Arwen', 'Veteran', 'user'],
    'elladan' => ['Elladan', 'Member', 'user'],
    'lindir' => ['Lindir', 'Member', 'user'],
    'elrond' => ['Elrond', 'Loremaster', 'moderator'],
    'galadriel' => ['Galadriel', 'Loremaster', 'moderator'],
    'cirdan' => ['Círdan', 'Member', 'user'],
];
$userIds = [];
foreach ($people as $username => [$display, $title, $role]) {
    $userIds[$username] = $upsert($db, 'users', ['username' => $username], [
        'email' => $username . '@retro.test',
        'password_hash' => $hash,
        'display_name' => $display,
        'title' => $title,
        'role' => $role,
        'status' => 'active',
        'email_verified_at' => $at(600),
        'onboarded_at' => $at(600),
        'created_at' => $at(600),
        'show_presence' => 1,
        'last_seen_at' => $at(0),
    ]);
}
$me = $userIds['erestor'];

// A pool the commend counts are drawn from — a reaction is one row per member,
// so a topic cannot be commended more times than the council has members.
$pool = [];
for ($i = 1; $i <= 40; $i++) {
    $pool[] = $upsert($db, 'users', ['username' => 'council' . $i], [
        'email' => 'council' . $i . '@retro.test',
        'password_hash' => $hash,
        'display_name' => 'Council member ' . $i,
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => $at(600),
        'created_at' => $at(600),
        'show_presence' => 0,
    ]);
}

// ── boards ──────────────────────────────────────────────────────────────────
$categories = [
    'The Commons' => ['announcements', 'introductions', 'the-archive', 'the-valley'],
    'Vilya · Expose' => ['interpretability', 'evaluations', 'audit-trails', 'capability-disclosure'],
];
$boardIds = [];
$catPos = 10;
foreach ($categories as $catName => $slugs) {
    $catId = $upsert($db, 'categories', ['name' => $catName], ['position' => $catPos++]);
    $pos = 0;
    foreach ($slugs as $slug) {
        $boardIds[$slug] = $upsert($db, 'boards', ['slug' => $slug], [
            'category_id' => $catId,
            'name' => $slug,
            'description' => '',
            'position' => $pos++,
            'visibility' => 'public',
            'created_at' => $at(600),
        ]);
        $db->run('UPDATE boards SET category_id = ?, name = ?, position = ? WHERE id = ?', [$catId, $slug, $pos, $boardIds[$slug]]);
    }
}

// ── the design's sixteen topics ─────────────────────────────────────────────
// [title, author, board, status, pinned, locked, replies, hoursSinceLastPost,
//  hoursSinceCreated, unread, starred, signal, snoozeDays, commends, body]
$topics = [
    ['Where should ratified decisions live once the council has spoken?', 'erestor', 'the-archive', 'decision_made', 1, 0, 42, 2, 40, true, false, 'replied', 0, 38, 'We keep re-litigating settled questions because the record scatters. A proposal: every decision gets a canonical topic, locked, with the brief pinned to the head.'],
    ['Evaluations as ritual, not gate', 'glorfindel', 'evaluations', 'needs_answer', 0, 0, 17, 5, 22, true, false, 'mentioned', 0, 31, 'A gate you always pass is a ritual. I want to know which of our evals have ever actually blocked a release, and what we changed when they did.'],
    ['Interpretability findings from the Bruinen run', 'arwen', 'interpretability', 'solved', 0, 0, 31, 26, 30, false, true, 'starred', 0, 29, 'Three features survived ablation across every checkpoint. The accepted answer below has the probe configs and the null results, which matter more.'],
    ['Audit trails: what must survive a rewrite?', 'elladan', 'audit-trails', 'open', 0, 0, 0, 30, 28, false, false, 'assigned', 0, 4, 'Starting a list of the fields we can never drop when a record is amended. Please add the ones I have missed rather than arguing about the format.'],
    ['On the precedence of edits', 'erestor', 'audit-trails', 'needs_answer', 0, 0, 5, 50, 52, true, false, 'watching', 0, 11, 'When two wardens amend the same record within the same minute, which version is the record? We have no rule and I have found three behaviours.'],
    ['Introduce yourself here', 'lindir', 'introductions', 'open', 0, 0, 214, 4, 900, false, false, 'followboard', 0, 24, 'The long-running welcome thread. One post each; tell us what you read and what you would like to be asked about.'],
    ['The valley in winter — photo thread', 'arwen', 'the-valley', 'open', 0, 0, 96, 74, 120, false, true, 'watchboard', 6, 36, 'The falls froze for the first time in years. Post yours; no captions longer than a line.'],
    ['Council conduct — read before posting', 'elrond', 'announcements', 'open', 1, 1, 0, 2200, 2200, false, false, 'followtag', 0, 8, 'How we disagree here. Short version: argue with the strongest reading of the post, cite the record, and let the wardens close what is finished.'],
    ['What counts as a citation of the record?', 'lindir', 'audit-trails', 'needs_answer', 0, 0, 8, 8, 60, true, false, 'mentioned', 0, 14, 'Half of us link the topic, half quote the brief, and the two drift the moment a decision is amended. I would like one form we all use.'],
    ['Probe configs for the Bruinen replication', 'elladan', 'interpretability', 'open', 0, 0, 3, 12, 45, true, false, 'watching', 0, 3, 'Posting the exact configs so the run can be reproduced without asking me. Null results included, because they are the useful half.'],
    ['Retention windows for anonymised IPs', 'galadriel', 'audit-trails', 'solved', 0, 0, 12, 34, 70, false, true, 'starred', 0, 33, 'Ninety days for the raw record, indefinitely for the anonymised aggregate. The accepted answer has the schema and the one exception.'],
    ['Amending a decision after it has been cited', 'lindir', 'audit-trails', 'needs_answer', 0, 0, 9, 96, 88, false, false, 'assigned', 3, 20, 'If three topics cite a decision and we then amend it, the citations now point at something that was never agreed. What is the rule?'],
    ['A shorter form for warden notes', 'arwen', 'the-archive', 'open', 0, 0, 21, 120, 10, false, false, 'replied', 0, 27, 'Nobody reads a note longer than the thing it annotates. Proposal: one line of what changed, one line of why, and a link to the record.'],
    ['Eval harness flakes on the third checkpoint', 'glorfindel', 'evaluations', 'needs_answer', 0, 0, 0, 18, 26, true, false, '', 0, 12, 'Reproduces one run in four, always the third checkpoint, never the first or second. I have not found the pattern and would welcome eyes.'],
    ['Should tags be able to close a topic?', 'erestor', 'the-valley', 'open', 0, 0, 44, 210, 150, false, false, '', 0, 35, 'A tag crosses boards, so a tag that closes topics closes them everywhere. I think that is too much power for a label, but I want the argument.'],
    ['Welcome, Celebrían', 'lindir', 'introductions', 'open', 0, 0, 6, 400, 6, false, false, '', 0, 18, 'Joining the archive work from the western valley. Ask her about provenance chains; she has opinions and the receipts to back them.'],
];

$slugify = static function (string $title): string {
    $s = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $title) ?: $title) ?? '');
    return trim(substr($s, 0, 180), '-');
};

$tagId = null;
if ($db->fetchValue("SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tags'") !== false) {
    $tagId = $upsert($db, 'tags', ['slug' => 'conduct'], ['name' => 'conduct', 'created_at' => $at(600)]);
}

foreach ($topics as [$title, $author, $board, $status, $pinned, $locked, $replies, $lastAgo, $createdAgo, $unread, $starred, $signal, $snoozeDays, $commends, $body]) {
    $boardId = $boardIds[$board];
    $authorId = $userIds[$author];
    $threadId = $upsert($db, 'threads', ['title' => $title], [
        'board_id' => $boardId,
        'user_id' => $authorId,
        'slug' => $slugify($title),
        'is_pinned' => $pinned,
        'is_locked' => $locked,
        'status' => $status,
        'status_changed_at' => $status === 'open' ? null : $at($lastAgo),
        'reply_count' => $replies,
        'created_at' => $at($createdAgo),
        'last_post_at' => $at($lastAgo),
    ]);
    $db->run(
        'UPDATE threads SET board_id = ?, user_id = ?, is_pinned = ?, is_locked = ?, status = ?, reply_count = ?, created_at = ?, last_post_at = ? WHERE id = ?',
        [$boardId, $authorId, $pinned, $locked, $status, $replies, $at($createdAgo), $at($lastAgo), $threadId],
    );

    $opId = $db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$threadId]);
    if ($opId === false || $opId === null) {
        $opId = $db->insert(
            'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, created_at) VALUES (?, ?, ?, ?, 1, ?)',
            [$threadId, $authorId, $body, '<p>' . htmlspecialchars($body, ENT_QUOTES, 'UTF-8') . '</p>', $at($createdAgo)],
        );
    }
    $opId = (int) $opId;

    // One real reply, so the reading pane and the row's reply count have a body
    // to render; the counter itself stays the design's number.
    $lastId = $opId;
    if ($replies > 0) {
        $replyId = $db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 0 ORDER BY id DESC LIMIT 1', [$threadId]);
        if ($replyId === false || $replyId === null) {
            $replyBody = 'The scattering is the symptom, not the disease. We have no rule about when a topic stops being a discussion and starts being a record.';
            $replyId = $db->insert(
                'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, created_at) VALUES (?, ?, ?, ?, 0, ?)',
                [$threadId, $userIds['glorfindel'], $replyBody, '<p>' . $replyBody . '</p>', $at($lastAgo)],
            );
        }
        $lastId = (int) $replyId;
        if ($status === 'solved') {
            $db->run('UPDATE threads SET accepted_answer_post_id = ? WHERE id = ?', [$lastId, $threadId]);
        }
    }
    $db->run('UPDATE threads SET last_post_id = ?, last_post_user_id = ? WHERE id = ?', [
        $lastId,
        $lastId === $opId ? $authorId : $userIds['glorfindel'],
        $threadId,
    ]);

    // Commends: reactions on the OP by members other than its author.
    $db->run('DELETE FROM reactions WHERE post_id = ?', [$opId]);
    for ($i = 0; $i < $commends; $i++) {
        $db->run('INSERT INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, ?)', [$opId, $pool[$i], '✦', $at($lastAgo)]);
    }

    // Read state. An absent cursor is unread; a cursor at the last post is read.
    $db->run('DELETE FROM thread_user WHERE user_id = ? AND thread_id = ?', [$me, $threadId]);
    $snooze = $snoozeDays > 0 ? $now->modify("+{$snoozeDays} days")->format('Y-m-d H:i:s') : null;
    $db->run(
        'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred, snoozed_until) VALUES (?, ?, ?, ?, ?)',
        [$me, $threadId, $unread ? null : $lastId, $starred ? 1 : 0, $snooze],
    );

    // The personal signal that puts the topic in For You, and names its reason.
    $db->run("DELETE FROM notifications WHERE user_id = ? AND thread_id = ?", [$me, $threadId]);
    $db->run("DELETE FROM subscriptions WHERE user_id = ? AND target_type = 'thread' AND target_id = ?", [$me, $threadId]);
    $db->run("DELETE FROM thread_assignments WHERE thread_id = ?", [$threadId]);
    switch ($signal) {
        case 'replied':
            $db->run(
                'INSERT INTO notifications (user_id, type, actor_id, thread_id, post_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)',
                [$me, 'reply', $userIds['glorfindel'], $threadId, $lastId, $at($lastAgo)],
            );
            break;
        case 'mentioned':
            $db->run(
                'INSERT INTO notifications (user_id, type, actor_id, thread_id, post_id, is_read, created_at) VALUES (?, ?, ?, ?, ?, 0, ?)',
                [$me, 'mention', $authorId, $threadId, $opId, $at($lastAgo)],
            );
            break;
        case 'watching':
            $db->run(
                "INSERT INTO subscriptions (user_id, target_type, target_id, frequency, created_at) VALUES (?, 'thread', ?, 'instant', ?)",
                [$me, $threadId, $at(600)],
            );
            break;
        case 'assigned':
            $db->run(
                'INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
                [$threadId, $me, $userIds['elrond'], $at($lastAgo)],
            );
            break;
        case 'followtag':
            if ($tagId !== null) {
                $db->run('INSERT IGNORE INTO thread_tags (thread_id, tag_id) VALUES (?, ?)', [$threadId, $tagId]);
                $db->run("INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at) VALUES (?, 'tag', ?, ?)", [$me, $tagId, $at(600)]);
            }
            break;
    }
}

/* One topic opened anonymously, on a board that allows it. The reading pane now
   prints the opening author, so the surface has to be able to prove it masks
   one - and that it does not hand out the rank either. */
$anonBoard = $boardIds['the-valley'];
$db->run('UPDATE boards SET allow_anonymous = 1 WHERE id = ?', [$anonBoard]);
$anonTitle = 'A question I would rather not sign';
$anonThread = $upsert($db, 'threads', ['title' => $anonTitle], [
    'board_id' => $anonBoard,
    'user_id' => $userIds['lindir'],
    'slug' => $slugify($anonTitle),
    'status' => 'needs_answer',
    'reply_count' => 1,
    'created_at' => $at(9),
    'last_post_at' => $at(7),
]);
$anonOp = $db->fetchValue('SELECT id FROM posts WHERE thread_id = ? AND is_op = 1 LIMIT 1', [$anonThread]);
if ($anonOp === false || $anonOp === null) {
    $anonBody = 'Asking without my name on it: when a warden and an author disagree about a record, who decides?';
    $anonOp = $db->insert(
        'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, is_anonymous, created_at) VALUES (?, ?, ?, ?, 1, 1, ?)',
        [$anonThread, $userIds['lindir'], $anonBody, '<p>' . $anonBody . '</p>', $at(9)],
    );
}
$db->run('UPDATE posts SET is_anonymous = 1 WHERE id = ?', [(int) $anonOp]);
$db->run('UPDATE threads SET last_post_id = ?, last_post_user_id = ? WHERE id = ?', [(int) $anonOp, $userIds['lindir'], $anonThread]);
$db->run('DELETE FROM thread_user WHERE user_id = ? AND thread_id = ?', [$me, $anonThread]);
$db->run('INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, NULL, 1)', [$me, $anonThread]);

// Board-level signals: one watched board, one followed board.
$db->run("DELETE FROM subscriptions WHERE user_id = ? AND target_type = 'board'", [$me]);
$db->run("DELETE FROM follows WHERE user_id = ? AND target_type = 'board'", [$me]);
$db->run(
    "INSERT INTO subscriptions (user_id, target_type, target_id, frequency, created_at) VALUES (?, 'board', ?, 'instant', ?)",
    [$me, $boardIds['the-valley'], $at(600)],
);
$db->run(
    "INSERT IGNORE INTO follows (user_id, target_type, target_id, created_at) VALUES (?, 'board', ?, ?)",
    [$me, $boardIds['introductions'], $at(600)],
);

// Engagement predates every topic, so nothing is unread merely by being old.
(new SettingRepository($db))->set('engagement_cutover_at', '2000-01-01 00:00:00');

// The design's default register: the rail and the reading pane both open, rows
// compact — density is an account preference and the queue only states it.
(new UserPreferenceRepository($db))->merge($me, [
    'rail_open' => true,
    'inbox_reading_open' => true,
    'density' => 'compact',
]);
// The onboarding tour paints over the surface being captured, and every
// account this fixture signs in as has already seen the valley.
$db->run('UPDATE users SET onboarded_at = ? WHERE onboarded_at IS NULL', [$at(600)]);

fwrite(STDOUT, "Seeded the design's forum-inbox dataset (sign in as erestor@retro.test).\n");
