<?php

declare(strict_types=1);

/** Disposable fixture for the actual worker CLI rehearsal. Never uses a real transport. */
use App\Core\Config;
use App\Core\Database;
use App\Core\Env;
use App\Core\Migrator;
use App\Repository\AccountDeletionRepository;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\ThreadRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Security\PasswordHasher;
use App\Security\WriteGate;
use App\Service\PostingService;
use App\Support\HtmlSanitizer;
use App\Support\Markdown;

$root = dirname(__DIR__, 2);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = Config::fromFile($root . '/config/config.php');
if (getenv('APP_ENV') !== 'test' || getenv('MAIL_DRIVER') !== 'array'
    || getenv('MAIL_FROM') !== 'notification-evidence@example.test'
    || !preg_match('/^retroboards_unified_workers(?:_[a-z0-9]+)*$/', (string) $config->get('db.database'))) {
    throw new RuntimeException('Worker rehearsal requires its disposable database and the pinned capture transport.');
}
$db = new Database($config->get('db'));
$command = $argv[1] ?? '';
if (!in_array($command, ['seed', 'inspect', 'verify'], true)) {
    throw new RuntimeException('Choose seed, inspect or verify. Seed resets the disposable schema.');
}

if ($command === 'seed') {
    (new Migrator($db->pdo(), $root . '/database/migrations'))->fresh();
    $settings = new SettingRepository($db);
    $settings->set('site_name', 'Notification worker rehearsal');
    $settings->set('email_require_verified_domain', false);
    $settings->set('features', ['notifications' => true, 'email' => true, 'saved_feeds' => true]);
    PasswordHasher::setDefaultOptions(['memory_cost' => 8, 'time_cost' => 1, 'threads' => 1]);
    $hash = (new PasswordHasher())->hash('rehearsal-password123');
    $users = new UserRepository($db);
    $ids = [];
    foreach (['author', 'null-zone', 'queued', 'purged', 'legacy', 'paused', 'suppressed', 'banned', 'saved-feed'] as $name) {
        $ids[$name] = $users->create([
            'username' => 'worker-' . $name, 'email' => 'worker-' . $name . '@example.test',
            'display_name' => 'Worker ' . $name, 'password_hash' => $hash,
            'role' => $name === 'author' ? 'admin' : 'user', 'status' => 'active',
        ]);
    }
    $boards = new BoardRepository($db);
    $boardId = $boards->create([
        'category_id' => (new CategoryRepository($db))->create('Worker evidence'),
        'name' => 'Worker evidence board', 'slug' => 'worker-evidence', 'visibility' => 'public',
    ]);
    $posting = new PostingService(
        $db, new ThreadRepository($db), new PostRepository($db), $boards, $users,
        new Markdown(new HtmlSanitizer()), new WriteGate(), new BoardPolicy(), $config,
    );
    $thread = $posting->createThread($users->findEntity($ids['author']), [
        'board_id' => $boardId, 'title' => 'Eligible worker activity',
        'body' => 'Only the capture mailer may receive this rehearsal activity.',
    ]);
    $threadId = (int) $thread['thread_id'];
    $postId = (int) $db->fetchValue('SELECT id FROM posts WHERE thread_id = ? ORDER BY id DESC LIMIT 1', [$threadId]);
    $end = gmdate('Y-m-d H:i:s');
    $db->run('UPDATE posts SET created_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s', time() - 60), $postId]);
    $payload = [
        'version' => 1, 'window_start_utc' => gmdate('Y-m-d H:i:s', time() - 3600),
        'window_end_utc' => $end, 'max_post_id' => $postId,
        'sources' => ['subscriptions' => [['target_type' => 'thread', 'target_id' => $threadId]], 'saved_feeds' => []],
    ];
    $subscriptions = new SubscriptionRepository($db);
    $deliveries = new EmailDeliveryRepository($db);
    foreach ($ids as $name => $uid) {
        if ($name === 'author') {
            continue;
        }
        $db->run('UPDATE users SET digest_hour = 0, timezone = ?, last_daily_digest_at = ? WHERE id = ?', [
            $name === 'null-zone' ? null : 'UTC',
            in_array($name, ['null-zone', 'saved-feed', 'paused', 'suppressed', 'banned'], true) ? null : $end,
            $uid,
        ]);
        if ($name !== 'saved-feed') {
            $subscriptions->set($uid, 'thread', $threadId, true, true, 'daily');
        }
        if (in_array($name, ['queued', 'purged', 'paused', 'suppressed', 'banned'], true)) {
            // Restricted recipients carry an older retry as today's due window is consumed.
            $keyDate = in_array($name, ['paused', 'suppressed', 'banned'], true)
                ? gmdate('Y-m-d', time() - 86400) : gmdate('Y-m-d');
            $deliveries->enqueue($uid, 'worker-' . $name . '@example.test', 'digest', 'Rehearsal ' . $name,
                'digest:' . $uid . ':' . $keyDate, $payload);
        }
    }
    (new UserPreferenceRepository($db))->merge($ids['paused'], ['pause_all_email' => true]);
    (new EmailSuppressionRepository($db))->suppress('worker-suppressed@example.test', 'manual');
    $db->run("UPDATE users SET status = 'banned' WHERE id = ?", [$ids['banned']]);
    $db->run("UPDATE users SET status = 'pending_deletion' WHERE id = ?", [$ids['purged']]);
    (new AccountDeletionRepository($db))->create($ids['purged'], $ids['purged'], gmdate('Y-m-d H:i:s', time() - 60), 'worker_rehearsal');
    $deliveries->enqueue($ids['legacy'], 'worker-legacy@example.test', 'digest', 'Rehearsal legacy', null, null);
    // One old queued row is classified by the drainer; one historical failed row stays terminal.
    $failed = $deliveries->enqueue($ids['legacy'], 'worker-legacy@example.test', 'digest', 'Rehearsal legacy failed', null, null);
    $db->run("UPDATE email_deliveries SET status = 'failed', error = 'unreplayable_legacy_digest' WHERE id = ?", [$failed]);
    $db->run('INSERT INTO saved_feed_filters (user_id, name, filter_json, digest_enabled) VALUES (?, ?, ?, 1)', [
        $ids['saved-feed'], 'Worker saved feed', json_encode(['board_ids' => [$boardId], 'sort' => 'latest'], JSON_THROW_ON_ERROR),
    ]);
    // The operator test-send producer stores the requesting admin as recipient.
    $deliveries->enqueue($ids['author'], 'worker-author@example.test', 'test', 'Rehearsal operator test');
}

$rows = $db->fetchAll('SELECT id, user_id, kind, subject, status, error, attempt_count, sent_at, message_id FROM email_deliveries ORDER BY id');
$members = $db->fetchAll("SELECT id, username, status, timezone, last_daily_digest_at FROM users WHERE username LIKE 'worker-%' ORDER BY id");
$checks = [];
if ($command === 'verify') {
    $named = [];
    foreach ($members as $member) {
        $named[substr($member['username'], strlen('worker-'))] = $member;
    }
    foreach (['null-zone', 'queued', 'saved-feed'] as $name) {
        $jobs = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === (int) $named[$name]['id']));
        $checks[$name . '_one_sent'] = count($jobs) === 1 && $jobs[0]['status'] === 'sent'
            && (int) $jobs[0]['attempt_count'] === 1 && $jobs[0]['sent_at'] !== null && $jobs[0]['message_id'] !== null;
    }
    foreach (['paused' => 'recipient_paused', 'suppressed' => 'address_suppressed', 'banned' => 'recipient_banned'] as $name => $reason) {
        $jobs = array_values(array_filter($rows, static fn (array $row): bool => (int) ($row['user_id'] ?? 0) === (int) $named[$name]['id']));
        $checks[$name . '_terminal'] = count($jobs) === 1 && $jobs[0]['status'] === 'suppressed'
            && $jobs[0]['error'] === $reason && (int) $jobs[0]['attempt_count'] === 0
            && $jobs[0]['sent_at'] === null && $jobs[0]['message_id'] === null
            && $named[$name]['last_daily_digest_at'] !== null;
    }
    $purged = array_values(array_filter($rows, static fn (array $row): bool => $row['subject'] === 'Rehearsal purged'));
    $checks['purged_recipient_drain_continues'] = count($purged) === 1 && $purged[0]['user_id'] === null
        && $purged[0]['status'] === 'suppressed' && $purged[0]['error'] === 'recipient_missing';
    $legacy = array_values(array_filter($rows, static fn (array $row): bool => str_starts_with((string) $row['subject'], 'Rehearsal legacy')));
    $checks['legacy_digest_permanent'] = count($legacy) === 2 && count(array_filter($legacy,
        static fn (array $row): bool => $row['status'] === 'failed' && $row['error'] === 'unreplayable_legacy_digest' && $row['sent_at'] === null)) === 2;
    $tests = array_values(array_filter($rows, static fn (array $row): bool => $row['kind'] === 'test'));
    $checks['operator_test_explicit_kind'] = count($tests) === 1 && $tests[0]['status'] === 'sent';
    $checks['no_queued_rows'] = count(array_filter($rows, static fn (array $row): bool => $row['status'] === 'queued')) === 0;
}
echo json_encode(['database' => $config->get('db.database'), 'command' => $command, 'checks' => $checks, 'deliveries' => $rows, 'members' => $members], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
if ($command === 'verify' && in_array(false, $checks, true)) {
    exit(1);
}
