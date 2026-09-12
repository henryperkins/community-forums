<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * /users-online — the presence roll (ADR 0031), verbatim from the design system's
 * templates/users-online/UsersOnline.dc.html as of the 2026-09-12 handoff
 * (docs/design-system/imladris/_archive/design_handoff_presence/README.md).
 *
 * The design draws a full member directory behind an off-by-default toggle
 * (Everyone / Staff / New this week) and a loading skeleton and error card; this
 * ships the presence pool only, because the other filters would publish a
 * public, searchable list of members who never opted into presence. Those
 * deferrals are recorded in the ADR, not left as a silent gap.
 *
 * Every control is a GET form or a plain link. Filters, search and paging all
 * work with JavaScript off; the poll only refreshes the rail widget, never this
 * page. The one control the design does not draw is the Search button: the
 * prototype filters as you type, and a GET form with no submit still submits on
 * Enter, but a button is what a mouse finds without JavaScript.
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
<div class="read-main users-online" data-users-online>
    <div class="users-online-column">
        <header class="users-online-hero">
            <p class="eyebrow">Presence</p>
            <h1>Who is at the council</h1>
            <p class="users-online-lede">Members who chose to show their presence and have been here recently. A leaf means here now; amber means stepped away.</p>
        </header>

        <?php // Controls: filters with counts, a search that echoes its term, the tally. ?>
        <div class="users-online-controls">
            <nav class="users-online-filters" aria-label="Filter by presence">
                <?php foreach ($tabs as $tab): ?>
                    <?php $isOn = $filter === $tab['value']; ?>
                    <?php if ($isOn): ?>
                        <a class="users-online-filter is-active" href="<?= $e($queryFor(['filter' => $tab['value'], 'page' => ''])) ?>" aria-current="page"><?= $e($tab['label']) ?><span class="users-online-filter-count"><?= (int) $tab['count'] ?><?= $e($suffix) ?></span></a>
                    <?php else: ?>
                        <a class="users-online-filter" href="<?= $e($queryFor(['filter' => $tab['value'], 'page' => ''])) ?>"><?= $e($tab['label']) ?><span class="users-online-filter-count"><?= (int) $tab['count'] ?><?= $e($suffix) ?></span></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>

            <?php // GET, so a search never mutates state and the result is linkable. ?>
            <form class="users-online-search" method="get" action="/users-online" role="search">
                <?php if ($filter !== ''): ?><input type="hidden" name="filter" value="<?= $e($filter) ?>"><?php endif; ?>
                <label class="users-online-field">
                    <svg class="users-online-field-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.6-3.6"/></svg>
                    <input id="presence-q" name="q" type="search" value="<?= $e($search) ?>" placeholder="Find a member" aria-label="Find a member" autocomplete="off">
                </label>
                <?php if ($search !== ''): ?>
                    <a class="users-online-clear" href="<?= $e($queryFor(['q' => '', 'page' => ''])) ?>">Clear</a>
                <?php endif; ?>
                <button class="btn btn-small" type="submit">Search</button>
            </form>

            <span class="users-online-result" aria-live="polite" data-users-online-count><?= $shown === 1 ? '1 member' : $shown . $e($suffix) . ' members' ?></span>
        </div>

        <?php if ($members === []): ?>
            <?php // Nobody on this page of the roll — one sentence, which one depends on why. ?>
            <p class="users-online-empty">
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
            <?php // The roll — the same row the rail renders, laid out wide. ?>
            <div class="users-online-roll">
                <ul class="presence-list presence-grid">
                    <?php foreach ($members as $member): ?>
                        <?= $this->partial('partials/presence_person', ['member' => $member]) ?>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($pages > 1): ?>
            <nav class="users-online-pager" aria-label="Roll pages">
                <?php if ($page > 1): ?>
                    <a class="users-online-page" rel="prev" href="<?= $e($queryFor(['page' => $page - 1])) ?>">Previous</a>
                <?php else: ?>
                    <span class="users-online-page is-disabled" aria-disabled="true">Previous</span>
                <?php endif; ?>
                <span class="users-online-page-label">Page <?= $page ?> of <?= $pages ?></span>
                <?php if ($page < $pages): ?>
                    <a class="users-online-page" rel="next" href="<?= $e($queryFor(['page' => $page + 1])) ?>">Next</a>
                <?php else: ?>
                    <span class="users-online-page is-disabled" aria-disabled="true">Next</span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <?php // Guidance strip — what each dot means, and what the roll cannot tell you. The dots here are decorative too: the label beside each is the state. ?>
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

        <p class="users-online-privacy">Presence is optional, and this roll is never the whole community. Turn yours off any time under <a href="/settings/privacy">privacy settings</a>.</p>
    </div>
</div>
