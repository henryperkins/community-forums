<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Edit board'); $errors = $errors ?? []; $old = $old ?? []; ?>
<div class="admin">
    <header class="admin-head">
        <h1>Edit board</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'structure', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <?php if (!empty($roster_error ?? null)): ?>
        <div class="flash flash-error" role="alert"><?= $e($roster_error) ?></div>
    <?php endif; ?>
    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>" class="stacked card">
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
            <input type="text" name="slug" class="input" maxlength="64" value="<?= $e($old['slug'] ?? $board['slug']) ?>"<?= field_attrs($errors, 'slug') ?>>
            <span class="muted">Changing the slug keeps the old one working via a redirect.</span>
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
            <span class="muted">Everyone who can read the board still sees its content; this only limits who may start topics and reply.</span>
            <?= field_error($errors, 'post_min_role') ?>
        </label>

        <label class="field"><span>Edit window (minutes, 0 = no limit)</span>
            <?php $editWindow = $old['edit_window_minutes'] ?? (string) intdiv((int) ($board['edit_window_seconds'] ?? 0), 60); ?>
            <input type="number" name="edit_window_minutes" class="input" min="0" max="10080" value="<?= $e((string) $editWindow) ?>"<?= field_attrs($errors, 'edit_window_minutes') ?>>
            <span class="muted">How long members may edit their own posts here. Staff are exempt.</span>
            <?= field_error($errors, 'edit_window_minutes') ?>
        </label>

        <?php $anon = $old['allow_anonymous'] ?? ($board['allow_anonymous'] ?? 0); ?>
        <label class="checkline"><input type="checkbox" name="allow_anonymous" value="1" <?= !empty($anon) ? 'checked' : '' ?>> Allow anonymous posting <span class="muted">(members may hide their name from other members; moderators can still reveal the author)</span></label>

        <?php $reqApproval = $old['require_approval'] ?? ($board['require_approval'] ?? 0); ?>
        <label class="checkline"><input type="checkbox" name="require_approval" value="1" <?= !empty($reqApproval) ? 'checked' : '' ?>> Require approval before posts appear <span class="muted">(new threads and replies are held for a moderator to release; admins and board moderators post without holds)</span></label>

        <label class="field"><span>Assignment mode</span>
            <?php $assignmentMode = $old['assignment_mode'] ?? ($board['assignment_mode'] ?? 'off'); ?>
            <select name="assignment_mode" class="input">
                <option value="off" <?= $assignmentMode === 'off' ? 'selected' : '' ?>>Off</option>
                <option value="self" <?= $assignmentMode === 'self' ? 'selected' : '' ?>>Members can assign themselves</option>
                <option value="staff" <?= $assignmentMode === 'staff' ? 'selected' : '' ?>>Staff can assign members</option>
            </select>
        </label>

        <?php $tagsEnabled = $old['tags_enabled'] ?? ($board['tags_enabled'] ?? 1); ?>
        <label class="checkline"><input type="checkbox" name="tags_enabled" value="1" <?= !empty($tagsEnabled) ? 'checked' : '' ?>> Allow approved tags on this board</label>

        <?php $wikiEnabled = $old['wiki_enabled'] ?? ($board['wiki_enabled'] ?? 0); ?>
        <label class="checkline"><input type="checkbox" name="wiki_enabled" value="1" <?= !empty($wikiEnabled) ? 'checked' : '' ?>> Allow wiki-style post editing</label>

        <div class="form-actions">
            <button class="btn" type="submit">Save board</button>
            <a class="linkbtn" href="/admin/structure">Cancel</a>
        </div>
    </form>

    <section class="card">
        <h2>Moderators</h2>
        <p class="muted">Board moderators can pin, lock, move, and remove content in <strong><?= $e($board['name']) ?></strong>. Administrators already moderate every board.</p>
        <ul class="admin-board-list">
            <?php foreach (($moderators ?? []) as $mod): ?>
                <li class="admin-board-row">
                    <span><a href="/u/<?= $e($mod['username']) ?>">@<?= $e($mod['username']) ?></a>
                        <?php if (!empty($mod['display_name'])): ?><span class="muted"><?= $e($mod['display_name']) ?></span><?php endif; ?>
                    </span>
                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/moderators/remove" class="inline">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $mod['user_id'] ?>">
                        <button class="linkbtn danger" type="submit" aria-label="Remove @<?= $e($mod['username']) ?> as moderator">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (empty($moderators)): ?>
                <li class="admin-board-row"><span class="muted">No board moderators yet — only administrators moderate this board.</span></li>
            <?php endif; ?>
        </ul>
        <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/moderators" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="username" class="input" placeholder="username" maxlength="32" aria-label="Username to assign as moderator" value="<?= $e(($roster_context ?? null) === 'moderator' ? ($roster_username ?? '') : '') ?>" required>
            <button class="btn btn-small" type="submit">Assign moderator</button>
        </form>
    </section>

    <section class="card">
        <h2>Members <span class="muted">— private &amp; hidden boards</span></h2>
        <p class="muted">Members can read and post here when this board is <strong>private</strong> or <strong>hidden</strong>. On a public board everyone already has access, so membership has no effect. Removing a member revokes their read, search, unread, and notification access immediately.</p>
        <ul class="admin-board-list">
            <?php foreach (($members ?? []) as $m): ?>
                <li class="admin-board-row">
                    <span><a href="/u/<?= $e($m['username']) ?>">@<?= $e($m['username']) ?></a>
                        <?php if (!empty($m['display_name'])): ?><span class="muted"><?= $e($m['display_name']) ?></span><?php endif; ?>
                    </span>
                    <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/members/remove" class="inline">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $m['user_id'] ?>">
                        <button class="linkbtn danger" type="submit" aria-label="Remove @<?= $e($m['username']) ?> as member">Remove</button>
                    </form>
                </li>
            <?php endforeach; ?>
            <?php if (empty($members)): ?>
                <li class="admin-board-row"><span class="muted">No members yet.</span></li>
            <?php endif; ?>
        </ul>
        <form method="post" action="/admin/boards/<?= (int) $board['id'] ?>/members" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="text" name="username" class="input" placeholder="username" maxlength="32" aria-label="Username to add as member" value="<?= $e(($roster_context ?? null) === 'member' ? ($roster_username ?? '') : '') ?>" required>
            <button class="btn btn-small" type="submit">Add member</button>
        </form>
    </section>
    </div>
</div>
