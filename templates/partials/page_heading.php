<?php /** Simple route context; identity and content headings stay in their panes. */ ?>
<?php if (($current_user ?? null) !== null): ?>
    <?php $this->start('subheader_leading'); ?>
    <h1 class="forum-page-title"><?= $e($heading) ?></h1>
    <?php $this->stop(); ?>
<?php else: ?>
    <h1<?= !empty($heading_class) ? ' class="' . $e($heading_class) . '"' : '' ?>><?= $e($heading) ?></h1>
<?php endif; ?>
