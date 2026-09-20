<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;
use Tests\Support\TestCase;

final class AppRequestPerformanceTest extends TestCase
{
    private function install(): void
    {
        $this->makeAdmin(['username' => 'performanceadmin']);
        (new SettingRepository($this->db))->set('installed_at', '2026-09-20 00:00:00');
    }

    /** Measure only kernel queries, excluding the test client's CSRF refresh. */
    private function measured(string $path, string $method = 'GET', array $body = []): Response
    {
        $this->db->resetMetrics();
        return $this->app->handle(new Request($method, $path, [], $body, $this->cookies, [
            'REMOTE_ADDR' => '127.0.0.1', 'HTTP_USER_AGENT' => 'phpunit',
        ]));
    }

    public function test_home_and_thread_round_trips_stay_bounded(): void
    {
        $this->install();
        $author = $this->makeUser(['username' => 'performanceauthor']);
        $viewer = $this->makeUser(['username' => 'performancereader']);
        $this->users()->updateLastSeen((int) $viewer['id']);
        $board = $this->makeBoard($this->makeCategory('Performance'), ['slug' => 'performance']);
        $thread = $this->makeThread($board, $author, 'Performance fixture', 'A **public** post.');
        $path = '/t/' . $thread['thread_id'] . '-' . $thread['slug'];

        // Guest home still reads settings, categories, boards, directory topic
        // signals, theme state and public presence. The thread also loads its
        // enabled workflow/intelligence/poll surfaces, even for a guest.
        foreach (['guest' => [6, 27], 'member' => [20, 60]] as $viewerType => [$homeBudget, $threadBudget]) {
            if ($viewerType === 'member') {
                $this->actingAs($viewer);
            }
            foreach (['/' => $homeBudget, $path => $threadBudget] as $route => $budget) {
                $response = $this->measured($route);
                self::assertSame(200, $response->status());
                self::assertLessThanOrEqual($budget, $this->db->metrics()['queries'], $viewerType . ' ' . $route);
            }
        }
    }

    public function test_poll_and_preview_queries_do_not_include_the_html_shell(): void
    {
        $this->install();
        $viewer = $this->makeUser(['username' => 'performanceviewer']);
        $this->users()->updateLastSeen((int) $viewer['id']);
        $this->actingAs($viewer);

        foreach (['/presence' => 6, '/notifications/bell' => 12] as $path => $budget) {
            $response = $this->measured($path);
            self::assertSame(200, $response->status());
            self::assertSame('application/json; charset=UTF-8', $response->getHeader('Content-Type'));
            self::assertLessThanOrEqual($budget, $this->db->metrics()['queries'], $path);
            self::assertNull($response->getHeader('Link'));
        }

        $preview = $this->measured('/composer/preview', 'POST', [
            '_token' => $this->csrfToken(), 'body' => '**Preview**',
        ]);
        self::assertSame(200, $preview->status());
        self::assertStringContainsString('<strong>Preview</strong>', $preview->body());
        self::assertLessThanOrEqual(6, $this->db->metrics()['queries']);
    }

    public function test_cookie_free_guest_reads_and_guest_forms_keep_csrf_protection(): void
    {
        $this->install();
        $home = $this->get('/');
        self::assertSame(200, $home->status());
        self::assertSame([], $home->cookieHeaders());
        self::assertSame([], $this->get('/presence')->cookieHeaders());

        $login = $this->get('/login');
        self::assertSame(200, $login->status());
        self::assertNotEmpty($login->cookieHeaders());
        self::assertNotEmpty($this->csrfToken());
        $invalid = $this->post('/login', ['email' => 'missing@example.test', 'password' => 'incorrect']);
        // A valid token reaches login validation; it is not a CSRF rejection.
        self::assertSame(422, $invalid->status());
        self::assertSame(403, $this->post('/login', [], false)->status());
    }

    public function test_json_endpoint_errors_still_render_the_complete_shell(): void
    {
        $this->install();
        $settings = new SettingRepository($this->db);
        $settings->set('site_name', 'Performance Community');
        $settings->set('features', ['presence' => false]);
        $disabled = $this->get('/presence');
        self::assertSame(404, $disabled->status());
        self::assertStringContainsString('Performance Community', $disabled->body());
        self::assertStringContainsString('<!doctype html>', $disabled->body());

        $invalidCsrf = $this->post('/composer/preview', [], false);
        self::assertSame(403, $invalidCsrf->status());
        self::assertStringContainsString('Performance Community', $invalidCsrf->body());
        self::assertNotNull($invalidCsrf->getHeader('Content-Security-Policy'));
    }

    public function test_reused_kernel_does_not_keep_settings_or_permissions_between_requests(): void
    {
        $this->install();
        $settings = new SettingRepository($this->db);
        $settings->set('site_name', 'Before update');
        self::assertStringContainsString('Before update', $this->get('/')->body());
        $settings->set('site_name', 'After update');
        $settings->set('features', ['presence' => false]);
        self::assertStringContainsString('After update', $this->get('/')->body());
        self::assertSame(404, $this->get('/presence')->status());
    }

    public function test_html_preloads_the_same_public_assets_that_the_page_uses(): void
    {
        $this->install();
        $response = $this->get('/');
        $links = (string) $response->getHeader('Link');
        self::assertNotSame('', $links);
        preg_match_all('/<link rel="stylesheet" href="([^"]+)"/', $response->body(), $styles);
        foreach (array_slice($styles[1], 0, 2) as $url) {
            self::assertStringContainsString('<' . html_entity_decode($url) . '>; rel=preload; as=style', $links);
        }
        self::assertMatchesRegularExpression('/; rel=preload; as=script/', $links);
        self::assertMatchesRegularExpression('/\.woff2>; rel=preload; as=font; type="font\/woff2"; crossorigin/', $links);
        self::assertStringNotContainsString('/theme/', $links);
        self::assertNull($this->get('/healthz')->getHeader('Link'));
    }

    public function test_session_touch_is_throttled_without_extending_expiry_or_revocation(): void
    {
        $this->install();
        $viewer = $this->makeUser(['username' => 'sessionperformance']);
        $this->actingAs($viewer);
        $id = hash('sha256', $this->cookies['rb_session']);
        $recent = gmdate('Y-m-d H:i:s', time() - 20);
        $this->db->run('UPDATE sessions SET last_seen_at = ? WHERE id = ?', [$recent, $id]);

        self::assertSame(200, $this->get('/notifications/bell')->status());
        self::assertSame($recent, $this->db->fetchValue('SELECT last_seen_at FROM sessions WHERE id = ?', [$id]));

        $stale = gmdate('Y-m-d H:i:s', time() - 120);
        $this->db->run('UPDATE sessions SET last_seen_at = ? WHERE id = ?', [$stale, $id]);
        self::assertSame(200, $this->get('/notifications/bell')->status());
        self::assertNotSame($stale, $this->db->fetchValue('SELECT last_seen_at FROM sessions WHERE id = ?', [$id]));

        $this->db->run('UPDATE sessions SET revoked_at = UTC_TIMESTAMP() WHERE id = ?', [$id]);
        self::assertSame(302, $this->get('/notifications/bell')->status());
        $this->db->run('UPDATE sessions SET revoked_at = NULL, expires_at = ? WHERE id = ?', [$stale, $id]);
        self::assertSame(302, $this->get('/notifications/bell')->status());
    }
}
