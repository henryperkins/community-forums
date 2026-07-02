<?php

declare(strict_types=1);

namespace App\Security\Packages;

use App\Security\ApiScopes;
use App\Security\CapabilityCatalog;
use App\Security\DataClasses;
use App\Security\WebhookEvents;

/**
 * Pure package permission vocabulary: one risk class + consent label per
 * (kind, key), plus added/removed/unchanged partitioning for declared sets.
 */
final class PermissionDiff
{
    /** @return array{kind:string,key:string,risk:string,label:string} */
    public static function describe(string $kind, string $key): array
    {
        [$risk, $label] = match ($kind) {
            'capability' => [
                CapabilityCatalog::has($key) ? CapabilityCatalog::all()[$key]['risk'] : 'high',
                (CapabilityCatalog::has($key) ? CapabilityCatalog::consent($key) : null)
                    ?? 'Protected capability - never grantable to a package.',
            ],
            'data_class' => [
                DataClasses::has($key) ? DataClasses::risk($key) : 'high',
                (DataClasses::has($key) ? DataClasses::consent($key) : null)
                    ?? 'Protected data class - never grantable to a package.',
            ],
            'api_scope' => ['medium', 'Use the read-only API: ' . (ApiScopes::SCOPES[$key] ?? $key) . '.'],
            'event' => ['low', 'Receive webhook events: ' . (WebhookEvents::EVENTS[$key] ?? $key) . '.'],
            'outbound_host' => ['medium', 'Send outbound requests to ' . $key . '.'],
            'job' => ['low', 'Run the scheduled job "' . $key . '".'],
            'broker_service' => ['medium', 'Use the broker service "' . $key . '".'],
            default => throw new PackagePolicyException('unknown_field', 'Unknown permission kind: ' . $kind . '.'),
        };

        return [
            'kind' => $kind,
            'key' => $key,
            'risk' => $risk === 'protected' ? 'high' : (string) $risk,
            'label' => (string) $label,
        ];
    }

    /**
     * @param list<array{kind:string,key:string}> $old
     * @param list<array{kind:string,key:string}> $new
     * @return array{added:list<array{kind:string,key:string,risk:string,label:string}>,removed:list<array{kind:string,key:string,risk:string,label:string}>,unchanged:list<array{kind:string,key:string,risk:string,label:string}>}
     */
    public static function diff(array $old, array $new): array
    {
        $index = static function (array $perms): array {
            $out = [];
            foreach ($perms as $permission) {
                $entry = [
                    'kind' => (string) $permission['kind'],
                    'key' => (string) $permission['key'],
                ];
                $out[$entry['kind'] . ':' . $entry['key']] = $entry;
            }

            return $out;
        };

        $oldMap = $index($old);
        $newMap = $index($new);
        $describe = static fn (array $entries): array => array_values(array_map(
            static fn (array $permission): array => self::describe($permission['kind'], $permission['key']),
            $entries,
        ));

        return [
            'added' => $describe(array_diff_key($newMap, $oldMap)),
            'removed' => $describe(array_diff_key($oldMap, $newMap)),
            'unchanged' => $describe(array_intersect_key($newMap, $oldMap)),
        ];
    }
}
