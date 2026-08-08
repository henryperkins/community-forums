<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Server extensions'); $this->section('variant', 'admin'); ?>
<?= $this->partial('admin/_console', ['area' => 'packages', 'tab' => 'extensions', 'pane_class' => 'admin-packages packages-extensions']) ?>
    <?php // constraint C-32: the design's copy for this note ("reserved and dark under
          // Gate B", "the flag is dark — no handler is dispatched") is false in the only
          // state production can render this page in: AdminExtensionController 404s while
          // server_extensions is off, so reaching here means the flag is ON. ?>
    <div class="callout extension-flag-note">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-5M12 8h.01"/></svg>
        <p><strong>Global emergency disable.</strong> Server extension execution is controlled by the server-side <code>server_extensions</code> flag; this page is only reachable while that flag is on. Turning it off closes this console and leaves core forum routes independent of extension code.</p>
    </div>

    <div class="admin-split">
        <section class="card packages-panel-card">
            <h3 class="packages-eyebrow">Sandbox probe</h3>
            <?php // constraint ADR 0011: the admin page must report a failed probe. Never
                  // hardcode the available/adapter values the design's fixture shows. ?>
            <p>
                <strong class="extension-probe-state"><?= !empty($probe['supported']) ? 'available' : 'unavailable' ?></strong>
                <code class="extension-probe-adapter"><?= $e((string) ($probe['adapter'] ?? 'unknown')) ?></code>
            </p>
            <?php if (!empty($probe['reason'])): ?><p class="extension-probe-reason"><?= $e((string) $probe['reason']) ?></p><?php endif; ?>
        </section>

        <section class="card packages-table-card">
            <h3 class="packages-eyebrow">Handlers</h3>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Server extension handlers">
            <table class="audit packages-table extension-handlers-table">
                <thead><tr><th scope="col">Package</th><th scope="col">Handler</th><th scope="col">Status</th><th scope="col">Entrypoint</th></tr></thead>
                <tbody>
                <?php foreach ($handlers as $h): ?>
                    <tr>
                        <td><?= $e((string) ($h['package_name'] ?? $h['package_uid'] ?? 'package')) ?></td>
                        <td class="packages-col-nowrap"><code class="packages-mono"><?= $e((string) $h['handler_key']) ?></code></td>
                        <?php // feature-added: the design's three-column table drops Status.
                              // Handler quarantine state is operator-relevant. ?>
                        <td class="packages-col-nowrap"><?= $e((string) $h['status']) ?></td>
                        <td><code class="packages-mono"><?= $e((string) $h['entrypoint']) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($handlers)): ?><tr><td colspan="4" class="packages-empty">No server extension handlers installed.</td></tr><?php endif; ?>
                </tbody>
            </table>
            </div>
        </section>
    </div>

    <section class="card packages-panel-card">
        <h3 class="packages-eyebrow">Run history</h3>
        <?php if (empty($runs)): ?>
            <p class="packages-empty">No extension runs yet.</p>
        <?php else: ?>
        <ul class="packages-rule-list">
            <?php foreach ($runs as $run): ?>
                <li class="packages-rule-row is-stacked">
                    <span class="packages-rule-head">
                        <code class="packages-rule-label"><?= $e((string) $run['handler_key']) ?></code>
                        <?php // The design inks "ok" with --success at .72rem, which measures
                              // 4.45:1 in twilight. --on-done is the AA-safe success ink. ?>
                        <?php if ((string) $run['status'] === 'ok'): ?>
                            <span class="packages-pill is-done">ok</span>
                        <?php else: ?>
                            <span class="packages-pill is-danger"><?= $e((string) $run['status']) ?></span>
                        <?php endif; ?>
                        <span class="packages-rule-when"><?= $e(human_datetime((string) $run['finished_at'])) ?></span>
                    </span>
                    <?php if ((string) ($run['error'] ?? '') !== ''): ?><span class="packages-rule-detail"><?= $e((string) $run['error']) ?></span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </section>
<?= $this->partial('admin/_console_end') ?>
