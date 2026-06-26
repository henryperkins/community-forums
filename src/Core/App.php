<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\AuthController;
use App\Controller\BoardController;
use App\Controller\ConversationController;
use App\Controller\EngagementController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Controller\InboxController;
use App\Controller\ModerationController;
use App\Controller\PostController;
use App\Controller\ProfileController;
use App\Controller\ReportController;
use App\Controller\SearchController;
use App\Controller\NotificationController;
use App\Controller\SetupController;
use App\Controller\SubscriptionController;
use App\Controller\UserModerationController;
use App\Controller\ThreadController;
use App\Controller\UnsubscribeController;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Mail\SendmailMailer;
use App\Search\MysqlSearchService;
use App\Search\SearchService;
use App\Repository\BlockRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\ReportRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadUserRepository;
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
use App\Service\DirectMessageService;
use App\Service\ModerationService;
use App\Service\NotificationService;
use App\Service\PostingService;
use App\Service\ReactionService;
use App\Service\RepairService;
use App\Service\ReportService;
use App\Service\UserModerationService;
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

        $features = [];
        try {
            $features = $container->get(FeatureFlags::class)->all();
        } catch (Throwable) {
            $features = [];
        }

        $container->get(View::class)->share([
            'site_name' => $siteName,
            'app_name' => $appName,
            'current_user' => $session->user(),
            'csrf_token' => $container->get(Csrf::class)->token(),
            'flash' => $flash->current(),
            'request_path' => $request->path(),
            'nav' => $nav,
            'features' => $features,
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

        // Private boards the viewer belongs to should appear in their sidebar.
        $memberBoardIds = [];
        if ($user !== null) {
            $memberBoardIds = array_flip($container->get(BoardMemberRepository::class)->boardIdsFor($user->id()));
        }

        $nav = [];
        foreach ($categories as $category) {
            $boards = array_values(array_filter(
                $allBoards,
                fn (array $b): bool => (int) $b['category_id'] === (int) $category['id']
                    && $policy->isListed($b, $user, isset($memberBoardIds[(int) $b['id']])),
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
        $c->bind(BlockRepository::class, fn (Container $c) => new BlockRepository($c->get(Database::class)));
        $c->bind(BoardModeratorRepository::class, fn (Container $c) => new BoardModeratorRepository($c->get(Database::class)));
        $c->bind(BoardMemberRepository::class, fn (Container $c) => new BoardMemberRepository($c->get(Database::class)));
        $c->bind(ThreadUserRepository::class, fn (Container $c) => new ThreadUserRepository($c->get(Database::class)));
        $c->bind(ReactionRepository::class, fn (Container $c) => new ReactionRepository($c->get(Database::class)));
        $c->bind(SubscriptionRepository::class, fn (Container $c) => new SubscriptionRepository($c->get(Database::class)));
        $c->bind(NotificationRepository::class, fn (Container $c) => new NotificationRepository($c->get(Database::class)));
        $c->bind(EmailDeliveryRepository::class, fn (Container $c) => new EmailDeliveryRepository($c->get(Database::class)));
        $c->bind(EmailSuppressionRepository::class, fn (Container $c) => new EmailSuppressionRepository($c->get(Database::class)));
        $c->bind(ConversationRepository::class, fn (Container $c) => new ConversationRepository($c->get(Database::class)));
        $c->bind(DmMessageRepository::class, fn (Container $c) => new DmMessageRepository($c->get(Database::class)));
        $c->bind(ReportRepository::class, fn (Container $c) => new ReportRepository($c->get(Database::class)));

        // Phase 2 shared services.
        $c->bind(FeatureFlags::class, fn (Container $c) => new FeatureFlags($c->get(SettingRepository::class)));
        $c->bind(RepairService::class, fn (Container $c) => new RepairService($c->get(Database::class)));
        $c->bind(SearchService::class, fn (Container $c) => new MysqlSearchService($c->get(Database::class)));
        $c->bind(Mailer::class, function () use ($config): Mailer {
            $mail = (array) $config->get('mail', []);
            if (($mail['driver'] ?? 'sendmail') === 'array') {
                return new ArrayMailer();
            }
            return new SendmailMailer((string) ($mail['from'] ?? ''), (string) ($mail['from_name'] ?? 'RetroBoards'));
        });
        $c->bind(NotificationService::class, fn (Container $c) => new NotificationService(
            $c->get(Database::class),
            $c->get(NotificationRepository::class),
            $c->get(SubscriptionRepository::class),
            $c->get(EmailDeliveryRepository::class),
            $c->get(EmailSuppressionRepository::class),
            $c->get(BlockRepository::class),
            $c->get(UserRepository::class),
            $c->get(FeatureFlags::class),
            $c->get(Mailer::class),
        ));
        $c->bind(UserModerationService::class, fn (Container $c) => new UserModerationService(
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(BoardModeratorRepository::class),
        ));
        $c->bind(ReportService::class, fn (Container $c) => new ReportService(
            $c->get(Database::class),
            $c->get(ReportRepository::class),
            $c->get(PostRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(NotificationRepository::class),
            $c->get(UserRepository::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(DirectMessageService::class, fn (Container $c) => new DirectMessageService(
            $c->get(Database::class),
            $c->get(ConversationRepository::class),
            $c->get(DmMessageRepository::class),
            $c->get(UserRepository::class),
            $c->get(BlockRepository::class),
            $c->get(WriteGate::class),
            $c->get(Markdown::class),
            $c->get(NotificationService::class),
            $config,
        ));
        $c->bind(ReactionService::class, fn (Container $c) => new ReactionService(
            $c->get(Database::class),
            $c->get(ReactionRepository::class),
            $c->get(PostRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
            $c->get(NotificationService::class),
        ));

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
            $c->get(NotificationService::class),
        ));
        $c->bind(ModerationService::class, fn (Container $c) => new ModerationService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(PostingService::class),
            $c->get(WriteGate::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(BoardRepository::class),
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
        $r->get('/inbox', [InboxController::class, 'index']);
        $r->get('/search', [SearchController::class, 'index']);

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

        // Engagement (P2-01/P2-02): reactions + stars.
        $r->post('/posts/{id}/react', [EngagementController::class, 'react']);
        $r->post('/t/{id}/star', [EngagementController::class, 'star']);

        // Subscriptions + notifications (P2-03).
        $r->post('/t/{id}/subscribe', [SubscriptionController::class, 'subscribeThread']);
        $r->post('/b/{id}/subscribe', [SubscriptionController::class, 'subscribeBoard']);
        $r->get('/notifications', [NotificationController::class, 'index']);
        $r->get('/notifications/bell', [NotificationController::class, 'bell']);
        $r->post('/notifications/read-all', [NotificationController::class, 'readAll']);
        $r->post('/notifications/clear', [NotificationController::class, 'clear']);
        $r->post('/notifications/{id}/read', [NotificationController::class, 'read']);

        // Login-free one-click unsubscribe (P2-04).
        $r->get('/unsubscribe', [UnsubscribeController::class, 'show']);
        $r->post('/unsubscribe', [UnsubscribeController::class, 'confirm']);
        $r->post('/resubscribe', [UnsubscribeController::class, 'resubscribe']);

        // Direct messages (P2-07).
        $r->get('/messages', [ConversationController::class, 'index']);
        $r->get('/messages/new', [ConversationController::class, 'newForm']);
        $r->post('/messages', [ConversationController::class, 'create']);
        $r->get('/messages/{id}', [ConversationController::class, 'show']);
        $r->post('/messages/{id}', [ConversationController::class, 'reply']);
        $r->post('/dm/{id}/report', [ConversationController::class, 'report']);

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
        $r->post('/mod/t/{id}/move', [ModerationController::class, 'move']);
        $r->post('/mod/p/{id}/restore', [ModerationController::class, 'restorePost']);

        // Reports queue (P2-08).
        $r->post('/posts/{id}/report', [ReportController::class, 'report']);
        $r->get('/mod/reports', [ReportController::class, 'queue']);
        $r->post('/mod/reports/{id}/claim', [ReportController::class, 'claim']);
        $r->post('/mod/reports/{id}/resolve', [ReportController::class, 'resolve']);
        $r->post('/mod/reports/{id}/dismiss', [ReportController::class, 'dismiss']);

        // User moderation (P2-08).
        $r->post('/mod/u/{id}/warn', [UserModerationController::class, 'warn']);
        $r->post('/mod/u/{id}/note', [UserModerationController::class, 'note']);
        $r->post('/mod/u/{id}/suspend', [UserModerationController::class, 'suspend']);
        $r->post('/mod/u/{id}/ban', [UserModerationController::class, 'ban']);
        $r->post('/mod/u/{id}/lift', [UserModerationController::class, 'lift']);

        return $r;
    }
}
