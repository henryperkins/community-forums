<?php /** @var \App\Core\View $this */ ?>
<?php
/** Word plus the commend star. The stored glyph stays in the form value, not in the chrome. */
$reactionLabel = reaction_label((string) ($emoji ?? ''));
?>
<span class="reaction-mark" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?></span><span class="reaction-name"><?= $e($reactionLabel) ?></span>
