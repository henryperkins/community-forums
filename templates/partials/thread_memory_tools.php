<?php /** @var \App\Core\View $this */ ?>
<details class="memory-curator-tools">
    <summary class="linkbtn">Curate topic memory</summary>
    <div class="memory-curator-tools-body">
        <?php if (!empty($memory_automation_paused)): ?>
            <p class="muted">Automatic refresh is paused for this topic.</p>
            <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/automation/resume">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit">Resume automatic refresh</button>
            </form>
        <?php else: ?>
            <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/refresh">
                <?= $this->csrfField() ?>
                <button class="btn btn-small" type="submit"<?= empty($memory_refresh['eligible']) ? ' disabled' : '' ?>>Refresh living brief</button>
            </form>
            <?php if (empty($memory_refresh['eligible'])): ?>
                <p class="muted">
                    <?= $e($memory_refresh['message'] ?? 'Refresh is not currently available.') ?>
                    <?php if (!empty($memory_refresh['next_eligible_at_utc'])): ?>
                        <time datetime="<?= $e($memory_refresh['next_eligible_at_utc']) ?>"><?= $e(($memory_refresh['next_eligible_at'] ?? '') . ' UTC') ?></time>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>

        <form class="composer" method="post" action="/t/<?= (int) $thread['id'] ?>/summary">
            <?= $this->csrfField() ?>
            <label for="summary-body">Summary</label>
            <textarea id="summary-body" class="composer-input" name="body" rows="4" maxlength="20000"></textarea>
            <label for="summary-sources">Source post IDs</label>
            <input id="summary-sources" class="input" type="text" name="source_post_ids" placeholder="1, 2, 3">
            <button class="btn btn-small" type="submit">Publish summary</button>
        </form>

        <?php if (!empty($living_brief)): ?>
            <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/retire">
                <?= $this->csrfField() ?>
                <button class="linkbtn muted" type="submit">Retire summary</button>
            </form>
        <?php endif; ?>

        <?php if (!empty($memory_history)): ?>
            <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/summary/restore">
                <?= $this->csrfField() ?>
                <label class="sr-only" for="summary-restore">Restore summary</label>
                <select id="summary-restore" class="input input-small" name="summary_id">
                    <?php foreach ($memory_history as $item): ?>
                        <option value="<?= (int) $item['id'] ?>">v<?= (int) $item['version'] ?> · <?= $e($item['label']) ?> · <?= $e($item['status']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-small" type="submit">Restore summary</button>
            </form>
        <?php endif; ?>

        <form class="inline-form" method="post" action="/t/<?= (int) $thread['id'] ?>/related">
            <?= $this->csrfField() ?>
            <input class="input input-small" type="number" name="related_thread_id" min="1" placeholder="Thread ID" required>
            <input class="input" type="text" name="reason" maxlength="255" placeholder="Reason">
            <button class="btn btn-small" type="submit">Add related topic</button>
        </form>
    </div>
</details>
