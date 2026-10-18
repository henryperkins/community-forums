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
?>
<?= $this->partial('admin/_console', ['area' => 'people', 'tab' => 'roles', 'pane_class' => 'admin-roles admin-role-record']) ?>
    <?= $this->partial('partials/back_link', ['href' => '/admin/roles', 'label' => 'All roles']) ?>
    <div class="role-record-heading">
        <h2 class="admin-record-title"><?= $e($role['name']) ?></h2>
        <span>v<?= (int) $role['version'] ?></span>
    </div>
    <p class="role-record-note">
        <code><?= $e($role['role_key']) ?></code> —
        <?= $isSystem ? 'Protected system anchor, read-only.' : 'Custom role.' ?>
        Active explicit assignments: <strong><?= (int) $row['impact'] ?></strong>.
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

            <?= $this->partial('admin/_role_capabilities', ['catalogue' => $catalogue, 'checked' => $checked, 'errors' => $defErrors, 'error_prefix' => 'err-role-definition']) ?>

            <?= $this->partial('admin/_role_live_effect', ['mode' => $mode]) ?>
            <div class="role-definition-footer">
                <div class="role-reauth-field">
                    <label class="role-form-label"><span>Confirm your password</span>
                        <input class="input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($defErrors, 'current_password', 'err-role-definition-current_password') ?> required>
                    </label>
                    <?= field_error($defErrors, 'current_password', 'err-role-definition-current_password') ?>
                </div>
                <button class="btn" type="submit">Save role</button>
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

            <?php
            $targetOptions = ['site' => 'Site-wide'];
            foreach (($categories ?? []) as $category) {
                $targetOptions['category:' . (int) $category['id']] = 'Category: ' . $category['name'];
            }
            foreach (($boards ?? []) as $board) {
                $targetOptions['board:' . (int) $board['id']] = 'Board: ' . $board['name'];
            }
            $oldScopeType = (string) ($old['assignment']['scope_type'] ?? 'site');
            $scopeTarget = $old['assignment']['scope_target'] ?? ($oldScopeType === 'site' ? 'site' : $oldScopeType . ':' . (string) ($old['assignment']['scope_id'] ?? ''));
            ?>
            <div>
                <label class="role-form-label"><span>Scope and target</span>
                    <select class="input" name="scope_target"<?= field_attrs($assignErrors, 'scope_target', 'err-role-assign-scope_target') ?>>
                        <option value="site"<?= $scopeTarget === 'site' ? ' selected' : '' ?>>Site-wide</option>
                        <?php if (!isset($targetOptions[$scopeTarget])): ?>
                            <option value="<?= $e($scopeTarget) ?>" selected>Unavailable target: <?= $e($scopeTarget) ?> — choose again</option>
                        <?php endif; ?>
                        <optgroup label="Categories">
                            <?php foreach (($categories ?? []) as $category): $value = 'category:' . (int) $category['id']; ?>
                                <option value="<?= $e($value) ?>"<?= $scopeTarget === $value ? ' selected' : '' ?>><?= $e($targetOptions[$value]) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="Boards">
                            <?php foreach (($boards ?? []) as $board): $value = 'board:' . (int) $board['id']; ?>
                                <option value="<?= $e($value) ?>"<?= $scopeTarget === $value ? ' selected' : '' ?>><?= $e($targetOptions[$value]) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </label>
                <?= field_error($assignErrors, 'scope_target', 'err-role-assign-scope_target') ?>
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
            <?= $this->partial('admin/_role_live_effect', ['mode' => $mode]) ?>
            <div class="role-assign-actions"><button class="btn" type="submit">Assign</button></div>
        </form>
    </section>
    <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
