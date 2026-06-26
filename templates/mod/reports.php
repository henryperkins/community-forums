<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Reports'); ?>
<div class="reports-view">
    <header class="board-header">
        <h1>Reports queue</h1>
        <p class="muted">Open and claimed reports in your scope.</p>
    </header>

    <?php if (empty($reports)): ?>
        <p class="muted empty">No open reports. Nice and quiet.</p>
    <?php else: ?>
        <ul class="report-list">
            <?php foreach ($reports as $r): ?>
                <li class="report-row">
                    <div class="report-head">
                        <span class="badge<?= $r['status'] === 'triaged' ? '' : ' badge-muted' ?>"><?= $e($r['status']) ?></span>
                        <?php if (($r['reason_code'] ?? '') !== ''): ?><span class="tag"><?= $e(str_replace('_', ' ', $r['reason_code'])) ?></span><?php endif; ?>
                        <span class="muted">by <?= $e($r['reporter_username']) ?> · <?= $e(human_datetime($r['created_at'])) ?></span>
                    </div>
                    <?php if (($r['post_id'] ?? null) !== null): ?>
                        <p class="report-target">
                            <a href="/t/<?= (int) $r['thread_id'] ?>-<?= $e($r['thread_slug']) ?>#p<?= (int) $r['post_id'] ?>"><?= $e($r['thread_title'] ?? 'thread') ?></a>
                        </p>
                        <blockquote class="report-excerpt"><?= $e(mb_strimwidth((string) ($r['post_body'] ?? ''), 0, 240, '…')) ?></blockquote>
                    <?php else: ?>
                        <p class="report-target"><em>Reported direct message #<?= (int) $r['dm_message_id'] ?></em></p>
                    <?php endif; ?>
                    <?php if (($r['reason'] ?? '') !== ''): ?><p class="muted">“<?= $e($r['reason']) ?>”</p><?php endif; ?>
                    <div class="report-actions">
                        <?php if ($r['status'] === 'open'): ?>
                            <form class="inline" method="post" action="/mod/reports/<?= (int) $r['id'] ?>/claim"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Claim</button></form>
                        <?php endif; ?>
                        <form class="inline" method="post" action="/mod/reports/<?= (int) $r['id'] ?>/resolve"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Resolve</button></form>
                        <form class="inline" method="post" action="/mod/reports/<?= (int) $r['id'] ?>/dismiss"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Dismiss</button></form>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
