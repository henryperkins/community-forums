<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$package = $plan['package'];
$release = $plan['release'];
$base = '/admin/packages/' . (int) $package['id'];
$this->section('title', 'Install plan: ' . $package['name']);
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-plan']) ?>
    <a class="admin-back" href="/admin/packages">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Package catalogue
    </a>
    <h2 class="admin-record-title">Install plan &mdash; <?= $e($package['name']) ?> <?= $e($release['version']) ?></h2>
    <?php foreach (($errors ?? []) as $err): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($err) ?></p>
    <?php endforeach; ?>

    <?php if ($plan['refusal'] !== null): ?>
        <?php // The structured {code}: {message} and the blocklist suffix are the only
              // producer of the pinned "local blocklist" string. Keep both. ?>
        <p class="callout callout-danger packages-refusal" role="alert">
            <?= $e($plan['refusal']['code'] . ': ' . $plan['refusal']['message']) ?>
            <?= $plan['refusal']['code'] === 'locally_blocked' ? ' Matched local blocklist.' : '' ?>
        </p>
    <?php endif; ?>

    <?php foreach ($plan['warnings'] as $warning): ?>
        <p class="callout callout-danger packages-refusal" role="alert"><?= $e($warning) ?></p>
    <?php endforeach; ?>

    <section class="card packages-record-card">
        <h3 class="packages-eyebrow">Install plan</h3>
        <p class="packages-section-caption">Installing records provenance and permissions; nothing executes until you consent and enable.</p>
        <dl class="packages-plan-facts">
            <?php // feature-added FA-21: the title carries {name} {version}; this row is the
                  // only place the canonical package_uid appears on the re-auth-gated
                  // install confirmation. ?>
            <div><dt class="packages-fact-term">Package</dt><dd class="packages-fact-value"><?= $e($package['name']) ?> <code class="packages-uid"><?= $e($package['package_uid']) ?></code></dd></div>
            <div><dt class="packages-fact-term">Version</dt><dd class="packages-fact-value is-mono"><?= $e($release['version']) ?></dd></div>
            <div><dt class="packages-fact-term">Digest</dt><dd class="packages-fact-value is-mono"><?= $e($release['digest']) ?></dd></div>
            <div><dt class="packages-fact-term">Registry</dt><dd class="packages-fact-value"><?= $plan['registry'] !== null ? $e($plan['registry']['source_id']) : 'local' ?></dd></div>
            <div><dt class="packages-fact-term">Review</dt><dd class="packages-fact-value"><?= $e($release['review_status']) ?></dd></div>
            <div><dt class="packages-fact-term">Compatibility</dt><dd class="packages-fact-value"><?= $plan['compatible'] === true ? '<span class="packages-pill is-done">compatible</span>' : '<span class="packages-pill is-review">incompatible</span>' ?></dd></div>
        </dl>

        <h3 class="packages-eyebrow">Permission preview</h3>
        <?php if ($plan['permissions'] === []): ?>
            <p class="packages-empty">No permissions declared.</p>
        <?php else: ?>
        <ul class="packages-rule-list">
            <?php foreach ($plan['permissions'] as $permission): ?>
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
    </section>

    <?php if ($plan['refusal'] === null && ($plan['installed'] === null || $plan['installed']['state'] === 'uninstalled')): ?>
        <section class="card packages-record-card is-narrow">
            <h3 class="packages-eyebrow">Install</h3>
            <form method="post" action="<?= $e($base) ?>/install" class="stacked">
                <?= $this->csrfField() ?>
                <input type="hidden" name="release_id" value="<?= (int) $release['id'] ?>">
                <label class="reauth-field"><span>Current password</span><input class="packages-secret-input" type="password" name="current_password" autocomplete="current-password" required></label>
                <div class="form-actions">
                    <?php // constraint: the label is an ADR 0023 remediation ("Record install"
                          // is truthful — nothing executes) and is pinned by gate-a. The design
                          // says "Install"; production wins. ?>
                    <button class="btn" type="submit">Record install</button>
                    <a class="packages-textbtn" href="<?= $e($base) ?>">Cancel</a>
                </div>
                <p class="packages-section-caption">Nothing runs yet: the next step asks you to review and consent to the permissions, and the package stays disabled until you enable it.</p>
            </form>
        </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
