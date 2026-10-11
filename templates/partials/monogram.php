<?php /** @var \App\Core\View $this */ ?>
<?php
$mgName = $name ?? '';
$mgSeed = $username ?? $mgName;
$mgLabel = $mgName !== '' ? $mgName : $mgSeed;
// An uploaded avatar replaces the monogram wherever a member's identity is drawn
// (USER §5.2 fallback chain). Callers pass the author's `avatar_path` from the
// row they already hold; mask_author() nulls it for an anonymous post, so a
// masked byline can never wear the real author's picture.
$avatarPath = isset($avatar_path) && is_string($avatar_path) ? trim($avatar_path) : '';
?>
<?php if ($avatarPath !== ''): ?>
    <img class="monogram avatar-img<?= !empty($gilt) ? ' monogram-gilt' : '' ?>" src="<?= $e($avatarPath) ?>" alt="" aria-hidden="true" loading="lazy" decoding="async">
<?php else: ?>
    <span class="monogram <?= $e(monogram_class((string) $mgSeed)) ?><?= !empty($gilt) ? ' monogram-gilt' : '' ?>" aria-hidden="true"><?= $e(monogram_initials((string) $mgLabel)) ?></span>
<?php endif; ?>
