<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\InstalledPackageRepository;
use App\Repository\PackageThemeRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;

// Every mutation below is restricted to an explicitly selected throwaway DB.
$database = getenv('DB_DATABASE');
if (!is_string($database) || !preg_match('/^retroboards_(?:e2e|[a-z0-9_]*browser[a-z0-9_]*)$/D', $database)) {
    throw new RuntimeException('Select a throwaway browser database with DB_DATABASE.');
}
$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$db = new Database(Config::fromFile($root . '/config/config.php')->get('db'));
$settings = new SettingRepository($db);
$themes = new PackageThemeRepository($db);
$installs = new InstalledPackageRepository($db);
$admin = (new UserRepository($db))->findByUsername('admin');
if ($admin === null) {
    throw new RuntimeException('Run the standard browser seed first.');
}

$action = $argv[1] ?? 'state';
if ($action === 'reset') {
    $db->transaction(function () use ($db, $settings, $themes, $admin): void {
        $themes->setState(null, null, null);
        $db->run('DELETE FROM package_theme_builds');
        $settings->set('theme_safe_mode', '');
        foreach ([
            'brand_color_primary', 'brand_color_accent', 'brand_logo_path',
            'brand_logo_light_path', 'brand_logo_dark_path', 'brand_favicon_path',
            'brand_custom_css', 'brand_custom_css_enabled',
        ] as $key) {
            $settings->set($key, '');
        }
        $settings->set('site_name', 'RetroBoards');
        $settings->set('brand_theme_default', 'system');
        $settings->set('brand_theme_preset', 'classic');
        $settings->set('brand_version', bin2hex(random_bytes(8)));
        (new UserPreferenceRepository($db))->merge((int) $admin['id'], ['theme' => 'system']);
        $flags = $settings->get('features', []);
        $flags['branding'] = true;
        $flags['package_registry'] = true;
        $flags['package_themes'] = true;
        $settings->set('features', $flags);
    });

    // The standard seed contains two installed themes, plus this independent
    // signed package with identical texture bytes. Building it must never
    // replace the active build's eligibility to serve those bytes.
    $package = $db->fetch('SELECT * FROM packages WHERE package_uid = ?', ['acme/midnight-theme']);
    if ($package === null) {
        throw new RuntimeException('Missing signed browser theme fixture.');
    }
    $installed = $installs->findByPackage((int) $package['id']);
    if ($installed === null) {
        $release = $db->fetch('SELECT * FROM package_releases WHERE id = ?', [(int) $package['latest_release_id']]);
        if ($release === null) {
            throw new RuntimeException('Missing signed browser theme release.');
        }
        $id = $installs->create([
            'package_id' => (int) $package['id'],
            'release_id' => (int) $release['id'],
            'digest' => (string) $release['digest'],
            'source_registry_id' => (int) $package['registry_id'],
            'publisher_id' => (int) $package['publisher_id'],
            'trust_class' => (string) $package['trust_class'],
            'review_status' => (string) $release['review_status'],
            'compat_min' => $release['core_min'],
            'compat_max' => $release['core_max'],
            'installed_by' => (int) $admin['id'],
        ]);
        $installs->setState($id, 'enabled');
    } else {
        $installs->setState((int) $installed['id'], 'enabled');
    }
} elseif ($action === 'flags-off') {
    $flags = $settings->get('features', []);
    $flags['branding'] = false;
    $flags['package_themes'] = false;
    $settings->set('features', $flags);
} elseif ($action === 'disable-preview-package') {
    $package = $db->fetch('SELECT id FROM packages WHERE package_uid = ?', ['acme/theme-evidence']);
    $installed = $package === null ? null : $installs->findByPackage((int) $package['id']);
    if ($installed === null) {
        throw new RuntimeException('Missing theme evidence install.');
    }
    $installs->setState((int) $installed['id'], 'disabled');
} elseif ($action === 'enable-preview-package') {
    $package = $db->fetch('SELECT id FROM packages WHERE package_uid = ?', ['acme/theme-evidence']);
    $installed = $package === null ? null : $installs->findByPackage((int) $package['id']);
    if ($installed === null) {
        throw new RuntimeException('Missing theme evidence install.');
    }
    $installs->setState((int) $installed['id'], 'enabled');
}

$override = $db->fetch("SELECT id, after_json FROM moderation_log WHERE action = 'branding_contrast_override' ORDER BY id DESC LIMIT 1");
$brandingUpdate = $db->fetch("SELECT after_json FROM moderation_log WHERE action = 'branding_update' ORDER BY id DESC LIMIT 1");
echo json_encode([
    'site_name' => $settings->getString('site_name'),
    'primary' => $settings->getString('brand_color_primary'),
    'accent' => $settings->getString('brand_color_accent'),
    'preset' => $settings->getString('brand_theme_preset'),
    'theme_default' => $settings->getString('brand_theme_default'),
    'assets' => [
        'logo' => $settings->getString('brand_logo_path'),
        'logo_light' => $settings->getString('brand_logo_light_path'),
        'logo_dark' => $settings->getString('brand_logo_dark_path'),
        'favicon' => $settings->getString('brand_favicon_path'),
    ],
    'branding_update' => $brandingUpdate !== null && $brandingUpdate['after_json'] !== null
        ? json_decode((string) $brandingUpdate['after_json'], true, flags: JSON_THROW_ON_ERROR)
        : null,
    'safe_mode' => $settings->getString('theme_safe_mode') === '1',
    'theme' => $themes->state(),
    'override_id' => $override !== null ? (int) $override['id'] : null,
    'override' => $override !== null ? json_decode((string) $override['after_json'], true, flags: JSON_THROW_ON_ERROR) : null,
], JSON_THROW_ON_ERROR);
