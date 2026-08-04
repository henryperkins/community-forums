<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Merge tag'); $this->section('variant', 'admin'); ?>
<?= $this->partial('admin/_console', ['area' => 'content', 'tab' => 'tags']) ?>
        <a class="admin-back" href="/admin/tags">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
            All tags
        </a>
        <h2 class="admin-record-title">Merge tag</h2>
        <section class="card confirm-card">
            <h2>Merge “<?= $e($source['name']) ?>” into “<?= $e($target['name']) ?>”?</h2>
            <p>Every thread tagged <strong><?= $e($source['name']) ?></strong> is retagged as <strong><?= $e($target['name']) ?></strong>, follows move across, and the source tag is removed. This cannot be undone.</p>

            <dl class="impact-list">
                <dt>Source tag</dt><dd><?= $e($source['name']) ?> (<code><?= $e($source['slug']) ?></code>)</dd>
                <dt>Merges into</dt><dd><?= $e($target['name']) ?> (<code><?= $e($target['slug']) ?></code>)</dd>
                <dt>Impact</dt><dd><?= (int) $association_count ?> tag association<?= (int) $association_count === 1 ? '' : 's' ?> (includes hidden, held, and deleted threads)</dd>
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
<?= $this->partial('admin/_console_end') ?>
