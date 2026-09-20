<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Core\Request;
use App\Core\View;
use App\Repository\NotificationRepository;
use App\Repository\SettingRepository;
use App\Security\Session;
use Tests\Support\TestCase;

final class NotificationShellStatement extends \PDOStatement
{
    public static array $queries = [];
    public static array $scopeQueries = [];
    public function execute(?array $params = null): bool
    {
        self::$queries[] = $this->queryString;
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        foreach ($trace as $frame) {
            // Include reads delegated to repositories while resolving scope,
            // excluding independent authority checks elsewhere in the request.
            if (($frame['class'] ?? '') === \App\Service\NotificationVisibilityService::class) {
                self::$scopeQueries[] = $this->queryString;
                break;
            }
        }
        return parent::execute($params);
    }
}

final class AppNotificationShellTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
        $this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [NotificationShellStatement::class]);
    }

    protected function tearDown(): void
    {
        $this->pdo->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [\PDOStatement::class]);
        parent::tearDown();
    }

    private function counts(): array
    {
        return array_values(array_filter(NotificationShellStatement::$queries,
            static fn (string $sql): bool => str_starts_with($sql, 'SELECT COUNT(*)') && str_contains($sql, 'FROM notifications n')));
    }

    private function lazyCount(?\App\Core\Database $lookupDb = null): \Closure
    {
        $request = new Request('GET', '/', [], [], $this->cookies, ['REMOTE_ADDR' => '127.0.0.1']);
        $container = (new \ReflectionMethod($this->app, 'buildContainer'))->invoke($this->app, $request);
        $container->get(Session::class)->start($request);
        if ($lookupDb !== null) {
            $container->bind(\App\Service\NotificationReadService::class, static fn () => new \App\Service\NotificationReadService(
                new NotificationRepository($lookupDb), new \App\Service\NotificationVisibilityService($lookupDb),
            ));
        }
        (new \ReflectionMethod($this->app, 'shareViewGlobals'))->invoke($this->app, $container, $request);
        return $container->get(View::class)->shared('notification_unread');
    }

    public function test_member_and_admin_initial_counts_are_visible_capped_and_accessible(): void
    {
        foreach ([$this->makeUser(), $this->makeAdmin()] as $member) {
            $this->actingAs($member);
            $repo = new NotificationRepository($this->db);
            foreach ([0, 105] as $unread) {
                for ($i = 0; $i < $unread; $i++) {
                    $repo->create(['user_id' => (int) $member['id'], 'type' => 'badge']);
                }
                foreach (array_merge(['/', '/notifications'], $member['role'] === 'admin' ? ['/admin'] : []) as $path) {
                    NotificationShellStatement::$queries = [];
                    NotificationShellStatement::$scopeQueries = [];
                    $response = $this->get($path);
                    $this->assertStatus(200, $response);
                    self::assertCount(1, $this->counts(), $path . ' has one shared count query');
                    $doc = new \DOMDocument();
                    @$doc->loadHTML($response->body());
                    $xpath = new \DOMXPath($doc);
                    $bell = $xpath->query('//a[@data-bell]');
                    self::assertSame(1, $bell->length);
                    self::assertSame(0, $xpath->query('//details//a[@data-bell]')->length, 'primary bell is outside the closed account menu');
                    self::assertSame($unread ? 'Notifications, 105 unread' : 'Notifications', $bell->item(0)->getAttribute('aria-label'));
                    foreach ($xpath->query('//*[@data-notification-count]') as $badge) {
                        self::assertSame($unread ? '99+' : '0', $badge->textContent);
                        self::assertSame($unread === 0, $badge->hasAttribute('hidden'));
                    }
                    self::assertGreaterThan(0, $xpath->query('//*[@data-notification-count]')->length);
                }
            }
        }
    }

    public function test_plain_health_and_unrelated_json_do_no_notification_count_work(): void
    {
        foreach (['/login', '/register'] as $path) {
            NotificationShellStatement::$queries = [];
            NotificationShellStatement::$scopeQueries = [];
            $this->assertStatus(200, $this->get($path));
            self::assertSame([], $this->counts());
            self::assertSame([], NotificationShellStatement::$scopeQueries);
        }
        $this->actingAs($this->makeUser());
        foreach (['/healthz', '/login', '/register', '/presence'] as $path) {
            NotificationShellStatement::$queries = [];
            NotificationShellStatement::$scopeQueries = [];
            $this->get($path);
            self::assertSame([], $this->counts(), $path);
            self::assertSame([], NotificationShellStatement::$scopeQueries, $path . ' does not resolve notification scope');
        }
    }

    public function test_bell_resolves_one_scope_and_count_without_shell_duplication(): void
    {
        $this->actingAs($this->makeUser());
        NotificationShellStatement::$queries = [];
        NotificationShellStatement::$scopeQueries = [];
        $response = $this->get('/notifications/bell');
        $this->assertStatus(200, $response);
        self::assertSame(0, json_decode($response->body(), true)['unread']);
        self::assertCount(1, $this->counts());
        self::assertSame(1, count(array_filter(NotificationShellStatement::$scopeQueries,
            static fn (string $sql): bool => $sql === 'SELECT board_id FROM board_members WHERE user_id = ?')));
        self::assertSame(1, count(array_filter(NotificationShellStatement::$scopeQueries,
            static fn (string $sql): bool => $sql === 'SELECT board_id FROM board_moderators WHERE user_id = ?')));
    }

    public function test_shell_count_is_lazy_and_caches_zero_and_lookup_failure(): void
    {
        $this->actingAs($this->makeUser());
        NotificationShellStatement::$queries = [];
        NotificationShellStatement::$scopeQueries = [];
        $count = $this->lazyCount();
        self::assertSame([], $this->counts());
        self::assertSame(0, $count());
        $after = $this->db->metrics()['queries'];
        self::assertSame(0, $count());
        self::assertSame($after, $this->db->metrics()['queries']);
        self::assertCount(1, $this->counts());
        // A temporary, incomplete table shadows only this connection's later schema.
        $this->pdo->exec('CREATE TEMPORARY TABLE notifications (id INT)');
        try {
            $count = $this->lazyCount();
            self::assertSame(0, $count());
            $after = $this->db->metrics()['queries'];
            self::assertSame(0, $count());
            self::assertSame($after, $this->db->metrics()['queries'], 'failed lookups are also memoized');
            $this->assertStatus(200, $this->get('/settings/account'));
            $this->assertStatus(200, $this->get('/healthz'));
        } finally {
            $this->pdo->exec('DROP TEMPORARY TABLE notifications');
        }
    }

    public function test_unreachable_notification_database_defaults_to_zero_once(): void
    {
        $this->actingAs($this->makeUser());
        $badDb = new \App\Core\Database([
            'host' => '127.0.0.1', 'port' => 1, 'database' => 'nope',
            'username' => 'x', 'password' => 'y', 'charset' => 'utf8mb4',
        ]);
        $count = $this->lazyCount($badDb);
        self::assertSame(0, $badDb->metrics()['connections']);
        self::assertSame(0, $count());
        $attempts = $badDb->metrics()['connections'];
        self::assertGreaterThan(0, $attempts);
        self::assertSame(0, $count());
        self::assertSame($attempts, $badDb->metrics()['connections']);
    }

    public function test_guest_and_disabled_shell_have_no_dead_bell_or_count_work(): void
    {
        NotificationShellStatement::$queries = [];
        NotificationShellStatement::$scopeQueries = [];
        self::assertStringNotContainsString('data-bell', $this->get('/')->body());
        self::assertSame([], $this->counts());
        $this->actingAs($this->makeUser());
        (new SettingRepository($this->db))->set('features', ['notifications' => false]);
        NotificationShellStatement::$queries = [];
        NotificationShellStatement::$scopeQueries = [];
        self::assertSame(0, ($this->lazyCount())());
        self::assertStringNotContainsString('data-notification-link', $this->get('/')->body());
        self::assertSame([], $this->counts());
        $this->assertStatus(404, $this->get('/notifications/bell'));
    }
}
