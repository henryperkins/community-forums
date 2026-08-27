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
//
// The two counts arrive null unless the denial is `initial_post_threshold` —
// ThreadIntelligenceViewService::emptyModel() runs the COUNT only for the one
// branch below that spends them, rather than on every topic view. Coalescing to
// 0 here is safe only because that branch is the only reader; do not move either
// value out from behind it without making the view model answer unconditionally.
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
// Retiring does two things in one transaction (CommunityMemoryService::
// retireSummary): it retires the published row AND pauses automation. So the
// ladder's first denial after a retirement is `automation_paused` — a
// CONSEQUENCE of the retirement, not the reason the brief is gone. The other
// route into this panel with versions behind it, an AI brief suppressed as
// stale, leaves its row `published`; that is how the two absences tell
// themselves apart without guessing.
$statuses = array_column($history, 'status');
$wasRetired = in_array('retired', $statuses, true) && !in_array('published', $statuses, true);
// The eligibility ladder speaks the schema's register: no terminal period, and
// "thread" where this app's noun is "topic" — including in the member-visible
// pause line on partials/living_brief.php. Its strings are pinned at source by
// tests/Unit/ThreadIntelligence/ThreadIntelligenceEligibilityTest.php and shared
// with the operator console, where "thread" is the right word, so they are
// adapted HERE, at the one render that puts them in front of a curator reading a
// topic. The brief-present twin of this paragraph — thread_memory_tools.php's
// `.living-brief-curator-note` — is mutually exclusive with this panel, so no
// reader ever sees the two registers side by side.
$ladderMessage = (string) ($refresh['message'] ?? '');
if ($ladderMessage === '') {
    $ladderMessage = 'No brief has been published for this topic yet.';
} else {
    // strtr() with an array matches longest key first, so "threads" wins over
    // "thread". Deliberately case-sensitive: "Thread memory is disabled" names
    // the operator-facing subsystem, not the topic.
    $ladderMessage = strtr($ladderMessage, ['threads' => 'topics', 'thread' => 'topic']);
    if (!str_ends_with($ladderMessage, '.')) {
        $ladderMessage .= '.';
    }
}
?>
<section class="living-brief-empty" aria-label="<?= $hasHistory ? 'No living brief showing' : 'No living brief yet' ?>">
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
    <?php elseif ($wasRetired && $code === 'automation_paused'): ?>
        <?php /* The canonical post-Retire state. Falling through to the ladder's
                 message here would answer the curator's question — "where did the
                 brief go?" — with the pause that retiring switched on, which is the
                 one slot on this panel where every other branch gives the real
                 reason. The second clause also arbitrates between the two actions
                 now on screen: Restore above undoes the retirement, Resume in the
                 footer below does not, and restoring deliberately leaves automation
                 paused (AppPhase4GateATest: "restore must not silently resume
                 automation"). */ ?>
        <p class="living-brief-empty-copy">
            Retiring the brief hid it from this topic and paused automatic refresh.
            Restore a version below to bring it back; automatic refresh stays paused until you resume it.
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
        <?php /* Any other denial: say what the eligibility ladder actually said, in
                 this panel's register rather than the schema's — see $ladderMessage. */ ?>
        <p class="living-brief-empty-copy">
            <?= $e($ladderMessage) ?>
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
                 a footnote two disclosures deep. The first row takes the panel's one
                 filled `.btn`; the rest stay quiet, and the footer's leading control
                 steps down beneath it ($restorePromoted in thread_memory_tools.php). */ ?>
        <?= $this->partial('partials/living_brief_versions', [
            'thread_id' => $threadId,
            'history' => $history,
            'title' => 'Restore a version',
            'promote_first' => true,
        ]) ?>
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
