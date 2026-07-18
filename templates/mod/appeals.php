<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Appeals queue'); ?>
<?php
$appealCount = count($appeals ?? []);
$errors = $errors ?? [];
$old = $old ?? [];
$failedAppealId = (int) ($old['appeal_id'] ?? 0);
?>
<div class="mod reports-view">
    <header class="mod-head">
        <span>
            <span class="eyebrow">Warden's table</span>
            <h1>Appeals queue</h1>
        </span>
        <span class="pill mod-pill">Moderation</span>
    </header>

    <nav class="mod-subnav" aria-label="Moderation queues">
        <a href="/mod/reports">Reports</a>
        <a href="/mod/approvals">Approval hold</a>
        <a class="active" href="/mod/appeals">Appeals <span class="mod-count"><?= $appealCount ?></span></a>
    </nav>

    <div class="mod-pane">
        <header class="board-header">
            <h2>Appeals</h2>
            <p class="muted">Open appeals in your moderation scope.</p>
        </header>

        <?php if (!empty($errors) && $failedAppealId === 0): ?>
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
                    <?php $isFailedRow = $failedAppealId === (int) $appeal['id']; ?>
                    <li class="report-row is-open">
                        <div class="report-head">
                            <span class="badge"><?= $e((string) $appeal['status']) ?></span>
                            <span class="muted">by <?= $e((string) ($appeal['appellant_username'] ?? 'unknown')) ?> · <?= $e(human_datetime($appeal['created_at'])) ?></span>
                        </div>
                        <p class="report-target"><?= $e((string) $appeal['target_type']) ?> #<?= (int) $appeal['target_id'] ?> · <?= $e((string) ($appeal['original_action'] ?? 'moderation action')) ?></p>
                        <?php if (($appeal['target_summary'] ?? '') !== ''): ?><blockquote class="report-excerpt"><?= $e((string) $appeal['target_summary']) ?></blockquote><?php endif; ?>
                        <p class="report-note"><?= $e((string) $appeal['reason']) ?></p>
                        <form method="post" action="/mod/appeals/<?= (int) $appeal['id'] ?>/resolve" class="appeal-resolve">
                            <?= $this->csrfField() ?>
                            <label class="field">
                                <span>Outcome</span>
                                <?php $selectedOutcome = $isFailedRow ? (string) ($old['outcome'] ?? '') : ''; ?>
                                <select name="outcome" class="input">
                                    <?php foreach (($outcomes ?? ['upheld','modified','reversed','dismissed']) as $outcome): ?>
                                        <option value="<?= $e($outcome) ?>"<?= $selectedOutcome === $outcome ? ' selected' : '' ?>><?= $e(ucfirst($outcome)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Resolution note</span>
                                <textarea name="note" class="input" rows="2"><?= $isFailedRow ? $e((string) ($old['note'] ?? '')) : '' ?></textarea>
                            </label>
                            <?php if ($isFailedRow && !empty($errors)): ?>
                                <div class="error-list" role="alert">
                                    <?php foreach ($errors as $message): ?>
                                        <p class="field-error"><?= $e($message) ?></p>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            <button class="btn btn-small" type="submit">Resolve appeal</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>
