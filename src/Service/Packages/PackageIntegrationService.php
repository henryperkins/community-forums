<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ApiTokenRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\SettingRepository;
use App\Repository\WebhookRepository;
use App\Security\ApiScopes;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackagePolicyException;
use App\Security\WebhookEvents;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\ApiTokenService;
use App\Service\SecretVault;
use App\Service\WebhookService;

/**
 * Install-scoped integration runtime for remote_app/automation packages:
 * package-owned credential provisioning + event/scope gating over the B2 seams.
 * This file's api_token surface is owned by the api-token-provisioning group;
 * webhook minting/delivery + overview/onInstallIneligible land with sibling groups.
 */
final class PackageIntegrationService
{
    /** Per-install setting key that carries the package-owned webhook destination URL. */
    public const WEBHOOK_URL_SETTING = 'webhook_url';

    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private InstalledPackageRepository $installs,
        private InstalledPackagePermissionRepository $permissions,
        private InstalledPackageSettingsRepository $settings,
        private InstalledPackageCredentialRepository $credentials,
        private ApiTokenService $apiTokens,
        private WebhookService $webhooks,
        private ApiTokenRepository $apiTokenRepo,
        private WebhookRepository $webhookRepo,
        private SecretVault $vault,
        private ManifestValidator $manifests,
        private PackageHistoryRepository $history,
        private PackageTransparencyLogRepository $transparency,
        private ModerationLogRepository $audit,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private FeatureFlags $flags,
        private SettingRepository $settingRepo,
        private Config $config,
    ) {
    }

    /**
     * Atomically mint the package-owned api_token from its declared+granted api_scopes.
     * All guards fail before the transaction, minting nothing.
     *
     * @return array{api_token:?string, webhook_secret:?string, credentials:list<array<string,mixed>>}
     */
    public function provisionCredentials(User $admin, string $currentPassword, int $installedId): array
    {
        $this->writeGate->assertCanWrite($admin);

        $install = $this->installs->find($installedId);
        if ($install === null) {
            throw new PackagePolicyException('unknown_install', 'No such installed package.');
        }
        $package = $this->packages->find((int) $install['package_id']);
        if ($package === null) {
            throw new PackagePolicyException('invalid_state', 'The installed package is no longer resolvable.');
        }
        if (!in_array((string) $package['type'], ['remote_app', 'automation'], true)) {
            throw new PackagePolicyException('not_integrable', 'Only remote_app and automation packages expose integration credentials.');
        }
        if ((string) $install['state'] !== 'enabled') {
            throw new PackagePolicyException('invalid_state', 'The package must be enabled before provisioning credentials.');
        }
        if ($this->isExecutionDisabled()) {
            throw new PackagePolicyException('execution_disabled', 'Package execution is under an emergency disable.');
        }
        if ($this->permissions->ungrantedCount($installedId) > 0) {
            throw new PackagePolicyException('not_consented', 'Grant every declared permission before provisioning credentials.');
        }
        if (!$this->flags->enabled('service_secrets')) {
            // Hard predecessor / kill switch: fail closed, mint nothing.
            throw new ValidationException(['integration' => 'Enable the secret vault (service_secrets) before minting credentials.']);
        }
        $this->reauth->requirePassword($admin, $currentPassword);

        $scopes = $this->grantedApiScopes($installedId, (int) $install['package_id'], $admin);

        return $this->db->transaction(function () use ($admin, $currentPassword, $installedId, $install, $package, $scopes): array {
            $this->lockInstallForCredentialProvision($installedId);

            $token = null;
            $webhookSecret = null;

            // Mint the webhook first so a later token-mint failure rolls it back
            // (WebhookService::register joins this shared transaction — no savepoints).
            if ($this->grantedEvents($installedId) !== []) {
                $mintedHook = $this->mintPackageWebhook($admin, $currentPassword, $package, $installedId);
                $webhookSecret = $mintedHook['secret'];
            }

            // ≤1 api_token: only when the install actually grants a locally-supported scope.
            if ($scopes !== []) {
                $this->assertNoActiveCredential($installedId, 'api_token');
                $label = $this->credentialLabel((string) $package['package_uid'], $installedId);
                $minted = $this->apiTokens->mint($admin, $currentPassword, $label, $scopes, null);
                $token = (string) $minted['token'];
                $scopesJson = json_encode($scopes, JSON_UNESCAPED_SLASHES) ?: '[]';
                $linkId = $this->credentials->insertApiToken($installedId, (int) $minted['id'], $label, $scopesJson, $admin->id());

                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => $installedId,
                    'event' => 'credential_mint',
                    'actor_id' => $admin->id(),
                    'detail' => json_encode(['kind' => 'api_token', 'credential_id' => $linkId, 'scopes' => $scopes], JSON_UNESCAPED_SLASHES),
                ]);
                $release = $install['release_id'] !== null ? $this->releases->find((int) $install['release_id']) : null;
                $this->transparency->record([
                    'package_uid' => (string) $package['package_uid'],
                    'version' => $release !== null ? (string) $release['version'] : null,
                    'digest' => (string) $install['digest'],
                    'event' => 'install',
                    'source' => 'local',
                    'actor_id' => $admin->id(),
                    'detail' => 'credential_mint:api_token',
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_credential_mint',
                    'target_type' => 'package',
                    'target_id' => (int) $install['package_id'],
                    'after' => ['kind' => 'api_token', 'credential_id' => $linkId, 'scopes' => $scopes],
                ]);
            }

            return [
                'api_token' => $token,
                'webhook_secret' => $webhookSecret,
                'credentials' => $this->credentials->activeForInstall($installedId),
            ];
        });
    }

    /**
     * Rotate one api_token credential: tokens have no in-place rotate, so revoke the old and
     * mint a replacement inside one transaction. @return array{secret:?string,token:?string} shown once.
     */
    public function rotateCredential(User $admin, string $currentPassword, int $installedId, int $credentialId): array
    {
        $this->writeGate->assertCanWrite($admin);

        $link = $this->credentials->find($credentialId);
        if ($link === null || (int) $link['installed_package_id'] !== $installedId || $link['revoked_at'] !== null) {
            throw new PackagePolicyException('unknown_credential', 'No active credential to rotate.');
        }
        if ($this->isExecutionDisabled()) {
            throw new PackagePolicyException('execution_disabled', 'Package execution is under an emergency disable.');
        }
        if (!$this->flags->enabled('service_secrets')) {
            throw new ValidationException(['integration' => 'Enable the secret vault (service_secrets) before rotating credentials.']);
        }

        // Webhook: rotate the signing secret in place (WebhookService::rotateSecret reauths).
        if ((string) $link['kind'] === 'webhook') {
            if ($link['webhook_id'] === null) {
                throw new PackagePolicyException('unknown_credential', 'No webhook endpoint to rotate.');
            }
            $secret = $this->webhooks->rotateSecret($admin, $currentPassword, (int) $link['webhook_id']);
            return ['secret' => $secret, 'token' => null];
        }

        $this->reauth->requirePassword($admin, $currentPassword);

        $install = $this->installs->find($installedId);
        $package = $install !== null ? $this->packages->find((int) $install['package_id']) : null;
        if ($install === null || $package === null) {
            throw new PackagePolicyException('invalid_state', 'The installed package is no longer resolvable.');
        }
        $scopes = $this->grantedApiScopes($installedId, (int) $install['package_id'], $admin);
        if ($scopes === []) {
            throw new PackagePolicyException('no_scopes', 'This install no longer grants any API scope to mint.');
        }

        return $this->db->transaction(function () use ($admin, $currentPassword, $installedId, $install, $package, $link, $scopes): array {
            $this->apiTokens->revoke($admin, (int) $link['api_token_id']);
            $this->credentials->markRevoked((int) $link['id']);
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'credential_revoke',
                'actor_id' => $admin->id(),
                'detail' => json_encode(['kind' => 'api_token', 'credential_id' => (int) $link['id'], 'reason' => 'rotate'], JSON_UNESCAPED_SLASHES),
            ]);

            $label = $this->credentialLabel((string) $package['package_uid'], $installedId);
            $minted = $this->apiTokens->mint($admin, $currentPassword, $label, $scopes, null);
            $newLinkId = $this->credentials->insertApiToken(
                $installedId,
                (int) $minted['id'],
                $label,
                json_encode($scopes, JSON_UNESCAPED_SLASHES) ?: '[]',
                $admin->id(),
            );
            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'credential_mint',
                'actor_id' => $admin->id(),
                'detail' => json_encode(['kind' => 'api_token', 'credential_id' => $newLinkId, 'scopes' => $scopes, 'rotated_from' => (int) $link['id']], JSON_UNESCAPED_SLASHES),
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_credential_rotate',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'after' => ['kind' => 'api_token', 'credential_id' => $newLinkId, 'rotated_from' => (int) $link['id']],
            ]);

            return ['secret' => null, 'token' => (string) $minted['token']];
        });
    }

    /**
     * Revoke one api_token credential (friction-free defensive action: WriteGate only, no reauth).
     * Idempotent: a no-op revoke forges no audit row.
     */
    public function revokeCredential(User $admin, int $installedId, int $credentialId): void
    {
        $this->writeGate->assertCanWrite($admin);

        $link = $this->credentials->find($credentialId);
        if ($link === null || (int) $link['installed_package_id'] !== $installedId) {
            return;
        }
        $install = $this->installs->find($installedId);

        // Webhook: idempotent defensive revoke — mark the link revoked, then delete the endpoint.
        if ((string) $link['kind'] === 'webhook') {
            if ($this->credentials->markRevoked((int) $link['id']) !== 1) {
                return; // already revoked -> idempotent no-op
            }
            if ($link['webhook_id'] !== null) {
                $this->webhooks->delete($admin, (int) $link['webhook_id']);
            }
            if ($install !== null) {
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => $installedId,
                    'event' => 'credential_revoke',
                    'actor_id' => $admin->id(),
                    'detail' => json_encode(['kind' => 'webhook', 'credential_id' => (int) $link['id']], JSON_UNESCAPED_SLASHES),
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_credential_revoke',
                    'target_type' => 'package',
                    'target_id' => (int) $install['package_id'],
                    'after' => ['kind' => 'webhook', 'credential_id' => (int) $link['id']],
                ]);
            }
            return;
        }

        $this->db->transaction(function () use ($admin, $installedId, $install, $link): void {
            if ($link['api_token_id'] !== null) {
                $this->apiTokens->revoke($admin, (int) $link['api_token_id']);
            }
            if ($this->credentials->markRevoked((int) $link['id']) === 1 && $install !== null) {
                $this->history->record([
                    'package_id' => (int) $install['package_id'],
                    'installed_package_id' => $installedId,
                    'event' => 'credential_revoke',
                    'actor_id' => $admin->id(),
                    'detail' => json_encode(['kind' => 'api_token', 'credential_id' => (int) $link['id']], JSON_UNESCAPED_SLASHES),
                ]);
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_credential_revoke',
                    'target_type' => 'package',
                    'target_id' => (int) $install['package_id'],
                    'after' => ['kind' => 'api_token', 'credential_id' => (int) $link['id']],
                ]);
            }
        });
    }

    /** True when the package_execution_disabled DB setting OR packages.execution_disabled config is set. */
    public function isExecutionDisabled(): bool
    {
        if ((bool) $this->config->get('packages.execution_disabled', false)) {
            return true;
        }
        try {
            return $this->settingRepo->getString('package_execution_disabled', '') === '1';
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Manifest = ceiling, grants = authority, local code = final gate: only declared+granted
     * api_scopes that ApiScopes still supports are minted; unknown/future scopes are denied
     * and audited (TM-SC-08), never included.
     *
     * @return list<string>
     */
    private function grantedApiScopes(int $installedId, int $packageId, User $admin): array
    {
        $scopes = [];
        foreach ($this->permissions->forInstall($installedId) as $row) {
            if ((string) $row['kind'] !== 'api_scope' || (int) $row['declared'] !== 1 || (int) $row['granted'] !== 1) {
                continue;
            }
            $key = (string) $row['permission_key'];
            if (!ApiScopes::isValid($key)) {
                $this->audit->log([
                    'actor_id' => $admin->id(),
                    'action' => 'package_scope_denied',
                    'target_type' => 'package',
                    'target_id' => $packageId,
                    'after' => ['installed_package_id' => $installedId, 'scope' => $key],
                ]);
                continue;
            }
            if (!in_array($key, $scopes, true)) {
                $scopes[] = $key;
            }
        }

        return $scopes;
    }

    private function credentialLabel(string $packageUid, int $installedId): string
    {
        // ApiTokenService caps names at 80 chars; keep the uid + install id inside that.
        return mb_substr('pkg:' . $packageUid . '#' . $installedId, 0, 80);
    }

    private function lockInstallForCredentialProvision(int $installedId): void
    {
        // Serializes concurrent plain-form POSTs so the app-enforced
        // "≤1 active credential per kind" invariant cannot race.
        $this->db->fetch('SELECT id FROM installed_packages WHERE id = ? FOR UPDATE', [$installedId]);
    }

    private function assertNoActiveCredential(int $installedId, string $kind): void
    {
        foreach ($this->credentials->activeForInstall($installedId) as $cred) {
            if ((string) $cred['kind'] === $kind) {
                throw new PackagePolicyException('credential_exists', 'This install already has an active ' . $kind . ' credential.');
            }
        }
    }

    /** @return list<string> granted, declared event permission keys. */
    private function grantedEvents(int $installedId): array
    {
        $events = [];
        foreach ($this->permissions->forInstall($installedId) as $p) {
            if ((string) $p['kind'] === 'event' && (int) $p['declared'] === 1 && (int) $p['granted'] === 1) {
                $events[] = (string) $p['permission_key'];
            }
        }
        return $events;
    }

    /** @return list<string> granted, declared outbound-host permission keys (lowercased). */
    private function grantedOutboundHosts(int $installedId): array
    {
        $hosts = [];
        foreach ($this->permissions->forInstall($installedId) as $p) {
            if ((string) $p['kind'] === 'outbound_host' && (int) $p['declared'] === 1 && (int) $p['granted'] === 1) {
                $hosts[] = strtolower((string) $p['permission_key']);
            }
        }
        return $hosts;
    }

    private function webhookUrlSetting(int $installedId): string
    {
        $row = $this->settings->find($installedId, self::WEBHOOK_URL_SETTING);
        $url = $row !== null && $row['value_json'] !== null ? json_decode((string) $row['value_json'], true) : null;
        if (!is_string($url) || $url === '') {
            throw new ValidationException([self::WEBHOOK_URL_SETTING => 'Set the destination URL before provisioning a webhook.']);
        }
        return $url;
    }

    /**
     * Mint one package-owned webhook. Runs after the provision guards, before the api_token
     * mint. Enforces event ⊆ WebhookEvents::domainEvents() (ping/unknown denied — TM-SC-08)
     * and host ∈ granted outbound_hosts (or the config test origin).
     * @param array<string,mixed> $package
     * @return array{webhook_id:int, secret:string, events:list<string>}
     */
    private function mintPackageWebhook(User $admin, string $currentPassword, array $package, int $installedId): array
    {
        $events = $this->grantedEvents($installedId);
        $domain = WebhookEvents::domainEvents();
        foreach ($events as $event) {
            if (!array_key_exists($event, $domain)) {
                throw new PackagePolicyException('event_not_grantable', 'Event "' . $event . '" cannot be delivered to a package.');
            }
        }

        $url = $this->webhookUrlSetting($installedId);
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = $this->grantedOutboundHosts($installedId);
        $testOrigin = strtolower((string) $this->config->get('packages.integration_test_origin', ''));
        if ($host === '' || (!in_array($host, $allowed, true) && ($testOrigin === '' || $host !== $testOrigin))) {
            throw new ValidationException([self::WEBHOOK_URL_SETTING => 'Destination host is not a granted outbound host.']);
        }
        $this->assertNoActiveCredential($installedId, 'webhook');

        $label = 'pkg:' . (string) $package['package_uid'] . '#' . $installedId;
        $result = $this->webhooks->register($admin, $currentPassword, $label, $url, $events);
        $this->credentials->insertWebhook(
            $installedId,
            (int) $result['id'],
            $label,
            json_encode(array_values($events), JSON_UNESCAPED_SLASHES) ?: '[]',
            $admin->id(),
        );
        $this->history->record([
            'package_id' => (int) $package['id'],
            'installed_package_id' => $installedId,
            'event' => 'credential_mint',
            'actor_id' => $admin->id(),
            'detail' => 'webhook:' . implode(',', $events),
        ]);
        $this->transparency->record([
            'package_uid' => (string) $package['package_uid'],
            'event' => 'install',
            'source' => 'local',
            'actor_id' => $admin->id(),
            'detail' => 'webhook credential minted',
        ]);
        $this->audit->log([
            'actor_id' => $admin->id(),
            'action' => 'package_credential_mint',
            'target_type' => 'package',
            'target_id' => (int) $package['id'],
            'after' => ['kind' => 'webhook', 'events' => $events],
        ]);

        return ['webhook_id' => (int) $result['id'], 'secret' => (string) $result['secret'], 'events' => $events];
    }

    /**
     * Pause all package-owned webhook endpoints for an install so the delivery worker /
     * dispatch() naturally skip them (is_active=0). Friction-free defensive action — no reauth.
     * @return int endpoints paused.
     */
    public function suspendDelivery(int $installedId, string $reason): int
    {
        $paused = 0;
        foreach ($this->credentials->activeForInstall($installedId) as $cred) {
            if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
                $paused += $this->webhookRepo->disable((int) $cred['webhook_id'], substr('Package delivery paused: ' . $reason, 0, 190));
            }
        }
        return $paused;
    }

    /**
     * Re-enable package-owned webhook endpoints on install re-enable. Refused while
     * emergency-disabled or when the install is not enabled. @return int endpoints resumed.
     */
    public function resumeDelivery(User $admin, int $installedId): int
    {
        $this->writeGate->assertCanWrite($admin);
        if ($this->isExecutionDisabled()) {
            throw new PackagePolicyException('execution_disabled', 'Package execution is globally disabled.');
        }
        $install = $this->installs->find($installedId);
        if ($install === null || (string) $install['state'] !== 'enabled') {
            throw new PackagePolicyException('invalid_state', 'Only enabled installs can resume delivery.');
        }
        $resumed = 0;
        foreach ($this->credentials->activeForInstall($installedId) as $cred) {
            if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
                $resumed += $this->webhookRepo->enable((int) $cred['webhook_id']);
            }
        }
        return $resumed;
    }

    /**
     * Lifecycle hook (mirrors ThemeStateService::onInstallIneligible): revoke every package-owned
     * credential and pause its webhook endpoints before an install's state flips inactive.
     * Idempotent, no reauth. reason ∈ disabled|uninstalled|quarantined|force_disabled|emergency_disabled.
     */
    public function onInstallIneligible(int $installedId, string $reason, ?int $actorId): void
    {
        foreach ($this->credentials->activeForInstall($installedId) as $cred) {
            if ((string) $cred['kind'] === 'webhook' && $cred['webhook_id'] !== null) {
                $this->webhookRepo->disable((int) $cred['webhook_id'], substr('Install ineligible: ' . $reason, 0, 190));
            } elseif ((string) $cred['kind'] === 'api_token' && $cred['api_token_id'] !== null) {
                $this->apiTokenRepo->revoke((int) $cred['api_token_id']);
            }
            $this->credentials->markRevoked((int) $cred['id']);
        }
    }
}
