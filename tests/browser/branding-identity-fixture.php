<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\AttachmentRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Service\AttachmentService;

// Identity-increment evidence (ADR 0054). Mutations are restricted to an
// explicitly selected throwaway browser database.
$database = getenv('DB_DATABASE');
if (!is_string($database) || !preg_match('/^retroboards_(?:e2e(?:_[a-z0-9_]+)?|[a-z0-9_]*browser[a-z0-9_]*)$/D', $database)) {
    throw new RuntimeException('Select a throwaway browser database with DB_DATABASE.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$settings = new SettingRepository($db);
$users = new UserRepository($db);
$preferences = new UserPreferenceRepository($db);
$admin = $users->findByUsername('admin');
if ($admin === null) {
    throw new RuntimeException('Run the standard browser seed first.');
}

const IDENTITY_KEYS = [
    'site_name', 'brand_tagline', 'brand_description', 'brand_directory_heading', 'brand_directory_intro',
    'brand_logo_path', 'brand_logo_light_path', 'brand_logo_dark_path', 'brand_favicon_path',
    'brand_color_primary', 'brand_color_accent', 'brand_theme_default', 'brand_theme_preset',
];

$bump = static fn () => $settings->set('brand_version', bin2hex(random_bytes(8)));
$action = $argv[1] ?? 'state';

if ($action === 'reset') {
    foreach (IDENTITY_KEYS as $key) {
        $settings->set($key, '');
    }
    // Branding history (ADR 0054 increment 2) starts empty for every journey.
    $db->run('DELETE FROM branding_revisions');
    $settings->set('brand_revision', 0);
    $settings->set('site_name', 'RetroBoards');
    $settings->set('brand_theme_default', 'system');
    $settings->set('brand_theme_preset', 'classic');
    $flags = $settings->get('features', []);
    $flags = is_array($flags) ? $flags : [];
    $flags['branding'] = true;
    $settings->set('features', $flags);
    foreach (['admin', 'alice'] as $username) {
        $user = $users->findByUsername($username);
        if ($user !== null) {
            $preferences->merge((int) $user['id'], ['theme' => 'system', 'font_size' => 'medium', 'density' => 'comfortable']);
            // The product tour's first step would cover the captured surfaces.
            $db->run('UPDATE users SET onboarded_at = COALESCE(onboarded_at, UTC_TIMESTAMP()) WHERE id = ?', [(int) $user['id']]);
        }
    }
    $bump();
} elseif ($action === 'set') {
    $values = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);
    foreach ($values as $key => $value) {
        if (!in_array($key, IDENTITY_KEYS, true) || !is_string($value)) {
            throw new RuntimeException('Unsupported identity setting: ' . $key);
        }
        $settings->set($key, $value);
    }
    $bump();
} elseif ($action === 'member') {
    $user = $users->findByUsername((string) ($argv[2] ?? ''));
    if ($user === null) {
        throw new RuntimeException('Unknown member.');
    }
    $preferences->merge((int) $user['id'], json_decode($argv[3] ?? '{}', true, 512, JSON_THROW_ON_ERROR));
} elseif ($action === 'logos') {
    // Real brand attachments through the production intake pipeline, each a
    // labelled wordmark so captures show which variant rendered.
    $service = new AttachmentService(
        new AttachmentRepository($db),
        (string) $config->get('uploads.storage_path'),
        (int) $config->get('uploads.max_bytes', 5_242_880),
    );
    $variants = [
        'base' => ['brand_logo_path', 'BASE', [46, 74, 58], [250, 246, 236]],
        'light' => ['brand_logo_light_path', 'LIGHT', [27, 35, 29], [237, 243, 237]],
        'dark' => ['brand_logo_dark_path', 'DARK', [236, 228, 210], [40, 52, 64]],
        'wide' => ['brand_logo_path', 'A VERY WIDE COMMUNITY WORDMARK', [46, 74, 58], [250, 246, 236]],
    ];
    foreach (['brand_logo_path', 'brand_logo_light_path', 'brand_logo_dark_path'] as $key) {
        $settings->set($key, '');
    }
    foreach (array_filter(explode(',', (string) ($argv[2] ?? ''))) as $variant) {
        [$key, $label, $ink, $ground] = $variants[$variant] ?? throw new RuntimeException('Unknown logo variant.');
        $width = $variant === 'wide' ? 720 : 220;
        $image = imagecreatetruecolor($width, 56);
        imagefill($image, 0, 0, imagecolorallocate($image, ...$ground));
        $inkColour = imagecolorallocate($image, ...$ink);
        imagerectangle($image, 2, 2, $width - 3, 53, $inkColour);
        imagestring($image, 5, 14, 20, $label, $inkColour);
        $file = tempnam(sys_get_temp_dir(), 'rb-logo-');
        imagepng($image, $file);
        $row = $service->storeUpload((int) $admin['id'], [
            'name' => $variant . '.png', 'type' => 'image/png', 'tmp_name' => $file, 'error' => UPLOAD_ERR_OK, 'size' => filesize($file),
        ], 'brand_logo');
        @unlink($file);
        (new AttachmentRepository($db))->finalizeBrandAsset((int) $row['id'], (int) $admin['id']);
        $settings->set($key, '/media/' . (int) $row['id']);
    }
    $bump();
} elseif ($action !== 'state') {
    throw new RuntimeException('Unknown action.');
}

$state = [];
foreach (IDENTITY_KEYS as $key) {
    $state[$key] = $settings->getString($key, '');
}
$state['brand_revision'] = (string) $settings->get('brand_revision', 0);
$state['revisions'] = (string) $db->fetchValue('SELECT COUNT(*) FROM branding_revisions');
echo json_encode($state, JSON_THROW_ON_ERROR);
