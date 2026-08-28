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
<?php // The boundary states how much is on the far side of it
      // (ThreadView.dc.html:231 `unreadLabel`). "First unread" alone names the rule
      // without answering the question a reader crossing it is holding. ?>
<?php if (!empty($p['is_first_unread'])): ?>
    <?php $unreadCount = (int) ($unread_count ?? 0); ?>
    <div class="first-unread-divider" data-first-unread-boundary role="separator"><span class="first-unread-dot" aria-hidden="true"></span><span>New since you last read<?= $unreadCount > 0 ? ' · ' . $unreadCount . ($unreadCount === 1 ? ' reply' : ' replies') : '' ?></span></div>
<?php endif; ?>
<article class="post<?= $accepted ? ' post-accepted' : '' ?><?= (int) $p['is_op'] === 1 ? ' post-op' : '' ?><?= $grouped ? ' post-grouped' : '' ?>" id="p<?= (int) $p['id'] ?>" data-post<?= !empty($p['is_first_unread']) ? ' data-first-unread="1"' : '' ?>>
    <?php if ($show_avatars ?? true): ?>
        <?php if ($grouped): ?><span class="post-avatar-spacer" aria-hidden="true"></span>
        <?php else: ?>
            <div class="post-avatar">
                <?= $this->partial('partials/monogram', ['name' => $a['mono_name'], 'username' => $a['mono_seed']]) ?>
                <?php // Regard plinth (§5.1): the author's commends earned, read from the
                      // real users.reputation. Suppressed for an anonymous post so a masked
                      // byline never leaks the real author's reputation. ?>
                <?php // The number and the mark, and the word "commends" only as the
                      // accessible name (ThreadView.dc.html:246). A printed COMMENDS
                      // caption under every avatar is the same word repeated once per
                      // post down the whole stream, in a 48px rail that cannot hold
                      // it — the mark already says which unit this is. ?>
                <?php if (!$isAnon && ($p['author_reputation'] ?? null) !== null): ?>
                    <?php $regard = number_format((int) $p['author_reputation']); ?>
                    <span class="regard-block" title="<?= $e($regard) ?> commends">
                        <span class="regard-n"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><?= $e($regard) ?></span>
                        <span class="sr-only"><?= $e($regard) ?> commends</span>
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <div class="post-main">
        <?php if ($accepted): ?>
            <p class="accepted-flag"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5"/></svg>Marked as the answer<?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?></p>
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
            <?php // Abbreviated inline stamp per the thread-view reference; the full
                  // UTC value stays machine-readable and on hover/long-press. ?>
            <time class="post-time" datetime="<?= $e(iso_datetime($p['created_at'])) ?>" title="<?= $e(human_datetime($p['created_at'])) ?>"><?= $e(post_datetime($p['created_at'])) ?></time>
            <?php if (!empty($p['edited_at'])): ?><time class="muted post-edited" datetime="<?= $e(iso_datetime($p['edited_at'])) ?>" title="<?= $e(human_datetime($p['edited_at'])) ?>">(edited)</time><?php endif; ?>
        </div>
        <div class="post-body formatted-content">
            <?= $p['body_html'] /* pre-sanitised at write time or rendered read fallback */ ?>
        </div>
        <?php /* A reference is a quotation from elsewhere in the record, and it is
                 drawn as one (ThreadView.dc.html:316-327): a gold rule, the quote
                 mark, and the board it came from stated as an eyebrow. It used to
                 wear `.badge.badge-muted` — the product's uppercase status pill,
                 the same object that shouts SOLVED and LOCKED — for the word
                 "Thread". The board hash is already the card's `meta` for a thread
                 or board target; a post or tag target has an excerpt or a
                 description there instead, and that reads as the snippet. */ ?>
        <?php if (!empty($reference_cards)): ?>
            <div class="reference-cards" aria-label="Referenced content">
                <?php foreach ($reference_cards as $card): ?>
                    <?php $refMeta = (string) ($card['meta'] ?? ''); $refIsBoard = str_starts_with($refMeta, '#'); ?>
                    <a class="reference-card" href="<?= $e($card['url']) ?>">
                        <span class="ref-mark" aria-hidden="true">&#10077;</span>
                        <span class="ref-body">
                            <span class="ref-type"><?= $refIsBoard ? $e($refMeta) : $e($card['type']) ?> · referenced</span>
                            <strong><?= $e($card['title']) ?></strong>
                            <?php if (!$refIsBoard && $refMeta !== ''): ?><span class="ref-snippet">&ldquo;<?= $e($refMeta) ?>&rdquo;</span><?php endif; ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php // Link previews (DECISIONS §6 #5). The author of the post — and anyone who
              // can moderate its board — keeps the last word on whether a card is shown;
              // `removed` rows are only ever returned to those viewers, so the take-down
              // is invisible to everyone else. Plain POSTs: this works with JS off. ?>
        <?php /* The design system ships a `.link-preview` component and this surface
                 had no consumer for it (components.css:1293): an unfurl rendered as
                 a `.reference-card` with the host name inside `.badge.badge-muted`,
                 which drew a full-width uppercase bar over every preview. The card
                 is a host line, a title and a description — the shape every reader
                 already knows an unfurl by. The captured image is deliberately never
                 painted: a remote asset would make every reader's browser announce
                 this page to the URL's operator. Copy and controls are unchanged. */ ?>
        <?php if (!empty($link_preview_cards)): ?>
            <div class="link-preview-cards" aria-label="Link previews">
                <?php foreach ($link_preview_cards as $card): ?>
                    <?php if (!empty($card['removed'])): ?>
                        <?php // Both branches state the same authorization condition. The
                              // service already withholds removed rows from viewers without
                              // manage rights, but a control that grants an action should not
                              // depend on a cross-file invariant to stay hidden. ?>
                        <?php if (!empty($card['can_manage'])): ?>
                            <div class="link-preview is-removed">
                                <span class="link-preview-note">Link preview removed from this post.</span>
                                <form class="inline link-preview-action" method="post" action="/posts/<?= (int) $p['id'] ?>/previews/<?= (int) $card['id'] ?>/restore">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn" type="submit">Restore preview</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php $previewHost = (string) ($card['site_name'] ?? ''); ?>
                        <div class="link-preview-item">
                            <a class="link-preview" href="<?= $e($card['url']) ?>" rel="nofollow ugc noopener">
                                <?php if ($previewHost !== ''): ?>
                                    <span class="link-preview-host"><span class="link-preview-mark" aria-hidden="true"><?= $e(mb_strtoupper(mb_substr($previewHost, 0, 1))) ?></span><span><?= $e($previewHost) ?></span></span>
                                <?php endif; ?>
                                <span class="link-preview-title"><?= $e($card['title']) ?></span>
                                <?php if (($card['description'] ?? '') !== ''): ?><span class="link-preview-desc"><?= $e($card['description']) ?></span><?php endif; ?>
                            </a>
                            <?php if (!empty($card['can_manage'])): ?>
                                <div class="link-preview-actions">
                                    <form class="inline link-preview-action" method="post" action="/posts/<?= (int) $p['id'] ?>/previews/<?= (int) $card['id'] ?>/remove">
                                        <?= $this->csrfField() ?>
                                        <button class="linkbtn is-danger" type="submit" aria-label="Remove the link preview for <?= $e($card['title']) ?>">Remove preview</button>
                                    </form>
                                    <span class="link-preview-hint"><?= $owner ? 'Only you and the wardens see this control.' : 'You hold post.delete_any on this board.' ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
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
                        <?php /* reaction-bare: the "·" in .reaction-n::before separates a
                                 reaction's NAME from its count, and production reactions are
                                 raw emoji with no name (ReactionService::ALLOWED). Unconditional
                                 because there is no named form to render. */ ?>
                        <button type="submit" class="reaction<?= $on ? ' reaction-on' : '' ?> reaction-bare" aria-pressed="<?= $on ? 'true' : 'false' ?>"
                                title="<?= $on ? 'Remove your reaction' : 'React' ?>"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></button>
                    </form>
                <?php else: ?>
                    <span class="reaction reaction-static reaction-bare"><?= $e($emoji) ?> <span class="reaction-n"><?= (int) $n ?></span></span>
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
