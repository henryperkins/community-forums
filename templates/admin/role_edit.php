<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Role: ' . ($row['role']['name'] ?? ''));
$this->section('variant', 'admin');
$role = $row['role'];
$isSystem = ((string) $role['kind']) === 'system';
$checked = (array) ($old['capabilities'] ?? $current_keys);
// This page hosts five forms (edit-definition, clone, per-row renew, assign)
// that share one flat $errors bag and some field names (current_password,
// ends_at). Scope each form's error paragraphs to the failure that produced
// them via mutually exclusive context flags, so e.g. a renew reauth error lands
// on its row only — never duplicated on the edit-definition or assign forms.
$renewAssignmentId = (int) ($old['renew_assignment_id'] ?? 0);
$renewErrorContext = isset($old['renew_assignment_id']);
$assignErrorContext = isset($old['assignment']);
$cloneErrorContext = isset($old['clone']);
$defErrorContext = !$renewErrorContext && !$assignErrorContext && !$cloneErrorContext;
$allErrors = is_array($errors ?? null) ? $errors : [];
$defErrors = $defErrorContext ? $allErrors : [];
$cloneErrors = $cloneErrorContext ? $allErrors : [];
$assignErrors = $assignErrorContext ? $allErrors : [];
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
<?= $this->partial('admin/_console', ['area' => 'people', 'tab' => 'roles', 'pane_class' => 'admin-roles admin-role-record']) ?>
    <a class="admin-back" href="/admin/roles">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        All roles
    </a>
    <div class="role-record-heading">
        <h2 class="admin-record-title"><?= $e($role['name']) ?></h2>
        <span>v<?= (int) $role['version'] ?></span>
    </div>
    <p class="role-record-note">
        <code><?= $e($role['role_key']) ?></code> —
        <?= $isSystem ? 'Protected system anchor, read-only.' : 'Custom role.' ?>
        Active assignments affected by changes: <strong><?= (int) $row['impact'] ?></strong>.
    </p>

    <?php if (!$isSystem): ?>
    <section class="card role-definition-card">
        <h3>Edit definition</h3>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>" class="role-definition-form">
            <?= $this->csrfField() ?>
            <div class="role-definition-grid">
                <div>
                    <label class="role-form-label"><span>Name</span>
                        <input class="input" type="text" name="name" maxlength="190" value="<?= $e($old['name'] ?? $role['name']) ?>"<?= field_attrs($defErrors, 'name', 'err-role-definition-name') ?> required>
                    </label>
                    <?= field_error($defErrors, 'name', 'err-role-definition-name') ?>
                </div>
                <div>
                    <label class="role-form-label"><span>Description (optional)</span>
                        <input class="input" type="text" name="description" maxlength="255" value="<?= $e($old['description'] ?? ($role['description'] ?? '')) ?>"<?= field_attrs($defErrors, 'description', 'err-role-definition-description') ?>>
                    </label>
                    <?= field_error($defErrors, 'description', 'err-role-definition-description') ?>
                </div>
            </div>

            <div class="role-capability-grid"<?php if (!empty($defErrors['capabilities'])): ?> role="group" aria-invalid="true" aria-describedby="err-role-definition-capabilities"<?php endif; ?>>
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
            <?= field_error($defErrors, 'capabilities', 'err-role-definition-capabilities') ?>

            <div class="role-definition-footer">
                <div class="role-reauth-field">
                    <label class="role-form-label"><span>Confirm your password</span>
                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($defErrors, 'current_password', 'err-role-definition-current_password') ?> required>
                    </label>
                    <?= field_error($defErrors, 'current_password', 'err-role-definition-current_password') ?>
                </div>
                <button class="btn" type="submit">Save (bumps version)</button>
            </div>
        </form>
    </section>
    <?php else: ?>
    <section class="card role-held-card">
        <h3>Capabilities held</h3>
        <ul class="role-held-list">
            <?php foreach ($current_keys as $key): ?><li><code><?= $e($key) ?></code></li><?php endforeach; ?>
        </ul>
    </section>
    <?php endif; ?>

    <section class="card role-clone-card">
        <h3>Clone into a new custom role</h3>
        <p class="role-section-intro">Cloning copies only currently-enforceable capabilities, so the copy is never wider than the anchor.</p>
        <?= field_error($cloneErrors, 'role', 'err-role-clone-role', alert: true) ?>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/clone" class="role-clone-form">
            <?= $this->csrfField() ?>
            <div>
                <label class="role-form-label"><span>New role name</span>
                    <input class="input" type="text" name="name" maxlength="190" value="<?= $e((string) ($old['clone']['name'] ?? '')) ?>"<?= field_attrs($cloneErrors, 'name', 'err-role-clone-name') ?> required>
                </label>
                <?= field_error($cloneErrors, 'name', 'err-role-clone-name') ?>
            </div>
            <div class="role-reauth-field">
                <label class="role-form-label"><span>Confirm your password</span>
                    <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($cloneErrors, 'current_password', 'err-role-clone-current_password') ?> required>
                </label>
                <?= field_error($cloneErrors, 'current_password', 'err-role-clone-current_password') ?>
            </div>
            <button class="btn btn-secondary" type="submit">Clone</button>
        </form>
    </section>

    <?php if (!$isSystem): ?>
    <?php
    $boardNames = [];
    foreach (($boards ?? []) as $b) {
        $boardNames[(int) $b['id']] = (string) $b['name'];
    }
    // Categories and boards auto-increment independently, so scope_id must be
    // resolved against the map matching the assignment's scope_type — resolving a
    // category scope_id through the board map shows an unrelated board's name (V4).
    $categoryNames = [];
    foreach (($categories ?? []) as $c) {
        $categoryNames[(int) $c['id']] = (string) $c['name'];
    }
    ?>
    <section class="card role-assignments-card">
        <h3>Assignments</h3>
        <?php if (empty($assignments)): ?>
        <p class="role-assignment-empty">No one has been assigned this role yet.</p>
        <?php else: ?>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Role assignments">
        <table class="audit role-assignment-table">
            <thead><tr><th scope="col">Member</th><th scope="col">Scope</th><th scope="col">Window</th><th scope="col">Status</th><th scope="col"><span class="sr-only">Actions</span></th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
                <?php
                $assignmentId = (int) $a['id'];
                $assignmentStatus = in_array((string) $a['status'], ['active', 'scheduled', 'expired', 'revoked'], true)
                    ? (string) $a['status']
                    : 'expired';
                $rowRenewErrors = $renewAssignmentId === $assignmentId ? $allErrors : [];
                ?>
                <tr>
                    <td><a class="role-assignment-member" href="/u/<?= $e($a['username']) ?>">@<?= $e($a['username']) ?></a></td>
                    <td><?= $e((string) $a['scope_type']) ?><?php if ($a['scope_id'] !== null): ?> &mdash; <?php
                        $scopeId = (int) $a['scope_id'];
                        $scopeName = ((string) $a['scope_type'] === 'category')
                            ? ($categoryNames[$scopeId] ?? ('#' . $scopeId))
                            : ($boardNames[$scopeId] ?? ('#' . $scopeId));
                        ?><?= $e($scopeName) ?><?php endif; ?></td>
                    <td class="role-assignment-window"><?= $e((string) ($a['starts_at'] ?? 'now')) ?> &rarr; <?= $e((string) ($a['ends_at'] ?? 'no expiry')) ?></td>
                    <td><span class="role-assignment-status role-assignment-status-<?= $e($assignmentStatus) ?>"><?= $e(ucfirst($assignmentStatus)) ?></span></td>
                    <td class="role-assignment-action-cell">
                        <?php if ($a['status'] !== 'revoked'): ?>
                        <div class="role-assignment-actions">
                            <form method="post" action="/admin/role-assignments/<?= $assignmentId ?>/revoke" class="inline">
                                <?= $this->csrfField() ?>
                                <button class="linkbtn danger" type="submit" aria-label="Revoke this role from @<?= $e($a['username']) ?>">Revoke</button>
                            </form>
                            <form method="post" action="/admin/role-assignments/<?= $assignmentId ?>/renew" class="role-renew-form">
                                <?= $this->csrfField() ?>
                                <input type="text" class="input" name="ends_at" placeholder="YYYY-MM-DD HH:MM" aria-label="New expiry (UTC) for @<?= $e($a['username']) ?>" value="<?= $renewAssignmentId === $assignmentId ? $e((string) ($old['renew']['ends_at'] ?? '')) : '' ?>"<?= field_attrs($rowRenewErrors, 'ends_at', 'err-role-renew-' . $assignmentId . '-ends_at') ?> required>
                                <label class="sr-only" for="renew-password-<?= $assignmentId ?>">Your password to renew @<?= $e($a['username']) ?>'s assignment</label>
                                <input type="password" class="input" id="renew-password-<?= $assignmentId ?>" name="current_password" placeholder="Your password" autocomplete="current-password"<?= field_attrs($rowRenewErrors, 'current_password', 'err-role-renew-' . $assignmentId . '-current_password') ?> required>
                                <button class="btn btn-small" type="submit">Renew</button>
                            </form>
                        </div>
                        <?php endif; ?>
                        <?php if ($renewAssignmentId === $assignmentId): ?>
                            <?php // Errors render OUTSIDE the status!=='revoked' guard: a renew that
                                  // fails BECAUSE the row was concurrently revoked must still show its
                                  // 'assignment' error, not vanish with the form (review V5). ?>
                            <?= field_error($rowRenewErrors, 'current_password', 'err-role-renew-' . $assignmentId . '-current_password') ?>
                            <?= field_error($rowRenewErrors, 'ends_at', 'err-role-renew-' . $assignmentId . '-ends_at') ?>
                            <?= field_error($rowRenewErrors, 'assignment', 'err-role-renew-' . $assignmentId . '-assignment', alert: true) ?>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>

        <h4>Assign this role</h4>
        <form method="post" action="/admin/roles/<?= (int) $role['id'] ?>/assignments" class="role-assign-form">
            <?= $this->csrfField() ?>
            <?= field_error($assignErrors, 'role', 'err-role-assign-role', alert: true) ?>
            <?= field_error($assignErrors, 'capabilities', 'err-role-assign-capabilities', alert: true) ?>
            <div>
                <label class="role-form-label"><span>Member username</span>
                    <input class="input role-mono-input" type="text" name="username" maxlength="32" value="<?= $e((string) ($old['assignment']['username'] ?? '')) ?>"<?= field_attrs($assignErrors, 'username', 'err-role-assign-username') ?> required>
                </label>
                <?= field_error($assignErrors, 'username', 'err-role-assign-username') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Scope</span>
                    <select class="input" name="scope_type"<?= field_attrs($assignErrors, 'scope_type', 'err-role-assign-scope_type') ?>>
                        <?php foreach (['site' => 'Site-wide', 'category' => 'Category', 'board' => 'Board'] as $value => $label): ?>
                            <option value="<?= $e($value) ?>"<?= ($old['assignment']['scope_type'] ?? 'site') === $value ? ' selected' : '' ?>><?= $e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <?= field_error($assignErrors, 'scope_type', 'err-role-assign-scope_type') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Board / category id <span class="role-label-note">(blank = site-wide)</span></span>
                    <input class="input role-mono-input" type="text" name="scope_id" list="assignment-board-options" value="<?= $e((string) ($old['assignment']['scope_id'] ?? '')) ?>"<?= field_attrs($assignErrors, 'scope_id', 'err-role-assign-scope_id') ?>>
                    <datalist id="assignment-board-options">
                        <?php foreach (($boards ?? []) as $b): ?><option value="<?= (int) $b['id'] ?>" label="<?= $e((string) $b['name']) ?>"><?php endforeach; ?>
                    </datalist>
                </label>
                <?= field_error($assignErrors, 'scope_id', 'err-role-assign-scope_id') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Starts (UTC) <span class="role-label-note">(blank starts now)</span></span>
                    <input class="input role-time-input" type="text" name="starts_at" placeholder="2026-08-10 09:00" value="<?= $e((string) ($old['assignment']['starts_at'] ?? '')) ?>"<?= field_attrs($assignErrors, 'starts_at', 'err-role-assign-starts_at', 'role-assign-time-format') ?>>
                </label>
                <?= field_error($assignErrors, 'starts_at', 'err-role-assign-starts_at') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Ends (UTC) <span class="role-label-note">(blank never expires)</span></span>
                    <input class="input role-time-input" type="text" name="ends_at" placeholder="2026-11-01 00:00" value="<?= $e((string) ($old['assignment']['ends_at'] ?? '')) ?>"<?= field_attrs($assignErrors, 'ends_at', 'err-role-assign-ends_at', 'role-assign-time-format') ?>>
                </label>
                <?= field_error($assignErrors, 'ends_at', 'err-role-assign-ends_at') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Confirm your password</span>
                    <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($assignErrors, 'current_password', 'err-role-assign-current_password') ?> required>
                </label>
                <?= field_error($assignErrors, 'current_password', 'err-role-assign-current_password') ?>
            </div>

            <div>
                <label class="role-form-label"><span>Reason (optional)</span>
                    <input class="input" type="text" name="reason" maxlength="255" value="<?= $e((string) ($old['assignment']['reason'] ?? '')) ?>">
                </label>
            </div>

            <p class="role-time-format" id="role-assign-time-format">Date-time format: <code>YYYY-MM-DD HH:MM</code> (UTC).</p>
            <div class="role-assign-actions"><button class="btn" type="submit">Assign</button></div>
        </form>
    </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
