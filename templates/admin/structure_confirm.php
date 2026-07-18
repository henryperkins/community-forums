<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $page_title); ?>
<div class="admin">
    <header class="admin-head">
        <h1><?= $e($page_title) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'structure', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <section class="card confirm-card">
            <h2><?= $e($heading) ?></h2>
            <p><?= $e($intro) ?></p>

            <?php if (!empty($impact)): ?>
                <dl class="impact-list">
                    <?php foreach ($impact as $row): ?>
                        <dt><?= $e($row['label']) ?></dt>
                        <dd><?= $e((string) $row['value']) ?></dd>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>

            <?php if (!empty($blocked)): ?>
                <div class="flash flash-error"><?= $e($blocked_reason) ?></div>
                <div class="form-actions">
                    <a class="btn" href="/admin/structure">Back to structure</a>
                </div>
            <?php else: ?>
                <?php if (!empty($error)): ?>
                    <div class="flash flash-error"><?= $e($error) ?></div>
                <?php endif; ?>
                <form method="post" action="<?= $e($action) ?>" class="stacked confirm-form">
                    <?= $this->csrfField() ?>
                    <?php if (!empty($move_options ?? [])): ?>
                        <label class="field">
                            <span><?= $e($move_label ?? 'Move threads to') ?></span>
                            <select name="move_to_board_id" class="input" required>
                                <option value="">Choose a destination board…</option>
                                <?php foreach ($move_options as $option): ?>
                                    <option value="<?= (int) $option['id'] ?>"<?= (int) ($move_selected ?? 0) === (int) $option['id'] ? ' selected' : '' ?>><?= $e($option['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                    <?php endif; ?>
                    <label class="field">
                        <span>Type the <?= $e($confirm_noun) ?> <code><?= $e($confirm_target) ?></code> to confirm</span>
                        <input type="text" name="confirm" class="input" autocomplete="off" autocapitalize="off" spellcheck="false" required>
                    </label>
                    <div class="form-actions">
                        <button class="btn<?= !empty($danger) ? ' danger' : '' ?>" type="submit"><?= $e($submit_label) ?></button>
                        <a class="linkbtn" href="/admin/structure">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </div>
</div>
