<?php /** @var \App\Core\View $this */ ?>
<?php
$mgName = $name ?? '';
$mgSeed = $username ?? $mgName;
$mgLabel = $mgName !== '' ? $mgName : $mgSeed;
?>
<span class="monogram <?= $e(monogram_class((string) $mgSeed)) ?><?= !empty($gilt) ? ' monogram-gilt' : '' ?>" aria-hidden="true"><?= $e(monogram_initials((string) $mgLabel)) ?></span>
