<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Top contributors'); ?>
<?php
$windowLabels = ['week' => 'Week', 'month' => 'Month', 'all' => 'All time'];
$windowLabel = $windowLabels[$window ?? 'all'] ?? $windowLabels['all'];
$this->start('leaderboard_window_controls');
?>
    <?php if (!empty($ledger_on)): ?>
        <nav class="inbox-tabs" aria-label="Leaderboard windows">
            <a class="inbox-tab<?= ($window ?? 'all') === 'week' ? ' is-active' : '' ?>" href="/leaderboard?window=week"<?= ($window ?? 'all') === 'week' ? ' aria-current="page"' : '' ?>>Week</a>
            <a class="inbox-tab<?= ($window ?? 'all') === 'month' ? ' is-active' : '' ?>" href="/leaderboard?window=month"<?= ($window ?? 'all') === 'month' ? ' aria-current="page"' : '' ?>>Month</a>
            <a class="inbox-tab<?= ($window ?? 'all') === 'all' ? ' is-active' : '' ?>" href="/leaderboard?window=all"<?= ($window ?? 'all') === 'all' ? ' aria-current="page"' : '' ?>>All time</a>
        </nav>
    <?php endif; ?>
<?php $this->stop(); ?>
<?php if ($current_user !== null): $this->start('subheader_leading'); ?>
    <div class="page-toolbar leaderboard-toolbar">
        <h1 class="forum-page-title">Top contributors</h1>
        <?php if (!empty($ledger_on)): ?>
            <div class="page-toolbar-controls">
                <details class="inbox-menu leaderboard-window-menu" name="inbox-controls" data-inbox-menu data-inbox-menu-align="end">
                    <summary aria-label="Leaderboard window: <?= $e($windowLabel) ?>"><span class="toolbar-control-prefix">Window:</span><span><?= $e($windowLabel) ?></span><?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?></summary>
                    <div class="inbox-menu-panel"><?= $this->block('leaderboard_window_controls') ?></div>
                </details>
            </div>
        <?php endif; ?>
    </div>
<?php $this->stop(); endif; ?>
<div class="leaderboard">
    <header class="board-header">
        <?php if ($current_user === null): ?><p class="eyebrow">The council</p><h1>Top contributors</h1><?php endif; ?>
        <p class="muted">Members ranked by appreciation received. Recognition only — it unlocks nothing.</p>
    </header>
    <?php if ($current_user === null): ?><?= $this->block('leaderboard_window_controls') ?><?php endif; ?>

    <?php if (empty($ranked)): ?>
        <p class="muted empty">No ranked contributors yet.</p>
    <?php else: ?>
        <ol class="leaderboard-list">
            <?php foreach ($ranked as $r): ?>
                <?php $rank = (int) $r['rank']; $roman = [1 => 'I', 2 => 'II', 3 => 'III'][$rank] ?? (string) $rank; $top = $rank <= 3; ?>
                <li class="leaderboard-row<?= $top ? ' lb-top' : '' ?>">
                    <span class="lb-rank<?= $top ? ' lb-rank-roman' : '' ?>"><?= $e($roman) ?></span>
                    <?= $this->partial('partials/monogram', ['name' => $r['display_name'], 'username' => $r['username'], 'avatar_path' => $r['avatar_path'] ?? null, 'gilt' => $top]) ?>
                    <?php if ($top): ?>
                        <?php // Top-3: a prominent identity card — name over a "@handle · title" sub-line. ?>
                        <div class="lb-id">
                            <a class="lb-name" href="/u/<?= $e($r['username']) ?>"><?= $e($r['display_name']) ?></a>
                            <span class="lb-handle">@<?= $e($r['username']) ?><?php if (($r['title'] ?? '') !== ''): ?> · <?= $e($r['title']) ?><?php endif; ?></span>
                        </div>
                        <span class="lb-rep"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) $r['reputation']) ?></span>
                    <?php else: ?>
                        <?php // Lower ranks: a compact scannable row — name + the smaller mono regard. ?>
                        <a class="lb-name" href="/u/<?= $e($r['username']) ?>"><?= $e($r['display_name']) ?></a>
                        <span class="lb-row-rep"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) $r['reputation']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <p class="lb-note">Esteem recognises contribution — it grants no powers, badges, or privileges, and never appears on your inbox. A member may keep themselves off this ledger at any time.</p>
</div>
