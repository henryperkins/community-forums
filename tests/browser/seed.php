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
if ($users->adminCount() > 0) {
    $settings->set('features', [
        'api_tokens' => true,
        'webhooks' => true,
        'service_secrets' => true,
        'first_party_hooks' => true,
        'announcements' => true,
        'polls' => true,
    ]);
    fwrite(STDOUT, "Already seeded (admin exists); nothing to do.\n");
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

$db->transaction(function () use ($db, $settings, $categories, $boards, $mods, $members, $posting, $users, $makeUser): void {
    // Site settings — past the first-run setup gate.
    $settings->set('site_name', 'RetroBoards');
    $settings->set('registration_mode', 'open');
    $settings->set('installed_at', gmdate('Y-m-d H:i:s'));
    $settings->set('features', [
        'api_tokens' => true,
        'webhooks' => true,
        'service_secrets' => true,
        'first_party_hooks' => true,
        'announcements' => true,
        'polls' => true,
    ]); // B2 admin pages + domain webhook evidence + announcements + carryover poll UI

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

fwrite(STDOUT, "Seeded RetroBoards e2e content.\n");
