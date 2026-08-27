<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php
$threadId = (int) $thread['id'];
$paused = !empty($memory_automation_paused);
$refresh = $memory_refresh ?? [];
$history = $memory_history ?? [];
$historyLabels = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];
?>
<div class="living-brief-curator" id="living-brief-curator-<?= $threadId ?>">
    <div class="living-brief-curator-row">
        <form class="inline-form" method="post" action="/t/<?= $threadId ?>/summary/refresh">
            <?= $this->csrfField() ?>
            <button class="btn" type="submit"<?= empty($refresh['eligible']) ? ' disabled' : '' ?>>Refresh</button>
        </form>
        <details class="lb-amend">
            <summary class="linkbtn">Amend</summary>
            <form class="composer" method="post" action="/t/<?= $threadId ?>/summary">
                <?= $this->csrfField() ?>
                <label for="summary-body-<?= $threadId ?>">Summary</label>
                <textarea id="summary-body-<?= $threadId ?>" class="composer-input" name="body" rows="4" maxlength="20000"></textarea>
                <label for="summary-sources-<?= $threadId ?>">Source post IDs</label>
                <input id="summary-sources-<?= $threadId ?>" class="input" type="text" name="source_post_ids" placeholder="1, 2, 3">
                <button class="btn btn-small" type="submit">Publish amendment</button>
            </form>
        </details>
    </div>

    <?php if (empty($refresh['eligible'])): ?>
        <p class="muted living-brief-curator-note">
            <?= $e($refresh['message'] ?? 'Refresh is not currently available.') ?>
            <?php if (!empty($refresh['next_eligible_at_utc'])): ?>
                <time datetime="<?= $e($refresh['next_eligible_at_utc']) ?>"><?= $e(($refresh['next_eligible_at'] ?? '') . ' UTC') ?></time>
            <?php endif; ?>
        </p>
    <?php endif; ?>

    <details class="lb-more">
        <summary class="linkbtn"><span class="lb-more-shut">More</span><span class="lb-more-open">Less</span></summary>
        <div class="lb-more-body">
            <?php if (!empty($history)): ?>
                <p class="lb-more-title">Earlier versions</p>
                <ul class="lb-versions">
                    <?php foreach ($history as $item): ?>
                        <li class="lb-version">
                            <span class="lb-version-v">v<?= (int) $item['version'] ?></span>
                            <span class="lb-version-who"><?= $e($item['label']) ?></span>
                            <span class="lb-version-status"><?= $e($historyLabels[$item['status']] ?? ucfirst((string) $item['status'])) ?></span>
                            <?php if (!empty($item['published_at'])): ?>
                                <time class="lb-version-when" datetime="<?= $e(gmdate('Y-m-d\TH:i:s\Z', strtotime((string) $item['published_at'] . ' UTC'))) ?>"><?= $e(human_datetime($item['published_at'])) ?></time>
                            <?php endif; ?>
                            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/restore">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="summary_id" value="<?= (int) $item['id'] ?>">
                                <button class="linkbtn" type="submit">Restore</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <form class="inline-form lb-more-related" method="post" action="/t/<?= $threadId ?>/related">
                <?= $this->csrfField() ?>
                <label class="sr-only" for="related-thread-<?= $threadId ?>">Related topic ID</label>
                <input id="related-thread-<?= $threadId ?>" class="input input-small" type="number" name="related_thread_id" min="1" placeholder="Thread ID" required>
                <label class="sr-only" for="related-reason-<?= $threadId ?>">Reason</label>
                <input id="related-reason-<?= $threadId ?>" class="input" type="text" name="reason" maxlength="255" placeholder="Reason">
                <button class="btn btn-small" type="submit">Add related topic</button>
            </form>

            <div class="lb-more-foot">
                <?php if ($paused): ?>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/automation/resume">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn" type="submit">Resume automatic refresh</button>
                    </form>
                <?php else: ?>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/automation/pause">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Pause automatic refresh</button>
                    </form>
                <?php endif; ?>
                <?php if (!empty($living_brief)): ?>
                    <details class="lb-confirm">
                        <summary class="linkbtn danger">Retire brief</summary>
                        <div class="lb-confirm-body">
                            <p>Retiring hides the brief from the topic and pauses automatic refresh. Curators can restore it from this panel.</p>
                            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/retire">
                                <?= $this->csrfField() ?>
                                <button class="btn danger" type="submit">Retire brief</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </details>
</div>
