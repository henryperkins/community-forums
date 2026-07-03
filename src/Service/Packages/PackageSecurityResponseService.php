<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Config;
use App\Core\Database;
use App\Domain\User;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Repository\SettingRepository;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;

/**
 * Local operator security-response console read model + the flag-independent
 * emergency execution brake. Reuses — never duplicates — the advisory
 * ({@see RegistryAdvisoryService}), blocklist ({@see LocalBlocklistService}),
 * and health-enforcement ({@see PackageHealthService}) services; those are held
 * so the console's advisory/blocklist/force-disable actions delegate to the
 * existing source-of-truth logic instead of re-deriving it. The emergency brake
 * is a plain DB setting OR'd with a break-glass config so it holds even while
 * `package_registry` is dark. Mirrors the ThemeStateService safe-mode precedent.
 */
final class PackageSecurityResponseService
{
    private const SETTING_KEY = 'package_execution_disabled';

    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private RegistryAdvisoryService $advisories,
        private LocalBlocklistService $blocklist,
        private PackageHealthService $enforcement,
        private PackageIntegrationService $integrations,
        private PackagePublisherRepository $publishers,
        private PublisherSigningKeyRepository $publisherKeys,
        private PackageAdvisoryRepository $advisoryRepo,
        private LocalPackageBlockRepository $blockRepo,
        private PackageTransparencyLogRepository $transparency,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private ModerationLogRepository $audit,
        private Config $config,
    ) {
    }

    /** DB setting OR the packages.execution_disabled config break-glass. Flag-independent. */
    public function isExecutionDisabled(): bool
    {
        if ((bool) $this->settings->get(self::SETTING_KEY, false)) {
            return true;
        }

        return (bool) $this->config->get('packages.execution_disabled', false);
    }

    /**
     * Flag-independent emergency execution brake. Reauths in both directions.
     * On disable: pauses every active integration install's package-owned
     * webhooks (so the delivery worker/dispatch skip them) and records
     * transparency; the runtime credential-auth path denies via isExecutionDisabled().
     * Never blocks view/revoke/export/uninstall — install lifecycle state is untouched.
     *
     * @return int affected active integration installs (0 when re-enabling; no auto-resume)
     */
    public function setExecutionDisabled(User $admin, string $currentPassword, bool $disabled, ?string $reason): int
    {
        $this->writeGate->assertCanWrite($admin);
        $this->reauth->requirePassword($admin, $currentPassword);

        $installs = $this->activeIntegrationInstalls();
        $cleanReason = $reason === null || trim($reason) === '' ? null : mb_substr(trim($reason), 0, 255);

        $this->db->transaction(function () use ($admin, $disabled, $cleanReason, $installs): void {
            $this->settings->set(self::SETTING_KEY, $disabled);

            if ($disabled) {
                foreach ($installs as $install) {
                    // Defensive pause (no reauth): is_active=0 so the existing delivery worker skips it.
                    $this->integrations->suspendDelivery((int) $install['id'], 'package execution disabled');
                    $this->transparency->record([
                        'package_uid' => (string) $install['package_uid'],
                        'version' => $install['release_version'] ?? null,
                        'digest' => $install['digest'] ?? null,
                        'event' => 'force_disable',
                        'source' => 'local',
                        'actor_id' => $admin->id(),
                        'detail' => json_encode(
                            ['reason' => 'package_execution_disabled', 'note' => $cleanReason],
                            JSON_UNESCAPED_SLASHES,
                        ),
                    ]);
                }
            }

            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => $disabled ? 'package_execution_disabled' : 'package_execution_enabled',
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => $cleanReason,
                'after' => ['disabled' => $disabled, 'affected_installs' => count($installs)],
            ]);
        });

        return $disabled ? count($installs) : 0;
    }

    /**
     * /admin/packages/security read model. Publisher/advisory/blocklist rows come
     * straight from the shared repositories — this console is a viewer, not a
     * second source of truth.
     *
     * @return array{publishers:list<array<string,mixed>>, advisories:list<array<string,mixed>>, blocklist:list<array<string,mixed>>, transparency:list<array<string,mixed>>, execution_disabled:bool, affected_installs:int}
     */
    public function overview(?\DateTimeImmutable $now = null): array
    {
        return [
            'publishers' => $this->publishers->all(),
            'advisories' => $this->advisoryRepo->all(),
            'blocklist' => $this->blockRepo->all(),
            'transparency' => $this->transparency->all(50),
            'execution_disabled' => $this->isExecutionDisabled(),
            'affected_installs' => count($this->activeIntegrationInstalls()),
        ];
    }

    /**
     * @return array{publisher:array<string,mixed>, keys:list<array<string,mixed>>, packages:list<array<string,mixed>>, decisions:list<array<string,mixed>>}|null
     */
    public function publisherDetail(int $publisherId): ?array
    {
        $publisher = $this->publishers->find($publisherId);
        if ($publisher === null) {
            return null;
        }

        return [
            'publisher' => $publisher,
            'keys' => $this->publisherKeys->forPublisher($publisherId),
            'packages' => $this->publishers->packagesFor($publisherId),
            'decisions' => [],   // per-package review decisions are merged by the controller via PackageReviewConsoleService
        ];
    }

    /** @return list<array<string,mixed>> enabled remote_app/automation installs (integration bridges) */
    private function activeIntegrationInstalls(): array
    {
        return $this->db->fetchAll(
            "SELECT ip.id, ip.digest, p.package_uid, r.version AS release_version
               FROM installed_packages ip
               JOIN packages p ON p.id = ip.package_id
               LEFT JOIN package_releases r ON r.id = ip.release_id
              WHERE ip.state = 'enabled'
                AND p.type IN ('remote_app', 'automation')
              ORDER BY ip.id",
        );
    }
}
