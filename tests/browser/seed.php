<?php

declare(strict_types=1);

/**
 * Deterministic seed for the browser-evidence run. Boots the same config the app
 * uses (so it honours DB_DATABASE) and populates a small, realistic community so
 * the Gate A pages have content to capture at desktop + mobile widths.
 *
 * Run against a freshly-migrated database, e.g.:
 *   DB_DATABASE=retroboards_e2e php tests/browser/seed.php
 *
 * Credentials it creates (used by the Playwright spec):
 *   admin@retro.test / password123   (role: admin)
 *   alice@retro.test / password123   (role: user, moderator of #general)
 *   bob@retro.test   / password123   (role: user, member of the private #staff-room)
 */

use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\PasswordHasher;
use App\Security\WriteGate;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));

$db->transaction(function () use ($db): void {
    $db->run("DELETE FROM webhooks WHERE name LIKE 'Evidence webhook (%'");
    $db->run("DELETE FROM service_secrets WHERE owner_type = 'webhook' AND label LIKE 'Webhook signing secret: Evidence webhook (%'");
    $db->run("DELETE FROM api_tokens WHERE name LIKE 'Evidence CI token (%'");
});

$users = new UserRepository($db);
$settings = new SettingRepository($db);
$includeDarkSurfaceFixtures = getenv('RB_BROWSER_DARK_SURFACES') === '1';
$evidenceFeatures = [
    'api_tokens' => true,
    'webhooks' => true,
    'service_secrets' => true,
    'first_party_hooks' => true,
    'announcements' => true,
    'polls' => true,
    'slash_giphy' => true,
    'badge_rules' => true, // GA default-on (2026-07-02); listed explicitly so the admin badge-rules operator surface is captured
    'topic_workflow' => true, // GA default-on (2026-07-01); listed explicitly so the workflow bar is captured regardless of the DEFAULTS map
    'server_drafts' => true, // GA default-on (2026-07-02); listed explicitly so the conflict journey is captured as a standard (non-dark) surface
    'account_lifecycle' => true, // GA default-on (2026-07-02; ADR 0006); listed explicitly so the member self-serve export/deactivate/delete surface is captured
    'appeals' => true, // GA default-on (2026-07-02; ADR 0007); listed explicitly so the member /appeals + staff /mod/appeals surfaces are captured (fixture: $ensureAppealFixture soft-deletes bob's reply)
    'custom_emoji' => true, // GA default-on (2026-07-03); listed explicitly so the admin catalogue + rendered shortcode evidence is captured
    'capabilities' => true, // Inc 1 (P5-08): role editor + simulator browser evidence (shadow-only)
    'package_registry' => true, // Inc 2 (P5-01): staff catalogue browse evidence (read-only)
    'package_themes' => true, // Inc 4 (P5-03): package theme preview/activate/safe-mode/rollback evidence
    'wysiwyg_composer' => false, // GA default-on (2026-07-02) but pinned OFF for the evidence baseline: gate-a + server-drafts journeys drive textarea.composer-input directly (fill/drop/toBeVisible), which a mounted Milkdown hides; the rich surface's browser evidence lives in wysiwyg-composer.spec.ts + the a11y.spec.ts scans, which toggle the flag per test
];
if ($includeDarkSurfaceFixtures) {
    $evidenceFeatures['appeals'] = true;
    $evidenceFeatures['server_extensions'] = true;
}
// appeals graduated to default-on (GA 2026-07-02): the appeal-target fixture
// runs on the STANDARD evidence path (not the dark branch) so /appeals renders
// an appealable action by default. Soft-deletes bob's #general reply as a
// moderator removal (with the matching delete_post moderation_log row that
// AppealService::openForPost requires). No spec depends on that reply being
// present, so removing it is safe for the gate-a journeys.
$ensureAppealFixture = static function () use ($db, $users): bool {
    $admin = $users->findByUsername('admin');
    $bob = $users->findByUsername('bob');
    if ($admin === null || $bob === null) {
        return false;
    }

    $bobReply = $db->fetch(
        'SELECT id FROM posts WHERE user_id = ? AND body = ? ORDER BY id ASC LIMIT 1',
        [(int) $bob['id'], 'Mine is jumping straight to the inbox.'],
    );
    if ($bobReply === null) {
        return false;
    }
    $postId = (int) $bobReply['id'];
    $db->run(
        'UPDATE posts
            SET is_deleted = 1, deleted_at = COALESCE(deleted_at, UTC_TIMESTAMP()), deleted_by = ?
          WHERE id = ?',
        [(int) $admin['id'], $postId],
    );
    $hasDeleteLog = $db->fetchValue(
        "SELECT 1 FROM moderation_log WHERE target_type = 'post' AND target_id = ? AND action = 'delete_post' LIMIT 1",
        [$postId],
    );
    if ($hasDeleteLog === false) {
        $db->run(
            "INSERT INTO moderation_log (actor_id, action, target_type, target_id, reason, created_at)
             VALUES (?, 'delete_post', 'post', ?, 'browser evidence appeal fixture', UTC_TIMESTAMP())",
            [(int) $admin['id'], $postId],
        );
    }

    return true;
};
$ensureNewSurfaceFixtures = static function () use ($db, $users): bool {
    $admin = $users->findByUsername('admin');
    $bob = $users->findByUsername('bob');
    if ($admin === null || $bob === null) {
        return false;
    }

    $db->run(
        'INSERT INTO server_drafts
           (user_id, context_key, revision, title, body, metadata, updated_at, expires_at)
         VALUES (?, ?, 1, ?, ?, ?, UTC_TIMESTAMP(), DATE_ADD(UTC_TIMESTAMP(), INTERVAL 90 DAY))
         ON DUPLICATE KEY UPDATE
           title = VALUES(title),
           body = VALUES(body),
           metadata = VALUES(metadata),
           updated_at = UTC_TIMESTAMP(),
           expires_at = VALUES(expires_at)',
        [
            (int) $bob['id'],
            'browser-a11y-reply',
            'Saved reply draft',
            'A server-side draft used by the accessibility evidence run.',
            json_encode(['path' => '/t/browser-evidence'], JSON_THROW_ON_ERROR),
        ],
    );

    $db->run(
        "INSERT INTO packages (package_uid, name, type, trust_class, created_at, updated_at)
         VALUES ('local.browser.extension', 'Browser Evidence Extension', 'server_extension', 'isolated_server', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = UTC_TIMESTAMP()",
    );
    $packageId = (int) $db->fetchValue("SELECT id FROM packages WHERE package_uid = 'local.browser.extension'");
    $db->run(
        "INSERT INTO installed_packages (package_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
         VALUES (?, REPEAT('c', 64), 'isolated_server', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           digest = VALUES(digest),
           trust_class = VALUES(trust_class),
           review_status = VALUES(review_status),
           state = VALUES(state),
           updated_at = UTC_TIMESTAMP()",
        [$packageId, (int) $admin['id']],
    );
    $installedId = (int) $db->fetchValue('SELECT id FROM installed_packages WHERE package_id = ?', [$packageId]);
    $db->run(
        'INSERT INTO server_extension_handlers
           (installed_package_id, handler_key, entrypoint, events_json, jobs_json, permissions_json,
            resource_limits_json, storage_quota_bytes, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'enabled\', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
           entrypoint = VALUES(entrypoint),
           events_json = VALUES(events_json),
           jobs_json = VALUES(jobs_json),
           permissions_json = VALUES(permissions_json),
           resource_limits_json = VALUES(resource_limits_json),
           storage_quota_bytes = VALUES(storage_quota_bytes),
           status = VALUES(status),
           updated_at = UTC_TIMESTAMP()',
        [
            $installedId,
            'browser-evidence',
            'extension.php',
            json_encode(['topic.created'], JSON_THROW_ON_ERROR),
            json_encode(['refresh-related'], JSON_THROW_ON_ERROR),
            json_encode(['broker' => [], 'outbound_hosts' => []], JSON_THROW_ON_ERROR),
            json_encode(['time_ms' => 1000, 'memory_mb' => 64, 'cpu_ms' => 500, 'output_kb' => 64, 'disk_kb' => 512], JSON_THROW_ON_ERROR),
            262144,
        ],
    );

    // Enabled remote_app integration install (P5-04): grant summary + settings form
    // render, and provisioning mints a token deterministically (api-scope-only).
    $db->run(
        "INSERT INTO packages (package_uid, name, type, trust_class, created_at, updated_at)
         VALUES ('acme/browser-remote', 'Browser Remote App', 'remote_app', 'reviewed_remote', UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE name = VALUES(name), updated_at = UTC_TIMESTAMP()",
    );
    $remotePkgId = (int) $db->fetchValue("SELECT id FROM packages WHERE package_uid = 'acme/browser-remote'");
    $remoteManifest = json_encode([
        'format' => 'rb-manifest.v2',
        'uid' => 'acme/browser-remote', 'version' => '1.0.0', 'name' => 'Browser Remote App', 'type' => 'remote_app',
        'core' => ['min' => '0.1.0'],
        'permissions' => ['api_scopes' => ['read:boards']],
        'settings_schema' => ['fields' => [
            ['key' => 'display_name', 'type' => 'string', 'label' => 'Display name', 'required' => false],
        ]],
    ], JSON_THROW_ON_ERROR);
    $db->run(
        "INSERT INTO package_releases (package_id, version, digest, license, manifest_json, review_status, channel, advisory_status, published_at)
         VALUES (?, '1.0.0', REPEAT('d', 64), 'MIT', ?, 'approved', 'stable', 'none', UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE manifest_json = VALUES(manifest_json)",
        [$remotePkgId, $remoteManifest],
    );
    $remoteReleaseId = (int) $db->fetchValue("SELECT id FROM package_releases WHERE package_id = ? ORDER BY id DESC LIMIT 1", [$remotePkgId]);
    $db->run(
        "INSERT INTO installed_packages (package_id, release_id, digest, trust_class, review_status, state, installed_by, installed_at, updated_at)
         VALUES (?, ?, REPEAT('d', 64), 'reviewed_remote', 'approved', 'enabled', ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE release_id = VALUES(release_id), state = 'enabled', updated_at = UTC_TIMESTAMP()",
        [$remotePkgId, $remoteReleaseId, (int) $admin['id']],
    );
    $remoteInstalledId = (int) $db->fetchValue('SELECT id FROM installed_packages WHERE package_id = ?', [$remotePkgId]);
    $db->run(
        "INSERT INTO installed_package_permissions (installed_package_id, kind, permission_key, risk_class, declared, granted, granted_at, granted_by)
         VALUES (?, 'api_scope', 'read:boards', 'low', 1, 1, UTC_TIMESTAMP(), ?)
         ON DUPLICATE KEY UPDATE granted = 1, granted_at = UTC_TIMESTAMP()",
        [$remoteInstalledId, (int) $admin['id']],
    );

    return true;
};
$ensureShortcutPoll = static function () use ($db, $users): bool {
    $thread = $db->fetch(
        "SELECT t.id
           FROM threads t
           JOIN boards b ON b.id = t.board_id
          WHERE b.slug = 'general' AND t.title = ?
          LIMIT 1",
        ['Share your favourite keyboard shortcuts'],
    );
    $alice = $users->findByUsername('alice');
    if ($thread === null || $alice === null) {
        return false;
    }

    $pollId = $db->fetchValue(
        'SELECT id FROM polls WHERE thread_id = ? LIMIT 1',
        [(int) $thread['id']],
    );
    if ($pollId === false) {
        $pollId = $db->insert(
            "INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at)
             VALUES (?, ?, 'single', 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())",
            [(int) $thread['id'], 'Which shortcut do you reach for first?', (int) $alice['id']],
        );
    }
    $pollId = (int) $pollId;

    foreach ([0 => 'Ctrl+K', 1 => 'Jump to inbox'] as $position => $body) {
        $exists = $db->fetchValue(
            'SELECT 1 FROM poll_options WHERE poll_id = ? AND body = ? LIMIT 1',
            [$pollId, $body],
        );
        if ($exists === false) {
            $db->run(
                'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, ?, UTC_TIMESTAMP())',
                [$pollId, $body, $position],
            );
        }
    }
    $db->run('DELETE FROM poll_votes WHERE poll_id = ?', [$pollId]);

    return true;
};
$ensureRegistryFixtures = static function () use ($db, $config, $users): bool {
    $db->transaction(function () use ($db): void {
        $db->run("DELETE FROM package_advisories WHERE advisory_uid LIKE 'RB-TEST-%'");
        $db->run("DELETE FROM local_package_blocks WHERE reason = 'Evidence blocklist entry'");
        $db->run("DELETE FROM packages WHERE package_uid LIKE 'acme/%'");
        $db->run("DELETE FROM package_publishers WHERE publisher_uid LIKE 'acme%'");
        $db->run("DELETE FROM package_registries WHERE source_id IN ('rb-test', 'rb-test-mobile', 'rb-consent', 'rb-theme-evidence', 'rb-theme-evidence-alt')");
    });

    $artifactDir = (string) $config->get('packages.storage_path');
    $registryRoot = \Tests\Support\Phase5\SigningHarness::generate('root-1');
    $registries = new \App\Repository\PackageRegistryRepository($db);
    $packages = new \App\Repository\PackageRepository($db);
    $releases = new \App\Repository\PackageReleaseRepository($db);

    $themePng = base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWP4z8AAAAMBAQCc479ZAAAAAElFTkSuQmCC',
        true,
    );
    if ($themePng === false) {
        throw new RuntimeException('Unable to decode browser theme asset fixture.');
    }
    $asset = [
        'name' => 'parchment',
        'kind' => 'png',
        'sha256' => hash('sha256', $themePng),
        'data_base64' => base64_encode($themePng),
    ];
    $midnightTheme = [
        'tokens' => [
            '--surface' => '#1d232e',
            '--surface-2' => '#252d3a',
            '--surface-3' => '#2d3748',
            '--text' => '#f4f7fb',
            '--text-muted' => '#d7deea',
            '--accent' => '#d2b062',
            '--accent-contrast' => '#241706',
            '--surface-texture' => 'parchment',
        ],
        'dark_tokens' => [
            '--surface' => '#1d232e',
            '--surface-2' => '#252d3a',
            '--surface-3' => '#2d3748',
            '--text' => '#f4f7fb',
            '--text-muted' => '#d7deea',
            '--accent' => '#d2b062',
            '--accent-contrast' => '#241706',
        ],
        'assets' => [$asset],
    ];
    $lkgTheme = [
        'tokens' => [
            '--surface' => '#f7fbff',
            '--surface-2' => '#ebf3fb',
            '--text' => '#172033',
            '--text-muted' => '#46556b',
            '--accent' => '#1f4fbf',
            '--accent-contrast' => '#ffffff',
        ],
        'dark_tokens' => [
            '--surface' => '#172033',
            '--surface-2' => '#202b40',
            '--text' => '#f7fbff',
            '--text-muted' => '#d5deec',
            '--accent' => '#8fb7ff',
            '--accent-contrast' => '#172033',
        ],
        'assets' => [],
    ];

    $registryIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot, $artifactDir, [
        'release' => ['manifest' => ['theme' => $midnightTheme]],
    ]);
    $mobileIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot, $artifactDir, [
        'source_id' => 'rb-test-mobile',
        'publisher_uid' => 'acme-mobile',
        'package_uid' => 'acme/midnight-theme-mobile',
        'name' => 'Midnight Theme Mobile',
        'release' => ['manifest' => ['theme' => $midnightTheme]],
    ]);
    $showcaseIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot, $artifactDir, [
        'source_id' => 'rb-theme-evidence',
        'publisher_uid' => 'acme-theme-evidence',
        'package_uid' => 'acme/theme-evidence',
        'name' => 'Theme Evidence',
        'release' => ['manifest' => ['theme' => $midnightTheme]],
    ]);
    $showcaseAltIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot, $artifactDir, [
        'source_id' => 'rb-theme-evidence-alt',
        'publisher_uid' => 'acme-theme-evidence-alt',
        'package_uid' => 'acme/theme-evidence-alt',
        'name' => 'Theme Evidence Alt',
        'release' => ['manifest' => ['theme' => $lkgTheme]],
    ]);

    foreach ([$registryIds, $mobileIds, $showcaseIds, $showcaseAltIds] as $ids) {
        $registries->setEnabled($ids['registry_id'], true);
        $packages->setLatestRelease($ids['package_id'], $ids['release_id']);
    }

    \Tests\Support\Phase5\RegistryFixtures::seedRelease($db, $registryRoot, $registryIds, [
        'uid' => 'acme/midnight-theme',
        'version' => '1.1.0',
        'manifest' => ['permissions' => [
            'data_classes' => ['package.own_storage'],
            'outbound_hosts' => ['api.example.com'],
        ], 'theme' => $midnightTheme],
    ], $artifactDir);
    \Tests\Support\Phase5\RegistryFixtures::seedRelease($db, $registryRoot, $mobileIds, [
        'uid' => 'acme/midnight-theme-mobile',
        'version' => '1.1.0',
        'manifest' => ['name' => 'Midnight Theme Mobile', 'permissions' => [
            'data_classes' => ['package.own_storage'],
            'outbound_hosts' => ['api.example.com'],
        ], 'theme' => $midnightTheme],
    ], $artifactDir);
    $packages->setLatestRelease($registryIds['package_id'], $registryIds['release_id']);
    $packages->setLatestRelease($mobileIds['package_id'], $mobileIds['release_id']);

    $consentIds = \Tests\Support\Phase5\RegistryFixtures::seed($db, $registryRoot, $artifactDir, [
        'source_id' => 'rb-consent',
        'publisher_uid' => 'acme-consent',
        'package_uid' => 'acme/consent-demo',
        'name' => 'Consent Demo Theme',
    ]);
    $registries->setEnabled($consentIds['registry_id'], true);
    $packages->setLatestRelease($consentIds['package_id'], $consentIds['release_id']);

    $admin = $users->findByUsername('admin');
    if ($admin !== null) {
        $installEnabledTheme = static function (array $ids) use ($db, $admin, $packages, $releases): void {
            $package = $packages->find($ids['package_id']);
            $release = $releases->find($ids['release_id']);
            if ($package === null || $release === null) {
                return;
            }

            $installedId = (new \App\Repository\InstalledPackageRepository($db))->create([
                'package_id' => (int) $package['id'],
                'release_id' => (int) $release['id'],
                'digest' => (string) $release['digest'],
                'source_registry_id' => (int) $ids['registry_id'],
                'publisher_id' => (int) $ids['publisher_id'],
                'trust_class' => (string) $package['trust_class'],
                'review_status' => (string) $release['review_status'],
                'compat_min' => $release['core_min'] !== null ? (string) $release['core_min'] : null,
                'compat_max' => $release['core_max'] !== null ? (string) $release['core_max'] : null,
                'installed_by' => (int) $admin['id'],
            ]);
            (new \App\Repository\InstalledPackageRepository($db))->setState($installedId, 'enabled');
        };
        $installEnabledTheme($showcaseIds);
        $installEnabledTheme($showcaseAltIds);

        $package = $packages->find($consentIds['package_id']);
        $release = $releases->find($consentIds['release_id']);
        if ($package !== null && $release !== null) {
            $installedId = (new \App\Repository\InstalledPackageRepository($db))->create([
                'package_id' => (int) $package['id'],
                'release_id' => (int) $release['id'],
                'digest' => (string) $release['digest'],
                'source_registry_id' => (int) $consentIds['registry_id'],
                'publisher_id' => (int) $consentIds['publisher_id'],
                'trust_class' => (string) $package['trust_class'],
                'review_status' => (string) $release['review_status'],
                'compat_min' => $release['core_min'] !== null ? (string) $release['core_min'] : null,
                'compat_max' => $release['core_max'] !== null ? (string) $release['core_max'] : null,
                'installed_by' => (int) $admin['id'],
            ]);
            $permission = \App\Security\Packages\PermissionDiff::describe('data_class', 'package.own_storage');
            (new \App\Repository\InstalledPackagePermissionRepository($db))->replaceDeclared($installedId, [[
                'kind' => 'data_class',
                'key' => 'package.own_storage',
                'risk' => $permission['risk'],
            ]]);
        }
    }

    $advisory = $registryRoot->mintAdvisory([
        'action' => 'warn',
        'summary' => 'Evidence advisory: upgrade past 1.0.0',
    ]);
    (new \App\Service\Registry\RegistryAdvisoryService(
        $db,
        new \App\Security\Registry\TrustChainVerifier(),
        new \App\Repository\RegistryTrustKeyRepository($db),
        new \App\Repository\PackageAdvisoryRepository($db),
        new \App\Repository\PackageRepository($db),
        new \App\Repository\PackageReleaseRepository($db),
        new \App\Repository\ModerationLogRepository($db),
    ))->ingest($registryIds['registry_id'], $advisory['json'], $advisory['signature'], $advisory['key_id']);
    (new \App\Repository\LocalPackageBlockRepository($db))->add(null, 'acme/legacy-widget', 'Evidence blocklist entry', null);

    return true;
};
if ($users->adminCount() > 0) {
    $settings->set('features', $evidenceFeatures);
    $settings->set('giphy_public_key', 'browser-evidence-key');
    $settings->set('giphy_rating', 'pg');
    $pollReady = $ensureShortcutPoll();
    $registryReady = $ensureRegistryFixtures();
    $ensureAppealFixture();
    $newSurfaceFixturesReady = $includeDarkSurfaceFixtures ? $ensureNewSurfaceFixtures() : false;
    fwrite(STDOUT, $pollReady
        ? ($registryReady
            ? ($newSurfaceFixturesReady
                ? "Already seeded (admin exists); refreshed feature flags, poll fixture, registry fixtures, and dark-surface fixtures.\n"
                : "Already seeded (admin exists); refreshed feature flags, poll fixture, and registry fixtures.\n")
            : "Already seeded (admin exists); refreshed feature flags and poll fixture.\n")
        : "Already seeded (admin exists); refreshed feature flags.\n");
    exit(0);
}

$hasher = new PasswordHasher();
$categories = new CategoryRepository($db);
$boards = new BoardRepository($db);
$mods = new BoardModeratorRepository($db);
$members = new BoardMemberRepository($db);

$posting = new PostingService(
    $db,
    new ThreadRepository($db),
    new PostRepository($db),
    new BoardRepository($db),
    new UserRepository($db),
    new Markdown(new HtmlSanitizer()),
    new WriteGate(),
    new BoardPolicy(),
    $config,
);

$makeUser = static function (string $username, string $display, string $role) use ($users, $hasher): int {
    $id = $users->create([
        'username' => $username,
        'email' => $username . '@retro.test',
        'password_hash' => $hasher->hash('password123'),
        'display_name' => $display,
        'role' => $role,
        'status' => 'active',
    ]);
    $users->markEmailVerified($id);
    return $id;
};

$db->transaction(function () use ($db, $settings, $categories, $boards, $mods, $members, $posting, $users, $makeUser, $evidenceFeatures): void {
    // Site settings — past the first-run setup gate.
    $settings->set('site_name', 'RetroBoards');
    $settings->set('registration_mode', 'open');
    $settings->set('installed_at', gmdate('Y-m-d H:i:s'));
    $settings->set('features', $evidenceFeatures); // B2/admin pages + announcements + carryover evidence
    $settings->set('giphy_public_key', 'browser-evidence-key');
    $settings->set('giphy_rating', 'pg');

    // Accounts.
    $adminId = $makeUser('admin', 'Site Admin', 'admin');
    $aliceId = $makeUser('alice', 'Alice Avery', 'user');
    $bobId   = $makeUser('bob', 'Bob Brooks', 'user');
    $carolId = $makeUser('carol', 'Carol Chen', 'user');
    // Dedicated non-admin used only by the account-lifecycle journey: deactivate/
    // reactivate/delete-request/cancel are destructive, so isolate them from bob
    // (staff-room/drafts/appeal fixtures) and carol (mobile poll voter).
    $makeUser('dana', 'Dana Diaz', 'user');

    // Categories + boards (one private board to exercise membership).
    $welcome = $categories->create('Welcome', 0);
    $community = $categories->create('Community', 1);

    $announce = $boards->create([
        'category_id' => $welcome, 'slug' => 'announcements', 'name' => 'Announcements',
        'description' => 'News and updates from the team.', 'visibility' => 'public', 'post_min_role' => 'user',
    ]);
    $general = $boards->create([
        'category_id' => $community, 'slug' => 'general', 'name' => 'General',
        'description' => 'Talk about anything.', 'visibility' => 'public', 'post_min_role' => 'user', 'allow_anonymous' => 1,
        'assignment_mode' => 'staff', // opt #general into topic assignment so the workflow assign control renders for staff (alice)
    ]);
    $boards->create([
        'category_id' => $community, 'slug' => 'feedback', 'name' => 'Feedback',
        'description' => 'Ideas and suggestions.', 'visibility' => 'public', 'post_min_role' => 'user',
    ]);
    $staff = $boards->create([
        'category_id' => $welcome, 'slug' => 'staff-room', 'name' => 'Staff Room',
        'description' => 'Private board for the team.', 'visibility' => 'private', 'post_min_role' => 'user',
    ]);

    // Roster: showcases the admin board-edit roster UI on /admin/boards/{general}/edit.
    $mods->assign($general, $aliceId);
    $members->add($staff, $bobId, $adminId);

    // Threads + replies so boards and thread pages render real content.
    $welcomeThread = $posting->createThread($users->findEntity($adminId), [
        'board_id' => $announce, 'title' => 'Welcome to RetroBoards',
        'body' => "We're glad you're here. Introduce yourself and explore the boards.\n\nThis community runs entirely without JavaScript — every action works as a plain form submit.",
    ]);
    $posting->reply($users->findEntity($aliceId), $welcomeThread['thread_id'], [
        'body' => 'Thanks for setting this up! Looking forward to the discussions.',
    ]);

    $tipsThread = $posting->createThread($users->findEntity($aliceId), [
        'board_id' => $general, 'title' => 'Share your favourite keyboard shortcuts',
        'body' => "What are the shortcuts you can't live without? I'll start: **Ctrl+K** for search.",
    ]);
    $posting->reply($users->findEntity($bobId), $tipsThread['thread_id'], ['body' => 'Mine is jumping straight to the inbox.']);
    $posting->reply($users->findEntity($carolId), $tipsThread['thread_id'], ['body' => 'I just use the sidebar, honestly.']);
    $pollId = $db->insert(
        "INSERT INTO polls (thread_id, question, mode, status, results_policy, created_by, created_at)
         VALUES (?, ?, 'single', 'open', 'after_vote_or_close', ?, UTC_TIMESTAMP())",
        [$tipsThread['thread_id'], 'Which shortcut do you reach for first?', $aliceId],
    );
    $db->run(
        'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, 0, UTC_TIMESTAMP())',
        [$pollId, 'Ctrl+K'],
    );
    $db->run(
        'INSERT INTO poll_options (poll_id, body, position, created_at) VALUES (?, ?, 1, UTC_TIMESTAMP())',
        [$pollId, 'Jump to inbox'],
    );

    $posting->createThread($users->findEntity($bobId), [
        'board_id' => $general, 'title' => 'Mobile layout looks great',
        'body' => 'Tested on my phone today — tap targets are comfortable and nothing overflows.',
    ]);
});

$ensureRegistryFixtures();
$ensureAppealFixture();

if ($includeDarkSurfaceFixtures) {
    $ensureNewSurfaceFixtures();
}

fwrite(STDOUT, "Seeded RetroBoards e2e content.\n");
