<?php

declare(strict_types=1);
namespace App\Support;

/** Original selection is retained even after access is revoked. */
final class SavedFeedFilter
{
    public static function parse(string $json): ?array
    {
        // Decode objects as objects: board_ids:{} is not intentional discovery [].
        $decoded = json_decode($json);
        if (!$decoded instanceof \stdClass) { return null; }
        $filter = get_object_vars($decoded);
        return self::valid($filter) ? $filter : null;
    }

    public static function valid(mixed $filter): bool
    {
        if (!is_array($filter) || array_diff(array_keys($filter), ['board_ids', 'sort']) !== []
            || ($filter['sort'] ?? null) !== 'latest'
            || !is_array($filter['board_ids'] ?? null) || !array_is_list($filter['board_ids'])) {
            return false;
        }
        foreach ($filter['board_ids'] as $id) {
            if (!is_int($id) || $id <= 0) { return false; }
        }
        return true;
    }
}
