<?php

declare(strict_types=1);

namespace Tests\Unit\Security\Packages;

use App\Security\Packages\PackagePolicyException;
use App\Security\Packages\PermissionDiff;
use PHPUnit\Framework\TestCase;

final class PermissionDiffTest extends TestCase
{
    public function test_describe_maps_each_kind_to_catalogue_risk_and_consent_label(): void
    {
        $capability = PermissionDiff::describe('capability', 'core.thread.lock');
        self::assertSame(['kind' => 'capability', 'key' => 'core.thread.lock'], ['kind' => $capability['kind'], 'key' => $capability['key']]);
        self::assertContains($capability['risk'], ['low', 'medium', 'high']);
        self::assertNotSame('', $capability['label']);

        self::assertSame('high', PermissionDiff::describe('data_class', 'content.private')['risk']);
        self::assertStringContainsString('private', strtolower(PermissionDiff::describe('data_class', 'content.private')['label']));
        self::assertSame('medium', PermissionDiff::describe('api_scope', 'read:boards')['risk']);
        self::assertSame('low', PermissionDiff::describe('event', 'topic.created')['risk']);
        self::assertSame('medium', PermissionDiff::describe('outbound_host', 'api.example.com')['risk']);
        self::assertStringContainsString('api.example.com', PermissionDiff::describe('outbound_host', 'api.example.com')['label']);
        self::assertSame('low', PermissionDiff::describe('job', 'sync')['risk']);
        self::assertSame('medium', PermissionDiff::describe('broker_service', 'search')['risk']);
        self::assertStringContainsString('search', PermissionDiff::describe('broker_service', 'search')['label']);
    }

    public function test_protected_capability_clamps_to_high_and_never_yields_a_null_label(): void
    {
        $described = PermissionDiff::describe('capability', 'core.owner.transfer');
        self::assertSame('high', $described['risk']);
        self::assertNotSame('', $described['label']);
    }

    public function test_unknown_kind_refuses_with_coded_exception(): void
    {
        try {
            PermissionDiff::describe('broker_service_typo', 'x');
            self::fail('expected PackagePolicyException');
        } catch (PackagePolicyException $e) {
            self::assertSame('unknown_field', $e->code);
        }
    }

    public function test_diff_partitions_added_removed_unchanged_across_kinds(): void
    {
        $old = [
            ['kind' => 'data_class', 'key' => 'package.own_storage'],
            ['kind' => 'event', 'key' => 'topic.created'],
        ];
        $new = [
            ['kind' => 'data_class', 'key' => 'package.own_storage'],
            ['kind' => 'data_class', 'key' => 'content.private'],
            ['kind' => 'outbound_host', 'key' => 'api.example.com'],
        ];
        $diff = PermissionDiff::diff($old, $new);

        self::assertSame(
            [['data_class', 'content.private'], ['outbound_host', 'api.example.com']],
            array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['added']),
        );
        self::assertSame([['event', 'topic.created']], array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['removed']));
        self::assertSame([['data_class', 'package.own_storage']], array_map(static fn (array $p): array => [$p['kind'], $p['key']], $diff['unchanged']));
        self::assertSame('high', $diff['added'][0]['risk']);
    }

    public function test_identical_sets_diff_to_no_change(): void
    {
        $set = [['kind' => 'api_scope', 'key' => 'read:boards']];
        $diff = PermissionDiff::diff($set, $set);
        self::assertSame([], $diff['added']);
        self::assertSame([], $diff['removed']);
        self::assertCount(1, $diff['unchanged']);
    }
}
