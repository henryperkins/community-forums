<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\BoardController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Controller\ModerationController;
use App\Controller\PostController;
use App\Controller\ProfileController;
use App\Controller\SetupController;
use App\Controller\ThreadController;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\PostRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\Csrf;
use App\Security\FileRateLimiter;
use App\Security\PasswordHasher;
use App\Security\RateLimiter;
use App\Security\SecurityHeaders;
use App\Security\Session;
use App\Security\WriteGate;
use App\Service\AccountService;
use App\Service\AdminService;
use App\Service\AuthService;
use App\Service\ModerationService;
use App\Service\PostingService;
use App\Service\SetupService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use Throwable;

/**
 * The HTTP kernel. Boots configuration and the service container, runs the
 * request pipeline (session → setup gate → CSRF → route dispatch), and applies
 * baseline security headers to every response. handle() is pure
 * (Request → Response) so the whole stack is testable in-process.
 */
final class App
{
    private Router $router;

    public function __construct(
        private Config $config,
        private ?Database $database = null,
        private ?RateLimiter $rateLimiter = null,
    ) {
        $this->router = $this->buildRouter();
    }

    public static function boot(string $basePath): self
    {
        Env::load($basePath . '/.env');
        $config = Config::fromFile($basePath . '/config/config.php');
        return new self($config);
    }

    public function run(): void
    {
        $this->handle(Request::fromGlobals())->send();
    }

    public function handle(Request $request): Response
    {
        $container = $this->buildContainer($request);

        /** @var Session $session */
        $session = $container->get(Session::class);
        /** @var Flash $flash */
        $flash = $container->get(Flash::class);

        $session->start($request);
        $flash->load($request);
        $this->shareViewGlobals($container, $request);

        $response = $this->process($container, $request);

        SecurityHeaders::apply($response, (bool) $this->config->get('security.hsts', true));
        $session->commit($response);
        $flash->commit($response);

        return $response;
    }

    private function process(Container $container, Request $request): Response
    {
        try {
            $path = $request->path();

            // The health check must answer even when the database is unreachable,
            // so it is dispatched BEFORE the setup gate (which queries the DB).
            if ($path === '/healthz') {
                [$handler, $params] = $this->router->match($request->method(), $path);
                [$class, $method] = $handler;
                return (new $class($container))->{$method}($request, $params);
            }

            // First-run setup gate.
            $initialized = $container->get(SetupService::class)->isInitialized();
            if (!$initialized && $path !== '/setup') {
                return $this->redirect('/setup');
            }
            if ($initialized && $path === '/setup') {
                return $this->redirect('/');
            }

            // CSRF on every state-changing request.
            if ($request->isPost() && !$container->get(Csrf::class)->verify((string) ($request->post('_token') ?? ''))) {
                return $this->renderError($container, 403, 'That form has expired or its security token was invalid. Please go back, reload, and try again.');
            }

            [$handler, $params] = $this->router->match($request->method(), $path);
            [$class, $method] = $handler;

            $controller = new $class($container);
            return $controller->{$method}($request, $params);
        } catch (HttpException $e) {
            if ($e->redirectTo !== null) {
                return $this->redirect($e->redirectTo, $e->statusCode() === 302 ? 302 : 303);
            }
            return $this->renderError($container, $e->statusCode(), $e->getMessage());
        } catch (Throwable $e) {
            return $this->renderServerError($container, $e);
        }
    }

    private function redirect(string $to, int $status = 302): Response
    {
        return Response::redirect($to, $status);
    }

    private function renderError(Container $container, int $status, string $message): Response
    {
        $message = $message !== '' ? $message : $this->defaultErrorMessage($status);
        try {
            $html = $container->get(View::class)->render('errors/error', [
                'status' => $status,
                'message' => $message,
            ]);
            return Response::html($html, $status);
        } catch (Throwable) {
            return Response::html('<h1>' . $status . '</h1><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>', $status);
        }
    }

    private function renderServerError(Container $container, Throwable $e): Response
    {
        error_log('[RetroBoards] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
        $debug = (bool) $this->config->get('app.debug', false);
        $message = $debug
            ? $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine()
            : 'Something went wrong. Please try again later.';
        return $this->renderError($container, 500, $message);
    }

    private function defaultErrorMessage(int $status): string
    {
        return match ($status) {
            403 => 'You do not have permission to view this page.',
            404 => 'The page you were looking for could not be found.',
            405 => 'That action is not allowed here.',
            default => 'Something went wrong.',
        };
    }

    private function shareViewGlobals(Container $container, Request $request): void
    {
        /** @var Session $session */
        $session = $container->get(Session::class);
        /** @var Flash $flash */
        $flash = $container->get(Flash::class);

        $appName = (string) $this->config->get('app.name', 'RetroBoards');
        $siteName = $appName;
        $nav = [];

        // Defensive: the shell renders before the setup gate and even on a
        // not-yet-migrated database, so a missing table must not 500 the app.
        try {
            $siteName = $container->get(SettingRepository::class)->getString('site_name', $appName);
            $nav = $this->buildNav($container, $session->user());
        } catch (Throwable) {
            $siteName = $appName;
            $nav = [];
        }

        $container->get(View::class)->share([
            'site_name' => $siteName,
            'app_name' => $appName,
            'current_user' => $session->user(),
            'csrf_token' => $container->get(Csrf::class)->token(),
            'flash' => $flash->current(),
            'request_path' => $request->path(),
            'nav' => $nav,
        ]);
    }

    /**
     * Sidebar navigation: categories with the boards visible to this viewer.
     *
     * @return array<int,array{category:array<string,mixed>,boards:array<int,array<string,mixed>>}>
     */
    private function buildNav(Container $container, ?\App\Domain\User $user): array
    {
        $policy = $container->get(BoardPolicy::class);
        $categories = $container->get(CategoryRepository::class)->all();
        $allBoards = $container->get(BoardRepository::class)->allOrdered();

        $nav = [];
        foreach ($categories as $category) {
            $boards = array_values(array_filter(
                $allBoards,
                fn (array $b): bool => (int) $b['category_id'] === (int) $category['id'] && $policy->isListed($b, $user),
            ));
            if ($boards !== []) {
                $nav[] = ['category' => $category, 'boards' => $boards];
            }
        }
        return $nav;
    }

    private function buildContainer(Request $request): Container
    {
        $config = $this->config;
        $c = new Container();

        $c->instance(Config::class, $config);
        $c->instance('request', $request);

        if ($this->database !== null) {
            $c->instance(Database::class, $this->database);
        } else {
            $c->bind(Database::class, fn () => new Database($config->get('db')));
        }

        if ($this->rateLimiter !== null) {
            $c->instance(RateLimiter::class, $this->rateLimiter);
        } else {
            $c->bind(RateLimiter::class, fn () => new FileRateLimiter((string) $config->get('paths.ratelimit')));
        }

        // Support + security primitives.
        $c->bind(HtmlSanitizer::class, fn () => new HtmlSanitizer());
        $c->bind(Markdown::class, fn (Container $c) => new Markdown($c->get(HtmlSanitizer::class)));
        $c->bind(PasswordHasher::class, fn () => new PasswordHasher());
        $c->bind(WriteGate::class, fn () => new WriteGate());
        $c->bind(BoardPolicy::class, fn () => new BoardPolicy());
        $c->bind(View::class, fn () => new View((string) $config->get('paths.templates')));
        $c->bind(Flash::class, fn () => new Flash((bool) $config->get('session.secure', true)));

        // Repositories.
        $c->bind(UserRepository::class, fn (Container $c) => new UserRepository($c->get(Database::class)));
        $c->bind(SessionRepository::class, fn (Container $c) => new SessionRepository($c->get(Database::class)));
        $c->bind(SettingRepository::class, fn (Container $c) => new SettingRepository($c->get(Database::class)));
        $c->bind(CategoryRepository::class, fn (Container $c) => new CategoryRepository($c->get(Database::class)));
        $c->bind(BoardRepository::class, fn (Container $c) => new BoardRepository($c->get(Database::class)));
        $c->bind(ThreadRepository::class, fn (Container $c) => new ThreadRepository($c->get(Database::class)));
        $c->bind(PostRepository::class, fn (Container $c) => new PostRepository($c->get(Database::class)));
        $c->bind(ModerationLogRepository::class, fn (Container $c) => new ModerationLogRepository($c->get(Database::class)));

        // Session + CSRF.
        $c->bind(Session::class, fn (Container $c) => new Session(
            $c->get(SessionRepository::class),
            $c->get(UserRepository::class),
            $config->get('session'),
        ));
        $c->bind(Csrf::class, fn (Container $c) => new Csrf($c->get(Session::class)));

        // Services.
        $c->bind(AuthService::class, fn (Container $c) => new AuthService(
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $config,
        ));
        $c->bind(AccountService::class, fn (Container $c) => new AccountService(
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(WriteGate::class),
            $config,
        ));
        $c->bind(PostingService::class, fn (Container $c) => new PostingService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(BoardRepository::class),
            $c->get(UserRepository::class),
            $c->get(Markdown::class),
            $c->get(WriteGate::class),
            $c->get(BoardPolicy::class),
            $config,
        ));
        $c->bind(ModerationService::class, fn (Container $c) => new ModerationService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(PostingService::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(AdminService::class, fn (Container $c) => new AdminService(
            $c->get(Database::class),
            $c->get(CategoryRepository::class),
            $c->get(BoardRepository::class),
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(SetupService::class, fn (Container $c) => new SetupService(
            $c->get(Database::class),
            $c->get(AuthService::class),
            $c->get(UserRepository::class),
            $c->get(SettingRepository::class),
            $c->get(CategoryRepository::class),
            $c->get(BoardRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Session::class),
        ));

        return $c;
    }

    private function buildRouter(): Router
    {
        $r = new Router();

        $r->get('/', [HomeController::class, 'index']);
        $r->get('/healthz', [HealthController::class, 'check']);

        $r->get('/c/{slug}', [BoardController::class, 'show']);
        $r->get('/t/{id}-{slug}', [ThreadController::class, 'show']);
        $r->get('/t/{id}', [ThreadController::class, 'show']);
        $r->get('/u/{username}', [ProfileController::class, 'show']);

        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->post('/logout', [AuthController::class, 'logout']);

        $r->get('/settings', [AccountController::class, 'index']);
        $r->get('/settings/account', [AccountController::class, 'accountForm']);
        $r->post('/settings/account', [AccountController::class, 'updateAccount']);
        $r->get('/settings/security', [AccountController::class, 'securityForm']);
        $r->post('/settings/security', [AccountController::class, 'updateSecurity']);

        $r->get('/setup', [SetupController::class, 'show']);
        $r->post('/setup', [SetupController::class, 'submit']);

        $r->post('/threads', [PostController::class, 'createThread']);
        $r->post('/t/{id}/reply', [PostController::class, 'reply']);
        $r->post('/posts/{id}/edit', [PostController::class, 'edit']);
        $r->post('/posts/{id}/delete', [PostController::class, 'delete']);

        $r->get('/admin', [AdminController::class, 'dashboard']);
        $r->get('/admin/structure', [AdminController::class, 'structure']);
        $r->post('/admin/site', [AdminController::class, 'updateSite']);
        $r->post('/admin/categories', [AdminController::class, 'createCategory']);
        $r->post('/admin/categories/{id}', [AdminController::class, 'updateCategory']);
        $r->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
        $r->get('/admin/boards/{id}/edit', [AdminController::class, 'editBoard']);
        $r->post('/admin/boards', [AdminController::class, 'createBoard']);
        $r->post('/admin/boards/{id}', [AdminController::class, 'updateBoard']);
        $r->post('/admin/boards/{id}/delete', [AdminController::class, 'deleteBoard']);

        $r->post('/mod/t/{id}/pin', [ModerationController::class, 'pin']);
        $r->post('/mod/t/{id}/lock', [ModerationController::class, 'lock']);

        return $r;
    }
}
