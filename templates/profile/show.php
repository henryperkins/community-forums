<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$this->section('title', $display . ' (@' . $profile['username'] . ')');
?>
<div class="profile">
    <header class="profile-head">
        <?= $this->partial('partials/monogram', ['name' => $display, 'username' => $profile['username']]) ?>
        <div class="profile-id">
            <h1><?= $e($display) ?></h1>
            <p class="muted">@<?= $e($profile['username']) ?></p>
            <?php if (!empty($profile['location'])): ?><p class="profile-loc"><?= $e($profile['location']) ?></p><?php endif; ?>
            <p class="muted">Member since <?= $e(human_date($profile['created_at'])) ?></p>
        </div>
    </header>

    <dl class="profile-stats">
        <div><dt>Posts</dt><dd><?= (int) $profile['post_count'] ?></dd></div>
        <div><dt>Reputation</dt><dd><?= (int) $profile['reputation'] ?></dd></div>
    </dl>

    <?php if (($bio_html ?? '') !== ''): ?>
        <section class="profile-bio">
            <h2>About</h2>
            <div class="prose"><?= $bio_html /* pre-sanitised */ ?></div>
        </section>
    <?php endif; ?>

    <?php if (!empty($recent_threads)): ?>
        <section class="profile-threads">
            <h2>Recent topics</h2>
            <ul class="link-list">
                <?php foreach ($recent_threads as $t): ?>
                    <li><a href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>"><?= $e($t['title']) ?></a> <span class="muted">in #<?= $e($t['board_slug']) ?></span></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
