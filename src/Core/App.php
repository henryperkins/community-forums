<?php

declare(strict_types=1);

namespace App\Core;

use App\Controller\AccountController;
use App\Controller\AdminAnnouncementController;
use App\Controller\AdminApiTokenController;
use App\Controller\AdminInvitationController;
use App\Controller\AdminBadgeRuleController;
use App\Controller\AdminController;
use App\Controller\AdminCustomEmojiController;
use App\Controller\AdminEmailController;
use App\Controller\AdminExtensionController;
use App\Controller\AdminFeatureController;
use App\Controller\AdminLinkPreviewController;
use App\Controller\AdminPackageIntegrationController;
use App\Controller\AdminPackageLifecycleController;
use App\Controller\AdminPackageSecurityController;
use App\Controller\AdminPackagesController;
use App\Controller\AdminRegistryController;
use App\Controller\AdminProviderController;
use App\Controller\AdminRoleController;
use App\Controller\AdminThemeController;
use App\Controller\AdminThreadIntelligenceController;
use App\Controller\AdminUserController;
use App\Controller\AdminWebhookController;
use App\Controller\Api\BoardsController as ApiBoardsController;
use App\Controller\Api\MeController as ApiMeController;
use App\Controller\ApprovalController;
use App\Controller\AppealController;
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
use App\Controller\PasskeyController;
use App\Controller\PostController;
use App\Controller\PollController;
use App\Controller\PresenceController;
use App\Controller\PersonalOrganizationController;
use App\Controller\ProfileController;
use App\Controller\ReportController;
use App\Controller\SearchController;
use App\Controller\SeoController;
use App\Controller\SlashGiphyController;
use App\Controller\NotificationController;
use App\Controller\OnboardingController;
use App\Controller\SettingsController;
use App\Controller\SetupController;
use App\Controller\SolvedController;
use App\Controller\SubscriptionController;
use App\Controller\TagController;
use App\Controller\ThemeController;
use App\Controller\UserModerationController;
use App\Controller\ThreadController;
use App\Controller\ThreadWorkflowController;
use App\Controller\UnsubscribeController;
use App\Hook\FirstPartyHookRegistry;
use App\Hook\HookEvent;
use App\Mail\ArrayMailer;
use App\Mail\Mailer;
use App\Mail\SendmailMailer;
use App\Search\MysqlSearchService;
use App\Search\SearchService;
use App\Repository\BadgeRepository;
use App\Repository\BlockRepository;
use App\Repository\AccountDeletionRepository;
use App\Repository\ApiTokenRepository;
use App\Repository\BoardMemberRepository;
use App\Repository\BoardModeratorRepository;
use App\Repository\BoardRepository;
use App\Repository\CapabilityRepository;
use App\Repository\CategoryRepository;
use App\Repository\IdentityProviderRepository;
use App\Repository\InvitationRepository;
use App\Repository\ModerationLogRepository;
use App\Repository\ModerationAppealRepository;
use App\Repository\ConversationRepository;
use App\Repository\DmMessageRepository;
use App\Repository\AttachmentRepository;
use App\Repository\EmailDomainStatusRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\FollowRepository;
use App\Repository\IdempotencyRepository;
use App\Repository\InstalledPackageCredentialRepository;
use App\Repository\InstalledPackagePermissionRepository;
use App\Repository\InstalledPackageRepository;
use App\Repository\InstalledPackageSettingsRepository;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\MfaRepository;
use App\Repository\NotificationRepository;
use App\Repository\OAuthIdentityRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageHistoryRepository;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\PackageReleaseRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PackageRepository;
use App\Repository\PackageThemeRepository;
use App\Repository\PackageTransparencyLogRepository;
use App\Repository\PostRepository;
use App\Repository\ReactionRepository;
use App\Repository\ReportRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Service\Registry\PublisherTrustService;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Repository\RoleAssignmentHistoryRepository;
use App\Repository\RoleAssignmentRepository;
use App\Repository\RoleCapabilityRepository;
use App\Repository\RoleRepository;
use App\Repository\SessionRepository;
use App\Repository\ServiceSecretRepository;
use App\Repository\ServerDraftRepository;
use App\Repository\ServerExtensionRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\TagRepository;
use App\Repository\ThreadRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\ThreadIntelligenceJobRepository;
use App\Repository\ThreadAssignmentRepository;
use App\Repository\ThreadUserRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserProfileFieldRepository;
use App\Repository\UsernameHistoryRepository;
use App\Repository\VerificationRepository;
use App\Repository\ProtectedOwnerRepository;
use App\Repository\UserRepository;
use App\Repository\WebAuthnChallengeRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Repository\WebhookDeliveryRepository;
use App\Repository\WebhookRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\ClientIdentifier;
use App\Security\CapabilityResolver;
use App\Security\Csrf;
use App\Security\EgressGuard;
use App\Security\FileRateLimiter;
use App\Security\LastOwnerGuard;
use App\Security\PasswordHasher;
use App\Security\ReauthGate;
use App\Security\RegistrationPolicy;
use App\Security\RateLimiter;
use App\Security\Registry\TrustChainVerifier;
use App\Security\Packages\ManifestValidator;
use App\Security\Packages\PackageSecurityGate;
use App\Security\SecurityHeaders;
use App\Security\SecretBox;
use App\Security\Session;
use App\Security\Totp;
use App\Security\WebhookEvents;
use App\Security\WebAuthn\RelyingParty;
use App\Security\WebAuthn\WebAuthnVerifier;
use App\Security\WriteGate;
use App\Service\AccountService;
use App\Service\AccountLifecycleService;
use App\Service\AdminDashboardService;
use App\Service\AdminService;
use App\Service\AnnouncementService;
use App\Service\AntiAbuseService;
use App\Service\AppealService;
use App\Service\ApiTokenService;
use App\Service\AttachmentService;
use App\Service\AttachmentScanService;
use App\Service\AuthService;
use App\Service\ContentReferenceService;
use App\Service\CustomEmojiService;
use App\Service\EmailDomainVerifier;
use App\Service\EmailPreferenceService;
use App\Service\EmailOpsService;
use App\Service\Extension\BubblewrapSandboxAdapter;
use App\Service\Extension\ExtensionSandbox;
use App\Service\EmailVerificationService;
use App\Service\PasswordResetService;
use App\Service\BadgeRuleService;
use App\Service\BadgeService;
use App\Service\CommunityMemoryService;
use App\Service\ComposerSuggestionService;
use App\Service\DirectMessageService;
use App\Service\FeedService;
use App\Service\FollowService;
use App\Service\LegacyAuthorityProjection;
use App\Service\LinkPreviewService;
use App\Service\IdentityProviderService;
use App\Service\InvitationService;
use App\Service\ModerationService;
use App\Service\MfaService;
use App\Service\NotificationService;
use App\Service\OAuthService;
use App\Service\PasskeyService;
use App\Service\OAuth\HttpClient as OAuthHttpClient;
use App\Service\OAuth\ProviderRegistry;
use App\Service\OAuth\Oidc\ClaimMapper;
use App\Service\OAuth\Oidc\JwksCache;
use App\Service\OAuth\Oidc\JwtVerifier;
use App\Service\OAuth\Oidc\OidcDiscovery;
use App\Service\OAuth\Oidc\OidcProvider;
use App\Service\Packages\PackageAcquisitionService;
use App\Service\Packages\PackageCredentialAuthGuard;
use App\Service\Packages\PackageIntegrationService;
use App\Service\Packages\PackageReviewConsoleService;
use App\Service\Packages\PackageSecurityResponseService;
use App\Service\Packages\PackageSettingsService;
use App\Service\Packages\PackageArtifactStore;
use App\Service\Packages\PackageHealthService;
use App\Service\Packages\PackageLifecycleService;
use App\Service\Packages\PackageUpdateService;
use App\Service\Packages\ThemeAssetScanner;
use App\Service\Packages\ThemeBuildService;
use App\Service\Packages\ThemeStateService;
use App\Service\PostingService;
use App\Service\PollService;
use App\Service\PersonalOrganizationService;
use App\Service\PermissionSimulatorService;
use App\Service\PreferenceService;
use App\Service\ProfileMediaService;
use App\Service\RateLimitService;
use App\Service\ReactionService;
use App\Service\RepairService;
use App\Service\ReportService;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\CurlRegistryTransport;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistryCatalogService;
use App\Service\Registry\RegistrySnapshotService;
use App\Service\Registry\RegistryTransport;
use App\Service\Registry\RegistryTrustService;
use App\Service\ResolverShadow;
use App\Service\ReputationLedgerService;
use App\Service\RoleAssignmentService;
use App\Service\RoleService;
use App\Service\SecretVault;
use App\Service\SinceLastReadContextService;
use App\Service\SolvedAnswerService;
use App\Service\TitleService;
use App\Service\ThreadSplitMergeService;
use App\Service\ThreadWorkflowService;
use App\Service\ThreadIntelligence\CurlOpenAiTransport;
use App\Service\ThreadIntelligence\OpenAiThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\OpenAiThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\OpenAiTransport;
use App\Service\ThreadIntelligence\ThreadIntelligenceAdminService;
use App\Service\ThreadIntelligence\ThreadIntelligenceBoardSweep;
use App\Service\ThreadIntelligence\ThreadIntelligenceBudget;
use App\Service\ThreadIntelligence\ThreadIntelligenceCandidateFinder;
use App\Service\ThreadIntelligence\ThreadIntelligenceConfig;
use App\Service\ThreadIntelligence\ThreadIntelligenceEligibility;
use App\Service\ThreadIntelligence\ThreadIntelligenceEvidenceBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceOperationsService;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\ThreadIntelligenceOutputValidator;
use App\Service\ThreadIntelligence\ThreadIntelligencePromptBuilder;
use App\Service\ThreadIntelligence\ThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligencePublisher;
use App\Service\ThreadIntelligence\ThreadIntelligenceQueue;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceViewService;
use App\Service\UserModerationService;
use App\Service\SetupService;
use App\Service\Webhook\CurlWebhookTransport;
use App\Service\Webhook\WebhookTransport;
use App\Service\WebhookService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;
use App\Support\MentionLinker;
use App\Worker\ThreadIntelligenceWorker;
use Throwable;

/**
 * The HTTP kernel. Boots configuration and the service container, runs the
 * request pipeline (session → setup gate → CSRF → route dispatch), and applies
 * baseline security headers to every response. handle() is pure
 * (Request → Response) so the whole stack is testable in-process.
 */
final class App
{
    /**
     * Core compatibility version (Foundation F2). Package releases declare
     * `core_min`/`core_max` (0049 package_releases) against this identity;
     * `App\Support\CoreVersion::satisfies()` is the comparison the Inc-2
     * compatibility resolver builds on. `-dev` orders BEFORE the bare release
     * (version_compare), so a dev core fails closed for packages that require
     * the released core. Bump on release; distinct from the cosmetic
     * `brand_version` setting (cache-busting only).
     */
    public const CORE_VERSION = '0.5.0-dev';

    private Router $router;

    public function __construct(
        private Config $config,
        private ?Database $database = null,
        private ?RateLimiter $rateLimiter = null,
        private ?OAuthHttpClient $oauthHttpClient = null,
        private ?ThreadIntelligenceProvider $threadIntelligenceProvider = null,
        private ?ThreadIntelligenceOutputModerator $threadIntelligenceOutputModerator = null,
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
        $container->get(Telemetry::class)->emit('http.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->status(),
        ]);

        SecurityHeaders::apply(
            $response,
            (bool) $this->config->get('security.hsts', true),
            $this->allowGiphyCsp($container),
        );
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

    private function allowGiphyCsp(Container $container): bool
    {
        try {
            if (!$container->get(FeatureFlags::class)->enabled('slash_giphy')) {
                return false;
            }
            $key = $container->get(SettingRepository::class)->getString(
                'giphy_public_key',
                (string) $this->config->get('giphy.public_key', ''),
            );
            return $key !== '';
        } catch (Throwable) {
            return false;
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
        $composing = ['enter_to_send' => true, 'show_preview' => true, 'smart_lists' => true];
        try {
            $user = $session->user();
            if ($user !== null) {
                $composing = $container->get(PreferenceService::class)->composing($user->id());
            }
        } catch (Throwable) {
            // keep safe defaults
        }

        // Branding (P3-07): operator name/logo/favicon/colors with safe fallbacks.
        $branding = $this->branding($container, $siteName, $appearance);

        // Declarative package themes (P5-03): external CSS only, with emergency
        // safe mode and admin-only preview kept outside the DB-backed session.
        $packageTheme = ['active_css_digest' => null, 'preview_css_digest' => null];
        try {
            if (!empty($features['package_themes']) && $request->path() !== '/admin/themes/safe-mode') {
                $themeState = $container->get(ThemeStateService::class);
                $user = $session->user();
                if ($user !== null && $user->isAdmin()) {
                    $previewId = $session->get('theme_preview_build');
                    $previewBuild = $themeState->previewBuildFor(is_int($previewId) ? $previewId : null);
                    if ($previewBuild !== null) {
                        $packageTheme['preview_css_digest'] = (string) $previewBuild['css_digest'];
                    } elseif ($previewId !== null) {
                        $session->forget('theme_preview_build');
                    }
                } elseif ($session->get('theme_preview_build') !== null) {
                    $session->forget('theme_preview_build');
                }

                if ($packageTheme['preview_css_digest'] === null) {
                    $activeBuild = $themeState->activeBuild();
                    if ($activeBuild !== null) {
                        $packageTheme['active_css_digest'] = (string) $activeBuild['css_digest'];
                    }
                }
            }
        } catch (Throwable) {
            $packageTheme = ['active_css_digest' => null, 'preview_css_digest' => null];
        }

        // Configured OAuth providers power the "Sign in with …" buttons. The
        // narrow menu never hydrates registry cache blobs or builds provider
        // objects — this runs on EVERY request, and only /login consumes it.
        $oauthProviders = [];
        try {
            if (!empty($features['oauth'])) {
                $oauthProviders = $container->get(ProviderRegistry::class)->loginMenu();
            }
        } catch (Throwable) {
            $oauthProviders = [];
        }

        // Passkey sign-in affordance (P5-11): offered only where the ceremony can
        // succeed — flag on AND the RelyingParty policy satisfiable (production
        // with a stale http:// APP_URL would guarantee a 422 on every challenge).
        // The catch also absorbs an unparseable APP_URL (the constructor throws);
        // no DB is touched. Only /login consumes this.
        $passkeysUsable = false;
        try {
            if (!empty($features['passkeys'])) {
                $passkeysUsable = $container->get(RelyingParty::class)->isUsable();
            }
        } catch (Throwable) {
            $passkeysUsable = false;
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

        // Site announcement banner (ADMIN §7.4; SCHEMA §7 #13): a defensive read
        // so the global shell can show an operator notice. Its own try/catch keeps
        // a missing or garbled settings row from 500ing the shell (it renders
        // pre-setup and against an un-migrated DB).
        $siteAnnouncement = null;
        try {
            $value = $container->get(SettingRepository::class)->get('site_announcement', null);
            if (is_array($value) && !empty($value['active'])) {
                $siteAnnouncement = $value;
            }
        } catch (Throwable) {
            $siteAnnouncement = null;
        }

        $moderationAccess = ['can_reports' => false, 'report_count' => 0];
        try {
            $user = $session->user();
            if ($user !== null && !empty($features['moderation_queue'])) {
                if ($user->isAdmin()) {
                    $moderationAccess = [
                        'can_reports' => true,
                        'report_count' => $container->get(ReportRepository::class)->openCount(true, []),
                    ];
                } else {
                    $boardIds = $container->get(BoardModeratorRepository::class)->boardsFor($user->id());
                    if ($boardIds !== []) {
                        $moderationAccess = [
                            'can_reports' => true,
                            'report_count' => $container->get(ReportRepository::class)->openCount(false, $boardIds),
                        ];
                    }
                }
            }
        } catch (Throwable) {
            $moderationAccess = ['can_reports' => false, 'report_count' => 0];
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
            'passkeys_usable' => $passkeysUsable,
            'appearance' => $appearance,
            'composing' => $composing,
            'branding' => $branding,
            'package_theme' => $packageTheme,
            'app_url' => (string) $this->config->get('app.url', ''),
            'needs_tour' => $needsTour,
            'site_announcement' => $siteAnnouncement,
            'moderation_access' => $moderationAccess,
        ]);
    }

    /**
     * Operator branding (P3-07): site name + optional logo/favicon/colors with
     * safe built-in fallbacks. Never throws — a missing setting or table yields
     * the default RetroBoards chrome so the shell always renders.
     *
     * @param array<string,mixed> $appearance
     * @return array{name:string,logo_path:?string,favicon_path:?string,color_primary:string,color_accent:string,theme_preset:string,has_custom_colors:bool,version:string}
     */
    private function branding(Container $container, string $siteName, array $appearance = []): array
    {
        $brand = [
            'name' => $siteName,
            'logo_path' => null,
            'favicon_path' => null,
            'color_primary' => '',
            'color_accent' => '',
            'theme_preset' => 'classic',
            'has_custom_colors' => false,
            'version' => '',
        ];
        try {
            if (!$container->get(FeatureFlags::class)->enabled('branding')) {
                return $brand;
            }
            $settings = $container->get(SettingRepository::class);
            $logo = $settings->getString('brand_logo_path', '');
            $lightLogo = $settings->getString('brand_logo_light_path', '');
            $darkLogo = $settings->getString('brand_logo_dark_path', '');
            $favicon = $settings->getString('brand_favicon_path', '');
            $primary = $settings->getString('brand_color_primary', '');
            $accent = $settings->getString('brand_color_accent', '');
            $preset = $settings->getString('brand_theme_preset', 'classic');
            $brand['version'] = $settings->getString('brand_version', '');
            $theme = (string) ($appearance['theme'] ?? 'system');
            $selectedLogo = match ($theme) {
                'dark' => $darkLogo !== '' ? $darkLogo : $logo,
                'light' => $lightLogo !== '' ? $lightLogo : $logo,
                default => $logo,
            };
            if ($selectedLogo !== '') {
                $brand['logo_path'] = $selectedLogo;
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
            if (in_array($preset, ['classic', 'retro'], true)) {
                $brand['theme_preset'] = $preset;
            }
            $hasCustomCss = $container->get(FeatureFlags::class)->enabled('custom_css')
                && $settings->get('brand_custom_css_enabled', false) === true
                && $settings->getString('brand_custom_css', '') !== '';
            $brand['has_custom_colors'] = $brand['color_primary'] !== ''
                || $brand['color_accent'] !== ''
                || $brand['theme_preset'] !== 'classic'
                || $hasCustomCss;
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
        $c->bind(HtmlSanitizer::class, fn (Container $c) => new HtmlSanitizer(
            allowGiphyImages: $this->allowGiphyCsp($c),
        ));
        $c->bind(Markdown::class, fn (Container $c) => new Markdown(
            $c->get(HtmlSanitizer::class),
            $c->get(FeatureFlags::class)->enabled('custom_emoji') ? $c->get(CustomEmojiService::class) : null,
            $c->get(MentionLinker::class),
        ));
        $c->bind(MentionLinker::class, fn (Container $c) => new MentionLinker(
            $c->get(UserRepository::class),
            $c->get(FeatureFlags::class)->enabled('mentions'),
        ));
        $c->bind(PasswordHasher::class, fn () => new PasswordHasher());
        $c->bind(ReauthGate::class, fn (Container $c) => new ReauthGate($c->get(PasswordHasher::class)));
        $c->bind(RegistrationPolicy::class, fn (Container $c) => new RegistrationPolicy(
            $c->get(SettingRepository::class),
            $c->get(FeatureFlags::class),
        ));
        $c->bind(Telemetry::class, fn () => new Telemetry($config));
        $c->bind(SecretBox::class, fn () => new SecretBox((string) $config->get('app.key', '')));
        $c->bind(Totp::class, fn () => new Totp());
        $c->bind(WriteGate::class, fn () => new WriteGate());
        $c->bind(BoardPolicy::class, fn () => new BoardPolicy());
        $c->bind(View::class, fn () => new View((string) $config->get('paths.templates')));
        $c->bind(Flash::class, fn () => new Flash((bool) $config->get('session.secure', true)));

        // Repositories.
        $c->bind(UserRepository::class, fn (Container $c) => new UserRepository($c->get(Database::class)));
        $c->bind(AccountDeletionRepository::class, fn (Container $c) => new AccountDeletionRepository($c->get(Database::class)));
        $c->bind(SessionRepository::class, fn (Container $c) => new SessionRepository($c->get(Database::class)));
        $c->bind(SettingRepository::class, fn (Container $c) => new SettingRepository($c->get(Database::class)));
        $c->bind(CategoryRepository::class, fn (Container $c) => new CategoryRepository($c->get(Database::class)));
        $c->bind(BoardRepository::class, fn (Container $c) => new BoardRepository($c->get(Database::class)));
        $c->bind(ThreadRepository::class, fn (Container $c) => new ThreadRepository($c->get(Database::class)));
        $c->bind(PostRepository::class, fn (Container $c) => new PostRepository($c->get(Database::class)));
        $c->bind(ThreadIntelligenceJobRepository::class, fn (Container $c) => new ThreadIntelligenceJobRepository($c->get(Database::class)));
        $c->bind(ThreadIntelligenceGenerationRepository::class, fn (Container $c) => new ThreadIntelligenceGenerationRepository($c->get(Database::class)));
        $c->bind(ModerationLogRepository::class, fn (Container $c) => new ModerationLogRepository($c->get(Database::class)));
        $c->bind(ModerationAppealRepository::class, fn (Container $c) => new ModerationAppealRepository($c->get(Database::class)));
        $c->bind(ServerDraftRepository::class, fn (Container $c) => new ServerDraftRepository($c->get(Database::class)));
        $c->bind(ServerExtensionRepository::class, fn (Container $c) => new ServerExtensionRepository($c->get(Database::class)));
        $c->bind(ApiTokenRepository::class, fn (Container $c) => new ApiTokenRepository($c->get(Database::class)));
        $c->bind(PackageCredentialAuthGuard::class, fn (Container $c) => new PackageCredentialAuthGuard(
            $c->get(InstalledPackageCredentialRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(LocalPackageBlockRepository::class),
            $c->get(SettingRepository::class),
            $config,
        ));
        $c->bind(ApiTokenService::class, fn (Container $c) => new ApiTokenService(
            $c->get(Database::class),
            $c->get(ApiTokenRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(PackageCredentialAuthGuard::class),
            $c->get(UserRepository::class),
        ));
        $c->bind(ServiceSecretRepository::class, fn (Container $c) => new ServiceSecretRepository($c->get(Database::class)));
        $c->bind(SecretVault::class, fn (Container $c) => new SecretVault(
            $c->get(Database::class),
            $c->get(ServiceSecretRepository::class),
            $c->get(SecretBox::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
        ));
        $c->bind(WebhookRepository::class, fn (Container $c) => new WebhookRepository($c->get(Database::class)));
        $c->bind(WebhookDeliveryRepository::class, fn (Container $c) => new WebhookDeliveryRepository($c->get(Database::class)));
        $c->bind(WebhookTransport::class, fn () => new CurlWebhookTransport(
            new EgressGuard(
                (bool) $config->get('webhooks.allow_http', false),
                (array) $config->get('webhooks.allowed_private_cidrs', []),
            ),
            (int) $config->get('webhooks.max_response_bytes', 65536),
        ));
        $c->bind(WebhookService::class, fn (Container $c) => new WebhookService(
            $c->get(Database::class),
            $c->get(WebhookRepository::class),
            $c->get(WebhookDeliveryRepository::class),
            $c->get(SecretVault::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $config,
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            new EgressGuard(
                (bool) $config->get('webhooks.allow_http', false),
                (array) $config->get('webhooks.allowed_private_cidrs', []),
            ),
        ));
        $c->bind(BlockRepository::class, fn (Container $c) => new BlockRepository($c->get(Database::class)));
        $c->bind(BoardModeratorRepository::class, fn (Container $c) => new BoardModeratorRepository($c->get(Database::class)));
        $c->bind(BoardMemberRepository::class, fn (Container $c) => new BoardMemberRepository($c->get(Database::class)));
        $c->bind(ThreadUserRepository::class, fn (Container $c) => new ThreadUserRepository($c->get(Database::class)));
        $c->bind(ReactionRepository::class, fn (Container $c) => new ReactionRepository($c->get(Database::class)));
        $c->bind(SubscriptionRepository::class, fn (Container $c) => new SubscriptionRepository($c->get(Database::class)));
        $c->bind(NotificationRepository::class, fn (Container $c) => new NotificationRepository($c->get(Database::class)));
        $c->bind(EmailDomainStatusRepository::class, fn (Container $c) => new EmailDomainStatusRepository($c->get(Database::class)));
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
        $c->bind(EmailPreferenceService::class, fn (Container $c) => new EmailPreferenceService($c->get(UserPreferenceRepository::class)));
        $c->bind(UserBoardPrefRepository::class, fn (Container $c) => new UserBoardPrefRepository($c->get(Database::class)));
        $c->bind(UserProfileFieldRepository::class, fn (Container $c) => new UserProfileFieldRepository($c->get(Database::class)));
        $c->bind(UsernameHistoryRepository::class, fn (Container $c) => new UsernameHistoryRepository($c->get(Database::class)));
        $c->bind(VerificationRepository::class, fn (Container $c) => new VerificationRepository($c->get(Database::class)));
        $c->bind(IdempotencyRepository::class, fn (Container $c) => new IdempotencyRepository($c->get(Database::class)));
        $c->bind(AttachmentRepository::class, fn (Container $c) => new AttachmentRepository($c->get(Database::class)));
        $c->bind(AttachmentScanService::class, fn (Container $c) => new AttachmentScanService($c->get(AttachmentRepository::class)));
        $c->bind(MfaRepository::class, fn (Container $c) => new MfaRepository($c->get(Database::class)));
        $c->bind(WebAuthnCredentialRepository::class, fn (Container $c) => new WebAuthnCredentialRepository($c->get(Database::class)));
        $c->bind(WebAuthnChallengeRepository::class, fn (Container $c) => new WebAuthnChallengeRepository($c->get(Database::class)));
        $c->bind(RelyingParty::class, function () use ($config): RelyingParty {
            $override = trim((string) $config->get('app.webauthn_rp_id', ''));
            return new RelyingParty(
                (string) $config->get('app.url', ''),
                $override !== '' ? $override : null,
                (string) $config->get('app.env', 'production'),
            );
        });
        $c->bind(WebAuthnVerifier::class, fn (Container $c) => new WebAuthnVerifier($c->get(RelyingParty::class)));
        $c->bind(CustomEmojiService::class, fn (Container $c) => new CustomEmojiService(
            $c->get(Database::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(ContentReferenceService::class, fn (Container $c) => new ContentReferenceService(
            $c->get(Database::class),
            $c->get(BoardRepository::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(TagRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(FeatureFlags::class)->enabled('tags'),
        ));
        $c->bind(LinkPreviewService::class, fn (Container $c) => new LinkPreviewService(
            $c->get(Database::class),
            $c->get(PostRepository::class),
            $c->get(SettingRepository::class),
            $config,
            new EgressGuard(
                (bool) $config->get('link_previews.allow_http', false),
                (array) $config->get('link_previews.allowed_private_cidrs', []),
            ),
        ));
        $c->bind(PollService::class, fn (Container $c) => new PollService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
            $c->get(AuthorityGate::class),
        ));
        $c->bind(PersonalOrganizationService::class, fn (Container $c) => new PersonalOrganizationService(
            $c->get(Database::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(ThreadRepository::class),
            $c->get(ThreadUserRepository::class),
        ));
        $c->bind(SinceLastReadContextService::class, fn (Container $c) => new SinceLastReadContextService(
            $c->get(Database::class),
        ));
        $c->bind(ProfileMediaService::class, fn (Container $c) => new ProfileMediaService(
            $c->get(Database::class),
            $c->get(AttachmentService::class),
            $c->get(AttachmentRepository::class),
            $c->get(UserRepository::class),
            $c->get(WriteGate::class),
        ));

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
            $c->get(FirstPartyHookRegistry::class),
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

        // Thread Intelligence is an explicitly composed, lazy singleton graph.
        // The raw credential reaches only the transport and keyed settings
        // fingerprint; status/services receive the typed readiness-only config.
        $threadIntelligenceBlock = (array) $config->get('thread_intelligence', []);
        $threadIntelligenceApiKey = is_string($threadIntelligenceBlock['api_key'] ?? null)
            ? $threadIntelligenceBlock['api_key']
            : '';
        $c->bind(ThreadIntelligenceConfig::class, fn () => ThreadIntelligenceConfig::fromArray($threadIntelligenceBlock));
        $c->bind(OpenAiTransport::class, fn (Container $c) => new CurlOpenAiTransport(
            $threadIntelligenceApiKey,
            $c->get(ThreadIntelligenceConfig::class),
        ));
        $c->bind(ThreadIntelligencePromptBuilder::class, fn () => new ThreadIntelligencePromptBuilder());
        if ($this->threadIntelligenceProvider !== null) {
            $c->instance(ThreadIntelligenceProvider::class, $this->threadIntelligenceProvider);
        } else {
            $c->bind(ThreadIntelligenceProvider::class, fn (Container $c) => new OpenAiThreadIntelligenceProvider(
                $c->get(OpenAiTransport::class),
                $c->get(ThreadIntelligenceConfig::class),
                $c->get(ThreadIntelligencePromptBuilder::class),
                (string) $config->get('app.key', ''),
            ));
        }
        if ($this->threadIntelligenceOutputModerator !== null) {
            $c->instance(ThreadIntelligenceOutputModerator::class, $this->threadIntelligenceOutputModerator);
        } else {
            $c->bind(ThreadIntelligenceOutputModerator::class, fn (Container $c) => new OpenAiThreadIntelligenceOutputModerator(
                $c->get(OpenAiTransport::class),
            ));
        }
        $c->bind(ThreadIntelligenceSettings::class, fn (Container $c) => new ThreadIntelligenceSettings(
            $c->get(SettingRepository::class),
            $c->get(ThreadIntelligenceConfig::class),
            (string) $config->get('app.key', ''),
            $threadIntelligenceApiKey,
            $c->get(Database::class),
        ));
        $c->bind(ThreadIntelligenceBudget::class, fn (Container $c) => new ThreadIntelligenceBudget(
            $c->get(Database::class),
            $c->get(ThreadIntelligenceConfig::class),
        ));
        $c->bind(ThreadIntelligenceEligibility::class, fn (Container $c) => new ThreadIntelligenceEligibility(
            $c->get(Database::class),
            $c->get(FeatureFlags::class),
            $c->get(ThreadIntelligenceConfig::class),
            $c->get(ThreadIntelligenceSettings::class),
            $c->get(ThreadIntelligenceBudget::class),
            $c->get(ThreadIntelligenceJobRepository::class),
        ));
        $c->bind(ThreadIntelligenceQueue::class, fn (Container $c) => new ThreadIntelligenceQueue(
            $c->get(Database::class),
            $c->get(ThreadIntelligenceJobRepository::class),
            $c->get(ThreadIntelligenceEligibility::class),
        ));
        $c->bind(ThreadIntelligenceBoardSweep::class, fn (Container $c) => new ThreadIntelligenceBoardSweep(
            $c->get(Database::class),
        ));
        $c->bind(ThreadIntelligenceCandidateFinder::class, fn (Container $c) => new ThreadIntelligenceCandidateFinder(
            $c->get(Database::class),
        ));
        $c->bind(ThreadIntelligenceEvidenceBuilder::class, fn (Container $c) => new ThreadIntelligenceEvidenceBuilder(
            $c->get(Database::class),
            $c->get(ThreadIntelligenceCandidateFinder::class),
            $c->get(ThreadIntelligenceConfig::class),
        ));
        $c->bind(ThreadIntelligenceOutputValidator::class, fn (Container $c) => new ThreadIntelligenceOutputValidator(
            $c->get(Markdown::class),
        ));
        $c->bind(ThreadIntelligencePublisher::class, fn (Container $c) => new ThreadIntelligencePublisher(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(ThreadIntelligenceJobRepository::class),
            $c->get(ThreadIntelligenceGenerationRepository::class),
            $c->get(ThreadIntelligenceEvidenceBuilder::class),
            $c->get(Markdown::class),
            $c->get(ContentReferenceService::class),
        ));
        $c->bind(ThreadIntelligenceWorker::class, fn (Container $c) => new ThreadIntelligenceWorker(
            $c->get(Database::class),
            $c->get(FeatureFlags::class),
            $c->get(ThreadIntelligenceConfig::class),
            $c->get(ThreadIntelligenceSettings::class),
            $c->get(ThreadIntelligenceBudget::class),
            $c->get(ThreadIntelligenceJobRepository::class),
            $c->get(ThreadIntelligenceGenerationRepository::class),
            $c->get(ThreadIntelligenceBoardSweep::class),
            $c->get(ThreadIntelligenceEligibility::class),
            $c->get(ThreadIntelligenceEvidenceBuilder::class),
            $c->get(ThreadIntelligenceProvider::class),
            $c->get(ThreadIntelligenceOutputValidator::class),
            $c->get(ThreadIntelligenceOutputModerator::class),
            $c->get(ThreadIntelligencePublisher::class),
            (string) $config->get('app.key', ''),
        ));
        $c->bind(ThreadIntelligenceOperationsService::class, fn (Container $c) => new ThreadIntelligenceOperationsService(
            $c->get(Database::class),
            $c->get(FeatureFlags::class),
            $c->get(ThreadIntelligenceConfig::class),
            $c->get(ThreadIntelligenceSettings::class),
            $c->get(ThreadIntelligenceBudget::class),
            $c->get(ThreadIntelligenceEligibility::class),
            $c->get(ThreadIntelligenceQueue::class),
            $c->get(ThreadIntelligenceJobRepository::class),
            $c->get(ThreadIntelligenceGenerationRepository::class),
        ));
        $c->bind(ThreadIntelligenceAdminService::class, fn (Container $c) => new ThreadIntelligenceAdminService(
            $c->get(Database::class),
            $c->get(FeatureFlags::class),
            $c->get(ThreadIntelligenceSettings::class),
            $c->get(ThreadIntelligenceOperationsService::class),
            $c->get(ThreadIntelligenceQueue::class),
            $c->get(ThreadIntelligenceJobRepository::class),
            $c->get(ThreadIntelligenceGenerationRepository::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(ThreadIntelligenceViewService::class, fn (Container $c) => new ThreadIntelligenceViewService(
            $c->get(Database::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(ThreadIntelligenceEligibility::class),
            $c->get(ThreadIntelligenceJobRepository::class),
        ));
        $c->bind(ExtensionSandbox::class, fn () => new BubblewrapSandboxAdapter());
        $c->bind(FirstPartyHookRegistry::class, function (Container $c): FirstPartyHookRegistry {
            $registry = new FirstPartyHookRegistry($c->get(FeatureFlags::class));
            foreach (WebhookEvents::domainEvents() as $event => $_description) {
                $registry->on($event, 'webhooks.' . $event, function (HookEvent $hookEvent) use ($c): void {
                    $c->get(WebhookService::class)->dispatch($hookEvent->name, $hookEvent->data, $hookEvent->id);
                });
            }
            return $registry;
        });
        $c->bind(RepairService::class, fn (Container $c) => new RepairService(
            $c->get(Database::class),
            (int) $config->get('community.solved_bonus', 5),
            $c->get(ThemeStateService::class),
        ));
        $c->bind(SearchService::class, fn (Container $c) => new MysqlSearchService($c->get(Database::class)));
        $c->bind(ComposerSuggestionService::class, fn (Container $c) => new ComposerSuggestionService(
            $c->get(UserRepository::class),
            $c->get(BoardRepository::class),
            $c->get(TagRepository::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(SearchService::class),
            $c->get(FeatureFlags::class),
            $c->get(CustomEmojiService::class),
        ));
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
            $c->get(EmailPreferenceService::class),
        ));
        $c->bind(EmailDomainVerifier::class, fn (Container $c) => new EmailDomainVerifier(
            $config,
            $c->get(SettingRepository::class),
            $c->get(EmailDomainStatusRepository::class),
        ));
        $c->bind(EmailOpsService::class, fn (Container $c) => new EmailOpsService(
            $c->get(Database::class),
            $c->get(EmailDeliveryRepository::class),
            $c->get(EmailSuppressionRepository::class),
            $c->get(SubscriptionRepository::class),
            $c->get(UserRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(Mailer::class),
            $c->get(EmailDomainVerifier::class),
        ));
        $c->bind(AnnouncementService::class, fn (Container $c) => new AnnouncementService(
            $c->get(Database::class),
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(NotificationRepository::class),
            $c->get(EmailDeliveryRepository::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(UserModerationService::class, fn (Container $c) => new UserModerationService(
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(BoardModeratorRepository::class),
            $c->get(AttachmentRepository::class),
            $c->get(FirstPartyHookRegistry::class),
            $c->get(AuthorityGate::class),
            $c->get(LastOwnerGuard::class),
            $c->get(ProtectedOwnerRepository::class),
            $c->get(SessionRepository::class),
            $c->get(ReauthGate::class),
            $c->get(CapabilityResolver::class),
        ));
        $c->bind(AppealService::class, fn (Container $c) => new AppealService(
            $c->get(Database::class),
            $c->get(ModerationAppealRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(NotificationRepository::class),
            $c->get(PostRepository::class),
            $c->get(UserRepository::class),
            $c->get(ModerationService::class),
            $c->get(UserModerationService::class),
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
            $c->get(FirstPartyHookRegistry::class),
            $c->get(AuthorityGate::class),
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
            $c->get(FeatureFlags::class)->enabled('content_references') ? $c->get(ContentReferenceService::class) : null,
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
            $c->get(FeatureFlags::class)->enabled('custom_emoji') ? $c->get(CustomEmojiService::class) : null,
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
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(BadgeRuleService::class, fn (Container $c) => new BadgeRuleService(
            $c->get(Database::class),
            $c->get(BadgeRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(WriteGate::class),
            $c->get(NotificationService::class),
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
            $c->get(FirstPartyHookRegistry::class),
            (int) $config->get('community.solved_bonus', 5),
            $c->get(AuthorityGate::class),
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
            $c->get(FeatureFlags::class),
            $c->get(AuthorityGate::class),
        ));
        $c->bind(ThreadSplitMergeService::class, fn (Container $c) => new ThreadSplitMergeService(
            $c->get(Database::class),
            $c->get(ThreadRepository::class),
            $c->get(PostRepository::class),
            $c->get(ModerationService::class),
            $c->get(ModerationLogRepository::class),
            $c->get(ThreadIntelligenceQueue::class),
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
            $c->get(FeatureFlags::class)->enabled('content_references') ? $c->get(ContentReferenceService::class) : null,
            $c->get(AuthorityGate::class),
            $c->get(ThreadIntelligenceQueue::class),
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
            $c->get(FirstPartyHookRegistry::class),
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
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(PasswordHasher::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $config,
            $c->get(UserPreferenceRepository::class),
            $c->get(FeatureFlags::class),
            $c->get(UserProfileFieldRepository::class),
        ));
        $c->bind(ProtectedOwnerRepository::class, fn (Container $c) => new ProtectedOwnerRepository(
            $c->get(Database::class),
        ));
        $c->bind(LastOwnerGuard::class, fn (Container $c) => new LastOwnerGuard(
            $c->get(ProtectedOwnerRepository::class),
            $c->get(UserRepository::class),
        ));
        $c->bind(CapabilityRepository::class, fn (Container $c) => new CapabilityRepository($c->get(Database::class)));
        $c->bind(RoleRepository::class, fn (Container $c) => new RoleRepository($c->get(Database::class)));
        $c->bind(RoleCapabilityRepository::class, fn (Container $c) => new RoleCapabilityRepository($c->get(Database::class)));
        $c->bind(RoleAssignmentRepository::class, fn (Container $c) => new RoleAssignmentRepository($c->get(Database::class)));
        $c->bind(RoleAssignmentHistoryRepository::class, fn (Container $c) => new RoleAssignmentHistoryRepository($c->get(Database::class)));
        $c->bind(LegacyAuthorityProjection::class, fn (Container $c) => new LegacyAuthorityProjection(
            $c->get(BoardModeratorRepository::class),
        ));
        $c->bind(CapabilityResolver::class, fn (Container $c) => new CapabilityResolver(
            $c->get(RoleCapabilityRepository::class),
            $c->get(RoleAssignmentRepository::class),
            $c->get(LegacyAuthorityProjection::class),
            $c->get(ProtectedOwnerRepository::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
            $c->get(WriteGate::class),
        ));
        $c->bind(ResolverShadow::class, fn (Container $c) => new ResolverShadow(
            $c->get(CapabilityResolver::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(AuthorityGate::class, function (Container $c) use ($config): AuthorityGate {
            if (!$c->get(FeatureFlags::class)->enabled('capabilities')) {
                return AuthorityGate::legacy();
            }
            return AuthorityGate::fromConfig(
                (string) $config->get('capabilities.mode', 'shadow'),
                $c->get(CapabilityResolver::class),
                $c->get(ResolverShadow::class),
                $c->get(Telemetry::class),
            );
        });
        $c->bind(RoleService::class, fn (Container $c) => new RoleService(
            $c->get(Database::class),
            $c->get(RoleRepository::class),
            $c->get(RoleCapabilityRepository::class),
            $c->get(CapabilityRepository::class),
            $c->get(RoleAssignmentRepository::class),
            $c->get(RoleAssignmentHistoryRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(CapabilityResolver::class),
        ));
        $c->bind(RoleAssignmentService::class, fn (Container $c) => new RoleAssignmentService(
            $c->get(Database::class),
            $c->get(RoleRepository::class),
            $c->get(RoleCapabilityRepository::class),
            $c->get(RoleAssignmentRepository::class),
            $c->get(RoleAssignmentHistoryRepository::class),
            $c->get(UserRepository::class),
            $c->get(BoardRepository::class),
            $c->get(CategoryRepository::class),
            $c->get(CapabilityResolver::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(PermissionSimulatorService::class, fn (Container $c) => new PermissionSimulatorService(
            $c->get(CapabilityResolver::class),
            $c->get(UserRepository::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(BoardPolicy::class),
        ));
        $c->bind(TrustChainVerifier::class, fn () => new TrustChainVerifier());
        $c->bind(PackageRegistryRepository::class, fn (Container $c) => new PackageRegistryRepository($c->get(Database::class)));
        $c->bind(RegistryTrustKeyRepository::class, fn (Container $c) => new RegistryTrustKeyRepository($c->get(Database::class)));
        $c->bind(PackagePublisherRepository::class, fn (Container $c) => new PackagePublisherRepository($c->get(Database::class)));
        $c->bind(PackageRepository::class, fn (Container $c) => new PackageRepository($c->get(Database::class)));
        $c->bind(PackageReleaseRepository::class, fn (Container $c) => new PackageReleaseRepository($c->get(Database::class)));
        $c->bind(PackageAdvisoryRepository::class, fn (Container $c) => new PackageAdvisoryRepository($c->get(Database::class)));
        $c->bind(PackageReviewDecisionRepository::class, fn (Container $c) => new PackageReviewDecisionRepository($c->get(Database::class)));
        $c->bind(LocalPackageBlockRepository::class, fn (Container $c) => new LocalPackageBlockRepository($c->get(Database::class)));
        $c->bind(InstalledPackageRepository::class, fn (Container $c) => new InstalledPackageRepository($c->get(Database::class)));
        $c->bind(InstalledPackagePermissionRepository::class, fn (Container $c) => new InstalledPackagePermissionRepository($c->get(Database::class)));
        $c->bind(PackageHistoryRepository::class, fn (Container $c) => new PackageHistoryRepository($c->get(Database::class)));
        $c->bind(PackageThemeRepository::class, fn (Container $c) => new PackageThemeRepository($c->get(Database::class)));
        $c->bind(PackageTransparencyLogRepository::class, fn (Container $c) => new PackageTransparencyLogRepository($c->get(Database::class)));
        $c->bind(ManifestValidator::class, fn () => new ManifestValidator());
        $c->bind(InstalledPackageSettingsRepository::class, fn (Container $c) => new InstalledPackageSettingsRepository($c->get(Database::class)));
        $c->bind(InstalledPackageCredentialRepository::class, fn (Container $c) => new InstalledPackageCredentialRepository($c->get(Database::class)));
        $c->bind(PublisherSigningKeyRepository::class, fn (Container $c) => new PublisherSigningKeyRepository($c->get(Database::class)));
        $c->bind(PackageReviewConsoleService::class, fn (Container $c) => new PackageReviewConsoleService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageReviewDecisionRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(PublisherTrustService::class, fn (Container $c) => new PublisherTrustService(
            $c->get(Database::class),
            $c->get(PackagePublisherRepository::class),
            $c->get(PublisherSigningKeyRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(TrustChainVerifier::class),
            $c->get(PackageHealthService::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(PackageSettingsService::class, fn (Container $c) => new PackageSettingsService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackageSettingsRepository::class),
            $c->get(SecretVault::class),
            $c->get(ManifestValidator::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(FeatureFlags::class),
            $config,
        ));
        $c->bind(PackageSecurityGate::class, fn (Container $c) => new PackageSecurityGate(
            $c->get(LocalPackageBlockRepository::class),
            $c->get(PackageAdvisoryRepository::class),
        ));
        $c->bind(RegistryTransport::class, fn () => new CurlRegistryTransport(
            new EgressGuard(
                (bool) $config->get('registry.allow_http', false),
                (array) $config->get('registry.allowed_private_cidrs', []),
            ),
            (int) $config->get('registry.max_snapshot_bytes', 1_048_576),
            (int) $config->get('registry.fetch_timeout_seconds', 10),
        ));
        $c->bind(PackageArtifactStore::class, fn () => new PackageArtifactStore((string) $config->get('packages.storage_path')));
        $c->bind(ThemeAssetScanner::class, fn () => new ThemeAssetScanner());
        $c->bind(ThemeBuildService::class, fn (Container $c) => new ThemeBuildService(
            $c->get(Database::class),
            $c->get(PackageThemeRepository::class),
            $c->get(ManifestValidator::class),
            $c->get(ThemeAssetScanner::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(ThemeStateService::class, fn (Container $c) => new ThemeStateService(
            $c->get(Database::class),
            $c->get(PackageThemeRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageArtifactStore::class),
            $c->get(PackageSecurityGate::class),
            $c->get(ThemeBuildService::class),
            $c->get(WriteGate::class),
            $c->get(ReauthGate::class),
            $c->get(SettingRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(Telemetry::class),
            (bool) $config->get('theme.safe_mode', false),
        ));
        $c->bind(PackageAcquisitionService::class, fn (Container $c) => new PackageAcquisitionService(
            $c->get(Database::class),
            $c->get(TrustChainVerifier::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageReviewDecisionRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(PackageArtifactStore::class),
            $c->get(ManifestValidator::class),
            $c->get(RegistryTransport::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(PackageIntegrationService::class, fn (Container $c) => new PackageIntegrationService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackagePermissionRepository::class),
            $c->get(InstalledPackageSettingsRepository::class),
            $c->get(InstalledPackageCredentialRepository::class),
            $c->get(ApiTokenService::class),
            $c->get(WebhookService::class),
            $c->get(ApiTokenRepository::class),
            $c->get(WebhookRepository::class),
            $c->get(SecretVault::class),
            $c->get(ManifestValidator::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(FeatureFlags::class),
            $c->get(SettingRepository::class),
            $config,
        ));
        $c->bind(PackageLifecycleService::class, fn (Container $c) => new PackageLifecycleService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackagePermissionRepository::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(PackageReviewDecisionRepository::class),
            $c->get(PackageAcquisitionService::class),
            $c->get(PackageSecurityGate::class),
            $c->get(PackageArtifactStore::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            (int) $config->get('packages.retention_days', 30),
            $c->get(Telemetry::class),
            $c->get(ThemeStateService::class),
            $c->get(PackageIntegrationService::class),
        ));
        $c->bind(PackageUpdateService::class, fn (Container $c) => new PackageUpdateService(
            $c->get(Database::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackagePermissionRepository::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(PackageAcquisitionService::class),
            $c->get(PackageSecurityGate::class),
            $c->get(ManifestValidator::class),
            $c->get(PackageArtifactStore::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Telemetry::class),
            $c->get(PackageIntegrationService::class),
        ));
        $c->bind(PackageHealthService::class, fn (Container $c) => new PackageHealthService(
            $c->get(Database::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackagePermissionRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(LocalPackageBlockRepository::class),
            $c->get(PackageHistoryRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(PackageArtifactStore::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Telemetry::class),
            $c->get(ThemeStateService::class),
            $c->get(PackageIntegrationService::class),
        ));
        $c->bind(RegistrySnapshotRepository::class, fn (Container $c) => new RegistrySnapshotRepository($c->get(Database::class)));
        $c->bind(RegistrySnapshotService::class, fn (Container $c) => new RegistrySnapshotService(
            $c->get(Database::class),
            $c->get(TrustChainVerifier::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(RegistrySnapshotRepository::class),
            $c->get(PackagePublisherRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(Telemetry::class),
        ));
        $c->bind(RegistryTrustService::class, fn (Container $c) => new RegistryTrustService(
            $c->get(Database::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(TrustChainVerifier::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(RegistryAdvisoryService::class, fn (Container $c) => new RegistryAdvisoryService(
            $c->get(Database::class),
            $c->get(TrustChainVerifier::class),
            $c->get(RegistryTrustKeyRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Telemetry::class),
            $c->get(PackageHealthService::class),
        ));
        $c->bind(LocalBlocklistService::class, fn (Container $c) => new LocalBlocklistService(
            $c->get(LocalPackageBlockRepository::class),
            $c->get(PackageRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $c->get(PackageHealthService::class),
        ));
        $c->bind(PackageSecurityResponseService::class, fn (Container $c) => new PackageSecurityResponseService(
            $c->get(Database::class),
            $c->get(SettingRepository::class),
            $c->get(RegistryAdvisoryService::class),
            $c->get(LocalBlocklistService::class),
            $c->get(PackageHealthService::class),
            $c->get(PackageIntegrationService::class),
            $c->get(PackagePublisherRepository::class),
            $c->get(PublisherSigningKeyRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(LocalPackageBlockRepository::class),
            $c->get(PackageTransparencyLogRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $config,
        ));
        $c->bind(RegistryCatalogService::class, fn (Container $c) => new RegistryCatalogService(
            $c->get(PackageRepository::class),
            $c->get(PackageReleaseRepository::class),
            $c->get(PackageAdvisoryRepository::class),
            $c->get(PackageRegistryRepository::class),
            $c->get(RegistrySnapshotService::class),
            $c->get(LocalBlocklistService::class),
            $c->get(InstalledPackageRepository::class),
            $c->get(InstalledPackagePermissionRepository::class),
            $c->get(PackageHistoryRepository::class),
        ));
        $c->bind(AccountLifecycleService::class, fn (Container $c) => new AccountLifecycleService(
            $c->get(Database::class),
            $c->get(UserRepository::class),
            $c->get(AccountDeletionRepository::class),
            $c->get(SessionRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(ServerDraftRepository::class),
            $c->get(ReauthGate::class),
            $c->get(WebAuthnCredentialRepository::class),
            $c->get(FeatureFlags::class)->enabled('capabilities') ? $c->get(LastOwnerGuard::class) : null,
        ));
        $c->bind(MfaService::class, fn (Container $c) => new MfaService(
            $c->get(MfaRepository::class),
            $c->get(UserRepository::class),
            $c->get(ReauthGate::class),
            $c->get(SecretBox::class),
            $c->get(Totp::class),
            $c->get(WriteGate::class),
            $c->get(ModerationLogRepository::class),
            $config,
        ));
        // One owner for "providers a member can sign in with right now": the
        // oauth master flag gates the whole set. Both lockout guards (oauth
        // unlink, passkey delete) receive THIS closure so they can never
        // disagree about whether removing a method locks a member out.
        $usableProviderNames = static fn (): array => $c->get(FeatureFlags::class)->enabled('oauth')
            ? $c->get(ProviderRegistry::class)->configuredNames()
            : [];
        $c->bind(PasskeyService::class, fn (Container $c) => new PasskeyService(
            $c->get(WebAuthnCredentialRepository::class),
            $c->get(WebAuthnChallengeRepository::class),
            $c->get(WebAuthnVerifier::class),
            $c->get(RelyingParty::class),
            $c->get(UserRepository::class),
            $c->get(OAuthIdentityRepository::class),
            $c->get(MfaService::class),
            $c->get(ReauthGate::class),
            $c->get(WriteGate::class),
            $c->get(LastOwnerGuard::class),
            $c->get(ModerationLogRepository::class),
            $c->get(Mailer::class),
            $config,
            $c->get(Database::class),
            $c->get(Telemetry::class),
            $usableProviderNames,
        ));
        $c->bind(PreferenceService::class, fn (Container $c) => new PreferenceService(
            $c->get(UserPreferenceRepository::class),
            (int) $config->get('pagination.threads_per_page', 20),
            (int) $config->get('pagination.posts_per_page', 20),
        ));
        $c->bind(OAuthHttpClient::class, fn () => $this->oauthHttpClient ?? new OAuthHttpClient());
        $c->bind(IdentityProviderService::class, fn (Container $c) => new IdentityProviderService(
            $c->get(Database::class),
            $c->get(IdentityProviderRepository::class),
            $c->get(SecretVault::class),
            $c->get(OidcDiscovery::class),
            $c->get(JwksCache::class),
            $c->get(ReauthGate::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
        ));
        $c->bind(IdentityProviderRepository::class, fn (Container $c) => new IdentityProviderRepository($c->get(Database::class)));
        $c->bind(InvitationRepository::class, fn (Container $c) => new InvitationRepository($c->get(Database::class)));
        $c->bind(InvitationService::class, fn (Container $c) => new InvitationService(
            $c->get(Database::class),
            $c->get(InvitationRepository::class),
            $c->get(AuthService::class),
            $c->get(BoardRepository::class),
            $c->get(BoardMemberRepository::class),
            $c->get(ModerationLogRepository::class),
        ));
        $c->bind(OidcDiscovery::class, fn (Container $c) => new OidcDiscovery($c->get(OAuthHttpClient::class)));
        $c->bind(JwksCache::class, fn (Container $c) => new JwksCache(
            $c->get(IdentityProviderRepository::class),
            $c->get(OAuthHttpClient::class),
        ));
        $c->bind(ProviderRegistry::class, function (Container $c) use ($config) {
            // Registry-backed generic-OIDC providers join only when the P5-12
            // flag is on; both loaders fail dark inside ProviderRegistry.
            $dynamic = null;
            $menu = null;
            if ($c->get(FeatureFlags::class)->enabled('provider_registry')) {
                $dynamic = function () use ($c): array {
                    $providers = [];
                    foreach ($c->get(IdentityProviderRepository::class)->enabledGenericOidc() as $row) {
                        $providers[] = new OidcProvider(
                            $row,
                            $c->get(IdentityProviderRepository::class),
                            $c->get(OidcDiscovery::class),
                            $c->get(JwksCache::class),
                            new JwtVerifier(),
                            new ClaimMapper(),
                            $c->get(SecretVault::class),
                            $c->get(OAuthHttpClient::class),
                        );
                    }
                    return $providers;
                };
                $menu = fn (): array => $c->get(IdentityProviderRepository::class)->loginMenuRows();
            }
            return new ProviderRegistry((array) $config->get('oauth', []), $c->get(OAuthHttpClient::class), $dynamic, $menu);
        });
        $c->bind(OAuthService::class, fn (Container $c) => new OAuthService(
            $c->get(Database::class),
            $c->get(OAuthIdentityRepository::class),
            $c->get(UserRepository::class),
            $c->get(RegistrationPolicy::class),
            $c->get(FirstPartyHookRegistry::class),
            $usableProviderNames,
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
            $c->get(FirstPartyHookRegistry::class),
            $c->get(FeatureFlags::class)->enabled('content_references') ? $c->get(ContentReferenceService::class) : null,
            $c->get(FeatureFlags::class)->enabled('link_previews') ? $c->get(LinkPreviewService::class) : null,
            $c->get(AuthorityGate::class),
            $c->get(ThreadIntelligenceQueue::class),
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
            $c->get(FirstPartyHookRegistry::class),
            $c->get(AuthorityGate::class),
            $c->get(ThreadIntelligenceQueue::class),
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
            $c->get(AuthorityGate::class),
            $c->get(CapabilityResolver::class),
            $c->get(ThreadIntelligenceBoardSweep::class),
        ));
        $c->bind(AdminDashboardService::class, fn (Container $c) => new AdminDashboardService(
            $c->get(Database::class),
            $c->get(EmailDeliveryRepository::class),
            $c->get(ModerationLogRepository::class),
            $c->get(FeatureFlags::class),
            $c->get(Mailer::class),
            $c->get(EmailDomainVerifier::class),
            $c->get(ThreadIntelligenceAdminService::class),
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
            $c->get(ProtectedOwnerRepository::class),
        ));

        return $c;
    }

    private function buildRouter(): Router
    {
        $r = new Router();

        $r->get('/', [HomeController::class, 'index']);
        $r->get('/privacy', [HomeController::class, 'privacy']);
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
        $r->post('/upload/file', [MediaController::class, 'uploadFile']);
        $r->get('/media/{id}', [MediaController::class, 'show']);
        $r->get('/media/{id}/download', [MediaController::class, 'download']);

        // Shared composer live preview (P3-02) — same render+sanitize pipeline.
        $r->post('/composer/preview', [ComposerController::class, 'preview']);
        $r->get('/composer/suggest', [ComposerController::class, 'suggest']);
        $r->get('/composer/giphy-config', [SlashGiphyController::class, 'pickerConfig']);
        $r->get('/drafts', [DraftController::class, 'index']);
        $r->post('/drafts/{id}/discard', [DraftController::class, 'discardPage']);
        $r->get('/api/drafts/{key}', [DraftController::class, 'load']);
        $r->post('/api/drafts/{key}', [DraftController::class, 'save']);
        $r->post('/api/drafts/{key}/discard', [DraftController::class, 'discard']);
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
        $r->post('/t/{id}/summary/refresh', [CommunityMemoryController::class, 'refreshSummary']);
        $r->post('/t/{id}/summary/automation/resume', [CommunityMemoryController::class, 'resumeAutomation']);
        $r->post('/t/{id}/related', [CommunityMemoryController::class, 'related']);
        $r->post('/t/{id}/poll', [PollController::class, 'create']);
        $r->post('/polls/{id}/vote', [PollController::class, 'vote']);
        $r->post('/polls/{id}/close', [PollController::class, 'close']);

        $r->get('/login', [AuthController::class, 'showLogin']);
        $r->post('/login', [AuthController::class, 'login']);
        $r->post('/login/mfa', [AuthController::class, 'completeMfa']);
        $r->post('/login/passkey/challenge', [AuthController::class, 'passkeyChallenge']);
        $r->post('/login/passkey', [AuthController::class, 'passkeyLogin']);
        $r->get('/register', [AuthController::class, 'showRegister']);
        $r->post('/register', [AuthController::class, 'register']);
        $r->get('/invite/{token}', [AuthController::class, 'invite']); // P5-13 invite landing (flag-gated, default-on 2026-07-09)
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
        $r->post('/settings/account/export', [AccountController::class, 'exportAccount']);
        $r->get('/settings/account/lifecycle', [AccountController::class, 'lifecycleForm']);
        $r->post('/settings/account/deactivate', [AccountController::class, 'deactivate']);
        $r->post('/settings/account/reactivate', [AccountController::class, 'reactivate']);
        $r->post('/settings/account/delete/request', [AccountController::class, 'requestDeletion']);
        $r->post('/settings/account/delete/cancel', [AccountController::class, 'cancelDeletion']);
        $r->post('/settings/avatar', [AccountController::class, 'uploadAvatar']);
        $r->post('/settings/avatar/remove', [AccountController::class, 'removeAvatar']);
        $r->get('/settings/security', [AccountController::class, 'securityForm']);
        $r->post('/settings/security', [AccountController::class, 'updateSecurity']);
        $r->post('/settings/security/totp/enroll', [AccountController::class, 'startTotpEnrollment']);
        $r->post('/settings/security/totp/confirm', [AccountController::class, 'confirmTotpEnrollment']);
        $r->post('/settings/security/totp/recovery/rotate', [AccountController::class, 'rotateRecoveryCodes']);
        $r->post('/settings/security/totp/disable', [AccountController::class, 'disableTotp']);
        $r->post('/settings/security/passkeys/challenge', [PasskeyController::class, 'challenge']);
        $r->post('/settings/security/passkeys/step-up-challenge', [PasskeyController::class, 'stepUpChallenge']);
        $r->post('/settings/security/passkeys/{id}/rename', [PasskeyController::class, 'rename']);
        $r->post('/settings/security/passkeys/{id}/revoke', [PasskeyController::class, 'revoke']);
        $r->post('/settings/security/passkeys', [PasskeyController::class, 'store']);

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
        $r->post('/settings/board-folders', [PersonalOrganizationController::class, 'createFolder']);
        $r->post('/settings/board-folders/{id}/boards', [PersonalOrganizationController::class, 'addBoard']);
        $r->post('/settings/bookmark-folders', [PersonalOrganizationController::class, 'createBookmarkFolder']);
        $r->post('/settings/bookmark-folders/add-thread', [PersonalOrganizationController::class, 'addThreadToBookmarkFolder']);
        $r->post('/settings/bookmark-folders/{id}/threads', [PersonalOrganizationController::class, 'addThreadToBookmarkFolder']);
        $r->post('/settings/saved-feeds', [PersonalOrganizationController::class, 'createSavedFeed']);

        $r->get('/setup', [SetupController::class, 'show']);
        $r->post('/setup', [SetupController::class, 'submit']);

        // Operator branding (P3-07): dynamic brand stylesheet + admin controls.
        $r->get('/brand.css', [BrandingController::class, 'css']);
        $r->get('/theme/preview.css', [ThemeController::class, 'previewCss']);
        $r->get('/theme/{digest}.css', [ThemeController::class, 'css']);
        $r->get('/theme/asset/{digest}', [ThemeController::class, 'asset']);
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

        // Moderation appeals (ADR 0007): member submission + staff resolution.
        $r->get('/appeals', [AppealController::class, 'index']);
        $r->post('/appeals/posts/{id}', [AppealController::class, 'openPost']);
        $r->post('/appeals/modlog/{id}', [AppealController::class, 'openModerationLog']);

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
        $r->get('/admin/api-tokens', [AdminApiTokenController::class, 'index']);
        $r->post('/admin/api-tokens', [AdminApiTokenController::class, 'mint']);
        $r->post('/admin/api-tokens/{id}/revoke', [AdminApiTokenController::class, 'revoke']);

        // Invitation lifecycle console (P5-13, Inc 9) — flag-gated, default-on 2026-07-09.
        $r->get('/admin/invitations', [AdminInvitationController::class, 'index']);
        $r->post('/admin/invitations', [AdminInvitationController::class, 'create']);
        $r->post('/admin/invitations/{id}/revoke', [AdminInvitationController::class, 'revoke']);
        $r->get('/admin/roles', [AdminRoleController::class, 'index']);
        $r->post('/admin/roles', [AdminRoleController::class, 'create']);
        $r->get('/admin/roles/simulator', [AdminRoleController::class, 'simulator']);
        $r->get('/admin/roles/{id}', [AdminRoleController::class, 'edit']);
        $r->post('/admin/roles/{id}', [AdminRoleController::class, 'update']);
        $r->post('/admin/roles/{id}/clone', [AdminRoleController::class, 'clone']);
        $r->post('/admin/roles/{id}/assignments', [AdminRoleController::class, 'assign']);
        $r->post('/admin/role-assignments/{id}/revoke', [AdminRoleController::class, 'revokeAssignment']);
        $r->post('/admin/role-assignments/{id}/renew', [AdminRoleController::class, 'renewAssignment']);
        // Identity-provider registry console (P5-12) — flag-gated, default-on 2026-07-09.
        $r->get('/admin/providers', [AdminProviderController::class, 'index']);
        $r->post('/admin/providers', [AdminProviderController::class, 'create']);
        $r->post('/admin/providers/{id}/test', [AdminProviderController::class, 'test']);
        $r->post('/admin/providers/{id}/enable', [AdminProviderController::class, 'enable']);
        $r->get('/admin/providers/{id}/disable', [AdminProviderController::class, 'disableConfirm']);
        $r->post('/admin/providers/{id}/disable', [AdminProviderController::class, 'disable']);
        $r->get('/admin/themes', [AdminThemeController::class, 'index']);
        $r->get('/admin/themes/safe-mode', [AdminThemeController::class, 'safeModeForm']);
        $r->post('/admin/themes/safe-mode', [AdminThemeController::class, 'safeMode']);
        $r->post('/admin/themes/preview/clear', [AdminThemeController::class, 'clearPreview']);
        $r->post('/admin/themes/rollback', [AdminThemeController::class, 'rollback']);
        $r->post('/admin/themes/{id}/preview', [AdminThemeController::class, 'preview']);
        $r->post('/admin/themes/{id}/activate', [AdminThemeController::class, 'activate']);
        $r->get('/admin/packages', [AdminPackagesController::class, 'index']);
        // Security-response console (P5-07-A) — non-numeric GETs registered before the numeric detail route.
        $r->get ('/admin/packages/security',                  [AdminPackageSecurityController::class, 'index']);
        $r->post('/admin/packages/security/execution',        [AdminPackageSecurityController::class, 'emergencyDisable']);
        // Publisher trust console (P5-07-A) — register the {id} publisher GET before the generic package GET.
        $r->get ('/admin/packages/publishers/{id}',           [AdminPackageSecurityController::class, 'publisher']);
        $r->post('/admin/packages/publishers/{id}/verify',    [AdminPackageSecurityController::class, 'verifyPublisher']);
        $r->post('/admin/packages/publishers/{id}/suspend',   [AdminPackageSecurityController::class, 'suspendPublisher']);
        $r->post('/admin/packages/publishers/{id}/reinstate', [AdminPackageSecurityController::class, 'reinstatePublisher']);
        $r->post('/admin/packages/publishers/{id}/keys',      [AdminPackageSecurityController::class, 'pinPublisherKey']);
        $r->post('/admin/packages/publishers/{id}/rotate',    [AdminPackageSecurityController::class, 'rotatePublisherKey']);
        $r->post('/admin/publisher-keys/{id}/revoke',         [AdminPackageSecurityController::class, 'revokePublisherKey']);
        $r->get('/admin/packages/{id}', [AdminPackagesController::class, 'show']);
        $r->post('/admin/packages/{id}/plan', [AdminPackageLifecycleController::class, 'plan']);
        $r->post('/admin/packages/{id}/install', [AdminPackageLifecycleController::class, 'install']);
        $r->get('/admin/packages/{id}/consent', [AdminPackageLifecycleController::class, 'consentForm']);
        $r->post('/admin/packages/{id}/consent', [AdminPackageLifecycleController::class, 'consent']);
        $r->post('/admin/packages/{id}/enable', [AdminPackageLifecycleController::class, 'enable']);
        $r->post('/admin/packages/{id}/disable', [AdminPackageLifecycleController::class, 'disable']);
        $r->post('/admin/packages/{id}/pin', [AdminPackageLifecycleController::class, 'pin']);
        $r->post('/admin/packages/{id}/update-policy', [AdminPackageLifecycleController::class, 'updatePolicy']);
        $r->post('/admin/packages/{id}/update', [AdminPackageLifecycleController::class, 'update']);
        $r->post('/admin/packages/{id}/update/cancel', [AdminPackageLifecycleController::class, 'cancelUpdate']);
        $r->post('/admin/packages/{id}/rollback', [AdminPackageLifecycleController::class, 'rollback']);
        $r->post('/admin/packages/{id}/uninstall', [AdminPackageLifecycleController::class, 'uninstall']);
        $r->post('/admin/packages/{id}/review', [AdminPackageSecurityController::class, 'recordReview']);
        $r->post('/admin/packages/{id}/export', [AdminPackageLifecycleController::class, 'export']);
        $r->post('/admin/packages/{id}/reverify', [AdminPackageLifecycleController::class, 'reverify']);
        // Integration runtime (P5-04) — remote_app / automation, flag-gated, default-on 2026-07-09.
        $r->post('/admin/packages/{id}/integration/settings',                          [AdminPackageIntegrationController::class, 'saveSettings']);
        $r->post('/admin/packages/{id}/integration/provision',                         [AdminPackageIntegrationController::class, 'provision']);
        $r->post('/admin/packages/{id}/integration/credentials/{credentialId}/rotate', [AdminPackageIntegrationController::class, 'rotateCredential']);
        $r->post('/admin/packages/{id}/integration/credentials/{credentialId}/revoke', [AdminPackageIntegrationController::class, 'revokeCredential']);
        $r->post('/admin/packages/{id}/integration/disable',                           [AdminPackageIntegrationController::class, 'disableIntegration']);
        $r->post('/admin/packages/{id}/integration/export',                            [AdminPackageIntegrationController::class, 'exportSettings']);
        $r->get('/admin/registries', [AdminRegistryController::class, 'index']);
        $r->post('/admin/registries', [AdminRegistryController::class, 'create']);
        $r->post('/admin/registries/{id}/enabled', [AdminRegistryController::class, 'setEnabled']);
        $r->post('/admin/registries/{id}/keys', [AdminRegistryController::class, 'pinKey']);
        $r->post('/admin/registries/{id}/rotate', [AdminRegistryController::class, 'rotate']);
        $r->post('/admin/registries/{id}/advisories', [AdminRegistryController::class, 'ingestAdvisory']);
        $r->post('/admin/registry-keys/{id}/revoke', [AdminRegistryController::class, 'revokeKey']);
        $r->post('/admin/advisories/{id}/ack', [AdminRegistryController::class, 'ackAdvisory']);
        $r->post('/admin/blocklist', [AdminRegistryController::class, 'block']);
        $r->post('/admin/blocklist/{id}/remove', [AdminRegistryController::class, 'unblock']);
        $r->get('/admin/webhooks', [AdminWebhookController::class, 'index']);
        $r->post('/admin/webhooks', [AdminWebhookController::class, 'create']);
        $r->get('/admin/webhooks/{id}', [AdminWebhookController::class, 'show']);
        $r->post('/admin/webhooks/{id}', [AdminWebhookController::class, 'update']);
        $r->post('/admin/webhooks/{id}/toggle', [AdminWebhookController::class, 'toggle']);
        $r->post('/admin/webhooks/{id}/rotate', [AdminWebhookController::class, 'rotate']);
        $r->post('/admin/webhooks/{id}/test', [AdminWebhookController::class, 'test']);
        $r->post('/admin/webhooks/{id}/delete', [AdminWebhookController::class, 'delete']);
        $r->post('/admin/webhooks/{id}/deliveries/{deliveryId}/replay', [AdminWebhookController::class, 'replay']);
        $r->get('/admin/email', [AdminEmailController::class, 'index']);
        $r->get('/admin/email/export', [AdminEmailController::class, 'export']);
        $r->post('/admin/email/test', [AdminEmailController::class, 'test']);
        $r->post('/admin/email/domain/verify', [AdminEmailController::class, 'verifyDomain']);
        $r->post('/admin/email/deliveries/{id}/requeue', [AdminEmailController::class, 'requeue']);
        $r->post('/admin/email/suppressions', [AdminEmailController::class, 'suppress']);
        $r->post('/admin/email/suppressions/remove', [AdminEmailController::class, 'unsuppress']);
        $r->get('/admin/features', [AdminFeatureController::class, 'index']);
        $r->get('/admin/thread-intelligence', [AdminThreadIntelligenceController::class, 'index']);
        $r->post('/admin/thread-intelligence/generation/pause', [AdminThreadIntelligenceController::class, 'pauseGeneration']);
        $r->post('/admin/thread-intelligence/generation/resume', [AdminThreadIntelligenceController::class, 'resumeGeneration']);
        $r->post('/admin/thread-intelligence/provider/retry', [AdminThreadIntelligenceController::class, 'retryProvider']);
        $r->post('/admin/thread-intelligence/threads/{id}/retry', [AdminThreadIntelligenceController::class, 'retryThread']);
        $r->post('/admin/thread-intelligence/threads/{id}/reconcile', [AdminThreadIntelligenceController::class, 'reconcileThread']);
        $r->post('/admin/thread-intelligence/threads/{id}/pause', [AdminThreadIntelligenceController::class, 'pauseThread']);
        $r->post('/admin/thread-intelligence/threads/{id}/resume', [AdminThreadIntelligenceController::class, 'resumeThread']);
        $r->get('/admin/extensions', [AdminExtensionController::class, 'index']);
        $r->get('/admin/announcements', [AdminAnnouncementController::class, 'form']);
        $r->post('/admin/announcements', [AdminAnnouncementController::class, 'save']);
        $r->get('/admin/badge-rules', [AdminBadgeRuleController::class, 'index']);
        $r->post('/admin/badge-rules', [AdminBadgeRuleController::class, 'create']);
        $r->get('/admin/badge-rules/{id}/preview', [AdminBadgeRuleController::class, 'preview']);
        $r->post('/admin/badge-rules/{id}/enable', [AdminBadgeRuleController::class, 'enable']);
        $r->post('/admin/badge-rules/{id}/disable', [AdminBadgeRuleController::class, 'disable']);
        $r->post('/admin/badge-rules/{id}/backfill', [AdminBadgeRuleController::class, 'backfill']);
        $r->post('/admin/badge-rules/{id}/revoke', [AdminBadgeRuleController::class, 'revoke']);
        $r->post('/admin/link-previews/{id}/refresh', [AdminLinkPreviewController::class, 'refresh']);
        $r->post('/admin/link-previews/{id}/purge', [AdminLinkPreviewController::class, 'purge']);
        $r->post('/admin/custom-emoji', [AdminCustomEmojiController::class, 'create']);
        $r->post('/admin/custom-emoji/{shortcode}/enable', [AdminCustomEmojiController::class, 'enable']);
        $r->post('/admin/custom-emoji/{shortcode}/disable', [AdminCustomEmojiController::class, 'disable']);
        $r->get('/admin/structure', [AdminController::class, 'structure']);
        $r->post('/admin/site', [AdminController::class, 'updateSite']);
        $r->post('/admin/settings', [AdminController::class, 'updateSettings']);
        $r->post('/admin/categories', [AdminController::class, 'createCategory']);
        $r->post('/admin/categories/{id}', [AdminController::class, 'updateCategory']);
        // Destructive structure actions are two-step: a GET confirmation page
        // (works with JS disabled, shows impact + typed-confirmation) then the
        // existing POST — the only mutating endpoint.
        $r->get('/admin/categories/{id}/delete', [AdminController::class, 'confirmDeleteCategory']);
        $r->post('/admin/categories/{id}/delete', [AdminController::class, 'deleteCategory']);
        $r->get('/admin/boards/{id}/edit', [AdminController::class, 'editBoard']);
        $r->post('/admin/boards', [AdminController::class, 'createBoard']);
        $r->post('/admin/boards/{id}', [AdminController::class, 'updateBoard']);
        $r->get('/admin/boards/{id}/delete', [AdminController::class, 'confirmDeleteBoard']);
        $r->post('/admin/boards/{id}/delete', [AdminController::class, 'deleteBoard']);

        // Structure ordering + archive (Phase 2). Static reorder before any
        // generic /admin/structure/{...}; {id} compiles to \d+ so the /move,
        // /archive, /unarchive suffixes never collide with /admin/boards/{id}.
        $r->post('/admin/categories/{id}/move', [AdminController::class, 'moveCategory']);
        $r->post('/admin/boards/{id}/move', [AdminController::class, 'moveBoard']);
        $r->post('/admin/structure/reorder', [AdminController::class, 'reorder']);
        $r->get('/admin/boards/{id}/archive', [AdminController::class, 'confirmArchiveBoard']);
        $r->post('/admin/boards/{id}/archive', [AdminController::class, 'archiveBoard']);
        $r->get('/admin/boards/{id}/unarchive', [AdminController::class, 'confirmUnarchiveBoard']);
        $r->post('/admin/boards/{id}/unarchive', [AdminController::class, 'unarchiveBoard']);

        // Board roster management (P2-08): assign/remove scoped moderators + members.
        $r->post('/admin/boards/{id}/moderators', [AdminController::class, 'assignModerator']);
        $r->post('/admin/boards/{id}/moderators/remove', [AdminController::class, 'unassignModerator']);
        $r->post('/admin/boards/{id}/members', [AdminController::class, 'addMember']);
        $r->post('/admin/boards/{id}/members/remove', [AdminController::class, 'removeMember']);

        // Per-user admin record (ADMIN §5.1/§5.2): directory + record screen,
        // manual badges + cosmetic title. Static before generic.
        $r->get('/admin/users', [AdminUserController::class, 'index']);
        $r->get('/admin/users/{id}', [AdminUserController::class, 'show']);
        $r->post('/admin/users/{id}/title', [AdminUserController::class, 'setTitle']);
        $r->post('/admin/users/{id}/avatar/remove', [AdminUserController::class, 'removeAvatar']);
        $r->post('/admin/users/{id}/signature/remove', [AdminUserController::class, 'removeSignature']);
        $r->post('/admin/users/{id}/badges/grant', [AdminUserController::class, 'grantBadge']);
        $r->post('/admin/users/{id}/badges/revoke', [AdminUserController::class, 'revokeBadge']);
        $r->post('/admin/users/{id}/warn', [AdminUserController::class, 'warn']);
        $r->post('/admin/users/{id}/note', [AdminUserController::class, 'note']);
        $r->post('/admin/users/{id}/suspend', [AdminUserController::class, 'suspend']);
        $r->post('/admin/users/{id}/ban', [AdminUserController::class, 'ban']);
        $r->post('/admin/users/{id}/lift', [AdminUserController::class, 'lift']);
        // users.role mutation (TM-PE-07): flag-INDEPENDENT — users.role exists
        // regardless of Phase 5, so this works when `capabilities` is rolled back.
        $r->post('/admin/users/{id}/role', [AdminUserController::class, 'changeRole']);

        $r->post('/mod/t/{id}/pin', [ModerationController::class, 'pin']);
        $r->post('/mod/t/{id}/lock', [ModerationController::class, 'lock']);
        $r->post('/mod/t/{id}/move', [ModerationController::class, 'move']);
        $r->post('/mod/t/{id}/split', [ModerationController::class, 'split']);
        $r->post('/mod/t/{id}/merge', [ModerationController::class, 'merge']);
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
        $r->get('/mod/appeals', [AppealController::class, 'queue']);
        $r->post('/mod/appeals/{id}/resolve', [AppealController::class, 'resolve']);

        // User moderation (P2-08).
        $r->post('/mod/u/{id}/warn', [UserModerationController::class, 'warn']);
        $r->post('/mod/u/{id}/note', [UserModerationController::class, 'note']);
        $r->post('/mod/u/{id}/suspend', [UserModerationController::class, 'suspend']);
        $r->post('/mod/u/{id}/ban', [UserModerationController::class, 'ban']);
        $r->post('/mod/u/{id}/lift', [UserModerationController::class, 'lift']);

        return $r;
    }
}
