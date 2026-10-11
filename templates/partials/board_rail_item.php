<?php /** @var \App\Core\View $this */ ?>
<?php
// Category rows and personal shortcuts use the same board state. Folder data
// has already passed BoardPolicy::canRead; it never widens the category listing.
$active = $request_path === '/c/' . $board['slug']
    || (int) ($active_thread_board_id ?? 0) === (int) $board['id'];
?>
<a class="board-rail-item<?= $active ? ' is-active' : '' ?>" data-board-slug="<?= $e($board['slug']) ?>" href="/c/<?= $e($board['slug']) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
    <span class="board-rail-name"><?= $e($board['name']) ?></span>
    <?= $unread_pill((int) ($board['unread_count'] ?? 0)) ?>
    <?php if ($board['visibility'] !== 'public'): ?><span class="board-rail-tag"><?= $e($board['visibility']) ?></span><?php endif; ?>
</a>
