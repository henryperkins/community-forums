<?php /** @var \App\Core\View $this */ ?>
<?php
$this->layout('layout');
$this->section('title', 'Drafts');
$this->section('robots', 'noindex,nofollow');
$serverOn = !empty($server_drafts_enabled);
$serverDrafts = $server_drafts ?? [];
$count = count($serverDrafts);
?>

<div class="settings-screen">
    <header class="settings-head">
        <span class="eyebrow">Account</span>
        <h1>Account settings</h1>
        <p>Everything this community knows about you, and everything it does on your behalf.</p>
    </header>
    <div class="settings">
        <?= $this->partial('partials/settings_nav', ['active' => 'drafts']) ?>

        <div class="settings-pane">
    <?php /* data-drafts-list / data-server-drafts / data-local-drafts-list are read by
             composer.js:972-977 to decide where the browser-local list renders. Renaming
             or dropping them silently disables that list.

             The hook sits on an inner wrapper, not on the <section>: with the flag off
             renderDraftsPage() resolves [data-drafts-list] and does host.innerHTML = ''
             (composer.js:993), which on the <section> would erase this pane's head. */ ?>
    <section class="scribe-panel is-flush">
        <div class="account-draft-head">
            <span class="account-draft-head-main">
                <h2 class="scribe-panel-head">Drafts</h2>
                <?php if ($serverOn): ?>
                    <span class="account-draft-sync">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6L9 17l-5-5"></path></svg>
                        Saved to your account · syncs across devices
                    </span>
                <?php endif; ?>
            </span>
            <?php if ($serverOn): ?>
                <span class="account-draft-count"><?= $count ?> saved</span>
            <?php endif; ?>
        </div>

        <div data-drafts-list<?= $serverOn ? ' data-server-drafts="1"' : '' ?>>
        <?php if ($serverOn): ?>
            <?php if ($count === 0): ?>
                <p class="account-draft-empty" data-drafts-empty>No drafts. Anything you start writing is kept here automatically.</p>
            <?php else: ?>
                <ul class="account-draft-list">
                    <?php foreach ($serverDrafts as $draft): ?>
                        <li class="account-draft-row">
                            <span class="account-draft-main">
                                <span class="account-draft-dest"><?= $e((string) $draft['context_key']) ?></span>
                                <span class="account-draft-title"><?= $e((string) ($draft['title'] ?? 'Untitled draft')) ?></span>
                                <p class="account-draft-snippet"><?= $e(mb_strimwidth((string) $draft['body'], 0, 240, '...')) ?></p>
                                <span class="account-draft-meta">
                                    saved <?= $e(human_datetime((string) $draft['updated_at'])) ?>
                                    <span class="account-draft-chip">r<?= (int) $draft['revision'] ?></span>
                                </span>
                            </span>
                            <span class="account-draft-actions">
                                <form method="post" action="/drafts/<?= (int) $draft['id'] ?>/discard" class="inline">
                                    <?= $this->csrfField() ?>
                                    <button class="linkbtn danger" type="submit">Discard</button>
                                </form>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="account-draft-section local-drafts" aria-labelledby="local-drafts-heading">
                <h3 class="account-subhead" id="local-drafts-heading">Saved in this browser</h3>
                <p class="account-note">Device-local drafts can include offline edits or work that has not synced yet.</p>
                <div data-local-drafts-list>
                    <p class="muted" data-drafts-empty>No browser-local drafts in this browser.</p>
                </div>
                <noscript><p class="muted">Browser-local drafts require JavaScript to list or discard.</p></noscript>
            </div>
        <?php else: ?>
            <p class="account-draft-empty" data-drafts-empty>Your saved drafts are stored in this browser. They will appear here when JavaScript is enabled.</p>
            <noscript><p class="account-draft-empty">Drafts are browser-local and require JavaScript to list or discard.</p></noscript>
        <?php endif; ?>
        </div>
    </section>
        </div>
    </div>
</div>
