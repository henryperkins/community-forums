<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Drafts'); $this->section('robots', 'noindex,nofollow'); ?>

<div class="settings">
    <h1>Drafts</h1>
    <?= $this->partial('partials/settings_nav') ?>

    <section class="card" data-drafts-list>
        <p class="muted" data-drafts-empty>Your saved drafts are stored in this browser. They will appear here when JavaScript is enabled.</p>
        <noscript><p class="muted">Drafts are browser-local and require JavaScript to list or discard.</p></noscript>
    </section>
</div>
