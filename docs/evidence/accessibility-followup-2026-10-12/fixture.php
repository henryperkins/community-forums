<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
App\Core\Env::load($root . '/.env');
$config = App\Core\Config::fromFile($root . '/config/config.php');
if (!str_starts_with((string) $config->get('db.database'), 'retroboards_review_')) {
    throw new RuntimeException('This fixture requires a disposable retroboards_review_* database.');
}
$db = new App\Core\Database($config->get('db'));
$boards = new App\Repository\BoardRepository($db);
if (!$boards->findBySlug('accessibility-review')) {
    $boards->create([
        'category_id' => (int) $db->fetchValue('SELECT category_id FROM boards WHERE slug=?', ['general']),
        'name' => 'Accessibility review', 'slug' => 'accessibility-review',
        'description' => 'Empty board fixture for touch evidence.',
    ]);
}
$drafts = new App\Repository\ServerDraftRepository($db);
foreach (['alice@retro.test', 'admin@retro.test'] as $email) {
    $user = (int) $db->fetchValue('SELECT id FROM users WHERE email=?', [$email]);
    if (!$drafts->findByContext($user, 'thread:new')) {
        $drafts->save($user, 'thread:new', 0, 'Accessibility review draft', 'An unsent fixture for measuring the revision chip.', []);
    }
}
$thread = $db->fetch('SELECT id, slug FROM threads WHERE title=?', ['Share your favourite keyboard shortcuts']);
$target = (int) $db->fetchValue('SELECT id FROM threads WHERE id<>? AND is_deleted=0 ORDER BY id LIMIT 1', [(int) $thread['id']]);
$post = (int) $db->fetchValue('SELECT id FROM posts WHERE thread_id=? AND is_op=1', [(int) $thread['id']]);
$tags = new App\Repository\TagRepository($db);
$references = new App\Service\ContentReferenceService(
    $db, $boards, new App\Repository\ThreadRepository($db), new App\Repository\PostRepository($db),
    $tags, new App\Repository\BoardMemberRepository($db), new App\Security\BoardPolicy(), true,
);
$references->capture('post', $post, '/t/' . $target);
$tag = (int) $db->fetchValue('SELECT id FROM tags ORDER BY id LIMIT 1');
if ($tag > 0) $db->run('INSERT IGNORE INTO thread_tags (thread_id, tag_id) VALUES (?, ?)', [(int) $thread['id'], $tag]);
echo 'Controls fixture ready; topic /t/' . $thread['id'] . '-' . $thread['slug'] . PHP_EOL;
