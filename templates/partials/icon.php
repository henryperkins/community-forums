<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Lucide-style inline SVG icons — CSP-safe (no sprite, no CDN, no inline style).
 * One self-contained <svg> per call; decorative by default (aria-hidden).
 *
 *   $this->partial('partials/icon', ['name' => 'plus']);
 *   $this->partial('partials/icon', ['name' => 'flag', 'class' => 'danger']);
 *
 * Most marks are stroked (Lucide register, stroke ~1.8, round caps); a few
 * (more-horizontal) are filled dots. Paths are static, trusted markup — the one
 * sanctioned raw echo. Unknown names render nothing. The map is seeded with the
 * icons the DM reading room needs and grows as later phases add surfaces.
 */
$iconName = (string) ($name ?? '');
$iconExtra = isset($class) && is_string($class) && $class !== '' ? ' ' . $class : '';

$iconStroke = [
    'plus'            => '<path d="M12 5v14M5 12h14"/>',
    'search'          => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
    'panel-right'     => '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M15 4v16"/>',
    'users'           => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'user'            => '<path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2"/><circle cx="12" cy="7" r="4"/>',
    'user-plus'       => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
    'bell-off'        => '<path d="M11 5 6 9H2v6h4l5 4z"/><path d="M22 9l-6 6M16 9l6 6"/>',
    'edit-3'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    'log-out'         => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
    'ban'             => '<circle cx="12" cy="12" r="9"/><path d="M5.6 5.6l12.8 12.8"/>',
    'flag'            => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
    'copy'            => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    'x'               => '<path d="M18 6 6 18M6 6l12 12"/>',
    'check'           => '<path d="M20 6 9 17l-5-5"/>',
    'arrow-up'        => '<path d="M12 19V5"/><path d="M5 12l7-7 7 7"/>',
    'chevron-left'    => '<path d="M15 18l-6-6 6-6"/>',
    'lock'            => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
];
$iconFilled = [
    'more-horizontal' => '<circle cx="5" cy="12" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="19" cy="12" r="1.7"/>',
];
$iconBrand = [
    'eight-point-star' => '<path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z"/><path d="M50 21 57.5 42.5 79 50 57.5 57.5 50 79 42.5 57.5 21 50 42.5 42.5Z" opacity="0.5"/><circle cx="50" cy="50" r="5" fill="currentColor" stroke="none"/>',
];
?>
<?php if (isset($iconBrand[$iconName])): ?>
<svg class="icon icon-<?= $e($iconName) . $e($iconExtra) ?>" viewBox="0 0 100 100" width="16" height="16" fill="none" stroke="currentColor" stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconBrand[$iconName] /* static, trusted markup */ ?></svg>
<?php elseif (isset($iconFilled[$iconName])): ?>
<svg class="icon icon-<?= $e($iconName) . $e($iconExtra) ?>" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none" aria-hidden="true"><?= $iconFilled[$iconName] /* static, trusted markup */ ?></svg>
<?php elseif (isset($iconStroke[$iconName])): ?>
<svg class="icon icon-<?= $e($iconName) . $e($iconExtra) ?>" viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?= $iconStroke[$iconName] /* static, trusted markup */ ?></svg>
<?php endif; ?>
