<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Error ' . $status); $this->section('variant', 'plain'); ?>
<?php
$moderationAccess = is_array($moderation_access ?? null) ? $moderation_access : [];
$moderationReportCount = (int) ($moderationAccess['report_count'] ?? 0);
?>
<div class="auth-card error-card">
    <h1><?= (int) $status ?></h1>
    <p><?= $e($message) ?></p>
    <?php if ((int) $status === 403 && !empty($moderationAccess['can_reports'])): ?>
        <p><a class="btn btn-secondary" href="/mod/reports">Moderation queue <span class="mod-count"><?= $moderationReportCount ?></span></a></p>
    <?php endif; ?>
    <p><a class="btn" href="/">Back to home</a></p>
</div>
