<?php

declare(strict_types=1);

namespace App\Service\Packages;

use App\Core\Config;
use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageRepository;
use App\Security\Packages\ManifestValidator;
use App\Security\ReauthGate;
use App\Security\WriteGate;
use App\Service\SecretVault;

final class PackageSettingsService
{
    private const MAX_SETTING_BYTES = 4096;

    public function __construct(
        private Database $db,
        private PackageRepository $packages,
        private PackageReleaseRepository $releases,
        private InstalledPackageRepository $installs,
        private InstalledPackageSettingsRepository $settings,
        private SecretVault $vault,
        private ManifestValidator $manifests,
        private PackageHistoryRepository $history,
        private ModerationLogRepository $audit,
        private ReauthGate $reauth,
        private WriteGate $writeGate,
        private FeatureFlags $flags,
        private Config $config,
    ) {
    }

    /**
     * Pure: validate submitted $input against manifest settings_schema $fields.
     *
     * @param list<array<string,mixed>> $fields  manifest settings_schema['fields']
     * @param array<string,mixed> $input         raw submitted values (form strings)
     * @return array{values:array<string,int|bool|string>, secrets:array<string,string>}
     * @throws ValidationException on unknown key / missing required / bad select / oversize / type mismatch
     */
    public static function validateInput(array $fields, array $input): array
    {
        $known = [];
        foreach ($fields as $field) {
            $known[(string) $field['key']] = $field;
        }

        $errors = [];
        foreach (array_keys($input) as $key) {
            if (!isset($known[(string) $key])) {
                $errors[(string) $key] = 'Unknown setting: ' . $key . '.';
            }
        }

        $values = [];
        $secrets = [];
        foreach ($known as $key => $field) {
            $key = (string) $key;
            $type = (string) $field['type'];
            $label = (string) $field['label'];
            $required = ($field['required'] ?? false) === true;
            $raw = $input[$key] ?? null;

            if (($field['secret'] ?? false) === true) {
                $val = is_string($raw) ? $raw : '';
                if ($val === '') {
                    continue; // empty = leave unchanged; required-secret enforced in save()
                }
                if (strlen($val) > self::MAX_SETTING_BYTES) {
                    $errors[$key] = $label . ' is too long.';
                } else {
                    $secrets[$key] = $val;
                }
                continue;
            }

            if ($type === 'boolean') {
                $values[$key] = in_array($raw, ['1', 'on', 'true', true, 1], true);
                continue;
            }

            if ($raw === null || $raw === '') {
                if ($required) {
                    $errors[$key] = $label . ' is required.';
                }
                continue;
            }
            if (!is_string($raw) && !is_int($raw)) {
                $errors[$key] = $label . ' is invalid.';
                continue;
            }
            $str = (string) $raw;
            if (strlen($str) > self::MAX_SETTING_BYTES) {
                $errors[$key] = $label . ' is too long.';
                continue;
            }

            if ($type === 'integer') {
                if (preg_match('/\A-?\d{1,18}\z/', $str) !== 1) {
                    $errors[$key] = $label . ' must be a whole number.';
                    continue;
                }
                $values[$key] = (int) $str;
            } elseif ($type === 'select') {
                $options = is_array($field['options'] ?? null) ? $field['options'] : [];
                if (!in_array($str, $options, true)) {
                    $errors[$key] = $label . ' is not a valid choice.';
                    continue;
                }
                $values[$key] = $str;
            } else {
                $values[$key] = $str;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors, self::safeOld($fields, $input));
        }

        return ['values' => $values, 'secrets' => $secrets];
    }

    /**
     * Repopulation payload for the form: non-secret values only, never secret plaintext.
     *
     * @param list<array<string,mixed>> $fields
     * @param array<string,mixed> $input
     * @return array{settings:array<string,string>}
     */
    private static function safeOld(array $fields, array $input): array
    {
        $secretKeys = [];
        foreach ($fields as $field) {
            if (($field['secret'] ?? false) === true) {
                $secretKeys[(string) $field['key']] = true;
            }
        }
        $old = [];
        foreach ($input as $key => $value) {
            if (isset($secretKeys[(string) $key]) || !is_scalar($value)) {
                continue;
            }
            $old[(string) $key] = (string) $value;
        }

        return ['settings' => $old];
    }

    /** @param array<string,mixed> $install @return list<array<string,mixed>> */
    private function fieldsFor(array $install): array
    {
        $release = $this->releases->find((int) $install['release_id']);
        $package = $this->packages->find((int) $install['package_id']);
        if ($release === null || $package === null) {
            return [];
        }
        try {
            $manifest = $this->manifests->validate(
                (array) json_decode((string) $release['manifest_json'], true, 512, JSON_THROW_ON_ERROR),
                (string) $package['package_uid'],
                (string) $release['version'],
            );
        } catch (\Throwable) {
            // A stored manifest that no longer re-validates (format drift, tampering)
            // must not break the operator detail page; it simply exposes no settings.
            return [];
        }

        return $manifest->settingsSchema['fields'] ?? [];
    }

    /**
     * Schema + current values for rendering; secret fields report has_value:bool, never plaintext.
     *
     * @return array{fields:list<array<string,mixed>>, values:array<string,mixed>, has_secret:array<string,bool>}
     */
    public function describe(int $installedId): array
    {
        $install = $this->installs->find($installedId);
        if ($install === null) {
            return ['fields' => [], 'values' => [], 'has_secret' => []];
        }
        $fields = $this->fieldsFor($install);

        $byKey = [];
        foreach ($this->settings->forInstall($installedId) as $row) {
            $byKey[(string) $row['setting_key']] = $row;
        }

        $out = [];
        $values = [];
        $hasSecret = [];
        foreach ($fields as $field) {
            $key = (string) $field['key'];
            $secret = ($field['secret'] ?? false) === true;
            $entry = [
                'key' => $key,
                'type' => (string) $field['type'],
                'label' => (string) $field['label'],
                'required' => ($field['required'] ?? false) === true,
                'secret' => $secret,
            ];
            if (isset($field['options'])) {
                $entry['options'] = $field['options'];
            }
            $out[] = $entry;

            $row = $byKey[$key] ?? null;
            if ($secret) {
                $hasSecret[$key] = $row !== null && ($row['secret_ref'] ?? null) !== null;
            } elseif ($row !== null && ($row['value_json'] ?? null) !== null) {
                $values[$key] = json_decode((string) $row['value_json'], true);
            }
        }

        return ['fields' => $out, 'values' => $values, 'has_secret' => $hasSecret];
    }

    /**
     * Validate $input against the active release manifest settings_schema and persist.
     * Non-secret → value_json; secret (type=string + secret:true) → SecretVault store/rotate, persist secret_ref only.
     *
     * @param array<string,mixed> $input
     */
    public function save(User $admin, ?string $currentPassword, int $installedId, array $input): void
    {
        $this->writeGate->assertCanWrite($admin);

        $install = $this->installs->find($installedId);
        if ($install === null) {
            throw new ValidationException(['settings' => 'Unknown install.']);
        }
        $fields = $this->fieldsFor($install);
        if ($fields === []) {
            throw new ValidationException(['settings' => 'This package has no configurable settings.']);
        }

        $parsed = self::validateInput($fields, $input);           // throws with safe ->old
        $writingSecret = $parsed['secrets'] !== [];

        // Fail closed BEFORE opening any transaction.
        if ($writingSecret && !$this->flags->enabled('service_secrets')) {
            throw new ValidationException(
                ['settings' => 'Secret settings require the service-secret store to be enabled.'],
                self::safeOld($fields, $input),
            );
        }
        if ($writingSecret) {
            $this->reauth->requirePassword($admin, (string) $currentPassword); // ValidationException on bad/missing
        }
        foreach ($fields as $field) {
            if (($field['secret'] ?? false) === true && ($field['required'] ?? false) === true) {
                $key = (string) $field['key'];
                if (!isset($parsed['secrets'][$key])) {
                    $existing = $this->settings->find($installedId, $key);
                    if ($existing === null || ($existing['secret_ref'] ?? null) === null) {
                        throw new ValidationException(
                            ['settings' => $field['label'] . ' is required.'],
                            self::safeOld($fields, $input),
                        );
                    }
                }
            }
        }

        $package = $this->packages->find((int) $install['package_id']);
        $uid = (string) ($package['package_uid'] ?? 'package');

        $this->db->transaction(function () use ($admin, $installedId, $install, $uid, $parsed): void {
            foreach ($parsed['values'] as $key => $val) {
                $this->settings->upsert($installedId, (string) $key, json_encode($val), null, false, $admin->id());
            }
            foreach ($parsed['secrets'] as $key => $plaintext) {
                $existing = $this->settings->find($installedId, (string) $key);
                $ref = $existing['secret_ref'] ?? null;
                if ($ref !== null) {
                    $this->vault->rotate((string) $ref, $plaintext, $admin);
                } else {
                    $ref = $this->vault->store('package_setting', $installedId, $uid . ':' . $key, $plaintext, $admin);
                }
                $this->settings->upsert($installedId, (string) $key, null, (string) $ref, true, $admin->id());
            }

            $summary = ['values' => [], 'secret_keys' => []];
            foreach ($this->settings->forInstall($installedId) as $row) {
                if ((int) $row['is_secret'] === 1) {
                    $summary['secret_keys'][] = (string) $row['setting_key'];
                } elseif (($row['value_json'] ?? null) !== null) {
                    $summary['values'][(string) $row['setting_key']] = json_decode((string) $row['value_json'], true);
                }
            }
            $this->installs->setSettingsSummary($installedId, json_encode($summary));

            $this->history->record([
                'package_id' => (int) $install['package_id'],
                'installed_package_id' => $installedId,
                'event' => 'settings_update',
                'actor_id' => $admin->id(),
                'detail' => json_encode([
                    'keys' => array_keys($parsed['values']),
                    'secret_keys' => array_keys($parsed['secrets']),
                ]),
            ]);
            $this->audit->log([
                'actor_id' => $admin->id(),
                'action' => 'package_settings_update',
                'target_type' => 'package',
                'target_id' => (int) $install['package_id'],
                'after' => [
                    'installed_package_id' => $installedId,
                    'keys' => array_keys($parsed['values']),
                    'secret_keys' => array_keys($parsed['secrets']),
                ],
            ]);
        });
    }
}
