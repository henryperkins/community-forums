<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Email delivery');
$this->section('variant', 'admin');
$pagerBase = array_filter([
    'status' => $f_status ?? '',
    'kind' => $f_kind ?? '',
    'email' => $f_email ?? '',
], static fn ($v): bool => $v !== '');
$page = max(1, (int) ($page ?? 1));
$totalPages = max(1, (int) ($total_pages ?? 1));
$statusOptions = is_array($status_options ?? null) ? $status_options : [];
$kindOptions = is_array($kind_options ?? null) ? $kind_options : [];
$domain = is_array($domain_status ?? null) ? $domain_status : [];
$reasonLabels = [
    'manual' => 'Added by an operator',
    'unsubscribe' => 'Unsubscribed by the member',
];
$domainStatusClass = static fn (string $status): string => match ($status) {
    'pass' => 'is-pass',
    'fail' => 'is-fail',
    default => 'is-unknown',
};
$flashMessage = trim((string) ($flash ?? ''));
$testStatus = str_starts_with($flashMessage, 'Test email sent to ') ? $flashMessage : '';
$testError = $flashMessage === 'Configure your sending domain first.'
    || $flashMessage === 'Email sending is blocked until SPF and DKIM pass.'
    || str_starts_with($flashMessage, 'Test send failed:')
        ? $flashMessage
        : '';
$testMessage = $testStatus !== '' ? $testStatus : $testError;
$unplacedFlash = $testMessage === '' ? $flashMessage : '';
?>
<?= $this->partial('admin/_console', [
    'area' => 'notifications',
    'tab' => 'email',
    'pane_class' => 'admin-notifications admin-notifications-email',
    'defer_flash' => true,
]) ?>
    <?php if ($unplacedFlash !== ''): ?>
        <?= $this->partial('partials/flash', ['flash' => $unplacedFlash]) ?>
    <?php endif; ?>

    <?php // F24: one status line per independent fact — transport, From, domain.
          // This ADR-locked block stays above the domain card and may not collapse
          // into one optimistic "configured" claim. ?>
    <?php if (empty($mailer_configured)): ?>
        <div class="flash" role="alert"><strong>Email is not ready to send.</strong> Outbound messages are skipped until the transport is configured — see the status facts below.</div>
    <?php endif; ?>
    <ul class="email-status-facts" aria-label="Email readiness">
        <li>
            <strong>Transport:</strong>
            <span><?= empty($mailer_configured) ? 'not configured — outbound email is skipped' : 'configured — the delivery worker drains queued mail' ?></span>
        </li>
        <li>
            <strong>From address:</strong>
            <span><?php if (($mail_from ?? '') !== ''): ?><code><?= $e($mail_from) ?></code><?php else: ?>not set — email fails closed (messages are skipped) until a From address is configured<?php endif; ?></span>
        </li>
        <li>
            <strong>Sending domain:</strong>
            <span>
                <?php if (($domain['domain'] ?? '') === ''): ?>
                    none — derived from the From address once it is set
                <?php elseif (!empty($send_blocked)): ?>
                    <code><?= $e((string) $domain['domain']) ?></code> — blocked until SPF and DKIM pass
                <?php elseif (($domain['spf_status'] ?? '') === 'pass' && ($domain['dkim_status'] ?? '') === 'pass'): ?>
                    <code><?= $e((string) $domain['domain']) ?></code> — verified (SPF and DKIM pass)
                <?php else: ?>
                    <code><?= $e((string) $domain['domain']) ?></code> — unverified; send blocking is off (ADR 0008 opt-in)
                <?php endif; ?>
            </span>
        </li>
    </ul>
    <?php if (!empty($send_blocked)): ?>
        <div class="flash" role="alert">
            <strong>Email sending is blocked until SPF and DKIM pass.</strong>
            Refresh the sending-domain status after DNS records are published.
        </div>
    <?php endif; ?>

    <div class="notification-top-grid">
        <section class="card notification-card notification-domain-card">
            <h2>Sending domain</h2>
            <?php if (($domain['domain'] ?? '') === ''): ?>
                <p class="notification-empty-copy">Set a From address before verifying SPF and DKIM.</p>
            <?php else: ?>
                <p class="notification-domain-name">
                    <strong><?= $e((string) $domain['domain']) ?></strong>
                    <span>selector <code><?= $e((string) ($domain['dkim_selector'] ?? 'default')) ?></code></span>
                </p>
                <p class="notification-domain-statuses">
                    <?php foreach (['SPF' => 'spf_status', 'DKIM' => 'dkim_status'] as $label => $key): ?>
                        <?php $status = (string) ($domain[$key] ?? 'unknown'); ?>
                        <span class="email-domain-chip <?= $domainStatusClass($status) ?>"><?= $e($label) ?> <?= $e($status) ?></span>
                    <?php endforeach; ?>
                    <?php if (!empty($domain['checked_at'])): ?>
                        <span class="notification-domain-checked">checked <?= $e(human_datetime((string) $domain['checked_at'])) ?></span>
                    <?php endif; ?>
                </p>
                <div class="notification-domain-actions">
                    <form method="post" action="/admin/email/domain/verify" class="inline-form">
                        <?= $this->csrfField() ?>
                        <button class="btn btn-secondary" type="submit">Refresh SPF/DKIM status</button>
                    </form>
                    <span>Verified-domain send blocking is <?= !empty($domain['required']) ? 'enabled' : 'disabled' ?>.</span>
                </div>
            <?php endif; ?>
        </section>

        <section class="card notification-card notification-test-card">
            <h2>Send a test email</h2>
            <p>Sends a one-off message to your own account address and records it in the log below.</p>
            <div class="notification-test-actions">
                <form method="post" action="/admin/email/test" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button class="btn" type="submit"<?= empty($mailer_configured) ? ' disabled' : '' ?>>Send test email</button>
                </form>
                <?php if ($testMessage !== ''): ?>
                    <span class="notification-inline-status<?= $testError !== '' ? ' is-error' : '' ?>" role="<?= $testError !== '' ? 'alert' : 'status' ?>"><?= $e($testMessage) ?></span>
                <?php endif; ?>
            </div>
        </section>
    </div>

    <section class="notification-queue-section">
        <h2>Queue status</h2>
        <ul class="stat-cards notification-stat-cards">
            <?php foreach (['queued', 'sent', 'failed', 'suppressed', 'bounced', 'complained'] as $status): ?>
                <li class="stat-card"><span class="stat-num"><?= (int) ($status_counts[$status] ?? 0) ?></span><span class="stat-label"><?= $e($status) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card notification-card notification-delivery-card">
        <h2>Delivery log</h2>
        <form method="get" action="/admin/email" class="notification-filter-form">
            <label class="notification-filter-field">
                <span>Status</span>
                <select name="status" class="input">
                    <option value="">Any</option>
                    <?php foreach ($statusOptions as $status): ?>
                        <option value="<?= $e((string) $status) ?>"<?= ($f_status ?? '') === $status ? ' selected' : '' ?>><?= $e((string) $status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="notification-filter-field">
                <span>Kind</span>
                <select name="kind" class="input">
                    <option value="">Any</option>
                    <?php foreach ($kindOptions as $kind): ?>
                        <option value="<?= $e((string) $kind) ?>"<?= ($f_kind ?? '') === $kind ? ' selected' : '' ?>><?= $e((string) $kind) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="notification-filter-field notification-filter-email">
                <span>Email</span>
                <input type="text" name="email" class="input" placeholder="address@example.com" value="<?= $e($f_email ?? '') ?>">
            </label>
            <button class="btn" type="submit">Filter</button>
            <a class="btn btn-secondary" href="/admin/email">Reset</a>
            <a class="btn btn-secondary" href="/admin/email/export<?= $pagerBase !== [] ? '?' . $e(http_build_query($pagerBase)) : '' ?>">Download CSV</a>
            <span class="notification-result-count"><?= (int) ($total ?? 0) === 1 ? '1 message' : (int) ($total ?? 0) . ' messages' ?></span>
        </form>

        <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="Email delivery log">
            <table class="audit notification-delivery-table">
                <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col" class="numeric">Attempts</th><th scope="col">Subject</th><th scope="col">Detail</th><th scope="col" class="action-cell">Action</th></tr></thead>
                <tbody>
                <?php foreach (($deliveries ?? []) as $delivery): ?>
                    <tr>
                        <td class="mono"><?= $e(human_datetime($delivery['created_at'])) ?></td>
                        <td class="notification-delivery-email"><?= $e($delivery['email']) ?></td>
                        <td><code><?= $e($delivery['kind']) ?></code></td>
                        <td><span class="state state-<?= $e((string) $delivery['status']) ?>"><?= $e(ucfirst((string) $delivery['status'])) ?></span></td>
                        <td class="numeric notification-attempts">
                            <?= (int) ($delivery['attempt_count'] ?? 0) ?> / <?= (int) ($delivery['max_attempts'] ?? 1) ?>
                            <?php if (!empty($delivery['next_attempt_at'])): ?>
                                <span>Next retry <?= $e(human_datetime((string) $delivery['next_attempt_at'])) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="notification-subject"><?= $e((string) ($delivery['subject'] ?? '')) ?></td>
                        <td class="notification-detail"><?= $e((string) ($delivery['error'] ?? $delivery['message_id'] ?? '')) ?></td>
                        <td class="action-cell">
                            <?php if (($delivery['status'] ?? '') === 'failed'): ?>
                                <form method="post" action="/admin/email/deliveries/<?= (int) $delivery['id'] ?>/requeue" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <button class="btn btn-secondary" type="submit">Requeue</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($deliveries ?? [])): ?>
            <?= $this->partial('partials/empty_state', [
                'heading' => 'Nothing matches these filters',
                'message' => 'Widen the filters to see more of the log.',
            ]) ?>
        <?php endif; ?>
        <?php if ($totalPages > 1 && $page <= $totalPages): ?>
            <?= $this->partial('partials/pager', [
                'path' => '/admin/email',
                'query' => $pagerBase,
                'page' => $page,
                'total_pages' => $totalPages,
                'aria_label' => 'Email delivery pages',
            ]) ?>
        <?php endif; ?>
    </section>

    <section class="card notification-card notification-suppressions-card">
        <h2>Suppressed addresses</h2>
        <form method="post" action="/admin/email/suppressions" class="notification-suppression-form">
            <?= $this->csrfField() ?>
            <label class="notification-form-field" for="suppress-email">
                <span>Email</span>
                <input type="email" id="suppress-email" name="email" class="input" placeholder="address@example.com" value="<?= $e(($suppress_old ?? [])['email'] ?? '') ?>"<?= field_attrs($suppress_errors ?? [], 'email', 'err-suppress-email') ?> required>
            </label>
            <button class="btn btn-secondary" type="submit">Suppress</button>
            <?= field_error($suppress_errors ?? [], 'email', 'err-suppress-email') ?>
        </form>
        <?php if (!empty($unsuppress_error ?? null)): ?>
            <p class="field-error" role="alert"><?= $e($unsuppress_error) ?></p>
        <?php endif; ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Suppressed addresses">
            <table class="audit notification-suppression-table">
                <thead><tr><th scope="col">Email</th><th scope="col">Reason</th><th scope="col">Since</th><th scope="col" class="action-cell"><span class="sr-only">Actions</span></th></tr></thead>
                <tbody>
                <?php foreach (($suppressions ?? []) as $row): ?>
                    <?php $reason = (string) ($row['reason'] ?? ''); ?>
                    <tr>
                        <td class="notification-suppression-email"><?= $e($row['email']) ?></td>
                        <td class="notification-suppression-reason"><?= $e($reasonLabels[$reason] ?? $reason) ?></td>
                        <td class="mono"><?= $e(human_datetime($row['created_at'])) ?></td>
                        <td class="action-cell">
                            <form method="post" action="/admin/email/suppressions/remove" class="inline-form">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="email" value="<?= $e($row['email']) ?>">
                                <button class="btn notification-release-button" type="submit">Release</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($suppressions ?? [])): ?>
            <p class="notification-empty-copy notification-suppression-empty">No addresses are suppressed. Unsubscribes and operator additions land here.</p>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
