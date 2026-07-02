<?php

declare(strict_types=1);

use App\Core\Env;

/**
 * Application configuration. Values resolve from the environment (.env / real
 * env vars) with safe defaults. This file returns a plain array consumed by
 * App\Core\Config.
 */
return [
    'app' => [
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => Env::bool('APP_DEBUG', false),
        'url' => Env::get('APP_URL', 'http://localhost:8000'),
        // Used to derive CSRF tokens for guests and to sign cookies.
        'key' => Env::get('APP_KEY', ''),
        'name' => Env::get('APP_NAME', 'RetroBoards'),
    ],

    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => (int) Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'retroboards'),
        'username' => Env::get('DB_USERNAME', 'retro'),
        'password' => Env::get('DB_PASSWORD', 'retropw'),
        'charset' => 'utf8mb4',
    ],

    'session' => [
        'name' => Env::get('SESSION_NAME', 'rb_session'),
        'secure' => Env::bool('SESSION_SECURE', true),
        'lifetime_days' => (int) Env::get('SESSION_LIFETIME_DAYS', '30'),
    ],

    'security' => [
        'hsts' => Env::bool('SECURITY_HSTS', true),
    ],

    'telemetry' => [
        // Foundation F8: structured correlation-ID telemetry. Dark by default;
        // emitted context is always redacted by App\Support\LogRedactor.
        'enabled' => Env::bool('TELEMETRY_ENABLED', false),
    ],

    'auth' => [
        // Password-reset link lifetime in seconds (default 1 hour). Tokens are
        // single-use; this only bounds how long an unused link stays valid.
        'password_reset_ttl' => (int) Env::get('AUTH_PASSWORD_RESET_TTL', '3600'),
        // Email-verification link lifetime in seconds (default 24 hours).
        'email_verify_ttl' => (int) Env::get('AUTH_EMAIL_VERIFY_TTL', '86400'),
    ],

    'mail' => [
        // 'sendmail' uses PHP mail(); swap to an SMTP/provider adapter behind the
        // App\Mail\Mailer interface later. Empty `from` ⇒ not configured ⇒ email
        // fails closed (in-app notifications still deliver).
        'driver' => Env::get('MAIL_DRIVER', 'sendmail'),
        'from' => Env::get('MAIL_FROM', ''),
        'from_name' => Env::get('MAIL_FROM_NAME', ''),
    ],

    'paths' => [
        'base' => dirname(__DIR__),
        'templates' => dirname(__DIR__) . '/templates',
        'migrations' => dirname(__DIR__) . '/database/migrations',
        'ratelimit' => Env::get('RATELIMIT_PATH', dirname(__DIR__) . '/storage/ratelimit'),
    ],

    'pagination' => [
        'threads_per_page' => 20,
        'posts_per_page' => 20,
    ],

    'dm' => [
        // New-user anti-spam: a brand-new account (below both thresholds) cannot
        // START a new conversation; replies to existing conversations are allowed.
        'new_user_min_posts' => (int) Env::get('DM_NEW_USER_MIN_POSTS', '1'),
        'new_user_min_age_minutes' => (int) Env::get('DM_NEW_USER_MIN_AGE_MINUTES', '1440'),
    ],

    // Community layer (P2-09). Reputation/titles/badges are cosmetic — they grant
    // no powers (COMMUNITY §1, §12). Thresholds are tunable but never gate ability.
    'community' => [
        // Accepted/"solved" answer reputation bonus (COMMUNITY §2.1: e.g. +5).
        'solved_bonus' => (int) Env::get('COMMUNITY_SOLVED_BONUS', '5'),
        // Cosmetic title ladder (New → … → Legend), by lifetime reputation. The
        // highest threshold a user's reputation meets wins; users.title overrides.
        'title_thresholds' => [
            0 => 'New',
            10 => 'Member',
            50 => 'Regular',
            200 => 'Veteran',
            1000 => 'Legend',
        ],
        // Auto-badge milestones (COMMUNITY §6).
        'badge_conversation_starter_threads' => 10,
        'badge_trusted_answerer_solved' => 10,
        'badge_appreciated_rep' => 100,
        'badge_well_liked_rep' => 1000,
        // Following feed page size.
        'feed_per_page' => 20,
        'leaderboard_size' => 50,
    ],

    // Presence (P2-11): only refresh last_seen_at at most once per this interval
    // per request to keep writes cheap; the roster shows users seen within the window.
    'presence' => [
        'heartbeat_seconds' => (int) Env::get('PRESENCE_HEARTBEAT_SECONDS', '60'),
        'online_window_seconds' => (int) Env::get('PRESENCE_ONLINE_WINDOW_SECONDS', '300'),
    ],

    // OAuth providers (P2-10). Each provider is "configured" only when it has a
    // client id + secret; an unconfigured provider fails closed (no button, no
    // callback). Tokens are never persisted; we store only the normalised identity.
    'oauth' => [
        'google' => [
            'client_id' => Env::get('OAUTH_GOOGLE_CLIENT_ID', ''),
            'client_secret' => Env::get('OAUTH_GOOGLE_CLIENT_SECRET', ''),
        ],
        'github' => [
            'client_id' => Env::get('OAUTH_GITHUB_CLIENT_ID', ''),
            'client_secret' => Env::get('OAUTH_GITHUB_CLIENT_SECRET', ''),
        ],
        'apple' => [
            'client_id' => Env::get('OAUTH_APPLE_CLIENT_ID', ''),
            'client_secret' => Env::get('OAUTH_APPLE_CLIENT_SECRET', ''),
        ],
    ],

    'limits' => [
        // Post/thread body maximum (COMPOSER.md ~20,000 chars).
        'post_body_max' => 20000,
        'thread_title_max' => 160,
        'bio_max' => 1000,
        'display_name_max' => 64,
        'location_max' => 64,
        'username_max' => 32,
        'username_min' => 3,
        'password_min' => 8,
    ],

    // ── Phase 3 ──────────────────────────────────────────────────────────────

    // Image uploads (P3-04). Files are sniffed, re-encoded, and stored OUTSIDE
    // the executable/public path; delivery is authorization-gated per parent.
    'uploads' => [
        'max_bytes' => (int) Env::get('UPLOADS_MAX_BYTES', (string) (5 * 1024 * 1024)), // 5 MB
        'max_width' => (int) Env::get('UPLOADS_MAX_WIDTH', '4096'),
        'max_height' => (int) Env::get('UPLOADS_MAX_HEIGHT', '4096'),
        // Decompression-bomb guard: reject before decoding when w*h exceeds this.
        'max_pixels' => (int) Env::get('UPLOADS_MAX_PIXELS', (string) (24_000_000)),
        'allowed_mime' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        'per_post_max' => (int) Env::get('UPLOADS_PER_POST_MAX', '10'),
        // A brand-new account (below this many posts) cannot upload (anti-abuse).
        'new_user_min_posts' => (int) Env::get('UPLOADS_NEW_USER_MIN_POSTS', '0'),
        'storage_path' => Env::get('UPLOADS_PATH', dirname(__DIR__) . '/storage/media'),
        // Refuse new uploads when free space drops below this reserve.
        'min_free_bytes' => (int) Env::get('UPLOADS_MIN_FREE_BYTES', (string) (256 * 1024 * 1024)),
        // Unfinalised temp uploads older than this are swept by worker:attachments.
        'temp_ttl_hours' => (int) Env::get('UPLOADS_TEMP_TTL_HOURS', '24'),
        // Media of a soft-deleted post is only reclaimed after this grace window,
        // so a restored/appealed post keeps its images (PHASE_3_PLAN §8.5).
        'deleted_grace_days' => (int) Env::get('UPLOADS_DELETED_GRACE_DAYS', '30'),
    ],

    // Central anti-abuse automation (P3-05). Defaults to OBSERVE so a fresh
    // install never silently holds/blocks legitimate content; an operator opts
    // up to flag → hold → block after reviewing false positives (PHASE_3_PLAN
    // §7 Milestone 3 / §13.1 step 7). Per-rule modes can also live in `settings`.
    'antiabuse' => [
        'mode' => Env::get('ANTIABUSE_MODE', 'observe'), // observe | flag | hold | block
        // A "new user" for throttling purposes is below either threshold.
        'new_user_min_posts' => (int) Env::get('ANTIABUSE_NEW_USER_MIN_POSTS', '3'),
        'new_user_min_age_minutes' => (int) Env::get('ANTIABUSE_NEW_USER_MIN_AGE_MINUTES', '0'),
        // Link ceilings (whole numbers). New users get the stricter limit.
        'new_user_max_links' => (int) Env::get('ANTIABUSE_NEW_USER_MAX_LINKS', '2'),
        'max_links' => (int) Env::get('ANTIABUSE_MAX_LINKS', '25'),
        // Identical body re-posted by the same user within this window = duplicate.
        'duplicate_window_seconds' => (int) Env::get('ANTIABUSE_DUP_WINDOW', '3600'),
        // More than N posts inside this window by one user = flood.
        'flood_window_seconds' => (int) Env::get('ANTIABUSE_FLOOD_WINDOW', '60'),
        'flood_max_posts' => (int) Env::get('ANTIABUSE_FLOOD_MAX', '10'),
        // Case-insensitive substring blocklist; operator-extendable via settings.
        'blocked_words' => [],
        // Spam-scoring provider seam (P3-05): an optional pluggable SpamScorer
        // contributes a severity from its [0,1] score. Default scorer abstains,
        // so these are inert until a provider is bound (first-party = Gate B).
        // Capped at hold — automated scoring never auto-blocks.
        'spam_flag_score' => (float) Env::get('ANTIABUSE_SPAM_FLAG_SCORE', '0.6'),
        'spam_hold_score' => (float) Env::get('ANTIABUSE_SPAM_HOLD_SCORE', '0.9'),
    ],

    // Named rate-limit policies (P3-05): [max_attempts, decay_seconds]. The
    // central RateLimitService keys these per account+client-ip.
    'rate_limits' => [
        'login' => [10, 900],
        'register' => [5, 3600],
        'post' => [30, 600],
        'dm' => [20, 600],
        'dm_report' => [10, 600],
        'upload' => [40, 3600],
        'composer_preview' => [120, 600],
        'composer_suggest' => [120, 60],
        'password_reset' => [5, 3600],
        'mfa_login' => [5, 900],
        'mfa_settings' => [10, 900],
        'api' => [120, 60],
        'webhook_test' => [20, 600],
        'email_test' => [20, 600],
        'announce' => [5, 3600],   // admin announcement publishes per admin (ADMIN §7.4)
    ],

    'secrets' => [
        // B2 service-secret registry (SecretVault, built on SecretBox).
        'rotation_grace_seconds' => 86400, // retired versions stay decryptable this long for rotation overlap
        'max_secret_bytes' => 4096,        // plaintext ceiling (fits VARBINARY(4096) ciphertext)
    ],

    'webhooks' => [
        'timeout_seconds' => 5,
        'max_attempts' => 6,
        'backoff_seconds' => [60, 300, 1500, 7200, 21600],
        'circuit_breaker_threshold' => 15,
        'max_response_bytes' => 65536,
        'allow_http' => Env::bool('WEBHOOK_ALLOW_HTTP', false),
        'allowed_private_cidrs' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('WEBHOOK_ALLOWED_PRIVATE_CIDRS', ''))))),
    ],

    'link_previews' => [
        'timeout_seconds' => (int) Env::get('LINK_PREVIEW_TIMEOUT_SECONDS', '4'),
        'max_bytes' => (int) Env::get('LINK_PREVIEW_MAX_BYTES', '262144'),
        'max_parse_bytes' => (int) Env::get('LINK_PREVIEW_MAX_PARSE_BYTES', '131072'),
        'allow_http' => Env::bool('LINK_PREVIEW_ALLOW_HTTP', false),
        'allowed_hosts' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('LINK_PREVIEW_ALLOWED_HOSTS', ''))))),
        'allowed_private_cidrs' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('LINK_PREVIEW_ALLOWED_PRIVATE_CIDRS', ''))))),
    ],

    'registry' => [
        'fetch_timeout_seconds' => (int) Env::get('REGISTRY_FETCH_TIMEOUT_SECONDS', '10'),
        'max_snapshot_bytes' => (int) Env::get('REGISTRY_MAX_SNAPSHOT_BYTES', '1048576'),
        'allow_http' => Env::bool('REGISTRY_ALLOW_HTTP', false),
        'allowed_private_cidrs' => array_values(array_filter(array_map('trim',
            explode(',', (string) Env::get('REGISTRY_ALLOWED_PRIVATE_CIDRS', ''))))),
    ],

    'packages' => [
        // Verified release documents, content-addressed by sha256. Never web-served.
        'storage_path' => Env::get('PACKAGES_STORAGE_PATH', dirname(__DIR__) . '/storage/packages'),
        // Default uninstall retention window when the manifest declares none.
        'retention_days' => (int) Env::get('PACKAGES_RETENTION_DAYS', '30'),
    ],

    'theme' => [
        // Emergency recovery: force the built-in system theme without mutating DB state.
        'safe_mode' => Env::bool('THEME_SAFE_MODE', false),
    ],

    'giphy' => [
        // Public browser API key only. The app never proxies, caches, rewrites, or
        // downloads GIPHY media; the picker uses GIPHY Search/Trending directly.
        'public_key' => Env::get('GIPHY_PUBLIC_KEY', ''),
        'rating' => Env::get('GIPHY_RATING', 'pg'),
    ],

    // Trusted reverse-proxy CIDRs whose X-Forwarded-For we honour for client IP.
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) Env::get('TRUSTED_PROXIES', ''))))),

    // Data retention (P3-05 / ADMIN §5.5): purge/anonymise captured IPs after N days.
    'retention' => [
        'ip_days' => (int) Env::get('RETENTION_IP_DAYS', '90'),
    ],
];
