<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'New topic');
$composeSelectedBoard = null;
foreach ($boards as $composeBoard) {
    if ((int) $composeBoard['id'] === (int) $selected_board) {
        $composeSelectedBoard = $composeBoard;
        break;
    }
}
$composeSelectedSlug = (string) ($composeSelectedBoard['slug'] ?? 'board');
$composeAllowsAnonymous = array_filter($boards, static fn (array $b): bool => !empty($b['allow_anonymous'])) !== [];
$composeWrapper = function () use ($boards, $selected_board, $errors, $old, $e): void {
    ?>
    <p class="muted">Markdown supported — <strong>**bold**</strong>, <em>*italic*</em>, <code>`code`</code>, <code>||spoiler||</code>, and <code>![alt](image)</code> after uploading.</p>
    <?php // field_error() emits a <p>, which cannot legally nest inside <label>,
          // so each error stays a sibling after its label — as it already was. ?>
    <label class="field">
        <span>Board</span>
        <select name="board_id" class="input"<?= field_attrs($errors, 'board_id') ?>>
            <?php foreach ($boards as $b): ?>
                <option value="<?= (int) $b['id'] ?>" <?= (int) $b['id'] === (int) $selected_board ? 'selected' : '' ?>>#<?= $e($b['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?= field_error($errors, 'board_id') ?>
    <label class="field">
        <span>Title</span>
        <input type="text" name="title" class="input" maxlength="160" value="<?= $e($old['title'] ?? '') ?>"<?= field_attrs($errors, 'title') ?> required>
    </label>
    <?= field_error($errors, 'title') ?>
    <?php
};
?>
<div class="read-main read-pad compose-page">
    <h1>New topic</h1>
    <?= $this->partial('partials/composer_shell', [
        'action' => '/threads',
        'context' => 'new_thread',
        'target_id' => (int) $selected_board,
        'instance_id' => 'new-thread-page',
        'placeholder' => 'Start a new topic in #' . $composeSelectedSlug . '…',
        'maxlength' => 20000,
        'body_value' => (string) ($old['body'] ?? ''),
        'submit_label' => 'Create topic',
        'form_class' => 'stacked',
        'body_error' => (string) ($errors['body'] ?? ''),
        // board_id/title own the focus when either errored first (field_attrs()' rule).
        'body_error_focus' => array_key_first($errors) === 'body',
        'identity' => [
            'display_name' => $current_user->displayName(),
            'username' => $current_user->username(),
            'show_avatar' => $show_avatars ?? true,
        ],
        'allow_anonymous' => $composeAllowsAnonymous,
        'anonymous_checked' => !empty($old['is_anonymous']),
        'anonymous_disclosure' => 'Only takes effect on boards that allow it; your name stays visible to moderators.',
        'wrapper_slot' => $composeWrapper,
    ]) ?>
</div>
