<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Disable ' . ($row['display_name'] ?? 'provider'));
?>
<div class="admin">
    <header class="admin-head">
        <h1>Disable <?= $e($row['display_name']) ?></h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'providers', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Before you disable</h2>
        <p>Disabling removes <strong><?= $e($row['display_name']) ?></strong> from sign-in and
        blocks its <code>/auth/<?= $e($row['provider_key']) ?>/…</code> flow. Linked identities
        are <strong>retained</strong> — re-enabling restores sign-in unchanged.</p>

        <?php if ($sole_accounts === []): ?>
            <p class="muted">No accounts rely on this provider as their only sign-in method.</p>
        <?php else: ?>
            <p class="field-error" role="alert">
                <?= count($sole_accounts) ?> account<?= count($sole_accounts) === 1 ? '' : 's' ?> can sign in
                <strong>only</strong> through this provider (no password, no passkey, no other provider).
                They will be locked out until they use password reset on their listed email, or you
                re-enable the provider. Contact them first.
            </p>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Sole sign-in accounts">
            <table class="audit">
                <thead><tr><th scope="col">Account</th><th scope="col">Email</th></tr></thead>
                <tbody>
                <?php foreach ($sole_accounts as $a): ?>
                    <tr>
                        <td><a href="/admin/users?q=<?= $e(urlencode((string) $a['username'])) ?>"><?= $e($a['username']) ?></a></td>
                        <td><?= $e($a['email']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors['provider'])): ?><p class="field-error" role="alert"><?= $e($errors['provider']) ?></p><?php endif; ?>

        <form method="post" action="/admin/providers/<?= (int) $row['id'] ?>/disable" class="stacked">
            <?= $this->csrfField() ?>
            <label>Your password (re-authentication)
                <input type="password" name="current_password" autocomplete="current-password"<?= field_attrs($errors ?? [], 'current_password') ?> required>
            </label>
            <?= field_error($errors ?? [], 'current_password') ?>
            <button class="btn" type="submit">Disable <?= $e($row['display_name']) ?></button>
            <a class="btn btn-secondary" href="/admin/providers">Cancel</a>
        </form>
    </section>
    </div>
</div>
