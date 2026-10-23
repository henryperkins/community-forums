<?php /** @var \App\Core\View $this */ ?>
<?php if (($org_form ?? '') === $form_key && !empty($org_errors)): ?>
    <div class="callout callout-danger" id="<?= $e($form_key) ?>-errors" role="alert" tabindex="-1" autofocus>
        <?php foreach ($org_errors as $field => $message): ?><p id="<?= $e($form_key . '-' . $field . '-error') ?>"><?= $e($message) ?></p><?php endforeach; ?>
    </div>
<?php endif; ?>
