<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Roles');
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'people', 'tab' => 'roles', 'pane_class' => 'admin-roles admin-roles-list']) ?>
    <div class="role-callout">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
        <div>
            <?= $this->partial('admin/_role_live_effect', ['mode' => $mode]) ?>
            <p>System roles are protected compatibility anchors and cannot be edited. Clone one to adapt it.
            Check a proposed permission in the <a href="/admin/roles/simulator">permission simulator</a>.</p>
        </div>
    </div>

    <?php $roleCount = count($rows); ?>
    <div class="role-list-meta"><span data-role-count><?= $roleCount ?> <?= $roleCount === 1 ? 'role' : 'roles' ?></span></div>
    <section class="card role-table-card">
        <div class="role-table-shell" data-overflow-cue>
        <p class="table-scroll-cue" data-overflow-cue-label>Scroll for role details and explicit assignments <span aria-hidden="true">→</span></p>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Role definitions" data-overflow-region>
        <table class="audit role-table">
            <thead><tr><th scope="col">Name</th><th scope="col"><span class="sr-only">Actions</span></th><th scope="col">Key</th><th scope="col">Kind</th><th scope="col" class="role-number">Version</th><th scope="col" class="role-number">Capabilities</th><th scope="col" class="role-number">Active explicit assignments</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): $role = $r['role']; ?>
                <tr>
                    <td><?= $e($role['name']) ?></td>
                    <td><a class="role-row-action" href="/admin/roles/<?= (int) $role['id'] ?>"><?= ((string) $role['kind']) === 'system' ? 'View / clone' : 'Edit' ?></a></td>
                    <td><code class="role-key"><?= $e($role['role_key']) ?></code></td>
                    <td><?php if (((string) $role['kind']) === 'system'): ?><span class="role-kind role-kind-system">Protected anchor</span><?php else: ?><span class="role-kind role-kind-custom">Custom</span><?php endif; ?></td>
                    <td class="role-number role-version">v<?= (int) $role['version'] ?></td>
                    <td class="role-number"><?= (int) $r['capability_count'] ?></td>
                    <td class="role-number"><?= (int) $r['impact'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        </div>
        <p class="role-section-intro role-assignment-count-note">These counts cover explicit role grants. They do not count members’ built-in User, Moderator, or Admin roles.</p>
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
            <?= $this->partial('admin/_role_capabilities', ['catalogue' => $catalogue, 'checked' => $checked, 'errors' => $errors ?? [], 'error_prefix' => 'err-role-create']) ?>

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
