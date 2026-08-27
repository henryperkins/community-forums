<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', $query !== '' ? 'Search: ' . $query : 'Search'); $this->section('robots', 'noindex, nofollow'); $this->section('route', 'search'); ?>
<?php
$scopeLabels = [
    'everything' => 'Everything',
    'topics' => 'Topics',
    'replies' => 'Replies',
    'mine' => 'Mine',
];
$orderLabels = [
    'relevance' => 'Relevance',
    'newest' => 'Newest',
];
$resultCount = count($results);
$countLabel = $resultCount . ($resultCount === 1 ? ' result' : ' results');
$orderCopy = $order === 'newest' ? 'newest first' : 'by relevance';
?>
<div class="search-surface" data-search-scope="<?= $e($scope) ?>" data-search-order="<?= $e($order) ?>">
    <div class="search-column">
        <h1>Search the council</h1>

        <form class="search-form" method="get" action="/search" role="search" aria-label="Search the council">
            <div class="search-input-row">
                <input class="search-query-well" type="search" enterkeyhint="search" name="q" value="<?= $e($query) ?>"
                       aria-label="Search the council" aria-describedby="search-query-help<?= $error !== null ? ' search-query-error' : '' ?>"
                       <?= $error !== null ? 'aria-invalid="true" ' : '' ?>required minlength="3"
                       placeholder="A phrase, a title, a member…" autofocus>
                <button class="btn" type="submit">Search</button>
            </div>
            <input type="hidden" name="scope" value="<?= $e($scope) ?>">
            <input type="hidden" name="order" value="<?= $e($order) ?>">
            <p class="search-query-help muted" id="search-query-help">Use at least three characters.</p>
            <?php if ($error !== null): ?><p class="form-error" id="search-query-error" role="alert"><?= $e($error) ?></p><?php endif; ?>
        </form>

        <nav class="search-view-bar" aria-label="Search view">
            <span class="search-view-label">Viewing</span>
            <span class="search-scope-options" role="group" aria-label="Scope">
                <?php foreach ($scopeLabels as $item => $label): ?>
                    <a href="<?= $e($search_query->url($item, $order)) ?>"<?= $item === $scope ? ' class="is-active" aria-current="page"' : '' ?>><?= $e($label) ?></a>
                <?php endforeach; ?>
            </span>
            <span class="search-order-options" role="group" aria-label="Order">
                <?php foreach ($orderLabels as $item => $label): ?>
                    <a href="<?= $e($search_query->url($scope, $item)) ?>"<?= $item === $order ? ' class="is-active" aria-current="page"' : '' ?>><?= $e($label) ?></a>
                <?php endforeach; ?>
            </span>
        </nav>

        <?php if ($submitted && $error === null): ?>
            <p class="search-result-count"><?= $e($countLabel) ?> for “<?= $e($query) ?>” · <?= $e($orderCopy) ?></p>
            <?php if ($results !== []): ?>
                <ol class="search-results">
                    <?php foreach ($results as $result): ?>
                        <?php $kind = $result['type'] === 'post' ? 'Reply' : 'Topic'; ?>
                        <li class="search-result">
                            <p class="search-result-byline"><span><?= $e($kind) ?></span><span aria-hidden="true">·</span><a href="/c/<?= $e($result['board_slug']) ?>"><?= $e($result['board_name']) ?></a></p>
                            <a class="search-result-title" href="<?= $e($result['url']) ?>"><?= $e($result['title']) ?></a>
                            <?php if (($result['snippet'] ?? '') !== ''): ?><p class="search-result-snippet"><?= $result['snippet'] /* HTML-safe plain text from SearchService */ ?></p><?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php else: ?>
                <div class="search-empty-state">
                    <img src="/assets/commend-star.svg" alt="" width="26" height="26">
                    <p class="search-empty-title">Nothing matches that.</p>
                    <p class="muted">Try a shorter phrase, or widen the scope above.</p>
                </div>
            <?php endif; ?>
        <?php elseif (!$submitted): ?>
            <div class="search-initial-state" id="search-query-initial">
                <img src="/assets/commend-star.svg" alt="" width="26" height="26">
                <p>Search topic titles and replies across every board you can read.</p>
                <p class="muted">Results always honor board visibility and membership.</p>
            </div>
        <?php endif; ?>
    </div>
</div>
