<?php /** @var \App\Core\View $this */ ?>
<?php
$canWrite = !empty($can_write);
$canReply = $canWrite && !empty($can_reply);
$canOwnerWrite = $canWrite && !empty($owner);
$canRemove = $canWrite && !empty($canModerate);
$canReveal = $canWrite && !empty($isAnon) && !empty($can_reveal_anon);
$canWiki = $canWrite && !empty($memory_on) && !empty($can_curate_wiki);
$canReport = $canWrite && !empty($features['moderation_queue']) && empty($owner);
$canReact = $canWrite && !empty($engagement) && ($show_reactions ?? true) && $allowed !== [];
$canAccept = $canWrite && !empty($can_mark_solved) && empty($accepted) && (int) $p['is_op'] === 0;
$editingThis = ($edit_post_id ?? 0) === (int) $p['id'];
$opRemoval = (int) $p['is_op'] === 1;
$permalink = '/t/' . (int) $thread['id'] . '-' . (string) $thread['slug']
    . ((int) $page > 1 ? '?page=' . (int) $page : '')
    . '#p' . (int) $p['id'];
?>
<?php if ($current_user !== null): ?>
<div class="post-toolbar" data-post-toolbar>
    <?php if ($canReact): ?>
        <details class="reaction-add post-toolbar-reactions">
            <summary class="post-toolbar-button" aria-label="Add a reaction"><?= $this->partial('partials/icon', ['name' => 'plus']) ?></summary>
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
    <?php if ($canReply): ?>
        <button type="button" class="post-toolbar-button" data-quote-post hidden aria-label="Quote in your reply"><?= $this->partial('partials/icon', ['name' => 'quote']) ?></button>
    <?php endif; ?>
    <?php if ($canAccept): ?>
        <form method="post" action="/posts/<?= (int) $p['id'] ?>/accept">
            <?= $this->csrfField() ?>
            <button class="post-toolbar-button" type="submit" aria-label="Accept as answer"><?= $this->partial('partials/icon', ['name' => 'check']) ?></button>
        </form>
    <?php endif; ?>
    <details class="post-menu" data-post-menu>
        <summary class="post-toolbar-button" aria-label="More post actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
        <div class="post-menu-pop">
            <a href="<?= $e($permalink) ?>" data-copy-post><?= $this->partial('partials/icon', ['name' => 'copy']) ?><span>Copy link</span></a>
            <?php if ($canOwnerWrite): ?>
                <button type="button" data-post-disclosure-open="post-edit-<?= (int) $p['id'] ?>" hidden><?= $this->partial('partials/icon', ['name' => 'edit-3']) ?><span>Edit</span></button>
                <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete">
                    <?= $this->csrfField() ?>
                    <button class="danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'x']) ?><span><?= $opRemoval ? 'Delete topic' : 'Delete' ?></span></button>
                </form>
            <?php endif; ?>
            <?php if ($canRemove): ?>
                <button type="button" class="danger" data-post-disclosure-open="post-remove-<?= (int) $p['id'] ?>" hidden><?= $this->partial('partials/icon', ['name' => 'flag']) ?><span><?= $opRemoval ? 'Remove topic (warden)' : 'Remove (warden)' ?></span></button>
            <?php endif; ?>
            <?php if ($canReveal): ?>
                <form method="post" action="/mod/p/<?= (int) $p['id'] ?>/reveal">
                    <?= $this->csrfField() ?>
                    <button type="submit"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>Reveal author — logged</span></button>
                </form>
            <?php endif; ?>
            <?php if ($canWiki && empty($p['is_wiki'])): ?>
                <form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki">
                    <?= $this->csrfField() ?>
                    <button type="submit"><?= $this->partial('partials/icon', ['name' => 'edit-3']) ?><span>Make wiki</span></button>
                </form>
            <?php endif; ?>
            <?php if ($canReport): ?>
                <button type="button" data-post-disclosure-open="post-report-<?= (int) $p['id'] ?>" hidden><?= $this->partial('partials/icon', ['name' => 'flag']) ?><span>Report</span></button>
            <?php endif; ?>
        </div>
    </details>
</div>

<?php if ($canOwnerWrite): ?>
    <div class="post-actions">
        <?php // Failed edits reopen with the rejected body and validation error. ?>
        <details class="post-native-disclosure post-edit" id="post-edit-<?= (int) $p['id'] ?>"<?= $editingThis ? ' open' : '' ?>>
            <summary class="linkbtn">Edit</summary>
            <?php if ($editingThis && ($edit_error ?? '') !== ''): ?><p class="field-error"><?= $e($edit_error) ?></p><?php endif; ?>
            <form method="post" action="/posts/<?= (int) $p['id'] ?>/edit" class="composer" data-composer-context="edit" data-composer-target-id="<?= (int) $p['id'] ?>" data-no-draft>
                <?= $this->csrfField() ?>
                <textarea name="body" rows="4" class="composer-input" maxlength="20000" required><?= $e($editingThis ? (string) ($edit_old ?? '') : $p['body']) ?></textarea>
                <button class="btn btn-small" type="submit">Save changes</button>
            </form>
        </details>
    </div>
<?php endif; ?>

<?php if ($canRemove): ?>
    <div class="post-actions">
        <details class="post-native-disclosure post-edit" id="post-remove-<?= (int) $p['id'] ?>">
            <summary class="linkbtn danger"><?= $opRemoval ? 'Remove topic (warden)' : 'Remove (warden)' ?></summary>
            <form method="post" action="/posts/<?= (int) $p['id'] ?>/delete" class="composer">
                <?= $this->csrfField() ?>
                <input type="text" name="reason" class="input" placeholder="Reason (required)" maxlength="255" required>
                <button class="btn btn-small danger" type="submit"><?= $opRemoval ? 'Remove topic' : 'Remove post' ?></button>
            </form>
        </details>
    </div>
<?php endif; ?>

<?php if ($canWiki && !empty($p['is_wiki'])): ?>
    <div class="post-actions">
        <details class="post-edit">
            <summary class="linkbtn">Edit wiki</summary>
            <form method="post" action="/posts/<?= (int) $p['id'] ?>/wiki/edit" class="composer" data-composer-context="edit" data-composer-target-id="<?= (int) $p['id'] ?>" data-no-draft>
                <?= $this->csrfField() ?>
                <textarea name="body" rows="4" class="composer-input" maxlength="20000" required><?= $e($p['body']) ?></textarea>
                <input type="text" name="reason" class="input" maxlength="255" placeholder="Reason">
                <button class="btn btn-small" type="submit">Save wiki edit</button>
            </form>
        </details>
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
    </div>
<?php endif; ?>

<?php if ($canReport): ?>
    <div class="post-report">
        <details class="post-native-disclosure" id="post-report-<?= (int) $p['id'] ?>">
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
<?php endif; ?>
