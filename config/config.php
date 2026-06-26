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

    'mail' => [
        // 'sendmail' uses PHP mail(); swap to an SMTP/provider adapter behind the
        // App\Mail\Mailer interface later. Empty `from` ⇒ not configured ⇒ email
        // fails closed (in-app notifications still deliver).
        'driver' => Env::get('MAIL_DRIVER', 'sendmail'),
        'from' => Env::get('MAIL_FROM', ''),
        'from_name' => Env::get('MAIL_FROM_NAME', 'RetroBoards'),
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
];
