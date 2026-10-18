<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));

if (($argv[1] ?? '') === 'clear-history') {
    $db->run("DELETE FROM link_previews WHERE url LIKE 'https://previews.example.test/admin-review-%'");
    $db->run("UPDATE boards SET link_previews_enabled=0 WHERE slug='staff-room'");
}

if (($argv[1] ?? '') === 'history') {
    $post = (int) $db->fetchValue("SELECT p.id FROM posts p JOIN threads t ON t.id=p.thread_id JOIN boards b ON b.id=t.board_id WHERE b.slug='general' AND p.is_deleted=0 ORDER BY p.id LIMIT 1");
    if ($post < 1) {
        throw new RuntimeException('Seed the private browser database first.');
    }
    for ($i = 1; $i <= 51; $i++) {
        $url = 'https://previews.example.test/admin-review-' . $i;
        $db->run("INSERT INTO link_previews (source_type,source_id,url,url_hash,status,title,http_status,fetched_at,created_at)
            VALUES ('post',?,?,?,'fetched',?,200,UTC_TIMESTAMP(),UTC_TIMESTAMP())
            ON DUPLICATE KEY UPDATE status='fetched',title=VALUES(title)",
            [$post, $url, hash('sha256', $url), 'Admin review ' . $i]);
    }
    // An opted-in private board must disclose its inert posture without hiding
    // or overlapping its Disable control in the compact three-column table.
    $db->run("UPDATE boards SET link_previews_enabled=1 WHERE slug='staff-room'");
}

$shortcode = (string) ($argv[2] ?? '');
echo json_encode([
    'fetched' => (int) $db->fetchValue("SELECT COUNT(*) FROM link_previews WHERE status='fetched'"),
    'emoji_audit' => $db->fetchAll("SELECT action,actor_id,before_json,after_json FROM moderation_log WHERE action LIKE 'custom_emoji.%' AND target_id=(SELECT id FROM custom_emoji WHERE shortcode=?) ORDER BY id", [$shortcode]),
], JSON_THROW_ON_ERROR);
