<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Edit board'); $this->section('variant', 'admin'); $errors = $errors ?? []; $old = $old ?? []; ?>
<?= $this->partial('admin/_console', [
    'area' => 'content',
    'tab' => 'structure',
    'pane_class' => 'admin-content admin-content-board-edit',
]) ?>
    <?= $this->partial('partials/back_link', ['href' => '/admin/structure', 'label' => 'All boards']) ?>
    <h2 class="admin-record-title">Edit board</h2>
    <?php if (!empty($roster_error ?? null)): ?>
        <div class="content-alert content-alert-danger" role="alert"><?= $e($roster_error) ?></div>
    <?php endif; ?>

    <!-- FA-09 extrapolation: the design draws this Edit destination but does not
         model it. Settings reuse the Add-board grid; rosters reuse category-card
         heads and board-row anatomy while every production field and POST stays. -->
    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>" class="stacked card content-board-grid content-board-edit-form">
        <?= $this->csrfField() ?>
        <label class="field"><span>Category</span>
            <select name="category_id" class="input"<?= field_attrs($errors, 'category_id') ?>>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category['id'] ?>" <?= (int) ($old['category_id'] ?? $board['category_id']) === (int) $category['id'] ? 'selected' : '' ?>>#<?= $e($category['name']) ?></option>
                <?php endforeach; ?>
            </select>
            <?= field_error($errors, 'category_id') ?>
        </label>

        <label class="field"><span>Name</span>
            <input type="text" name="name" class="input" maxlength="80" value="<?= $e($old['name'] ?? $board['name']) ?>"<?= field_attrs($errors, 'name') ?> required>
            <?= field_error($errors, 'name') ?>
        </label>

        <label class="field"><span>Slug</span>
            <input type="text" name="slug" class="input content-mono-input" maxlength="64" value="<?= $e($old['slug'] ?? $board['slug']) ?>"<?= field_attrs($errors, 'slug') ?>>
            <span class="content-field-help">Changing the slug keeps the old one working via a redirect.</span>
            <?= field_error($errors, 'slug') ?>
        </label>

        <label class="field"><span>Description</span>
            <input type="text" name="description" class="input" maxlength="255" value="<?= $e($old['description'] ?? $board['description'] ?? '') ?>"<?= field_attrs($errors, 'description') ?>>
            <?= field_error($errors, 'description') ?>
        </label>

        <label class="field"><span>Visibility</span>
            <?php $vis = $old['visibility'] ?? $board['visibility']; ?>
            <select name="visibility" class="input"<?= field_attrs($errors, 'visibility') ?>>
                <option value="public" <?= $vis === 'public' ? 'selected' : '' ?>>Public</option>
                <option value="hidden" <?= $vis === 'hidden' ? 'selected' : '' ?>>Hidden (unlisted)</option>
                <option value="private" <?= $vis === 'private' ? 'selected' : '' ?>>Private (members only)</option>
            </select>
            <?= field_error($errors, 'visibility') ?>
        </label>

        <label class="field"><span>Who can post</span>
            <?php $minRole = $old['post_min_role'] ?? ($board['post_min_role'] ?? 'user'); ?>
            <select name="post_min_role" class="input"<?= field_attrs($errors, 'post_min_role') ?>>
                <option value="user" <?= $minRole === 'user' ? 'selected' : '' ?>>All members</option>
                <option value="moderator" <?= $minRole === 'moderator' ? 'selected' : '' ?>>Moderators and admins</option>
                <option value="admin" <?= $minRole === 'admin' ? 'selected' : '' ?>>Admins only (announcements)</option>
            </select>
            <span class="content-field-help">Everyone who can read the board still sees its content; this only limits who may start topics and reply.</span>
            <?= field_error($errors, 'post_min_role') ?>
        </label>

        <label class="field"><span>Edit window (minutes, 0 = no limit)</span>
            <?php $editWindow = $old['edit_window_minutes'] ?? (string) intdiv((int) ($board['edit_window_seconds'] ?? 0), 60); ?>
            <input type="number" name="edit_window_minutes" class="input content-mono-input content-edit-window-input" min="0" max="10080" value="<?= $e((string) $editWindow) ?>"<?= field_attrs($errors, 'edit_window_minutes') ?>>
            <span class="content-field-help">How long members may edit their own posts here. Staff are exempt.</span>
            <?= field_error($errors, 'edit_window_minutes') ?>
        </label>

        <label class="field"><span>Assignment mode</span>
            <?php $assignmentMode = $old['assignment_mode'] ?? ($board['assignment_mode'] ?? 'off'); ?>
            <select name="assignment_mode" class="input">
                <option value="off" <?= $assignmentMode === 'off' ? 'selected' : '' ?>>Off</option>
                <option value="self" <?= $assignmentMode === 'self' ? 'selected' : '' ?>>Members can assign themselves</option>
                <option value="staff" <?= $assignmentMode === 'staff' ? 'selected' : '' ?>>Staff can assign members</option>
            </select>
        </label>

        <div class="content-check-grid content-check-grid-detailed">
            <?php $anon = $old['allow_anonymous'] ?? ($board['allow_anonymous'] ?? 0); ?>
            <label class="checkline"><input type="hidden" name="allow_anonymous" value="0"><input type="checkbox" name="allow_anonymous" value="1" <?= !empty($anon) ? 'checked' : '' ?>> <span>Allow anonymous posting <span class="content-field-help">Members may hide their name from other members; moderators can still reveal the author.</span></span></label>

            <?php $reqApproval = $old['require_approval'] ?? ($board['require_approval'] ?? 0); ?>
            <label class="checkline"><input type="hidden" name="require_approval" value="0"><input type="checkbox" name="require_approval" value="1" <?= !empty($reqApproval) ? 'checked' : '' ?>> <span>Require approval before posts appear <span class="content-field-help">New threads and replies are held for a moderator; admins and board moderators post without holds.</span></span></label>

            <?php $tagsEnabled = $old['tags_enabled'] ?? ($board['tags_enabled'] ?? 1); ?>
            <label class="checkline"><input type="hidden" name="tags_enabled" value="0"><input type="checkbox" name="tags_enabled" value="1" <?= !empty($tagsEnabled) ? 'checked' : '' ?>> <span>Allow approved tags on this board</span></label>

            <?php $wikiEnabled = $old['wiki_enabled'] ?? ($board['wiki_enabled'] ?? 0); ?>
            <label class="checkline"><input type="hidden" name="wiki_enabled" value="0"><input type="checkbox" name="wiki_enabled" value="1" <?= !empty($wikiEnabled) ? 'checked' : '' ?>> <span>Allow wiki-style post editing</span></label>

            <?php // Per-board link-preview opt-in (DECISIONS §6 #5). Rendered only while
                  // the flag is on; the service keeps the stored value when the field is
                  // absent, so a rollback never silently revokes an opt-in. ?>
            <?php if (!empty($features['link_previews'])): ?>
                <?php $previewsEnabled = $old['link_previews_enabled'] ?? ($board['link_previews_enabled'] ?? 0); ?>
                <label class="checkline"><input type="hidden" name="link_previews_enabled" value="0"><input type="checkbox" name="link_previews_enabled" value="1" <?= !empty($previewsEnabled) ? 'checked' : '' ?>> <span>Unfurl link previews on this board <span class="content-field-help">Public boards only. The server fetches metadata from allowlisted hosts &mdash; see <a href="/admin/link-previews">Link previews</a>.</span></span></label>
            <?php endif; ?>
        </div>

        <div class="form-actions content-grid-actions">
            <button class="btn" type="submit">Save board</button>
            <a class="btn btn-secondary" href="/admin/structure">Cancel</a>
        </div>
    </form>

    <section class="card admin-cat content-roster-card">
        <div class="admin-cat-head content-roster-head">
            <h2>Moderators</h2>
        </div>
        <div class="content-roster-body">
            <p class="muted">Board moderators can pin, lock, move, and remove content in <strong><?= $e($board['name']) ?></strong>. Administrators already moderate every board.</p>
            <ul class="admin-board-list content-roster-list">
                <?php foreach (($moderators ?? []) as $mod): ?>
                    <li class="admin-board-row content-roster-row">
                        <span><a href="/u/<?= $e($mod['username']) ?>">@<?= $e($mod['username']) ?></a>
                            <?php if (!empty($mod['display_name'])): ?><span class="muted"><?= $e($mod['display_name']) ?></span><?php endif; ?>
                        </span>
                        <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/moderators/remove" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $mod['user_id'] ?>">
                            <button class="content-board-action content-board-action-danger" type="submit" aria-label="Remove @<?= $e($mod['username']) ?> as moderator">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($moderators)): ?>
                    <li class="admin-board-row content-roster-row content-roster-empty"><span>No board moderators yet — only administrators moderate this board.</span></li>
                <?php endif; ?>
            </ul>
            <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/moderators" class="inline-form content-roster-form">
                <?= $this->csrfField() ?>
                <input type="text" name="username" class="input" placeholder="username" maxlength="32" aria-label="Username to assign as moderator" value="<?= $e(($roster_context ?? null) === 'moderator' ? ($roster_username ?? '') : '') ?>" required>
                <button class="btn btn-small" type="submit">Assign moderator</button>
            </form>
        </div>
    </section>

    <section class="card admin-cat content-roster-card">
        <div class="admin-cat-head content-roster-head">
            <h2>Members <span class="muted">— private &amp; hidden boards</span></h2>
        </div>
        <div class="content-roster-body">
            <p class="muted">Members can read and post here when this board is <strong>private</strong> or <strong>hidden</strong>. On a public board everyone already has access, so membership has no effect. Removing a member revokes their read, search, unread, and notification access immediately.</p>
            <ul class="admin-board-list content-roster-list">
                <?php foreach (($members ?? []) as $m): ?>
                    <li class="admin-board-row content-roster-row">
                        <span><a href="/u/<?= $e($m['username']) ?>">@<?= $e($m['username']) ?></a>
                            <?php if (!empty($m['display_name'])): ?><span class="muted"><?= $e($m['display_name']) ?></span><?php endif; ?>
                        </span>
                        <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/members/remove" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                            <button class="content-board-action content-board-action-danger" type="submit" aria-label="Remove @<?= $e($m['username']) ?> as member">Remove</button>
                        </form>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($members)): ?>
                    <li class="admin-board-row content-roster-row content-roster-empty"><span>No members yet.</span></li>
                <?php endif; ?>
            </ul>
            <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/members" class="inline-form content-roster-form">
                <?= $this->csrfField() ?>
                <input type="text" name="username" class="input" placeholder="username" maxlength="32" aria-label="Username to add as member" value="<?= $e(($roster_context ?? null) === 'member' ? ($roster_username ?? '') : '') ?>" required>
                <button class="btn btn-small" type="submit">Add member</button>
            </form>
        </div>
    </section>
<?= $this->partial('admin/_console_end') ?>
