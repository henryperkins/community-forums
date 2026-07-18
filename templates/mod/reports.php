<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Reports'); ?>
<?php
$filters = $filters ?? ['status' => '', 'reason_code' => '', 'board_id' => 0];
$total = (int) ($total ?? count($reports ?? []));
$page = (int) ($page ?? 0);
$urgentCount = count(array_filter($reports ?? [], static fn (array $r): bool => ($r['reason_code'] ?? '') === 'harassment'));
$pagerBase = array_filter([
    'status' => (string) $filters['status'],
    'reason_code' => (string) $filters['reason_code'],
    'board_id' => (int) $filters['board_id'] > 0 ? (string) $filters['board_id'] : '',
], static fn (string $v): bool => $v !== '');
$staleBefore = time() - 86400;
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
        <a class="active" href="/mod/reports">Reports <span class="mod-count<?= $urgentCount > 0 ? ' is-urgent' : '' ?>"><?= $total ?></span></a>
        <a href="/mod/approvals">Approval hold</a>
        <a href="/mod/appeals">Appeals</a>
    </nav>

    <div class="mod-pane">
        <header class="board-header">
            <h2>Reports</h2>
            <p class="muted">Open and claimed reports in your scope, oldest first. Reports older than a day are flagged.</p>
        </header>

        <form method="get" action="/mod/reports" class="filter-form">
            <div class="filter-grid">
                <label class="field">
                    <span>Status</span>
                    <select name="status" class="input">
                        <option value="">Open or claimed</option>
                        <option value="open"<?= $filters['status'] === 'open' ? ' selected' : '' ?>>Open only</option>
                        <option value="triaged"<?= $filters['status'] === 'triaged' ? ' selected' : '' ?>>Claimed only</option>
                    </select>
                </label>
                <label class="field">
                    <span>Reason</span>
                    <select name="reason_code" class="input">
                        <option value="">Any reason</option>
                        <?php foreach (($reasons ?? []) as $reason): ?>
                            <option value="<?= $e($reason) ?>"<?= $filters['reason_code'] === $reason ? ' selected' : '' ?>><?= $e(ucfirst(str_replace('_', ' ', $reason))) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Board</span>
                    <select name="board_id" class="input">
                        <option value="">Any board</option>
                        <?php foreach (($boards ?? []) as $board): ?>
                            <option value="<?= (int) $board['id'] ?>"<?= (int) $filters['board_id'] === (int) $board['id'] ? ' selected' : '' ?>>#<?= $e($board['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>
            <div class="form-actions">
                <button class="btn btn-small" type="submit">Apply filters</button>
                <a class="btn btn-small btn-ghost" href="/mod/reports">Reset</a>
            </div>
        </form>

        <?php if (empty($reports)): ?>
            <p class="muted empty">No open reports<?= $pagerBase !== [] ? ' match these filters' : '' ?>. Nice and quiet.</p>
        <?php else: ?>
            <ul class="report-list">
                <?php foreach ($reports as $r): ?>
                    <?php
                    $urgent = ($r['reason_code'] ?? '') === 'harassment';
                    $createdTs = strtotime(((string) $r['created_at']) . ' UTC') ?: time();
                    $stale = $createdTs < $staleBefore;
                    ?>
                    <li class="report-row<?= $urgent ? ' is-urgent' : ' is-open' ?><?= $stale ? ' is-stale' : '' ?>">
                        <div class="report-head">
                            <span class="badge<?= $r['status'] === 'triaged' ? '' : ' badge-muted' ?>"><?= $e($r['status']) ?></span>
                            <?php if (($r['reason_code'] ?? '') !== ''): ?><span class="tag"><?= $e(str_replace('_', ' ', $r['reason_code'])) ?></span><?php endif; ?>
                            <?php if ($stale): ?><span class="tag tag-stale">waiting over a day</span><?php endif; ?>
                            <span class="muted">by <?= $e($r['reporter_username']) ?> · <?= $e(human_datetime($r['created_at'])) ?></span>
                        </div>
                        <?php if (($r['post_id'] ?? null) !== null): ?>
                            <?php $reportedAuthor = mask_author(null, $r['post_author_username'] ?? null, 'user', (int) ($r['post_is_anonymous'] ?? 0) === 1); ?>
                            <p class="report-target">
                                <a href="/t/<?= (int) $r['thread_id'] ?>-<?= $e($r['thread_slug']) ?>#p<?= (int) $r['post_id'] ?>"><?= $e($r['thread_title'] ?? 'thread') ?></a>
                                <?php if (($r['post_author_username'] ?? '') !== ''): ?>
                                    <span class="muted">· post by <?= $reportedAuthor['profile_url'] !== null ? '@' : '' ?><?= $e($reportedAuthor['label']) ?></span>
                                <?php endif; ?>
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
                            <?php // Anonymous authors stay masked here: no /mod/u/{id} shortcut —
                                  // unmasking is only the audited reveal on the post itself (ADMIN §1.3). ?>
                            <?php if (($r['post_author_id'] ?? null) !== null && (int) ($r['post_is_anonymous'] ?? 0) === 0): ?>
                                <a class="linkbtn" href="/mod/u/<?= (int) $r['post_author_id'] ?>">Warn author…</a>
                            <?php endif; ?>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
            <p class="muted"><?= $total ?> report<?= $total === 1 ? '' : 's' ?> in scope.</p>
            <nav class="pager">
                <?php if ($page > 0): ?>
                    <a class="btn btn-small" href="/mod/reports?<?= $e(http_build_query($pagerBase + ['page' => $page - 1])) ?>">Previous</a>
                <?php endif; ?>
                <?php if (!empty($has_next)): ?>
                    <a class="btn btn-small" href="/mod/reports?<?= $e(http_build_query($pagerBase + ['page' => $page + 1])) ?>">Next</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </div>
</div>
