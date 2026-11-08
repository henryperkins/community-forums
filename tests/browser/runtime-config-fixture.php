<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\SettingRepository;

$database = getenv('DB_DATABASE');
if (!is_string($database) || !preg_match('/^retroboards_[a-z0-9_]*browser[a-z0-9_]*$/D', $database)) {
    throw new RuntimeException('Select a throwaway browser database with DB_DATABASE.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$settings = new SettingRepository($db);

if (($argv[1] ?? '') === 'reset') {
    $db->run('DELETE FROM settings WHERE `key` = ?', ['antiabuse_mode']);
    $settings->set('antiabuse_blocked_words', []);
}
$audit = $db->fetch("SELECT before_json, after_json FROM moderation_log WHERE reason = 'anti_abuse_settings' ORDER BY id DESC LIMIT 1");
echo json_encode([
    'mode' => $settings->get('antiabuse_mode'),
    'has_override' => $settings->has('antiabuse_mode'),
    'before' => $audit === null ? null : json_decode($audit['before_json'], true),
    'after' => $audit === null ? null : json_decode($audit['after_json'], true),
], JSON_THROW_ON_ERROR);
