<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Link previews');
$this->section('variant', 'admin');
$errors = $errors ?? [];
$old = $old ?? [];
$counts = $preview['counts'];
$rows = $preview['rows'];
$boards = $preview['boards'];
$filter = (string) ($preview['status_filter'] ?? '');
// Re-render after a rejected save keeps what the operator typed; a clean render
// shows what is stored.
$hostsText = array_key_exists('allowed_hosts', $old)
    ? (string) $old['allowed_hosts']
    : (string) $preview['allowed_hosts_text'];
$killSwitch = $errors === [] ? !empty($preview['kill_switch']) : !empty($old['kill_switch']);
$statusLabels = [
    'queued' => 'Queued',
    'fetched' => 'Rendering',
    'blocked' => 'Blocked',
    'failed' => 'Failed',
    'purged' => 'Purged',
    'removed' => 'Removed by author',
];
$statusClass = [
    'queued' => 'state-pending',
    'fetched' => 'state-active',
    'blocked' => 'state-failed',
    'failed' => 'state-failed',
    'purged' => 'state-paused',
    'removed' => 'state-paused',
];
?>
<?= $this->partial('admin/_console', ['area' => 'features', 'tab' => 'link_previews', 'pane_class' => 'admin-features features-link-previews']) ?>
    <p class="pane-intro features-intro">Link previews are fetched server-side and only for public boards. Three gates must all be open before a URL is ever requested: the <code>link_previews</code> feature flag, the board&rsquo;s own opt-in, and the host allowlist below. Authors can remove a preview from their own post at any time &mdash; those rows are never refetched.</p>

    <?php if (!empty($preview['blockers'])): ?>
        <div class="callout callout-review link-preview-blockers">
            <p><strong>Nothing is being fetched right now.</strong></p>
            <ul>
                <?php foreach ($preview['blockers'] as $blocker): ?>
                    <li><?= $e((string) $blocker) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <section class="features-stat-grid" aria-label="Link preview queue summary">
        <div class="card features-stat">
            <span class="features-stat-head">Queued</span>
            <strong class="features-stat-count"><?= (int) $counts['queued'] ?></strong>
            <span class="features-stat-detail">drained by <code>worker:previews</code></span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Rendering</span>
            <strong class="features-stat-count"><?= (int) $counts['fetched'] ?></strong>
            <span class="features-stat-detail"><?= (int) $counts['purged'] ?> purged · <?= (int) $counts['removed'] ?> removed by authors</span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Blocked</span>
            <strong class="features-stat-count"><?= (int) $counts['blocked'] ?></strong>
            <span class="features-stat-detail">refused by the allowlist or egress guard</span>
        </div>
        <div class="card features-stat">
            <span class="features-stat-head">Boards opted in</span>
            <strong class="features-stat-count"><?= (int) $preview['boards_opted_in'] ?></strong>
            <span class="features-stat-detail"><?= (int) $counts['failed'] ?> fetch<?= (int) $counts['failed'] === 1 ? '' : 'es' ?> failed</span>
        </div>
    </section>

    <div class="features-two-up link-preview-config">
        <section class="card features-form-card" aria-labelledby="link-preview-settings-heading">
            <h2 id="link-preview-settings-heading">Allowlist &amp; kill switch</h2>
            <form method="post" action="/admin/link-previews/settings" class="stacked">
                <?= $this->csrfField() ?>
                <label class="field">
                    <span>Allowed hosts</span>
                    <textarea name="allowed_hosts" class="input link-preview-hosts" rows="6" placeholder="example.com&#10;*.wikipedia.org"<?= field_attrs($errors, 'allowed_hosts', 'err-preview-hosts', 'hint-preview-hosts') ?>><?= $e($hostsText) ?></textarea>
                    <span class="field-hint" id="hint-preview-hosts">One host per line (commas also work). <code>*.example.com</code> matches sub-domains. An empty list refuses every fetch.</span>
                    <?php if (!empty($errors['allowed_hosts'])): ?><span class="field-error" id="err-preview-hosts"><?= $e((string) $errors['allowed_hosts']) ?></span><?php endif; ?>
                </label>
                <?php if ((string) $preview['hosts_source'] === 'config'): ?>
                    <p class="features-note">No allowlist is stored yet, so the <code>LINK_PREVIEW_ALLOWED_HOSTS</code> environment value is in effect. Saving this form stores an explicit list that takes precedence over it.</p>
                <?php endif; ?>
                <label class="checkline features-switch">
                    <input type="hidden" name="kill_switch" value="0">
                    <input type="checkbox" name="kill_switch" value="1"<?= $killSwitch ? ' checked' : '' ?>>
                    <span>Engage the kill switch (worker skips every queued row)</span>
                </label>
                <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
            </form>
            <p class="features-note">Transport: timeout <?= (int) $preview['transport']['timeout_seconds'] ?>s, at most <?= (int) round($preview['transport']['max_bytes'] / 1024) ?>&nbsp;KiB read per response, plaintext HTTP <?= !empty($preview['transport']['allow_http']) ? 'allowed' : 'refused' ?>. These are environment settings (<code>LINK_PREVIEW_*</code>), not stored here.</p>
        </section>

        <section class="card features-table-card" aria-labelledby="link-preview-boards-heading">
            <h2 id="link-preview-boards-heading">Per-board opt-in</h2>
            <?php if ($boards === []): ?>
                <p class="features-empty">No boards exist yet.</p>
            <?php else: ?>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Board link preview opt-in">
                    <table class="audit features-table link-preview-board-table">
                        <thead><tr><th scope="col">Board</th><th scope="col">State</th><th scope="col" class="features-col-actions"><span class="sr-only">Action</span></th></tr></thead>
                        <tbody>
                        <?php foreach ($boards as $board): ?>
                            <tr>
                                <td><strong><?= $e((string) $board['name']) ?></strong> <code>/c/<?= $e((string) $board['slug']) ?></code></td>
                                <td>
                                    <?php if (!empty($board['effective'])): ?>
                                        <span class="features-pill is-on">On</span>
                                    <?php elseif (!empty($board['enabled'])): ?>
                                        <?php // Opted in but not public: previews are never fetched for a
                                              // non-public board, so say inert rather than On. ?>
                                        <span class="features-pill is-off">Inert (<?= $e((string) $board['visibility']) ?> board)</span>
                                    <?php else: ?>
                                        <span class="features-pill is-off">Off</span>
                                    <?php endif; ?>
                                </td>
                                <td class="features-col-actions">
                                    <form method="post" action="/admin/link-previews/boards/<?= (int) $board['id'] ?>" class="inline-form">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="enabled" value="<?= !empty($board['enabled']) ? '0' : '1' ?>">
                                        <input type="hidden" name="return" value="/admin/link-previews">
                                        <button class="btn btn-small features-toggle-btn" type="submit" aria-label="<?= !empty($board['enabled']) ? 'Disable' : 'Enable' ?> link previews on <?= $e((string) $board['name']) ?>"><?= !empty($board['enabled']) ? 'Disable' : 'Enable' ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>

    <section class="card features-group-card link-preview-rows" aria-labelledby="link-preview-rows-heading">
        <h2 class="features-group-title" id="link-preview-rows-heading">Recent previews</h2>
        <?php // No-JS filter: a GET form with its own submit, never an onchange handler
              // (strict CSP forbids inline script, and the console must work unenhanced). ?>
        <form method="get" action="/admin/link-previews" class="inline link-preview-filter">
            <label class="field field-compact">
                <span>Status</span>
                <select name="status" class="input input-small">
                    <option value=""<?= $filter === '' ? ' selected' : '' ?>>All</option>
                    <?php foreach ($statusLabels as $key => $label): ?>
                        <option value="<?= $e((string) $key) ?>"<?= $filter === (string) $key ? ' selected' : '' ?>><?= $e((string) $label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-small" type="submit">Filter</button>
        </form>

        <?php if ($rows === []): ?>
            <p class="features-empty"><?= $filter === '' ? 'No link previews have been discovered yet.' : 'No previews with that status.' ?></p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Recent link previews">
                <table class="audit features-table link-preview-table">
                    <thead>
                        <tr>
                            <th scope="col">URL</th>
                            <th scope="col">Status</th>
                            <th scope="col">Source</th>
                            <th scope="col">Discovered</th>
                            <th scope="col" class="features-col-actions"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($rows as $row): ?>
                        <?php $status = (string) $row['status']; ?>
                        <tr>
                            <td class="link-preview-url">
                                <code><?= $e((string) $row['url']) ?></code>
                                <?php if ((string) $row['title'] !== ''): ?><span class="muted"><?= $e((string) $row['title']) ?></span><?php endif; ?>
                                <?php if ((string) $row['error'] !== ''): ?><span class="field-error"><?= $e((string) $row['error']) ?></span><?php endif; ?>
                            </td>
                            <td>
                                <span class="state <?= $e($statusClass[$status] ?? '') ?>"><?= $e($statusLabels[$status] ?? $status) ?></span>
                                <?php if ($row['http_status'] !== null): ?><span class="muted">HTTP <?= (int) $row['http_status'] ?></span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($row['thread_href'])): ?>
                                    <a href="<?= $e((string) $row['thread_href']) ?>#p<?= (int) $row['source_id'] ?>"><?= $e((string) $row['thread_title']) ?></a>
                                <?php elseif ((string) $row['thread_title'] !== ''): ?>
                                    <?= $e((string) $row['thread_title']) ?>
                                <?php else: ?>
                                    <span class="muted"><?= $e((string) $row['source_type']) ?> #<?= (int) $row['source_id'] ?></span>
                                <?php endif; ?>
                                <?php if ((string) $row['board_name'] !== ''): ?>
                                    <span class="muted"><?= $e((string) $row['board_name']) ?><?= empty($row['board_opted_in']) ? ' · opted out' : '' ?></span>
                                <?php endif; ?>
                            </td>
                            <td><time datetime="<?= $e(iso_datetime((string) $row['created_at'])) ?>"><?= $e(human_datetime((string) $row['created_at'])) ?></time></td>
                            <td class="features-col-actions">
                                <?php if (!empty($row['can_refresh'])): ?>
                                    <form method="post" action="/admin/link-previews/<?= (int) $row['id'] ?>/refresh" class="inline-form">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="return" value="/admin/link-previews">
                                        <button class="btn btn-small" type="submit" aria-label="Re-queue the preview for <?= $e((string) $row['url']) ?>">Refresh</button>
                                    </form>
                                <?php endif; ?>
                                <form method="post" action="/admin/link-previews/<?= (int) $row['id'] ?>/purge" class="inline-form">
                                    <?= $this->csrfField() ?>
                                    <input type="hidden" name="return" value="/admin/link-previews">
                                    <button class="btn btn-small danger" type="submit" aria-label="Purge the stored metadata for <?= $e((string) $row['url']) ?>">Purge</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        <p class="features-note">Purging clears the stored metadata; the URL is re-queued the next time its post is saved. An author-removed row stays removed &mdash; the console deliberately offers no refresh for it. Operator actions here are written to the audit log against the post they belong to.</p>
    </section>
<?= $this->partial('admin/_console_end') ?>
