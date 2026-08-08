<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$package = $detail['package'];
$base = '/admin/packages/' . (int) $package['id'];
$isUpdate = $staged_plan !== null;
$target = $isUpdate ? $staged_plan['target'] : null;
$this->section('title', $isUpdate ? 'Approve update: ' . $package['name'] : 'Consent: ' . $package['name']);
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-consent']) ?>
    <a class="admin-back" href="/admin/packages">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Package catalogue
    </a>
    <h2 class="admin-record-title"><?= $isUpdate ? 'Approve update to ' . $e($target['version']) : 'Consent to permissions' ?></h2>
    <p class="packages-lead">Granting is per-permission and audited. A package cannot be enabled while any grant is pending.</p>
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <?php // feature-added: the staged-plan race guard has no design representation. It is
          // the server refusing to approve an update whose target moved underneath. ?>
    <?php if ($isUpdate && $staged_plan['refusal'] !== null): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($staged_plan['refusal']['code'] . ': ' . $staged_plan['refusal']['message']) ?></p>
    <?php endif; ?>

    <section class="card packages-record-card">
        <?php if ($isUpdate): ?>
            <?php // feature-added: the whole permission-diff branch is unmodelled. Losing it
                  // would mean approving an update without seeing what changed. ?>
            <h3 class="packages-eyebrow">New permissions</h3>
            <?php if ($staged_plan['diff']['added'] === []): ?>
                <p class="packages-empty">No new permissions.</p>
            <?php else: ?>
            <ul class="packages-rule-list">
                <?php foreach ($staged_plan['diff']['added'] as $permission): ?>
                    <li class="packages-rule-row">
                        <span class="packages-rule-main">
                            <span class="packages-rule-label"><?= $e($permission['label']) ?></span>
                            <code class="packages-rule-id"><?= $e($permission['kind']) ?>:<?= $e($permission['key']) ?></code>
                        </span>
                        <span class="packages-rule-meta"><?= $e($permission['risk']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <h3 class="packages-eyebrow">Removed</h3>
            <?php if ($staged_plan['diff']['removed'] === []): ?>
                <p class="packages-empty">No removed permissions.</p>
            <?php else: ?>
            <ul class="packages-rule-list">
                <?php foreach ($staged_plan['diff']['removed'] as $permission): ?>
                    <li class="packages-rule-row">
                        <span class="packages-rule-main"><span class="packages-rule-label"><?= $e($permission['label']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>

            <h3 class="packages-eyebrow">Unchanged</h3>
            <?php if ($staged_plan['diff']['unchanged'] === []): ?>
                <p class="packages-empty">No unchanged permissions.</p>
            <?php else: ?>
            <ul class="packages-rule-list">
                <?php foreach ($staged_plan['diff']['unchanged'] as $permission): ?>
                    <li class="packages-rule-row">
                        <span class="packages-rule-main"><span class="packages-rule-label"><?= $e($permission['label']) ?></span></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        <?php else: ?>
            <h3 class="packages-eyebrow">Pending grants</h3>
            <?php if ($pending_permissions === []): ?>
                <p class="packages-empty">No pending grants.</p>
            <?php else: ?>
            <ul class="packages-rule-list">
                <?php foreach ($pending_permissions as $permission): ?>
                    <li class="packages-rule-row">
                        <span class="packages-rule-main">
                            <span class="packages-rule-label"><?= $e($permission['label']) ?></span>
                            <code class="packages-rule-id"><?= $e($permission['kind']) ?>:<?= $e($permission['permission_key']) ?></code>
                        </span>
                        <span class="packages-rule-meta"><?= $e($permission['risk_class']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <section class="card packages-record-card is-narrow">
        <h3 class="packages-eyebrow">Grant</h3>
        <form method="post" action="<?= $e($base) ?>/consent" class="stacked">
            <?= $this->csrfField() ?>
            <input type="hidden" name="intent" value="<?= $isUpdate ? 'approve_update' : 'consent' ?>">
            <?php if ($isUpdate): ?>
                <input type="hidden" name="staged_release_id" value="<?= (int) $install['staged_release_id'] ?>">
            <?php endif; ?>
            <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password" required></label>
            <div class="form-actions">
                <button class="btn" type="submit">Grant and continue</button>
                <a class="packages-textbtn" href="<?= $e($base) ?>">Cancel &mdash; back to the package</a>
            </div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
