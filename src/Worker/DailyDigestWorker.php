<?php

declare(strict_types=1);

namespace App\Worker;

use App\Core\Config;
use App\Core\Database;
use App\Domain\User;
use App\Mail\Mailer;
use App\Repository\DigestActivityRepository;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Repository\PostRepository;
use App\Repository\SettingRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Service\DigestService;
use App\Service\EmailPreferenceService;
use App\Service\EmailDomainVerifier;
use App\Service\NotificationVisibilityService;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

/** Atomically consume each due local-date window and persist a replayable job. */
final class DailyDigestWorker
{
    public function __construct(
        private Database $db,
        private EmailDeliveryRepository $deliveries,
        private EmailSuppressionRepository $suppress,
        private Mailer $mailer,
        private Config $config,
        private ?SettingRepository $settings = null,
        private ?EmailDomainVerifier $domainVerifier = null,
        private ?EmailPreferenceService $emailPrefs = null,
        private ?NotificationVisibilityService $visibility = null,
        private ?DigestService $digests = null,
        private ?NotificationEmailWorker $drainer = null,
    ) {
        $this->visibility ??= new NotificationVisibilityService($db);
        $this->emailPrefs ??= new EmailPreferenceService(new UserPreferenceRepository($db));
        $this->digests ??= new DigestService(new DigestActivityRepository($db), $this->visibility, $config, $settings);
        $this->drainer ??= new NotificationEmailWorker($deliveries, $suppress, new PostRepository($db),
            new UserRepository($db), $mailer, $config, $settings, $domainVerifier, $this->emailPrefs, $this->visibility, $this->digests);
    }

    public function run(string $nowUtc): array
    {
        $stats = ['queued' => 0, 'skipped_empty' => 0, 'suppressed' => 0, 'invalid_timezone' => 0];
        $now = new DateTimeImmutable($nowUtc, new DateTimeZone('UTC'));
        $nowUtc = $now->format('Y-m-d H:i:s');
        $users = $this->db->fetchAll("SELECT id FROM users WHERE digest_hour IS NOT NULL AND status <> 'deleted'");
        foreach ($users as $candidate) {
            $outcome = $this->db->transaction(function () use ($candidate, $now, $nowUtc): ?string {
                $u = $this->db->fetch('SELECT * FROM users WHERE id = ? FOR UPDATE', [$candidate['id']]);
                if ($u === null || $u['digest_hour'] === null || $u['status'] === 'deleted') { return null; }
                try {
                    $zone = new DateTimeZone((string) ($u['timezone'] ?? '') ?: 'UTC');
                } catch (Throwable) {
                    return 'invalid_timezone';
                }
                $local = $now->setTimezone($zone);
                if ((int) $local->format('G') < (int) $u['digest_hour']) { return null; }
                $last = $u['last_daily_digest_at'];
                if ($last !== null) {
                    // Never reverse UTC bounds, including after timezone changes.
                    if ($last >= $nowUtc) { return null; }
                    $lastLocal = (new DateTimeImmutable($last, new DateTimeZone('UTC')))->setTimezone($zone);
                    if ($lastLocal->format('Y-m-d') === $local->format('Y-m-d')) { return null; }
                }
                $key = 'digest:' . $u['id'] . ':' . $local->format('Y-m-d');
                if ($this->db->fetch('SELECT id FROM email_deliveries WHERE idempotency_key = ?', [$key]) !== null) { return null; }
                $viewer = User::fromRow($u);
                $outcome = 'suppressed';
                if (in_array($u['status'], ['active', 'suspended', 'deactivated', 'pending_deletion'], true)
                    && !$this->emailPrefs->pauseAllEmail($viewer->id()) && !$this->suppress->isSuppressed($viewer->email())) {
                    $from = $last ?? $now->modify('-1 day')->format('Y-m-d H:i:s');
                    $maxId = (int) $this->db->fetchValue('SELECT COALESCE(MAX(id), 0) FROM posts');
                    $payload = $this->digests->snapshot($viewer, $from, $nowUtc, $maxId);
                    $rendered = $this->digests->render($viewer, $payload);
                    $outcome = 'skipped_empty';
                    if ($rendered !== null) {
                        $id = $this->deliveries->enqueue($viewer->id(), $viewer->email(), 'digest', $rendered['subject'], $key, $payload);
                        if ($id === 0) { throw new \RuntimeException('Digest enqueue did not persist the due window.'); }
                        $outcome = 'queued';
                    }
                }
                $this->db->run('UPDATE users SET last_daily_digest_at = ? WHERE id = ?', [$nowUtc, $viewer->id()]);
                return $outcome;
            });
            if ($outcome !== null) { $stats[$outcome]++; }
        }
        // Drain retries even when no new recipient is due. Transport guards run only here.
        $drained = $this->drainer->run(100, 'digest');
        $stats['suppressed'] += $drained['suppressed'];
        unset($drained['suppressed']);
        return $stats + $drained;
    }
}
