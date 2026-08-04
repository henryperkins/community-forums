<?php

declare(strict_types=1);

namespace App\Repository;

use App\Core\Database;

/**
 * Typed key/value site configuration. Values are stored as JSON.
 */
final class SettingRepository
{
    public function __construct(private Database $db)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->getMany([$key => $default])[$key];
    }

    /**
     * Load multiple settings in one query and retain them for this request.
     *
     * @param array<string,mixed> $defaults
     * @return array<string,mixed>
     */
    public function getMany(array $defaults): array
    {
        if ($defaults === []) {
            return [];
        }

        $keys = array_keys($defaults);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $rows = $this->db->fetchAll(
            'SELECT `key`, `value` FROM settings WHERE `key` IN (' . $placeholders . ')',
            $keys,
        );

        $values = $defaults;
        foreach ($rows as $row) {
            $decoded = json_decode((string) $row['value'], true);
            if ($decoded === null && json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }
            $values[(string) $row['key']] = $decoded;
        }
        return $values;
    }

    public function getString(string $key, string $default = ''): string
    {
        $value = $this->get($key, $default);
        return is_string($value) ? $value : $default;
    }

    public function set(string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $this->db->run(
            'INSERT INTO settings (`key`, `value`, updated_at) VALUES (:key, :value, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), updated_at = UTC_TIMESTAMP()',
            ['key' => $key, 'value' => $json],
        );
    }

    public function has(string $key): bool
    {
        return $this->db->fetchValue('SELECT 1 FROM settings WHERE `key` = ? LIMIT 1', [$key]) !== false;
    }
}
