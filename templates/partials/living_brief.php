<?php /** @var \App\Core\View $this */ ?>
<section class="living-brief study-living-brief" data-living-brief aria-label="Living brief">
    <?php // The topic title leads the region visually, so this heading is never seen.
          // It stays because the outline needs it: the region sits between the topic
          // <h1> and the poll's own <h2> in the stream, and a landmark with an
          // aria-label but no heading is absent from a screen reader's outline of the
          // page. The browser suite cannot see a level skip here either — axe tags
          // `heading-order` `best-practice`, which the spec's wcag2a/2aa/21a/21aa
          // filter excludes, and scores it `moderate`, below its serious/critical
          // threshold — so ThreadIntelligenceSurfaceTest pins the outline instead.
          // It is deliberately outside the label row, which stays headingless. ?>
    <h2 class="sr-only">Living brief</h2>
    <p class="living-brief-label">
        <?php // The commend star, as the design marks this region
              // (ThreadView.dc.html:511) — the same four-point mark that carries
              // regard and the accepted answer, never a second glyph. ?>
        <span class="living-brief-mark" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'commend-star']) ?></span>
        <?php if (!empty($living_brief['has_ai_lineage'])): ?>
            <a href="/privacy#thread-intelligence"><?= $e($living_brief['label']) ?></a>
        <?php else: ?>
            <?= $e($living_brief['label']) ?>
        <?php endif; ?>
    </p>
    <?php if (!empty($memory_automation_paused)): ?>
        <p class="living-brief-status is-paused">
            <span class="living-brief-status-icon" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'pause']) ?></span>
            <span>Automatic refresh is paused for this topic. The brief stands as published.</span>
        </p>
    <?php endif; ?>
    <div class="post-body formatted-content"><?= $living_brief['body_html'] ?></div>

    <?php /* The summary always shows; its provenance is one disclosure away
             (ThreadView.dc.html:520-543). Version, publication stamp and the posts
             the brief was drawn from used to print unconditionally — a metadata
             line above the summary and an <h3>Sources</h3> list below it — so the
             three-sentence artifact a reader came for arrived wrapped in six lines
             of bookkeeping. Nothing is lost: everything the head and the list held
             is inside, in the same order, and it opens with one click and no
             JavaScript. */ ?>
    <details class="living-brief-provenance">
        <summary><span class="living-brief-provenance-open">Where this came from</span><span class="living-brief-provenance-close">Hide where this came from</span></summary>
        <div class="living-brief-provenance-body">
            <?php if (!empty($living_brief_sources)): ?>
                <p class="living-brief-eyebrow">Drawn from</p>
                <p class="living-brief-sources">
                    <?php foreach ($living_brief_sources as $source): ?>
                        <span class="living-brief-source">
                            <a href="<?= $e($source['url']) ?>">Post #<?= (int) $source['id'] ?></a>
                            <span class="muted"><?= ($source['author_username'] ?? null) !== null ? 'by @' . $e($source['author_username']) : 'by an anonymous member' ?></span>
                        </span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
            <?php // One line, in the register the rest of the surface stamps its times
                  // in: "Updated automatically · Version 3 · Aug 27 at 23:00". The raw
                  // "2026-08-27 23:00:57 UTC" was the only machine stamp on a reading
                  // page; the full value stays on the <time> element. ?>
            <p class="living-brief-meta"><?= $e($living_brief['metadata']) ?> · Version <?= (int) $living_brief['version'] ?> · <time datetime="<?= $e($living_brief['published_at_utc']) ?>" title="<?= $e($living_brief['published_at']) ?>"><?= $e(post_datetime($living_brief['published_at_raw'] ?? null) !== '' ? post_datetime($living_brief['published_at_raw']) : $living_brief['published_at']) ?></time></p>
        </div>
    </details>

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

    <?php if (!empty($can_curate_memory)): ?>
        <?= $this->partial('partials/thread_memory_tools', [
            'thread' => $thread,
            'living_brief' => $living_brief,
            'memory_history' => $memory_history ?? [],
            'memory_refresh' => $memory_refresh ?? [],
            'memory_automation_paused' => !empty($memory_automation_paused),
            'can_curate_memory' => true,
        ]) ?>
    <?php endif; ?>
</section>
