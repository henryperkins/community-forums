<?php /** @var \App\Core\View $this */ ?>
<?php
// ADR 0043: creation stays available on every member-chrome page. Destinations
// enforce write eligibility; this shared row needs no additional shell reads.
$leading = $this->block('subheader_leading', '');
$member = ($current_user ?? null) !== null;
if (!$member && trim($leading) === '') { return; }
$path = (string) ($request_path ?? '/');
$composeBoard = (string) ($compose_board ?? '');
$composeHref = '/compose' . ($composeBoard !== '' ? '?board=' . rawurlencode($composeBoard) : '');
?>
<div class="forum-subheader" data-subheader>
    <div class="forum-subheader-leading"><?= $leading ?></div>
    <?php if ($member): ?>
        <?php if (!empty($features['dms'])): ?>
            <details class="create-menu" name="inbox-controls" data-create-menu data-inbox-menu-align="end">
                <summary class="create-trigger" data-create-trigger aria-label="New topic or message">
                    <?= $this->partial('partials/icon', ['name' => 'plus']) ?><span class="create-label">New</span><?= $this->partial('partials/icon', ['name' => 'chevron-down']) ?>
                </summary>
                <div class="create-menu-panel">
                    <a href="<?= $e($composeHref) ?>"<?= $path === '/compose' ? ' aria-current="page"' : '' ?>>New topic</a>
                    <a href="/messages/new" data-new-message<?= $path === '/messages/new' ? ' aria-current="page"' : '' ?>>New message</a>
                </div>
            </details>
        <?php else: ?>
            <a class="create-trigger" data-create-trigger href="<?= $e($composeHref) ?>" aria-label="New topic"<?= $path === '/compose' ? ' aria-current="page"' : '' ?>><?= $this->partial('partials/icon', ['name' => 'plus']) ?><span class="create-label">New topic</span></a>
        <?php endif; ?>
    <?php endif; ?>
</div>
