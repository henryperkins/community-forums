<?php

declare(strict_types=1);

use App\Core\Request;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

// Uses the dedicated PHPUnit database, seeds a rollback-only fixture and
// measures kernel queries without the HTTP test client's extra CSRF lookup.
require dirname(__DIR__) . '/bootstrap.php';

final class RequestPerformanceProbe extends TestCase
{
    public function measure(): array
    {
        $this->setUp();
        try {
            $this->makeAdmin(['username' => 'perfadmin']);
            $author = $this->makeUser(['username' => 'perfauthor']);
            $viewer = $this->makeUser(['username' => 'perfviewer']);
            $this->users()->updateLastSeen((int) $viewer['id']);
            (new SettingRepository($this->db))->set('installed_at', '2026-09-20 00:00:00');
            $board = $this->makeBoard($this->makeCategory('Performance'), ['slug' => 'performance']);
            $thread = $this->makeThread($board, $author, 'Performance fixture', 'A **public** post.');
            $path = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];
            $samples = [];
            $measure = function (string $name, string $route, string $method = 'GET', array $body = []) use (&$samples): void {
                $this->db->resetMetrics();
                $started = hrtime(true);
                $response = $this->app->handle(new Request($method, $route, [], $body, $this->cookies, [
                    'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'performance-probe',
                ]));
                $samples[$name] = ['status' => $response->status()] + $this->db->metrics() + [
                    'total_ms' => round((hrtime(true) - $started) / 1_000_000, 3),
                ];
            };

            $measure('guest_home', '/');
            $measure('guest_thread', $path);
            $this->actingAs($viewer);
            $measure('member_home', '/');
            $measure('member_thread', $path);
            $measure('presence', '/presence');
            $measure('bell', '/notifications/bell');
            $measure('preview', '/composer/preview', 'POST', [
                '_token' => $this->csrfToken(), 'body' => '**Preview**',
            ]);
            return $samples;
        } finally {
            $this->tearDown();
        }
    }
}

echo json_encode((new RequestPerformanceProbe('measure'))->measure(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
