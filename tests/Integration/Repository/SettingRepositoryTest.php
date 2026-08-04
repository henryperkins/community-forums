<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class SettingRepositoryTest extends TestCase
{
    public function test_get_many_batches_settings_without_hiding_later_writes(): void
    {
        $writer = new SettingRepository($this->db);
        $writer->set('perf_bulk_one', 'one');
        $writer->set('perf_bulk_null', null);

        $reader = new SettingRepository($this->db);
        $this->db->resetMetrics();

        $values = $reader->getMany([
            'perf_bulk_one' => 'fallback',
            'perf_bulk_null' => 'fallback',
            'perf_bulk_missing' => 'fallback',
        ]);

        self::assertSame('one', $values['perf_bulk_one']);
        self::assertNull($values['perf_bulk_null']);
        self::assertSame('fallback', $values['perf_bulk_missing']);
        self::assertSame(1, $this->db->metrics()['queries']);

        $writer->set('perf_bulk_one', 'updated');
        $this->db->resetMetrics();
        self::assertSame('updated', $reader->getString('perf_bulk_one'));
        self::assertTrue($reader->has('perf_bulk_null'));
        self::assertFalse($reader->has('perf_bulk_missing'));
        self::assertSame(3, $this->db->metrics()['queries']);
    }
}
