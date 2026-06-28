<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Top Contributors'); ?>
<div class="leaderboard">
    <header class="board-header">
        <h1>Top Contributors</h1>
        <p class="muted">Members ranked by appreciation received. Recognition only — it unlocks nothing.</p>
    </header>
    <?php if (!empty($ledger_on)): ?>
        <nav class="inbox-tabs" aria-label="Leaderboard windows">
            <a class="inbox-tab<?= ($window ?? 'all') === 'week' ? ' is-active' : '' ?>" href="/leaderboard?window=week">Week</a>
            <a class="inbox-tab<?= ($window ?? 'all') === 'month' ? ' is-active' : '' ?>" href="/leaderboard?window=month">Month</a>
            <a class="inbox-tab<?= ($window ?? 'all') === 'all' ? ' is-active' : '' ?>" href="/leaderboard?window=all">All time</a>
        </nav>
    <?php endif; ?>

    <?php if (empty($ranked)): ?>
        <p class="muted empty">No ranked contributors yet.</p>
    <?php else: ?>
        <ol class="leaderboard-list">
            <?php foreach ($ranked as $r): ?>
                <li class="leaderboard-row">
                    <span class="lb-rank">#<?= (int) $r['rank'] ?></span>
                    <?= $this->partial('partials/monogram', ['name' => $r['display_name'], 'username' => $r['username']]) ?>
                    <a class="lb-name" href="/u/<?= $e($r['username']) ?>"><?= $e($r['display_name']) ?></a>
                    <span class="badge badge-title"><?= $e($r['title']) ?></span>
                    <span class="lb-rep"><?= (int) $r['reputation'] ?> rep</span>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
