<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$display = ($profile['display_name'] ?? '') !== '' ? $profile['display_name'] : $profile['username'];
$this->section('title', $display . ' (@' . $profile['username'] . ')');
$profileUrl = '/u/' . $profile['username'];
?>
<div class="profile">
    <?php if (!empty($mod_error_context)): ?>
        <?php
        // Failed staff action (POST /mod/u/*): surface the error and hand the
        // typed input back in a retry form so nothing is lost — the same
        // anti-draft-loss contract as the admin record screen. This block only
        // renders in the direct 422 response to the acting staff member.
        $modOld = $mod_old ?? [];
        ?>
        <section class="card mod-action-retry">
            <h2>Moderation action failed</h2>
            <?php foreach (($mod_errors ?? []) as $message): ?>
                <p class="field-error"><?= $e($message) ?></p>
            <?php endforeach; ?>
            <?php if (in_array($mod_error_context, ['warn', 'note', 'suspend', 'ban'], true)): ?>
                <form method="post" action="<?= $e('/mod/u/' . (int) $profile['id'] . '/' . $mod_error_context) ?>" class="stacked">
                    <?= $this->csrfField() ?>
                    <?php if ($mod_error_context === 'note'): ?>
                        <label class="field">
                            <span>Note (visible to staff only)</span>
                            <textarea name="body" class="input" rows="3"><?= $e((string) ($modOld['body'] ?? '')) ?></textarea>
                        </label>
                    <?php else: ?>
                        <label class="field">
                            <span>Reason</span>
                            <input type="text" name="reason" class="input" maxlength="255" value="<?= $e((string) ($modOld['reason'] ?? '')) ?>" required>
                        </label>
                        <?php if ($mod_error_context === 'suspend'): ?>
                            <label class="field">
                                <span>Until (UTC, optional — leave blank for indefinite)</span>
                                <input type="text" name="until" class="input" placeholder="YYYY-MM-DD HH:MM:SS" value="<?= $e((string) ($modOld['until'] ?? '')) ?>">
                            </label>
                        <?php endif; ?>
                        <?php if ($mod_error_context === 'warn' && ($modOld['board_id'] ?? '') !== ''): ?>
                            <input type="hidden" name="board_id" value="<?= (int) $modOld['board_id'] ?>">
                        <?php endif; ?>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button class="btn<?= in_array($mod_error_context, ['suspend', 'ban'], true) ? ' danger' : '' ?>" type="submit">Try again</button>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    <?php endif; ?>
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
                <span class="profile-rep-label">Regard</span>
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
                        <?php // Destructive actions live behind the ··· (consolidation §5c),
                              // reusing the DM popover — native <details>, so no-JS still works. ?>
                        <details class="dm-menu">
                            <summary class="dm-iconbtn" aria-label="More actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                            <div class="dm-menu-pop" role="menu">
                                <form method="post" action="<?= $e($profileUrl) ?>/block">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="return" value="<?= $e($profileUrl) ?>">
                                    <button class="dm-menu-item danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'ban']) ?><span><?= !empty($viewer_blocks_profile) ? 'Unblock' : 'Block' ?></span></button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            <?php elseif (!empty($is_self)): ?>
                <div class="profile-actions">
                    <a class="btn btn-small" href="/settings/account">Edit profile</a>
                </div>
            <?php endif; ?>
        </div>
    </header>

    <?php // Marks of esteem (§5.4): the badge row sits in the identity header, always visible. ?>
    <?php if (!empty($community) && !empty($badges)): ?>
        <?= $this->partial('partials/badges', ['badges' => $badges]) ?>
    <?php endif; ?>

    <?php
    // Activity tabs (§5.4): Overview / Topics / Posts / Commends. Each is a real
    // ?tab= URL (works without JS, crawlable); Commends only exists when the
    // community layer is on. Unknown/guarded values fall back to Overview.
    $activeTab = (string) ($tab ?? 'overview');
    if ($activeTab === 'commends' && empty($community)) { $activeTab = 'overview'; }
    ?>
    <nav class="profile-tabs" aria-label="Profile activity">
        <a class="profile-tab<?= $activeTab === 'overview' ? ' is-active' : '' ?>"<?= $activeTab === 'overview' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>">Overview</a>
        <a class="profile-tab<?= $activeTab === 'threads' ? ' is-active' : '' ?>"<?= $activeTab === 'threads' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=threads">Topics</a>
        <a class="profile-tab<?= $activeTab === 'posts' ? ' is-active' : '' ?>"<?= $activeTab === 'posts' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=posts">Posts</a>
        <?php if (!empty($community)): ?>
            <a class="profile-tab<?= $activeTab === 'commends' ? ' is-active' : '' ?>"<?= $activeTab === 'commends' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=commends">Commends</a>
        <?php endif; ?>
    </nav>

    <?php if ($activeTab === 'overview'): ?>
        <?php if (($bio_html ?? '') !== ''): ?>
            <section class="profile-bio">
                <h2>About</h2>
                <div class="prose"><?= $bio_html /* pre-sanitised */ ?></div>
            </section>
        <?php endif; ?>
        <?php if (!empty($custom_fields)): ?>
            <section class="profile-fields">
                <h2>Profile details</h2>
                <dl class="profile-custom-fields">
                    <?php foreach ($custom_fields as $field): ?>
                        <div><dt><?= $e($field['label']) ?></dt><dd><?= $e($field['value']) ?></dd></div>
                    <?php endforeach; ?>
                </dl>
            </section>
        <?php endif; ?>
        <?php if (!empty($recent_threads)): ?>
            <section class="profile-threads">
                <h2>Recent topics</h2>
                <ul class="link-list">
                    <?php foreach (array_slice($recent_threads, 0, 5) as $t): ?>
                        <li><a href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>"><?= $e($t['title']) ?></a> <span class="muted">in #<?= $e($t['board_slug']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php if (!empty($recent_posts)): ?>
            <section class="profile-posts">
                <h2>Recent posts</h2>
                <ul class="link-list">
                    <?php foreach (array_slice($recent_posts, 0, 5) as $p): ?>
                        <li>
                            <a href="/t/<?= (int) $p['thread_id'] ?>-<?= $e($p['thread_slug']) ?>#p<?= (int) $p['id'] ?>"><?= $e($p['thread_title']) ?></a>
                            <span class="muted">— <?= $e(mb_strimwidth((string) $p['body'], 0, 90, '…')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
        <?php if (($bio_html ?? '') === '' && empty($custom_fields) && empty($recent_threads) && empty($recent_posts)): ?>
            <p class="profile-panel-empty">No public activity yet.</p>
        <?php endif; ?>
    <?php elseif ($activeTab === 'threads'): ?>
        <section class="profile-threads">
            <h2>Topics</h2>
            <?php if (!empty($recent_threads)): ?>
                <ul class="link-list">
                    <?php foreach ($recent_threads as $t): ?>
                        <li><a href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>"><?= $e($t['title']) ?></a> <span class="muted">in #<?= $e($t['board_slug']) ?></span></li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="profile-panel-empty">No topics started yet.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($activeTab === 'posts'): ?>
        <section class="profile-posts">
            <h2>Posts</h2>
            <?php if (!empty($recent_posts)): ?>
                <ul class="link-list">
                    <?php foreach ($recent_posts as $p): ?>
                        <li>
                            <a href="/t/<?= (int) $p['thread_id'] ?>-<?= $e($p['thread_slug']) ?>#p<?= (int) $p['id'] ?>"><?= $e($p['thread_title']) ?></a>
                            <span class="muted">— <?= $e(mb_strimwidth((string) $p['body'], 0, 90, '…')) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="profile-panel-empty">No posts yet.</p>
            <?php endif; ?>
        </section>
    <?php elseif ($activeTab === 'commends'): ?>
        <section class="profile-commends">
            <h2>Regard</h2>
            <p class="profile-rep-recap"><span class="star-marker" aria-hidden="true">✦</span><strong><?= number_format((int) $profile['reputation']) ?></strong> Regard</p>
            <p class="muted">Regard is the sum of the reactions <?= $e($display) ?>'s posts have received, with a small bonus for accepted answers. It recognises contribution — it grants no powers.</p>
        </section>
    <?php endif; ?>
</div>
