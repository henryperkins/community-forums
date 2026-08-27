<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php
$threadId = (int) $thread['id'];
$paused = !empty($memory_automation_paused);
$refresh = $memory_refresh ?? [];
$history = $memory_history ?? [];
$historyLabels = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];
// Two callers: partials/living_brief.php (a brief is published) and
// partials/living_brief_empty.php (none is). The footer is the same set of
// controls either way; only the copy that names a published brief changes.
$hasBrief = !empty($living_brief);
// "Is a brief showing" and "has this topic ever carried one" are different
// questions, and the gap between them is exactly the state this panel exists
// for: a retired brief, or an AI brief suppressed as stale, leaves its version
// rows behind. Keyed on $history, which every caller already passes.
$everPublished = $hasBrief || $history !== [];
?>
<div class="living-brief-curator" id="living-brief-curator-<?= $threadId ?>">
    <?php /* While automation is paused the brief itself already carries the paused line
             (partials/living_brief.php), and refresh is denied, so a Refresh control here
             would be a dead primary above a near-duplicate of that sentence. Resume takes
             the primary slot instead — it is the only meaningful action in this state, and
             it renders here rather than in the More footer so it appears exactly once.
             Keyed on $paused, not $refresh['code']: the eligibility ladder reports only its
             FIRST denial, so a paused topic that is also below the post threshold reports
             `initial_post_threshold` while `automation_paused` on the job row stays true. */ ?>
    <div class="living-brief-curator-row">
        <?php if ($paused): ?>
            <form class="inline-form" method="post" action="/t/<?= $threadId ?>/summary/automation/resume">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit">Resume automatic refresh</button>
            </form>
        <?php else: ?>
            <form class="inline-form" method="post" action="/t/<?= $threadId ?>/summary/refresh">
                <?= $this->csrfField() ?>
                <button class="btn" type="submit"<?= empty($refresh['eligible']) ? ' disabled' : '' ?>>Refresh</button>
            </form>
        <?php endif; ?>
        <details class="lb-amend">
            <?php /* One route, three truths: amending what is showing, writing the
                     topic's first summary, and writing its next one after a
                     retirement — "first" would be a lie when the next publish is v3. */ ?>
            <summary class="linkbtn"><?= $hasBrief ? 'Amend' : ($everPublished ? 'Write a new summary' : 'Write the first summary') ?></summary>
            <form class="composer" method="post" action="/t/<?= $threadId ?>/summary">
                <?= $this->csrfField() ?>
                <label for="summary-body-<?= $threadId ?>">Summary</label>
                <textarea id="summary-body-<?= $threadId ?>" class="composer-input" name="body" rows="4" maxlength="20000"></textarea>
                <label for="summary-sources-<?= $threadId ?>">Source post IDs</label>
                <input id="summary-sources-<?= $threadId ?>" class="input" type="text" name="source_post_ids" placeholder="1, 2, 3">
                <button class="btn btn-small" type="submit"><?= $hasBrief ? 'Publish amendment' : 'Publish summary' ?></button>
            </form>
        </details>
    </div>

    <?php /* Gated on $hasBrief as well: with no brief, the empty state directly
             above this footer already states why none has been drawn — in the
             `initial_post_threshold` case with the real numbers, otherwise with
             this very message — so repeating it here would be a near-duplicate
             sentence one nesting level down, the same trap the paused primary
             above avoids. */ ?>
    <?php if ($hasBrief && !$paused && empty($refresh['eligible'])): ?>
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
            <?php /* Gated on $hasBrief: with no brief showing, Restore is the panel's
                     whole point, so partials/living_brief_empty.php promotes these same
                     rows above this footer rather than burying them two disclosures
                     deep. Rendering them here as well would duplicate every form. */ ?>
            <?php if ($hasBrief && !empty($history)): ?>
                <?php /* Every summary row, including the one currently published — the view
                         service applies no status or version filter — so the heading must not
                         promise "earlier". A re-restore of the live version stays reachable. */ ?>
                <p class="lb-more-title">Version history</p>
                <ul class="lb-versions">
                    <?php foreach ($history as $item): ?>
                        <li class="lb-version">
                            <span class="lb-version-v">v<?= (int) $item['version'] ?></span>
                            <span class="lb-version-who"><?= $e($item['label']) ?></span>
                            <span class="lb-version-status"><?= $e($historyLabels[$item['status']] ?? ucfirst((string) $item['status'])) ?></span>
                            <?php if (!empty($item['published_at'])): ?>
                                <time class="lb-version-when" datetime="<?= $e(iso_datetime($item['published_at'])) ?>"><?= $e(human_datetime($item['published_at'])) ?></time>
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
                <?php if (!$paused): ?>
                    <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/automation/pause">
                        <?= $this->csrfField() ?>
                        <button class="linkbtn muted" type="submit">Pause automatic refresh</button>
                    </form>
                <?php endif; ?>
                <?php if ($hasBrief): ?>
                    <details class="lb-confirm">
                        <summary class="linkbtn danger">Retire brief</summary>
                        <div class="lb-confirm-body">
                            <p>Retiring hides the brief from the topic and pauses automatic refresh. Curators can restore it from this panel.</p>
                            <?php /* Distinct from the summary's "Retire brief" so the confirm step is
                                     audible, not merely visual: a screen reader announcing the same name
                                     twice one nesting level apart gives no signal that a second, real
                                     commit follows the disclosure. */ ?>
                            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/retire">
                                <?= $this->csrfField() ?>
                                <button class="btn danger" type="submit">Confirm retirement</button>
                            </form>
                        </div>
                    </details>
                <?php endif; ?>
            </div>
        </div>
    </details>
</div>
