<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Server extensions'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Server extensions</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <?= $this->partial('admin/_nav', ['active' => 'extensions', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <section class="card">
        <h2>Sandbox probe</h2>
        <p>
            <strong><?= !empty($probe['supported']) ? 'available' : 'unavailable' ?></strong>
            <span class="muted"><?= $e((string) ($probe['adapter'] ?? 'unknown')) ?></span>
        </p>
        <?php if (!empty($probe['reason'])): ?><p class="muted"><?= $e((string) $probe['reason']) ?></p><?php endif; ?>
    </section>

    <section class="card">
        <h2>Global emergency disable</h2>
        <p class="muted">Server extension execution is controlled by the server-side <code>server_extensions</code> feature flag. Turning it off leaves core forum routes independent of extension code.</p>
    </section>

    <section class="card">
        <h2>Handlers</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Server extension handlers">
        <table class="audit">
            <thead><tr><th scope="col">Package</th><th scope="col">Handler</th><th scope="col">Status</th><th scope="col">Entrypoint</th></tr></thead>
            <tbody>
            <?php foreach ($handlers as $h): ?>
                <tr>
                    <td><?= $e((string) ($h['package_name'] ?? $h['package_uid'] ?? 'package')) ?></td>
                    <td><?= $e((string) $h['handler_key']) ?></td>
                    <td><?= $e((string) $h['status']) ?></td>
                    <td><code><?= $e((string) $h['entrypoint']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($handlers)): ?><tr><td colspan="4" class="muted">No server extension handlers installed.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>

    <section class="card">
        <h2>Run history</h2>
        <div class="table-scroll" tabindex="0" role="region" aria-label="Extension run history">
        <table class="audit">
            <thead><tr><th scope="col">When</th><th scope="col">Handler</th><th scope="col">Status</th><th scope="col">Detail</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td><?= $e(human_datetime((string) $run['finished_at'])) ?></td>
                    <td><?= $e((string) $run['handler_key']) ?></td>
                    <td><?= $e((string) $run['status']) ?></td>
                    <td><?= $e((string) ($run['error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($runs)): ?><tr><td colspan="4" class="muted">No extension runs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
        </div>
    </section>
    </div>
</div>
