<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding revision ' . (int) $revision['id']);
$this->section('robots', 'noindex, nofollow');
$this->section('variant', 'admin');
$this->section('recovery_surface', '1');
$revisionId = (int) $revision['id'];
$isLive = $revisionId === (int) $current_revision && $differences === [];
$actionLabels = [
    'baseline' => 'the configuration before history began',
    'publish' => 'a published change',
    'restore' => 'a restore',
    'reset' => 'a reset',
];
$labels = \App\Support\BrandingDocument::LABELS;
$groups = \App\Support\BrandingDocument::KEYS;
// Logos show as thumbnails and colours as swatches. A swatch is an SVG fill,
// not a style attribute, so the strict style-src policy still holds.
$cell = static function (array $document, string $key) use ($e, $groups): string {
    $value = $document[$key] ?? '';
    $text = $e(\App\Support\BrandingDocument::describe($document, $key));
    if (($groups[$key] ?? '') === 'assets') {
        return is_string($value) && $value !== ''
            ? '<img class="branding-revision-thumb" src="' . $e($value) . '" alt=""><span>' . $e($value) . '</span>'
            : '<span>' . $text . '</span>';
    }
    if (($groups[$key] ?? '') === 'colours' && is_string($value) && preg_match('/^#[0-9a-f]{6}$/D', $value) === 1) {
        return '<svg class="branding-revision-swatch" viewBox="0 0 12 12" aria-hidden="true"><rect width="12" height="12" rx="2" fill="' . $e($value) . '"/></svg><span>' . $text . '</span>';
    }
    return '<span>' . $text . '</span>';
};
$errors = $errors ?? [];
?>
<?= $this->partial('admin/_console', [
    'area' => 'appearance',
    'tab' => 'branding_history',
    'pane_class' => 'admin-appearance admin-appearance-revision',
]) ?>
        <p class="pane-intro">
            <a href="/admin/branding/history">Branding history</a> · Revision #<?= $revisionId ?> is <?= $e($actionLabels[(string) $revision['action']] ?? (string) $revision['action']) ?>,
            recorded <?= $e(human_datetime((string) $revision['created_at'])) ?><?= $revision['actor_username'] !== null ? ' by @' . $e((string) $revision['actor_username']) : '' ?>.
        </p>

        <?php if (is_array($conflict ?? null)): ?>
            <section class="callout callout-review branding-conflict" aria-labelledby="branding-conflict-title">
                <h3 id="branding-conflict-title">Branding changed after this review opened</h3>
                <p>Nothing was restored. The comparison below now reflects the live branding. These changes were published in the meantime:</p>
                <ul>
                    <?php foreach ($conflict['newer'] as $newer): ?>
                        <li><a href="/admin/branding/history/<?= (int) $newer['id'] ?>">Revision <?= (int) $newer['id'] ?></a>, <?= $e(human_datetime((string) $newer['when'])) ?><?= $newer['actor'] !== '' ? ' by @' . $e($newer['actor']) : '' ?>: <?= $e($newer['changed'] !== [] ? implode(', ', $newer['changed']) : ucfirst((string) $newer['action'])) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <?php if ($missing !== []): ?>
            <p class="callout callout-review" role="status">
                <?= $e(implode(', ', array_map(static fn (string $key): string => $labels[$key] ?? $key, $missing))) ?>
                <?= count($missing) === 1 ? 'is' : 'are' ?> no longer available, so restoring uses the built-in fallback for <?= count($missing) === 1 ? 'it' : 'them' ?>.
            </p>
        <?php endif; ?>

        <section class="card branding-revision-card" aria-labelledby="branding-revision-diff">
            <h2 id="branding-revision-diff" class="branding-section-label">What restoring changes</h2>
            <?php if ($differences === []): ?>
                <p class="brand-preview-note">This revision matches the live branding. Restoring it would change nothing.</p>
            <?php else: ?>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Differences from the live branding">
                    <table class="audit branding-revision-table">
                        <thead>
                            <tr>
                                <th scope="col">Setting</th>
                                <th scope="col">Live now</th>
                                <th scope="col">Revision #<?= $revisionId ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($differences as $key): ?>
                                <tr>
                                    <th scope="row"><?= $e($labels[$key] ?? $key) ?></th>
                                    <td><span class="branding-revision-value"><?= $cell($published, $key) ?></span></td>
                                    <td><span class="branding-revision-value"><?= $cell($candidate, $key) ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <form method="post" action="/admin/branding/history/<?= $revisionId ?>/restore" class="card branding-restore-card">
            <?= $this->csrfField() ?>
            <input type="hidden" name="base_revision" value="<?= (int) $current_revision ?>">
            <h2 class="branding-section-label">Restore</h2>
            <p class="brand-preview-note">Restoring replaces every branding setting with this revision’s — community name and text, logos and favicon, colours, visitor mode and preset — and records it as a new revision. Package themes, theme safe mode, feature settings and members’ own appearance choices stay as they are.</p>
            <?php if (!empty($contrast_warnings)): ?>
                <section class="callout callout-review branding-contrast-warning" aria-labelledby="branding-restore-contrast-title">
                    <input type="hidden" name="contrast_review" value="<?= $e($contrast_review) ?>">
                    <h3 id="branding-restore-contrast-title">Review colour contrast</h3>
                    <p>With the active theme, this revision’s colours can make parts of the community difficult to read.</p>
                    <ul>
                        <?php foreach ($contrast_warnings as $warning): ?>
                            <li><?= $e($warning) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <label class="branding-checkline" for="branding-restore-contrast-ack">
                        <input id="branding-restore-contrast-ack" type="checkbox" name="contrast_ack" value="1"<?= field_attrs($errors, 'contrast_ack') ?>>
                        <span>I understand these readability risks and want to restore these colours. This acknowledgement will be recorded in the audit log.</span>
                    </label>
                    <?= field_error($errors, 'contrast_ack') ?>
                </section>
            <?php endif; ?>
            <div class="branding-save-row">
                <button class="btn" type="submit"<?= $differences === [] ? ' disabled' : '' ?>>Restore revision #<?= $revisionId ?></button>
                <a class="btn btn-secondary" href="/admin/branding/history">Back to history</a>
            </div>
        </form>
<?= $this->partial('admin/_console_end') ?>
