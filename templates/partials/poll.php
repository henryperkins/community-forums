<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The topic poll, rendered where the design puts it: directly beneath the
 * opening post, because the opening post is what asked the question
 * (ThreadView.dc.html:459 — `pollAfter: raw.op`).
 *
 * One eyebrow line carries the whole state — "Poll · choose one", and
 * "· closed" when it is. It used to be three objects stacked against each
 * other: a gold ✦ tile, a two-line Poll/Choose-one label, and an Open/Closed
 * status pill on the far right, all announcing a control the question below
 * them names anyway.
 */
$pollClosed = (string) ($poll['status'] ?? '') === 'closed';
$pollMode = (string) ($poll['mode'] ?? 'single') === 'multiple' ? 'choose any' : 'choose one';
?>
<section class="poll-card poll-panel" aria-labelledby="poll-question-<?= (int) $poll['id'] ?>">
    <p class="poll-eyebrow">Poll<span class="poll-eyebrow-mode">· <?= $e($pollClosed ? 'closed' : $pollMode) ?></span></p>
    <h2 class="poll-question" id="poll-question-<?= (int) $poll['id'] ?>"><?= $e($poll['question']) ?></h2>
    <?php if (!empty($poll['results_visible'])): ?>
        <?php $pollTotal = max(1, array_sum(array_map(static fn (array $option): int => (int) $option['vote_count'], $poll['options']))); ?>
        <ul class="poll-results link-list">
            <?php foreach ($poll['options'] as $option): ?>
                <?php $n = (int) $option['vote_count']; $pollPercent = (int) round(($n / $pollTotal) * 100); ?>
                <li class="poll-result<?= !empty($option['viewer_voted']) ? ' is-mine' : '' ?>">
                    <span class="poll-result-row">
                        <strong><?= $e($option['body']) ?></strong>
                        <?php // One esteem glyph: the commend star marks the reader's own
                              // vote here exactly as it marks regard and the accepted answer. ?>
                        <?php if (!empty($option['viewer_voted'])): ?><span class="poll-result-mine" title="Your vote"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => 'star-marker']) ?><span class="sr-only">Your vote</span></span><?php endif; ?>
                        <span class="poll-result-count"><?= $n ?></span>
                    </span>
                    <span class="poll-result-bar" role="img" aria-label="<?= $e($option['body']) ?> — <?= $n ?> vote<?= $n === 1 ? '' : 's' ?> of <?= $pollTotal ?>">
                        <svg class="poll-result-progress" viewBox="0 0 100 8" preserveAspectRatio="none" aria-hidden="true" focusable="false">
                            <rect class="poll-result-track" x="0" y="0" width="100" height="8" rx="4" />
                            <rect class="poll-result-fill" x="0" y="0" width="<?= $pollPercent ?>" height="8" rx="4" />
                        </svg>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php elseif (!empty($poll['can_vote'])): ?>
        <form method="post" action="/polls/<?= (int) $poll['id'] ?>/vote" class="poll-options">
            <?= $this->csrfField() ?>
            <?php foreach ($poll['options'] as $option): ?>
                <label class="poll-option">
                    <input type="<?= ($poll['mode'] ?? 'single') === 'multiple' ? 'checkbox' : 'radio' ?>" name="option_ids[]" value="<?= (int) $option['id'] ?>">
                    <span class="poll-option-mark" aria-hidden="true"></span>
                    <?= $e($option['body']) ?>
                </label>
            <?php endforeach; ?>
            <div class="poll-foot">
                <span class="poll-meta">Open to the council</span>
                <button class="btn btn-small" type="submit">Vote</button>
            </div>
        </form>
    <?php else: ?>
        <p class="muted">Results are visible after voting or after the poll closes.</p>
    <?php endif; ?>
</section>
