<?php /** @var \App\Core\View $this */ ?>
<?php
$replyExpanded = !empty($reply_errors) || trim((string) ($reply_old['body'] ?? '')) !== '';
$replyThreadId = (int) $thread['id'];
$replyInstance = 'reply-thread-' . $replyThreadId;
?>
<?= $this->partial('partials/composer_shell', [
    'action' => '/t/' . $replyThreadId . '/reply',
    'context' => 'reply',
    'target_id' => $replyThreadId,
    'instance_id' => $replyInstance,
    'placeholder' => 'Reply to “' . (string) $thread['title'] . '”…',
    'maxlength' => 20000,
    'body_value' => (string) ($reply_old['body'] ?? ''),
    'submit_label' => 'Reply',
    'form_id' => 'reply',
    'form_class' => 'reply-composer thread-composer-card',
    'expanded' => $replyExpanded,
    'body_error' => (string) ($reply_errors['body'] ?? ''),
    'identity' => [
        'display_name' => $current_user->displayName(),
        'username' => $current_user->username(),
        'show_avatar' => $show_avatars ?? true,
    ],
    'allow_anonymous' => !empty($thread['board_allow_anonymous']),
    'anonymous_checked' => !empty($reply_old['is_anonymous']),
    'anonymous_disclosure' => 'Your name is hidden from other members; moderators can still see it.',
    'thread_composer' => true,
]) ?>
