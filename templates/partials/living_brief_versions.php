<?php /** @var \App\Core\View $this */ ?>
<?php
// The one version-row component, rendered on two mutually exclusive surfaces:
// the brief's More disclosure (partials/thread_memory_tools.php, headed "Version
// history") and the curator empty state, where nothing is published and the rows
// ARE the panel's primary action (partials/living_brief_empty.php, headed
// "Restore a version", first row promoted to the filled `.btn`). They differ only
// in that heading and that promotion; the row itself — the four meta cells, the
// CSRF field, the `summary_id`, the per-row accessible name — is one definition,
// so a change to the form cannot reach one surface and miss the other.
//
// Every summary row arrives here, including the one currently published: the view
// service applies no status or version filter, and a re-restore of the live
// version stays reachable. Neither heading may therefore promise "earlier".
//
// The heading stays a <p>. The empty state carries no heading of its own (it
// names its region with aria-label), so an <h3> here would hang off the topic
// <h1> and skip a level on exactly the surface that has no <h2> to hang from.
$threadId = (int) $thread_id;
$rows = is_array($history ?? null) ? $history : [];
$promoteFirst = !empty($promote_first);
$historyLabels = ['draft' => 'Draft', 'published' => 'Published', 'retired' => 'Retired'];
?>
<p class="lb-more-title"><?= $e($title) ?></p>
<ul class="lb-versions">
    <?php foreach ($rows as $index => $item): ?>
        <li class="lb-version">
            <span class="lb-version-v">v<?= (int) $item['version'] ?></span>
            <span class="lb-version-who"><?= $e($item['label']) ?></span>
            <span class="lb-version-status"><?= $e($historyLabels[$item['status']] ?? ucfirst((string) $item['status'])) ?></span>
            <?php if (!empty($item['published_at'])): ?>
                <time class="lb-version-when" datetime="<?= $e(iso_datetime($item['published_at'])) ?>"><?= $e(human_datetime($item['published_at'])) ?></time>
            <?php endif; ?>
            <form class="inline" method="post" action="/t/<?= $threadId ?>/summary/restore">
                <?= $this->csrfField() ?>
                <input type="hidden" name="summary_id" value="<?= (int) $item['id'] ?>">
                <button class="<?= $promoteFirst && $index === 0 ? 'btn' : 'linkbtn' ?>" type="submit">Restore<span class="sr-only"> version <?= (int) $item['version'] ?></span></button>
            </form>
        </li>
    <?php endforeach; ?>
</ul>
