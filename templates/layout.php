<?php /** @var \App\Core\View $this */ ?>
<?php $variant = $this->block('variant', 'app'); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $e($this->block('title', $site_name)) ?></title>
    <meta name="description" content="<?= $e($this->block('description', $site_name . ' — a community forum.')) ?>">
    <link rel="stylesheet" href="/assets/app.css">
</head>
<body class="variant-<?= $e($variant) ?>">
<a class="skip-link" href="#main">Skip to content</a>
<?= $this->partial('partials/topbar') ?>
<?php if ($variant === 'app'): ?>
    <div class="app-shell">
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
</body>
</html>
