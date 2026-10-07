<?php /** @var \App\Core\View $this */ ?>
<?php
// Shared creation must remain reachable when the responsive room hides its
// conversation list. Render this panel beside the room, outside its panes.
$dmAllowGroups = !empty($allow_groups);
$dmShowAvatars = $show_avatars ?? true;
$dmCompose = $compose ?? [];
$dmComposeErrors = $dmCompose['errors'] ?? [];
$dmDialogInstance = 'dm-new-dialog';
$dmDialogWrapper = function () use ($dmAllowGroups, $dmDialogInstance, $dmCompose, $dmComposeErrors, $dmShowAvatars): void {
    ?><div class="dm-dialog-body"><input type="hidden" name="origin" value="dialog"><?php
    echo $this->partial('partials/dm_compose_fields', [
        'to' => $dmCompose['to'] ?? '',
        'title' => $dmCompose['title'] ?? '',
        'errors' => $dmComposeErrors,
        'allow_groups' => $dmAllowGroups,
        'instance_id' => $dmDialogInstance,
        'show_avatars' => $dmShowAvatars,
    ]);
    ?></div><?php
};
$dmDialogBeforeSubmit = function (): void {
    ?><a class="btn btn-ghost" href="/messages" data-close-compose>Cancel</a><?php
};
?>
<div class="dm-compose-panel" data-dm-compose<?= empty($dmCompose['open']) ? ' hidden' : '' ?>>
    <div class="dm-dialog" aria-labelledby="dm-compose-title">
        <div class="dm-dialog-head">
            <div><span class="eyebrow">Private counsel</span><h2 id="dm-compose-title">New message</h2></div>
            <a href="/messages" class="dm-dialog-close" data-close-compose aria-label="Close"><?= $this->partial('partials/icon', ['name' => 'x']) ?></a>
        </div>
        <?= $this->partial('partials/composer_shell', [
            'action' => '/messages',
            'context' => 'dm',
            'no_wysiwyg' => true,
            'target_id' => 0,
            'instance_id' => $dmDialogInstance,
            'placeholder' => 'Message @recipient…',
            'maxlength' => 5000,
            'body_value' => $dmCompose['body'] ?? '',
            'body_error' => $dmComposeErrors['body'] ?? '',
            'body_error_focus' => array_key_first($dmComposeErrors) === 'body',
            'submit_label' => 'Send',
            'form_class' => 'dm-form',
            'identity' => [
                'display_name' => $current_user->displayName(),
                'username' => $current_user->username(),
                'avatar_path' => $current_user->avatarPath(),
                'show_avatar' => $dmShowAvatars,
            ],
            'wrapper_slot' => $dmDialogWrapper,
            'before_submit_slot' => $dmDialogBeforeSubmit,
        ]) ?>
    </div>
</div>
