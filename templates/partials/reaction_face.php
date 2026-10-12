<?php /** @var \App\Core\View $this */ ?>
<?php
/** Compact icons by default; static chips request visible labels with show_label. */
$reactionLabel = reaction_label((string) ($emoji ?? ''));
$reactionIcon = match ((string) ($emoji ?? '')) {
    '👍' => 'commend-star',
    '❤️' => 'heart',
    '😂' => 'laugh',
    '🎉' => 'party-popper',
    '🔥' => 'flame',
    '💯' => 'check',
    '😮' => 'surprised',
    '😢' => 'frown',
    '👀' => 'lightbulb',
    default => null,
};
?>
<span class="reaction-mark" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => $reactionIcon ?? 'commend-star']) ?></span><span class="reaction-name<?= $reactionIcon !== null && empty($show_label) ? ' sr-only' : '' ?>"><?= $e($reactionLabel) ?></span>
