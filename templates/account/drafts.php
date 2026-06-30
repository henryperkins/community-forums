<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Drafts'); $this->section('robots', 'noindex,nofollow'); ?>

<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Drafts</h1>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav') ?>

        <div class="settings-pane">
    <section class="card" data-drafts-list<?= !empty($server_drafts_enabled) ? ' data-server-drafts="1"' : '' ?>>
        <?php if (!empty($server_drafts_enabled)): ?>
            <?php if (empty($server_drafts)): ?>
                <p class="muted" data-drafts-empty>No server drafts yet.</p>
            <?php else: ?>
                <ul class="report-list">
                    <?php foreach ($server_drafts as $draft): ?>
                        <li class="report-row">
                            <div class="report-head">
                                <span class="badge">r<?= (int) $draft['revision'] ?></span>
                                <span class="muted"><?= $e((string) $draft['context_key']) ?> · <?= $e(human_datetime((string) $draft['updated_at'])) ?></span>
                            </div>
                            <h2><?= $e((string) ($draft['title'] ?? 'Untitled draft')) ?></h2>
                            <blockquote class="report-excerpt"><?= $e(mb_strimwidth((string) $draft['body'], 0, 240, '...')) ?></blockquote>
                            <form method="post" action="/drafts/<?= (int) $draft['id'] ?>/discard" class="inline-form">
                                <?= $this->csrfField() ?>
                                <button class="btn btn-small danger" type="submit">Discard</button>
                            </form>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <p class="muted">Browser-local drafts remain available offline in this browser.</p>
        <?php else: ?>
            <p class="muted" data-drafts-empty>Your saved drafts are stored in this browser. They will appear here when JavaScript is enabled.</p>
            <noscript><p class="muted">Drafts are browser-local and require JavaScript to list or discard.</p></noscript>
        <?php endif; ?>
    </section>
        </div>
    </div>
</div>
