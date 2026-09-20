<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The board rail — the design system's BoardRail
 * (docs/design-system/imladris/components/forum/BoardRail.jsx), rendered
 * server-side (ADR 0032). Boards and nothing else, at one width, with the
 * active board marked the same way on every surface that has one; the presence
 * widget hangs in the footer slot, so the same roster and the same "See everyone
 * online" travel with the member across every route.
 *
 * The class vocabulary is the design's own and the layered /assets/imladris.css
 * styles it; app.css adds only what the layer cannot express (the sticky desktop
 * rail, the phone drawer, the persisted rail-closed state, the picker's button
 * reset) — see its "Member chrome" block.
 *
 * On /compose the rail is the destination picker: a row is a GET button that
 * selects a board, the chosen row says "posting here", and a board the viewer
 * may not post to is shown locked rather than quietly missing.
 */
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

$composeMode = is_array($compose_boards ?? null);
$composeBoardMap = [];
if ($composeMode) {
    foreach ($compose_boards as $composeBoard) {
        $composeBoardMap[(int) $composeBoard['id']] = $composeBoard;
    }
}
$composeSelectedId = (int) ($selected_board ?? 0);

$unreadPill = static function (int $unread) use ($e): string {
    if ($unread <= 0) {
        return '';
    }
    $label = $unread . ' unread topic' . ($unread === 1 ? '' : 's');
    return '<span class="board-rail-unread" data-board-unread-count="' . $unread . '" title="' . $e($label) . '" aria-label="' . $e($label) . '">'
        . ($unread > 99 ? '99+' : $unread) . '</span>';
};
?>
<nav class="board-rail" id="sidebar-nav" data-sidebar aria-label="Boards"<?= $this->block('account_settings', '') === '1' ? ' tabindex="0"' : '' ?>>
    <?php $organization = !$composeMode && is_callable($organization_nav ?? null) ? $organization_nav() : []; ?>
    <?php foreach (($organization['board_folders'] ?? []) as $folder): ?>
        <span class="board-rail-cat"><?= $e($folder['name']) ?></span>
        <?php foreach ($folder['boards'] as $shortcut): ?>
            <a class="board-rail-item" href="/c/<?= $e($shortcut['slug']) ?>"><span class="board-rail-name"><?= $e($shortcut['name']) ?></span></a>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php if (!empty($organization['saved_feeds'])): ?>
        <span class="board-rail-cat">Saved feeds</span>
        <?php foreach ($organization['saved_feeds'] as $shortcut): ?>
            <a class="board-rail-item<?= $request_path === '/feeds/saved/' . $shortcut['id'] ? ' is-active' : '' ?>" href="/feeds/saved/<?= (int) $shortcut['id'] ?>"<?= $request_path === '/feeds/saved/' . $shortcut['id'] ? ' aria-current="page"' : '' ?>><span class="board-rail-name"><?= $e($shortcut['name']) ?></span></a>
        <?php endforeach; ?>
    <?php endif; ?>
    <?php if (empty($nav)): ?>
        <p class="muted board-rail-empty">No boards yet.</p>
    <?php else: ?>
        <?php foreach ($nav as $section): ?>
            <span class="board-rail-cat"><?= $e($section['category']['name']) ?></span>
            <?php foreach ($section['boards'] as $board): ?>
                <?php
                $unread = (int) ($board['unread_count'] ?? 0);
                $composeBoard = $composeBoardMap[(int) $board['id']] ?? null;
                ?>
                <?php if ($composeMode && $composeBoard !== null): ?>
                    <?php if (!empty($composeBoard['can_post'])): ?>
                        <?php $picked = (int) $board['id'] === $composeSelectedId; ?>
                        <form class="compose-board-picker-form" method="get" action="/compose">
                            <button class="board-rail-item compose-board-picker<?= $picked ? ' is-active' : '' ?>" type="submit" name="board" value="<?= $e($board['slug']) ?>" data-board-slug="<?= $e($board['slug']) ?>" data-compose-board-picker="<?= $e($board['slug']) ?>" aria-pressed="<?= $picked ? 'true' : 'false' ?>">
                                <span class="board-rail-name"><?= $e($board['name']) ?></span>
                                <?= $unreadPill($unread) ?>
                                <?php if ($picked): ?><span class="board-rail-note">posting here</span><?php endif; ?>
                            </button>
                        </form>
                    <?php else: ?>
                        <span class="board-rail-item is-locked" data-board-slug="<?= $e($board['slug']) ?>" data-compose-board-disabled="<?= $e($board['slug']) ?>" title="<?= $e($composeBoard['post_block_reason'] ?? 'You cannot open a topic here.') ?>">
                            <span class="board-rail-name"><?= $e($board['name']) ?></span>
                            <?= $unreadPill($unread) ?>
                            <svg class="board-rail-lock" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 018 0v4"/></svg>
                        </span>
                    <?php endif; ?>
                <?php else: ?>
                    <?php $active = $request_path === '/c/' . $board['slug']
                        || (int) ($active_thread_board_id ?? 0) === (int) $board['id']; ?>
                    <?php // The active board keeps its href too (the component renders it inert): the row is also the way back to the board's first page. ?>
                    <a class="board-rail-item<?= $active ? ' is-active' : '' ?>" data-board-slug="<?= $e($board['slug']) ?>" href="/c/<?= $e($board['slug']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
                        <span class="board-rail-name"><?= $e($board['name']) ?></span>
                        <?= $unreadPill($unread) ?>
                        <?php if ($board['visibility'] !== 'public'): ?><span class="board-rail-tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if (!empty($features['presence'])): ?>
        <div class="board-rail-foot">
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
                <?php // The empty and "+N more" lines are always in the DOM so the poller can toggle them; the server renders them right for the no-JS reader. ?>
                <?php if ($presenceTotal === 0): ?>
                    <p class="presence-empty" data-presence-empty>No one is showing as online.</p>
                <?php else: ?>
                    <p class="presence-empty" data-presence-empty hidden>No one is showing as online.</p>
                <?php endif; ?>
                <p class="presence-more" data-presence-more<?= $presenceMore > 0 ? '' : ' hidden' ?>><?= $presenceMore > 0 ? '+' . $presenceMore . ' more' : '' ?></p>
                <p class="presence-foot"><a class="presence-all" href="/users-online">See everyone online</a></p>
            </section>
        </div>
    <?php endif; ?>
</nav>
