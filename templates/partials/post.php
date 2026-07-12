<?php /** @var \App\Core\View $this */ ?>
<?php
$canWrite = !empty($can_write);
$owner = $current_user !== null && $current_user->id() === (int) $p['user_id'];
// Board moderators — not just global admins — get the mod controls; the caller's
// can_delete_posts flag is the exact core.post.delete_any capability. Account
// state is orthogonal, so every write surface also consumes can_write.
$canModerate = $canWrite && !empty($can_delete_posts) && !$owner;
$isAnon = (int) ($p['is_anonymous'] ?? 0) === 1;
// Public byline is ALWAYS masked when anonymous; a mod "reveal" is a separate
// audited action (flash), never an un-mask of this render.
$a = mask_author($p['author_display_name'] ?? null, $p['author_username'] ?? null, $p['author_role'] ?? 'user', $isAnon);
?>
<?php $accepted = $accepted ?? false; ?>
<?php // A grouped post is a consecutive reply by the same (non-anonymous) author —
      // it drops the repeated avatar and name (§5.1). The OP and the accepted answer
      // always keep their full header, so they are never grouped; staff/mod/admin and
      // wiki posts are also left ungrouped so their role/Wiki badge is never hidden. ?>
<?php $grouped = ($grouped ?? false) && !$accepted && (int) $p['is_op'] !== 1
    && (($p['author_role'] ?? 'user') === 'user') && empty($p['is_wiki']); ?>
<article class="post<?= $accepted ? ' post-accepted' : '' ?><?= (int) $p['is_op'] === 1 ? ' post-op' : '' ?><?= $grouped ? ' post-grouped' : '' ?>" id="p<?= (int) $p['id'] ?>" data-post>
    <?php if ($show_avatars ?? true): ?>
        <?php if ($grouped): ?><span class="post-avatar-spacer" aria-hidden="true"></span>
        <?php else: ?>
            <div class="post-avatar">
                <?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?>
                <?php // Regard plinth (§5.1): the author's commends earned, read from the
                      // real users.reputation. Suppressed for an anonymous post so a masked
                      // byline never leaks the real author's reputation. ?>
                <?php if (!$isAnon && ($p['author_reputation'] ?? null) !== null): ?>
                    <span class="regard-block">
                        <span class="regard-n"><span class="star-marker" aria-hidden="true">✦</span><?= number_format((int) $p['author_reputation']) ?></span>
                        <span class="regard-label">Commends</span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="post-main">
        <?php if ($accepted): ?>
            <p class="accepted-flag"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>Marked as the answer<span class="star-marker" aria-hidden="true">✦</span></p>
        <?php endif; ?>
        <div class="post-head">
            <?php if (!$grouped): ?>
                <?php if ($a['profile_url'] !== null): ?>
                    <a class="post-author" href="<?= $e($a['profile_url']) ?>"><?= $e($a['label']) ?></a>
                <?php else: ?>
                    <span class="post-author"><?= $e($a['label']) ?></span>
                <?php endif; ?>
                <?php if (!$isAnon && ($p['author_title_label'] ?? null) !== null): ?>
                    <span class="post-title-chip" data-author-title="<?= $e($p['author_title_label']) ?>"><?= $e($p['author_title_label']) ?></span>
                <?php endif; ?>
                <?php if ((int) $p['is_op'] === 1): ?><span class="badge">OP</span><?php endif; ?>
                <?php if (!empty($p['is_wiki'])): ?><span class="badge">Wiki</span><?php endif; ?>
                <?php if ($a['is_staff']): ?><span class="badge badge-staff">Staff</span><?php endif; ?>
            <?php endif; ?>
            <span class="post-time"><?= $e(human_datetime($p['created_at'])) ?></span>
            <?php if (!empty($p['edited_at'])): ?><span class="muted post-edited">(edited)</span><?php endif; ?>
        </div>
        <div class="post-body">
            <?php if (($p['body_html'] ?? '') !== ''): ?>
                <?= $p['body_html'] /* pre-sanitised at write time */ ?>
            <?php else: ?>
                <p><?= $e($p['body']) ?></p>
            <?php endif; ?>
        </div>
        <?php if (!empty($reference_cards)): ?>
            <div class="reference-cards" aria-label="Referenced content">
                <?php foreach ($reference_cards as $card): ?>
                    <a class="reference-card" href="<?= $e($card['url']) ?>">
                        <span class="badge badge-muted"><?= $e($card['type']) ?></span>
                        <strong><?= $e($card['title']) ?></strong>
                        <?php if (($card['meta'] ?? '') !== ''): ?><span class="muted"><?= $e($card['meta']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($link_preview_cards)): ?>
            <div class="reference-cards" aria-label="Link previews">
                <?php foreach ($link_preview_cards as $card): ?>
                    <a class="reference-card" href="<?= $e($card['url']) ?>" rel="nofollow ugc noopener">
                        <?php if (($card['site_name'] ?? '') !== ''): ?><span class="badge badge-muted"><?= $e($card['site_name']) ?></span><?php endif; ?>
                        <strong><?= $e($card['title']) ?></strong>
                        <?php if (($card['description'] ?? '') !== ''): ?><span class="muted"><?= $e($card['description']) ?></span><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php
        // Author signature (P3-01): shown only when the reader keeps signatures on
        // and the author has one. Never shown for an anonymous post — the byline is
        // masked, so a signature would deanonymise. Plain text: escaped + nl2br.
        $authorSig = $isAnon ? '' : trim((string) ($p['author_signature'] ?? ''));
        ?>
        <?php if (($show_signatures ?? true) && $authorSig !== ''): ?>
            <div class="post-signature muted"><?= nl2br($e($authorSig)) ?></div>
        <?php endif; ?>
        <?php
        // Reactions (P2-02). $counts: emoji=>n; $mine: emoji the viewer added.
        $engagement = $engagement ?? false;
        $counts = $counts ?? [];
        $mine = $mine ?? [];
        $allowed = $allowed_emoji ?? [];
        if ($engagement && ($show_reactions ?? true)):
        ?>
        <div class="reactions" data-post="<?= (int) $p['id'] ?>">
            <?php foreach ($counts as $emoji => $n): ?>
                <?php $on = in_array($emoji, $mine, true); ?>
                <?php if ($current_user !== null && $canWrite): ?>
                    <form class="reaction-form inline" method="post" action="/posts/<?= (int) $p['id'] ?>/react">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="emoji" value="<?= $e($emoji) ?>">
                        <button type="submit" class="reaction<?= $on ? ' reaction-on' : '' ?>" aria-pressed="<?= $on ? 'true' : 'false' ?>"
                                title="<?= $on ? 'Remove your reaction' : 'React' ?>"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></button>
                    </form>
                <?php else: ?>
                    <span class="reaction reaction-static"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></span>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?= $this->partial('partials/post_toolbar', [
            'p' => $p,
            'thread' => $thread,
            'page' => $page,
            'can_write' => $canWrite,
            'can_reply' => $can_reply ?? false,
            'owner' => $owner,
            'canModerate' => $canModerate,
            'isAnon' => $isAnon,
            'accepted' => $accepted,
            'engagement' => $engagement,
            'show_reactions' => $show_reactions ?? true,
            'allowed' => $allowed,
            'can_mark_solved' => $can_mark_solved ?? false,
            'can_reveal_anon' => $can_reveal_anon ?? false,
            'memory_on' => $memory_on ?? false,
            'can_curate_wiki' => $can_curate_wiki ?? false,
            'wiki_revisions' => $wiki_revisions ?? [],
            'features' => $features ?? [],
            'edit_post_id' => $edit_post_id ?? 0,
            'edit_old' => $edit_old ?? '',
            'edit_error' => $edit_error ?? '',
        ]) ?>
    </div>
</article>
