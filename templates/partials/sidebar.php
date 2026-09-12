<?php /** @var \\App\\Core\\View $this */ ?>
<?php
// presence_snapshot is a CLOSURE, not an array: the roster is built only for the
// templates that actually render it, never for a JSON endpoint or a plain page.
$presence = is_callable($presence_snapshot ?? null) ? ($presence_snapshot)() : [];
$presenceMembers = is_array($presence['members'] ?? null) ? $presence['members'] : [];
$presenceHere = (int) ($presence['here'] ?? 0);
$presenceTotal = (int) ($presence['total'] ?? 0);
$presenceCapped = !empty($presence['capped']);
$presenceLimit = max(1, (int) ($presence['rail_limit'] ?? 5));
$presenceShown = array_slice($presenceMembers, 0, $presenceLimit);
$presenceMore = max(0, $presenceTotal - count($presenceShown));
?>
<?php
$composeMode = is_array($compose_boards ?? null);
$composeBoardMap = [];
if ($composeMode) {
    foreach ($compose_boards as $composeBoard) {
        $composeBoardMap[(int) $composeBoard['id']] = $composeBoard;
    }
}
$composeSelectedId = (int) ($selected_board ?? 0);
?>
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
                            <?php $composeBoard = $composeBoardMap[(int) $board['id']] ?? null; ?>
                            <li>
                                <?php if ($composeMode && $composeBoard !== null): ?>
                                    <?php if (!empty($composeBoard['can_post'])): ?>
                                        <form class="compose-board-picker-form" method="get" action="/compose">
                                            <button class="compose-board-picker<?= (int) $board['id'] === $composeSelectedId ? ' is-active' : '' ?>" type="submit" name="board" value="<?= $e($board['slug']) ?>" data-board-slug="<?= $e($board['slug']) ?>" data-compose-board-picker="<?= $e($board['slug']) ?>" aria-pressed="<?= (int) $board['id'] === $composeSelectedId ? 'true' : 'false' ?>">
                                                <span class="board-rail-name"><span class="hash">#</span><?= $e($board['name']) ?></span>
                                                <?php if ($unread > 0): ?><span class="board-unread-count" data-board-unread-count="<?= $unread ?>" aria-label="<?= $unread ?> unread topic<?= $unread === 1 ? '' : 's' ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                                                <?php if ((int) $board['id'] === $composeSelectedId): ?><span class="compose-posting-here">posting here</span><?php endif; ?>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="compose-board-disabled" data-board-slug="<?= $e($board['slug']) ?>" data-compose-board-disabled="<?= $e($board['slug']) ?>" title="<?= $e($composeBoard['post_block_reason'] ?? 'You cannot open a topic here.') ?>">
                                            <span class="board-rail-name"><span class="hash">#</span><?= $e($board['name']) ?></span>
                                            <?php if ($unread > 0): ?><span class="board-unread-count" data-board-unread-count="<?= $unread ?>" aria-label="<?= $unread ?> unread topic<?= $unread === 1 ? '' : 's' ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                                            <?= $this->partial('partials/icon', ['name' => 'lock']) ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <a class="<?= $request_path === '/c/' . $board['slug'] ? 'active' : '' ?>" data-board-slug="<?= $e($board['slug']) ?>" href="/c/<?= $e($board['slug']) ?>"<?= $request_path === '/c/' . $board['slug'] ? ' aria-current="page"' : '' ?>>
                                        <span class="board-rail-name"><span class="hash">#</span><?= $e($board['name']) ?></span>
                                        <?php if ($unread > 0): ?><span class="board-unread-count" data-board-unread-count="<?= $unread ?>" aria-label="<?= $unread ?> unread topic<?= $unread === 1 ? '' : 's' ?>"><?= $unread > 99 ? '99+' : $unread ?></span><?php endif; ?>
                                        <?php if ($board['visibility'] !== 'public'): ?><span class="tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                                    </a>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </nav>

    <?php if (!empty($features['presence'])): ?>
        <?php
        // The widget is NEVER hidden, with or without JavaScript: it carries the
        // shell's only route to /users-online, so hiding it on an empty roster
        // would remove the one way to go and look. The poll updates it in place.
        ?>
        <section class="presence-widget" data-presence data-presence-limit="<?= $presenceLimit ?>" data-presence-poll>
            <h2 class="presence-title">
                <a href="/users-online">Online</a>
                <span class="presence-count" data-presence-count><?= $presenceCapped ? $presenceHere . '+' : $presenceHere ?></span>
            </h2>
            <?php
            // Exactly one live region, and it announces a SUMMARY. The list used
            // to be the live region while the poller replaced its innerHTML
            // wholesale, so a screen reader re-read the entire roster every 45
            // seconds whether or not anything had changed.
            ?>
            <p class="sr-only" aria-live="polite" data-presence-summary><?= $presenceHere ?> member<?= $presenceHere === 1 ? '' : 's' ?> here now<?= $presenceTotal > $presenceHere ? ', ' . ($presenceTotal - $presenceHere) . ' away' : '' ?>.</p>
            <ul class="presence-list" data-presence-list>
                <?php foreach ($presenceShown as $member): ?>
                    <?= $this->partial('partials/presence_person', ['member' => $member]) ?>
                <?php endforeach; ?>
            </ul>
            <?php if ($presenceTotal === 0): ?>
                <p class="presence-empty" data-presence-empty>No one is showing as online.</p>
            <?php else: ?>
                <p class="presence-empty" data-presence-empty hidden>No one is showing as online.</p>
            <?php endif; ?>
            <?php // "+N more" is server-rendered, so it is right with JS off too. ?>
            <p class="presence-more" data-presence-more<?= $presenceMore > 0 ? '' : ' hidden' ?>><?= $presenceMore > 0 ? '+' . $presenceMore . ' more' : '' ?></p>
            <p class="presence-foot"><a class="presence-all" href="/users-online">See everyone online</a></p>
        </section>
    <?php endif; ?>
</aside>
