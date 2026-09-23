<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'New message');
$dmNewInstance = 'dm-new-page';
// Split the way ConversationController::resolveRecipients() does, so a
// prefilled or re-rendered group is addressed as a group, not by its first name.
$dmNewRecipients = array_values(array_filter(array_map(
    static fn (string $name): string => ltrim($name, '@'),
    preg_split('/[\s,]+/', trim((string) $to)) ?: [],
), static fn (string $name): bool => $name !== ''));
$dmNewRecipientLabel = $dmNewRecipients[0] ?? '';
$dmNewPlaceholder = count($dmNewRecipients) > 1 || trim((string) ($title ?? '')) !== ''
    ? 'Message the group…'
    : 'Message @' . ($dmNewRecipientLabel !== '' ? $dmNewRecipientLabel : 'recipient') . '…';
$dmNewShowAvatars = $show_avatars ?? true;
$dmNewWrapper = function () use ($to, $title, $errors, $allowGroups, $dmNewInstance, $dmNewShowAvatars): void {
    echo $this->partial('partials/dm_compose_fields', [
        'to' => $to,
        'title' => $title ?? '',
        'errors' => $errors,
        'allow_groups' => $allowGroups ?? false,
        'instance_id' => $dmNewInstance,
        'show_avatars' => $dmNewShowAvatars,
    ]);
};
?>
<div class="dm-shell reading">
    <?= $this->partial('partials/dm_list', ['conversations' => $conversations ?? [], 'allow_groups' => $allowGroups ?? false, 'show_avatars' => $dmNewShowAvatars, 'heading_tag' => 'h2']) ?>

    <section class="dm-threadpane">
        <header class="dm-thread-head dm-thread-head-compose">
            <a class="dm-back" href="/messages" aria-label="Back to messages"><?= $this->partial('partials/icon', ['name' => 'chevron-left']) ?></a>
            <div class="dm-thread-id"><div><span class="dm-thread-eyebrow"><?= $this->partial('partials/icon', ['name' => 'lock']) ?>Private counsel</span><h1 class="dm-thread-title">New message</h1></div></div>
        </header>
        <div class="dm-compose">
            <div class="dm-compose-wrap">
                <?php if (!empty($new_user_throttled)): ?><p class="dm-empty-note">New accounts can reply to messages they receive. To start a conversation, make your first post or come back later.</p><?php endif; ?>

                <?= $this->partial('partials/composer_shell', [
                    'action' => '/messages',
                    'context' => 'dm',
                    'target_id' => 0,
                    'instance_id' => $dmNewInstance,
                    'placeholder' => $dmNewPlaceholder,
                    'maxlength' => 5000,
                    'body_value' => (string) $body,
                    'submit_label' => 'Send',
                    'form_class' => 'dm-form',
                    'body_error' => (string) ($errors['body'] ?? ''),
                    // The wrapper's To/Group title own the focus when either errored first.
                    'body_error_focus' => array_key_first($errors ?? []) === 'body',
                    'identity' => [
                        'display_name' => $current_user->displayName(),
                        'username' => $current_user->username(),
                        'show_avatar' => $show_avatars ?? true,
                    ],
                    'wrapper_slot' => $dmNewWrapper,
                ]) ?>
            </div>
        </div>
    </section>
</div>
