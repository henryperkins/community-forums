<?php

declare(strict_types=1);

/** Disposable local fixture for the selector/cache browser regression. */
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\PasswordHasher;

$root = dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if ($config->get('app.env') !== 'test'
    || !preg_match('/^retroboards_e2e_dkim_[a-z0-9_]+$/', (string) $config->get('db.database'))
    || !in_array($config->get('db.host'), ['127.0.0.1', 'localhost'], true)
    || $config->get('db.socket') !== ''
    || $config->get('mail.driver') !== 'array'
    || $config->get('mail.from') !== 'dkim-evidence@example.test'
    || $config->get('mail.dkim_selector') !== 'cf2024-1') {
    throw new RuntimeException('Use the private local DKIM evidence database, array mail, example.test sender and cf2024-1 selector.');
}
$db = new Database($config->get('db'));
$command = $argv[1] ?? 'inspect';
if (in_array($command, ['seed-failed', 'seed-passed'], true)) {
    $db->transaction(function () use ($db, $command): void {
        $users = new UserRepository($db);
        $admin = $users->findByUsername('dkim-admin');
        if ($admin === null) {
            $id = $users->create([
                'username' => 'dkim-admin',
                'email' => 'dkim-admin@retro.test',
                'display_name' => 'DKIM evidence admin',
                'password_hash' => (new PasswordHasher())->hash('password123'),
                'role' => 'admin',
                'status' => 'active',
            ]);
            $db->run('UPDATE users SET email_verified_at = UTC_TIMESTAMP(), onboarded_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
            (new UserPreferenceRepository($db))->merge($id, ['theme' => 'light']);
        }
        $settings = new SettingRepository($db);
        $settings->set('installed_at', '2026-10-18 00:00:00');
        $settings->set('site_name', 'Email selector regression');
        $settings->set('features', ['email' => true]);
        $settings->set('email_require_verified_domain', true);
        // The browser must prove environment/config wiring, with no selector override.
        $db->run('DELETE FROM settings WHERE `key` = ?', ['email_dkim_selector']);
        $settings->set('email_dns_txt_records', [
            'example.test' => ['v=spf1 include:_spf.example.test -all'],
            'cf2024-1._domainkey.example.test' => ['v=DKIM1; k=rsa; p=fixture-public-key'],
            'default._domainkey.example.test' => [],
        ]);
        (new EmailDomainStatusRepository($db))->save('example.test', 'default', 'pass',
            $command === 'seed-passed' ? 'pass' : 'fail', [
                'spf_host' => 'example.test',
                'dkim_host' => 'default._domainkey.example.test',
                'dkim_records' => $command === 'seed-passed' ? ['v=DKIM1; p=old-fixture-key'] : [],
            ]);
        $db->run('UPDATE email_domain_status SET checked_at = ? WHERE domain = ?', ['2020-01-01 00:00:00', 'example.test']);
    });
} elseif ($command !== 'inspect') {
    throw new InvalidArgumentException('Unknown fixture command.');
}

$row = (new EmailDomainStatusRepository($db))->find('example.test');
fwrite(STDOUT, json_encode([
    'database' => $config->get('db.database'),
    'mail_driver' => $config->get('mail.driver'),
    'configured_selector' => $config->get('mail.dkim_selector'),
    'selector_override' => (new SettingRepository($db))->get('email_dkim_selector'),
    'cached_status' => $row,
    'refresh_audit_count' => (int) $db->fetchValue('SELECT COUNT(*) FROM moderation_log WHERE action = ?', ['email_domain_verified']),
    'email_delivery_count' => (int) $db->fetchValue('SELECT COUNT(*) FROM email_deliveries'),
], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . PHP_EOL);
