<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * /users-online — the presence directory (ADR 0031).
 *
 * Adopted from docs/design-system/imladris/templates/users-online/UsersOnline.dc.html.
 * The design draws a full member roll (Everyone / Wardens / New this week);
 * this ships the presence pool only, because the other three filters would
 * publish a public, searchable list of members who never opted into presence.
 * That deferral is recorded in the ADR, not left as a silent gap.
 *
 * Every control is a GET form or a plain link. Filters, search and paging all
 * work with JavaScript off; the poll only refreshes what is already here.
 */
$this->layout('layout');
$this->section('title', 'Members online');

$d = is_array($directory ?? null) ? $directory : [];
$members = is_array($d['members'] ?? null) ? $d['members'] : [];
$here = (int) ($d['here'] ?? 0);
$away = (int) ($d['away'] ?? 0);
$total = (int) ($d['total'] ?? 0);
$shown = (int) ($d['shown'] ?? 0);
$capped = !empty($d['capped']);
$page = (int) ($d['page'] ?? 1);
$pages = (int) ($d['pages'] ?? 1);
$filter = (string) ($d['filter'] ?? '');
$search = (string) ($d['search'] ?? '');

$suffix = $capped ? '+' : '';
$tabs = [
    ['value' => '', 'label' => 'Everyone here', 'count' => $total],
    ['value' => 'online', 'label' => 'Here now', 'count' => $here],
    ['value' => 'away', 'label' => 'Away', 'count' => $away],
];
$queryFor = static function (array $over) use ($filter, $search): string {
    $q = array_filter([
        'filter' => $over['filter'] ?? $filter,
        'q' => $over['q'] ?? $search,
        'page' => (string) ($over['page'] ?? ''),
    ], static fn ($v): bool => (string) $v !== '');
    return $q === [] ? '/users-online' : '/users-online?' . http_build_query($q);
};
?>
<div class="read-main read-pad users-online" data-users-online>
    <header class="users-online-hero">
        <p class="eyebrow">Presence</p>
        <h1>Who is at the council</h1>
        <p class="users-online-lede">Members who chose to show their presence and have been here recently. A leaf means here now; amber means stepped away.</p>
    </header>

    <div class="users-online-controls">
        <nav class="users-online-filters" aria-label="Filter by presence">
            <?php foreach ($tabs as $tab): ?>
                <?php $isOn = $filter === $tab['value']; ?>
                <a class="users-online-filter<?= $isOn ? ' is-active' : '' ?>"
                   href="<?= $e($queryFor(['filter' => $tab['value'], 'page' => ''])) ?>"
                   <?= $isOn ? 'aria-current="page"' : '' ?>><?= $e($tab['label']) ?>
                    <span class="users-online-filter-count"><?= (int) $tab['count'] ?><?= $e($suffix) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <?php // GET, so a search never mutates state and the result is linkable. ?>
        <form class="users-online-search" method="get" action="/users-online" role="search">
            <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= $e($filter) ?>"><?php endif; ?>
            <label class="sr-only" for="presence-q">Find a member</label>
            <input id="presence-q" name="q" type="search" value="<?= $e($search) ?>" placeholder="Find a member" autocomplete="off">
            <button class="btn btn-small" type="submit">Search</button>
            <?php if ($search !== ''): ?>
                <a class="users-online-clear" href="<?= $e($queryFor(['q' => '', 'page' => ''])) ?>">Clear</a>
            <?php endif; ?>
        </form>

        <p class="users-online-result" data-users-online-count>
            <?= $shown === 1 ? '1 member' : $shown . $e($suffix) . ' members' ?>
        </p>
    </div>

    <?php if ($members === []): ?>
        <p class="presence-empty users-online-empty">
            <?php if ($search !== ''): ?>
                No member on the roll matches “<?= $e($search) ?>”.
            <?php elseif ($filter === 'away'): ?>
                No one has stepped away.
            <?php elseif ($filter === 'online'): ?>
                No one is at the council right now.
            <?php else: ?>
                No one is showing as online right now.
            <?php endif; ?>
        </p>
    <?php else: ?>
        <ul class="presence-list presence-grid">
            <?php foreach ($members as $member): ?>
                <?= $this->partial('partials/presence_person', ['member' => $member]) ?>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($pages > 1): ?>
        <nav class="users-online-pager" aria-label="Roll pages">
            <?php if ($page > 1): ?>
                <a class="btn btn-small" rel="prev" href="<?= $e($queryFor(['page' => $page - 1])) ?>">Previous</a>
            <?php else: ?>
                <span class="btn btn-small is-disabled" aria-disabled="true">Previous</span>
            <?php endif; ?>
            <span class="users-online-page-label">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-small" rel="next" href="<?= $e($queryFor(['page' => $page + 1])) ?>">Next</a>
            <?php else: ?>
                <span class="btn btn-small is-disabled" aria-disabled="true">Next</span>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

    <?php // The design's guidance strip: what each dot means, and what the roll cannot tell you. ?>
    <div class="presence-guidance">
        <div class="presence-guidance-card">
            <span class="presence-guidance-label"><span class="presence-guidance-dot" aria-hidden="true"></span>Here now</span>
            <p>Seen in the last few minutes. The leaf is the only mark presence gets.</p>
        </div>
        <div class="presence-guidance-card">
            <span class="presence-guidance-label"><span class="presence-guidance-dot is-away" aria-hidden="true"></span>Stepped away</span>
            <p>Idle for a while but still around. No one is chased off the roll for being quiet.</p>
        </div>
        <div class="presence-guidance-card">
            <span class="presence-guidance-label"><span class="presence-guidance-dot is-off" aria-hidden="true"></span>Not shown</span>
            <p>Members can turn presence off in their privacy settings. They are here; you simply are not told.</p>
        </div>
    </div>

    <p class="presence-foot users-online-privacy">Presence is optional, and this roll is never the whole community. Turn yours off any time under <a href="/settings/privacy">privacy settings</a>.</p>
</div>
