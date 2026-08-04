<?php

declare(strict_types=1);

namespace App\Mail;

use App\Core\Config;

/**
 * The one place a Mailer is chosen from configuration.
 *
 * The kernel and `bin/console` both need a transport, and both used to build it
 * from a private copy of the same logic ("matches App::buildContainer" was the
 * console's comment). That drifts the moment a driver is added — and a cron
 * worker quietly sending through a different transport than the web app is the
 * kind of split that only shows up as "some emails never arrive".
 */
final class MailerFactory
{
    /**
     * @param callable():string $siteName resolves the From display name when no
     *        MAIL_FROM_NAME is set. Deferred and supplied by the caller because
     *        it reads the settings table, which may not exist yet — the caller
     *        owns that fallback.
     */
    public static function fromConfig(Config $config, callable $siteName): Mailer
    {
        $mail = (array) $config->get('mail', []);
        $driver = (string) ($mail['driver'] ?? 'sendmail');

        if ($driver === 'array') {
            return new ArrayMailer();
        }

        $fromName = (string) ($mail['from_name'] ?? '');
        if ($fromName === '') {
            $fromName = $siteName();
        }
        $from = (string) ($mail['from'] ?? '');

        if ($driver === 'cloudflare') {
            $cloudflare = (array) ($mail['cloudflare'] ?? []);
            return new CloudflareMailer(
                new CurlCloudflareTransport(),
                (string) ($cloudflare['account_id'] ?? ''),
                (string) ($cloudflare['api_token'] ?? ''),
                $from,
                $fromName,
                (int) ($cloudflare['timeout'] ?? 10),
            );
        }

        return new SendmailMailer($from, $fromName);
    }
}
