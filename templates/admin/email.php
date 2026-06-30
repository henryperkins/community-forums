<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Email delivery');
?>
<div class="admin">
    <header class="admin-head">
        <h1>Email delivery</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <?php if (!empty($features['webhooks'])): ?><a href="/admin/webhooks">Webhooks</a><?php endif; ?>
        <a class="active" href="/admin/email">Email</a>
    </nav>

    <?php if (empty($mailer_configured)): ?>
        <div class="flash" role="alert">
            <strong>Email is not ready to send.</strong>
            Configure your sending domain (set a From address) before sending. Queued mail waits until the transport is configured.
        </div>
    <?php else: ?>
        <p class="muted">Sending is configured<?php if (($mail_from ?? '') !== ''): ?> from <code><?= $e($mail_from) ?></code><?php endif; ?>. The delivery worker drains queued mail.</p>
    <?php endif; ?>
    <?php if (!empty($send_blocked)): ?>
        <div class="flash" role="alert">
            <strong>Email sending is blocked until SPF and DKIM pass.</strong>
            Refresh the sending-domain status after DNS records are published.
        </div>
    <?php endif; ?>

    <section class="card">
        <h2>Sending domain</h2>
        <?php $domain = $domain_status ?? []; ?>
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
            <a class="btn btn-small" href="/admin/email/export">Download CSV</a>
        </form>
        <table class="audit">
            <thead><tr><th>When</th><th>To</th><th>Kind</th><th>Status</th><th>Subject</th><th>Detail</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($deliveries as $d): ?>
                <tr>
                    <td><?= $e(human_datetime($d['created_at'])) ?></td>
                    <td><?= $e($d['email']) ?></td>
                    <td><?= $e($d['kind']) ?></td>
                    <td><?= $e($d['status']) ?></td>
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
                <tr><td colspan="7" class="muted">No deliveries match.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        <p class="muted"><?= (int) $total ?> total matching deliveries.</p>
    </section>

    <section class="card">
        <h2>Suppressed addresses</h2>
        <form method="post" action="/admin/email/suppressions" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="email" name="email" class="input" placeholder="address@example.com" required>
            <button class="btn btn-small" type="submit">Suppress</button>
        </form>
        <table class="audit">
            <thead><tr><th>Email</th><th>Reason</th><th>Since</th><th></th></tr></thead>
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
    </section>
</div>
