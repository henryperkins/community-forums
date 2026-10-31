<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Shown on every page while an administrator previews their private branding
 * draft (ADR 0054). It paints from the constant stage tokens, so no draft
 * palette can make the way out unreadable.
 */
$returnPath = (string) ($request_path ?? '/');
?>
<div class="branding-draft-bar" role="region" aria-label="Branding draft preview">
    <p class="branding-draft-bar-text"><strong>Previewing your branding draft.</strong> Only you see it; visitors see the published branding.</p>
    <div class="branding-draft-bar-actions">
        <a href="/admin/branding">Review and publish</a>
        <a href="/admin/branding/preview/sign-in">Sign-in page</a>
        <form method="post" action="/admin/branding/preview">
            <?= $this->csrfField() ?>
            <input type="hidden" name="preview" value="0">
            <input type="hidden" name="return" value="<?= $e($returnPath) ?>">
            <button type="submit">Exit preview</button>
        </form>
    </div>
</div>
