<?php /** @var \App\Core\View $this */ ?>
<form class="inbox-visibility" method="post" action="/t/<?= (int) $thread_id ?>/snooze" data-inbox-visibility>
    <?= $this->csrfField() ?>
    <?php if (($return_to ?? '') !== ''): ?><input type="hidden" name="return" value="<?= $e($return_to) ?>"><?php endif; ?>
    <input type="hidden" name="until" value="<?= $hidden ? '' : 'manual' ?>">
    <button type="submit" class="inbox-visibility-toggle" role="switch" aria-checked="<?= $hidden ? 'false' : 'true' ?>" aria-describedby="inbox-visibility-note-<?= (int) $thread_id ?>">
        <span>Show in Inbox</span><span class="inbox-switch-track" aria-hidden="true"><span class="inbox-switch-thumb"></span></span>
    </button>
    <p class="inbox-menu-note" id="inbox-visibility-note-<?= (int) $thread_id ?>"><?= $hidden ? 'Turn on to restore this topic.' : 'Turn off until you restore this topic.' ?></p>
</form>
