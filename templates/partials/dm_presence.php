<?php /** @var \App\Core\View $this */ ?>
<?php /* Params: user_id, state, separator (default true: the " · " that joins it to a preceding @handle on one line; false where it stands alone). */ ?>
<?php $state = in_array($state ?? '', ['online', 'away'], true) ? $state : 'offline'; ?>
<span class="dm-presence is-<?= $e($state) ?>" data-dm-presence="<?= (int) ($user_id ?? 0) ?>"<?= $state === 'offline' ? ' hidden' : '' ?>><?php if ($separator ?? true): ?><span aria-hidden="true"> · </span><?php endif; ?><span class="presence-dot-bare" aria-hidden="true"></span><span data-dm-presence-label><?= $state === 'online' ? 'Here now' : ($state === 'away' ? 'Away' : '') ?></span></span>
