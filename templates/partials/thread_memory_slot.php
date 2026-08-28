<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The living-brief region, rendered where the design puts it: beneath the
 * opening post, because the brief summarises the question that post asked
 * (ThreadView.dc.html:463 — `briefRegion: raw.op`).
 *
 * One region, showing or not. `$can_curate_memory` is the caller's already
 * resolved curator flag, so a brief-less topic still reaches a curator: writing
 * the first summary was never gated on a brief existing.
 */
if ($living_brief === null && empty($can_curate_memory)) {
    return;
}
?>
<div class="thread-memory-slot">
    <?php if ($living_brief !== null): ?>
        <?= $this->partial('partials/living_brief', [
            'thread' => $thread,
            'living_brief' => $living_brief,
            'living_brief_sources' => $living_brief_sources,
            'can_curate_memory' => $can_curate_memory,
            'memory_automation_paused' => $memory_automation_paused,
            'memory_history' => $memory_history,
            'memory_refresh' => $memory_refresh,
        ]) ?>
    <?php else: ?>
        <?= $this->partial('partials/living_brief_empty', [
            'thread' => $thread,
            'can_curate_memory' => $can_curate_memory,
            'memory_automation_paused' => $memory_automation_paused,
            'memory_history' => $memory_history,
            'memory_refresh' => $memory_refresh,
        ]) ?>
    <?php endif; ?>
</div>
