<?php

declare(strict_types=1);

/**
 * features-toggles.spec.ts state helper. The evidence seed pins many flags in
 * settings.features (wysiwyg_composer=false among them), and a console round
 * trip back to a default removes a pinned key, so the spec snapshots the raw
 * stored JSON and restores it byte-for-byte after every test.
 *
 *   php features-toggles-fixture.php snapshot          -> {"raw": <string|null>}
 *   php features-toggles-fixture.php restore <base64>  restores a snapshot ("" = no row)
 *   php features-toggles-fixture.php corrupt           stores a JSON string, not an object
 *   php features-toggles-fixture.php audit <flag>      -> {"audit": [...]} for that flag
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));

$action = (string) ($argv[1] ?? '');
$argument = (string) ($argv[2] ?? '');

switch ($action) {
    case 'snapshot':
        $raw = $db->fetchValue("SELECT `value` FROM settings WHERE `key` = 'features'");
        echo json_encode(['raw' => is_string($raw) ? $raw : null]);
        break;
    case 'restore':
        $raw = base64_decode($argument, true);
        if ($raw === false) {
            throw new RuntimeException('restore expects the base64 snapshot');
        }
        if ($raw === '') {
            $db->run("DELETE FROM settings WHERE `key` = 'features'");
        } else {
            $db->run(
                "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('features', ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = UTC_TIMESTAMP()",
                [$raw],
            );
        }
        echo json_encode(['restored' => true]);
        break;
    case 'corrupt':
        $db->run(
            "INSERT INTO settings (`key`, `value`, updated_at) VALUES ('features', '\"not-an-object\"', UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = UTC_TIMESTAMP()",
        );
        echo json_encode(['corrupt' => true]);
        break;
    case 'audit':
        echo json_encode(['audit' => $db->fetchAll(
            "SELECT action, reason, before_json, after_json FROM moderation_log
             WHERE action LIKE 'feature\\_flag.%' AND reason = ? ORDER BY id",
            ['features.' . $argument],
        )]);
        break;
    default:
        throw new RuntimeException('Unknown action: ' . $action);
}
