<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Security\WriteGate;

/**
 * The /admin/features write path (ADR 0049). It performs the same
 * read-modify-write of `settings.features` that docs/runbooks/operations.md §2
 * documents and audits it in the same transaction. FeatureFlags stays the only
 * reader: nothing here changes how a stored override is interpreted.
 */
final class FeatureFlagAdminService
{
    public function __construct(
        private Database $db,
        private SettingRepository $settings,
        private ModerationLogRepository $log,
        private WriteGate $writeGate,
        private FeatureFlags $features,
    ) {
    }

    /**
     * Make one declared flag effectively on or off. The target is explicit,
     * never a flip, so a double submit or a second admin's stale page cannot
     * invert a change: a flag already in that state is left untouched and
     * unaudited. Returning to the code default removes the override (the
     * runbook's "remove the key"); anything else stores a JSON boolean. Every
     * other key, declared or not, is preserved through the JSON decode/encode
     * round trip.
     *
     * For a flag already in that state, `last_change` names the newest audited
     * console change that left it there, when one did.
     *
     * @return array{flag:string,changed:bool,enabled:bool,override:?bool,audit_id:?int,last_change:?array{actor_id:?int,created_at:string}}
     */
    public function setEnabled(User $admin, string $flag, bool $enabled): array
    {
        if (!$admin->isAdmin()) {
            throw new ForbiddenException('Administrator access required.');
        }
        $this->writeGate->assertCanWrite($admin);

        $defaults = FeatureFlags::defaults();
        if (!array_key_exists($flag, $defaults)) {
            throw new NotFoundException('Not found.');
        }
        $default = $defaults[$flag];

        $result = $this->db->transaction(function () use ($admin, $flag, $enabled, $default): array {
            // The row lock serializes concurrent console writes, so a change to
            // another flag committed meanwhile is read here, never overwritten.
            $stored = $this->settings->lockForUpdate('features', new \stdClass());
            if (!FeatureFlags::isOverrideMap($stored)) {
                // FeatureFlags is ignoring every override; writing a fresh object
                // would silently replace whatever the operator meant to store.
                throw new ValidationException([
                    'flag' => 'settings.features is not a JSON object, so no flag can change here until it is repaired (docs/runbooks/operations.md §2).',
                ], ['flag' => $flag]);
            }

            $map = $stored;
            $hadOverride = array_key_exists($flag, $map);
            $beforeOverride = $hadOverride ? $map[$flag] : null;
            $current = $hadOverride ? FeatureFlags::normalizeOverride($map[$flag]) : $default;
            if ($current === $enabled) {
                return [
                    'flag' => $flag,
                    'changed' => false,
                    'enabled' => $enabled,
                    'override' => $hadOverride ? $current : null,
                    'audit_id' => null,
                    'last_change' => $this->lastChange($flag, $enabled, $beforeOverride),
                ];
            }

            $blocker = $enabled ? FeatureFlags::consoleEnableBlocker($flag) : null;
            if ($blocker !== null) {
                throw new ValidationException([
                    'flag' => 'The console cannot turn on ' . $flag . '. ' . $blocker,
                ], ['flag' => $flag]);
            }

            if ($enabled === $default) {
                unset($map[$flag]);
                $override = null;
            } else {
                $map[$flag] = $enabled;
                $override = $enabled;
            }
            // An object even when empty: `[]` would store a JSON array.
            $next = (object) $map;
            // A stored number outside the float range decodes to INF, which
            // json_encode refuses: the loader still applies that map, but it
            // cannot be written back, and an unchecked set() would end in a 500.
            if (json_encode($next, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === false) {
                throw new ValidationException([
                    'flag' => 'settings.features holds a number outside the range PHP can write back, so no flag can change here until it is repaired (docs/runbooks/operations.md §2).',
                ], ['flag' => $flag]);
            }
            $this->settings->set('features', $next);

            $auditId = $this->log->log([
                'actor_id' => $admin->id(),
                'action' => $enabled ? 'feature_flag.enable' : 'feature_flag.disable',
                // Configuration has no row id; the reason names the key, as the
                // settings pages' audit entries do.
                'target_type' => 'setting',
                'target_id' => 0,
                'reason' => 'features.' . $flag,
                'before' => ['flag' => $flag, 'override' => $beforeOverride, 'effective' => $current],
                'after' => ['flag' => $flag, 'override' => $override, 'effective' => $enabled],
            ]);

            return [
                'flag' => $flag,
                'changed' => true,
                'enabled' => $enabled,
                'override' => $override,
                'audit_id' => $auditId,
                'last_change' => null,
            ];
        });

        $this->features->invalidate();

        return $result;
    }

    /**
     * The newest console change of $flag, when its recorded result matches the
     * effective state and stored override the flag has now. A runbook hand edit
     * leaves no entry, so attribution is best effort: it names the last audited
     * change, not every write.
     *
     * @return array{actor_id:?int,created_at:string}|null
     */
    private function lastChange(string $flag, bool $enabled, mixed $override): ?array
    {
        $entry = $this->log->latestSettingChange('feature_flag.', 'features.' . $flag);
        $after = $entry !== null ? json_decode((string) $entry['after_json'], true) : null;
        if (
            !is_array($after)
            || ($after['effective'] ?? null) !== $enabled
            || !array_key_exists('override', $after)
            || $after['override'] !== $override
        ) {
            return null;
        }

        return [
            'actor_id' => $entry['actor_id'] !== null ? (int) $entry['actor_id'] : null,
            'created_at' => (string) $entry['created_at'],
        ];
    }
}
