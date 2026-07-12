<?php /** @var \App\Core\View $this */ ?>
<?php
$showWatch = !empty($topic_tool_sections['watch']);
$showStanding = !empty($topic_tool_sections['standing']);
$showTags = !empty($topic_tool_sections['tags']);
$showMemory = !empty($topic_tool_sections['memory']);
$showManagement = !empty($topic_tool_sections['management']);
$hasTools = in_array(true, $topic_tool_sections, true);
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
            <div class="topic-tools-section-body">
                <?php if (($notifications_on ?? false)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/subscribe">
                        <?= $this->csrfField() ?>
                        <label for="study-sub-freq-<?= (int) $thread['id'] ?>">Frequency</label>
                        <select id="study-sub-freq-<?= (int) $thread['id'] ?>" class="input" name="frequency">
                            <?php $frequency = $subscription['frequency'] ?? 'off'; ?>
                            <?php foreach (['instant' => 'Instant', 'daily' => 'Daily', 'off' => 'Off'] as $value => $label): ?>
                                <option value="<?= $e($value) ?>"<?= $frequency === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="in_app" value="1"><input type="hidden" name="email" value="1">
                        <button class="btn btn-small" type="submit">Save watch</button>
                    </form>
                <?php endif; ?>
                <?php if (($workflow_on ?? false)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/snooze">
                        <?= $this->csrfField() ?>
                        <label for="study-snooze-<?= (int) $thread['id'] ?>">Quiet until</label>
                        <select id="study-snooze-<?= (int) $thread['id'] ?>" class="input" name="until">
                            <option value="">Clear snooze</option><option value="later_today">Later today</option><option value="tomorrow">Tomorrow</option><option value="week">Next week</option>
                        </select>
                        <button class="btn btn-small" type="submit">Save snooze</button>
                    </form>
                <?php endif; ?>
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
        <?php if ($showManagement): ?>
        <details data-topic-tools-section="management">
            <summary><span>Topic management</span><span><?= !empty($assignment) ? '@' . $e($assignment['assigned_username']) : 'unassigned' ?></span></summary>
            <div class="topic-tools-section-body">
                <?php if (!empty($can_self_assign) || !empty($can_staff_assign) || !empty($assignment)): ?>
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
                <?php if (($accepted_post_id ?? null) !== null && !empty($can_mark_solved)): ?>
                    <form method="post" action="/t/<?= (int) $thread['id'] ?>/unaccept"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Clear accepted answer</button></form>
                <?php endif; ?>
                <?php if (!empty($can_pin)): ?>
                    <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/pin"><?= $this->csrfField() ?><button class="linkbtn" type="submit"><?= (int) $thread['is_pinned'] === 1 ? 'Unpin' : 'Pin' ?></button></form>
                <?php endif; ?>
                <?php if (!empty($can_lock)): ?>
                    <form method="post" action="/mod/t/<?= (int) $thread['id'] ?>/lock"><?= $this->csrfField() ?><button class="linkbtn danger" type="submit"><?= (int) $thread['is_locked'] === 1 ? 'Unlock' : 'Lock' ?></button></form>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>
    </div>
</aside>
<?php endif; ?>
