<?php /** @var \App\Core\View $this */ ?>
<?php if (empty($can_curate_memory)) { return; } ?>
<?php
// Curator-only by contract: a guest or an ordinary member on a brief-less topic
// must see no living-brief markup at all. The gate lives HERE as well as at the
// call site so no later caller can leak the panel by forgetting it.
$threadId = (int) $thread['id'];
$refresh = is_array($memory_refresh ?? null) ? $memory_refresh : [];
$history = is_array($memory_history ?? null) ? $memory_history : [];
$code = (string) ($refresh['code'] ?? '');
// `eligible` (bool — a refresh is permitted right now) and `eligible_posts`
// (int — how many posts pass the brief's eligibility predicate) are one key
// apart and mean different things. Never `empty()` the count: zero eligible
// posts is a real answer, not a missing one.
$refreshAllowed = !empty($refresh['eligible']);
$eligiblePosts = (int) ($refresh['eligible_posts'] ?? 0);
$threshold = (int) ($refresh['initial_post_threshold'] ?? 0);
$thresholdWords = [
    1 => 'one', 2 => 'two', 3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six',
    7 => 'seven', 8 => 'eight', 9 => 'nine', 10 => 'ten', 11 => 'eleven', 12 => 'twelve',
];
$thresholdLabel = $thresholdWords[$threshold] ?? (string) $threshold;
// A topic reaches this panel with versions behind it in two ways: its brief was
// retired, or an AI brief was suppressed as stale while still published
// (ThreadIntelligenceViewService::forThread()). In both, "no brief YET" and "the
// archive has not drawn one" are false, and Restore — not authoring — is the
// action the curator came for.
$hasHistory = $history !== [];
$historyLabels = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];
?>
<section class="living-brief-empty" aria-label="No living brief yet">
    <p class="living-brief-empty-eyebrow"><?= $hasHistory ? 'No brief showing' : 'No brief yet' ?></p>
    <?php if ($code === 'initial_post_threshold'): ?>
        <?php /* The count sentence is earned only by the post-count denial. A topic
                 can lack a brief because its board is not public, because the thread
                 is gone, or because automation is dead; telling that curator "this
                 needs eight eligible posts" would be false. Both numbers below apply
                 the SAME eligibility predicate and differ only by the opening post,
                 so there is no second, larger count to contrast them against. */ ?>
        <p class="living-brief-empty-copy">
            The archive draws a brief once a topic carries <?= $e($thresholdLabel) ?> eligible posts —
            the opening post plus every reply that is public, visible, and approved.
            This one has <?= $eligiblePosts ?>.
        </p>
    <?php elseif ($refreshAllowed && $hasHistory): ?>
        <p class="living-brief-empty-copy">
            This topic is ready for a brief. Refresh to draw a new one, or restore a version below.
        </p>
    <?php elseif ($refreshAllowed): ?>
        <p class="living-brief-empty-copy">
            This topic is ready for a brief; the archive has not drawn one yet.
            Refresh to draw it now, or publish the first summary yourself.
        </p>
    <?php else: ?>
        <?php /* Any other denial: say what the eligibility ladder actually said. */ ?>
        <p class="living-brief-empty-copy">
            <?= $e($refresh['message'] ?? 'No brief has been published for this topic yet.') ?>
            <?php /* `hourly_limit` is the one denial whose MESSAGE already carries a
                     formatted time — decide() embeds one whenever `$explicit`, and the
                     view model only ever asks through forExplicitRefresh(). Appending
                     the UTC <time> as well would restate the same instant in a second
                     timezone, the restatement this panel exists to avoid. */ ?>
            <?php if ($code !== 'hourly_limit' && !empty($refresh['next_eligible_at_utc'])): ?>
                <time datetime="<?= $e($refresh['next_eligible_at_utc']) ?>"><?= $e(($refresh['next_eligible_at'] ?? '') . ' UTC') ?></time>
            <?php endif; ?>
        </p>
    <?php endif; ?>
    <?php if ($hasHistory): ?>
        <?php /* The design spec's required Restore affordance: the same version-row
                 component the brief's More panel uses, promoted to the surface because
                 with nothing published this IS the panel's primary action rather than
                 a footnote two disclosures deep. The first row carries the filled
                 `.btn` treatment Refresh uses; the rest stay quiet. */ ?>
        <p class="lb-more-title">Restore a version</p>
        <ul class="lb-versions">
            <?php foreach ($history as $index => $item): ?>
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
                        <button class="<?= $index === 0 ? 'btn' : 'linkbtn' ?>" type="submit">Restore</button>
                    </form>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php /* The same curator footer the brief carries, with `living_brief` null so
             Retire stays out and the composer names a first — or a next — summary
             rather than an amendment. Delegating rather than re-rendering the forms
             keeps ONE `id="living-brief-curator-{id}"` in the document — the anchor
             partials/thread_tools.php links to — and one copy of each form. Before
             the redesign these controls rendered whenever a curator could curate,
             independent of whether a brief existed; the brief-scoped footer alone
             would have silently dropped first-summary authoring. */ ?>
    <?= $this->partial('partials/thread_memory_tools', [
        'thread' => $thread,
        'living_brief' => null,
        'memory_history' => $history,
        'memory_refresh' => $refresh,
        'memory_automation_paused' => !empty($memory_automation_paused),
        'can_curate_memory' => true,
    ]) ?>
</section>
