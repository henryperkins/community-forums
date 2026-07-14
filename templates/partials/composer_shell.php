<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * Server-rendered progressive-enhancement base for every body composer.
 *
 * Required: action, context, target_id, instance_id, placeholder, maxlength,
 * body_value, submit_label.
 *
 * Optional: body_name, form_id, form_class, expanded, body_error, identity,
 * allow_anonymous, anonymous_checked, anonymous_disclosure, no_draft,
 * no_wysiwyg, thread_composer, wrapper_slot, below_input_slot,
 * before_submit_slot.
 */
$shellContexts = ['reply', 'new_thread', 'dm', 'edit'];
$shellContext = (string) ($context ?? '');
$shellInstance = (string) ($instance_id ?? '');
if (!in_array($shellContext, $shellContexts, true)) {
    throw new \InvalidArgumentException('Unsupported composer context.');
}
if ($shellInstance === '' || preg_match('/^[a-z0-9-]+$/', $shellInstance) !== 1) {
    throw new \InvalidArgumentException('Invalid composer instance ID.');
}

$shellAction = (string) ($action ?? '');
$shellTargetId = (int) ($target_id ?? 0);
$shellPlaceholder = (string) ($placeholder ?? '');
$shellMaxlength = max(1, (int) ($maxlength ?? 0));
$shellBodyValue = (string) ($body_value ?? '');
$shellSubmitLabel = (string) ($submit_label ?? 'Send');
$shellBodyName = (string) ($body_name ?? 'body');
$shellFormId = isset($form_id) && $form_id !== null ? trim((string) $form_id) : '';
$shellFormClass = trim((string) ($form_class ?? ''));
$shellExpanded = !empty($expanded);
$shellBodyError = trim((string) ($body_error ?? ''));
$shellIdentity = is_array($identity ?? null) ? $identity : null;
$shellAllowAnonymous = !empty($allow_anonymous);
$shellAnonymousChecked = !empty($anonymous_checked);
$shellAnonymousDisclosure = (string) ($anonymous_disclosure ?? '');
$shellNoDraft = !empty($no_draft);
$shellNoWysiwyg = !empty($no_wysiwyg);
$shellThreadComposer = !empty($thread_composer);
$shellWrapperSlot = ($wrapper_slot ?? null) instanceof \Closure ? $wrapper_slot : null;
$shellBelowInputSlot = ($below_input_slot ?? null) instanceof \Closure ? $below_input_slot : null;
$shellBeforeSubmitSlot = ($before_submit_slot ?? null) instanceof \Closure ? $before_submit_slot : null;

$shellClasses = 'composer composer-shell';
if ($shellFormClass !== '') {
    $shellClasses .= ' ' . $shellFormClass;
}
if ($shellExpanded) {
    $shellClasses .= ' is-expanded';
}

$shellBodyId = 'composer-body-' . $shellInstance;
$shellBodyErrorId = 'composer-body-error-' . $shellInstance;
$shellAnonymousId = 'composer-anonymous-' . $shellInstance;
$shellAnonymousDisclosureId = 'composer-anonymous-disclosure-' . $shellInstance;
$shellSubmitStatusId = 'composer-submit-status-' . $shellInstance;
?>
<form class="<?= $e($shellClasses) ?>" method="post" action="<?= $e($shellAction) ?>" data-composer-context="<?= $e($shellContext) ?>" data-composer-target-id="<?= $shellTargetId ?>" data-composer-instance="<?= $e($shellInstance) ?>"<?= $shellFormId !== '' ? ' id="' . $e($shellFormId) . '"' : '' ?><?= $shellNoDraft ? ' data-no-draft' : '' ?><?= $shellNoWysiwyg ? ' data-no-wysiwyg' : '' ?><?= $shellThreadComposer ? ' data-thread-composer' : '' ?>>
    <?= $this->csrfField() ?>
    <input type="hidden" name="idempotency_key" value="<?= $e(bin2hex(random_bytes(16))) ?>">
    <?php if ($shellWrapperSlot !== null): ?><?php $shellWrapperSlot(); ?><?php endif; ?>
    <div class="composer-box">
        <div class="composer-format-slot" data-composer-format-slot></div>
        <?php if ($shellBodyError !== ''): ?><p class="field-error" id="<?= $e($shellBodyErrorId) ?>"><?= $e($shellBodyError) ?></p><?php endif; ?>
        <textarea class="composer-input" id="<?= $e($shellBodyId) ?>" name="<?= $e($shellBodyName) ?>" rows="4" maxlength="<?= $shellMaxlength ?>" placeholder="<?= $e($shellPlaceholder) ?>"<?= $shellBodyError !== '' ? ' aria-describedby="' . $e($shellBodyErrorId) . '"' : '' ?> required><?= $e($shellBodyValue) ?></textarea>
        <?php if ($shellBelowInputSlot !== null): ?><?php $shellBelowInputSlot(); ?><?php endif; ?>
        <div class="composer-upload-tray" data-composer-upload-tray aria-live="polite"></div>
        <div class="composer-actions-bar">
            <div class="composer-actions-start">
                <span data-composer-actions-start-slot></span>
                <?php if ($shellIdentity !== null): ?>
                    <?php
                    $shellIdentityName = (string) ($shellIdentity['display_name'] ?? '');
                    $shellIdentityUsername = (string) ($shellIdentity['username'] ?? '');
                    $shellShowAvatar = !array_key_exists('show_avatar', $shellIdentity) || !empty($shellIdentity['show_avatar']);
                    ?>
                    <span class="composer-identity" dir="auto">
                        <?php if ($shellShowAvatar): ?><?= $this->partial('partials/monogram', ['name' => $shellIdentityName, 'username' => $shellIdentityUsername]) ?><?php endif; ?>
                        <span class="composer-identity-copy">as <strong><?= $e($shellIdentityName) ?></strong></span>
                    </span>
                <?php endif; ?>
                <?php if ($shellAllowAnonymous): ?>
                    <span class="composer-anonymous-chip">
                        <input type="checkbox" id="<?= $e($shellAnonymousId) ?>" name="is_anonymous" value="1" aria-describedby="<?= $e($shellAnonymousDisclosureId) ?>"<?= $shellAnonymousChecked ? ' checked' : '' ?>>
                        <label for="<?= $e($shellAnonymousId) ?>">Anonymous</label>
                    </span>
                <?php endif; ?>
            </div>
            <div class="composer-actions-end">
                <span data-composer-actions-end-slot></span>
                <?php if ($shellBeforeSubmitSlot !== null): ?><?php $shellBeforeSubmitSlot(); ?><?php endif; ?>
                <button type="submit" class="btn composer-send" aria-label="<?= $e($shellSubmitLabel) ?>">
                    <span aria-hidden="true">✒</span>
                </button>
            </div>
        </div>
    </div>
    <div class="composer-meta-row">
        <span class="composer-meta-draft" data-composer-draft-slot></span>
        <?php if ($shellAllowAnonymous): ?><span class="composer-anonymous-disclosure" id="<?= $e($shellAnonymousDisclosureId) ?>"><?= $e($shellAnonymousDisclosure) ?></span><?php endif; ?>
        <span class="composer-meta-count" data-composer-counter-slot></span>
    </div>
    <div data-composer-after-box></div>
    <span class="sr-only" id="<?= $e($shellSubmitStatusId) ?>" role="status" aria-live="polite" data-composer-submit-status></span>
</form>
