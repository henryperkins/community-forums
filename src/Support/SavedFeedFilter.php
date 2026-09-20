<?php

declare(strict_types=1);
namespace App\Support;

/** Original selection is retained even after access is revoked. */
final class SavedFeedFilter
{
    public static function parse(string $json): ?array
    {
        $filter = json_decode($json, true);
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
