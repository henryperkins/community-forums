<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The topic star — a personal bookmark — as one control in two sizes. Both are a
 * plain form post, so starring works without JavaScript.
 *
 *   thread_id    int     the topic
 *   starred      bool    whether the viewer has starred it
 *   return_to    string  where the post returns to
 *   variant      'pill' (default) the labelled control in a topic's head
 *                'icon' the square toggle in a queue row; its accessible name
 *                       carries topic_title, because a list holds many of them
 *   topic_title  string  required by the icon variant
 *   inbox_action bool    exposes the form to the inbox's `s` shortcut
 *   form_class   string  extra classes for the form, when it is itself a layout cell
 *
 * One glyph in both: the four-point commend star. The pill keeps it filled and
 * lets its own fill carry the state; the icon draws it in outline until starred,
 * so the two states differ in shape and not only in ink.
 */
$starThreadId = (int) ($thread_id ?? 0);
$starOn = !empty($starred);
$starIcon = ($variant ?? 'pill') === 'icon';
$starPressed = $starOn ? 'true' : 'false';
$starFormClass = trim(($starIcon ? 'star-form' : 'inline star-form') . ' ' . (string) ($form_class ?? ''));
?>
<form class="<?= $e($starFormClass) ?>" method="post" action="/t/<?= $starThreadId ?>/star"<?= !empty($inbox_action) ? ' data-inbox-action="star"' : '' ?>>
    <?= $this->csrfField() ?>
    <input type="hidden" name="return" value="<?= $e((string) ($return_to ?? '')) ?>">
    <?php if ($starIcon): ?>
        <button class="star-toggle" type="submit" aria-pressed="<?= $starPressed ?>" aria-label="Star <?= $e((string) ($topic_title ?? '')) ?>" title="<?= $starOn ? 'Starred' : 'Star' ?>"><?= $this->partial('partials/icon', ['name' => 'commend-star', 'class' => $starOn ? '' : 'is-outline']) ?></button>
    <?php else: ?>
        <?php /* The label keeps its own <span> so the button's accessible name is
                 exactly "Star" / "Starred". */ ?>
        <button class="linkbtn star-btn<?= $starOn ? ' star-on' : '' ?>" type="submit" aria-pressed="<?= $starPressed ?>"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?><span><?= $starOn ? 'Starred' : 'Star' ?></span></button>
    <?php endif; ?>
</form>
