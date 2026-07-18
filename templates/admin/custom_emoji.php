<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Custom emoji');
$errors = $emoji_errors ?? [];
$old = $emoji_old ?? [];
$mime = (string) ($old['mime'] ?? 'image/webp');
?>
<div class="admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Appearance</span>
            <h1>Custom emoji</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'custom_emoji', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <p class="pane-intro">Add approved static assets to the post renderer and optionally make them available as reactions.</p>

        <div class="custom-emoji-panel">
        <section class="card" aria-labelledby="custom-emoji-create-heading">
            <h2 id="custom-emoji-create-heading">Add or replace emoji</h2>
            <form method="post" action="/admin/custom-emoji" class="stacked">
                <?= $this->csrfField() ?>
                <div class="form-grid">
                    <label class="field">
                        <span>Shortcode</span>
                        <input type="text" name="shortcode" class="input" maxlength="40" placeholder="party" pattern="[A-Za-z0-9_+\-]{2,40}" value="<?= $e((string) ($old['shortcode'] ?? '')) ?>" required>
                        <?php if (!empty($errors['shortcode'])): ?><span class="field-error"><?= $e($errors['shortcode']) ?></span><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Name</span>
                        <input type="text" name="name" class="input" maxlength="80" placeholder="Party" value="<?= $e((string) ($old['name'] ?? '')) ?>" required>
                        <?php if (!empty($errors['name'])): ?><span class="field-error"><?= $e($errors['name']) ?></span><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>Asset path</span>
                        <input type="text" name="image_path" class="input" placeholder="/emoji/party.webp" value="<?= $e((string) ($old['image_path'] ?? '')) ?>" required>
                        <?php if (!empty($errors['image_path'])): ?><span class="field-error"><?= $e($errors['image_path']) ?></span><?php endif; ?>
                    </label>
                    <label class="field">
                        <span>MIME type</span>
                        <select name="mime" class="input" required>
                            <?php if (!in_array($mime, ['image/webp', 'image/png'], true)): ?>
                                <option value="<?= $e($mime) ?>" selected><?= $e($mime) ?></option>
                            <?php endif; ?>
                            <option value="image/webp"<?= $mime === 'image/webp' ? ' selected' : '' ?>>image/webp</option>
                            <option value="image/png"<?= $mime === 'image/png' ? ' selected' : '' ?>>image/png</option>
                        </select>
                        <?php if (!empty($errors['mime'])): ?><span class="field-error"><?= $e($errors['mime']) ?></span><?php endif; ?>
                    </label>
                </div>
                <label class="checkline">
                    <input type="checkbox" name="allow_reactions" value="1"<?= !empty($old['allow_reactions']) ? ' checked' : '' ?>>
                    <span>Allow as a reaction</span>
                </label>
                <div class="form-actions"><button class="btn" type="submit">Save emoji</button></div>
            </form>
        </section>

        <section class="card" aria-labelledby="custom-emoji-catalogue-heading">
            <h2 id="custom-emoji-catalogue-heading">Catalogue</h2>
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
                                    <form method="post" action="/admin/custom-emoji/<?= rawurlencode($shortcode) ?>/<?= !empty($emoji['is_enabled']) ? 'disable' : 'enable' ?>" class="inline">
                                        <?= $this->csrfField() ?>
                                        <button class="btn btn-small" type="submit"><?= !empty($emoji['is_enabled']) ? 'Disable' : 'Enable' ?></button>
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
    </div>
</div>
