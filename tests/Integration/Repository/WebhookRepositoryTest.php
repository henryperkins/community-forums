<?php

declare(strict_types=1);

namespace Tests\Integration\Repository;

use App\Repository\WebhookRepository;
use Tests\Support\TestCase;

final class WebhookRepositoryTest extends TestCase
{
    private function repo(): WebhookRepository
    {
        return new WebhookRepository($this->db);
    }

    private function makeHook(): int
    {
        $admin = $this->makeAdmin();
        return $this->repo()->insert('ci', 'https://x.test/h', json_encode(['ping']) ?: '[]', 'svcsec_x', (int) $admin['id']);
    }

    public function test_insert_list_excludes_secret_ref_but_active_includes_it(): void
    {
        $id = $this->makeHook();
        $listed = $this->repo()->list();
        self::assertArrayNotHasKey('secret_ref', $listed[0]);
        $active = $this->repo()->activeEndpoints();
        self::assertSame('svcsec_x', $active[0]['secret_ref']);
        self::assertSame($id, (int) $active[0]['id']);
    }

    public function test_disable_then_enable_is_state_guarded(): void
    {
        $id = $this->makeHook();
        self::assertSame(1, $this->repo()->disable($id, 'broke'));
        self::assertSame(0, $this->repo()->disable($id, 'broke again'));
        self::assertSame([], $this->repo()->activeEndpoints());
        self::assertSame(1, $this->repo()->enable($id));
        self::assertSame(0, (int) $this->repo()->findById($id)['consecutive_failures']);
    }

    public function test_failure_counter_and_last_status(): void
    {
        $id = $this->makeHook();
        $this->repo()->incrementConsecutiveFailures($id);
        $this->repo()->incrementConsecutiveFailures($id);
        self::assertSame(2, (int) $this->repo()->findById($id)['consecutive_failures']);
        $this->repo()->setLastStatus($id, 200, true);
        self::assertSame(200, (int) $this->repo()->findById($id)['last_status']);
        $this->repo()->resetConsecutiveFailures($id);
        self::assertSame(0, (int) $this->repo()->findById($id)['consecutive_failures']);
    }
}
