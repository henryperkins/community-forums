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
            <?php if (!empty($presence_online)): ?><span class="presence-dot" title="Active recently" role="img" aria-label="Online"></span><?php endif; ?>
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
                <span class="profile-rep-value"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) $profile['reputation']) ?></span>
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
                            <div class="dm-menu-pop">
                                <a class="dm-menu-item" href="<?= $e($profileUrl) ?>" data-copy-link>
                                    <?= $this->partial('partials/icon', ['name' => 'copy']) ?><span>Copy link</span>
                                </a>
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

    <?php if (!empty($can_view_member_record)): ?>
        <section class="profile-moderator" aria-label="Moderator context">
            <?= $this->partial('partials/icon', ['name' => 'shield', 'class' => 'profile-moderator-icon']) ?>
            <div class="profile-moderator-copy">
                <strong>Moderator context</strong>
                <span><?= ($profile_status ?? 'active') === 'active' ? 'No active sanctions.' : (($profile_status ?? '') === 'suspended' ? 'This member is suspended.' : 'This member has an active sanction.') ?></span>
            </div>
            <a href="/mod/u/<?= (int) $profile['id'] ?>">Open member record</a>
        </section>
    <?php endif; ?>

    <?php
    // Every tab and control is a real GET URL so the profile remains complete
    // without JavaScript.
    $activeTab = (string) ($tab ?? 'overview');
    if (in_array($activeTab, ['commends', 'connections'], true) && empty($community)) { $activeTab = 'overview'; }
    $firstName = trim(explode(' ', $display)[0] ?? $display);
    $listTotal = (int) ($list_total ?? 0);
    $currentPage = (int) ($page ?? 1);
    $pageCount = (int) ($page_count ?? 1);
    $activeSort = (string) ($sort ?? 'newest');
    $q = (string) ($search_query ?? '');
    $connMode = (string) ($conn_mode ?? 'followers');
    $connQ = (string) ($conn_query ?? '');
    $isListTab = in_array($activeTab, ['threads', 'posts'], true);
    $listUrl = static function (array $changes = []) use ($profileUrl, $activeTab, $activeSort, $q, $currentPage): string {
        $params = ['tab' => $activeTab, 'q' => $q, 'sort' => $activeSort, 'page' => $currentPage];
        $params = array_merge($params, $changes);
        $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null && $value !== 1 && $value !== 'newest');
        return $profileUrl . ($params === [] ? '' : '?' . http_build_query($params));
    };
    $connUrl = static function (array $changes = []) use ($profileUrl, $connMode, $connQ): string {
        $params = array_merge(['tab' => 'connections', 'c' => $connMode, 'cq' => $connQ], $changes);
        $params = array_filter($params, static fn ($value): bool => $value !== '' && $value !== null && $value !== 'followers');
        return $profileUrl . '?' . http_build_query($params);
    };
    ?>
    <nav class="profile-tabs" aria-label="Profile activity">
        <a class="profile-tab<?= $activeTab === 'overview' ? ' is-active' : '' ?>"<?= $activeTab === 'overview' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>">Overview</a>
        <a class="profile-tab<?= $activeTab === 'threads' ? ' is-active' : '' ?>"<?= $activeTab === 'threads' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=threads">Topics</a>
        <a class="profile-tab<?= $activeTab === 'posts' ? ' is-active' : '' ?>"<?= $activeTab === 'posts' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=posts">Posts</a>
        <?php if (!empty($community)): ?>
            <a class="profile-tab<?= $activeTab === 'commends' ? ' is-active' : '' ?>"<?= $activeTab === 'commends' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=commends">Commends</a>
            <a class="profile-tab<?= $activeTab === 'connections' ? ' is-active' : '' ?>"<?= $activeTab === 'connections' ? ' aria-current="page"' : '' ?> href="<?= $e($profileUrl) ?>?tab=connections">Connections</a>
        <?php endif; ?>
    </nav>

    <?php if ($activeTab === 'overview'): ?>
        <?php
        $hasAside = !empty($custom_fields) || !empty($board_activity);
        $overviewEmpty = ($bio_html ?? '') === '' && empty($recent_threads) && empty($recent_posts) && !$hasAside;
        ?>
        <div class="profile-overview<?= $hasAside ? '' : ' is-single' ?>">
            <div class="profile-overview-main">
                <?php if (($bio_html ?? '') !== ''): ?>
                    <section class="profile-bio">
                        <h2>About</h2>
                        <?php // formatted-content is the shared prose contract — a bio is rendered
                              // through the same Markdown pipeline as a post, so it must be styled
                              // by the same rules (lists, code, quotes, tables) rather than UA defaults. ?>
                        <div class="prose formatted-content"><?= $bio_html /* pre-sanitised */ ?></div>
                    </section>
                <?php endif; ?>
                <?php if (!empty($recent_threads)): ?>
                    <section class="profile-threads">
                        <h2>Recent topics</h2>
                        <ul class="profile-rows">
                            <?php foreach ($recent_threads as $t): ?>
                                <li class="profile-row">
                                    <a class="profile-row-title" href="/t/<?= (int) $t['id'] ?>-<?= $e($t['slug']) ?>"><?= $e($t['title']) ?></a>
                                    <p class="profile-row-meta"><span>#<?= $e($t['board_slug']) ?></span><span><?= (int) ($t['reply_count'] ?? 0) ?> replies</span><span><?= $e(human_datetime($t['created_at'])) ?></span></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
                <?php if (!empty($recent_posts)): ?>
                    <section class="profile-posts">
                        <h2>Recent posts</h2>
                        <ul class="profile-rows">
                            <?php foreach ($recent_posts as $p): ?>
                                <li class="profile-row">
                                    <a class="profile-row-title" href="/t/<?= (int) $p['thread_id'] ?>-<?= $e($p['thread_slug']) ?>#p<?= (int) $p['id'] ?>"><?= $e($p['thread_title']) ?></a>
                                    <p class="profile-row-excerpt"><?= $e(mb_strimwidth((string) $p['body'], 0, 140, '…')) ?></p>
                                    <p class="profile-row-meta"><span><?= $e(human_datetime($p['created_at'])) ?></span><span><?= (int) ($p['commend_count'] ?? 0) ?> commends</span></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
                <?php if ($overviewEmpty): ?>
                    <div class="profile-panel-empty">
                        <h2>No public activity yet</h2>
                        <p><?= $e($firstName) ?> has taken a seat but not yet spoken.</p>
                    </div>
                <?php endif; ?>
            </div>
            <?php if ($hasAside): ?>
                <aside class="profile-overview-aside">
                    <?php if (!empty($custom_fields)): ?>
                        <section>
                            <h2>Profile details</h2>
                            <dl class="profile-custom-fields">
                                <?php foreach ($custom_fields as $field): ?>
                                    <div><dt><?= $e($field['label']) ?></dt><dd><?= $e($field['value']) ?></dd></div>
                                <?php endforeach; ?>
                            </dl>
                        </section>
                    <?php endif; ?>
                    <?php if (!empty($board_activity)): ?>
                        <section>
                            <h2>Most active in</h2>
                            <ul class="profile-active-boards">
                                <?php foreach ($board_activity as $board): ?>
                                    <li><a href="/c/<?= $e($board['slug']) ?>">#<?= $e($board['slug']) ?></a><span><?= number_format((int) $board['post_count']) ?></span></li>
                                <?php endforeach; ?>
                            </ul>
                        </section>
                    <?php endif; ?>
                </aside>
            <?php endif; ?>
        </div>

    <?php elseif ($isListTab): ?>
        <div class="profile-list">
            <form class="profile-list-tools" method="get" action="<?= $e($profileUrl) ?>">
                <input type="hidden" name="tab" value="<?= $e($activeTab) ?>">
                <?php if ($activeSort !== 'newest'): ?><input type="hidden" name="sort" value="<?= $e($activeSort) ?>"><?php endif; ?>
                <label class="profile-search">
                    <?= $this->partial('partials/icon', ['name' => 'search']) ?>
                    <input type="search" name="q" value="<?= $e($q) ?>" placeholder="Search this member's activity" aria-label="Search this member's activity">
                </label>
                <button class="btn btn-small" type="submit">Search</button>
                <span class="profile-list-count"><?= $listTotal === 1 ? '1 entry' : number_format($listTotal) . ' entries' ?></span>
                <span class="profile-sort">
                    <a class="profile-sort-opt<?= $activeSort === 'newest' ? ' is-on' : '' ?>" href="<?= $e($listUrl(['sort' => 'newest', 'page' => 1])) ?>">Newest</a>
                    <a class="profile-sort-opt<?= $activeSort === 'commends' ? ' is-on' : '' ?>" href="<?= $e($listUrl(['sort' => 'commends', 'page' => 1])) ?>">Most commended</a>
                </span>
            </form>

            <?php if (!empty($list_rows)): ?>
                <ul class="profile-rows profile-rows-lg">
                    <?php foreach ($list_rows as $row): ?>
                        <?php
                        $isTopic = $activeTab === 'threads';
                        $rowUrl = $isTopic
                            ? '/t/' . (int) $row['id'] . '-' . $row['slug']
                            : '/t/' . (int) $row['thread_id'] . '-' . $row['thread_slug'] . '#p' . (int) $row['id'];
                        $rowTitle = $isTopic ? (string) $row['title'] : (string) $row['thread_title'];
                        $rowBody = $isTopic ? (string) ($row['excerpt_body'] ?? '') : (string) $row['body'];
                        ?>
                        <li class="profile-row">
                            <div class="profile-row-body">
                                <a class="profile-row-title" href="<?= $e($rowUrl) ?>"><?= $e($rowTitle) ?></a>
                                <?php if ($rowBody !== ''): ?><p class="profile-row-excerpt"><?= $e(mb_strimwidth($rowBody, 0, 140, '…')) ?></p><?php endif; ?>
                                <p class="profile-row-meta"><span>#<?= $e($row['board_slug']) ?></span><span><?= $e(human_datetime($row['created_at'])) ?></span></p>
                            </div>
                            <span class="profile-row-commends"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) ($row['commend_count'] ?? 0)) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ($q !== ''): ?>
                <div class="profile-panel-empty">
                    <h2>Nothing matches “<?= $e($q) ?>”</h2>
                    <p>Try a shorter phrase, or clear the search to see everything.</p>
                    <p><a class="btn btn-small" href="<?= $e($listUrl(['q' => '', 'page' => 1])) ?>">Clear search</a></p>
                </div>
            <?php else: ?>
                <p class="profile-panel-empty"><?= $activeTab === 'threads' ? 'No topics started yet.' : 'No posts yet.' ?></p>
            <?php endif; ?>

            <?php if ($pageCount > 1): ?>
                <nav class="profile-pager" aria-label="Pagination">
                    <?php if ($currentPage > 1): ?><a class="btn btn-small" href="<?= $e($listUrl(['page' => $currentPage - 1])) ?>">Previous</a><?php else: ?><span class="btn btn-small is-disabled" aria-disabled="true">Previous</span><?php endif; ?>
                    <span class="profile-pager-label">Page <?= $currentPage ?> of <?= $pageCount ?></span>
                    <?php if ($currentPage < $pageCount): ?><a class="btn btn-small" href="<?= $e($listUrl(['page' => $currentPage + 1])) ?>">Next</a><?php else: ?><span class="btn btn-small is-disabled" aria-disabled="true">Next</span><?php endif; ?>
                </nav>
            <?php endif; ?>
        </div>

    <?php elseif ($activeTab === 'commends'): ?>
        <div class="profile-commends">
            <section class="profile-regard-card">
                <span class="profile-regard-value"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) $profile['reputation']) ?></span>
                <p class="profile-regard-label">Regard</p>
                <p class="profile-regard-note">Regard recognises contribution; it grants no powers.</p>
            </section>
            <section class="profile-commend-list">
                <h2>Most commended</h2>
                <?php if (!empty($top_commended)): ?>
                    <ul class="profile-commend-rows">
                        <?php foreach ($top_commended as $post): ?>
                            <li>
                                <span class="profile-commend-count"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= number_format((int) $post['commend_count']) ?></span>
                                <span class="profile-commend-body"><a href="/t/<?= (int) $post['thread_id'] ?>-<?= $e($post['thread_slug']) ?>#p<?= (int) $post['id'] ?>"><?= $e($post['thread_title']) ?></a></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <p class="profile-panel-empty">No commended posts yet.</p>
                <?php endif; ?>
            </section>
        </div>

    <?php elseif ($activeTab === 'connections'): ?>
        <div class="profile-connections">
            <div class="profile-conn-tools">
                <span class="profile-seg">
                    <a class="profile-seg-opt<?= $connMode === 'followers' ? ' is-on' : '' ?>" href="<?= $e($connUrl(['c' => 'followers', 'cq' => ''])) ?>">Followers · <?= number_format((int) ($follower_count ?? 0)) ?></a>
                    <a class="profile-seg-opt<?= $connMode === 'following' ? ' is-on' : '' ?>" href="<?= $e($connUrl(['c' => 'following', 'cq' => ''])) ?>">Following · <?= number_format((int) ($following_count ?? 0)) ?></a>
                </span>
                <form class="profile-conn-search" method="get" action="<?= $e($profileUrl) ?>">
                    <input type="hidden" name="tab" value="connections">
                    <input type="hidden" name="c" value="<?= $e($connMode) ?>">
                    <label class="profile-search">
                        <?= $this->partial('partials/icon', ['name' => 'search']) ?>
                        <input type="search" name="cq" value="<?= $e($connQ) ?>" placeholder="Find a member" aria-label="Find a member">
                    </label>
                    <button class="btn btn-small" type="submit">Search</button>
                </form>
            </div>
            <?php if (!empty($conn_list)): ?>
                <ul class="profile-conn-grid">
                    <?php foreach ($conn_list as $person): ?>
                        <?php $personDisplay = ($person['display_name'] ?? '') !== '' ? $person['display_name'] : $person['username']; ?>
                        <li class="profile-conn-card">
                            <?= $this->partial('partials/monogram', ['name' => $personDisplay, 'username' => $person['username']]) ?>
                            <span class="profile-conn-id">
                                <a href="/u/<?= $e($person['username']) ?>"><?= $e($personDisplay) ?></a>
                                <span class="profile-conn-meta">@<?= $e($person['username']) ?> · <?= number_format((int) ($person['reputation'] ?? 0)) ?> regard</span>
                            </span>
                            <?php if (!empty($can_remove_followers) && $connMode === 'followers'): ?>
                                <form class="inline" method="post" action="<?= $e($profileUrl) ?>/followers/<?= (int) $person['id'] ?>/remove">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn danger" type="submit">Remove follower</button>
                                </form>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php elseif ($connQ !== ''): ?>
                <div class="profile-panel-empty">
                    <h2>Nothing matches “<?= $e($connQ) ?>”</h2>
                    <p><a class="btn btn-small" href="<?= $e($connUrl(['cq' => ''])) ?>">Clear search</a></p>
                </div>
            <?php else: ?>
                <div class="profile-panel-empty">
                    <h2>No one here yet</h2>
                    <p><?= $connMode === 'followers' ? 'When members follow ' . $e($firstName) . ', they will be listed here.' : $e($firstName) . ' is not following anyone yet.' ?></p>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
