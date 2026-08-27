<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Open a topic');
$this->section('route', 'compose');
$composeSelectedBoard = $selected_board_row;
$composeSelectedSlug = (string) $composeSelectedBoard['slug'];
$composeSelectedName = (string) $composeSelectedBoard['name'];
$composeAllowsAnonymous = !empty($composeSelectedBoard['allow_anonymous']);
$composeWrapper = function () use ($boards, $selected_board, $errors, $old, $e): void {
    ?>
    <label class="field compose-title-field">
        <span>Title</span>
        <input type="text" name="title" class="input input-engraved compose-title-input" data-compose-title
               maxlength="160" minlength="3" value="<?= $e($old['title'] ?? '') ?>"
               placeholder="What should the council consider?"<?= field_attrs($errors, 'title') ?> required>
    </label>
    <?= field_error($errors, 'title') ?>

    <label class="field compose-board-field">
        <span>Board</span>
        <select name="board_id" class="input input-engraved compose-board-select" data-compose-board-select<?= field_attrs($errors, 'board_id') ?>>
            <?php foreach ($boards as $board): ?>
                <option value="<?= (int) $board['id'] ?>"
                        data-board-slug="<?= $e($board['slug']) ?>"
                        data-board-name="<?= $e($board['name']) ?>"
                        data-board-anonymous="<?= !empty($board['allow_anonymous']) ? '1' : '0' ?>"
                        <?= (int) $board['id'] === (int) $selected_board ? 'selected ' : '' ?><?= empty($board['can_post']) ? 'disabled' : '' ?>><?= $e($board['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <?= field_error($errors, 'board_id') ?>
    <?php
};
?>
<div class="compose-surface" data-compose data-compose-selected-board="<?= $e($composeSelectedSlug) ?>">
    <div class="compose-column">
        <p class="compose-eyebrow" data-compose-board-name>Posting to <?= $e($composeSelectedName) ?></p>
        <h1>Open a topic</h1>
        <p class="compose-lede">Say what you want the council to consider, and what would change your mind.</p>

        <?= $this->partial('partials/composer_shell', [
            'action' => '/threads',
            'context' => 'new_thread',
            'target_id' => (int) $selected_board,
            'instance_id' => 'new-thread-page',
            'placeholder' => 'Open with the strongest version of your question…',
            'maxlength' => 20000,
            'body_value' => (string) ($old['body'] ?? ''),
            'submit_label' => 'Create topic',
            'form_class' => 'compose-topic-form',
            'expanded' => true,
            'body_error' => (string) ($errors['body'] ?? ''),
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

        <footer class="compose-footer">
            <a href="/" class="compose-cancel">Cancel</a>
            <span class="compose-draft-copy" data-compose-draft-copy hidden>Draft kept on this device.</span>
        </footer>
    </div>
</div>
