<?php /** @var \App\Core\View $this */ ?>
<?php
$mgName = $name ?? '';
$mgSeed = $username ?? $mgName;
$mgLabel = $mgName !== '' ? $mgName : $mgSeed;
$avatarPath = isset($avatar_path) && is_string($avatar_path) ? trim($avatar_path) : '';
?>
<?php if ($avatarPath !== ''): ?>
    <img class="monogram avatar-img<?= !empty($gilt) ? ' monogram-gilt' : '' ?>" src="<?= $e($avatarPath) ?>" alt="" aria-hidden="true">
<?php else: ?>
    <span class="monogram <?= $e(monogram_class((string) $mgSeed)) ?><?= !empty($gilt) ? ' monogram-gilt' : '' ?>" aria-hidden="true"><?= $e(monogram_initials((string) $mgLabel)) ?></span>
<?php endif; ?>
