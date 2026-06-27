<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Core\App;
use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Domain\User;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\PostRepository;
use App\Repository\SessionRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\ArrayRateLimiter;
use App\Security\BoardPolicy;
use App\Security\PasswordHasher;
use App\Security\WriteGate;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use PDO;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base for integration tests. Each test runs inside a transaction that is rolled
 * back in tearDown (fast isolation), drives the real App kernel in-process via a
 * cookie-jar HTTP client, and tracks the CSRF secret so POSTs are authenticated.
 */
abstract class TestCase extends BaseTestCase
{
    protected PDO $pdo;
    protected Database $db;
    protected Config $config;
    protected App $app;
    protected ArrayRateLimiter $rateLimiter;

    /** @var array<string,string> */
    protected array $cookies = [];
    protected ?string $csrfSecret = null;

    protected function setUp(): void
    {
        $this->pdo = $GLOBALS['__RB_TEST_PDO'];
        $this->config = $GLOBALS['__RB_TEST_CONFIG'];

        $this->db = new Database($GLOBALS['__RB_TEST_DBCONFIG']);
        $this->db->setPdo($this->pdo);

        $this->pdo->beginTransaction();

        $this->rateLimiter = new ArrayRateLimiter();
        $this->app = new App($this->config, $this->db, $this->rateLimiter);

        $this->cookies = [];
        $this->csrfSecret = null;
    }

    protected function tearDown(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    // ---- HTTP client ------------------------------------------------------

    /** @param array<string,mixed> $query */
    protected function get(string $path, array $query = []): Response
    {
        return $this->request('GET', $path, [], $query);
    }

    /** @param array<string,mixed> $body */
    protected function post(string $path, array $body = [], bool $withToken = true): Response
    {
        if ($withToken && !array_key_exists('_token', $body)) {
            $body['_token'] = $this->csrfToken();
        }
        return $this->request('POST', $path, $body, []);
    }

    /**
     * @param array<string,mixed> $body
     * @param array<string,mixed> $query
     */
    protected function request(string $method, string $path, array $body, array $query): Response
    {
        $request = new Request($method, $path, $query, $body, $this->cookies, [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'phpunit',
        ]);

        $response = $this->app->handle($request);
        $this->applyCookies($response);
        $this->refreshSecret();
        return $response;
    }

    protected function csrfToken(): string
    {
        return $this->csrfSecret === null ? '' : hash_hmac('sha256', 'rb-csrf-token', $this->csrfSecret);
    }

    private function applyCookies(Response $response): void
    {
        foreach ($response->cookieHeaders() as $header) {
            $parts = explode(';', $header);
            [$name, $value] = array_pad(explode('=', $parts[0], 2), 2, '');
            $name = rawurldecode(trim($name));
            $value = rawurldecode(trim($value));

            $deleting = $value === '';
            foreach ($parts as $segment) {
                if (stripos(trim($segment), 'Max-Age=0') === 0) {
                    $deleting = true;
                }
            }

            if ($deleting) {
                unset($this->cookies[$name]);
            } else {
                $this->cookies[$name] = $value;
            }
        }
    }

    /** Keep the CSRF secret in sync with the current cookie/session state. */
    private function refreshSecret(): void
    {
        if (isset($this->cookies['rb_session'])) {
            $id = hash('sha256', $this->cookies['rb_session']);
            $secret = $this->db->fetchValue('SELECT csrf_secret FROM sessions WHERE id = ?', [$id]);
            if ($secret !== false && $secret !== null) {
                $this->csrfSecret = (string) $secret;
                return;
            }
        }
        if (isset($this->cookies['rb_csrf'])) {
            $this->csrfSecret = $this->cookies['rb_csrf'];
            return;
        }
        $this->csrfSecret = null;
    }

    /** Programmatically authenticate as a user for subsequent requests. */
    protected function actingAs(array $userRow): void
    {
        $raw = bin2hex(random_bytes(32));
        $id = hash('sha256', $raw);
        $secret = bin2hex(random_bytes(32));

        (new SessionRepository($this->db))->create([
            'id' => $id,
            'user_id' => (int) $userRow['id'],
            'csrf_secret' => $secret,
            'user_agent' => 'phpunit',
            'expires_at' => gmdate('Y-m-d H:i:s', time() + 86400),
        ]);

        $this->cookies['rb_session'] = $raw;
        unset($this->cookies['rb_csrf']);
        $this->csrfSecret = $secret;
    }

    protected function logoutClient(): void
    {
        $this->cookies = [];
        $this->csrfSecret = null;
    }

    // ---- Seeding ----------------------------------------------------------

    /**
     * @param array<string,mixed> $attrs
     * @return array<string,mixed> the users row
     */
    protected function makeUser(array $attrs = []): array
    {
        $users = new UserRepository($this->db);
        $username = $attrs['username'] ?? ('u' . bin2hex(random_bytes(4)));
        $id = $users->create([
            'username' => $username,
            'email' => $attrs['email'] ?? ($username . '@example.test'),
            'password_hash' => (new PasswordHasher())->hash($attrs['password'] ?? 'password123'),
            'display_name' => $attrs['display_name'] ?? null,
            'role' => $attrs['role'] ?? 'user',
            'status' => $attrs['status'] ?? 'active',
        ]);
        if (($attrs['status'] ?? 'active') !== 'active' || isset($attrs['suspended_until'])) {
            $users->setStatus($id, $attrs['status'] ?? 'active', $attrs['suspended_until'] ?? null);
        }
        return $users->find($id);
    }

    /** @param array<string,mixed> $attrs */
    protected function makeAdmin(array $attrs = []): array
    {
        return $this->makeUser(['role' => 'admin'] + $attrs);
    }

    protected function userEntity(array $row): User
    {
        return User::fromRow($row);
    }

    protected function makeCategory(string $name = 'General'): int
    {
        return (new CategoryRepository($this->db))->create($name);
    }

    /** @param array<string,mixed> $attrs */
    protected function makeBoard(int $categoryId, array $attrs = []): array
    {
        $boards = new BoardRepository($this->db);
        $slug = $attrs['slug'] ?? ('b' . bin2hex(random_bytes(4)));
        $id = $boards->create([
            'category_id' => $categoryId,
            'slug' => $slug,
            'name' => $attrs['name'] ?? 'Board ' . $slug,
            'description' => $attrs['description'] ?? null,
            'visibility' => $attrs['visibility'] ?? 'public',
            'post_min_role' => $attrs['post_min_role'] ?? 'user',
            'allow_anonymous' => $attrs['allow_anonymous'] ?? 0,
        ]);
        return $boards->find($id);
    }

    /** @return array{thread_id:int, slug:string} */
    protected function makeThread(array $boardRow, array $authorRow, string $title = 'A topic', string $body = 'Opening post body.'): array
    {
        return $this->posting()->createThread(
            $this->userEntity($authorRow),
            ['board_id' => (int) $boardRow['id'], 'title' => $title, 'body' => $body],
        );
    }

    protected function posting(): PostingService
    {
        return new PostingService(
            $this->db,
            new ThreadRepository($this->db),
            new PostRepository($this->db),
            new BoardRepository($this->db),
            new UserRepository($this->db),
            new Markdown(new HtmlSanitizer()),
            new WriteGate(),
            new BoardPolicy(),
            $this->config,
        );
    }

    // ---- Convenience accessors -------------------------------------------

    protected function users(): UserRepository
    {
        return new UserRepository($this->db);
    }

    protected function threads(): ThreadRepository
    {
        return new ThreadRepository($this->db);
    }

    protected function posts(): PostRepository
    {
        return new PostRepository($this->db);
    }

    protected function boards(): BoardRepository
    {
        return new BoardRepository($this->db);
    }

    // ---- Assertions -------------------------------------------------------

    protected function assertStatus(int $expected, Response $response): void
    {
        self::assertSame($expected, $response->status(), 'Unexpected HTTP status. Body: ' . substr($response->body(), 0, 400));
    }

    protected function assertRedirect(Response $response, ?string $location = null): void
    {
        self::assertContains($response->status(), [301, 302, 303], 'Expected a redirect status.');
        if ($location !== null) {
            self::assertSame($location, $response->getHeader('location'));
        }
    }

    protected function assertRedirectContains(Response $response, string $needle): void
    {
        self::assertContains($response->status(), [301, 302, 303], 'Expected a redirect status.');
        self::assertStringContainsString($needle, (string) $response->getHeader('location'));
    }

    protected function assertSeeText(Response $response, string $text): void
    {
        self::assertStringContainsString($text, $response->body());
    }

    protected function assertDontSeeText(Response $response, string $text): void
    {
        self::assertStringNotContainsString($text, $response->body());
    }
}
