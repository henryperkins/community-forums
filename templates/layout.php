<?php /** @var \App\Core\View $this */ ?>
<?php
$variant = $this->block('variant', 'app');
$appearance = $appearance ?? ['theme' => 'system', 'density' => 'comfortable', 'font_size' => 'medium', 'reduced_motion' => false];
$composing = $composing ?? ['enter_to_send' => false, 'show_preview' => true, 'smart_lists' => true];
$brand = $branding ?? ['name' => $site_name, 'logo_path' => null, 'favicon_path' => null, 'color_primary' => '#2f6fed', 'color_accent' => '#7c3aed'];
$appUrl = rtrim((string) ($app_url ?? ''), '/');
$canonical = $this->block('canonical', '');
$robots = $this->block('robots', '');
$ogType = $this->block('og_type', 'website');
$ogImage = $this->block('og_image', '');
$desc = $this->block('description', $brand['name'] . ' — a community forum.');
?>
<!doctype html>
<html lang="en"
      data-theme="<?= $e($appearance['theme']) ?>"
      data-density="<?= $e($appearance['density']) ?>"
      data-font-size="<?= $e($appearance['font_size']) ?>"
      <?= !empty($appearance['reduced_motion']) ? 'data-reduced-motion="1"' : '' ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($this->block('title', $brand['name'])) ?></title>
    <meta name="description" content="<?= $e($desc) ?>">
    <?php if ($robots !== ''): ?><meta name="robots" content="<?= $e($robots) ?>"><?php endif; ?>
    <?php if ($canonical !== ''): ?><link rel="canonical" href="<?= $e($appUrl . $canonical) ?>"><?php endif; ?>
    <meta property="og:site_name" content="<?= $e($brand['name']) ?>">
    <meta property="og:title" content="<?= $e($this->block('title', $brand['name'])) ?>">
    <meta property="og:description" content="<?= $e($desc) ?>">
    <meta property="og:type" content="<?= $e($ogType) ?>">
    <?php if ($canonical !== ''): ?><meta property="og:url" content="<?= $e($appUrl . $canonical) ?>"><?php endif; ?>
    <?php if ($ogImage !== ''): ?><meta property="og:image" content="<?= $e($ogImage) ?>"><?php endif; ?>
    <?php if (!empty($brand['favicon_path'])): ?>
        <link rel="icon" href="<?= $e($brand['favicon_path']) ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css">
    <?php if (!empty($brand['has_custom_colors'])): ?><link rel="stylesheet" href="/brand.css?v=<?= $e($brand['version'] ?: '1') ?>"><?php endif; ?>
</head>
<body class="variant-<?= $e($variant) ?>" data-route="<?= $e($this->block('route', '')) ?>" data-drafts="<?= !empty($features['drafts']) ? '1' : '0' ?>"<?php if (($current_user ?? null) !== null): ?> data-user="<?= $e($current_user->username()) ?>" data-enter-to-send="<?= !empty($composing['enter_to_send']) ? '1' : '0' ?>" data-show-preview="<?= !empty($composing['show_preview']) ? '1' : '0' ?>" data-smart-lists="<?= !empty($composing['smart_lists']) ? '1' : '0' ?>"<?php endif; ?><?php if (!empty($needs_tour)): ?> data-tour="1"<?php endif; ?>>
<a class="skip-link" href="#main">Skip to content</a>
<?= $this->partial('partials/topbar') ?>
<?php if (is_array($site_announcement ?? null) && !empty($site_announcement['active'])): ?>
<?= $this->partial('partials/announcement_banner') ?>
<?php endif; ?>
<?php if ($variant === 'app'): ?>
    <div class="app-shell">
        <div class="nav-scrim" data-nav-scrim hidden></div>
        <?= $this->partial('partials/sidebar') ?>
        <main class="main" id="main">
            <?= $this->partial('partials/flash') ?>
            <?= $content ?>
        </main>
    </div>
<?php else: ?>
    <main class="container" id="main">
        <?= $this->partial('partials/flash') ?>
        <?= $content ?>
    </main>
<?php endif; ?>
<script src="/assets/app.js" defer></script>
<?php if (!empty($features['rich_composer'])): ?><script src="/assets/composer.js" defer></script><?php endif; ?>
<?php if (!empty($features['product_tour']) && ($current_user ?? null) !== null): ?><script src="/assets/tour.js" defer></script><?php endif; ?>
</body>
</html>
