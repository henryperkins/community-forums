<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Package security response');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'packages', 'pane_class' => 'admin-packages packages-security']) ?>
    <a class="admin-back" href="/admin/packages">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Package catalogue
    </a>
    <h2 class="admin-record-title">Package security response</h2>

    <section class="card packages-brake-card">
        <?php // constraint C-07: the chip is reclassified to .pill-danger. .pill-admin is
              // the accent-filled operator chip and carries three different meanings across
              // 41 call sites — recolouring it here would change all of them. The chip also
              // moves out of the <h2> so it stops polluting the accessible heading name. ?>
        <div class="packages-card-head">
            <h2 class="packages-section-title is-lg">Emergency execution brake</h2>
            <?= $execution_disabled ? '<span class="pill pill-danger">disabled</span>' : '<span class="pill packages-pill is-done">live</span>' ?>
        </div>
        <?php // feature-changed: the design drops "applies regardless of the package flag"
              // from the engaged blurb. Carrying it in both branches is deliberate — the
              // fact must not vanish exactly when the brake is armed. ?>
        <p class="packages-lead"><?php if ($execution_disabled): ?>Package execution is halted: <?= (int) $affected_installs ?> integration install<?= (int) $affected_installs === 1 ? ' is' : 's are' ?> paused. Operators can still view, revoke, export, and uninstall. The brake applies regardless of the package flag.<?php else: ?>Package-owned webhooks and credentials are live for <?= (int) $affected_installs ?> integration install<?= (int) $affected_installs === 1 ? '' : 's' ?>. The brake applies regardless of the package flag.<?php endif; ?></p>
        <?php foreach (['execution', 'current_password'] as $ek): ?><?= field_error($errors ?? [], $ek, 'err-brake-' . $ek) ?><?php endforeach; ?>
        <form method="post" action="/admin/packages/security/execution" class="inline-form packages-brake-row">
            <?= $this->csrfField() ?>
            <input type="hidden" name="disabled" value="<?= $execution_disabled ? '0' : '1' ?>">
            <label class="sr-only" for="brake-reason">Reason (optional)</label>
            <input class="packages-secret-input" type="text" id="brake-reason" name="reason" placeholder="Reason (optional)" value="<?= $e($old['reason'] ?? '') ?>">
            <label class="sr-only" for="brake-password">Your password</label>
            <input class="packages-secret-input" type="password" id="brake-password" name="current_password" autocomplete="current-password" placeholder="Your password" required>
            <button class="btn<?= $execution_disabled ? '' : ' danger' ?>" type="submit"><?= $execution_disabled ? 'Resume package execution' : 'Emergency-disable all packages' ?></button>
        </form>
    </section>

    <section class="card packages-table-card">
        <h3 class="packages-eyebrow">Publishers</h3>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Publishers">
        <table class="audit packages-table packages-publishers-table">
            <thead><tr><th scope="col">Publisher</th><th scope="col">Status</th><th scope="col">Verified</th><th scope="col" class="packages-col-actions"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($publishers as $pub): ?>
                <tr>
                    <td>
                        <strong class="packages-name"><?= $e($pub['display_name']) ?></strong>
                        <code class="packages-uid"><?= $e($pub['publisher_uid']) ?></code>
                    </td>
                    <td class="packages-col-nowrap"><?= $e($pub['status'] ?? 'active') ?></td>
                    <td class="packages-col-nowrap"><?= $pub['verified_at'] !== null ? $e($pub['verified_at']) . ' UTC' : '<span class="packages-none">unverified</span>' ?></td>
                    <?php // feature-changed: the design offers inline Suspend/Reinstate text
                          // buttons. Suspension needs a reason and a password and force-disables
                          // every install, so it keeps its drill-in rather than a one-click row
                          // action that cannot carry either. ?>
                    <td class="packages-col-actions"><a class="packages-rowbtn" href="/admin/packages/publishers/<?= (int) $pub['id'] ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($publishers)): ?>
                <tr><td colspan="4" class="packages-empty">No publishers recorded yet.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <?php // feature-added FA-20: two live counts that exist nowhere else on this console. ?>
    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Advisories &amp; blocklist</h3>
        <p class="packages-lead"><?= count($advisories) ?> advisory record(s), <?= count($blocklist) ?> local block(s). Ingest, acknowledge, and block on the <a href="/admin/registries">registry trust console</a>.</p>
    </section>

    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Transparency log</h3>
        <?php if (($transparency ?? []) === []): ?>
            <p class="packages-empty">No transparency entries yet. Brake toggles, publisher trust changes and review decisions are recorded here.</p>
        <?php else: ?>
        <ul class="packages-rule-list">
            <?php foreach ($transparency as $row): ?>
                <li class="packages-rule-row is-stacked">
                    <span class="packages-rule-head">
                        <code class="packages-rule-label"><?= $e($row['event']) ?></code>
                        <span class="packages-rule-when"><?= $e($row['created_at']) ?> UTC</span>
                    </span>
                    <?php if (($row['detail'] ?? '') !== ''): ?><span class="packages-rule-detail"><?= $e((string) $row['detail']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
