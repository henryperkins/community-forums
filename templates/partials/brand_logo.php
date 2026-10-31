<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The community logo for one identity surface (ADR 0054), from a
 * \App\Support\BrandLogo::plan(). System mode is resolved by the browser, not
 * by script: a <picture> source for two images, or a split pair whose
 * prefers-color-scheme rule (app.css, "Community logo") shows the image in one
 * mode and the caller's built-in $fallback in the other. Both wrappers are
 * display: contents, so the image stays its container's own flex item.
 *
 * @var array{kind:string,src:?string,light:?string,dark:?string} $plan
 * @var string $fallback trusted markup for the built-in mark ('' for none)
 */
$plan = is_array($plan ?? null) ? $plan : ['kind' => 'none', 'src' => null, 'light' => null, 'dark' => null];
$fallback = (string) ($fallback ?? '');
$logoClass = (string) ($class ?? 'brand-logo');
$logoAlt = (string) ($alt ?? '');
$logoHeight = isset($height) ? (int) $height : null;
$logoAttrs = isset($attrs) ? (string) $attrs : '';
$image = static fn (string $src): string => '<img class="' . $e($logoClass) . '" src="' . $e($src) . '" alt="' . $e($logoAlt) . '"'
    . ($logoHeight !== null ? ' height="' . $logoHeight . '"' : '') . $logoAttrs . '>';
?>
<?php if ($plan['kind'] === 'single' && $plan['src'] !== null): ?>
<?= $image($plan['src']) ?>
<?php elseif ($plan['kind'] === 'picture' && $plan['light'] !== null && $plan['dark'] !== null): ?>
<picture class="brand-picture"><source media="(prefers-color-scheme: dark)" srcset="<?= $e($plan['dark']) ?>"><?= $image($plan['light']) ?></picture>
<?php elseif ($plan['kind'] === 'split'): ?>
<span class="brand-when-light"><?= $plan['light'] !== null ? $image($plan['light']) : $fallback ?></span><span class="brand-when-dark"><?= $plan['dark'] !== null ? $image($plan['dark']) : $fallback ?></span>
<?php else: ?>
<?= $fallback ?>
<?php endif; ?>
