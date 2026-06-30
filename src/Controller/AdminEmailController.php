<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Mail\Mailer;
use App\Repository\EmailDeliveryRepository;
use App\Repository\EmailSuppressionRepository;
use App\Service\EmailDomainVerifier;
use App\Service\EmailOpsService;
use App\Service\RateLimitService;

/** Admin email delivery operations dashboard (ADMIN §7.5/§7.6/§10.1), gated by the `email` flag. */
final class AdminEmailController extends Controller
{
    private const STATUSES = ['queued', 'sent', 'bounced', 'complained', 'suppressed', 'failed'];
    private const KINDS = ['instant', 'digest', 'test', 'system'];

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('email')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $status = $this->oneOf($request->str('status'), self::STATUSES);
        $kind = $this->oneOf($request->str('kind'), self::KINDS);
        $email = trim($request->str('email'));
        $page = max(1, $request->int('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;

        $deliveries = $this->container->get(EmailDeliveryRepository::class);
        $suppress = $this->container->get(EmailSuppressionRepository::class);
        $mailer = $this->container->get(Mailer::class);
        $domain = $this->container->get(EmailDomainVerifier::class)->current();

        return $this->view('admin/email', [
            'deliveries' => $deliveries->recent($perPage, $offset, $status, $kind, $email !== '' ? $email : null),
            'total' => $deliveries->count($status, $kind, $email !== '' ? $email : null),
            'status_counts' => $deliveries->statusCounts(),
            'suppressions' => $suppress->list(100, 0, null),
            'suppression_count' => $suppress->count(null),
            'mailer_configured' => $mailer->isConfigured(),
            'mail_from' => (string) $this->config()->get('mail.from', ''),
            'domain_status' => $domain,
            'send_blocked' => !empty($domain['required']) && empty($domain['allowed']),
            'f_status' => $status ?? '',
            'f_kind' => $kind ?? '',
            'f_email' => $email,
            'page' => $page,
        ]);
    }

    /** @param array<string,string> $params */
    public function test(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $this->container->get(RateLimitService::class)->enforce('email_test', $request, $admin);
        try {
            $this->container->get(EmailOpsService::class)->sendTest($admin);
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Test email sent to ' . $admin->email() . '.');
    }

    /** @param array<string,string> $params */
    public function suppress(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        try {
            $this->container->get(EmailOpsService::class)->manualSuppress($admin, $request->str('email'));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Address added to the suppression list.');
    }

    /** @param array<string,string> $params */
    public function unsuppress(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        try {
            $this->container->get(EmailOpsService::class)->unsuppress($admin, $request->str('email'));
        } catch (ValidationException $e) {
            return $this->redirectWithFlash('/admin/email', $e->first());
        }
        return $this->redirectWithFlash('/admin/email', 'Address removed from the suppression list.');
    }

    /** @param array<string,string> $params */
    public function requeue(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        $this->container->get(EmailOpsService::class)->requeueFailed($admin, (int) ($params['id'] ?? 0));

        return $this->redirectWithFlash('/admin/email?status=failed', 'Failed delivery requeued.');
    }

    /** @param array<string,string> $params */
    public function verifyDomain(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        $status = $this->container->get(EmailDomainVerifier::class)->verify();
        $this->container->get(\App\Repository\ModerationLogRepository::class)->log([
            'actor_id' => $admin->id(),
            'action' => 'email_domain_verified',
            'target_type' => 'setting',
            'target_id' => 0,
            'after' => [
                'domain' => $status['domain'] ?? '',
                'spf_status' => $status['spf_status'] ?? 'unknown',
                'dkim_status' => $status['dkim_status'] ?? 'unknown',
            ],
        ]);

        return $this->redirectWithFlash('/admin/email', 'Email domain status refreshed.');
    }

    /** Read-only CSV export of the (filtered) delivery log. GET → no CSRF. @param array<string,string> $params */
    public function export(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        $status = $this->oneOf($request->str('status'), self::STATUSES);
        $kind = $this->oneOf($request->str('kind'), self::KINDS);
        $email = trim($request->str('email'));

        $rows = $this->container->get(EmailDeliveryRepository::class)
            ->recent(10000, 0, $status, $kind, $email !== '' ? $email : null);

        $fh = fopen('php://temp', 'r+');
        // Pass $escape explicitly: PHP 8.4+ deprecates the implicit default and
        // an empty escape string is the forward-compatible (PHP 9) behaviour.
        fputcsv($fh, ['id', 'created_at', 'kind', 'status', 'email', 'subject', 'message_id', 'error', 'sent_at'], escape: '');
        foreach ($rows as $r) {
            fputcsv($fh, [
                (int) $r['id'],
                (string) $r['created_at'],
                (string) $r['kind'],
                (string) $r['status'],
                (string) $r['email'],
                (string) ($r['subject'] ?? ''),
                (string) ($r['message_id'] ?? ''),
                (string) ($r['error'] ?? ''),
                (string) ($r['sent_at'] ?? ''),
            ], escape: '');
        }
        rewind($fh);
        $csv = (string) stream_get_contents($fh);
        fclose($fh);

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="retroboards-email-deliveries.csv"',
        ]);
    }

    /**
     * @param list<string> $allowed
     */
    private function oneOf(string $value, array $allowed): ?string
    {
        $value = trim($value);
        return in_array($value, $allowed, true) ? $value : null;
    }
}
