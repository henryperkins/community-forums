<?php /** @var \App\Core\View $this */ ?>
<section class="living-brief study-living-brief" data-living-brief aria-labelledby="living-brief-heading">
    <div class="living-brief-head">
        <div>
            <p class="living-brief-label">
                <?php if (!empty($living_brief['has_ai_lineage'])): ?>
                    <a href="/privacy#thread-intelligence"><?= $e($living_brief['label']) ?></a>
                <?php else: ?>
                    <?= $e($living_brief['label']) ?>
                <?php endif; ?>
            </p>
            <h2 id="living-brief-heading">Where the discussion stands</h2>
        </div>
        <p class="living-brief-meta">
            <span><?= $e($living_brief['metadata']) ?></span>
            <span>Version <?= (int) $living_brief['version'] ?></span>
            <time datetime="<?= $e($living_brief['published_at_utc']) ?>"><?= $e($living_brief['published_at']) ?></time>
        </p>
        <?php if (!empty($can_curate_memory)): ?><button type="button" class="living-brief-curate" data-topic-tools-open="memory" hidden>Curate</button><?php endif; ?>
    </div>
    <div class="post-body"><?= $living_brief['body_html'] ?></div>

    <?php if (!empty($living_brief_sources)): ?>
        <div class="living-brief-sources">
            <h3>Sources</h3>
            <ul>
                <?php foreach ($living_brief_sources as $source): ?>
                    <li>
                        <a href="<?= $e($source['url']) ?>">Post #<?= (int) $source['id'] ?></a>
                        <span class="muted"><?= ($source['author_username'] ?? null) !== null ? 'by @' . $e($source['author_username']) : 'by Anonymous' ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($living_brief['reference_cards'])): ?>
        <div class="reference-cards" aria-label="Referenced content">
            <?php foreach ($living_brief['reference_cards'] as $card): ?>
                <a class="reference-card" href="<?= $e($card['url']) ?>">
                    <span class="badge badge-muted"><?= $e($card['type']) ?></span>
                    <strong><?= $e($card['title']) ?></strong>
                    <?php if (($card['meta'] ?? '') !== ''): ?><span class="muted"><?= $e($card['meta']) ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($living_brief_related)): ?>
        <div class="living-brief-related" aria-label="Related topics">
            <?php foreach ($living_brief_related as $related): ?>
                <a class="living-brief-related-card" href="<?= $e($related['url']) ?>">
                    <strong><?= $e($related['title']) ?></strong>
                    <span><?= $e($related['reason']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
