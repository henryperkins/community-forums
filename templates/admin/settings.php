<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'General & registration');
$errors = $settings_errors ?? [];
$old = $settings_old ?? [];
$siteValue = (string) ($old['site_name'] ?? $site_name ?? '');
$registrationSelected = (string) ($old['registration_mode'] ?? $registration_mode ?? 'open');
$modes = $registration_modes ?? \App\Security\RegistrationPolicy::MODES;
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Operator desk</span>
            <h1>General & registration</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'settings', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <p class="pane-intro">Manage the community name and who can create an account. Each form saves only its own setting.</p>

        <section class="card settings-card" aria-labelledby="site-name-heading">
            <h2 id="site-name-heading">Site name</h2>
            <p class="muted">Shown throughout the community and in system messages.</p>
            <form method="post" action="/admin/site" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field" for="admin-site-name">
                    <span>Community name</span>
                    <input type="text" id="admin-site-name" name="site_name" class="input" maxlength="80" value="<?= $e($siteValue) ?>" required aria-describedby="site-name-help<?= !empty($errors['site_name']) ? ' site-name-error' : '' ?>">
                    <span class="muted" id="site-name-help">Use 1–80 characters.</span>
                    <?php if (!empty($errors['site_name'])): ?>
                        <span class="field-error" id="site-name-error"><?= $e($errors['site_name']) ?></span>
                    <?php endif; ?>
                </label>
                <div class="form-actions"><button class="btn" type="submit">Save site name</button></div>
            </form>
        </section>

        <section class="card settings-card" aria-labelledby="registration-heading">
            <h2 id="registration-heading">Registration</h2>
            <p class="muted">Choose whether new members can join directly, need an invitation, or cannot register.</p>
            <form method="post" action="/admin/settings/registration" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field" for="admin-registration-mode">
                    <span>Registration mode</span>
                    <select id="admin-registration-mode" name="registration_mode" class="input" aria-describedby="registration-help<?= !empty($errors['registration_mode']) ? ' registration-error' : '' ?>">
                        <?php if (!in_array($registrationSelected, $modes, true)): ?>
                            <option value="<?= $e($registrationSelected) ?>" selected><?= $e($registrationSelected) ?></option>
                        <?php endif; ?>
                        <?php $notes = ['open' => 'Open — anyone can register', 'invite' => 'Invite only (invitation required)', 'closed' => 'Closed (no new sign-ups)']; ?>
                        <?php foreach ($modes as $mode): ?>
                            <option value="<?= $e($mode) ?>"<?= $registrationSelected === $mode ? ' selected' : '' ?>><?= $e($notes[$mode] ?? ucfirst($mode)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="muted" id="registration-help">Existing members can continue signing in in every mode.</span>
                    <?php if ($registrationSelected === 'invite' && empty($invitations_flag_on)): ?>
                        <span class="field-error">Registration mode is “invite” but the invitations feature is off — registration is effectively closed.</span>
                    <?php endif; ?>
                    <?php if (!empty($errors['registration_mode'])): ?>
                        <span class="field-error" id="registration-error"><?= $e($errors['registration_mode']) ?></span>
                    <?php endif; ?>
                </label>
                <div class="form-actions"><button class="btn" type="submit">Save registration mode</button></div>
            </form>
        </section>
    </div>
</div>
