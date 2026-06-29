<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$this->section('title', $display . ' (@' . $profile['username'] . ')');
$profileUrl = '/u/' . $profile['username'];
?>
<div class="profile">
    <header class="profile-cover">
        <svg class="profile-cover-star" viewBox="0 0 100 100" fill="none" aria-hidden="true"><g stroke="currentColor" stroke-width="1.3" stroke-linejoin="round" stroke-linecap="round"><path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/></g></svg>
        <span class="profile-avatar">
            <?= $this->partial('partials/monogram', ['name' => $display, 'username' => $profile['username'], 'avatar_path' => $profile['avatar_path'] ?? null, 'gilt' => true]) ?>
            <?php if (!empty($presence_online)): ?><span class="presence-dot" title="Active recently" aria-label="Online"></span><?php endif; ?>
        </span>
        <div class="profile-id">
            <h1 class="profile-name">
                <?= $e($display) ?>
                <?php if (($title ?? '') !== ''): ?><span class="profile-tier" title="Cosmetic rank"><?= $e($title) ?></span><?php endif; ?>
            </h1>
            <p class="profile-handle">@<?= $e($profile['username']) ?><?php if (!empty($profile['pronouns'])): ?> · <?= $e($profile['pronouns']) ?><?php endif; ?></p>
            <p class="profile-meta">Member since <?= $e(human_date($profile['created_at'])) ?><?php if (!empty($profile['location'])): ?> · <?= $e($profile['location']) ?><?php endif; ?></p>
            <?php if (!empty($profile['website'])): ?>
                <p class="profile-web"><a href="<?= $e($profile['website']) ?>" rel="nofollow noopener ugc" target="_blank"><?= $e($profile['website']) ?></a></p>
            <?php endif; ?>
            <dl class="profile-stats">
                <div><dt>Posts</dt><dd><?= number_format((int) $profile['post_count']) ?></dd></div>
                <?php if (!empty($community)): ?>
                    <div><dt><a href="<?= $e($profileUrl) ?>/followers">Followers</a></dt><dd><?= number_format((int) ($follower_count ?? 0)) ?></dd></div>
                    <div><dt><a href="<?= $e($profileUrl) ?>/following">Following</a></dt><dd><?= number_format((int) ($following_count ?? 0)) ?></dd></div>
                    <?php if ((int) ($solved_count ?? 0) > 0): ?>
                        <div><dt>Solved</dt><dd><?= number_format((int) $solved_count) ?></dd></div>
                    <?php endif; ?>
                <?php endif; ?>
            </dl>
        </div>
        <div class="profile-aside">
            <div class="profile-rep">
                <span class="profile-rep-value"><span class="star-marker" aria-hidden="true">✦</span><?= number_format((int) $profile['reputation']) ?></span>
                <span class="profile-rep-label">Reputation</span>
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
        </div>
    </header>

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
