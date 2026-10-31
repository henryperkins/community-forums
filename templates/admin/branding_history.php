<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Branding history');
$this->section('robots', 'noindex, nofollow');
$this->section('variant', 'admin');
$this->section('recovery_surface', '1');
$actionLabels = [
    'baseline' => 'Configuration before history began',
    'publish' => 'Published',
    'restore' => 'Restored',
    'reset' => 'Reset',
];
$changedSummary = static function (array $keys): string {
    $labels = array_map(static fn (string $key): string => \App\Support\BrandingDocument::LABELS[$key] ?? $key, $keys);
    if (count($labels) > 4) {
        return implode(', ', array_slice($labels, 0, 4)) . ' and ' . (count($labels) - 4) . ' more';
    }
    return implode(', ', $labels);
};
?>
<?= $this->partial('admin/_console', [
    'area' => 'appearance',
    'tab' => 'branding_history',
    'pane_class' => 'admin-appearance admin-appearance-history',
]) ?>
        <p class="pane-intro">Every published branding change, newest first, with its logos. Review a revision to compare it with the live branding and restore it. A restore publishes that configuration as a new revision, so it can be undone the same way. Package themes, theme safe mode, feature settings and members’ own appearance choices are not part of branding history.</p>

        <?php if ($revisions === []): ?>
            <?= $this->partial('partials/empty_state', [
                'heading' => 'No branding changes yet',
                'message' => 'The first change you publish is recorded here, together with the configuration it replaced.',
                'action_href' => '/admin/branding',
                'action_label' => 'Open branding',
            ]) ?>
        <?php else: ?>
            <section class="card branding-history-card">
                <div class="table-scroll" tabindex="0" role="region" aria-label="Branding revisions">
                    <table class="audit branding-history-table">
                        <thead>
                            <tr>
                                <th scope="col">Revision</th>
                                <th scope="col">When</th>
                                <th scope="col">By</th>
                                <th scope="col">Change</th>
                                <th scope="col"><span class="sr-only">Review</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($revisions as $revision): ?>
                                <?php $isCurrent = (int) $revision['id'] === (int) $current_revision; ?>
                                <tr<?= $isCurrent ? ' aria-current="true"' : '' ?>>
                                    <th scope="row">
                                        #<?= (int) $revision['id'] ?>
                                        <?php if ($isCurrent): ?><span class="state state-active">Live</span><?php endif; ?>
                                    </th>
                                    <td><time datetime="<?= $e(gmdate('Y-m-d\TH:i:s\Z', (int) strtotime((string) $revision['created_at'] . ' UTC'))) ?>"><?= $e(human_datetime((string) $revision['created_at'])) ?></time></td>
                                    <td><?= $revision['actor_username'] !== null ? '@' . $e((string) $revision['actor_username']) : ($revision['action'] === 'baseline' ? '—' : 'Deleted account') ?></td>
                                    <td>
                                        <span class="branding-history-action"><?= $e($actionLabels[(string) $revision['action']] ?? (string) $revision['action']) ?><?php if ($revision['restored_from_id'] !== null): ?> revision #<?= (int) $revision['restored_from_id'] ?><?php endif; ?></span>
                                        <?php if ($revision['changed_keys'] !== []): ?>
                                            <span class="branding-history-changed"><?= $e($changedSummary($revision['changed_keys'])) ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><a href="/admin/branding/history/<?= (int) $revision['id'] ?>" aria-label="Review revision <?= (int) $revision['id'] ?>">Review</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
            <?php if ($pages > 1): ?>
                <?= $this->partial('partials/pager', [
                    'page' => $page,
                    'total_pages' => $pages,
                    'path' => '/admin/branding/history',
                    'aria_label' => 'Branding history pages',
                    'noun' => 'history',
                ]) ?>
            <?php endif; ?>
        <?php endif; ?>
<?= $this->partial('admin/_console_end') ?>
