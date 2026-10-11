<?php /** @var \App\Core\View $this */ ?>
<?php if (!empty($new_user_throttled)): ?>
    <p class="dm-empty-note"><?= $this->partial('partials/icon', ['name' => 'lock']) ?><span>You can reply to messages you receive. To start a conversation, make your first post or try again later.</span></p>
<?php endif; ?>
<div class="dm-empty-actions"><?php if (!empty($new_user_throttled)): ?><a class="btn" href="/compose">Write your first post</a><?php endif; ?><a class="btn<?= !empty($new_user_throttled) ? ' btn-ghost' : '' ?>" href="/messages/new">New message</a></div>
