<?php

declare(strict_types=1);

/**
 * The thread view — /t/{id} — seeded with the design's own topic.
 *
 * templates/thread-view/ThreadView.dc.html ships one Imladris topic that
 * exercises every control surface at once: workflow status plus its history, an
 * assignment, tags, a poll, a living brief with three versions, an accepted
 * answer, a grouped reply, an anonymous post, reactions, a signature, a
 * referenced post and a link preview. `thread-data.js` is the whole dataset and
 * this file reproduces it row for row.
 *
 * Reproducing that set is what makes a side-by-side render meaningful: a topic
 * with three plain replies and nothing attached cannot disagree with its design,
 * and four of ADR 0028/0029's findings were hiding in exactly the states a thin
 * capture never reaches.
 *
 * Run after tests/browser/prepare.sh, against the same DB_DATABASE:
 *   DB_DATABASE=retroboards_e2e php tests/browser/thread-view-fixture.php
 *
 * Prints the topic path. Sign in as elladan@retro.test (member, wrote two of the
 * replies) or elrond@retro.test (the board's warden) / password123.
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Security\PasswordHasher;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$markdown = new Markdown(new HtmlSanitizer());

$hash = (new PasswordHasher())->hash('password123');
$now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$at = static fn (string $modifier): string => $now->modify($modifier)->format('Y-m-d H:i:s');

/** @return int the id of an existing-or-inserted row */
$upsert = static function (Database $db, string $table, array $unique, array $values): int {
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
// title + reputation are what the byline chip and the regard plinth read; the
// signature is what the OP's signature block reads. thread-data.js VIEWERS and
// POSTS between them fix every one of these values.
$people = [
    // username      display       title         role         rep    signature
    'erestor'    => ['Erestor',    'Loremaster', 'user',      3940, "— Erestor, of the household of Elrond\n“The record outlives the argument.”"],
    'glorfindel' => ['Glorfindel', 'Veteran',    'moderator', 2140, ''],
    'lindir'     => ['Lindir',     'Member',     'user',      180,  ''],
    'elladan'    => ['Elladan',    'Member',     'user',      310,  ''],
    'arwen'      => ['Arwen',      'Legend',     'user',      5210, '— Arwen · Evenstar of her people'],
    'elrond'     => ['Elrond',     'Loremaster', 'moderator', 4820, ''],
];
$userIds = [];
foreach ($people as $username => [$display, $title, $role, $rep, $signature]) {
    $userIds[$username] = $upsert($db, 'users', ['username' => $username], [
        'email' => $username . '@retro.test',
        'password_hash' => $hash,
        'display_name' => $display,
        'title' => $title,
        'role' => $role,
        'status' => 'active',
        'email_verified_at' => $at('-600 hours'),
        'onboarded_at' => $at('-600 hours'),
        'created_at' => $at('-600 hours'),
        'show_presence' => 1,
        'last_seen_at' => $at('-1 hours'),
    ]);
    $db->run(
        'UPDATE users SET display_name = ?, title = ?, role = ?, reputation = ?, signature = ? WHERE id = ?',
        [$display, $title, $role, $rep, $signature, $userIds[$username]],
    );
}

// Appearance is a stored preference, so a run that ended in the twilight register
// would hand the next one a dark page it never asked for. The fixture is the
// reset: every capture starts from the account defaults.
if ($userIds !== []) {
    $place = implode(',', array_fill(0, count($userIds), '?'));
    $db->run("DELETE FROM user_preferences WHERE user_id IN ($place)", array_values($userIds));
}

// A pool the reaction counts are drawn from: a reaction is one row per member,
// so a post cannot be commended more times than the council has members.
$pool = [];
for ($i = 1; $i <= 20; $i++) {
    $pool[] = $upsert($db, 'users', ['username' => 'council' . $i], [
        'email' => 'council' . $i . '@retro.test',
        'password_hash' => $hash,
        'display_name' => 'Council member ' . $i,
        'role' => 'user',
        'status' => 'active',
        'email_verified_at' => $at('-600 hours'),
        'created_at' => $at('-600 hours'),
        'show_presence' => 0,
    ]);
}

// ── board ───────────────────────────────────────────────────────────────────
$catId = $upsert($db, 'categories', ['name' => 'The Commons'], ['position' => 10]);
$boardId = $upsert($db, 'boards', ['slug' => 'the-archive'], [
    'category_id' => $catId,
    'name' => 'The Archive',
    'description' => 'Where the council keeps what it has decided.',
    'position' => 0,
    'visibility' => 'public',
    'created_at' => $at('-600 hours'),
]);
$db->run('UPDATE boards SET category_id = ?, name = ?, wiki_enabled = 1, tags_enabled = 1 WHERE id = ?', [$catId, 'The Archive', $boardId]);
// Two more, so "Move to board" has real destinations (BOARDS_MOVABLE).
foreach (['the-hall-of-fire' => 'The Hall of Fire', 'the-healing-halls' => 'The Healing Halls'] as $slug => $name) {
    $id = $upsert($db, 'boards', ['slug' => $slug], [
        'category_id' => $catId,
        'name' => $name,
        'description' => '',
        'position' => 1,
        'visibility' => 'public',
        'created_at' => $at('-600 hours'),
    ]);
    $db->run('UPDATE boards SET name = ? WHERE id = ?', [$name, $id]);
}
// Elrond and Glorfindel tend this board (WARDENS); Elrond is the assignee.
foreach (['elrond', 'glorfindel'] as $warden) {
    $db->run(
        'INSERT IGNORE INTO board_moderators (board_id, user_id) VALUES (?, ?)',
        [$boardId, $userIds[$warden]],
    );
}

// ── the topic ───────────────────────────────────────────────────────────────
$title = 'Where should ratified decisions live once the council has spoken?';
$slug = 'ratified-decisions';
$threadId = $upsert($db, 'threads', ['slug' => $slug], [
    'board_id' => $boardId,
    'user_id' => $userIds['erestor'],
    'title' => $title,
    'created_at' => $at('-56 hours'),
]);
$db->run(
    'UPDATE threads SET board_id = ?, user_id = ?, title = ?, status = ?, status_changed_at = ?, status_changed_by = ?, is_pinned = 0, is_locked = 0 WHERE id = ?',
    [$boardId, $userIds['erestor'], $title, 'solved', $at('-8 hours'), $userIds['elrond'], $threadId],
);
$db->run('DELETE FROM posts WHERE thread_id = ?', [$threadId]);

// ── the posts ───────────────────────────────────────────────────────────────
// Bodies are Markdown, exactly as a member would have typed them: the design's
// `paras`, `list`, `after` and `quote` fields are one post's body between them,
// and body_html comes from the product's own renderer so nothing here can drift
// from what a real post looks like.
$posts = [
    [
        'key' => 'op', 'author' => 'erestor', 'op' => true, 'anon' => false, 'when' => '-56 hours',
        'body' => <<<'MD'
            Every council here ends the same way: a verdict is spoken, heads nod, and the topic scrolls on. A season later somebody asks what we decided about lantern-oil rationing, and we spend an evening excavating.

            Three failures, plainly:

            - Verdicts live in whichever topic hosted the argument — findable only by those who were there.
            - The wiki holds three of our last eleven decisions, each written in a different form.
            - Nothing records which decision supersedes which.

            Before I propose ritual, I would hear the keep: where should a ratified decision live, and who tends it?

            https://imladris.council/the-charter
            MD,
        'reactions' => ['👍' => 4, '🔥' => 2],
    ],
    [
        'key' => 'glorfindel', 'author' => 'glorfindel', 'op' => false, 'anon' => false, 'when' => '-54 hours',
        'body' => <<<'MD'
            > Nothing records which decision supersedes which.

            This is the sharp end. The guard solved it years ago for watch-orders: every standing order carries the name of the order it replaces, and the replaced one is struck through within the hour. Two rules, kept forever.

            I would copy that discipline before we argue about rooms and shelves.
            MD,
        'reactions' => ['💯' => 3],
    ],
    [
        'key' => 'anon', 'author' => 'lindir', 'op' => false, 'anon' => true, 'when' => '-32 hours',
        'body' => 'As one who missed two verdicts last season while away at the fords: whatever we choose, let it be one place. I do not care which. I care that returning after a month does not require an archaeology of six topics.',
        'reactions' => ['👍' => 2],
    ],
    [
        'key' => 'elladan1', 'author' => 'elladan', 'op' => false, 'anon' => false, 'when' => '-24 hours',
        'body' => 'Seconding the single-place rule. Could the board index itself carry the latest verdicts? The rail already shows unread counts — a small ledger line under each board name would do.',
        'reactions' => [],
    ],
    [
        'key' => 'elladan2', 'author' => 'elladan', 'op' => false, 'anon' => false, 'when' => '-23 hours -54 minutes',
        'body' => '(And if the ledger line linked straight to the verdict post, not the topic head, better still.)',
        'reactions' => [],
    ],
    [
        'key' => 'arwen', 'author' => 'arwen', 'op' => false, 'anon' => false, 'when' => '-8 hours',
        'body' => <<<'MD'
            Let the decision be an artifact, not a memory. When a council concludes, the closer writes a verdict post in a fixed form and pins it to a Decisions topic — one per board, tended by the wardens:

            - The verdict itself, one paragraph, dated and signed.
            - What it replaces, struck through and linked — Glorfindel's discipline.
            - Where the argument lived, so the reasoning is never lost.

            The wiki then holds only the index of verdicts. One place to look, one form to trust, and the reasoning a link away.
            MD,
        'reactions' => ['👍' => 12, '🎉' => 5],
    ],
];

$postIds = [];
foreach ($posts as $p) {
    $body = preg_replace('/^ {12}/m', '', $p['body']) ?? $p['body'];
    $id = $db->insert(
        'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, is_anonymous, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
        [
            $threadId,
            $userIds[$p['author']],
            $body,
            $markdown->render($body, ['link_mentions' => true]),
            $p['op'] ? 1 : 0,
            $p['anon'] ? 1 : 0,
            $at($p['when']),
        ],
    );
    $postIds[$p['key']] = $id;
    $i = 0;
    foreach ($p['reactions'] as $emoji => $n) {
        for ($k = 0; $k < $n; $k++) {
            $db->run(
                'INSERT IGNORE INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, ?)',
                [$id, $pool[$i++ % count($pool)], $emoji, $at($p['when'])],
            );
        }
    }
}
// Elladan has already commended the accepted answer, so the viewer's own
// reaction state renders as pressed rather than as one more anonymous count.
$db->run(
    'INSERT IGNORE INTO reactions (post_id, user_id, emoji, created_at) VALUES (?, ?, ?, ?)',
    [$postIds['arwen'], $userIds['elladan'], '👍', $at('-7 hours')],
);

$lastId = $postIds['arwen'];
$replies = count($posts) - 1;
$db->run(
    'UPDATE threads SET reply_count = ?, last_post_id = ?, last_post_user_id = ?, last_post_at = ?, accepted_answer_post_id = ? WHERE id = ?',
    [$replies, $lastId, $userIds['arwen'], $at('-8 hours'), $postIds['arwen'], $threadId],
);

// ── the link preview on the opening post ────────────────────────────────────
$db->run("DELETE FROM link_previews WHERE source_type = 'post' AND source_id = ?", [$postIds['op']]);
$db->run(
    "INSERT INTO link_previews (source_type, source_id, url, url_hash, final_url, status, title, description, site_name, http_status, fetched_at, created_at)
     VALUES ('post', ?, ?, ?, ?, 'fetched', ?, ?, ?, 200, ?, ?)",
    [
        $postIds['op'],
        'https://imladris.council/the-charter',
        hash('sha256', 'https://imladris.council/the-charter'),
        'https://imladris.council/the-charter',
        'A charter for keeping counsel',
        'Status is verified, not asserted. Outcomes resolve into artifacts. Testimony never outranks the work.',
        'imladris.council',
        $at('-55 hours'),
        $at('-55 hours'),
    ],
);

// ── the referenced topic on Glorfindel's reply ──────────────────────────────
$refBoardId = $upsert($db, 'boards', ['slug' => 'interpretability'], [
    'category_id' => $catId,
    'name' => 'Interpretability',
    'description' => '',
    'position' => 2,
    'visibility' => 'public',
    'created_at' => $at('-600 hours'),
]);
$refThreadId = $upsert($db, 'threads', ['slug' => 'attention-as-a-map'], [
    'board_id' => $refBoardId,
    'user_id' => $userIds['arwen'],
    'title' => 'Reading attention as a map, not a verdict',
    'created_at' => $at('-400 hours'),
]);
if ($db->fetchValue('SELECT 1 FROM posts WHERE thread_id = ? LIMIT 1', [$refThreadId]) === false) {
    $refBody = 'Attention tells you where the model looked, not what it concluded.';
    $db->insert(
        'INSERT INTO posts (thread_id, user_id, body, body_html, is_op, created_at) VALUES (?, ?, ?, ?, 1, ?)',
        [$refThreadId, $userIds['arwen'], $refBody, $markdown->render($refBody), $at('-400 hours')],
    );
    $db->run('UPDATE threads SET reply_count = 17 WHERE id = ?', [$refThreadId]);
}
$db->run("DELETE FROM content_references WHERE source_type = 'post' AND source_id = ?", [$postIds['glorfindel']]);
$db->run(
    "INSERT INTO content_references (source_type, source_id, target_type, target_id, token, resolved_at, created_at)
     VALUES ('post', ?, 'thread', ?, ?, ?, ?)",
    [$postIds['glorfindel'], $refThreadId, '/t/' . $refThreadId . '-attention-as-a-map', $at('-54 hours'), $at('-54 hours')],
);

// ── tags ────────────────────────────────────────────────────────────────────
foreach (['governance', 'records', 'precedent', 'ritual', 'lore-keeping'] as $tagSlug) {
    $tagId = $upsert($db, 'tags', ['slug' => $tagSlug], [
        'name' => $tagSlug,
        'is_enabled' => 1,
        'created_by' => $userIds['elrond'],
        'created_at' => $at('-600 hours'),
    ]);
    if (in_array($tagSlug, ['governance', 'records'], true)) {
        $db->run(
            'INSERT IGNORE INTO thread_tags (thread_id, tag_id, added_by, created_at) VALUES (?, ?, ?, ?)',
            [$threadId, $tagId, $userIds['elrond'], $at('-50 hours')],
        );
    }
}

// ── standing history, assignment, watch ─────────────────────────────────────
$db->run('DELETE FROM thread_status_history WHERE thread_id = ?', [$threadId]);
foreach ([
    ['needs_answer', 'open', 'glorfindel', '-54 hours', ''],
    ['solved', 'needs_answer', 'elrond', '-8 hours', 'Accepted Arwen’s proposal'],
] as [$to, $from, $actor, $when, $reason]) {
    $db->run(
        'INSERT INTO thread_status_history (thread_id, actor_id, previous_status, new_status, reason, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$threadId, $userIds[$actor], $from, $to, $reason, $at($when)],
    );
}
$db->run('DELETE FROM thread_assignments WHERE thread_id = ?', [$threadId]);
$db->run(
    'INSERT INTO thread_assignments (thread_id, assigned_user_id, assigned_by, assigned_at) VALUES (?, ?, ?, ?)',
    [$threadId, $userIds['elrond'], $userIds['elrond'], $at('-50 hours')],
);
$db->run("DELETE FROM subscriptions WHERE target_type = 'thread' AND target_id = ?", [$threadId]);
foreach (['elladan', 'elrond'] as $watcher) {
    $db->run(
        "INSERT INTO subscriptions (user_id, target_type, target_id, email_enabled, in_app_enabled, frequency, created_at) VALUES (?, 'thread', ?, 1, 1, 'instant', ?)",
        [$userIds[$watcher], $threadId, $at('-50 hours')],
    );
}
// Elladan starred the topic and last read his own second reply, so Arwen's
// accepted answer is the one unread reply — the design's unread set exactly
// (its own reply is never news to a reader).
$db->run('DELETE FROM thread_user WHERE thread_id = ?', [$threadId]);
$db->run(
    'INSERT INTO thread_user (user_id, thread_id, last_read_post_id, is_starred) VALUES (?, ?, ?, 1)',
    [$userIds['elladan'], $threadId, $postIds['elladan2']],
);

// ── the living brief ────────────────────────────────────────────────────────
$db->run(
    'DELETE FROM thread_summary_sources WHERE summary_id IN (SELECT id FROM thread_summaries WHERE thread_id = ?)',
    [$threadId],
);
$db->run('DELETE FROM thread_summaries WHERE thread_id = ?', [$threadId]);
$versions = [
    [1, 'retired', 'ai', 'Erestor asks where a ratified decision should live. Early replies favour a single place over the topic that hosted the argument.', '-264 hours'],
    [2, 'retired', 'manual', 'Two shapes are on the table: a pinned Decisions topic per board, or one wiki page per season. The council has not chosen, but agrees a verdict must name what it replaces.', '-120 hours'],
    [3, 'published', 'ai', 'The council is converging on treating each verdict as a standalone artifact — a short written decision with its precedence rule attached — kept in a pinned Decisions topic per board. The wiki would hold only the index.', '-7 hours'],
];
$summaryIds = [];
foreach ($versions as [$v, $status, $kind, $body, $when]) {
    $summaryIds[$v] = $db->insert(
        'INSERT INTO thread_summaries (thread_id, kind, status, body, body_html, version, author_id, published_at, retired_at, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $threadId,
            $kind,
            $status,
            $body,
            $markdown->render($body),
            $v,
            $kind === 'manual' ? $userIds['elrond'] : null,
            $at($when),
            $status === 'retired' ? $at($when) : null,
            $at($when),
            $at($when),
        ],
    );
}
$sourcePostIds = [$postIds['glorfindel'], $postIds['arwen']];
foreach ($sourcePostIds as $sourcePostId) {
    $db->run(
        'INSERT IGNORE INTO thread_summary_sources (summary_id, post_id) VALUES (?, ?)',
        [$summaryIds[3], $sourcePostId],
    );
}
// The generation that published v3. ThreadIntelligenceViewService suppresses an
// AI brief whose publishing generation is missing or whose recorded sources no
// longer match the live ones — without this row the brief is withheld and the
// topic renders as though it had never had one.
$db->run('DELETE FROM thread_intelligence_generations WHERE thread_id = ?', [$threadId]);
$db->run(
    "INSERT INTO thread_intelligence_generations
        (thread_id, trigger_code, status, retry_number, window_number, published_summary_id,
         source_snapshot_hash, source_post_ids, request_fingerprint, prompt_version, model,
         requested_at, completed_at, published_at)
     VALUES (?, 'activity', 'published', 0, 3, ?, ?, ?, ?, 'v1', 'claude-opus-5', ?, ?, ?)",
    [
        $threadId,
        $summaryIds[3],
        hash('sha256', 'thread-view-fixture:' . $threadId),
        json_encode(array_map('intval', $sourcePostIds), JSON_THROW_ON_ERROR),
        hash('sha256', 'thread-view-fixture-request:' . $threadId),
        $at('-7 hours'),
        $at('-7 hours'),
        $at('-7 hours'),
    ],
);
$db->run('DELETE FROM thread_intelligence_jobs WHERE thread_id = ?', [$threadId]);
$db->run(
    "INSERT INTO thread_intelligence_jobs (thread_id, state, trigger_code, automation_paused, last_generated_at, last_processed_post_id, created_at, updated_at)
     VALUES (?, 'idle', 'activity', 0, ?, ?, ?, ?)",
    [$threadId, $at('-7 hours'), $postIds['arwen'], $at('-7 hours'), $at('-7 hours')],
);

// ── the poll ────────────────────────────────────────────────────────────────
$db->run('DELETE FROM poll_votes WHERE poll_id IN (SELECT id FROM polls WHERE thread_id = ?)', [$threadId]);
$db->run('DELETE FROM poll_options WHERE poll_id IN (SELECT id FROM polls WHERE thread_id = ?)', [$threadId]);
$db->run('DELETE FROM polls WHERE thread_id = ?', [$threadId]);
$pollId = $db->insert(
    "INSERT INTO polls (thread_id, question, mode, status, created_by, created_at) VALUES (?, ?, 'single', 'open', ?, ?)",
    [$threadId, 'Where should ratified decisions live?', $userIds['erestor'], $at('-56 hours')],
);
$pollVotes = [
    ['A pinned Decisions topic per board', 14],
    ['The board wiki, one page per season', 9],
    ['A quarterly ledger post', 4],
];
$voter = 0;
foreach ($pollVotes as $position => [$body, $votes]) {
    $optionId = $db->insert(
        'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, ?, ?)',
        [$pollId, $body, $position, $at('-56 hours')],
    );
    for ($k = 0; $k < $votes; $k++) {
        $db->run(
            'INSERT IGNORE INTO poll_votes (poll_id, option_id, user_id, created_at) VALUES (?, ?, ?, ?)',
            [$pollId, $optionId, $pool[$voter++ % count($pool)], $at('-50 hours')],
        );
    }
}

// ── related topics ──────────────────────────────────────────────────────────
// The Related row after the stream reads the brief's own overlay when there is
// one and the deterministic rows when there is not, so the seeded row is both:
// tag-derived, and AI-selected against the generation that published v3.
$db->run('DELETE FROM related_threads WHERE source_thread_id = ?', [$threadId]);
$generationId = (int) $db->fetchValue(
    'SELECT id FROM thread_intelligence_generations WHERE thread_id = ? ORDER BY id DESC LIMIT 1',
    [$threadId],
);
$db->run(
    "INSERT INTO related_threads
        (source_thread_id, related_thread_id, relation_type, source, score, reason, status, curator_id, created_at,
         ai_generation_id, ai_reason, ai_selected, ai_selected_at)
     VALUES (?, ?, 'related', 'tag', 0.820, 'Shares the governance tag', 'approved', ?, ?, ?, ?, 1, ?)",
    [
        $threadId, $refThreadId, $userIds['elrond'], $at('-7 hours'),
        $generationId, 'Both topics argue about where a verdict lives', $at('-7 hours'),
    ],
);

echo '/t/' . $threadId . '-' . $slug . "\n";
