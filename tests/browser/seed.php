<?php

declare(strict_types=1);

/**
 * Deterministic seed for the browser-evidence run. Boots the same config the app
 * uses (so it honours DB_DATABASE) and populates a small, realistic community so
 * the Gate A pages have content to capture at desktop + mobile widths.
 *
 * Run against a freshly-migrated database, e.g.:
 *   DB_DATABASE=retroboards_e2e php tests/browser/seed.php
 *
 * Credentials it creates (used by the Playwright spec):
 *   admin@retro.test / password123   (role: admin)
 *   alice@retro.test / password123   (role: user, moderator of #general)
 *   bob@retro.test   / password123   (role: user, member of the private #staff-room)
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadRepository;
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

$db->transaction(function () use ($db): void {
    $db->run("DELETE FROM webhooks WHERE name LIKE 'Evidence webhook (%'");
    $db->run("DELETE FROM service_secrets WHERE owner_type = 'webhook' AND label LIKE 'Webhook signing secret: Evidence webhook (%'");
    $db->run("DELETE FROM api_tokens WHERE name LIKE 'Evidence CI token (%'");
});

$users = new UserRepository($db);
$settings = new SettingRepository($db);
$includeDarkSurfaceFixtures = getenv('RB_BROWSER_DARK_SURFACES') === '1';
$evidenceFeatures = [
    'api_tokens' => true,
    'webhooks' => true,
    'service_secrets' => true,
    'first_party_hooks' => true,
    'announcements' => true,
    'polls' => true,
    'slash_giphy' => true,
    'topic_workflow' => true, // GA default-on (2026-07-01); listed explicitly so the workflow bar is captured regardless of the DEFAULTS map
];
if ($includeDarkSurfaceFixtures) {
    $evidenceFeatures['appeals'] = true;
    $evidenceFeatures['server_drafts'] = true;
    $evidenceFeatures['server_extensions'] = true;
}
$ensureNewSurfaceFixtures = static function () use ($db, $users): bool {
    $admin = $users->findByUsername('admin');
    $bob = $users->findByUsername('bob');
    if ($admin === null || $bob === null) {
        return false;
    }

    $bobReply = $db->fetch(
        'SELECT id FROM posts WHERE user_id = ? AND body = ? ORDER BY id ASC LIMIT 1',
        [(int) $bob['id'], 'Mine is jumping straight to the inbox.'],
    );
    if ($bobReply !== null) {
        $postId = (int) $bobReply['id'];
        $db->run(
            'UPDATE posts
                SET is_deleted = 1, deleted_at = COALESCE(deleted_at, UTC_TIMESTAMP()), deleted_by = ?
              WHERE id = ?',
            [(int) $admin['id'], $postId],
        );
        $hasDeleteLog = $db->fetchValue(
            "SELECT 1 FROM moderation_log WHERE target_type = 'post' AND target_id = ? AND action = 'delete_post' LIMIT 1",
            [$postId],
        );
        if ($hasDeleteLog === false) {
            $db->run(
                "INSERT INTO moderation_log (actor_id, action, target_type, target_id, reason, created_at)
                 VALUES (?, 'delete_post', 'post', ?, 'browser evidence appeal fixture', UTC_TIMESTAMP())",
                [(int) $admin['id'], $postId],
            );
        }
    }

    $db->run(
        'INSERT INTO server_drafts
           (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
         VALUES (?, ?, 1, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY))
         ON DUPLICATE KEY UPDATE
           title = VALUES(title),
           body = VALUES(body),
           metadata = VALUES(metadata),
           updated_at = UTC_TIMESTAMP(),
           expires_at = VALUES(expires_at)',
        [
            (int) $bob['id'],
            'browser-a11y-reply',
            'Saved reply draft',
            'A server-side draft used by the accessibility evidence run.',
            json_encode(['path' => '/t/browser-evidence'], JSON_THROW_ON_ERROR),
        ],
    );

    $db->run(
        "INSERT INTO packages (package_uid, name, type, trust_class, created_at, updated_at)
         VALUES ('local.browser.extension', 'Browser Evidence Extension', 'server_extension', 'isolated_server', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = UTC_TIMESTAMP()",
    );
    $packageId = (int) $db->fetchValue("SELECT id FROM packages WHERE package_uid = 'local.browser.extension'");
    $db->run(
        "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
         VALUES (?, REPEAT('c', 64), 'isolated_server', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           digest = VALUES(digest),
           trust_class = VALUES(trust_class),
           review_status = VALUES(review_status),
           state = VALUES(state),
           updated_at = UTC_TIMESTAMP()",
        [$packageId, (int) $admin['id']],
    );
    $installedId = (int) $db->fetchValue('SELECT id FROM installed_packages WHERE package_id = ?', [$packageId]);
    $db->run(
        'INSERT INTO server_extension_handlers
           (installed_package_id, handler_key, entrypoint, events_json, jobs_json, permissions_json,
            resource_limits_json, storage_quota_bytes, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'enabled\', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           entrypoint = VALUES(entrypoint),
           events_json = VALUES(events_json),
           jobs_json = VALUES(jobs_json),
           permissions_json = VALUES(permissions_json),
           resource_limits_json = VALUES(resource_limits_json),
           storage_quota_bytes = VALUES(storage_quota_bytes),
           status = VALUES(status),
           updated_at = UTC_TIMESTAMP()',
        [
            $installedId,
            'browser-evidence',
            'extension.php',
            json_encode(['topic.created'], JSON_THROW_ON_ERROR),
            json_encode(['refresh-related'], JSON_THROW_ON_ERROR),
            json_encode(['broker' => [], 'outbound_hosts' => []], JSON_THROW_ON_ERROR),
            json_encode(['time_ms' => 1000, 'memory_mb' => 64, 'cpu_ms' => 500, 'output_kb' => 64, 'disk_kb' => 512], JSON_THROW_ON_ERROR),
            262144,
        ],
    );

    return true;
};
$ensureShortcutPoll = static function () use ($db, $users): bool {
    $thread = $db->fetch(
        "SELECT t.id
           FROM threads t
           JOIN boards b ON b.id = t.board_id
          WHERE b.slug = 'general' AND t.title = ?
          LIMIT 1",
        ['Share your favourite keyboard shortcuts'],
    );
    $alice = $users->findByUsername('alice');
    if ($thread === null || $alice === null) {
        return false;
    }

    $pollId = $db->fetchValue(
        'SELECT id FROM polls WHERE thread_id = ? LIMIT 1',
        [(int) $thread['id']],
    );
    if ($pollId === false) {
        $pollId = $db->insert(
            "INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at)
             VALUES (?, ?, 'single', 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())",
            [(int) $thread['id'], 'Which shortcut do you reach for first?', (int) $alice['id']],
        );
    }
    $pollId = (int) $pollId;

    foreach ([0 => 'Ctrl+K', 1 => 'Jump to inbox'] as $position => $body) {
        $exists = $db->fetchValue(
            'SELECT 1 FROM poll_options WHERE poll_id = ? AND body = ? LIMIT 1',
            [$pollId, $body],
        );
        if ($exists === false) {
            $db->run(
                'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [$pollId, $body, $position],
            );
        }
    }
    $db->run('DELETE FROM poll_votes WHERE poll_id = ?', [$pollId]);

    return true;
};
if ($users->adminCount() > 0) {
    $settings->set('features', $evidenceFeatures);
    $settings->set('giphy_public_key', 'browser-evidence-key');
    $settings->set('giphy_rating', 'pg');
    $pollReady = $ensureShortcutPoll();
    $newSurfaceFixturesReady = $includeDarkSurfaceFixtures ? $ensureNewSurfaceFixtures() : false;
    fwrite(STDOUT, $pollReady
        ? ($newSurfaceFixturesReady
            ? "Already seeded (admin exists); refreshed feature flags, poll fixture, and dark-surface fixtures.\n"
            : "Already seeded (admin exists); refreshed feature flags and poll fixture.\n")
        : "Already seeded (admin exists); refreshed feature flags.\n");
    exit(0);
}

$hasher = new PasswordHasher();
$categories = new CategoryRepository($db);
$boards = new BoardRepository($db);
$mods = new BoardModeratorRepository($db);
$members = new BoardMemberRepository($db);

$posting = new PostingService(
    $db,
    new ThreadRepository($db),
    new PostRepository($db),
    new BoardRepository($db),
    new UserRepository($db),
    new Markdown(new HtmlSanitizer()),
    new WriteGate(),
    new BoardPolicy(),
    $config,
);

$makeUser = static function (string $username, string $display, string $role) use ($users, $hasher): int {
    $id = $users->create([
        'username' => $username,
        'email' => $username . '@retro.test',
        'password_hash' => $hasher->hash('password123'),
        'display_name' => $display,
        'role' => $role,
        'status' => 'active',
    ]);
    $users->markEmailVerified($id);
    return $id;
};

$db->transaction(function () use ($db, $settings, $categories, $boards, $mods, $members, $posting, $users, $makeUser, $evidenceFeatures): void {
    // Site settings — past the first-run setup gate.
    $settings->set('site_name', 'RetroBoards');
    $settings->set('registration_mode', 'open');
    $settings->set('installed_at', gmdate('Y-m-d H:i:s'));
    $settings->set('features', $evidenceFeatures); // B2/admin pages + announcements + carryover evidence
    $settings->set('giphy_public_key', 'browser-evidence-key');
    $settings->set('giphy_rating', 'pg');

    // Accounts.
    $adminId = $makeUser('admin', 'Site Admin', 'admin');
    $aliceId = $makeUser('alice', 'Alice Avery', 'user');
    $bobId   = $makeUser('bob', 'Bob Brooks', 'user');
    $carolId = $makeUser('carol', 'Carol Chen', 'user');

    // Categories + boards (one private board to exercise membership).
    $welcome = $categories->create('Welcome', 0);
    $community = $categories->create('Community', 1);

    $announce = $boards->create([
        'category_id' => $welcome, 'slug' => 'announcements', 'name' => 'Announcements',
        'description' => 'News and updates from the team.', 'visibility' => 'public', 'post_min_role' => 'user',
    ]);
    $general = $boards->create([
        'category_id' => $community, 'slug' => 'general', 'name' => 'General',
        'description' => 'Talk about anything.', 'visibility' => 'public', 'post_min_role' => 'user', 'allow_anonymous' => 1,
        'assignment_mode' => 'staff', // opt #general into topic assignment so the workflow assign control renders for staff (alice)
    ]);
    $boards->create([
        'category_id' => $community, 'slug' => 'feedback', 'name' => 'Feedback',
        'description' => 'Ideas and suggestions.', 'visibility' => 'public', 'post_min_role' => 'user',
    ]);
    $staff = $boards->create([
        'category_id' => $welcome, 'slug' => 'staff-room', 'name' => 'Staff Room',
        'description' => 'Private board for the team.', 'visibility' => 'private', 'post_min_role' => 'user',
    ]);

    // Roster: showcases the admin board-edit roster UI on /admin/boards/{general}/edit.
    $mods->assign($general, $aliceId);
    $members->add($staff, $bobId, $adminId);

    // Threads + replies so boards and thread pages render real content.
    $welcomeThread = $posting->createThread($users->findEntity($adminId), [
        'board_id' => $announce, 'title' => 'Welcome to RetroBoards',
        'body' => "We're glad you're here. Introduce yourself and explore the boards.\n\nThis community runs entirely without JavaScript — every action works as a plain form submit.",
    ]);
    $posting->reply($users->findEntity($aliceId), $welcomeThread['thread_id'], [
        'body' => 'Thanks for setting this up! Looking forward to the discussions.',
    ]);

    $tipsThread = $posting->createThread($users->findEntity($aliceId), [
        'board_id' => $general, 'title' => 'Share your favourite keyboard shortcuts',
        'body' => "What are the shortcuts you can't live without? I'll start: **Ctrl+K** for search.",
    ]);
    $posting->reply($users->findEntity($bobId), $tipsThread['thread_id'], ['body' => 'Mine is jumping straight to the inbox.']);
    $posting->reply($users->findEntity($carolId), $tipsThread['thread_id'], ['body' => 'I just use the sidebar, honestly.']);
    $pollId = $db->insert(
        "INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at)
         VALUES (?, ?, 'single', 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())",
        [$tipsThread['thread_id'], 'Which shortcut do you reach for first?', $aliceId],
    );
    $db->run(
        'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, 0, UTC_TIMESTAMP())',
        [$pollId, 'Ctrl+K'],
    );
    $db->run(
        'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())',
        [$pollId, 'Jump to inbox'],
    );

    $posting->createThread($users->findEntity($bobId), [
        'board_id' => $general, 'title' => 'Mobile layout looks great',
        'body' => 'Tested on my phone today — tap targets are comfortable and nothing overflows.',
    ]);
});

if ($includeDarkSurfaceFixtures) {
    $ensureNewSurfaceFixtures();
}

fwrite(STDOUT, "Seeded RetroBoards e2e content.\n");
