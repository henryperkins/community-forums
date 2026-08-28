<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * "Catch me up" — the deterministic since-you-last-read strip
 * (ThreadView.dc.html:194-215).
 *
 * Read once, then never again, so it costs ONE line until it is asked to open.
 * It used to be a full panel — a heading, a count paragraph and a bulleted list
 * of excerpts — printed at full height above the topic, every visit, for a
 * reader whose whole question was "what did I miss?". The strip answers that in
 * the summary line and keeps the detail behind the disclosure.
 *
 * A <details>, not a JS toggle: strict CSP forbids an inline handler and this
 * has to work with JavaScript off like everything else on the surface. There is
 * no dismiss control because there is nothing to dismiss — production tracks the
 * read position, so opening the page clears the strip for the next visit; the
 * design's ✕ exists because its prototype has no read state to advance.
 *
 * @var array<string,mixed> $since_last_read_context
 */
$items = is_array($since_last_read_context['items'] ?? null) ? $since_last_read_context['items'] : [];
$count = (int) ($since_last_read_context['post_count'] ?? 0);
if ($items === [] || $count < 1) {
    return;
}
// The names, in order, as one phrase: "Arwen", "Arwen and Elrond",
// "Arwen, Elrond, then Glorfindel". mask_author() has already collapsed an
// anonymous reply to the constant identity upstream, so reading the strip can
// never be a way around the mask.
$names = [];
foreach ($items as $item) {
    $label = (string) ($item['author'] ?? '');
    if ($label !== '' && !in_array($label, $names, true)) {
        $names[] = $label;
    }
}
$namePhrase = match (count($names)) {
    0 => '',
    1 => $names[0],
    2 => $names[0] . ' and ' . $names[1],
    default => implode(', ', array_slice($names, 0, -1)) . ', then ' . $names[count($names) - 1],
};
$summary = $count . ($count === 1 ? ' reply' : ' replies') . ($namePhrase !== '' ? ' — ' . $namePhrase : '');
?>
<details class="catch-up" data-catch-up>
    <summary class="catch-up-summary">
        <span class="catch-up-mark" aria-hidden="true"><?= $this->partial('partials/icon', ['name' => 'history']) ?></span>
        <span class="catch-up-label">Catch me up</span>
        <span class="catch-up-line"><?= $e($summary) ?></span>
        <span class="catch-up-toggle" aria-hidden="true"><span class="catch-up-toggle-open">Read</span><span class="catch-up-toggle-close">Hide</span></span>
    </summary>
    <ul class="catch-up-points">
        <?php foreach ($items as $item): ?>
            <li>
                <a href="<?= $e($item['url'] ?? ('#p' . (int) $item['post_id'])) ?>"><?= $e((string) ($item['author'] ?? '')) ?></a>
                <span><?= $e((string) ($item['excerpt'] ?? '')) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</details>
