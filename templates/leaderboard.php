<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Top contributors'); ?>
<div class="leaderboard">
    <header class="board-header">
        <p class="eyebrow">The council</p>
        <h1>Top contributors</h1>
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
                <?php $rank = (int) $r['rank']; $roman = [1 => 'I', 2 => 'II', 3 => 'III'][$rank] ?? (string) $rank; $top = $rank <= 3; ?>
                <li class="leaderboard-row<?= $top ? ' lb-top' : '' ?>">
                    <span class="lb-rank<?= $top ? ' lb-rank-roman' : '' ?>"><?= $e($roman) ?></span>
                    <?= $this->partial('partials/monogram', ['name' => $r['display_name'], 'username' => $r['username'], 'gilt' => $top]) ?>
                    <?php if ($top): ?>
                        <?php // Top-3: a prominent identity card — name over a "@handle · title" sub-line. ?>
                        <div class="lb-id">
                            <a class="lb-name" href="/u/<?= $e($r['username']) ?>"><?= $e($r['display_name']) ?></a>
                            <span class="lb-handle">@<?= $e($r['username']) ?><?php if (($r['title'] ?? '') !== ''): ?> · <?= $e($r['title']) ?><?php endif; ?></span>
                        </div>
                        <span class="lb-rep"><span class="star-marker" aria-hidden="true">✦</span><?= number_format((int) $r['reputation']) ?></span>
                    <?php else: ?>
                        <?php // Lower ranks: a compact scannable row — name + the smaller mono regard. ?>
                        <a class="lb-name" href="/u/<?= $e($r['username']) ?>"><?= $e($r['display_name']) ?></a>
                        <span class="lb-row-rep"><span class="star-marker" aria-hidden="true">✦</span><?= number_format((int) $r['reputation']) ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
    <p class="lb-note">Esteem recognises contribution — it grants no powers, badges, or privileges, and never appears on your inbox. A member may keep themselves off this ledger at any time.</p>
</div>
