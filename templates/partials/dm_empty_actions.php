<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($new_user_throttled)): ?>
    <p class="dm-empty-note"><?= $this->partial('partials/icon', ['name' => 'lock']) ?><span>New accounts can reply to messages they receive. To start a conversation, make your first post or come back later.</span></p>
<?php endif; ?>
<p class="dm-empty-actions"><?php if (!empty($new_user_throttled)): ?><a class="btn" href="/compose">Write your first post</a><?php endif; ?><a class="btn<?= !empty($new_user_throttled) ? ' btn-ghost' : '' ?>" href="/messages/new">New message</a></p>
