<?php /** @var \App\Core\View $this */ ?>
<?php
$dmRoutePrefix = '/messages/';
$isDmRoute = $request_path === '/messages'
    || strncmp((string) $request_path, $dmRoutePrefix, strlen($dmRoutePrefix)) === 0;
?>
<aside class="sidebar" id="sidebar-nav" data-sidebar>
    <a class="sidebar-home<?= $request_path === '/' ? ' active' : '' ?>" href="/">
        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>
        <span>Home</span>
    </a>
    <?php if ($current_user !== null): ?>
        <nav class="rail-filters-nav" aria-label="Quick filters">
            <ul class="rail-filters">
                <?php if (!empty($features['search'])): ?>
                    <li><a class="rail-filter mobile-only mobile-search-link" href="/search">
                        <?= $this->partial('partials/icon', ['name' => 'search', 'class' => 'rail-ic']) ?>
                        <span>Search</span></a></li>
                <?php endif; ?>
                <?php if (!empty($features['engagement'])): ?>
                    <li><a class="rail-filter<?= $request_path === '/inbox' ? ' active' : '' ?>" href="/inbox">
                        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/></svg>
                        <span>Inbox</span></a></li>
                <?php endif; ?>
                <?php if (!empty($features['dms'])): ?>
                    <li><a class="rail-filter<?= $isDmRoute ? ' active' : '' ?>" href="/messages">
                        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                        <span>Messages</span></a></li>
                <?php endif; ?>
                <?php if (!empty($features['drafts'])): ?>
                    <li><a class="rail-filter<?= $request_path === '/drafts' ? ' active' : '' ?>" href="/drafts">
                        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>
                        <span>Drafts</span></a></li>
                <?php endif; ?>
                <?php if (!empty($features['community'])): ?>
                    <li><a class="rail-filter<?= $request_path === '/feed' ? ' active' : '' ?>" href="/feed">
                        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        <span>Following</span></a></li>
                    <li><a class="rail-filter<?= $request_path === '/leaderboard' ? ' active' : '' ?>" href="/leaderboard">
                        <svg class="rail-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 21h8"/><path d="M12 17v4"/><path d="M7 4h10v4a5 5 0 0 1-10 0z"/><path d="M5 4H3v2a3 3 0 0 0 3 3"/><path d="M19 4h2v2a3 3 0 0 1-3 3"/></svg>
                        <span>Top contributors</span></a></li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
    <nav aria-label="Boards">
        <?php if (empty($nav)): ?>
            <p class="muted sidebar-empty">No boards yet.</p>
        <?php else: ?>
            <?php foreach ($nav as $section): ?>
                <div class="nav-cat">
                    <span class="nav-cat-name"><?= $e($section['category']['name']) ?></span>
                    <ul class="nav-boards">
                        <?php foreach ($section['boards'] as $b): ?>
                            <li>
                                <a class="<?= $request_path === '/c/' . $b['slug'] ? 'active' : '' ?>" href="/c/<?= $e($b['slug']) ?>">
                                    <span class="hash">#</span><?= $e($b['name']) ?>
                                    <?php if ($b['visibility'] !== 'public'): ?><span class="tag"><?= $e($b['visibility']) ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>
    <?php if (!empty($features['presence']) && $current_user !== null): ?>
        <section class="presence-widget" data-presence hidden aria-live="polite">
            <h2 class="presence-title">Online <span class="presence-count" data-presence-count>0</span></h2>
            <ul class="presence-list" data-presence-list></ul>
        </section>
    <?php endif; ?>
</aside>
