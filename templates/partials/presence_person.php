<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * One presence row — the single anatomy shared by the board rail, the
 * /users-online directory grid, and the rows public/assets/app.js builds when
 * the poll refreshes (ADR 0031).
 *
 * The server used to render one shape and the poller another: six rows here,
 * twenty there, a bare `.dot` span in one and nothing in the other, so a second
 * after load the rail silently reflowed from 264px to 595px. Keeping ONE
 * anatomy in ONE file is what makes the reconcile in app.js a no-op when nothing
 * has changed, and what lets a Playwright assertion prove it.
 *
 * The dot is decorative on purpose: it is aria-hidden everywhere and the state
 * is always present as text in `.presence-sub`, so a screen reader reads the
 * member once, not a leaf and a name.
 *
 * Expects: $member = ['username','display_name','state','is_self','is_staff'].
 */
$pState = ($member['state'] ?? '') === 'away' ? 'away' : 'online';
$pName = ($member['display_name'] ?? '') !== '' ? (string) $member['display_name'] : (string) $member['username'];
$pUser = (string) ($member['username'] ?? '');
// The signature is what app.js diffs against, so a row that has not changed is
// never re-rendered — and the live region stays quiet.
$pSig = $pUser . ':' . $pState . ':' . (!empty($member['is_staff']) ? 's' : '-') . ':' . (!empty($member['is_self']) ? 'y' : '-');
?>
<li class="presence-row" data-presence-row="<?= $e($pUser) ?>" data-presence-sig="<?= $e($pSig) ?>">
    <a class="presence-person" href="/u/<?= $e($pUser) ?>" data-presence-state="<?= $e($pState) ?>"<?= !empty($member['is_self']) ? ' data-presence-self="1"' : '' ?>>
        <?php
        // "Show avatars" is a member reading preference, and it applies here too:
        // the row is on the shell's rail on every route. With it off the dot
        // stands alone — the state must not disappear with the avatar, because
        // the dot is what the row is for.
        // One source, deliberately. A template scope carries only the shared
        // globals plus its own data (View::renderTemplate), so the per-page
        // `show_avatars` the thread and board controllers pass never reaches a
        // shared partial — reading it here would be a second, dead path.
        $pAvatars = is_callable($rail_avatars ?? null) ? ($rail_avatars)() : true;
        ?>
        <?php if ($pAvatars): ?>
            <span class="avatar-wrap">
                <?= $this->partial('partials/monogram', ['name' => $pName, 'username' => $pUser]) ?>
                <span class="presence-dot<?= $pState === 'away' ? ' is-away' : '' ?>" aria-hidden="true"></span>
            </span>
        <?php else: ?>
            <span class="presence-dot presence-dot-bare<?= $pState === 'away' ? ' is-away' : '' ?>" aria-hidden="true"></span>
        <?php endif; ?>
        <span class="presence-person-id">
            <span class="presence-name"><?= $e($pName) ?><?php if (!empty($member['is_self'])): ?><span class="presence-you">you</span><?php endif; ?><?php if (!empty($member['is_staff'])): ?><span class="presence-staff">Staff</span><?php endif; ?></span>
            <span class="presence-sub">@<?= $e($pUser) ?> · <?= $pState === 'away' ? 'Away' : 'Here now' ?></span>
        </span>
    </a>
</li>
