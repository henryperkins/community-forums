<?php /** @var \App\Core\View $this */ ?>
<?php
$showWatch = !empty($topic_tool_sections['watch']);
$showStanding = !empty($topic_tool_sections['standing']);
$showTags = !empty($topic_tool_sections['tags']);
$showMemory = !empty($topic_tool_sections['memory']);
$showManagement = !empty($topic_tool_sections['management']);
$hasTools = in_array(true, $topic_tool_sections, true);
$moveBoards = is_array($move_boards ?? null) ? $move_boards : [];
$moveError = (string) ($move_error ?? '');
$moveSelected = (int) ($move_selected ?? 0);
// Which snooze window is standing. The column stores the instant, not the choice
// that produced it (ThreadWorkflowController::parseSnooze writes now + 4h / 24h /
// 7d), so the pill is read back by bucketing what is left — the same three
// windows, inverted. A snooze already past shows none of them lit.
$snoozeChoice = '';
if (!empty($my_snooze)) {
    $remaining = (strtotime((string) $my_snooze . ' UTC') ?: 0) - time();
    if ($remaining > 0) {
        $snoozeChoice = $remaining <= 6 * 3600 ? 'later_today' : ($remaining <= 30 * 3600 ? 'tomorrow' : 'week');
    }
}
?>
<?php if ($hasTools): ?>
<div class="topic-tools-scrim" data-topic-tools-scrim hidden></div>
<aside class="topic-tools" id="topic-tools-<?= (int) $thread['id'] ?>" data-topic-tools aria-labelledby="topic-tools-title-<?= (int) $thread['id'] ?>">
    <header class="topic-tools-head">
        <span class="topic-tools-mark" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'eight-point-star']) ?></span>
        <h2 id="topic-tools-title-<?= (int) $thread['id'] ?>">Topic tools</h2>
        <button type="button" class="topic-tools-close" data-topic-tools-close hidden aria-label="Close Topic tools"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
    </header>
    <div class="topic-tools-body">
        <?php if ($showWatch): ?>
        <details data-topic-tools-section="watch" open>
            <summary><span>Your watch</span><span><?= $e($subscription['frequency'] ?? 'off') ?></span></summary>
            <?php /* One click per choice, not a select and a Save
                     (ThreadView.dc.html:769-786). Three forms, each posting one
                     value, so the segmented control and the snooze pills work with
                     JavaScript off exactly as they do with it — the picker-plus-
                     commit shape charged two interactions and a page load for a
                     setting whose whole value is that it is quick to change. */ ?>
            <div class="topic-tools-section-body">
                <?php if (($notifications_on ?? false)): ?>
                    <?php $frequency = (string) ($subscription['frequency'] ?? 'off'); ?>
                    <div class="watch-segmented" role="group" aria-label="Watch this topic">
                        <?php foreach ([
                            'instant' => ['Instant', 'Every reply, as it lands'],
                            'daily' => ['Daily', 'One evening summary'],
                            'off' => ['Off', 'Only when named'],
                        ] as $value => [$label, $hint]): ?>
                            <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/subscribe">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="frequency" value="<?= $e($value) ?>">
                                <input type="hidden" name="in_app" value="1"><input type="hidden" name="email" value="1">
                                <button type="submit" class="watch-choice<?= $frequency === $value ? ' is-on' : '' ?>" title="<?= $e($hint) ?>" aria-pressed="<?= $frequency === $value ? 'true' : 'false' ?>"><?= $e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if (($workflow_on ?? false)): ?>
                    <?php // The active pill clears the snooze when pressed again, which is
                          // the only way back the design gives it — a "Clear snooze" option
                          // in a list of three futures is a fourth choice that is not one. ?>
                    <div class="snooze-row" role="group" aria-label="Quiet until">
                        <span class="snooze-label">Quiet until</span>
                        <?php foreach (['later_today' => 'Later today', 'tomorrow' => 'Tomorrow', 'week' => 'Next week'] as $value => $label): ?>
                            <?php $snoozeOn = $snoozeChoice === $value; ?>
                            <form class="inline" method="post" action="/t/<?= (int) $thread['id'] ?>/snooze">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="until" value="<?= $snoozeOn ? '' : $e($value) ?>">
                                <button type="submit" class="snooze-choice<?= $snoozeOn ? ' is-on' : '' ?>" aria-pressed="<?= $snoozeOn ? 'true' : 'false' ?>"><?= $e($label) ?></button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <p class="topic-tools-note">Watching and snooze are yours alone.</p>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showStanding): ?>
        <details data-topic-tools-section="standing">
            <summary><span>Standing</span><span><?= $e($status_labels[$thread['status'] ?? 'open'] ?? 'Open') ?></span></summary>
            <div class="topic-tools-section-body">
                <?php if (!empty($can_write) && !empty(array_filter($can_change_statuses ?? []))): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/status">
                        <?= $this->csrfField() ?>
                        <label for="thread-status">Status</label>
                        <select id="thread-status" class="input" name="status">
                            <?php foreach ($status_labels as $value => $label): ?>
                                <?php if (!empty($can_change_statuses[$value]) || $value === ($thread['status'] ?? 'open')): ?>
                                    <option value="<?= $e($value) ?>"<?= $value === ($thread['status'] ?? 'open') ? ' selected' : '' ?>><?= $e($label) ?></option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <label for="thread-status-reason">Reason</label>
                        <input id="thread-status-reason" class="input" name="reason" maxlength="255">
                        <button class="btn btn-small" type="submit">Update status</button>
                    </form>
                <?php endif; ?>
                <?= $this->partial('partials/thread_status_history', compact('status_history', 'status_labels')) ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showTags): ?>
        <details data-topic-tools-section="tags">
            <summary><span>Tags</span><span><?= $e(implode(' · ', array_column($thread_tags ?? [], 'name'))) ?></span></summary>
            <div class="topic-tools-section-body">
                <?php foreach (($thread_tags ?? []) as $tag): ?><a class="tag" href="/tags/<?= $e($tag['slug']) ?>"><?= $e($tag['name']) ?></a><?php endforeach; ?>
                <?php if (!empty($can_edit_tags)): ?>
                    <h3>Edit tags</h3>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/tags">
                        <?= $this->csrfField() ?>
                        <?php $selected = array_flip(array_map(static fn (array $tag): int => (int) $tag['id'], $thread_tags ?? [])); ?>
                        <?php foreach (($all_tags ?? []) as $tag): ?>
                            <label class="checkline"><input type="checkbox" name="tag_ids[]" value="<?= (int) $tag['id'] ?>"<?= isset($selected[(int) $tag['id']]) ? ' checked' : '' ?>><?= $e($tag['name']) ?></label>
                        <?php endforeach; ?>
                        <button class="btn btn-small" type="submit">Save tags</button>
                    </form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showMemory): ?>
        <details data-topic-tools-section="memory">
            <summary><span>Living Brief</span><span aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'eight-point-star']) ?></span></summary>
            <div class="topic-tools-section-body">
                <p class="muted">Curator tools for this brief sit at the foot of the brief itself.</p>
                <?php // With JS on this drawer is a modal (body scroll locked, scrim up), so the
                      // anchor must also dismiss it. The delegated closer branch in app.js does
                      // not preventDefault, so the fragment navigation still runs; with JS off
                      // the attribute is inert and this stays a plain working anchor. ?>
                <a class="linkbtn" href="#living-brief-curator-<?= (int) $thread['id'] ?>" data-topic-tools-close>Go to the brief's curator tools</a>
            </div>
        </details>
        <?php endif; ?>
        <?php if ($showManagement): ?>
        <details data-topic-tools-section="management"<?= $moveError !== '' ? ' open' : '' ?>>
            <summary><span>Topic management</span><span><?= !empty($assignment) ? '@' . $e($assignment['assigned_username']) : 'unassigned' ?></span></summary>
            <div class="topic-tools-section-body">
                <?php if (!empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)): ?>
                    <p class="topic-tools-eyebrow">Tended by</p>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/assign">
                        <?= $this->csrfField() ?>
                        <?php if (!empty($can_staff_assign)): ?>
                            <label for="study-thread-assignee">Assign to</label>
                            <input id="study-thread-assignee" class="input" type="text" name="assignee" maxlength="32" placeholder="username">
                            <button class="btn btn-small" type="submit">Assign</button>
                        <?php elseif (!empty($can_self_assign)): ?>
                            <input type="hidden" name="self" value="1">
                            <button class="btn btn-small" type="submit">Assign to me</button>
                        <?php endif; ?>
                        <?php if (!empty($assignment)): ?><button class="linkbtn muted" type="submit" name="action" value="unassign">Unassign</button><?php endif; ?>
                    </form>
                <?php endif; ?>
                <?php if (!empty($can_move) && $moveBoards !== []): ?>
                    <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/move">
                        <?= $this->csrfField() ?>
                        <label for="thread-move-board-<?= (int) $thread['id'] ?>">Move to board</label>
                        <?php // The enclosing <details> is forced open on error above, so
                              // autofocus lands on a select the member can actually see. ?>
                        <select id="thread-move-board-<?= (int) $thread['id'] ?>" class="input" name="board_id"<?= $moveError !== '' ? ' aria-invalid="true" aria-describedby="thread-move-error-' . (int) $thread['id'] . '" autofocus' : '' ?> required>
                            <option value="">Choose a board…</option>
                            <?php foreach ($moveBoards as $candidate): ?>
                                <option value="<?= (int) $candidate['id'] ?>"<?= $moveSelected === (int) $candidate['id'] ? ' selected' : '' ?>><?= $e($candidate['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($moveError !== ''): ?><p class="field-error" id="thread-move-error-<?= (int) $thread['id'] ?>"><?= $e($moveError) ?></p><?php endif; ?>
                        <button class="btn btn-small" type="submit">Move topic</button>
                    </form>
                <?php endif; ?>
                <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Clear accepted answer</button></form>
                <?php endif; ?>
                <?php /* Two states, stated (ThreadView.dc.html:906-909). "Pin" / "Unpin"
                         names the act and leaves the reader to infer the state from the
                         verb's tense; the design's switch says what is true and lets the
                         control carry the change. role="switch" because that is what a
                         submit button standing in for a checkbox has to announce — the
                         write is still one POST, so it works with JavaScript off. */ ?>
                <?php if (!empty($can_pin) || !empty($can_lock)): ?>
                    <div class="topic-tools-switches">
                        <?php if (!empty($can_pin)): ?>
                            <?php $isPinned = (int) $thread['is_pinned'] === 1; ?>
                            <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin">
                                <?= $this->csrfField() ?>
                                <button type="submit" class="tool-switch<?= $isPinned ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $isPinned ? 'true' : 'false' ?>"><span class="tool-switch-track" aria-hidden="true"><span class="tool-switch-knob"></span></span><span>Pinned above the board</span></button>
                            </form>
                        <?php endif; ?>
                        <?php if (!empty($can_lock)): ?>
                            <?php $isLocked = (int) $thread['is_locked'] === 1; ?>
                            <form class="inline" method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock">
                                <?= $this->csrfField() ?>
                                <button type="submit" class="tool-switch<?= $isLocked ? ' is-on' : '' ?>" role="switch" aria-checked="<?= $isLocked ? 'true' : 'false' ?>"><span class="tool-switch-track" aria-hidden="true"><span class="tool-switch-knob"></span></span><span>Locked to replies</span></button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (!empty($poll['can_close'])): ?>
                    <form method="post" action="/polls/<?= (int) $poll['id'] ?>/close"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Close poll</button></form>
                <?php endif; ?>
                <?php if (!empty($can_create_poll)): ?>
                    <details class="poll-builder">
                        <summary>Add poll</summary>
                        <form class="stacked" method="post" action="/t/<?= (int) $thread['id'] ?>/poll">
                            <?= $this->csrfField() ?>
                            <label class="field"><span>Question</span><input class="input" type="text" name="question" maxlength="255" required></label>
                            <label class="field"><span>Mode</span><select class="input" name="mode"><option value="single">Single choice</option><option value="multiple">Multiple choice</option></select></label>
                            <label class="field"><span>Closes</span><select class="input" name="closes_in"><option value="never">Never</option><option value="1d">In 1 day</option><option value="3d">In 3 days</option><option value="1w">In 1 week</option></select></label>
                            <label class="field"><span>Options, one per line</span><textarea class="input" name="options" rows="4" required></textarea></label>
                            <button class="btn btn-small" type="submit">Create poll</button>
                        </form>
                    </details>
                <?php endif; ?>
                <?php if (!empty($can_split_merge)): ?><button type="button" data-thread-restructure-open hidden>Split or merge…</button><?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
        <?php /* The drawer says how to leave it and what its acts cost
                 (ThreadView.dc.html:920). A modal with a scrim and no stated exit
                 is one a keyboard reader has to guess at. */ ?>
        <p class="topic-tools-foot"><?= !empty($is_staff)
            ? 'Esc closes. Warden acts are recorded in the ledger.'
            : 'Esc closes. Your watch and your snooze are yours alone.' ?></p>
    </div>
</aside>
<?php endif; ?>
