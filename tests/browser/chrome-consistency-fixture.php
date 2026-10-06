<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Domain\User;
use App\Repository\BoardFolderRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;
use App\Security\BoardPolicy;
use App\Security\WriteGate;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$database = (string) $config->get('db.database');
if (!preg_match('/^retroboards_(?:e2e(?:_[a-z0-9_]+)?|chrome_fix_browser_[0-9]{8})$/', $database)) {
    throw new RuntimeException('Chrome fixtures require an isolated browser database.');
}
$db = new Database($config->get('db'));
$fixture = $db->transaction(function () use ($db, $config): array {
    $users = new UserRepository($db);
    $viewer = $users->findByUsername('chrome_reader');
    $uid = $viewer === null ? $users->create([
        'username' => 'chrome_reader', 'email' => 'chrome-reader@retro.test',
        'display_name' => 'Chrome reader', 'password_hash' => (new PasswordHasher())->hash('password123'),
    ]) : (int) $viewer['id'];
    $db->run('UPDATE users SET onboarded_at=UTC_TIMESTAMP() WHERE id=?', [$uid]);
    $boards = new BoardRepository($db);
    $board = $db->fetch("SELECT * FROM boards WHERE slug='chrome-place'");
    if ($board === null) {
        $cid = (new CategoryRepository($db))->create('Chrome regression');
        $bid = $boards->create(['category_id' => $cid, 'slug' => 'chrome-place', 'name' => 'Chrome place', 'visibility' => 'public']);
        $board = $boards->find($bid);
    }
    $bid = (int) $board['id'];
    $folders = new BoardFolderRepository($db);
    $fid = (int) ($db->fetchValue("SELECT id FROM board_folders WHERE user_id=? AND name='My chrome places'", [$uid])
        ?: $folders->create($uid, 'My chrome places'));
    $folders->addBoard($fid, $bid);
    $thread = $db->fetch('SELECT * FROM threads WHERE board_id=? ORDER BY id LIMIT 1', [$bid]);
    if ($thread === null) {
        $posting = new PostingService($db, new ThreadRepository($db), new PostRepository($db), $boards,
            $users, new Markdown(new HtmlSanitizer()), new WriteGate(), new BoardPolicy(), $config);
        $created = $posting->createThread(User::fromRow($users->findByUsername('bob')), [
            'board_id' => $bid, 'title' => str_repeat('UnbrokenTitle', 12), 'body' => 'Chrome regression body.',
        ]);
        $thread = $db->fetch('SELECT * FROM threads WHERE id=?', [$created['thread_id']]);
    }
    $tid = (int) $thread['id'];
    (new ThreadUserRepository($db))->markUnread($uid, $tid);
    (new SubscriptionRepository($db))->set($uid, 'thread', $tid, true, false, 'instant');
    (new UserPreferenceRepository($db))->merge($uid, ['theme' => 'light', 'rail_open' => true, 'inbox_reading_open' => true]);
    if ((new TagRepository($db))->findBySlug('chrome-tag') === null) {
        (new TagRepository($db))->create('chrome-tag', 'Chrome tag', null, $uid);
    }
    return ['user' => $uid, 'board' => $bid, 'thread' => $tid, 'topic' => '/t/' . $tid . '-' . $thread['slug']];
});
fwrite(STDOUT, json_encode($fixture, JSON_THROW_ON_ERROR) . PHP_EOL);
