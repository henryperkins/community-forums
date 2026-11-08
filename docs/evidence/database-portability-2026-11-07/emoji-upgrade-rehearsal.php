<?php
declare(strict_types=1);

require getcwd() . '/vendor/autoload.php';

$port = (int) ($argv[1] ?? 0);
if (!in_array($port, [33084, 33101], true)) {
    throw new RuntimeException('Use an isolated portability container port');
}
$options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
$admin = new PDO("mysql:host=127.0.0.1;port=$port;charset=utf8mb4", 'root', '', $options);
$name = 'retroboards_emoji_upgrade_' . bin2hex(random_bytes(4));
$legacy = sys_get_temp_dir() . '/rb-emoji-legacy-' . bin2hex(random_bytes(4));
mkdir($legacy);
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
$expect = static function (bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
};
try {
    $pdo = new PDO("mysql:host=127.0.0.1;port=$port;dbname=$name;charset=utf8mb4", 'root', '', $options);
    foreach (glob(getcwd() . '/database/migrations/*.php') as $file) {
        if ((int) basename($file) < 86) symlink($file, $legacy . '/' . basename($file));
    }
    $applied = (new App\Core\Migrator($pdo, $legacy))->migrate();
    $expect($applied === 85, 'All 85 historical migrations must be applied');
    $pdo->exec("INSERT INTO users (username,email) VALUES ('emoji_author','emoji-author@retro.test'),('emoji_fan','emoji-fan@retro.test')");
    $author = (int) $pdo->query("SELECT id FROM users WHERE username='emoji_author'")->fetchColumn();
    $fan = (int) $pdo->query("SELECT id FROM users WHERE username='emoji_fan'")->fetchColumn();
    $pdo->exec("INSERT INTO categories (name) VALUES ('Emoji upgrade')");
    $category = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO boards (category_id,slug,name) VALUES ($category,'emoji-upgrade','Emoji upgrade')");
    $board = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO threads (board_id,user_id,title,slug) VALUES ($board,$author,'Emoji upgrade','emoji-upgrade')");
    $thread = (int) $pdo->lastInsertId();
    $pdo->exec("INSERT INTO posts (thread_id,user_id,body,is_op) VALUES ($thread,$author,'Emoji upgrade',1)");
    $post = (int) $pdo->lastInsertId();
    $insert = $pdo->prepare('INSERT INTO reactions (post_id,user_id,emoji,created_at) VALUES (?,?,?,?)');
    foreach (['👍', '❤️', ':' . str_repeat('a', 40) . ':'] as $emoji) {
        $insert->execute([$post, $fan, $emoji, '2026-11-07 00:00:00']);
    }
    $snapshot = static fn (): array => $pdo->query('SELECT id,post_id,user_id,HEX(emoji) AS emoji_bytes,created_at FROM reactions ORDER BY id')->fetchAll();
    $shape = static fn (): array => $pdo->query("SELECT COLLATION_NAME AS collation_name,CHARACTER_MAXIMUM_LENGTH AS capacity,IS_NULLABLE AS nullable FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='reactions' AND COLUMN_NAME='emoji'")->fetch();
    $alterCount = static fn (): int => (int) $pdo->query("SHOW SESSION STATUS LIKE 'Com_alter_table'")->fetch()['Value'];
    $oldRows = $snapshot();
    $oldShape = $shape();
    $expect($oldShape['collation_name'] !== 'utf8mb4_bin', 'Historical schema must still need the identity migration');
    $migration = require getcwd() . '/database/migrations/0086_reaction_emoji_identity.php';
    $before = $alterCount();
    $migration->up($pdo);
    $firstAlterCount = $alterCount() - $before;
    $expect($snapshot() === $oldRows, 'Upgrade must preserve existing IDs, references, emoji bytes and timestamps');
    $newShape = $shape();
    $expect($newShape['collation_name'] === 'utf8mb4_bin' && (int) $newShape['capacity'] === 48 && $newShape['nullable'] === 'NO', 'Upgrade must preserve capacity/nullability and use binary identity');
    $before = $alterCount();
    $migration->up($pdo);
    $secondAlterCount = $alterCount() - $before;
    $expect($firstAlterCount === 1 && $secondAlterCount === 0, 'Direct replay must skip ALTER after a completed upgrade');
    $db = new App\Core\Database([]);
    $db->setPdo($pdo);
    $repo = new App\Repository\ReactionRepository($db);
    foreach ([true, false] as $emulated) {
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, $emulated);
        $expect(!$repo->exists($post, $fan, '🎉'), 'A missing supplementary emoji must not match an existing reaction');
        $expect($repo->toggle($post, $fan, '🎉') === 'added', 'A second supplementary emoji must be added independently');
        $expect($repo->exists($post, $fan, '👍') && $repo->exists($post, $fan, '🎉'), 'Both supplementary emoji must coexist');
        $expect($repo->toggle($post, $fan, '🎉') === 'removed', 'Toggling the second emoji must remove only itself');
        $expect($snapshot() === $oldRows, 'Removing the second emoji must preserve all legacy reactions');
    }
    $repo->toggle($post, $fan, '🎉');
    $migration->down($pdo);
    $expect($shape()['collation_name'] === 'utf8mb4_bin' && $repo->exists($post, $fan, '👍') && $repo->exists($post, $fan, '🎉'), 'down() must retain the safe collation and distinct reactions');
    echo json_encode(['server' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'historical_migrations' => $applied, 'legacy_collation' => $oldShape['collation_name'], 'new_column' => $newShape, 'preserved_rows' => count($oldRows), 'first_up_alters' => $firstAlterCount, 'second_up_alters' => $secondAlterCount, 'prepare_modes' => ['emulated', 'native'], 'result' => 'passed'], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} finally {
    $admin->exec("DROP DATABASE `$name`");
    foreach (glob($legacy . '/*') as $link) unlink($link);
    rmdir($legacy);
}
