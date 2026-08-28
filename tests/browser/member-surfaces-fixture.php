<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\SettingRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$users = new UserRepository($db);
$alice = $users->findByUsername('alice');
if ($alice === null) {
    throw new RuntimeException('Run tests/browser/prepare.sh before the member-surface fixture.');
}

$db->run("UPDATE boards SET post_min_role = 'admin' WHERE slug = 'announcements'");
(new SettingRepository($db))->set('engagement_cutover_at', '2000-01-01 00:00:00');

$threadIds = array_map(
    static fn (array $row): int => (int) $row['id'],
    $db->fetchAll(
        "SELECT t.id
           FROM threads t
           JOIN boards b ON b.id = t.board_id
          WHERE b.slug IN ('announcements', 'general')
          ORDER BY t.id",
    ),
);
$threadState = new ThreadUserRepository($db);
foreach ($threadIds as $threadId) {
    $threadState->setStar((int) $alice['id'], $threadId, true);
    $threadState->markUnread((int) $alice['id'], $threadId);
}

(new UserPreferenceRepository($db))->merge((int) $alice['id'], [
    'rail_open' => true,
    'inbox_reading_open' => true,
    'directory_sort' => 'category',
    'directory_peek' => 3,
]);

fwrite(STDOUT, "Seeded member-surface interaction fixtures.\n");
