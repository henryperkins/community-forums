<?php /** @var \App\Core\View $this */ ?>
<?php
$owner = $current_user !== null && $current_user->id() === (int) $p['user_id'];
$admin = $current_user?->isAdmin() ?? false;
$canModerate = $admin && !$owner;
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
<div class="post<?= $accepted ? ' post-accepted' : '' ?><?= (int) $p['is_op'] === 1 ? ' post-op' : '' ?><?= $grouped ? ' post-grouped' : '' ?>" id="p<?= (int) $p['id'] ?>">
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
                <?php if ((int) $p['is_op'] === 1): ?><span class="badge">OP</span><?php endif; ?>
                <?php if (!empty($p['is_wiki'])): ?><span class="badge">Wiki</span><?php endif; ?>
                <?php if ($a['is_staff']): ?><span class="badge badge-staff">Staff</span><?php endif; ?>
            <?php endif; ?>
            <span class="post-time"><?= $e(human_datetime($p['created_at'])) ?></span>
            <?php if (!empty($p['edited_at'])): ?><span class="muted post-edited">(edited)</span><?php endif; ?>
            <?php if ($isAnon && !empty($can_reveal_anon)): ?>
                <form class="inline reveal-anon" method="post" action="/mod/p/<?= (int) $p['id'] ?>/reveal">
                    <?= $this->csrfField() ?>
                    <button class="linkbtn muted" type="submit" title="Reveal the real author — this is logged">Reveal author</button>
                </form>
            <?php endif; ?>
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
            $threadPath = '/t/' . (int) $thread['id'] . '-' . $thread['slug'];
        ?>
        <div class="reactions" data-post="<?= (int) $p['id'] ?>">
            <?php foreach ($counts as $emoji => $n): ?>
                <?php $on = in_array($emoji, $mine, true); ?>
                <?php if ($current_user !== null): ?>
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
            <?php if ($current_user !== null && $allowed !== []): ?>
                <details class="reaction-add">
                    <summary class="reaction reaction-pick" title="Add a reaction">＋</summary>
                    <div class="reaction-menu">
                        <?php foreach ($allowed as $emoji): ?>
                            <form class="reaction-form inline" method="post" action="/posts/<?= (int) $p['id'] ?>/react">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="emoji" value="<?= $e($emoji) ?>">
                                <button type="submit" class="reaction"><?= $e($emoji) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($can_mark_solved) && empty($accepted) && (int) $p['is_op'] === 0): ?>
            <form class="inline solved-action" method="post" action="/posts/<?= (int) $p['id'] ?>/accept">
                <?= $this->csrfField() ?>
                <button class="linkbtn" type="submit">✓ Accept as answer</button>
            </form>
        <?php endif; ?>
        <?php if ($owner || $canModerate): ?>
            <div class="post-actions">
                <?php if ($owner): ?>
                    <?php // When an edit fails validation the controller re-renders the thread with
                          // this post's edit form re-opened and the rejected text + error preserved
                          // (edit_post_id / edit_old / edit_error), instead of dropping the typed edit. ?>
                    <?php $editingThis = ($edit_post_id ?? 0) === (int) $p['id']; ?>
                    <details class="post-edit"<?= $editingThis ? ' open' : '' ?>>
                        <summary class="linkbtn">Edit</summary>
                        <?php if ($editingThis && ($edit_error ?? '') !== ''): ?><p class="field-error"><?= $e($edit_error) ?></p><?php endif; ?>
                        <?php // data-no-draft: the textarea is pre-filled with the current body, so a
                              // local draft can never be restored here; opting out avoids a misleading,
                              // unrecoverable "Post edit" draft that the next page load would discard. ?>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/edit" class="composer" data-composer-context="edit" data-composer-target-id="<?= (int) $p['id'] ?>" data-no-draft>
                            <?= $this->csrfField() ?>
                            <textarea name="body" rows="4" class="composer-input" maxlength="20000" required><?= $e($editingThis ? (string) ($edit_old ?? '') : $p['body']) ?></textarea>
                            <button class="btn btn-small" type="submit">Save changes</button>
                        </form>
                    </details>
                    <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete" class="inline">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn danger" type="submit">Delete</button>
                    </form>
                <?php elseif ($canModerate): ?>
                    <details class="post-edit">
                        <summary class="linkbtn danger">Remove (mod)</summary>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete" class="composer">
                            <?= $this->csrfField() ?>
                            <input type="text" name="reason" class="input" placeholder="Reason (required)" maxlength="255" required>
                            <button class="btn btn-small danger" type="submit">Remove post</button>
                        </form>
                    </details>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($memory_on) && !empty($can_curate_wiki)): ?>
            <div class="post-actions">
                <?php if (empty($p['is_wiki'])): ?>
                    <form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki" class="inline">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn" type="submit">Make wiki</button>
                    </form>
                <?php else: ?>
                    <details class="post-edit">
                        <summary class="linkbtn">Edit wiki</summary>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki/edit" class="composer" data-composer-context="edit" data-composer-target-id="<?= (int) $p['id'] ?>" data-no-draft>
                            <?= $this->csrfField() ?>
                            <textarea name="body" rows="4" class="composer-input" maxlength="20000" required><?= $e($p['body']) ?></textarea>
                            <input type="text" name="reason" class="input" maxlength="255" placeholder="Reason">
                            <button class="btn btn-small" type="submit">Save wiki edit</button>
                        </form>
                    </details>
                    <?php // Revert lives with the other curator (admin + board moderator) wiki
                          // tools so every authorized curator can reach it — not just non-owner
                          // admins. $wiki_revisions is only populated for curators by ThreadController. ?>
                    <?php if (!empty($wiki_revisions)): ?>
                        <form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki/revert" class="inline-form">
                            <?= $this->csrfField() ?>
                            <label class="sr-only" for="wiki-revision-<?= (int) $p['id'] ?>">Revision</label>
                            <select id="wiki-revision-<?= (int) $p['id'] ?>" class="input input-small" name="revision_id">
                                <?php foreach ($wiki_revisions as $rev): ?>
                                    <option value="<?= (int) $rev['id'] ?>">#<?= (int) $rev['id'] ?> · @<?= $e($rev['editor_username']) ?> · <?= $e(human_datetime($rev['created_at'])) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="linkbtn muted" type="submit">Revert wiki</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($features['moderation_queue']) && $current_user !== null && !$owner): ?>
            <div class="post-report">
                <details>
                    <summary class="linkbtn muted">Report</summary>
                    <form method="post" action="/posts/<?= (int) $p['id'] ?>/report" class="composer">
                        <?= $this->csrfField() ?>
                        <select name="reason_code" class="input input-small">
                            <?php foreach (['spam', 'harassment', 'off_topic', 'nsfw', 'illegal', 'other'] as $rc): ?>
                                <option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="reason" class="input" placeholder="Details (optional)" maxlength="255">
                        <label class="checkline"><input type="checkbox" name="notify_reporter" value="1"> Notify me of the outcome</label>
                        <button class="btn btn-small" type="submit">Submit report</button>
                    </form>
                </details>
            </div>
        <?php endif; ?>
    </div>
</div>
