<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Security\ApiScopes;
use App\Security\CapabilityCatalog;
use App\Security\DataClasses;
use App\Security\Registry\PackageIdentity;
use App\Security\WebhookEvents;
use App\Support\CoreVersion;

/**
 * Fail-closed rb-manifest.v2 validation. Compatibility syntax is validated
 * here; compatibility enforcement happens in the install path.
 */
final class ManifestValidator
{
    public const FORMAT = 'rb-manifest.v2';
    public const TYPES = ['theme', 'automation', 'remote_app', 'local'];
    public const MAX_STORAGE_QUOTA_KB = 10_240;

    private const TOP_KEYS = [
        'format', 'uid', 'type', 'version', 'name', 'description', 'license',
        'core', 'permissions', 'settings_schema', 'storage_quota_kb', 'install', 'support',
    ];
    private const PERMISSION_KINDS = [
        'capabilities' => 'capability',
        'data_classes' => 'data_class',
        'api_scopes' => 'api_scope',
        'events' => 'event',
        'outbound_hosts' => 'outbound_host',
        'jobs' => 'job',
    ];
    private const SETTING_TYPES = ['string', 'boolean', 'integer', 'select'];
    private const SETTING_FIELD_KEYS = ['key', 'type', 'label', 'required', 'options'];
    private const JOB_SCHEDULES = ['hourly', 'daily', 'weekly'];
    private const HOST_PATTERN = '/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/';
    private const KEY_PATTERN = '/^[a-z][a-z0-9_]{0,63}$/';

    /** @param array<string,mixed> $manifest */
    public function validate(array $manifest, string $expectedUid, string $expectedVersion): PackageManifest
    {
        if (($manifest['format'] ?? null) !== self::FORMAT) {
            $this->refuse('manifest_format', 'Manifest must declare format ' . self::FORMAT . '.');
        }
        foreach (array_keys($manifest) as $key) {
            if (!in_array((string) $key, self::TOP_KEYS, true)) {
                $this->refuse('unknown_field', 'Unknown manifest field: ' . $key . '.');
            }
        }

        $uid = is_string($manifest['uid'] ?? null) ? $manifest['uid'] : '';
        if (!PackageIdentity::isValidUid($uid) || $uid !== $expectedUid) {
            $this->refuse('manifest_identity', 'Manifest uid does not match the release identity.');
        }

        $version = is_string($manifest['version'] ?? null) ? $manifest['version'] : '';
        if ($version === '' || $version !== $expectedVersion) {
            $this->refuse('manifest_identity', 'Manifest version does not match the release identity.');
        }

        $type = is_string($manifest['type'] ?? null) ? $manifest['type'] : '';
        if (!in_array($type, self::TYPES, true)) {
            $this->refuse('manifest_type', 'Package type "' . $type . '" is not allowed in Gate A.');
        }

        $name = trim($this->stringField($manifest, 'name', 190, 'manifest_name') ?? '');
        if ($name === '') {
            $this->refuse('manifest_name', 'Manifest name is required.');
        }

        $description = $this->stringField($manifest, 'description', 512, 'manifest_field');
        $license = $this->stringField($manifest, 'license', 190, 'manifest_field');
        [$coreMin, $coreMax] = $this->core($manifest['core'] ?? null);
        $permissions = $this->permissions($manifest['permissions'] ?? []);
        $settingsSchema = $this->settingsSchema($manifest['settings_schema'] ?? null);
        $storageQuotaKb = $this->storageQuota($manifest['storage_quota_kb'] ?? 0);
        $retentionDays = $this->installPolicy($manifest['install'] ?? null);
        $support = $this->support($manifest['support'] ?? []);

        return new PackageManifest(
            $uid,
            $type,
            $version,
            $name,
            $description,
            $license,
            $coreMin,
            $coreMax,
            $permissions,
            $settingsSchema,
            $storageQuotaKb,
            $retentionDays,
            $support,
        );
    }

    private function refuse(string $code, string $message): never
    {
        throw new PackagePolicyException($code, $message);
    }

    /** @param array<string,mixed> $manifest */
    private function stringField(array $manifest, string $key, int $max, string $code): ?string
    {
        if (!array_key_exists($key, $manifest) || $manifest[$key] === null) {
            return null;
        }
        if (!is_string($manifest[$key]) || mb_strlen(trim($manifest[$key])) > $max) {
            $this->refuse($code, 'Manifest field "' . $key . '" must be a string of at most ' . $max . ' characters.');
        }

        return trim($manifest[$key]);
    }

    /** @return array{0:string,1:?string} */
    private function core(mixed $core): array
    {
        if (!is_array($core) || $this->unknownKeys($core, ['min', 'max']) !== []) {
            $this->refuse('manifest_core', 'Manifest core range must be an object with only min/max.');
        }

        $min = $core['min'] ?? null;
        if (!is_string($min) || !CoreVersion::isValid($min)) {
            $this->refuse('manifest_core', 'Manifest core.min must be a valid version.');
        }

        $max = $core['max'] ?? null;
        if ($max !== null && (!is_string($max) || !CoreVersion::isValid($max))) {
            $this->refuse('manifest_core', 'Manifest core.max must be null or a valid version.');
        }

        return [$min, $max];
    }

    /** @return list<array{kind:string,key:string,risk:string,label:string}> */
    private function permissions(mixed $in): array
    {
        if (!is_array($in)) {
            $this->refuse('manifest_field', 'Manifest permissions must be an object of kind lists.');
        }
        foreach (array_keys($in) as $kind) {
            if (!array_key_exists((string) $kind, self::PERMISSION_KINDS)) {
                $this->refuse('unknown_field', 'Unknown permission kind: ' . $kind . '.');
            }
        }

        $out = [];
        $seen = [];
        $add = function (string $kind, string $key) use (&$out, &$seen): void {
            $dedupe = $kind . ':' . $key;
            if (isset($seen[$dedupe])) {
                $this->refuse('manifest_field', 'Duplicate permission declaration: ' . $dedupe . '.');
            }
            $seen[$dedupe] = true;
            $out[] = PermissionDiff::describe($kind, $key);
        };

        foreach ($this->permissionList($in, 'capabilities') as $key) {
            if (!is_string($key) || !CapabilityCatalog::has($key)) {
                $this->refuse('unknown_capability', 'Unknown capability name in manifest.');
            }
            if (CapabilityCatalog::isProtected($key)) {
                $this->refuse('protected_capability', 'Protected capabilities are never grantable to a package.');
            }
            $add('capability', $key);
        }

        foreach ($this->permissionList($in, 'data_classes') as $key) {
            if (!is_string($key) || !DataClasses::has($key)) {
                $this->refuse('unknown_data_class', 'Unknown data class in manifest.');
            }
            if (!DataClasses::grantable($key)) {
                $this->refuse('protected_data_class', 'Protected data classes are never grantable to a package.');
            }
            $add('data_class', $key);
        }

        foreach ($this->permissionList($in, 'api_scopes') as $key) {
            if (!is_string($key) || !ApiScopes::isValid($key)) {
                $this->refuse('unknown_api_scope', 'Unknown API scope in manifest.');
            }
            $add('api_scope', $key);
        }

        $events = WebhookEvents::domainEvents();
        foreach ($this->permissionList($in, 'events') as $key) {
            if (!is_string($key) || !isset($events[$key])) {
                $this->refuse('unknown_event', 'Unknown or non-subscribable event in manifest.');
            }
            $add('event', $key);
        }

        foreach ($this->permissionList($in, 'outbound_hosts') as $host) {
            if (!is_string($host) || preg_match(self::HOST_PATTERN, $host) !== 1) {
                $this->refuse('outbound_host', 'Outbound hosts must be explicit lowercase hostnames.');
            }
            $add('outbound_host', $host);
        }

        foreach ($this->permissionList($in, 'jobs') as $job) {
            if (!is_array($job) || $this->unknownKeys($job, ['name', 'schedule']) !== []) {
                $this->refuse('job_declaration', 'Each job must declare exactly name and schedule.');
            }
            $jobName = $job['name'] ?? null;
            $schedule = $job['schedule'] ?? null;
            if (
                !is_string($jobName)
                || preg_match(self::KEY_PATTERN, $jobName) !== 1
                || !is_string($schedule)
                || !in_array($schedule, self::JOB_SCHEDULES, true)
            ) {
                $this->refuse('job_declaration', 'Job declarations must use a lowercase name and an hourly/daily/weekly schedule.');
            }
            $add('job', $jobName);
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $permissions
     * @return list<mixed>
     */
    private function permissionList(array $permissions, string $kind): array
    {
        $value = $permissions[$kind] ?? [];
        if (!is_array($value) || $value !== array_values($value)) {
            $this->refuse('manifest_field', 'Permission kind "' . $kind . '" must be a list.');
        }

        return $value;
    }

    /** @return ?array{fields:list<array<string,mixed>>} */
    private function settingsSchema(mixed $schema): ?array
    {
        if ($schema === null) {
            return null;
        }
        if (
            !is_array($schema)
            || array_keys($schema) !== ['fields']
            || !is_array($schema['fields'])
            || $schema['fields'] === []
        ) {
            $this->refuse('settings_schema', 'settings_schema must be {"fields": [non-empty list]}.');
        }

        $fields = [];
        $seenKeys = [];
        foreach ($schema['fields'] as $field) {
            if (!is_array($field) || $this->unknownKeys($field, self::SETTING_FIELD_KEYS) !== []) {
                $this->refuse('settings_schema', 'Unknown settings field property.');
            }

            $key = $field['key'] ?? null;
            $type = $field['type'] ?? null;
            $label = $field['label'] ?? null;
            if (!is_string($key) || preg_match(self::KEY_PATTERN, $key) !== 1 || isset($seenKeys[$key])) {
                $this->refuse('settings_schema', 'Settings field keys must be unique lowercase identifiers.');
            }
            if (!is_string($type) || !in_array($type, self::SETTING_TYPES, true)) {
                $this->refuse('settings_schema', 'Settings field type must be string/boolean/integer/select.');
            }
            if (!is_string($label) || trim($label) === '' || mb_strlen($label) > 190) {
                $this->refuse('settings_schema', 'Settings field label is required.');
            }
            if (array_key_exists('required', $field) && !is_bool($field['required'])) {
                $this->refuse('settings_schema', 'Settings field "required" must be a boolean.');
            }

            $hasOptions = array_key_exists('options', $field);
            if ($type === 'select') {
                $options = $field['options'] ?? null;
                if (!$this->validOptions($options)) {
                    $this->refuse('settings_schema', 'select fields require a non-empty list of string options.');
                }
            } elseif ($hasOptions) {
                $this->refuse('settings_schema', 'Only select fields may declare options.');
            }

            $seenKeys[$key] = true;
            $fields[] = $field;
        }

        return ['fields' => $fields];
    }

    private function storageQuota(mixed $quota): int
    {
        if (!is_int($quota) || $quota < 0 || $quota > self::MAX_STORAGE_QUOTA_KB) {
            $this->refuse('storage_quota', 'storage_quota_kb must be an integer between 0 and ' . self::MAX_STORAGE_QUOTA_KB . '.');
        }

        return $quota;
    }

    private function installPolicy(mixed $install): ?int
    {
        if ($install === null) {
            return null;
        }
        if (!is_array($install) || $this->unknownKeys($install, ['retention_days']) !== []) {
            $this->refuse('install_policy', 'install policy allows only retention_days.');
        }
        if (!array_key_exists('retention_days', $install)) {
            return null;
        }

        $days = $install['retention_days'];
        if (!is_int($days) || $days < 1 || $days > 365) {
            $this->refuse('install_policy', 'install.retention_days must be an integer between 1 and 365.');
        }

        return $days;
    }

    /** @return array<string,string> */
    private function support(mixed $support): array
    {
        if (!is_array($support) || $this->unknownKeys($support, ['homepage', 'issues']) !== []) {
            $this->refuse('support_link', 'support allows only homepage and issues.');
        }

        $out = [];
        foreach ($support as $key => $url) {
            if (!is_string($url) || !str_starts_with($url, 'https://') || mb_strlen($url) > 512) {
                $this->refuse('support_link', 'Support links must be https:// URLs.');
            }
            $out[(string) $key] = $url;
        }

        return $out;
    }

    /** @param array<mixed,mixed> $array @param list<string> $allowed @return list<string> */
    private function unknownKeys(array $array, array $allowed): array
    {
        return array_values(array_diff(array_map('strval', array_keys($array)), $allowed));
    }

    private function validOptions(mixed $options): bool
    {
        if (!is_array($options) || $options === [] || $options !== array_values($options)) {
            return false;
        }
        foreach ($options as $option) {
            if (!is_string($option) || $option === '' || mb_strlen($option) > 190) {
                return false;
            }
        }

        return true;
    }
}
