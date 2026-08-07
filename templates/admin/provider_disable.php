<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Disable ' . ($row['display_name'] ?? 'provider'));
$this->section('variant', 'admin');
?>
<?= $this->partial('admin/_console', ['area' => 'integrations', 'tab' => 'providers', 'pane_class' => 'admin-integrations integrations-provider-disable']) ?>
    <a class="admin-back" href="/admin/providers">
        <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
        Sign-in providers
    </a>
    <h2 class="admin-record-title">Before you disable <?= $e($row['display_name']) ?></h2>
    <section class="card provider-disable-card">
        <p class="provider-disable-blurb">Disabling removes <strong><?= $e($row['display_name']) ?></strong> from sign-in and
        blocks its <code>/auth/<?= $e($row['provider_key']) ?>/…</code> flow. Linked identities
        are <strong>retained</strong> — re-enabling restores sign-in unchanged.</p>

        <?php if ($sole_accounts === []): ?>
            <p class="provider-disable-none">No accounts rely on this provider as their only sign-in method.</p>
        <?php else: ?>
            <p class="provider-sole-warning" role="alert">
                <?= count($sole_accounts) ?> account<?= count($sole_accounts) === 1 ? '' : 's' ?> can sign in
                <strong>only</strong> through this provider (no password, no passkey, no other provider).
                They will be locked out until they use password reset on their listed email, or you
                re-enable the provider. Contact them first.
            </p>
            <?php /*
             * The design's ruled <ul> replaces the table this page shipped, so the
             * scroll region from the round-2 plan (line 426) carries forward here as
             * a named region: the list is vertical, so it needs the accessible name
             * without the table's focusable horizontal scroll.
             */ ?>
            <div class="provider-sole-region" role="region" aria-label="Sole sign-in accounts">
                <ul class="provider-sole-list">
                    <?php foreach ($sole_accounts as $a): ?>
                        <li class="provider-sole-row">
                            <?= $this->partial('partials/monogram', ['name' => $a['username'], 'username' => $a['username']]) ?>
                            <?php // "Contact them first" needs a path to the record. ?>
                            <a class="provider-sole-name" href="/admin/users?q=<?= $e(urlencode((string) $a['username'])) ?>"><?= $e($a['username']) ?></a>
                            <span class="provider-sole-email"><?= $e($a['email']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php // The provider-level sink (e.g. the builtin write guard) is deliberately un-helpered here. ?>
        <?php if (!empty($errors['provider'])): ?><p class="field-error" role="alert"><?= $e($errors['provider']) ?></p><?php endif; ?>

        <form method="post" action="/admin/providers/<?= (int) $row['id'] ?>/disable" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field field-narrow">
                <span>Your password (re-authentication)</span>
                <input class="input integrations-secret-input" type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>
            <div class="provider-disable-actions">
                <button class="btn danger btn-small" type="submit">Disable <?= $e($row['display_name']) ?></button>
                <a class="provider-disable-cancel" href="/admin/providers">Cancel</a>
            </div>
        </form>
    </section>
<?= $this->partial('admin/_console_end') ?>
