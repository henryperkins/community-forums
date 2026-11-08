<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Fluent System Icons (ADR 0060) as inline SVG — CSP-safe (no sprite, no CDN,
 * no inline style). One self-contained <svg> per call, decorative by default
 * (aria-hidden), painted with currentColor.
 *
 *   $this->partial('partials/icon', ['name' => 'plus']);
 *   $this->partial('partials/icon', ['name' => 'flag', 'class' => 'danger']);
 *   $this->partial('partials/icon', ['name' => 'bell', 'filled' => true]);       // a selected state
 *   $this->partial('partials/icon', ['name' => 'bell', 'scale' => 'large']);     // a fixed preview
 *
 * The artwork comes from App\Support\IconSet, generated from resources/icons/
 * by bin/build-icons.php, which validated every path; that data is the one
 * sanctioned raw echo here. App\Support\Icons picks the 16px or 20px artwork
 * for the member's icon-size preference. The `commend-star` draws Star Filled,
 * and Star Regular while its class carries `is-outline` (an unset star, ADR
 * 0036). CSS sets the drawn size; width/height are only the fallback.
 * Unknown names render nothing.
 */
$iconName = (string) ($name ?? '');
$iconExtra = isset($class) && is_string($class) && $class !== '' ? ' ' . $class : '';
$iconStyle = !empty($filled) ? 'filled' : null;
if ($iconName === 'commend-star' && preg_match('/(?:^|\s)is-outline(?:\s|$)/', $iconExtra) === 1) {
    $iconStyle = 'regular';
}
$iconScale = isset($scale) && is_string($scale)
    ? $scale
    : (string) ((isset($appearance) && is_array($appearance) ? $appearance['icon_size'] ?? null : null) ?? 'standard');
$iconGlyph = \App\Support\Icons::glyph($iconName, $iconScale, $iconStyle);
?>
<?php if ($iconGlyph !== null): ?>
<svg class="icon icon-<?= $e($iconName) . $e($iconExtra) ?>" viewBox="0 0 <?= (int) $iconGlyph['size'] ?> <?= (int) $iconGlyph['size'] ?>" width="16" height="16" fill="currentColor" aria-hidden="true"><?php foreach ($iconGlyph['paths'] as $iconPath): ?><path d="<?= $iconPath['d'] /* validated path data */ ?>"<?= isset($iconPath['rule']) ? ' fill-rule="' . $e($iconPath['rule']) . '"' : '' ?>/><?php endforeach; ?></svg>
<?php endif; ?>
