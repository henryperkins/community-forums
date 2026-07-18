<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Admin');
$settingsErrors = $settings_errors ?? [];
$settingsOld = $settings_old ?? [];
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Operator desk</span>
            <h1>Admin console</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'dashboard', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
    <p class="pane-intro">Start with the queues and health flags that need operator action, then drop into settings and audit detail without leaving the console.</p>

    <section class="admin-dashboard-grid" aria-label="Operational summary">
        <?php foreach ($cards as $card): ?>
            <?php $tag = !empty($card['href']) ? 'a' : 'div'; ?>
            <<?= $tag ?> class="card queue-card<?= empty($card['href']) ? ' is-static' : '' ?>"<?= !empty($card['href']) ? ' href="' . $e($card['href']) . '"' : '' ?>>
                <span class="queue-card-head"><?= $e($card['title']) ?></span>
                <strong class="queue-card-count"><?= (int) $card['count'] ?></strong>
                <span class="queue-card-detail"><?= $e($card['detail']) ?></span>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </section>

    <section class="card">
        <h2>Needs attention</h2>
        <?php if (empty($attention)): ?>
            <p class="muted">No pending operator work right now.</p>
        <?php else: ?>
            <ul class="link-list attention-list">
                <?php foreach ($attention as $item): ?>
                    <li>
                        <?php if (!empty($item['href'])): ?>
                            <a href="<?= $e($item['href']) ?>"><?= $e($item['label']) ?></a>
                        <?php else: ?>
                            <span><?= $e($item['label']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Site name</h2>
        <form method="post" action="/admin/site" class="inline-form">
            <?= $this->csrfField() ?>
            <label class="sr-only" for="admin-site-name">Site name</label>
            <input type="text" id="admin-site-name" name="site_name" class="input" maxlength="80" value="<?= $e((string) ($settingsOld['site_name'] ?? $site_name)) ?>"<?= field_attrs($settingsErrors, 'site_name') ?> required>
            <button class="btn btn-small" type="submit">Update</button>
        </form>
        <?= field_error($settingsErrors, 'site_name') ?>
    </section>

    <section class="card">
        <h2>Trust &amp; safety</h2>
        <form method="post" action="/admin/settings" class="stacked">
            <?= $this->csrfField() ?>
            <label class="field">
                <span>Registration</span>
                <?php $regSelected = (string) ($settingsOld['registration_mode'] ?? ($registration_mode ?? 'open')); ?>
                <select name="registration_mode" class="input">
                    <?php $regModeNotes = ['open' => '', 'invite' => ' (invitation required)', 'closed' => ' (no new sign-ups)']; ?>
                    <?php foreach (($registration_modes ?? \App\Security\RegistrationPolicy::MODES) as $m): ?>
                        <option value="<?= $e($m) ?>"<?= $regSelected === $m ? ' selected' : '' ?>><?= $e(ucfirst($m) . ($regModeNotes[$m] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php if ($regSelected === 'invite' && empty($invitations_flag_on)): ?>
                    <span class="field-error">Registration mode is “invite” but the invitations feature is off — registration is effectively closed.</span>
                <?php endif; ?>
                <?php if (!empty($settingsErrors['registration_mode'])): ?>
                    <?= field_error($settingsErrors, 'registration_mode') ?>
                <?php endif; ?>
            </label>
            <label class="field">
                <span>Anti-abuse enforcement</span>
                <?php $aaSelected = (string) ($settingsOld['antiabuse_mode'] ?? ($antiabuse_mode ?? 'observe')); ?>
                <select name="antiabuse_mode" class="input">
                    <?php foreach (($antiabuse_modes ?? ['observe', 'flag', 'hold', 'block']) as $m): ?>
                        <option value="<?= $e($m) ?>"<?= $aaSelected === $m ? ' selected' : '' ?>><?= $e(ucfirst($m)) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="muted">observe = log only · flag · hold = queue for approval · block = reject</span>
                <?php if (!empty($settingsErrors['antiabuse_mode'])): ?>
                    <?= field_error($settingsErrors, 'antiabuse_mode') ?>
                <?php endif; ?>
            </label>
            <label class="field">
                <span>Blocked words</span>
                <?php $wordsValue = isset($settingsOld['antiabuse_blocked_words']) && is_string($settingsOld['antiabuse_blocked_words'])
                    ? $settingsOld['antiabuse_blocked_words']
                    : implode("\n", $antiabuse_blocked_words ?? []); ?>
                <textarea name="antiabuse_blocked_words" class="input" rows="4" placeholder="One word or phrase per line (commas also separate entries)"><?= $e($wordsValue) ?></textarea>
                <span class="muted">One per line, or comma-separated. Case-insensitive; matched as substrings against new posts. Entries shorter than 3 characters are ignored.</span>
                <?php if (!empty($settingsErrors['antiabuse_blocked_words'])): ?>
                    <?= field_error($settingsErrors, 'antiabuse_blocked_words') ?>
                <?php endif; ?>
            </label>
            <div class="form-actions"><button class="btn" type="submit">Save settings</button></div>
        </form>
    </section>

    <?php if (!empty($custom_emoji_on)): ?>
    <section class="card custom-emoji-panel" aria-labelledby="custom-emoji-heading">
        <h2 id="custom-emoji-heading">Custom emoji</h2>
        <?php $emojiErr = $emoji_errors ?? []; $emojiOld = $emoji_old ?? []; ?>
        <form method="post" action="/admin/custom-emoji" class="stacked">
            <?= $this->csrfField() ?>
            <div class="form-grid">
                <label class="field">
                    <span>Shortcode</span>
                    <input type="text" name="shortcode" class="input" maxlength="40" placeholder="party" pattern="[A-Za-z0-9_+\-]{2,40}" value="<?= $e((string) ($emojiOld['shortcode'] ?? '')) ?>"<?= field_attrs($emojiErr, 'shortcode', 'err-emoji-shortcode') ?> required>
                </label>
                <label class="field">
                    <span>Name</span>
                    <input type="text" name="name" class="input" maxlength="80" placeholder="Party" value="<?= $e((string) ($emojiOld['name'] ?? '')) ?>"<?= field_attrs($emojiErr, 'name', 'err-emoji-name') ?> required>
                </label>
                <label class="field">
                    <span>Asset path</span>
                    <input type="text" name="image_path" class="input" placeholder="/emoji/party.webp" value="<?= $e((string) ($emojiOld['image_path'] ?? '')) ?>"<?= field_attrs($emojiErr, 'image_path', 'err-emoji-image_path') ?> required>
                </label>
                <label class="field">
                    <span>MIME type</span>
                    <select name="mime" class="input"<?= field_attrs($emojiErr, 'mime', 'err-emoji-mime') ?> required>
                        <option value="image/webp"<?= ($emojiOld['mime'] ?? '') === 'image/webp' ? ' selected' : '' ?>>image/webp</option>
                        <option value="image/png"<?= ($emojiOld['mime'] ?? '') === 'image/png' ? ' selected' : '' ?>>image/png</option>
                    </select>
                </label>
            </div>
            <?php foreach (['shortcode', 'name', 'image_path', 'mime'] as $emojiField): ?>
                <?= field_error($emojiErr, $emojiField, 'err-emoji-' . $emojiField) ?>
            <?php endforeach; ?>
            <label class="checkline">
                <input type="checkbox" name="allow_reactions" value="1"<?= !empty($emojiOld['allow_reactions']) ? ' checked' : '' ?>>
                <span>Allow as a reaction</span>
            </label>
            <div class="form-actions"><button class="btn" type="submit">Save emoji</button></div>
        </form>

        <?php if (empty($custom_emoji)): ?>
            <p class="muted">No custom emoji have been added yet.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Custom emoji catalogue">
                <table class="audit">
                    <thead><tr><th scope="col">Emoji</th><th scope="col">Name</th><th scope="col">Asset</th><th scope="col">Reactions</th><th scope="col">Status</th><th scope="col">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($custom_emoji as $emoji): ?>
                        <?php $shortcode = (string) $emoji['shortcode']; ?>
                        <tr>
                            <td><img src="<?= $e($emoji['image_path']) ?>" alt=":<?= $e($shortcode) ?>:" width="24" height="24"> <code>:<?= $e($shortcode) ?>:</code></td>
                            <td><?= $e($emoji['name']) ?></td>
                            <td><code><?= $e($emoji['image_path']) ?></code></td>
                            <td><?= !empty($emoji['allow_reactions']) ? 'Allowed' : 'Post rendering only' ?></td>
                            <td><?= !empty($emoji['is_enabled']) ? 'Enabled' : 'Disabled' ?></td>
                            <td>
                                <?php if (!empty($emoji['is_enabled'])): ?>
                                    <form method="post" action="/admin/custom-emoji/<?= rawurlencode($shortcode) ?>/disable" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="btn btn-small" type="submit">Disable</button>
                                    </form>
                                <?php else: ?>
                                    <form method="post" action="/admin/custom-emoji/<?= rawurlencode($shortcode) ?>/enable" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="btn btn-small" type="submit">Enable</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <section class="card" id="recent-activity">
        <h2>Recent activity</h2>
        <?php if (empty($audit)): ?>
            <p class="muted">No moderation or admin actions yet.</p>
        <?php else: ?>
            <div class="table-scroll" tabindex="0" role="region" aria-label="Recent activity">
            <table class="audit">
                <thead><tr><th scope="col">When</th><th scope="col">Actor</th><th scope="col">Action</th><th scope="col">Target</th><th scope="col">Reason</th></tr></thead>
                <tbody>
                <?php foreach ($audit as $row): ?>
                    <tr>
                        <td class="nowrap"><?= $e(human_datetime($row['created_at'])) ?></td>
                        <td><?= $e($row['actor_username'] ?? 'system') ?></td>
                        <td class="action-cell"><code><?= $e($row['action']) ?></code></td>
                        <td><?= $e($row['target_type']) ?> #<?= (int) $row['target_id'] ?></td>
                        <td><?= $e($row['reason'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <p class="muted"><a href="/admin/audit">Search the full audit log</a></p>
        <?php endif; ?>
    </section>
    </div>
</div>
