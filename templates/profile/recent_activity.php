<?php /** @var \App\Core\View $this */ ?>
<section class="profile-activity" id="recent-activity" aria-labelledby="recent-activity-heading">
    <div class="profile-activity-heading">
        <h2 id="recent-activity-heading">Recent activity</h2>
        <nav class="inbox-tabs" aria-label="Show">
            <?php foreach (['all' => 'All', 'threads' => 'Topics', 'posts' => 'Replies'] as $key => $label): ?>
                <a class="inbox-tab<?= $filter === $key ? ' is-active' : '' ?>"<?= $filter === $key ? ' aria-current="page"' : '' ?> href="<?= $e($profile_url . ($key === 'all' ? '' : '?activity=' . $key) . '#recent-activity') ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </nav>
    </div>
    <?php if ($rows !== []): ?>
        <ul class="profile-activity-rows">
            <?php foreach ($rows as $row): ?>
                <?php
                $isTopic = $row['kind'] === 'threads';
                $detailId = 'activity-' . $row['kind'] . '-' . $row['id'];
                $excerpt = \App\Support\Str::snippet(\App\Support\Str::plainText((string) $row['excerpt_html']), 140);
                ?>
                <li class="profile-activity-row">
                    <div class="profile-activity-line">
                        <span class="profile-activity-kind"><?= $isTopic ? 'Topic' : 'Reply' ?></span>
                        <a class="profile-activity-title" href="<?= $e($row['url']) ?>" title="<?= $e($row['title']) ?>"><?= $e($row['title']) ?></a>
                        <time datetime="<?= $e(iso_datetime($row['created_at'])) ?>" title="<?= $e(human_datetime($row['created_at'])) ?>"><?= $e(relative_datetime($row['created_at'])) ?></time>
                        <button class="profile-activity-toggle" type="button" data-activity-toggle data-activity-title="<?= $e($row['title']) ?>" aria-expanded="false" aria-controls="<?= $e($detailId) ?>" aria-label="Show details: <?= $e($row['title']) ?>" hidden>
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg>
                        </button>
                    </div>
                    <div class="profile-activity-detail" id="<?= $e($detailId) ?>" hidden>
                        <?php if ($excerpt !== ''): ?><p class="profile-row-excerpt"><?= $e($excerpt) ?></p><?php endif; ?>
                        <p class="profile-row-meta"><span>#<?= $e($row['board_slug']) ?></span><span><span class="profile-number"><?= number_format((int) $row['count']) ?></span> <?= $isTopic ? ((int) $row['count'] === 1 ? 'reply' : 'replies') : ((int) $row['count'] === 1 ? 'commend' : 'commends') ?></span></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <div class="profile-panel-empty">
            <h2><?= $filter === 'threads' ? 'No topics started yet.' : ($filter === 'posts' ? 'No replies yet.' : 'No public activity yet') ?></h2>
            <p><?= $filter === 'all' ? $e($first_name) . ' has taken a seat but not yet spoken.' : ($filter === 'threads' ? 'Public topics will be listed here.' : 'Public replies will be listed here.') ?></p>
        </div>
    <?php endif; ?>
</section>
