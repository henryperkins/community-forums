<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'New message');
$dmNewInstance = 'dm-new-page';
$dmNewFirstRecipient = trim(explode(',', (string) $to, 2)[0]);
$dmNewRecipientLabel = ltrim($dmNewFirstRecipient, '@');
$dmNewPlaceholder = 'Message @' . ($dmNewRecipientLabel !== '' ? $dmNewRecipientLabel : 'recipient') . '…';
$dmNewWrapper = function () use ($to, $title, $errors, $allowGroups, $dmNewInstance): void {
    echo $this->partial('partials/dm_compose_fields', [
        'to' => $to,
        'title' => $title ?? '',
        'errors' => $errors,
        'allow_groups' => $allowGroups ?? false,
        'instance_id' => $dmNewInstance,
    ]);
};
?>
<div class="dm-shell reading">
    <aside class="dm-listpane dm-return-pane" aria-label="Messages">
        <header class="dm-listpane-head">
            <div class="dm-listpane-top">
                <span>
                    <span class="eyebrow">Private counsel</span>
                    <h1>Messages</h1>
                </span>
            </div>
        </header>
        <div class="dm-empty-inner dm-return-copy">
            <span class="star" aria-hidden="true">✦</span>
            <p><a href="/messages">Back to all messages</a></p>
        </div>
    </aside>

    <section class="dm-threadpane">
        <div class="dm-compose">
            <div class="dm-compose-wrap">
                <p class="breadcrumb"><a href="/messages">← Messages</a></p>
                <span class="eyebrow">Private counsel</span>
                <h1>New message</h1>

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
