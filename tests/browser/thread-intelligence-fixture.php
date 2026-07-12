<?php

declare(strict_types=1);

use App\Core\App;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\Request;
use App\Repository\SettingRepository;
use App\Repository\ThreadIntelligenceGenerationRepository;
use App\Repository\UserRepository;
use App\Service\CommunityMemoryService;
use App\Service\PostingService;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceOutputModerator;
use App\Service\ThreadIntelligence\FakeThreadIntelligenceProvider;
use App\Service\ThreadIntelligence\ThreadIntelligenceFailureCode;
use App\Service\ThreadIntelligence\ThreadIntelligenceProviderException;
use App\Service\ThreadIntelligence\ThreadIntelligenceResult;
use App\Service\ThreadIntelligence\ThreadIntelligenceSettings;
use App\Service\ThreadIntelligence\ThreadIntelligenceUsage;
use App\Worker\ThreadIntelligenceWorker;

const TI_FIXTURE_PROJECTS = ['desktop', 'mobile'];

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
$db = new Database($config->get('db'));
$embedded = defined('RB_BROWSER_THREAD_INTELLIGENCE_EMBEDDED');
$action = $embedded ? 'seed' : (string) ($argv[1] ?? 'show');
$project = $embedded ? 'desktop' : (string) ($argv[2] ?? 'desktop');

if (!in_array($project, TI_FIXTURE_PROJECTS, true)) {
    throw new InvalidArgumentException('fixture project must be desktop or mobile');
}

/** Build the exact production service graph while replacing both network boundaries. */
function tiContainer(
    Config $config,
    Database $db,
    ?FakeThreadIntelligenceProvider $provider = null,
): Container {
    $app = new App(
        $config,
        $db,
        null,
        null,
        $provider ?? new FakeThreadIntelligenceProvider(),
        new FakeThreadIntelligenceOutputModerator(),
    );
    $method = (new ReflectionClass($app))->getMethod('buildContainer');
    /** @var Container $container */
    $container = $method->invoke($app, new Request('GET', '/', [], [], [], []));
    return $container;
}

function tiTitle(string $kind, string $project): string
{
    return match ($kind) {
        'fallback' => 'TI Fallback ' . $project,
        'fallback_target' => 'TI Fallback Reference ' . $project,
        'brief' => 'TI Living Brief ' . $project,
        'brief_target' => 'TI Related ' . str_repeat('wrap', 22) . ' ' . $project,
        'lineage' => 'TI Curator Lineage ' . $project,
        'lineage_target' => 'TI Lineage Reference ' . $project,
        'last_good' => 'TI Last Good ' . $project,
        'last_good_target' => 'TI Last Good Reference ' . $project,
        'source_invalid' => 'TI Source Safety ' . $project,
        'source_target' => 'TI Source Safety Reference ' . $project,
        'budget' => 'TI Budget Last Good ' . $project,
        'budget_target' => 'TI Budget Reference ' . $project,
        'admin' => 'TI Admin Recovery ' . $project,
        default => throw new InvalidArgumentException('unknown fixture kind'),
    };
}

/** @return array<string,mixed> */
function tiThreadByTitle(Database $db, string $title): array
{
    $thread = $db->fetch('SELECT * FROM threads WHERE title = ? ORDER BY id ASC LIMIT 1', [$title]);
    if ($thread === null) {
        throw new RuntimeException('missing fixture thread: ' . $title);
    }
    return $thread;
}

/** @return list<int> */
function tiPostIds(Database $db, int $threadId): array
{
    return array_map(
        static fn (array $row): int => (int) $row['id'],
        $db->fetchAll(
            'SELECT id FROM posts WHERE thread_id = ? AND is_deleted = 0 AND is_pending = 0 ORDER BY id ASC',
            [$threadId],
        ),
    );
}

/** @return array<string,mixed> */
function tiEnsureThread(
    Config $config,
    Database $db,
    string $title,
    int $postCount,
    string $opening,
): array {
    $existing = $db->fetch('SELECT * FROM threads WHERE title = ? ORDER BY id ASC LIMIT 1', [$title]);
    if ($existing !== null) {
        return $existing;
    }

    $container = tiContainer($config, $db);
    /** @var UserRepository $users */
    $users = $container->get(UserRepository::class);
    $alice = $users->findByUsername('alice');
    if ($alice === null) {
        throw new RuntimeException('browser seed must create alice before Thread Intelligence fixtures');
    }
    $author = $users->findEntity((int) $alice['id']);
    $boardId = (int) $db->fetchValue("SELECT id FROM boards WHERE slug = 'general' LIMIT 1");
    if ($author === null || $boardId < 1) {
        throw new RuntimeException('browser seed must create #general before Thread Intelligence fixtures');
    }

    /** @var PostingService $posting */
    $posting = $container->get(PostingService::class);
    $created = $posting->createThread($author, [
        'board_id' => $boardId,
        'title' => $title,
        'body' => $opening,
    ]);
    for ($index = 1; $index < $postCount; $index++) {
        $body = $index === 1
            ? 'Public source ' . $index . ' contains a deliberately long value ' . str_repeat('sourcewrap', 28) . '.'
            : 'Public source ' . $index . ' records deterministic evidence for ' . $title . '.';
        $posting->reply($author, (int) $created['thread_id'], ['body' => $body]);
    }

    return tiThreadByTitle($db, $title);
}

function tiEnableFeatures(Database $db): void
{
    $settings = new SettingRepository($db);
    $features = $settings->get('features', []);
    if (!is_array($features)) {
        $features = [];
    }
    $features['community_memory'] = true;
    $features['automated_context'] = true;
    $settings->set('features', $features);
}

function tiResetGlobalState(Config $config, Database $db, bool $clearBudget = false): void
{
    tiEnableFeatures($db);
    $container = tiContainer($config, $db);
    /** @var ThreadIntelligenceSettings $settings */
    $settings = $container->get(ThreadIntelligenceSettings::class);
    $settings->setGenerationPaused(false);
    $settings->clearProviderBlock();
    if ($clearBudget) {
        $db->run("DELETE FROM settings WHERE `key` = 'thread_intelligence_daily_budget'");
    }
}

function tiQueueNow(Database $db, int $threadId, bool $initial): void
{
    $db->run(
        "UPDATE thread_intelligence_jobs
         SET due_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY)
         WHERE thread_id <> ? AND state IN ('queued', 'retry')",
        [$threadId],
    );
    $db->run(
        "INSERT INTO thread_intelligence_jobs
            (thread_id, state, trigger_code, due_at, attempt_count, last_processed_post_id,
             last_generated_at, automation_paused, activity_version, reconcile_required, created_at, updated_at)
         VALUES (?, 'queued', ?, UTC_TIMESTAMP(), 0, NULL, NULL, 0, 1, 1, UTC_TIMESTAMP(), UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE
            state = 'queued', trigger_code = VALUES(trigger_code), due_at = UTC_TIMESTAMP(),
            lease_token = NULL, lease_expires_at = NULL, attempt_count = 0, last_error_code = NULL,
            last_processed_post_id = CASE WHEN ? = 1 THEN NULL ELSE last_processed_post_id END,
            last_generated_at = CASE WHEN ? = 1 THEN NULL ELSE DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR) END,
            automation_paused = 0, paused_by = NULL, paused_at = NULL,
            activity_version = activity_version + 1, reconcile_required = 1, updated_at = UTC_TIMESTAMP()",
        [$threadId, $initial ? 'post_created' : 'curator_refresh', $initial ? 1 : 0, $initial ? 1 : 0],
    );
}

function tiProviderResult(Database $db, int $threadId, ?int $relatedThreadId, string $overview): ThreadIntelligenceResult
{
    $postIds = tiPostIds($db, $threadId);
    if (count($postIds) < 8) {
        throw new RuntimeException('real-worker fixture requires eight eligible posts');
    }
    $first = $postIds[0];
    $last = $postIds[array_key_last($postIds)];
    $related = $relatedThreadId === null ? [] : [[
        'thread_id' => $relatedThreadId,
        'explanation' => 'A related public discussion with a deliberately long explanation ' . str_repeat('relationshipwrap', 10),
    ]];
    return new ThreadIntelligenceResult([
        'overview' => [
            'markdown' => $overview,
            'source_post_ids' => $postIds,
        ],
        'key_points' => [
            ['markdown' => 'The opening evidence establishes the shared public context.', 'source_post_ids' => [$first]],
            ['markdown' => 'The newest evidence records the current supported outcome.', 'source_post_ids' => [$last]],
        ],
        'open_questions' => [
            ['markdown' => 'The community can continue documenting follow-up decisions.', 'source_post_ids' => [$last]],
        ],
        'related_topics' => $related,
    ], 'resp_browser_' . $threadId, ThreadIntelligenceResult::STATUS_COMPLETED, null,
        new ThreadIntelligenceUsage(480, 160, 24, 8));
}

function tiRunSuccess(
    Config $config,
    Database $db,
    int $threadId,
    ?int $relatedThreadId,
    string $overview,
    bool $initial,
): void {
    tiResetGlobalState($config, $db);
    tiQueueNow($db, $threadId, $initial);
    $provider = new FakeThreadIntelligenceProvider();
    $provider->queueResult(tiProviderResult($db, $threadId, $relatedThreadId, $overview));
    /** @var ThreadIntelligenceWorker $worker */
    $worker = tiContainer($config, $db, $provider)->get(ThreadIntelligenceWorker::class);
    $counts = $worker->run(1, 'browser-fixture-success');
    if ($counts !== ['processed' => 1, 'succeeded' => 1, 'failed' => 0] || $provider->callCount() !== 1) {
        throw new RuntimeException('real worker did not publish the deterministic browser fixture');
    }
}

function tiRunFailure(Config $config, Database $db, int $threadId, string $safeCode): void
{
    tiResetGlobalState($config, $db);
    tiQueueNow($db, $threadId, false);
    $provider = new FakeThreadIntelligenceProvider();
    $provider->queueException(new ThreadIntelligenceProviderException($safeCode));
    /** @var ThreadIntelligenceWorker $worker */
    $worker = tiContainer($config, $db, $provider)->get(ThreadIntelligenceWorker::class);
    $counts = $worker->run(1, 'browser-fixture-failure');
    if ($counts !== ['processed' => 1, 'succeeded' => 0, 'failed' => 1] || $provider->callCount() !== 1) {
        throw new RuntimeException('real worker did not record the deterministic browser failure');
    }
}

function tiDeterministicRelation(Database $db, int $sourceThreadId, int $targetThreadId): void
{
    $db->run(
        "INSERT INTO related_threads
            (source_thread_id, related_thread_id, relation_type, source, score, reason, status,
             curator_id, ai_generation_id, ai_reason, ai_selected, ai_selected_at, created_at)
         VALUES (?, ?, 'related', 'tag', 1, NULL, 'approved', NULL, NULL, NULL, 0, NULL, UTC_TIMESTAMP())
         ON DUPLICATE KEY UPDATE source = 'tag', status = 'approved', ai_generation_id = NULL,
            ai_reason = NULL, ai_selected = 0, ai_selected_at = NULL",
        [$sourceThreadId, $targetThreadId],
    );
}

function tiSeedProject(Config $config, Database $db, string $project): void
{
    $fallbackTarget = tiEnsureThread($config, $db, tiTitle('fallback_target', $project), 1, 'Deterministic fallback target.');
    $fallback = tiEnsureThread($config, $db, tiTitle('fallback', $project), 3, 'A short thread below the provider threshold.');
    tiDeterministicRelation($db, (int) $fallback['id'], (int) $fallbackTarget['id']);

    $briefTarget = tiEnsureThread($config, $db, tiTitle('brief_target', $project), 1, 'A public related topic used by the real worker.');
    $brief = tiEnsureThread($config, $db, tiTitle('brief', $project), 8, 'The community is documenting a stable browser-evidence workflow.');
    $lineageTarget = tiEnsureThread($config, $db, tiTitle('lineage_target', $project), 1, 'A public lineage target.');
    $lineage = tiEnsureThread($config, $db, tiTitle('lineage', $project), 8, 'Curators maintain a human baseline for future refreshes.');
    $lastGoodTarget = tiEnsureThread($config, $db, tiTitle('last_good_target', $project), 1, 'A last-good related target.');
    $lastGood = tiEnsureThread($config, $db, tiTitle('last_good', $project), 8, 'The current brief must survive a later provider failure.');
    $sourceTarget = tiEnsureThread($config, $db, tiTitle('source_target', $project), 1, 'A safe deterministic fallback after source invalidation.');
    $sourceInvalid = tiEnsureThread($config, $db, tiTitle('source_invalid', $project), 8, 'Every cited source must remain readable and public.');
    tiDeterministicRelation($db, (int) $sourceInvalid['id'], (int) $sourceTarget['id']);
    $budgetTarget = tiEnsureThread($config, $db, tiTitle('budget_target', $project), 1, 'A budget related target.');
    $budget = tiEnsureThread($config, $db, tiTitle('budget', $project), 8, 'The daily budget preserves the last published result.');
    $admin = tiEnsureThread($config, $db, tiTitle('admin', $project), 8, 'Administrators can recover review-required generation work.');

    // Successful publications always precede failure, latch, or budget states.
    tiRunSuccess($config, $db, (int) $brief['id'], (int) $briefTarget['id'],
        'The browser evidence confirms an attributed, source-bound Living Brief with safe related navigation.', true);
    tiRunSuccess($config, $db, (int) $lineage['id'], (int) $lineageTarget['id'],
        'The initial generated brief provides the baseline that a curator will edit.', true);
    tiRunSuccess($config, $db, (int) $lastGood['id'], (int) $lastGoodTarget['id'],
        'Last good brief remains published while later provider work fails safely.', true);
    tiRunSuccess($config, $db, (int) $sourceInvalid['id'], null,
        'This generated brief is visible only while every cited source remains readable.', true);
    tiRunSuccess($config, $db, (int) $budget['id'], (int) $budgetTarget['id'],
        'Budget guardrails retain this already published Living Brief.', true);

    $lineageProvider = new FakeThreadIntelligenceProvider();
    $lineageContainer = tiContainer($config, $db, $lineageProvider);
    /** @var UserRepository $users */
    $users = $lineageContainer->get(UserRepository::class);
    $alice = $users->findByUsername('alice');
    $actor = $alice === null ? null : $users->findEntity((int) $alice['id']);
    if ($actor === null) {
        throw new RuntimeException('missing curator fixture');
    }
    /** @var CommunityMemoryService $memory */
    $memory = $lineageContainer->get(CommunityMemoryService::class);
    $lineageSources = tiPostIds($db, (int) $lineage['id']);
    $memory->publishSummary($actor, (int) $lineage['id'], 'Human curator baseline retained for the next generated refresh.', [$lineageSources[0]]);
    tiRunSuccess($config, $db, (int) $lineage['id'], (int) $lineageTarget['id'],
        'Curator baseline carried forward through the real worker and retained as explicit lineage.', false);

    tiRunFailure($config, $db, (int) $lastGood['id'], ThreadIntelligenceFailureCode::TRANSPORT);
    tiRunFailure($config, $db, (int) $admin['id'], ThreadIntelligenceFailureCode::SCHEMA_INVALID);

    $publishedBudgetId = (int) $db->fetchValue(
        "SELECT id FROM thread_summaries WHERE thread_id = ? AND status = 'published' ORDER BY version DESC LIMIT 1",
        [(int) $budget['id']],
    );
    $generations = new ThreadIntelligenceGenerationRepository($db);
    $budgetAttempt = $generations->start([
        'thread_id' => (int) $budget['id'],
        'trigger_code' => 'curator_refresh',
        'baseline_summary_id' => $publishedBudgetId,
        'model' => 'gpt-5.6-luna',
        'reasoning_effort' => 'low',
        'prompt_version' => 'thread-intelligence-v1',
    ]);
    $generations->complete($budgetAttempt, [
        'status' => 'retry',
        'failure_code' => 'budget_exhausted',
        'failure_message' => 'budget_exhausted',
    ]);
    $db->run(
        "UPDATE thread_intelligence_jobs SET state = 'retry', due_at = DATE_ADD(UTC_TIMESTAMP(), INTERVAL 1 DAY),
            last_error_code = 'budget_exhausted', updated_at = UTC_TIMESTAMP() WHERE thread_id = ?",
        [(int) $budget['id']],
    );

    // Start source-invalid and prove the model fails closed; the browser test
    // restores it after its own assertion so desktop/mobile remain independent.
    $sourceId = (int) $db->fetchValue(
        'SELECT post_id FROM thread_summary_sources WHERE summary_id = (
            SELECT id FROM thread_summaries WHERE thread_id = ? AND status = \'published\' ORDER BY version DESC LIMIT 1
         ) ORDER BY post_id ASC LIMIT 1',
        [(int) $sourceInvalid['id']],
    );
    $db->run('UPDATE posts SET is_pending = 1 WHERE id = ?', [$sourceId]);
}

/** @return array<string,mixed> */
function tiState(Database $db, string $project): array
{
    $fallback = tiThreadByTitle($db, tiTitle('fallback', $project));
    $brief = tiThreadByTitle($db, tiTitle('brief', $project));
    $briefTarget = tiThreadByTitle($db, tiTitle('brief_target', $project));
    $lastGood = tiThreadByTitle($db, tiTitle('last_good', $project));
    $sourceInvalid = tiThreadByTitle($db, tiTitle('source_invalid', $project));
    $sourceId = (int) $db->fetchValue(
        'SELECT post_id FROM thread_summary_sources WHERE summary_id = (
            SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY version ASC, id ASC LIMIT 1
         ) ORDER BY post_id ASC LIMIT 1',
        [(int) $sourceInvalid['id']],
    );
    $briefSourceId = (int) $db->fetchValue(
        'SELECT post_id FROM thread_summary_sources WHERE summary_id = (
            SELECT id FROM thread_summaries WHERE thread_id = ? ORDER BY version ASC, id ASC LIMIT 1
         ) ORDER BY post_id ASC LIMIT 1',
        [(int) $brief['id']],
    );

    return [
        'fallback' => ['path' => '/t/' . (int) $fallback['id'] . '-' . $fallback['slug']],
        'brief' => [
            'path' => '/t/' . (int) $brief['id'] . '-' . $brief['slug'],
            'source_id' => $briefSourceId,
            'source_path' => '/t/' . (int) $brief['id'] . '-' . $brief['slug'] . '#p' . $briefSourceId,
            'related_path' => '/t/' . (int) $briefTarget['id'] . '-' . $briefTarget['slug'],
        ],
        'last_good' => ['path' => '/t/' . (int) $lastGood['id'] . '-' . $lastGood['slug']],
        'source_invalid' => [
            'path' => '/t/' . (int) $sourceInvalid['id'] . '-' . $sourceInvalid['slug'],
            'source_id' => $sourceId,
        ],
        'admin' => ['thread_title' => tiTitle('admin', $project)],
    ];
}

function tiResetBrief(Config $config, Database $db, string $project): void
{
    tiResetGlobalState($config, $db, true);
    $thread = tiThreadByTitle($db, tiTitle('brief', $project));
    $initial = (int) $db->fetchValue(
        "SELECT id FROM thread_summaries WHERE thread_id = ? AND kind = 'ai' ORDER BY version ASC, id ASC LIMIT 1",
        [(int) $thread['id']],
    );
    $db->run("UPDATE thread_summaries SET status = 'retired', retired_at = UTC_TIMESTAMP() WHERE thread_id = ?", [(int) $thread['id']]);
    $db->run("UPDATE thread_summaries SET status = 'published', retired_at = NULL, published_at = UTC_TIMESTAMP() WHERE id = ?", [$initial]);
    $db->run(
        "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, lease_token = NULL,
            lease_expires_at = NULL, last_generated_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR),
            automation_paused = 0, paused_by = NULL, paused_at = NULL, reconcile_required = 0,
            updated_at = UTC_TIMESTAMP() WHERE thread_id = ?",
        [(int) $thread['id']],
    );
}

function tiPrepareRefresh(Config $config, Database $db, string $project): void
{
    tiResetGlobalState($config, $db, true);
    $thread = tiThreadByTitle($db, tiTitle('brief', $project));
    $db->run(
        "UPDATE thread_intelligence_jobs SET state = 'idle', due_at = NULL, lease_token = NULL,
            lease_expires_at = NULL, last_generated_at = DATE_SUB(UTC_TIMESTAMP(), INTERVAL 2 HOUR),
            automation_paused = 0, paused_by = NULL, paused_at = NULL, reconcile_required = 1,
            updated_at = UTC_TIMESTAMP() WHERE thread_id = ?",
        [(int) $thread['id']],
    );
}

function tiRunRefresh(Config $config, Database $db, string $project): void
{
    $thread = tiThreadByTitle($db, tiTitle('brief', $project));
    $target = tiThreadByTitle($db, tiTitle('brief_target', $project));
    tiRunSuccess($config, $db, (int) $thread['id'], (int) $target['id'],
        'Curator baseline carried forward through the real worker with fresh public provenance.', false);
}

function tiSetSourcePending(Database $db, string $project, bool $pending): void
{
    $state = tiState($db, $project);
    $sourceId = (int) $state['source_invalid']['source_id'];
    $db->run('UPDATE posts SET is_pending = ? WHERE id = ?', [$pending ? 1 : 0, $sourceId]);
}

function tiSetBudget(Database $db, bool $exhausted): void
{
    if (!$exhausted) {
        $db->run("DELETE FROM settings WHERE `key` = 'thread_intelligence_daily_budget'");
        return;
    }
    (new SettingRepository($db))->set('thread_intelligence_daily_budget', [
        'date' => gmdate('Y-m-d'),
        'reserved_calls' => 0,
        'used_calls' => 100,
        'reserved_input_tokens' => 0,
        'used_input_tokens' => 0,
    ]);
}

function tiResetAdmin(Config $config, Database $db, string $project): void
{
    tiResetGlobalState($config, $db, true);
    $thread = tiThreadByTitle($db, tiTitle('admin', $project));
    $db->run(
        "UPDATE thread_intelligence_jobs SET state = 'review_required', due_at = NULL,
            lease_token = NULL, lease_expires_at = NULL, attempt_count = 3,
            last_error_code = 'schema_invalid', last_generated_at = NULL,
            automation_paused = 0, paused_by = NULL, paused_at = NULL,
            reconcile_required = 0, updated_at = UTC_TIMESTAMP() WHERE thread_id = ?",
        [(int) $thread['id']],
    );
}

switch ($action) {
    case 'seed':
        tiEnableFeatures($db);
        foreach (TI_FIXTURE_PROJECTS as $fixtureProject) {
            if ($db->fetchValue('SELECT 1 FROM threads WHERE title = ? LIMIT 1', [tiTitle('brief', $fixtureProject)]) === false) {
                tiSeedProject($config, $db, $fixtureProject);
            }
        }
        tiResetGlobalState($config, $db);
        break;
    case 'reset-brief':
        tiResetBrief($config, $db, $project);
        break;
    case 'prepare-refresh':
        tiPrepareRefresh($config, $db, $project);
        break;
    case 'run-refresh':
        tiRunRefresh($config, $db, $project);
        break;
    case 'reset-guardrails':
        tiResetGlobalState($config, $db, true);
        tiSetBudget($db, true);
        break;
    case 'restore-guardrails':
        tiResetGlobalState($config, $db, true);
        break;
    case 'invalidate-source':
        tiSetSourcePending($db, $project, true);
        break;
    case 'restore-source':
        tiSetSourcePending($db, $project, false);
        break;
    case 'reset-admin':
        tiResetAdmin($config, $db, $project);
        break;
    case 'latch-provider':
        tiResetGlobalState($config, $db, true);
        /** @var ThreadIntelligenceSettings $settings */
        $settings = tiContainer($config, $db)->get(ThreadIntelligenceSettings::class);
        $settings->blockProvider(ThreadIntelligenceFailureCode::AUTHENTICATION, new DateTimeImmutable('now', new DateTimeZone('UTC')));
        break;
    case 'exhaust-budget':
        tiResetGlobalState($config, $db, true);
        tiSetBudget($db, true);
        break;
    case 'show':
        break;
    default:
        throw new InvalidArgumentException('unknown fixture action: ' . $action);
}

if (!$embedded) {
    fwrite(STDOUT, json_encode(tiState($db, $project), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . PHP_EOL);
}
