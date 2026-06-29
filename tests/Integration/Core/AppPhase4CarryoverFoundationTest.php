<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\FeatureFlags;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppPhase4CarryoverFoundationTest extends TestCase
{
    public function test_phase4_carryover_flags_default_dark_and_override_independently(): void
    {
        $flags = new FeatureFlags(new SettingRepository($this->db));
        $carryovers = [
            'link_previews',
            'expanded_files',
            'polls',
            'custom_emoji',
            'slash_giphy',
            'split_merge',
            'profile_media',
            'board_folders',
            'saved_feeds',
            'automated_context',
            'content_references',
        ];

        foreach ($carryovers as $flag) {
            self::assertArrayHasKey($flag, $flags->all(), "$flag must be declared, not merely unknown");
            self::assertFalse($flags->enabled($flag), "$flag must deploy dark by default");
        }

        (new SettingRepository($this->db))->set('features', ['polls' => true]);
        $overridden = new FeatureFlags(new SettingRepository($this->db));

        self::assertTrue($overridden->enabled('polls'));
        self::assertFalse($overridden->enabled('custom_emoji'), 'enabling polls must not enable another carryover flag');
    }

    public function test_phase4_carryover_additive_schema_exists(): void
    {
        foreach ([
            'link_previews',
            'polls',
            'poll_options',
            'poll_votes',
            'custom_emoji',
            'board_folders',
            'board_folder_boards',
            'saved_feed_filters',
            'since_last_read_context',
        ] as $table) {
            self::assertTrue($this->tableExists($table), "$table should exist");
        }

        foreach ([
            'scan_status',
            'scan_checked_at',
            'quarantined_at',
            'quarantine_reason',
            'download_name',
        ] as $column) {
            self::assertTrue($this->columnExists('attachments', $column), "attachments.$column should exist");
        }

        foreach ([
            'signature_removed_at',
            'signature_removed_by',
            'avatar_removed_at',
            'avatar_removed_by',
        ] as $column) {
            self::assertTrue($this->columnExists('users', $column), "users.$column should exist");
        }
    }

    private function tableExists(string $table): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1',
            [$table],
        ) !== false;
    }

    private function columnExists(string $table, string $column): bool
    {
        return $this->db->fetchValue(
            'SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ? LIMIT 1',
            [$table, $column],
        ) !== false;
    }
}
