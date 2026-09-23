<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Repository\SettingRepository;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$config = Config::fromFile(dirname(__DIR__, 2) . '/config/config.php');
if (!in_array($config->get('db.database'), ['retroboards_upload_http', 'retroboards_upload_browser'], true)) {
    throw new RuntimeException('Upload fixtures require their dedicated disposable database.');
}
$db = new Database($config->get('db'));
$operation = $argv[1] ?? 'rich';
if ($operation === 'seed') {
    if ((int) $db->fetchValue('SELECT COUNT(*) FROM users') !== 0) {
        throw new RuntimeException('Upload seed requires its freshly migrated disposable database.');
    }
    \App\Security\PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $users = new \App\Repository\UserRepository($db);
    $ids = [];
    foreach (['admin' => 'admin', 'alice' => 'user', 'bob' => 'user'] as $name => $role) {
        $ids[$name] = $users->create(['username' => $name, 'email' => $name . '@retro.test',
            'display_name' => ucfirst($name), 'role' => $role, 'status' => 'active',
            'password_hash' => (new \App\Security\PasswordHasher())->hash('password123')]);
        $users->markEmailVerified($ids[$name]);
    }
    $settings = new SettingRepository($db);
    $settings->set('site_name', 'Upload verification');
    $settings->set('installed_at', gmdate('Y-m-d H:i:s'));
    $category = (new \App\Repository\CategoryRepository($db))->create('Upload fixtures');
    $boards = new \App\Repository\BoardRepository($db);
    $general = $boards->create(['category_id' => $category, 'slug' => 'general', 'name' => 'General',
        'visibility' => 'public', 'post_min_role' => 'user']);
    $private = $boards->create(['category_id' => $category, 'slug' => 'staff-room', 'name' => 'Staff room',
        'visibility' => 'private', 'post_min_role' => 'user']);
    (new \App\Repository\BoardMemberRepository($db))->add($private, $ids['bob'], $ids['admin']);
    $posting = new \App\Service\PostingService($db, new \App\Repository\ThreadRepository($db),
        new \App\Repository\PostRepository($db), $boards, $users,
        new \App\Support\Markdown(new \App\Support\HtmlSanitizer()),
        new \App\Security\WriteGate(), new \App\Security\BoardPolicy(), $config);
    $posting->createThread($users->findEntity($ids['alice']), ['board_id' => $general,
        'title' => 'Share your favourite keyboard shortcuts', 'body' => 'Synthetic upload verification topic.']);
}
if ($operation === 'attachment') {
    $id = filter_var($argv[2] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($id === false) { throw new RuntimeException('A synthetic attachment ID is required.'); }
    echo json_encode($db->fetch('SELECT a.id, a.status, a.user_id, a.post_id, a.dm_message_id, a.mime, a.size_bytes,
        COALESCE(p.body, m.body) AS body FROM attachments a LEFT JOIN posts p ON p.id = a.post_id
        LEFT JOIN dm_messages m ON m.id = a.dm_message_id WHERE a.id = ?', [$id]), JSON_THROW_ON_ERROR) . PHP_EOL;
    exit;
}
if (in_array($operation, ['grant-reader', 'revoke-reader'], true)) {
    $board = (int) $db->fetchValue("SELECT id FROM boards WHERE slug = 'staff-room'");
    $alice = (int) $db->fetchValue("SELECT id FROM users WHERE username = 'alice'");
    $members = new \App\Repository\BoardMemberRepository($db);
    if ($operation === 'grant-reader') { $members->add($board, $alice, null); }
    else { $members->remove($board, $alice); }
    echo "{}\n";
    exit;
}
$settings = new SettingRepository($db);
$features = $settings->get('features', []);
$features['rich_composer'] = true;
$features['wysiwyg_composer'] = ($argv[1] ?? 'rich') !== 'source';
$features['uploads'] = true;
$settings->set('features', $features);
$db->run("DELETE FROM server_drafts WHERE user_id IN (SELECT id FROM users WHERE email IN ('alice@retro.test', 'bob@retro.test'))");
$thread = $db->fetch("SELECT id, slug, (SELECT MIN(id) FROM posts WHERE thread_id = threads.id) AS first_post_id FROM threads WHERE title = 'Share your favourite keyboard shortcuts' LIMIT 1");
if ($thread === null) {
    throw new RuntimeException('Run the browser seed before the upload fixture.');
}
if ($operation === 'wiki') {
    $db->run('UPDATE boards SET wiki_enabled = 1 WHERE id = (SELECT board_id FROM threads WHERE id = ?)', [$thread['id']]);
    $db->run('UPDATE posts SET is_wiki = 1 WHERE id = ?', [$thread['first_post_id']]);
}
echo json_encode(['thread' => '/t/' . $thread['id'] . '-' . $thread['slug'], 'thread_id' => (int) $thread['id'],
    'post_id' => (int) $thread['first_post_id'],
    'private_board_id' => (int) $db->fetchValue("SELECT id FROM boards WHERE slug = 'staff-room'"),
    'alice_id' => (int) $db->fetchValue("SELECT id FROM users WHERE username = 'alice'")], JSON_THROW_ON_ERROR) . PHP_EOL;
