<?php /** @var \\App\\Core\\View $this */ ?>
<?php $online = is_array($presence_roster ?? null) ? $presence_roster : []; ?>
<aside class="sidebar" id="sidebar-nav" data-sidebar>
    <div class="sidebar-heading">
        <span class="eyebrow">Boards</span>
        <button class="sidebar-close" type="button" data-nav-toggle aria-label="Close board rail"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
    </div>

    <nav aria-label="Boards">
        <?php if (empty($nav)): ?>
            <p class="muted sidebar-empty">No boards yet.</p>
        <?php else: ?>
            <?php foreach ($nav as $section): ?>
                <div class="nav-cat">
                    <span class="nav-cat-name"><?= $e($section['category']['name']) ?></span>
                    <ul class="nav-boards">
                        <?php foreach ($section['boards'] as $board): ?>
                            <?php $unread = (int) ($board['unread_count'] ?? 0); ?>
                            <li>
                                <a class="<?= $request_path === '/c/' . $board['slug'] ? 'active' : '' ?>" data-board-slug="<?= $e($board['slug']) ?>" href="/c/<?= $e($board['slug']) ?>"<?= $request_path === '/c/' . $board['slug'] ? ' aria-current="page"' : '' ?>>
                                    <span class="board-rail-name"><span class="hash">#</span><?= $e($board['name']) ?></span>
                                    <?php if ($unread > 0): ?><span class="board-unread-count" data-board-unread-count="<?= $unread ?>" aria-label="<?= $unread ?> unread topic<?= $unread === 1 ? '' : 's' ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                                    <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <?php if (!empty($features['presence'])): ?>
        <section class="presence-widget" data-presence aria-live="polite">
            <h2 class="presence-title"><a href="/users-online">Online</a> <span class="presence-count" data-presence-count><?= count($online) ?></span></h2>
            <ul class="presence-list" data-presence-list>
                <?php foreach (array_slice($online, 0, 6) as $member): ?>
                    <li><a href="/u/<?= $e($member['username']) ?>">
                        <span class="dot" aria-hidden="true"></span>
                        <span><?= $e($member['display_name']) ?></span>
                    </a></li>
                <?php endforeach; ?>
            </ul>
            <?php if ($online === []): ?><p class="presence-empty">No one is showing as online.</p><?php endif; ?>
            <a class="presence-all" href="/users-online">See everyone online</a>
        </section>
    <?php endif; ?>
</aside>
