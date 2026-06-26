<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$this->section('title', $display . ' (@' . $profile['username'] . ')');
$profileUrl = '/u/' . $profile['username'];
?>
<div class="profile">
    <header class="profile-head">
        <?= $this->partial('partials/monogram', ['name' => $display, 'username' => $profile['username']]) ?>
        <div class="profile-id">
            <h1>
                <?= $e($display) ?>
                <?php if (($title ?? '') !== ''): ?><span class="badge badge-title" title="Cosmetic rank"><?= $e($title) ?></span><?php endif; ?>
                <?php if (!empty($presence_online)): ?><span class="pill pill-online" title="Active recently">● Online</span><?php endif; ?>
            </h1>
            <p class="muted">@<?= $e($profile['username']) ?></p>
            <?php if (!empty($profile['pronouns'])): ?><p class="muted profile-pronouns"><?= $e($profile['pronouns']) ?></p><?php endif; ?>
            <?php if (!empty($profile['location'])): ?><p class="profile-loc"><?= $e($profile['location']) ?></p><?php endif; ?>
            <?php if (!empty($profile['website'])): ?>
                <p class="profile-web"><a href="<?= $e($profile['website']) ?>" rel="nofollow noopener ugc" target="_blank"><?= $e($profile['website']) ?></a></p>
            <?php endif; ?>
            <p class="muted">Member since <?= $e(human_date($profile['created_at'])) ?></p>
        </div>

        <?php if (($current_user !== null) && empty($is_self)): ?>
            <div class="profile-actions">
                <?php if (!empty($can_follow)): ?>
                    <form class="inline" method="post" action="<?= $e($profileUrl) ?>/follow" data-follow>
                        <?= $this->csrfField() ?>
                        <button class="btn btn-small<?= !empty($is_following) ? ' btn-on' : '' ?>" type="submit">
                            <?= !empty($is_following) ? 'Following' : 'Follow' ?>
                        </button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($can_message)): ?>
                    <a class="btn btn-small" href="/messages/new?to=<?= $e($profile['username']) ?>">Message</a>
                <?php endif; ?>
                <?php if (!empty($can_block)): ?>
                    <form class="inline" method="post" action="<?= $e($profileUrl) ?>/block">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="return" value="<?= $e($profileUrl) ?>">
                        <button class="linkbtn muted" type="submit"><?= !empty($viewer_blocks_profile) ? 'Unblock' : 'Block' ?></button>
                    </form>
                <?php endif; ?>
            </div>
        <?php elseif (!empty($is_self)): ?>
            <div class="profile-actions">
                <a class="btn btn-small" href="/settings/account">Edit profile</a>
            </div>
        <?php endif; ?>
    </header>

    <dl class="profile-stats">
        <div><dt>Posts</dt><dd><?= (int) $profile['post_count'] ?></dd></div>
        <div><dt>Reputation</dt><dd><?= (int) $profile['reputation'] ?></dd></div>
        <?php if (!empty($community)): ?>
            <div><dt><a href="<?= $e($profileUrl) ?>/followers">Followers</a></dt><dd><?= (int) ($follower_count ?? 0) ?></dd></div>
            <div><dt><a href="<?= $e($profileUrl) ?>/following">Following</a></dt><dd><?= (int) ($following_count ?? 0) ?></dd></div>
            <?php if ((int) ($solved_count ?? 0) > 0): ?>
                <div><dt>Solved</dt><dd><?= (int) $solved_count ?></dd></div>
            <?php endif; ?>
        <?php endif; ?>
    </dl>

    <?php if (!empty($community) && !empty($badges)): ?>
        <?= $this->partial('partials/badges', ['badges' => $badges]) ?>
    <?php endif; ?>

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

    <?php if (!empty($recent_posts)): ?>
        <section class="profile-posts">
            <h2>Recent posts</h2>
            <ul class="link-list">
                <?php foreach ($recent_posts as $p): ?>
                    <li>
                        <a href="/t/<?= (int) $p['thread_id'] ?>-<?= $e($p['thread_slug']) ?>#p<?= (int) $p['id'] ?>"><?= $e($p['thread_title']) ?></a>
                        <span class="muted">— <?= $e(mb_strimwidth((string) $p['body'], 0, 90, '…')) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
