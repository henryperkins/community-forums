<?php /** @var \App\Core\View $this */ ?>
<?php $state = in_array($state ?? '', ['online', 'away'], true) ? $state : 'offline'; ?>
<span class="dm-presence is-<?= $e($state) ?>" data-dm-presence="<?= (int) ($user_id ?? 0) ?>"<?= $state === 'offline' ? ' hidden' : '' ?>><span aria-hidden="true"> · </span><span class="presence-dot-bare" aria-hidden="true"></span><span data-dm-presence-label><?= $state === 'online' ? 'Here now' : ($state === 'away' ? 'Away' : '') ?></span></span>
