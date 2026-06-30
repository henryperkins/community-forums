<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Appeals queue'); ?>
<div class="reports-view">
    <header class="board-header">
        <h1>Appeals queue</h1>
        <p class="muted">Open appeals in your moderation scope.</p>
    </header>

    <?php if (!empty($errors)): ?>
        <div class="card error-list" role="alert">
            <?php foreach ($errors as $message): ?>
                <p><?= $e($message) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (empty($appeals)): ?>
        <p class="muted empty">No open appeals.</p>
    <?php else: ?>
        <ul class="report-list">
            <?php foreach ($appeals as $appeal): ?>
                <li class="report-row">
                    <div class="report-head">
                        <span class="badge"><?= $e((string) $appeal['status']) ?></span>
                        <span class="muted">by <?= $e((string) ($appeal['appellant_username'] ?? 'unknown')) ?> · <?= $e(human_datetime($appeal['created_at'])) ?></span>
                    </div>
                    <p class="report-target"><?= $e((string) $appeal['target_type']) ?> #<?= (int) $appeal['target_id'] ?> · <?= $e((string) ($appeal['original_action'] ?? 'moderation action')) ?></p>
                    <?php if (($appeal['target_summary'] ?? '') !== ''): ?><blockquote class="report-excerpt"><?= $e((string) $appeal['target_summary']) ?></blockquote><?php endif; ?>
                    <p><?= $e((string) $appeal['reason']) ?></p>
                    <form method="post" action="/mod/appeals/<?= (int) $appeal['id'] ?>/resolve" class="stacked">
                        <?= $this->csrfField() ?>
                        <label class="field">
                            <span>Outcome</span>
                            <select name="outcome" class="input">
                                <?php foreach (($outcomes ?? ['upheld','modified','reversed','dismissed']) as $outcome): ?>
                                    <option value="<?= $e($outcome) ?>"><?= $e(ucfirst($outcome)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="field">
                            <span>Resolution note</span>
                            <textarea name="note" class="input" rows="2"></textarea>
                        </label>
                        <button class="btn btn-small" type="submit">Resolve appeal</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
