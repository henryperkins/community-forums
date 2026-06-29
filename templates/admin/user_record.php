<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'User · ' . ($subject['username'] ?? ''));
$display = ($subject['display_name'] ?? '') !== '' ? $subject['display_name'] : ($subject['username'] ?? '');
?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($display) ?> <span class="muted">@<?= $e($subject['username']) ?></span></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a href="/admin/structure">Boards &amp; categories</a>
        <a class="active" href="/admin/users">Users</a>
    </nav>

    <section class="card">
        <h2>Identity</h2>
        <dl class="profile-stats">
            <div><dt>Role</dt><dd><?= $e($subject['role']) ?></dd></div>
            <div><dt>State</dt><dd><?= $e($subject['status']) ?></dd></div>
            <div><dt>Reputation</dt><dd><?= (int) $subject['reputation'] ?></dd></div>
            <div><dt>Profile</dt><dd><a href="/u/<?= $e($subject['username']) ?>">View public profile</a></dd></div>
        </dl>
    </section>

    <section class="card">
        <h2>Cosmetic title</h2>
        <p class="muted">Effective: <strong><?= $e($effective_title) ?></strong> · Derived ladder: <?= $e($derived_title) ?></p>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/title" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Title override</span>
                <input type="text" name="title" class="input" maxlength="64" value="<?= $e($old['title'] ?? ($stored_title ?? '')) ?>">
            </label>
            <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>
            <div class="form-actions"><button class="btn" type="submit">Save title</button></div>
        </form>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/title" class="inline-form">
            <?= $this->csrfField() ?>
            <input type="hidden" name="title" value="">
            <button class="btn btn-small" type="submit">Clear (revert to derived)</button>
        </form>
    </section>

    <?php if (!empty($profile_media)): ?>
        <section class="card">
            <h2>Profile media</h2>
            <?php if (!empty($subject['signature'])): ?>
                <p class="muted">Current signature: <?= nl2br($e($subject['signature'])) ?></p>
                <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/signature/remove" class="inline-form">
                    <?= $this->csrfField() ?>
                    <button class="btn btn-small danger" type="submit">Remove signature</button>
                </form>
            <?php else: ?>
                <p class="muted">No signature set.</p>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <section class="card">
        <h2>Badges</h2>
        <h3>Grant a manual badge</h3>
        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/badges/grant" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Badge</span>
                <select name="slug" class="input" required>
                    <?php foreach ($catalogue as $b): ?>
                        <option value="<?= $e($b['slug']) ?>"><?= $e($b['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!empty($errors['slug'])): ?><p class="field-error"><?= $e($errors['slug']) ?></p><?php endif; ?>
            <label class="field">
                <span>Reason (optional)</span>
                <input type="text" name="reason" class="input" maxlength="255" value="<?= $e($old['reason'] ?? '') ?>">
            </label>
            <div class="form-actions"><button class="btn" type="submit">Grant badge</button></div>
        </form>

        <h3>Held manual badges</h3>
        <?php if (empty($held_manual)): ?>
            <p class="muted">No manual badges granted.</p>
        <?php else: ?>
            <ul class="link-list">
                <?php foreach ($held_manual as $b): ?>
                    <li>
                        <span class="badge-icon" aria-hidden="true"><?= $e($b['icon'] ?? '🏷️') ?></span>
                        <?= $e($b['name']) ?>
                        <form method="post" action="/admin/users/<?= (int) $subject['id'] ?>/badges/revoke" class="inline">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="slug" value="<?= $e($b['slug']) ?>">
                            <button class="linkbtn muted" type="submit">Revoke</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>
