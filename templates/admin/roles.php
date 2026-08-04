<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Roles');
$this->section('variant', 'admin');
// Group the flat capability catalogue by its middle namespace token
// (core.board.* → Board, core.user.* → User, …) so the checkbox run scans as
// tiers instead of one undifferentiated list.
$groupedCatalogue = [];
foreach ($catalogue as $capKey => $capMeta) {
    $parts = explode('.', (string) $capKey);
    $groupedCatalogue[ucfirst($parts[1] ?? 'other')][$capKey] = $capMeta;
}
ksort($groupedCatalogue);
?>
<?= $this->partial('admin/_console', ['area' => 'people', 'tab' => 'roles', 'pane_class' => 'admin-roles admin-roles-list']) ?>
    <div class="role-callout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
        <p>Resolver posture: <strong><?= $e($mode ?? 'shadow') ?></strong>
        (<code>CAPABILITIES_MODE</code>). Under <code>shadow</code> the legacy rules decide and the
        resolver only shadow-compares; under <code>enforce</code> the resolver decides and fails
        closed. Unknown mode values run <code>shadow</code> and emit
        <code>capabilities.mode_invalid</code> telemetry. System roles are protected
        compatibility anchors and cannot be edited — clone one to adapt it. Try changes safely in
        the <a href="/admin/roles/simulator">permission simulator</a>.</p>
    </div>

    <?php $roleCount = count($rows); ?>
    <div class="role-list-meta"><span data-role-count><?= $roleCount ?> <?= $roleCount === 1 ? 'role' : 'roles' ?></span></div>
    <section class="card role-table-card">
        <div class="table-scroll" tabindex="0" role="region" aria-label="Role definitions">
        <table class="audit role-table">
            <thead><tr><th scope="col">Name</th><th scope="col">Key</th><th scope="col">Kind</th><th scope="col" class="role-number">Version</th><th scope="col" class="role-number">Capabilities</th><th scope="col" class="role-number">Active assignments</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $role = $r['role']; ?>
                <tr>
                    <td><?= $e($role['name']) ?></td>
                    <td><code class="role-key"><?= $e($role['role_key']) ?></code></td>
                    <td><?php if (((string) $role['kind']) === 'system'): ?><span class="role-kind role-kind-system">Protected anchor</span><?php else: ?><span class="role-kind role-kind-custom">Custom</span><?php endif; ?></td>
                    <td class="role-number role-version">v<?= (int) $role['version'] ?></td>
                    <td class="role-number"><?= (int) $r['capability_count'] ?></td>
                    <td class="role-number"><?= (int) $r['impact'] ?></td>
                    <td><a class="role-row-action" href="/admin/roles/<?= (int) $role['id'] ?>"><?= ((string) $role['kind']) === 'system' ? 'View / clone' : 'Edit' ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="card role-create-card">
        <h2>Create a custom role</h2>
        <p class="role-section-intro">Only delegable capabilities are offered — protected authority is never on this list. Creating a role is a reauthenticated action.</p>
        <form method="post" action="/admin/roles" class="role-create-form" data-role-capability-form>
            <?= $this->csrfField() ?>
            <div class="role-definition-grid">
                <div>
                    <label class="role-form-label"><span>Name</span>
                        <input class="input" type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? '') ?>"<?= field_attrs($errors ?? [], 'name') ?> required>
                    </label>
                    <?= field_error($errors ?? [], 'name') ?>
                </div>
                <div>
                    <label class="role-form-label"><span>Description (optional)</span>
                        <input class="input" type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? '') ?>"<?= field_attrs($errors ?? [], 'description') ?>>
                    </label>
                    <?= field_error($errors ?? [], 'description') ?>
                </div>
            </div>

            <?php $checked = (array) ($old['capabilities'] ?? []); ?>
            <div class="role-capability-grid">
                <?php foreach ($groupedCatalogue as $groupLabel => $groupCaps): ?>
                    <fieldset>
                        <legend><?= $e($groupLabel) ?></legend>
                        <?php foreach ($groupCaps as $key => $meta): $enforced = \App\Security\EnforcedCapabilities::has($key); ?>
                            <label class="role-capability-option">
                                <input type="checkbox" name="capabilities[]" value="<?= $e($key) ?>" <?= in_array($key, $checked, true) ? 'checked' : '' ?><?= $enforced ? '' : ' disabled' ?>>
                                <span><code><?= $e($key) ?></code><span class="role-capability-description"> — <?= $e($meta['consent'] ?? $meta['description']) ?></span>
                                <?php if ($meta['risk'] === 'high'): ?><span class="role-risk">High risk</span><?php endif; ?>
                                <?php if (!$enforced): ?><span class="role-capability-locked">(not yet enforceable)</span><?php endif; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </fieldset>
                <?php endforeach; ?>
            </div>
            <?= field_error($errors ?? [], 'capabilities') ?>

            <div class="role-create-footer">
                <div class="role-reauth-field">
                    <label class="role-form-label"><span>Confirm your password</span>
                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
                    </label>
                    <?= field_error($errors ?? [], 'current_password') ?>
                </div>
                <button class="btn" type="submit">Create role</button>
            </div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
