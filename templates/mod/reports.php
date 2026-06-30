<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Reports'); ?>
<?php
$reportCount = count($reports ?? []);
$urgentCount = count(array_filter($reports ?? [], static fn (array $r): bool => ($r['reason_code'] ?? '') === 'harassment'));
?>
<div class="mod reports-view">
    <header class="mod-head">
        <span>
            <span class="eyebrow">Warden's table</span>
            <h1>Reports queue</h1>
        </span>
        <span class="pill mod-pill">Moderation</span>
    </header>

    <nav class="mod-subnav" aria-label="Moderation queues">
        <a class="active" href="/mod/reports">Reports <span class="mod-count<?= $urgentCount > 0 ? ' is-urgent' : '' ?>"><?= $reportCount ?></span></a>
        <a href="/mod/approvals">Approval hold</a>
        <a href="/mod/appeals">Appeals</a>
    </nav>

    <div class="mod-pane">
        <header class="board-header">
            <h1>Reports</h1>
            <p class="muted">Open and claimed reports in your scope.</p>
        </header>

        <?php if (empty($reports)): ?>
            <p class="muted empty">No open reports. Nice and quiet.</p>
        <?php else: ?>
            <ul class="report-list">
                <?php foreach ($reports as $r): ?>
                    <?php $urgent = ($r['reason_code'] ?? '') === 'harassment'; ?>
                    <li class="report-row<?= $urgent ? ' is-urgent' : ' is-open' ?>">
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
                            <?php
                                $dmSender = ($r['dm_sender_display_name'] ?? '') !== '' ? $r['dm_sender_display_name'] : ($r['dm_sender_username'] ?? 'unknown');
                                $dmHandle = $r['dm_sender_username'] ?? 'unknown';
                                $dmTitle = ($r['dm_conversation_title'] ?? '') !== '' ? $r['dm_conversation_title'] : (($r['dm_conversation_kind'] ?? '') === 'group' ? 'Group conversation' : 'Direct message');
                            ?>
                            <p class="report-target"><em><?= $e($dmTitle) ?> · message #<?= (int) $r['dm_message_id'] ?> from <?= $e($dmSender) ?> (@<?= $e($dmHandle) ?>)</em></p>
                            <blockquote class="report-excerpt"><?= $e(mb_strimwidth((string) ($r['dm_body'] ?? ''), 0, 240, '…')) ?></blockquote>
                        <?php endif; ?>
                        <?php if (($r['reason'] ?? '') !== ''): ?><p class="report-note"><?= $e($r['reason']) ?></p><?php endif; ?>
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
</div>
