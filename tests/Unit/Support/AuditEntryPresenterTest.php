<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\AuditEntryPresenter;
use PHPUnit\Framework\TestCase;

final class AuditEntryPresenterTest extends TestCase
{
    /** @param array<string,mixed> $overlay */
    private function entry(array $overlay): array
    {
        return AuditEntryPresenter::present($overlay + [
            'actor_id' => 42, 'action' => 'future.action', 'target_type' => 'setting', 'target_id' => 0,
        ]);
    }

    public function test_missing_actor_is_not_misattributed_to_the_system(): void
    {
        self::assertSame('Unavailable member #42', $this->entry([])['actor']);
        self::assertSame('System or deleted actor', $this->entry(['actor_id' => null])['actor']);
    }

    public function test_unknown_nested_scalar_and_malformed_payloads_keep_bounded_raw_details(): void
    {
        foreach (['"scalar"', '[1,2,3]', '{broken'] as $raw) {
            $entry = $this->entry(['after_json' => $raw]);
            self::assertSame([], $entry['changes']);
            self::assertSame($raw, $entry['after_raw']);
            self::assertSame('future.action', $entry['action']);
        }
        $raw = json_encode(['payload' => str_repeat('深', 10000)], JSON_UNESCAPED_UNICODE);
        $entry = $this->entry(['after_json' => $raw]);
        self::assertLessThan(8250, mb_strlen($entry['after_raw']));
        self::assertStringEndsWith('[truncated]', $entry['after_raw']);
        self::assertLessThan(250, mb_strlen($entry['changes'][0]['after']));
        $deep = str_repeat('{"nested":', 40) . '{}' . str_repeat('}', 40);
        self::assertSame([], $this->entry(['after_json' => $deep])['changes']);
    }

    public function test_changes_skip_unchanged_fields_distinguish_missing_from_null_and_cap_the_list(): void
    {
        $before = ['status' => 'active', 'same' => 'retain', 'removed' => 'old'];
        $after = ['status' => 'suspended', 'same' => 'retain', 'nullable' => null];
        $entry = $this->entry(['before_json' => json_encode($before), 'after_json' => json_encode($after)]);
        self::assertSame([
            ['label' => 'Account state', 'before' => 'Active', 'after' => 'Suspended'],
            ['label' => 'Removed', 'before' => 'old', 'after' => 'Not recorded'],
            ['label' => 'Nullable', 'before' => 'Not recorded', 'after' => 'None'],
        ], $entry['changes']);
        $many = [];
        for ($i = 0; $i < 20; $i++) {
            $many['field_' . $i] = $i;
        }
        $bounded = $this->entry(['after_json' => json_encode($many)]);
        self::assertCount(12, $bounded['changes']);
        self::assertTrue($bounded['omitted']);
    }
}
