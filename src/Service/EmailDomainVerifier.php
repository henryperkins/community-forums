<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Config;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\SettingRepository;
use Throwable;

final class EmailDomainVerifier
{
    public const BLOCKED_REASON = 'Email sending is blocked until SPF and DKIM pass.';

    public function __construct(
        private Config $config,
        private SettingRepository $settings,
        private EmailDomainStatusRepository $statuses,
    ) {
    }

    /** @return array<string,mixed> */
    public function current(): array
    {
        $domain = $this->fromDomain();
        $selector = $this->selector();
        $row = $domain !== '' ? $this->statuses->find($domain) : null;
        $spf = (string) ($row['spf_status'] ?? 'unknown');
        $dkim = (string) ($row['dkim_status'] ?? 'unknown');

        return [
            'domain' => $domain,
            'dkim_selector' => (string) ($row['dkim_selector'] ?? $selector),
            'spf_status' => $spf,
            'dkim_status' => $dkim,
            'checked_at' => $row['checked_at'] ?? null,
            'details' => $row['details'] ?? [],
            'required' => $this->requiresVerifiedDomain(),
            'allowed' => !$this->requiresVerifiedDomain() || ($spf === 'pass' && $dkim === 'pass'),
        ];
    }

    /** @return array<string,mixed> */
    public function verify(): array
    {
        $domain = $this->fromDomain();
        if ($domain === '') {
            return $this->current();
        }

        $selector = $this->selector();
        $spfRecords = $this->txt($domain);
        $dkimHost = $selector . '._domainkey.' . $domain;
        $dkimRecords = $this->txt($dkimHost);
        $spf = $this->containsToken($spfRecords, 'v=spf1') ? 'pass' : 'fail';
        $dkim = $this->containsToken($dkimRecords, 'v=DKIM1') ? 'pass' : 'fail';

        $this->statuses->save($domain, $selector, $spf, $dkim, [
            'spf_host' => $domain,
            'spf_records' => $spfRecords,
            'dkim_host' => $dkimHost,
            'dkim_records' => $dkimRecords,
        ]);

        return $this->current();
    }

    public function blockedReason(): ?string
    {
        $status = $this->current();
        if (!empty($status['required']) && empty($status['allowed'])) {
            return self::BLOCKED_REASON;
        }
        return null;
    }

    public function requiresVerifiedDomain(): bool
    {
        $setting = $this->settings->get('email_require_verified_domain', null);
        if (is_bool($setting)) {
            return $setting;
        }
        return (bool) $this->config->get('mail.require_verified_domain', false);
    }

    public function fromDomain(): string
    {
        $from = strtolower(trim((string) $this->config->get('mail.from', '')));
        $at = strrpos($from, '@');
        if ($at === false) {
            return '';
        }
        return trim(substr($from, $at + 1), " \t\n\r\0\x0B.<>");
    }

    public function selector(): string
    {
        $setting = $this->settings->getString('email_dkim_selector', '');
        $selector = $setting !== '' ? $setting : (string) $this->config->get('mail.dkim_selector', 'default');
        $selector = strtolower(trim($selector));
        return preg_match('/^[a-z0-9._-]{1,64}$/', $selector) === 1 ? $selector : 'default';
    }

    /** @return list<string> */
    private function txt(string $host): array
    {
        $host = strtolower($host);
        $fixture = $this->settings->get('email_dns_txt_records', []);
        if (is_array($fixture) && array_key_exists($host, $fixture)) {
            $records = $fixture[$host];
            if (is_array($records)) {
                return array_values(array_filter(array_map('strval', $records), static fn (string $v): bool => $v !== ''));
            }
            if (is_string($records) && $records !== '') {
                return [$records];
            }
        }

        try {
            $dns = dns_get_record($host, DNS_TXT);
        } catch (Throwable) {
            return [];
        }
        if (!is_array($dns)) {
            return [];
        }
        $out = [];
        foreach ($dns as $row) {
            if (isset($row['txt']) && is_string($row['txt'])) {
                $out[] = $row['txt'];
            }
        }
        return $out;
    }

    /** @param list<string> $records */
    private function containsToken(array $records, string $needle): bool
    {
        foreach ($records as $record) {
            if (stripos($record, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
