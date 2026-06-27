<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', $thread['title']);
// SEO (P3-10): canonical URL + description for public threads; threads in a
// private/hidden board are excluded from indexing (defence in depth — the read
// gate already blocks crawlers).
$this->section('canonical', '/t/' . (int) $thread['id'] . '-' . $thread['slug']);
$this->section('og_type', 'article');
$this->section('description', mb_strimwidth(preg_replace('/\s+/', ' ', (string) $thread['title']) ?? '', 0, 160, '…'));
if (($thread['board_visibility'] ?? 'public') !== 'public') {
    $this->section('robots', 'noindex, nofollow');
}
?>
<article class="thread">
    <header class="thread-head">
        <p class="breadcrumb"><a href="/c/<?= $e($thread['board_slug']) ?>"><span class="hash">#</span><?= $e($thread['board_name']) ?></a></p>
        <h1>
            <?php if ((int) $thread['is_pinned'] === 1): ?><span class="badge">Pinned</span><?php endif; ?>
            <?php if ((int) $thread['is_locked'] === 1): ?><span class="badge badge-muted">Locked</span><?php endif; ?>
            <?php if (($accepted_post_id ?? null) !== null): ?><span class="badge badge-solved">✓ Solved</span><?php endif; ?>
            <?= $e($thread['title']) ?>
        </h1>
        <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?>
            <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept">
                <?= $this->csrfField() ?>
                <button class="linkbtn muted" type="submit">Clear accepted answer</button>
            </form>
        <?php endif; ?>
        <?php if (($engagement ?? false) && $current_user !== null): ?>
            <form class="inline star-form" method="post" action="/t/<?= (int) $thread['id'] ?>/star">
                <?= $this->csrfField() ?>
                <input type="hidden" name="return" value="/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">
                <button class="linkbtn star-btn<?= ($is_starred ?? false) ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= ($is_starred ?? false) ? 'true' : 'false' ?>">
                    <?= ($is_starred ?? false) ? '★ Starred' : '☆ Star' ?>
                </button>
            </form>
        <?php endif; ?>
        <?php if (($notifications_on ?? false) && $current_user !== null): ?>
            <?php $freq = $subscription['frequency'] ?? 'off'; ?>
            <form class="inline subscribe-form" method="post" action="/t/<?= (int) $thread['id'] ?>/subscribe">
                <?= $this->csrfField() ?>
                <label class="sr-only" for="sub-freq">Notify me</label>
                <select class="input input-small" id="sub-freq" name="frequency">
                    <option value="instant"<?= $freq === 'instant' ? ' selected' : '' ?>>Notify: Instant</option>
                    <option value="daily"<?= $freq === 'daily' ? ' selected' : '' ?>>Notify: Daily</option>
                    <option value="off"<?= $freq === 'off' ? ' selected' : '' ?>>Notify: Off</option>
                </select>
                <input type="hidden" name="in_app" value="1">
                <input type="hidden" name="email" value="1">
                <button class="linkbtn" type="submit">Save</button>
            </form>
        <?php endif; ?>
        <?php if ($is_admin): ?>
            <div class="mod-bar">
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button>
                </form>
                <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button>
                </form>
            </div>
        <?php endif; ?>
    </header>

    <?php if (empty($posts)): ?>
        <p class="muted empty">This thread has no visible posts.</p>
    <?php else: ?>
        <div class="post-stream">
            <?php foreach ($posts as $p): ?>
                <?= $this->partial('partials/post', [
                    'p' => $p,
                    'thread' => $thread,
                    'engagement' => $engagement ?? false,
                    'counts' => ($reaction_counts ?? [])[(int) $p['id']] ?? [],
                    'mine' => ($my_reactions ?? [])[(int) $p['id']] ?? [],
                    'allowed_emoji' => $allowed_emoji ?? [],
                    'accepted' => ($accepted_post_id ?? null) === (int) $p['id'],
                    'can_mark_solved' => $can_mark_solved ?? false,
                    'can_reveal_anon' => $can_reveal_anon ?? false,
                    'show_avatars' => $show_avatars ?? true,
                    'show_signatures' => $show_signatures ?? true,
                    'show_reactions' => $show_reactions ?? true,
                ]) ?>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= $this->partial('partials/pagination', [
        'page' => $page,
        'pages' => $pages,
        'base_url' => '/t/' . (int) $thread['id'] . '-' . $thread['slug'] . '?',
    ]) ?>

    <?php if ($locked): ?>
        <div class="joinbar">This thread is locked and is not accepting replies.</div>
    <?php elseif ($can_reply): ?>
        <?= $this->partial('partials/composer', ['thread' => $thread, 'reply_errors' => $reply_errors, 'reply_old' => $reply_old]) ?>
    <?php elseif ($current_user === null): ?>
        <div class="joinbar">You're browsing as a guest — <a href="/login?next=/t/<?= (int) $thread['id'] ?>-<?= $e($thread['slug']) ?>">log in</a> to reply.</div>
    <?php elseif ($current_user !== null && !$current_user->isActive()): ?>
        <div class="joinbar">Your account cannot post right now.</div>
    <?php elseif ($current_user !== null): ?>
        <div class="joinbar">You don't have permission to reply in this board.</div>
    <?php endif; ?>
</article>
