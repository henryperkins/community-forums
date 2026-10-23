<?php

declare(strict_types=1);

namespace App\Support;

/** Bounded, plain-text audit presentation. Templates still escape every value. */
final class AuditEntryPresenter
{
    private const ACTIONS = [
        'warn' => 'Warned member', 'suspend' => 'Suspended member', 'ban' => 'Banned member',
        'lift' => 'Lifted restrictions', 'lift_ban' => 'Lifted restrictions', 'note' => 'Added staff note',
        'change_role' => 'Changed role', 'view_pii' => 'Viewed private contact details',
        'set_title' => 'Set member title', 'clear_title' => 'Cleared member title',
        'clear_signature' => 'Removed signature', 'clear_avatar' => 'Removed avatar',
        'update_setting' => 'Updated settings',
        'create_board' => 'Created board', 'update_board' => 'Updated board', 'delete_board' => 'Deleted board',
        'archive_board' => 'Archived board', 'unarchive_board' => 'Unarchived board',
        'create_category' => 'Created category', 'update_category' => 'Updated category', 'delete_category' => 'Deleted category',
        'move_board_content' => 'Moved board content', 'reorder_categories' => 'Reordered categories', 'reorder_boards' => 'Reordered boards',
        'assign_moderator' => 'Assigned board moderator', 'unassign_moderator' => 'Removed board moderator',
        'add_member' => 'Added board member', 'remove_member' => 'Removed board member',
        'feature_flag.enable' => 'Enabled feature', 'feature_flag.disable' => 'Disabled feature',
        'auto_allow' => 'Automatically allowed content', 'auto_flag' => 'Automatically flagged content',
        'auto_hold' => 'Automatically held content', 'auto_block' => 'Automatically blocked content',
    ];
    private const FIELDS = [
        'site_name' => 'Community name', 'registration_mode' => 'Registration',
        'antiabuse_mode' => 'Anti-abuse mode', 'antiabuse_blocked_words' => 'Blocked phrases',
        'anti_abuse_settings' => 'Anti-abuse settings', 'role' => 'Role', 'status' => 'Account state',
        'name' => 'Name', 'slug' => 'URL name', 'position' => 'Position', 'visibility' => 'Visibility',
        'post_min_role' => 'Minimum posting role', 'allow_anonymous' => 'Anonymous posts',
        'require_approval' => 'Approval required', 'tags_enabled' => 'Tags', 'wiki_enabled' => 'Wiki posts',
        'link_previews_enabled' => 'Link previews', 'is_archived' => 'Archived',
        'edit_window_seconds' => 'Edit window (seconds)', 'effective' => 'Effective state',
        'override' => 'Override', 'flag' => 'Feature', 'suspended_until' => 'Suspended until (UTC)',
        'expires_at' => 'Expires (UTC)', 'user_id' => 'Member ID', 'board_id' => 'Board ID',
        'category_id' => 'Category ID', 'threads_moved' => 'Threads moved',
    ];
    private const SWITCHES = ['allow_anonymous', 'require_approval', 'tags_enabled', 'wiki_enabled', 'link_previews_enabled', 'is_archived', 'effective', 'override'];
    private const ENUMS = [
        'registration_mode' => ['open' => 'Open', 'closed' => 'Closed', 'invite' => 'Invitation only'],
        'antiabuse_mode' => ['observe' => 'Observe', 'flag' => 'Flag', 'hold' => 'Hold', 'block' => 'Block'],
        'role' => ['user' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Administrator'],
        'post_min_role' => ['user' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Administrator'],
        'status' => ['active' => 'Active', 'suspended' => 'Suspended', 'banned' => 'Banned', 'deactivated' => 'Deactivated', 'pending_deletion' => 'Pending deletion'],
        'visibility' => ['public' => 'Public', 'hidden' => 'Hidden', 'private' => 'Private'],
    ];

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function present(array $row): array
    {
        $action = (string) $row['action'];
        $type = (string) $row['target_type'];
        $id = (int) $row['target_id'];
        $before = self::object((string) ($row['before_json'] ?? ''));
        $after = self::object((string) ($row['after_json'] ?? ''));
        $target = ucfirst($type) . ' #' . $id;
        $targetLink = null;
        if ($type === 'user') {
            $target = self::identity($row['target_username'] ?? null, $row['target_display_name'] ?? null, $id);
            if (($row['target_username'] ?? null) !== null) {
                $targetLink = '/admin/users/' . $id;
            }
        } elseif ($type === 'setting') {
            $target = self::FIELDS[(string) ($row['reason'] ?? '')] ?? 'Site settings';
        } elseif (in_array($type, ['board', 'category'], true)) {
            // Snapshot names describe the event, never an unaudited identity reveal.
            $name = $after['name'] ?? $before['name'] ?? null;
            if (is_string($name) && $name !== '') {
                $target = ucfirst($type) . ': ' . $name . ' (#' . $id . ')';
            }
        }

        $changes = [];
        $omitted = false;
        if ($before !== null || $after !== null) {
            foreach (array_unique(array_merge(array_keys($before ?? []), array_keys($after ?? []))) as $key) {
                $had = array_key_exists($key, $before ?? []);
                $has = array_key_exists($key, $after ?? []);
                if ($had && $has && $before[$key] === $after[$key]) {
                    continue;
                }
                if (count($changes) === 12) {
                    $omitted = true;
                    break;
                }
                $changes[] = [
                    'label' => self::clip(self::FIELDS[$key] ?? ucfirst(str_replace('_', ' ', (string) $key)), 80),
                    'before' => $had ? self::value((string) $key, $before[$key]) : 'Not recorded',
                    'after' => $has ? self::value((string) $key, $after[$key]) : 'Not recorded',
                ];
            }
        }
        // ON DELETE SET NULL uses the same value as a system action. Do not
        // invent an identity or misattribute a removed member to automation.
        return [
            'actor' => ($row['actor_id'] ?? null) === null ? 'System or deleted actor' : self::identity($row['actor_username'] ?? null, $row['actor_display_name'] ?? null, (int) $row['actor_id']),
            'action' => self::clip(self::ACTIONS[$action] ?? $action, 160),
            'action_code' => self::clip($action, 160),
            'target' => self::clip($target, 240), 'target_link' => $targetLink,
            'reason' => self::clip($type === 'setting' ? (self::FIELDS[(string) ($row['reason'] ?? '')] ?? (string) ($row['reason'] ?? '')) : (string) ($row['reason'] ?? ''), 1000),
            'reason_raw' => self::clip((string) ($row['reason'] ?? ''), 1000),
            'changes' => $changes, 'omitted' => $omitted,
            'before_raw' => self::clip((string) ($row['before_json'] ?? ''), 8192),
            'after_raw' => self::clip((string) ($row['after_json'] ?? ''), 8192),
        ];
    }

    /** @return array<string,mixed>|null */
    private static function object(string $raw): ?array
    {
        if ($raw === '' || strlen($raw) > 65536) {
            return null;
        }
        $value = json_decode($raw, false, 32);
        return $value instanceof \stdClass ? get_object_vars($value) : null;
    }

    private static function identity(?string $username, ?string $display, int $id): string
    {
        if ($username === null) {
            return 'Unavailable member #' . $id;
        }
        $handle = '@' . $username;
        return self::clip(($display !== null && trim($display) !== '' && $display !== $username ? $display . ' (' . $handle . ')' : $handle) . ' (#' . $id . ')', 240);
    }

    private static function value(string $key, mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }
        if (is_bool($value) || (in_array($key, self::SWITCHES, true) && in_array($value, [0, 1, '0', '1'], true))) {
            return (bool) $value ? 'Yes' : 'No';
        }
        if (is_string($value)) {
            return self::clip(self::ENUMS[$key][$value] ?? ($value === '' ? 'Empty' : $value), 200);
        }
        if (is_array($value) && array_is_list($value)) {
            $items = array_map(static fn (mixed $item): string => self::value('', $item), array_slice($value, 0, 5));
            return self::clip($items === [] ? 'None' : implode(', ', $items) . (count($value) > 5 ? ' … [more items]' : ''), 200);
        }
        return self::clip(is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: 'Unrepresentable value'), 200);
    }

    private static function clip(string $value, int $limit): string
    {
        return mb_strlen($value) > $limit ? mb_substr($value, 0, $limit) . ' … [truncated]' : $value;
    }
}
