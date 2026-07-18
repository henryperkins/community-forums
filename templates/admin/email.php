<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Email delivery');
$pagerBase = array_filter([
    'status' => $f_status ?? '',
    'kind' => $f_kind ?? '',
    'email' => $f_email ?? '',
], static fn ($v): bool => $v !== '');
$page = (int) ($page ?? 1);
?>
<div class="admin">
    <header class="admin-head">
        <h1>Email delivery</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'email', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php // F24: one status line per independent fact — transport, From, domain.
          // The old combined copy could claim "Sending is configured" while email
          // fails closed with no From (round-2 audit, 2026-07-18). ?>
    <?php $domain = $domain_status ?? []; ?>
    <?php if (empty($mailer_configured)): ?>
        <div class="flash" role="alert"><strong>Email is not ready to send.</strong> Outbound messages are skipped until the transport is configured — see the status facts below.</div>
    <?php endif; ?>
    <ul class="email-status-facts">
        <li><strong>Transport:</strong>
            <?= empty($mailer_configured) ? 'not configured — outbound email is skipped' : 'configured — the delivery worker drains queued mail' ?></li>
        <li><strong>From address:</strong>
            <?php if (($mail_from ?? '') !== ''): ?><code><?= $e($mail_from) ?></code>
            <?php else: ?>not set — email fails closed (messages are skipped) until a From address is configured<?php endif; ?></li>
        <li><strong>Sending domain:</strong>
            <?php if (($domain['domain'] ?? '') === ''): ?>
                none — derived from the From address once it is set
            <?php elseif (!empty($send_blocked)): ?>
                <code><?= $e((string) $domain['domain']) ?></code> — blocked until SPF and DKIM pass
            <?php elseif (($domain['spf_status'] ?? '') === 'pass' && ($domain['dkim_status'] ?? '') === 'pass'): ?>
                <code><?= $e((string) $domain['domain']) ?></code> — verified (SPF and DKIM pass)
            <?php else: ?>
                <code><?= $e((string) $domain['domain']) ?></code> — unverified; send blocking is off (ADR 0008 opt-in)
            <?php endif; ?></li>
    </ul>
    <?php if (!empty($send_blocked)): ?>
        <div class="flash" role="alert">
            <strong>Email sending is blocked until SPF and DKIM pass.</strong>
            Refresh the sending-domain status after DNS records are published.
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Sending domain</h2>
        <?php if (($domain['domain'] ?? '') === ''): ?>
            <p class="muted">Set a From address before verifying SPF and DKIM.</p>
        <?php else: ?>
            <p>
                <strong><?= $e((string) $domain['domain']) ?></strong>
                <span class="muted">selector <code><?= $e((string) ($domain['dkim_selector'] ?? 'default')) ?></code></span>
            </p>
            <p class="muted">
                SPF: <?= $e((string) ($domain['spf_status'] ?? 'unknown')) ?> ·
                DKIM: <?= $e((string) ($domain['dkim_status'] ?? 'unknown')) ?>
                <?php if (!empty($domain['checked_at'])): ?> · checked <?= $e(human_datetime((string) $domain['checked_at'])) ?><?php endif; ?>
            </p>
            <form method="post" action="/admin/email/domain/verify" class="inline-form">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit">Refresh SPF/DKIM status</button>
            </form>
            <p class="muted">Verified-domain send blocking is <?= !empty($domain['required']) ? 'enabled' : 'disabled' ?>.</p>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Queue status</h2>
        <ul class="stat-cards">
            <?php foreach (['queued', 'sent', 'failed', 'suppressed', 'bounced', 'complained'] as $s): ?>
                <li class="stat-card"><span class="stat-num"><?= (int) ($status_counts[$s] ?? 0) ?></span> <span class="stat-label"><?= $e($s) ?></span></li>
            <?php endforeach; ?>
        </ul>
    </section>

    <section class="card">
        <h2>Send a test email</h2>
        <form method="post" action="/admin/email/test" class="inline-form">
            <?= $this->csrfField() ?>
            <button class="btn btn-small" type="submit"<?= empty($mailer_configured) ? ' disabled' : '' ?>>Send test email</button>
        </form>
        <p class="muted">Sends a one-off message to your own account address and records it in the log below.</p>
    </section>

    <section class="card">
        <h2>Delivery log</h2>
        <form method="get" action="/admin/email" class="inline-form">
            <label>Status
                <select name="status" class="input">
                    <option value="">Any</option>
                    <?php foreach (['queued', 'sent', 'bounced', 'complained', 'suppressed', 'failed'] as $s): ?>
                        <option value="<?= $e($s) ?>"<?= ($f_status ?? '') === $s ? ' selected' : '' ?>><?= $e($s) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Kind
                <select name="kind" class="input">
                    <option value="">Any</option>
                    <?php foreach (['instant', 'digest', 'test', 'system'] as $k): ?>
                        <option value="<?= $e($k) ?>"<?= ($f_kind ?? '') === $k ? ' selected' : '' ?>><?= $e($k) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Email
                <input type="text" name="email" class="input" value="<?= $e($f_email ?? '') ?>">
            </label>
            <button class="btn btn-small" type="submit">Filter</button>
            <a class="btn btn-small" href="/admin/email/export<?= $pagerBase !== [] ? '?' . $e(http_build_query($pagerBase)) : '' ?>">Download CSV</a>
        </form>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Email delivery log">
        <table class="audit">
            <thead><tr><th scope="col">When</th><th scope="col">To</th><th scope="col">Kind</th><th scope="col">Status</th><th scope="col">Attempts</th><th scope="col">Subject</th><th scope="col">Detail</th><th scope="col">Action</th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= $e(human_datetime($d['created_at'])) ?></td>
                    <td><?= $e($d['email']) ?></td>
                    <td><?= $e($d['kind']) ?></td>
                    <td><span class="state state-<?= $e((string) $d['status']) ?>"><?= $e((string) $d['status']) ?></span></td>
                    <td>
                        <?= (int) ($d['attempt_count'] ?? 0) ?> / <?= (int) ($d['max_attempts'] ?? 1) ?>
                        <?php if (!empty($d['next_attempt_at'])): ?>
                            <br><span class="muted">Next retry <?= $e(human_datetime((string) $d['next_attempt_at'])) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= $e((string) ($d['subject'] ?? '')) ?></td>
                    <td><?= $e((string) ($d['error'] ?? $d['message_id'] ?? '')) ?></td>
                    <td>
                        <?php if (($d['status'] ?? '') === 'failed'): ?>
                            <form method="post" action="/admin/email/deliveries/<?= (int) $d['id'] ?>/requeue" class="inline-form">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-small" type="submit">Requeue</button>
                            </form>
                        <?php else: ?>
                            <span class="muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($deliveries)): ?>
                <tr><td colspan="8" class="muted">No deliveries match.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
        <p class="muted"><?= (int) $total ?> total matching deliveries.</p>
        <nav class="pager" aria-label="Pagination">
            <?php if ($page > 1): ?>
                <a class="btn btn-small" href="/admin/email?<?= $e(http_build_query($pagerBase + ['page' => $page - 1])) ?>">Previous</a>
            <?php endif; ?>
            <?php if (!empty($has_next)): ?>
                <a class="btn btn-small" href="/admin/email?<?= $e(http_build_query($pagerBase + ['page' => $page + 1])) ?>">Next</a>
            <?php endif; ?>
        </nav>
    </section>

    <section class="card">
        <h2>Suppressed addresses</h2>
        <form method="post" action="/admin/email/suppressions" class="inline-form">
            <?= $this->csrfField() ?>
            <label class="sr-only" for="suppress-email">Email address to suppress</label>
            <input type="email" id="suppress-email" name="email" class="input" placeholder="address@example.com" value="<?= $e(($suppress_old ?? [])['email'] ?? '') ?>" required>
            <button class="btn btn-small" type="submit">Suppress</button>
        </form>
        <?php if (!empty(($suppress_errors ?? [])['email'] ?? null)): ?>
            <p class="field-error" role="alert"><?= $e($suppress_errors['email']) ?></p>
        <?php endif; ?>
        <?php if (!empty($unsuppress_error ?? null)): ?>
            <p class="field-error" role="alert"><?= $e($unsuppress_error) ?></p>
        <?php endif; ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Suppressed addresses">
        <table class="audit">
            <thead><tr><th scope="col">Email</th><th scope="col">Reason</th><th scope="col">Since</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($suppressions as $row): ?>
                <tr>
                    <td><?= $e($row['email']) ?></td>
                    <td><?= $e($row['reason']) ?></td>
                    <td><?= $e(human_datetime($row['created_at'])) ?></td>
                    <td>
                        <form method="post" action="/admin/email/suppressions/remove" class="inline-form">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="email" value="<?= $e($row['email']) ?>">
                            <button class="btn btn-small" type="submit">Remove</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($suppressions)): ?>
                <tr><td colspan="4" class="muted">No suppressed addresses.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>
</div>
