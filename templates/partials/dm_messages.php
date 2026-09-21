<?php /** @var \App\Core\View $this */ ?>
<?php
// Group consecutive messages by author into de-boxed "letters":
// one author line per run, then the run's messages.
$dmGroups = [];
foreach ($messages as $m) {
    $lastIdx = count($dmGroups) - 1;
    if ($lastIdx >= 0 && (int) $dmGroups[$lastIdx]['user_id'] === (int) $m['user_id']
        && substr($dmGroups[$lastIdx]['items'][0]['created_at'], 0, 10) === substr($m['created_at'], 0, 10)) {
        $dmGroups[$lastIdx]['items'][] = $m;
    } else {
        $dmGroups[] = ['user_id' => (int) $m['user_id'], 'items' => [$m]];
    }
}
// Conversation role (owner/member) per user, for the group rank pill.
$dmRoles = [];
foreach (($participants ?? []) as $pp) {
    $dmRoles[(int) $pp['user_id']] = (string) ($pp['role'] ?? '');
}
?>
<?php $dmDay = null; ?>
<?php foreach ($dmGroups as $g): ?>
    <?php
    $first = $g['items'][0];
    $mine = $current_user !== null && (int) $first['user_id'] === $current_user->id();
    $authorName = ($first['author_display_name'] ?? '') !== '' ? $first['author_display_name'] : $first['author_username'];
    ?>
    <?php $day = substr($first['created_at'], 0, 10); ?>
    <?php if ($day !== $dmDay): ?>
        <div class="dm-day" data-dm-day="<?= $e($day) ?>"><?= $this->partial('partials/dm_time', ['at' => $first['created_at'], 'mode' => 'day']) ?></div>
        <?php $dmDay = $day; ?>
    <?php endif; ?>
    <div class="dm-group<?= $mine ? ' mine' : '' ?>" data-dm-author="<?= (int) $first['user_id'] ?>" data-dm-date="<?= $e($day) ?>">
        <?php if (!$mine): ?>
            <span class="dm-mono-col"><?= $this->partial('partials/monogram', ['name' => $authorName, 'username' => $first['author_username']]) ?></span>
        <?php endif; ?>
        <div class="dm-msgs">
            <div class="dm-ghead">
                <span class="dm-name"><?= $mine ? 'You' : $e($authorName) ?></span>
                <?php if (!$mine && !empty($is_group) && ($dmRoles[(int) $first['user_id']] ?? '') === 'owner'): ?>
                    <span class="dm-rank">Owner</span>
                <?php endif; ?>
                <?= $this->partial('partials/dm_time', ['at' => $first['created_at'], 'mode' => 'clock', 'class' => 'dm-gtime']) ?>
            </div>
            <?php foreach ($g['items'] as $m): ?>
                <div class="dm-line" id="m<?= (int) $m['id'] ?>" data-message-id="<?= (int) $m['id'] ?>" data-created-at="<?= $e(\App\Support\DmTime::iso($m['created_at'])) ?>">
                    <?= $this->partial('partials/dm_time', ['at' => $m['created_at'], 'mode' => 'clock', 'class' => 'sr-only']) ?>
                    <div class="dm-body formatted-content">
                        <?= $m['body_html'] /* sanitised at write time or rendered read fallback */ ?>
                    </div>
                    <?php if (!$mine): ?>
                        <span class="dm-line-menu">
                            <details class="dm-report">
                                <summary class="dm-dotbtn" aria-label="Message actions"><?= $this->partial('partials/icon', ['name' => 'more-horizontal']) ?></summary>
                                <form method="post" action="/dm/<?= (int) $m['id'] ?>/report" class="dm-report-form">
                                    <?= $this->csrfField() ?>
                                    <button type="button" class="linkbtn dm-copy" data-copy-message hidden><?= $this->partial('partials/icon', ['name' => 'copy']) ?><span>Copy text</span></button>
                                    <select name="reason_code" class="input input-small" aria-label="Report reason">
                                        <?php foreach ($reasons as $rc): ?><option value="<?= $e($rc) ?>"><?= $e(ucfirst(str_replace('_', ' ', $rc))) ?></option><?php endforeach; ?>
                                    </select>
                                    <input type="text" name="reason" class="input input-small" placeholder="Details (optional)" maxlength="255" aria-label="Report details">
                                    <button class="btn btn-small danger" type="submit">Report message</button>
                                </form>
                            </details>
                        </span>
                    <?php endif; ?>
                </div>
                <?php $messageReferenceCards = ($reference_cards ?? [])[(int) $m['id']] ?? []; ?>
                <?php if (!empty($messageReferenceCards)): ?>
                    <div class="reference-cards" aria-label="Referenced content">
                        <?php foreach ($messageReferenceCards as $card): ?>
                            <a class="reference-card" href="<?= $e($card['url']) ?>">
                                <span class="ref-type"><?= $e($card['type']) ?></span>
                                <strong><?= $e($card['title']) ?></strong>
                                <?php if (($card['meta'] ?? '') !== ''): ?><span class="ref-meta"><?= $e($card['meta']) ?></span><?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <?php if ((int) ($receipt_message_id ?? 0) === (int) $m['id']): ?>
                    <div class="dm-receipt-row" data-dm-receipt="<?= (int) $m['id'] ?>"><span class="dm-receipt"><?= (int) ($other_last_read_message_id ?? 0) >= (int) $m['id'] ? 'Read' : 'Delivered' ?></span></div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>
