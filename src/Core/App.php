<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\AccountController;
use App\Controller\AdminController;
use App\Controller\Api\BoardsController as ApiBoardsController;
use App\Controller\Api\MeController as ApiMeController;
use App\Controller\ApprovalController;
use App\Controller\AuthController;
use App\Controller\BlockController;
use App\Controller\BrandingController;
use App\Controller\ComposerController;
use App\Controller\CommunityMemoryController;
use App\Controller\BoardController;
use App\Controller\ConversationController;
use App\Controller\DraftController;
use App\Controller\EngagementController;
use App\Controller\FeedController;
use App\Controller\FollowController;
use App\Controller\HealthController;
use App\Controller\HomeController;
use App\Controller\InboxController;
use App\Controller\LeaderboardController;
use App\Controller\MediaController;
use App\Controller\ModerationController;
use App\Controller\OAuthController;
use App\Controller\PostController;
use App\Controller\PresenceController;
use App\Controller\ProfileController;
use App\Controller\ReportController;
use App\Controller\SearchController;
use App\Controller\SeoController;
use App\Controller\NotificationController;
use App\Controller\OnboardingController;
use App\Controller\SettingsController;
use App\Controller\SetupController;
use App\Controller\SolvedController;
use App\Controller\SubscriptionController;
use App\Controller\TagController;
use App\Controller\UserModerationController;
use App\Controller\ThreadController;
use App\Controller\ThreadWorkflowController;
use App\Controller\UnsubscribeController;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Mail\SendmailMailer;
use App\Search\MysqlSearchService;
use App\Search\SearchService;
use App\Repository\BadgeRepository;
use App\Repository\BlockRepository;
use App\Repository\ApiTokenRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\AttachmentRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\FollowRepository;
use App\Repository\IdempotencyRepository;
use App\Repository\MfaRepository;
use App\Repository\NotificationRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\ReportRepository;
use App\Repository\SessionRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadAssignmentRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UsernameHistoryRepository;
use App\Repository\VerificationRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\ClientIdentifier;
use App\Security\Csrf;
use App\Security\FileRateLimiter;
use App\Security\PasswordHasher;
use App\Security\RateLimiter;
use App\Security\SecurityHeaders;
use App\Security\SecretBox;
use App\Security\Session;
use App\Security\Totp;
use App\Security\WriteGate;
use App\Service\AccountService;
use App\Service\AdminService;
use App\Service\AntiAbuseService;
use App\Service\ApiTokenService;
use App\Service\AttachmentService;
use App\Service\AuthService;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use App\Service\BadgeService;
use App\Service\CommunityMemoryService;
use App\Service\DirectMessageService;
use App\Service\FeedService;
use App\Service\FollowService;
use App\Service\ModerationService;
use App\Service\MfaService;
use App\Service\NotificationService;
use App\Service\OAuthService;
use App\Service\OAuth\ProviderRegistry;
use App\Service\PostingService;
use App\Service\PreferenceService;
use App\Service\RateLimitService;
use App\Service\ReactionService;
use App\Service\RepairService;
use App\Service\ReportService;
use App\Service\ReputationLedgerService;
use App\Service\SolvedAnswerService;
use App\Service\TitleService;
use App\Service\ThreadWorkflowService;
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
        $this->heartbeat($container, $session->user());
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
            // and robots/sitemap should serve regardless of first-run state, so
            // these are dispatched BEFORE the setup gate (which queries the DB).
            if (in_array($path, ['/healthz', '/robots.txt', '/sitemap.xml'], true)) {
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

            // CSRF on every state-changing request, except the OAuth provider
            // callback — that POST originates cross-site from the provider and is
            // protected by the signed `state` cookie instead of a form token.
            $oauthCallback = preg_match('#^/auth/[^/]+/callback$#', $path) === 1;
            if ($request->isPost() && !$oauthCallback
                && !$container->get(Csrf::class)->verify((string) ($request->post('_token') ?? ''))) {
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

    /**
     * Presence heartbeat (P2-11): refresh users.last_seen_at at most once per
     * heartbeat window for a signed-in user. Best-effort — never breaks a request.
     */
    private function heartbeat(Container $container, ?\App\Domain\User $user): void
    {
        if ($user === null) {
            return;
        }
        try {
            if (!$container->get(FeatureFlags::class)->enabled('presence')) {
                return;
            }
            $lastSeen = $user->toArray()['last_seen_at'] ?? null;
            $window = (int) $this->config->get('presence.heartbeat_seconds', 60);
            $stale = true;
            if (is_string($lastSeen) && $lastSeen !== '') {
                $ts = strtotime($lastSeen . ' UTC');
                $stale = $ts === false || $ts <= time() - $window;
            }
            if ($stale) {
                $container->get(UserRepository::class)->updateLastSeen($user->id());
            }
        } catch (Throwable) {
            // Presence is non-essential; swallow errors (e.g. pre-migration DB).
        }
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

        // Appearance prefs stamp the document root (no theme flash; no-JS themes).
        // Guests use the site default; a signed-in user's resolved prefs win.
        $appearance = ['theme' => 'system', 'density' => 'comfortable', 'font_size' => 'medium', 'reduced_motion' => false];
        try {
            $user = $session->user();
            if ($user !== null) {
                $appearance = $container->get(PreferenceService::class)->appearance($user->id());
            } else {
                $default = $container->get(SettingRepository::class)->getString('brand_theme_default', 'system');
                if (in_array($default, ['system', 'light', 'dark'], true)) {
                    $appearance['theme'] = $default;
                }
            }
        } catch (Throwable) {
            // Pre-migration / DB-less render: keep safe defaults.
        }

        // Composing prefs (P3-01) gate the shared composer client-side
        // (enter-to-send, live preview, smart list continuation). Stamped on
        // <body> for signed-in users; safe defaults if pre-migration or DB-less.
        $composing = ['enter_to_send' => false, 'show_preview' => true, 'smart_lists' => true];
        try {
            $user = $session->user();
            if ($user !== null) {
                $composing = $container->get(PreferenceService::class)->composing($user->id());
            }
        } catch (Throwable) {
            // keep safe defaults
        }

        // Branding (P3-07): operator name/logo/favicon/colors with safe fallbacks.
        $branding = $this->branding($container, $siteName);

        // Configured OAuth providers power the "Sign in with …" buttons.
        $oauthProviders = [];
        try {
            if (!empty($features['oauth'])) {
                $oauthProviders = $container->get(ProviderRegistry::class)->configuredNames();
            }
        } catch (Throwable) {
            $oauthProviders = [];
        }

        // Product tour (P3-11): a signed-in user who hasn't completed it yet gets
        // the tour on enhanced pages. Never throws if the column is pre-migration.
        $needsTour = false;
        try {
            $user = $session->user();
            if ($user !== null && !empty($features['product_tour'])) {
                $needsTour = ($user->toArray()['onboarded_at'] ?? null) === null;
            }
        } catch (Throwable) {
            $needsTour = false;
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
            'oauth_providers' => $oauthProviders,
            'appearance' => $appearance,
            'composing' => $composing,
            'branding' => $branding,
            'app_url' => (string) $this->config->get('app.url', ''),
            'needs_tour' => $needsTour,
        ]);
    }

    /**
     * Operator branding (P3-07): site name + optional logo/favicon/colors with
     * safe built-in fallbacks. Never throws — a missing setting or table yields
     * the default RetroBoards chrome so the shell always renders.
     *
     * @return array{name:string,logo_path:?string,favicon_path:?string,color_primary:string,color_accent:string}
     */
    private function branding(Container $container, string $siteName): array
    {
        $brand = [
            'name' => $siteName,
            'logo_path' => null,
            'favicon_path' => null,
            'color_primary' => '',
            'color_accent' => '',
            'has_custom_colors' => false,
            'version' => '',
        ];
        try {
            if (!$container->get(FeatureFlags::class)->enabled('branding')) {
                return $brand;
            }
            $settings = $container->get(SettingRepository::class);
            $logo = $settings->getString('brand_logo_path', '');
            $favicon = $settings->getString('brand_favicon_path', '');
            $primary = $settings->getString('brand_color_primary', '');
            $accent = $settings->getString('brand_color_accent', '');
            $brand['version'] = $settings->getString('brand_version', '');
            if ($logo !== '') {
                $brand['logo_path'] = $logo;
            }
            if ($favicon !== '') {
                $brand['favicon_path'] = $favicon;
            }
            if (self::isHexColor($primary)) {
                $brand['color_primary'] = $primary;
            }
            if (self::isHexColor($accent)) {
                $brand['color_accent'] = $accent;
            }
            $brand['has_custom_colors'] = $brand['color_primary'] !== '' || $brand['color_accent'] !== '';
        } catch (Throwable) {
            // Keep safe defaults.
        }
        return $brand;
    }

    private static function isHexColor(string $value): bool
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1;
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
        $mutedBoardIds = [];
        if ($user !== null) {
            $memberBoardIds = array_flip($container->get(BoardMemberRepository::class)->boardIdsFor($user->id()));
            // Muted boards are excluded from the sidebar (USER §4.3).
            $mutedBoardIds = array_flip($container->get(UserBoardPrefRepository::class)->mutedBoardIds($user->id()));
        }

        $nav = [];
        foreach ($categories as $category) {
            $boards = array_values(array_filter(
                $allBoards,
                fn (array $b): bool => (int) $b['category_id'] === (int) $category['id']
                    && !isset($mutedBoardIds[(int) $b['id']])
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
        $c->bind(SecretBox::class, fn () => new SecretBox((string) $config->get('app.key', '')));
        $c->bind(Totp::class, fn () => new Totp());
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
        $c->bind(ApiTokenRepository::class, fn (Container $c) => new ApiTokenRepository($c->get(Database::class)));
        $c->bind(ApiTokenService::class, fn (Container $c) => new ApiTokenService(
            $c->get(Database::class),
            $c->get(ApiTokenRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
            $c->get(PasswordHasher::class),
            $c->get(WriteGate::class),
        ));
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
        $c->bind(ThreadAssignmentRepository::class, fn (Container $c) => new ThreadAssignmentRepository($c->get(Database::class)));
        $c->bind(FollowRepository::class, fn (Container $c) => new FollowRepository($c->get(Database::class)));
        $c->bind(TagRepository::class, fn (Container $c) => new TagRepository($c->get(Database::class)));
        $c->bind(BadgeRepository::class, fn (Container $c) => new BadgeRepository($c->get(Database::class)));
        $c->bind(OAuthIdentityRepository::class, fn (Container $c) => new OAuthIdentityRepository($c->get(Database::class)));
        $c->bind(UserPreferenceRepository::class, fn (Container $c) => new UserPreferenceRepository($c->get(Database::class)));
        $c->bind(UserBoardPrefRepository::class, fn (Container $c) => new UserBoardPrefRepository($c->get(Database::class)));
        $c->bind(UsernameHistoryRepository::class, fn (Container $c) => new UsernameHistoryRepository($c->get(Database::class)));
        $c->bind(VerificationRepository::class, fn (Container $c) => new VerificationRepository($c->get(Database::class)));
        $c->bind(IdempotencyRepository::class, fn (Container $c) => new IdempotencyRepository($c->get(Database::class)));
        $c->bind(AttachmentRepository::class, fn (Container $c) => new AttachmentRepository($c->get(Database::class)));
        $c->bind(MfaRepository::class, fn (Container $c) => new MfaRepository($c->get(Database::class)));

        // Phase 3 anti-abuse + rate limiting (P3-05).
        $c->bind(ClientIdentifier::class, fn () => new ClientIdentifier((array) $config->get('trusted_proxies', [])));
        $c->bind(RateLimitService::class, fn (Container $c) => new RateLimitService(
            $c->get(RateLimiter::class),
            $config,
            $c->get(ClientIdentifier::class),
        ));
        // Spam-scoring provider seam (P3-05): the default scorer abstains. A
        // first-party/external provider (Gate B) is enabled by rebinding this.
        $c->bind(\App\Service\Spam\SpamScorer::class, fn (Container $c) => new \App\Service\Spam\NullSpamScorer());
        $c->bind(AntiAbuseService::class, fn (Container $c) => new AntiAbuseService(
            $c->get(Database::class),
            $config,
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(\App\Service\Spam\SpamScorer::class),
        ));

        // Image uploads (P3-04).
        $c->bind(AttachmentService::class, fn (Container $c) => new AttachmentService(
            $c->get(AttachmentRepository::class),
            (string) $config->get('uploads.storage_path'),
            (int) $config->get('uploads.max_bytes', 5_242_880),
            (int) $config->get('uploads.max_width', 4096),
            (int) $config->get('uploads.max_height', 4096),
            (int) $config->get('uploads.max_pixels', 24_000_000),
            (array) $config->get('uploads.allowed_mime', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']),
            (int) $config->get('uploads.min_free_bytes', 0),
        ));

        // Phase 2 shared services.
        $c->bind(FeatureFlags::class, fn (Container $c) => new FeatureFlags($c->get(SettingRepository::class)));
        $c->bind(RepairService::class, fn (Container $c) => new RepairService(
            $c->get(Database::class),
            (int) $config->get('community.solved_bonus', 5),
        ));
        $c->bind(SearchService::class, fn (Container $c) => new MysqlSearchService($c->get(Database::class)));
        $c->bind(Mailer::class, function (Container $c) use ($config): Mailer {
            $mail = (array) $config->get('mail', []);
            if (($mail['driver'] ?? 'sendmail') === 'array') {
                return new ArrayMailer();
            }
            $fromName = (string) ($mail['from_name'] ?? '');
            if ($fromName === '') {
                try {
                    $fromName = $c->get(SettingRepository::class)->getString('site_name', (string) $config->get('app.name', ''));
                } catch (Throwable) {
                    $fromName = (string) $config->get('app.name', '');
                }
            }
            return new SendmailMailer((string) ($mail['from'] ?? ''), $fromName);
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
            $c->get(FeatureFlags::class)->enabled('uploads') ? $c->get(AttachmentRepository::class) : null,
        ));
        $c->bind(ReactionService::class, fn (Container $c) => new ReactionService(
            $c->get(Database::class),
            $c->get(ReactionRepository::class),
            $c->get(PostRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
            $c->get(NotificationService::class),
            $c->get(ReputationLedgerService::class),
        ));
        $c->bind(ReputationLedgerService::class, fn (Container $c) => new ReputationLedgerService(
            $c->get(Database::class),
            $c->get(UserRepository::class),
        ));

        // Community identity (P2-09).
        $c->bind(TitleService::class, fn () => new TitleService(
            (array) $config->get('community.title_thresholds', []),
        ));
        $c->bind(BadgeService::class, fn (Container $c) => new BadgeService(
            $c->get(Database::class),
            $c->get(BadgeRepository::class),
            $c->get(UserRepository::class),
            $c->get(NotificationService::class),
            (int) $config->get('community.badge_conversation_starter_threads', 10),
            (int) $config->get('community.badge_trusted_answerer_solved', 10),
            (int) $config->get('community.badge_appreciated_rep', 100),
            (int) $config->get('community.badge_well_liked_rep', 1000),
        ));
        $c->bind(FollowService::class, fn (Container $c) => new FollowService(
            $c->get(FollowRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardRepository::class),
            $c->get(TagRepository::class),
            $c->get(BlockRepository::class),
            $c->get(WriteGate::class),
            $c->get(NotificationService::class),
        ));
        $c->bind(FeedService::class, fn (Container $c) => new FeedService(
            $c->get(Database::class),
            $c->get(FollowRepository::class),
            $c->get(BlockRepository::class),
            $c->get(BoardMemberRepository::class),
        ));
        $c->bind(SolvedAnswerService::class, fn (Container $c) => new SolvedAnswerService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(BadgeService::class),
            $c->get(NotificationService::class),
            $c->get(WriteGate::class),
            $c->get(ThreadWorkflowService::class),
            $c->get(ReputationLedgerService::class),
            (int) $config->get('community.solved_bonus', 5),
        ));
        $c->bind(ThreadWorkflowService::class, fn (Container $c) => new ThreadWorkflowService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(ThreadAssignmentRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(CommunityMemoryService::class, fn (Container $c) => new CommunityMemoryService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
            $c->get(Markdown::class),
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
        $c->bind(PasswordResetService::class, fn (Container $c) => new PasswordResetService(
            $c->get(UserRepository::class),
            $c->get(VerificationRepository::class),
            $c->get(SessionRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(Mailer::class),
            $config,
            $c->get(SettingRepository::class),
        ));
        $c->bind(EmailVerificationService::class, fn (Container $c) => new EmailVerificationService(
            $c->get(UserRepository::class),
            $c->get(VerificationRepository::class),
            $c->get(BadgeService::class),
            $c->get(Mailer::class),
            $config,
            $c->get(SettingRepository::class),
        ));
        $c->bind(AccountService::class, fn (Container $c) => new AccountService(
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(WriteGate::class),
            $config,
            $c->get(UserPreferenceRepository::class),
        ));
        $c->bind(MfaService::class, fn (Container $c) => new MfaService(
            $c->get(MfaRepository::class),
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(SecretBox::class),
            $c->get(Totp::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $config,
        ));
        $c->bind(PreferenceService::class, fn (Container $c) => new PreferenceService(
            $c->get(UserPreferenceRepository::class),
            (int) $config->get('pagination.threads_per_page', 20),
            (int) $config->get('pagination.posts_per_page', 20),
        ));
        $c->bind(ProviderRegistry::class, fn () => new ProviderRegistry((array) $config->get('oauth', [])));
        $c->bind(OAuthService::class, fn (Container $c) => new OAuthService(
            $c->get(Database::class),
            $c->get(OAuthIdentityRepository::class),
            $c->get(UserRepository::class),
            $c->get(SettingRepository::class),
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
            $c->get(FeatureFlags::class)->enabled('anti_abuse') ? $c->get(AntiAbuseService::class) : null,
            $c->get(IdempotencyRepository::class),
            $c->get(FeatureFlags::class)->enabled('uploads') ? $c->get(AttachmentRepository::class) : null,
            $c->get(ReputationLedgerService::class),
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
            $c->get(UserRepository::class),
        ));
        $c->bind(AdminService::class, fn (Container $c) => new AdminService(
            $c->get(Database::class),
            $c->get(CategoryRepository::class),
            $c->get(BoardRepository::class),
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(UserRepository::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(BoardMemberRepository::class),
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
        $r->get('/sitemap.xml', [SeoController::class, 'sitemap']);
        $r->get('/robots.txt', [SeoController::class, 'robots']);
        $r->get('/inbox', [InboxController::class, 'index']);
        $r->get('/search', [SearchController::class, 'index']);
        $r->get('/presence', [PresenceController::class, 'index']);

        // Read-only Bearer API (B2 sub-project 2); GET-only so the CSRF/HTML
        // kernel is bypassed — ApiController self-authenticates and emits JSON.
        $r->get('/api/v1/me', [ApiMeController::class, 'show']);
        $r->get('/api/v1/boards', [ApiBoardsController::class, 'index']);
        $r->get('/api/v1/boards/{id}/threads', [ApiBoardsController::class, 'threads']);

        $r->get('/c/{slug}', [BoardController::class, 'show']);
        $r->get('/t/{id}-{slug}', [ThreadController::class, 'show']);
        $r->get('/t/{id}', [ThreadController::class, 'show']);
        $r->get('/u/{username}', [ProfileController::class, 'show']);
        $r->get('/u/{username}/followers', [ProfileController::class, 'followers']);
        $r->get('/u/{username}/following', [ProfileController::class, 'following']);

        // Community identity (P2-09): follows, feed, leaderboard, solved answers.
        $r->get('/feed', [FeedController::class, 'index']);
        $r->get('/leaderboard', [LeaderboardController::class, 'index']);
        $r->get('/tags', [TagController::class, 'index']);
        $r->get('/tags/{slug}', [TagController::class, 'show']);
        $r->post('/tags/{slug}/follow', [TagController::class, 'follow']);

        // Image uploads + authorization-gated delivery (P3-04).
        $r->post('/upload', [MediaController::class, 'upload']);
        $r->get('/media/{id}', [MediaController::class, 'show']);

        // Shared composer live preview (P3-02) — same render+sanitize pipeline.
        $r->post('/composer/preview', [ComposerController::class, 'preview']);
        $r->get('/drafts', [DraftController::class, 'index']);
        $r->post('/u/{username}/follow', [FollowController::class, 'toggle']);
        $r->post('/u/{username}/followers/{id}/remove', [FollowController::class, 'removeFollower']);
        $r->post('/b/{id}/follow', [FollowController::class, 'toggleBoard']);
        $r->post('/u/{username}/block', [BlockController::class, 'toggle']);
        $r->post('/posts/{id}/accept', [SolvedController::class, 'accept']);
        $r->post('/t/{id}/unaccept', [SolvedController::class, 'unaccept']);
        $r->post('/t/{id}/status', [ThreadWorkflowController::class, 'status']);
        $r->post('/t/{id}/snooze', [ThreadWorkflowController::class, 'snooze']);
        $r->post('/t/{id}/assign', [ThreadWorkflowController::class, 'assign']);
        $r->post('/t/{id}/tags', [TagController::class, 'updateThread']);
        $r->post('/t/{id}/summary', [CommunityMemoryController::class, 'summary']);
        $r->post('/t/{id}/summary/retire', [CommunityMemoryController::class, 'retireSummary']);
        $r->post('/t/{id}/summary/restore', [CommunityMemoryController::class, 'republishSummary']);
        $r->post('/t/{id}/related', [CommunityMemoryController::class, 'related']);

        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->post('/login/mfa', [AuthController::class, 'completeMfa']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->post('/logout', [AuthController::class, 'logout']);

        // Forgotten-password recovery (P2 Gate A "account recovery").
        $r->get('/forgot', [AuthController::class, 'showForgot']);
        $r->post('/forgot', [AuthController::class, 'forgot']);
        $r->get('/reset', [AuthController::class, 'showReset']);
        $r->post('/reset', [AuthController::class, 'reset']);

        // Registration email verification (P2 Gate A "email-verification").
        $r->get('/verify', [AuthController::class, 'verifyEmail']);
        $r->post('/verify/resend', [AuthController::class, 'resendVerification']);

        // OAuth sign-in / account linking (P2-10). The callback is state-cookie
        // protected (not _token), so it is exempt from the CSRF gate (see process()).
        $r->get('/auth/{provider}/redirect', [OAuthController::class, 'start']);
        $r->get('/auth/{provider}/callback', [OAuthController::class, 'callback']);
        $r->post('/auth/{provider}/callback', [OAuthController::class, 'callback']);
        $r->get('/settings/connections', [OAuthController::class, 'connections']);
        $r->post('/settings/connections/unlink', [OAuthController::class, 'unlink']);
        $r->post('/settings/connections/set-password', [OAuthController::class, 'setPassword']);

        // Onboarding product tour (P3-11): record completion / request a replay.
        $r->post('/onboarding/complete', [OnboardingController::class, 'complete']);
        $r->post('/onboarding/replay', [OnboardingController::class, 'replay']);

        $r->get('/settings', [AccountController::class, 'index']);
        $r->get('/settings/account', [AccountController::class, 'accountForm']);
        $r->post('/settings/account', [AccountController::class, 'updateAccount']);
        $r->get('/settings/security', [AccountController::class, 'securityForm']);
        $r->post('/settings/security', [AccountController::class, 'updateSecurity']);
        $r->post('/settings/security/totp/enroll', [AccountController::class, 'startTotpEnrollment']);
        $r->post('/settings/security/totp/confirm', [AccountController::class, 'confirmTotpEnrollment']);
        $r->post('/settings/security/totp/recovery/rotate', [AccountController::class, 'rotateRecoveryCodes']);
        $r->post('/settings/security/totp/disable', [AccountController::class, 'disableTotp']);

        // Member controls (P2-10): privacy, preferences, notifications, sessions, blocks, boards.
        $r->get('/settings/privacy', [SettingsController::class, 'privacyForm']);
        $r->post('/settings/privacy', [SettingsController::class, 'updatePrivacy']);
        $r->get('/settings/appearance', [SettingsController::class, 'appearanceForm']);
        $r->post('/settings/appearance', [SettingsController::class, 'updateAppearance']);
        $r->get('/settings/preferences', [SettingsController::class, 'preferencesForm']);
        $r->post('/settings/preferences', [SettingsController::class, 'updatePreferences']);
        $r->get('/settings/composing', [SettingsController::class, 'composingForm']);
        $r->post('/settings/composing', [SettingsController::class, 'updateComposing']);
        $r->post('/settings/preferences/reset', [SettingsController::class, 'resetPreferences']);
        $r->get('/settings/preferences/export', [SettingsController::class, 'exportPreferences']);
        $r->get('/settings/notifications', [SettingsController::class, 'notificationsForm']);
        $r->post('/settings/notifications', [SettingsController::class, 'updateNotifications']);
        $r->get('/settings/sessions', [SettingsController::class, 'sessions']);
        $r->post('/settings/sessions/revoke', [SettingsController::class, 'revokeSession']);
        $r->post('/settings/sessions/revoke-others', [SettingsController::class, 'revokeOtherSessions']);
        $r->get('/settings/blocks', [BlockController::class, 'index']);
        $r->get('/settings/boards', [SettingsController::class, 'boards']);
        $r->post('/settings/boards/toggle', [SettingsController::class, 'toggleBoardPref']);

        $r->get('/setup', [SetupController::class, 'show']);
        $r->post('/setup', [SetupController::class, 'submit']);

        // Operator branding (P3-07): dynamic brand stylesheet + admin controls.
        $r->get('/brand.css', [BrandingController::class, 'css']);
        $r->get('/admin/branding', [BrandingController::class, 'form']);
        $r->post('/admin/branding', [BrandingController::class, 'update']);
        $r->get('/admin/tags', [TagController::class, 'admin']);
        $r->post('/admin/tags', [TagController::class, 'create']);
        $r->post('/admin/tags/{id}', [TagController::class, 'update']);
        $r->post('/admin/tags/{id}/merge', [TagController::class, 'merge']);

        $r->post('/threads', [PostController::class, 'createThread']);
        $r->post('/t/{id}/reply', [PostController::class, 'reply']);
        $r->post('/posts/{id}/edit', [PostController::class, 'edit']);
        $r->post('/posts/{id}/delete', [PostController::class, 'delete']);
        $r->post('/posts/{id}/wiki', [CommunityMemoryController::class, 'makeWiki']);
        $r->post('/posts/{id}/wiki/edit', [CommunityMemoryController::class, 'editWiki']);
        $r->post('/posts/{id}/wiki/revert', [CommunityMemoryController::class, 'revertWiki']);

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
        $r->post('/messages/{id}/members', [ConversationController::class, 'addMember']);
        $r->post('/messages/{id}/members/remove', [ConversationController::class, 'removeMember']);
        $r->post('/messages/{id}/rename', [ConversationController::class, 'rename']);
        $r->post('/messages/{id}/mute', [ConversationController::class, 'mute']);
        $r->post('/messages/{id}/transfer', [ConversationController::class, 'transfer']);
        $r->post('/dm/{id}/report', [ConversationController::class, 'report']);

        $r->get('/admin', [AdminController::class, 'dashboard']);
        $r->get('/admin/structure', [AdminController::class, 'structure']);
        $r->post('/admin/site', [AdminController::class, 'updateSite']);
        $r->post('/admin/settings', [AdminController::class, 'updateSettings']);
        $r->post('/admin/categories', [AdminController::class, 'createCategory']);
        $r->post('/admin/categories/{id}', [AdminController::class, 'updateCategory']);
        $r->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
        $r->get('/admin/boards/{id}/edit', [AdminController::class, 'editBoard']);
        $r->post('/admin/boards', [AdminController::class, 'createBoard']);
        $r->post('/admin/boards/{id}', [AdminController::class, 'updateBoard']);
        $r->post('/admin/boards/{id}/delete', [AdminController::class, 'deleteBoard']);

        // Board roster management (P2-08): assign/remove scoped moderators + members.
        $r->post('/admin/boards/{id}/moderators', [AdminController::class, 'assignModerator']);
        $r->post('/admin/boards/{id}/moderators/remove', [AdminController::class, 'unassignModerator']);
        $r->post('/admin/boards/{id}/members', [AdminController::class, 'addMember']);
        $r->post('/admin/boards/{id}/members/remove', [AdminController::class, 'removeMember']);

        $r->post('/mod/t/{id}/pin', [ModerationController::class, 'pin']);
        $r->post('/mod/t/{id}/lock', [ModerationController::class, 'lock']);
        $r->post('/mod/t/{id}/move', [ModerationController::class, 'move']);
        $r->post('/mod/p/{id}/restore', [ModerationController::class, 'restorePost']);
        $r->post('/mod/p/{id}/reveal', [ModerationController::class, 'reveal']);

        // Approval queue (P3-05): release/reject content held by anti-abuse or board approval.
        $r->get('/mod/approvals', [ApprovalController::class, 'queue']);
        $r->post('/mod/approvals/thread/{id}/approve', [ApprovalController::class, 'approveThread']);
        $r->post('/mod/approvals/thread/{id}/reject', [ApprovalController::class, 'rejectThread']);
        $r->post('/mod/approvals/post/{id}/approve', [ApprovalController::class, 'approvePost']);
        $r->post('/mod/approvals/post/{id}/reject', [ApprovalController::class, 'rejectPost']);

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
