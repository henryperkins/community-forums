<?php /** @var \App\Core\View $this */ ?>
<p class="role-live-effect" data-role-live-effect>
    <?php if (($mode ?? 'shadow') === 'enforce'): ?>
        <strong>Permissions are live (enforce).</strong> Changes apply to live access when assignments are active.
    <?php else: ?>
        <strong>Permissions are in preview mode (shadow).</strong> Saving roles and assignments does not change live access. Existing role rules still decide access.
    <?php endif; ?>
</p>
