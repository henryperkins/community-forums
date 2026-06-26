<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use Tests\Support\TestCase;

/**
 * Foundation + read path: health, security headers, the setup gate, guest
 * reading, pagination, and 404s.
 */
final class AppTest extends TestCase
{
    public function test_healthz_reports_ok_with_database_status(): void
    {
        $response = $this->get('/healthz');
        $this->assertStatus(200, $response);
        self::assertSame('application/json; charset=UTF-8', $response->getHeader('content-type'));
        self::assertSame('{"status":"ok","database":"ok"}', $response->body());
    }

    public function test_healthz_reports_down_when_database_unreachable(): void
    {
        // An App whose database points at a refused port → ping() returns false.
        $badDb = new Database([
            'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope',
            'username' => 'x', 'password' => 'y', 'charset' => 'utf8mb4',
        ]);
        $app = new App($this->config, $badDb, $this->rateLimiter);
        $response = $app->handle(new Request('GET', '/healthz', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']));

        self::assertSame(503, $response->status());
        self::assertSame('{"status":"error","database":"down"}', $response->body());
        // No secrets leaked.
        self::assertStringNotContainsString('password', $response->body());
        self::assertStringNotContainsString('127.0.0.1', $response->body());
    }

    public function test_head_request_is_handled_like_get(): void
    {
        $this->makeAdmin();
        $response = $this->request('HEAD', '/healthz', [], []);
        self::assertSame(200, $response->status());
    }

    public function test_security_headers_present_on_every_response(): void
    {
        $response = $this->get('/healthz');
        self::assertNotNull($response->getHeader('content-security-policy'));
        self::assertStringContainsString("default-src 'self'", (string) $response->getHeader('content-security-policy'));
        self::assertSame('nosniff', $response->getHeader('x-content-type-options'));
        self::assertSame('strict-origin-when-cross-origin', $response->getHeader('referrer-policy'));
        self::assertStringContainsString('max-age=', (string) $response->getHeader('strict-transport-security'));
    }

    public function test_uninitialised_install_redirects_to_setup(): void
    {
        // No admin exists → not initialised.
        $this->assertRedirect($this->get('/'), '/setup');
        $this->assertStatus(200, $this->get('/setup'));
    }

    public function test_initialised_install_blocks_setup_route(): void
    {
        $this->makeAdmin();
        $this->assertRedirect($this->get('/setup'), '/');
    }

    public function test_guest_can_read_home_board_and_thread(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser(['username' => 'opauthor']);
        $categoryId = $this->makeCategory('General');
        $board = $this->makeBoard($categoryId, ['slug' => 'general', 'name' => 'General']);
        $thread = $this->makeThread($board, $author, 'Welcome thread', 'Hello **world**');

        $home = $this->get('/');
        $this->assertStatus(200, $home);
        $this->assertSeeText($home, 'General');

        $boardPage = $this->get('/c/general');
        $this->assertStatus(200, $boardPage);
        $this->assertSeeText($boardPage, 'Welcome thread');

        $threadPage = $this->get('/t/' . $thread['thread_id'] . '-' . $thread['slug']);
        $this->assertStatus(200, $threadPage);
        $this->assertSeeText($threadPage, '<strong>world</strong>');
        // Guest sees the join-bar, not a writable composer.
        $this->assertSeeText($threadPage, 'log in</a> to reply');
        $this->assertDontSeeText($threadPage, 'name="body"');
    }

    public function test_thread_canonicalises_id_only_url(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'canon']);
        $thread = $this->makeThread($board, $author, 'Canonical title');

        $response = $this->get('/t/' . $thread['thread_id']);
        $this->assertRedirect($response, '/t/' . $thread['thread_id'] . '-' . $thread['slug']);
    }

    public function test_unknown_routes_return_404(): void
    {
        $this->makeAdmin();
        $this->assertStatus(404, $this->get('/c/does-not-exist'));
        $this->assertStatus(404, $this->get('/no/such/path'));
    }

    public function test_thread_list_pagination(): void
    {
        $this->makeAdmin();
        $author = $this->makeUser();
        $board = $this->makeBoard($this->makeCategory(), ['slug' => 'busy']);
        for ($i = 0; $i < 25; $i++) {
            $this->makeThread($board, $author, 'Topic ' . $i, 'body');
        }

        $page1 = $this->get('/c/busy');
        $this->assertStatus(200, $page1);
        $this->assertSeeText($page1, 'page=2');

        $page2 = $this->get('/c/busy', ['page' => 2]);
        $this->assertStatus(200, $page2);
    }
}
