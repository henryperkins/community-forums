<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Per-user preference blob (USER §4, §7). Stored as a single JSON document so
 * new client/reading/privacy keys can be added without migrations. Server-side
 * prefs (pagination, sort, leaderboard opt-out) are read back here and enforced;
 * client-only prefs (theme, density) are merely persisted for the browser.
 */
final class UserPreferenceRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return array<string,mixed> decoded prefs ({} when none stored) */
    public function get(int $userId): array
    {
        $raw = $this->db->fetchValue('SELECT prefs FROM user_preferences WHERE user_id = ?', [$userId]);
        if ($raw === false || $raw === null) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Merge $changes into the stored prefs and persist (upsert). Keys set to null
     * are removed. Returns the merged document.
     *
     * @param array<string,mixed> $changes
     * @return array<string,mixed>
     */
    public function merge(int $userId, array $changes): array
    {
        $prefs = $this->get($userId);
        foreach ($changes as $key => $value) {
            if ($value === null) {
                unset($prefs[$key]);
            } else {
                $prefs[$key] = $value;
            }
        }
        $json = json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->db->run(
            'INSERT INTO user_preferences (user_id, prefs, updated_at)
             VALUES (:uid, :prefs, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE prefs = VALUES(prefs), updated_at = UTC_TIMESTAMP()',
            ['uid' => $userId, 'prefs' => $json],
        );
        return $prefs;
    }
}
