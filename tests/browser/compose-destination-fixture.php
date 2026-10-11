<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if (!preg_match('/^retroboards_e2e(?:_[a-z0-9_]+)?$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Compose fixtures require an isolated browser database.');
}

$db = new Database($config->get('db'));
$fixture = $db->transaction(function () use ($db): array {
    $boards = new BoardRepository($db);
    $general = $boards->findBySlug('general');
    if ($general === null) {
        throw new RuntimeException('Run tests/browser/prepare.sh before the compose fixture.');
    }
    $slug = (string) $general['id'];
    $name = 'Compose numeric destination';
    $board = $boards->findBySlug($slug);
    if ($board === null) {
        $id = $boards->create([
            'category_id' => (int) $general['category_id'],
            'slug' => $slug,
            'name' => $name,
            'visibility' => 'public',
        ]);
        $board = $boards->find($id);
    }
    if ($board === null || $board['name'] !== $name
        || (int) $board['category_id'] !== (int) $general['category_id']
        || (int) $board['position'] <= (int) $general['position']) {
        throw new RuntimeException('The numeric compose fixture must sort after General.');
    }

    $users = new UserRepository($db);
    $reader = $users->findByUsername('compose_destination_reader');
    $userId = $reader !== null ? (int) $reader['id'] : $users->create([
        'username' => 'compose_destination_reader',
        'email' => 'compose-destination-reader@retro.test',
        'display_name' => 'Compose destination reader',
        'password_hash' => (new PasswordHasher())->hash('password123'),
    ]);
    $db->run('UPDATE users SET onboarded_at=UTC_TIMESTAMP() WHERE id=?', [$userId]);

    return ['id' => (int) $board['id'], 'slug' => $slug, 'name' => $name];
});
fwrite(STDOUT, json_encode($fixture, JSON_THROW_ON_ERROR) . PHP_EOL);
