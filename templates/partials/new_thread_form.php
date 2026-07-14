<?php /** @var \App\Core\View $this */ ?>
<?php
$newThreadBoardId = (int) $board['id'];
$newThreadInstance = 'new-thread-board-' . $newThreadBoardId;
$newThreadWrapper = function () use ($board, $errors, $old, $e): void {
    ?>
    <input type="hidden" name="board_id" value="<?= (int) $board['id'] ?>">
    <?php if (!empty($errors['title'])): ?><p class="field-error"><?= $e($errors['title']) ?></p><?php endif; ?>
    <input type="text" name="title" class="input" placeholder="Title" maxlength="160" value="<?= $e($old['title'] ?? '') ?>" required>
    <?php
};
$newThreadBeforeSubmit = function (): void {
    ?><button class="btn-secondary composer-cancel" type="button" data-close-composer>Cancel</button><?php
};
?>
<?= $this->partial('partials/composer_shell', [
    'action' => '/threads',
    'context' => 'new_thread',
    'target_id' => $newThreadBoardId,
    'instance_id' => $newThreadInstance,
    'placeholder' => 'Start a new topic in #' . (string) $board['slug'] . '…',
    'maxlength' => 20000,
    'body_value' => (string) ($old['body'] ?? ''),
    'submit_label' => 'Create topic',
    'form_class' => 'stacked',
    'body_error' => (string) ($errors['body'] ?? ''),
    'identity' => [
        'display_name' => $current_user->displayName(),
        'username' => $current_user->username(),
        'show_avatar' => $show_avatars ?? true,
    ],
    'allow_anonymous' => !empty($board['allow_anonymous']),
    'anonymous_checked' => !empty($old['is_anonymous']),
    'anonymous_disclosure' => 'Your name is hidden from other members; moderators can still see it.',
    'wrapper_slot' => $newThreadWrapper,
    'before_submit_slot' => $newThreadBeforeSubmit,
]) ?>
