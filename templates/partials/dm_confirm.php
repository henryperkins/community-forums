<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * A two-step confirmation for an action one click should not take: a native
 * <details> whose summary names the action and whose step says what follows
 * before the real submit (the profile's Block grammar). It works without
 * JavaScript; app.js adds Escape and outside-click dismissal and posts the
 * step once.
 *
 * Params:
 *   summary_class  the first step's look (dm-menu-item, dm-rail-btn or dm-linkbtn)
 *   label          the first step's visible words
 *   label_name     optional accessible name, starting with label (WCAG 2.5.3)
 *   icon           optional icon drawn before label
 *   note           what follows, in a sentence or two
 *   note_id        unique id for note; the confirm button is described by it
 *   action         POST target, or null when the action is not open to the
 *                  viewer and the note says why (no button renders)
 *   fields         hidden inputs, name => value
 *   confirm        the confirm button's words
 *   danger         true for an action that takes something away
 *   group          optional exclusive-accordion name, so one step is open at a time
 */
$confirmAction = ($action ?? null) ?: null;
$confirmNoteId = (string) $note_id;
?>
<details class="dm-confirm"<?= !empty($group) ? ' name="' . $e((string) $group) . '"' : '' ?>>
    <summary class="<?= $e((string) $summary_class) ?>"<?= !empty($label_name) ? ' aria-label="' . $e((string) $label_name) . '"' : '' ?>><?php if (!empty($icon)): ?><?= $this->partial('partials/icon', ['name' => (string) $icon]) ?><?php endif; ?><span><?= $e((string) $label) ?></span></summary>
    <?php if ($confirmAction !== null): ?>
        <form class="dm-confirm-step" method="post" action="<?= $e((string) $confirmAction) ?>" data-submit-once>
            <?= $this->csrfField() ?>
            <?php foreach (($fields ?? []) as $fieldName => $fieldValue): ?>
                <input type="hidden" name="<?= $e((string) $fieldName) ?>" value="<?= $e((string) $fieldValue) ?>">
            <?php endforeach; ?>
            <p class="dm-confirm-note" id="<?= $e($confirmNoteId) ?>"><?= $e((string) $note) ?></p>
            <button class="btn btn-small<?= !empty($danger) ? ' danger' : '' ?>" type="submit" aria-describedby="<?= $e($confirmNoteId) ?>"><?= $e((string) $confirm) ?></button>
        </form>
    <?php else: ?>
        <div class="dm-confirm-step">
            <p class="dm-confirm-note" id="<?= $e($confirmNoteId) ?>"><?= $e((string) $note) ?></p>
        </div>
    <?php endif; ?>
</details>
