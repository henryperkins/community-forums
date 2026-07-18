<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Merge tag'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Merge tag</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'tags', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <section class="card confirm-card">
            <h2>Merge “<?= $e($source['name']) ?>” into “<?= $e($target['name']) ?>”?</h2>
            <p>Every thread tagged <strong><?= $e($source['name']) ?></strong> is retagged as <strong><?= $e($target['name']) ?></strong>, follows move across, and the source tag is removed. This cannot be undone.</p>

            <dl class="impact-list">
                <dt>Source tag</dt><dd><?= $e($source['name']) ?> (<code><?= $e($source['slug']) ?></code>)</dd>
                <dt>Merges into</dt><dd><?= $e($target['name']) ?> (<code><?= $e($target['slug']) ?></code>)</dd>
                <dt>Threads affected</dt><dd><?= (int) $thread_count ?></dd>
            </dl>

            <form method="post" action="/admin/tags/<?= (int) $source['id'] ?>/merge" class="stacked confirm-form">
                <?= $this->csrfField() ?>
                <input type="hidden" name="target_id" value="<?= (int) $target['id'] ?>">
                <div class="form-actions">
                    <button class="btn danger" type="submit">Merge and remove “<?= $e($source['name']) ?>”</button>
                    <a class="linkbtn" href="/admin/tags">Cancel</a>
                </div>
            </form>
        </section>
    </div>
</div>
