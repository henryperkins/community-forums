<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'General & registration');
$this->section('variant', 'admin');
$errors = $settings_errors ?? [];
$old = $settings_old ?? [];
$siteValue = (string) ($old['site_name'] ?? $site_name ?? '');
$registrationSelected = (string) ($old['registration_mode'] ?? $registration_mode ?? 'open');
$modes = $registration_modes ?? \App\Security\RegistrationPolicy::MODES;
?>
<?= $this->partial('admin/_console', ['area' => 'settings', 'tab' => 'settings']) ?>
        <div class="settings-general-grid">
            <section class="card settings-card settings-general-card" aria-labelledby="site-name-heading">
                <h2 id="site-name-heading">Identity</h2>
                <p class="muted settings-card-intro">The name this community goes by — in the topbar, in every email, and on the sign-in page.</p>
                <form method="post" action="/admin/site" class="stacked">
                    <?= $this->csrfField() ?>
                    <label class="field" for="admin-site-name">
                        <span class="settings-field-label">Community name</span>
                        <input type="text" id="admin-site-name" name="site_name" class="input" maxlength="80" value="<?= $e($siteValue) ?>"<?= field_attrs($errors, 'site_name', null, 'site-name-help') ?> required>
                        <span class="muted" id="site-name-help">Use 1–80 characters.</span>
                    </label>
                    <?= field_error($errors, 'site_name') ?>
                    <div class="form-actions settings-actions"><button class="btn" type="submit">Save name</button></div>
                </form>
            </section>

            <section class="card settings-card settings-general-card" aria-labelledby="registration-heading">
                <h2 id="registration-heading">Registration</h2>
                <p class="muted settings-card-intro">Choose whether new members can join directly, need an invitation, or cannot register.</p>
                <form method="post" action="/admin/settings/registration" class="stacked">
                    <?= $this->csrfField() ?>
                    <label class="field" for="admin-registration-mode">
                        <span class="settings-field-label">Registration mode</span>
                        <select id="admin-registration-mode" name="registration_mode" class="input"<?= field_attrs($errors, 'registration_mode', null, 'registration-help') ?>>
                            <?php if (!in_array($registrationSelected, $modes, true)): ?>
                                <option value="<?= $e($registrationSelected) ?>" selected><?= $e($registrationSelected) ?></option>
                            <?php endif; ?>
                            <?php $notes = ['open' => 'Open — anyone can register', 'invite' => 'Invite only (invitation required)', 'closed' => 'Closed (no new sign-ups)']; ?>
                            <?php foreach ($modes as $mode): ?>
                                <option value="<?= $e($mode) ?>"<?= $registrationSelected === $mode ? ' selected' : '' ?>><?= $e($notes[$mode] ?? ucfirst($mode)) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="muted" id="registration-help">Existing members can continue signing in in every mode.</span>
                    </label>
                    <?php if ($registrationSelected === 'invite' && empty($invitations_flag_on)): ?>
                        <p class="settings-invite-conflict" role="alert">Registration mode is “invite” but the invitations feature is off — registration is effectively closed.</p>
                    <?php endif; ?>
                    <?= field_error($errors, 'registration_mode') ?>
                    <div class="form-actions settings-actions"><button class="btn" type="submit">Save registration mode</button></div>
                </form>
            </section>
        </div>
<?= $this->partial('admin/_console_end') ?>
