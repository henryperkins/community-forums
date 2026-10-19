<?php /** @var \App\Core\View $this */ ?>
<?php
/**
 * The collapsible details rail — the home for "everything about this thing":
 * identity → facts → quiet actions → danger. Everything that used to be scattered
 * across the conversation header / inline group panel lives here. Server-rendered
 * (works no-JS as a real column / anchor target); app.js only toggles it.
 *
 * All controls submit the EXISTING POST endpoints — no new routes. Group members
 * / owner tools only render for group conversations (which can only exist while
 * the group_dms feature is enabled).
 *
 * Params: conversation, conversation_id, is_group, is_owner, other, participants,
 * muted, other_is_blocked, rail_label (optional — the caller's precomputed
 * icon/label text, kept in sync with the header toggle + menu item that name
 * the same rail), rail_errors / rail_old (a failed owner tool's field error
 * and typed value), rail_close_href, leave_note / leave_action (the caller's
 * Leave step; a null action renders the reason without a submit),
 * show_avatars. $current_user and $e are ambient — the View shares them with
 * every template/partial, not passed explicitly.
 */
$railId = (int) $conversation_id;
$railIsGroup = !empty($is_group);
$railMuted = !empty($muted);
$muteLabel = $railMuted ? 'Unmute conversation' : 'Mute conversation';
$muteNext = $railMuted ? '0' : '1';
$railLabel = $rail_label ?? ($railIsGroup ? 'Members & details' : 'Details');
$railErrors = $rail_errors ?? [];
$railOld = $rail_old ?? [];
$railCloseHref = (string) ($rail_close_href ?? '#');
$railDefined = get_defined_vars();
$railLeaveNote = (string) ($leave_note ?? 'You stop receiving its messages and it leaves your list. Only the owner can add you back, and you would read only what follows.');
$railLeaveAction = array_key_exists('leave_action', $railDefined) ? $leave_action : '/messages/' . $railId . '/members/remove';
$railAddId = 'dm-add-member-' . $railId;
$railAddErrId = 'dm-add-member-error-' . $railId;
$railTitleId = 'dm-rename-group-' . $railId;
$railTitleErrId = 'dm-rename-group-error-' . $railId;
?>
<aside class="dm-inforail" id="dm-rail" aria-label="<?= $e($railLabel) ?>">
    <div class="dm-rail-head">
        <span class="eyebrow"><?= $e($railLabel) ?></span>
        <a href="<?= $e($railCloseHref) ?>" class="dm-iconbtn" data-rail-close aria-label="Close details"><?= $this->partial('partials/icon', ['name' => 'x']) ?></a>
    </div>

    <div class="dm-rail-body">
        <?php if ($railIsGroup): ?>
            <?php
            $railTitle = ($conversation['title'] ?? '') !== '' ? (string) $conversation['title'] : 'Group conversation';
            $activeCount = count(array_filter($participants ?? [], static fn ($p) => empty($p['left_at'])));
            ?>
            <div class="dm-rail-id">
                <?= $this->partial('partials/monogram', ['name' => $railTitle, 'username' => 'group-' . $railId, 'gilt' => true]) ?>
                <h2 class="dm-rail-name"><?= $e($railTitle) ?></h2>
                <span class="dm-rail-handle"><?= $activeCount ?> in counsel</span>
            </div>

            <div class="dm-rail-sec">
                <h3>Members</h3>
                <ul class="dm-members">
                    <?php foreach (($participants ?? []) as $p): ?>
                        <?php
                        $pName = ($p['display_name'] ?? '') !== '' ? $p['display_name'] : $p['username'];
                        $pLeft = !empty($p['left_at']);
                        $pOwner = ($p['role'] ?? '') === 'owner';
                        $pMe = $current_user !== null && (int) $p['user_id'] === $current_user->id();
                        $canManage = !empty($is_owner) && !$pLeft && !$pMe && !$pOwner;
                        ?>
                        <li class="dm-member<?= $pLeft ? ' is-left' : '' ?>">
                            <?= $this->partial('partials/monogram', ['name' => $pName, 'username' => (string) $p['username'], 'avatar_path' => $p['avatar_path'] ?? null]) ?>
                            <span class="m-id">
                                <span class="m-name"><?= $e($pName) ?><?= $pMe ? ' (you)' : '' ?></span>
                                <span class="m-handle">@<?= $e($p['username']) ?><?= $this->partial('partials/dm_presence', ['user_id' => $p['user_id'], 'state' => $presence_states[(int) $p['user_id']] ?? 'offline']) ?></span>
                            </span>
                            <?php if ($pOwner): ?>
                                <span class="m-role">Owner</span>
                            <?php elseif ($pLeft): ?>
                                <span class="m-role left">Left</span>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <?php /* Names start with the visible words (WCAG 2.5.3) and say which member. */ ?>
                                <span class="dm-member-tools">
                                    <?= $this->partial('partials/dm_confirm', [
                                        'summary_class' => 'dm-linkbtn',
                                        'label' => 'Make owner',
                                        'label_name' => 'Make owner: ' . $pName,
                                        'note' => $pName . ' becomes the owner. They can add and remove members and rename the group, and you become a member. Only the new owner can hand ownership on.',
                                        'note_id' => 'dm-owner-note-' . $railId . '-' . (int) $p['user_id'],
                                        'action' => '/messages/' . $railId . '/transfer',
                                        'fields' => ['user_id' => (int) $p['user_id']],
                                        'confirm' => 'Make ' . $pName . ' owner',
                                        'group' => 'dm-member-' . $railId . '-' . (int) $p['user_id'],
                                    ]) ?>
                                    <?= $this->partial('partials/dm_confirm', [
                                        'summary_class' => 'dm-linkbtn danger',
                                        'label' => 'Remove',
                                        'label_name' => 'Remove ' . $pName,
                                        'note' => $pName . ' stops receiving new messages here and keeps only what came before. Only the owner can add them back, and they would read only what follows.',
                                        'note_id' => 'dm-remove-note-' . $railId . '-' . (int) $p['user_id'],
                                        'action' => '/messages/' . $railId . '/members/remove',
                                        'fields' => ['user_id' => (int) $p['user_id']],
                                        'confirm' => 'Remove ' . $pName,
                                        'danger' => true,
                                        'group' => 'dm-member-' . $railId . '-' . (int) $p['user_id'],
                                    ]) ?>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($is_owner)): ?>
                <div class="dm-rail-sec">
                    <h3>Owner tools</h3>
                    <form class="dm-owner-tool" method="post" action="/messages/<?= $railId ?>/members" data-submit-once>
                        <?= $this->csrfField() ?>
                        <div data-dm-picker data-dm-allow-groups="0" data-dm-avatars="<?= ($show_avatars ?? true) ? '1' : '0' ?>">
                            <input class="input input-small" type="text" id="<?= $e($railAddId) ?>" name="username" maxlength="32" placeholder="username" aria-label="Add member" value="<?= $e($railOld['username'] ?? '') ?>"<?= field_attrs($railErrors, 'username', $railAddErrId) ?> required>
                            <template data-dm-chip-template><span class="dm-chip"><?php if ($show_avatars ?? true): ?><span class="monogram dm-chip-mono" aria-hidden="true"></span><?php endif; ?><span data-chip-name></span><button type="button" class="dm-chip-x"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button></span></template>
                        </div>
                        <button class="btn btn-small" type="submit">Add</button>
                        <?= field_error($railErrors, 'username', $railAddErrId) ?>
                    </form>
                    <form class="dm-owner-tool" method="post" action="/messages/<?= $railId ?>/rename" data-submit-once>
                        <?= $this->csrfField() ?>
                        <input class="input input-small" type="text" id="<?= $e($railTitleId) ?>" name="title" maxlength="120" value="<?= $e($railOld['title'] ?? ($conversation['title'] ?? '')) ?>" aria-label="Rename group"<?= field_attrs($railErrors, 'title', $railTitleErrId) ?> required>
                        <button class="btn btn-small" type="submit">Rename</button>
                        <?= field_error($railErrors, 'title', $railTitleErrId) ?>
                    </form>
                </div>
            <?php endif; ?>

            <div class="dm-rail-sec">
                <h3>This conversation</h3>
                <div class="dm-rail-actions">
                    <form class="dm-rail-form" method="post" action="/messages/<?= $railId ?>/mute">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="muted" value="<?= $muteNext ?>">
                        <button class="dm-rail-btn" type="submit"><?= $this->partial('partials/icon', ['name' => 'bell-off']) ?><span><?= $e($muteLabel) ?></span></button>
                    </form>
                    <?= $this->partial('partials/dm_confirm', [
                        'summary_class' => 'dm-rail-btn danger',
                        'icon' => 'log-out',
                        'label' => 'Leave group',
                        'note' => $railLeaveNote,
                        'note_id' => 'dm-leave-note-rail-' . $railId,
                        'action' => $railLeaveAction,
                        'fields' => ['user_id' => $current_user !== null ? (int) $current_user->id() : 0],
                        'confirm' => 'Leave group',
                        'danger' => true,
                    ]) ?>
                </div>
            </div>
        <?php else: ?>
            <?php
            $railOtherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
            $railOtherUser = (string) ($other['username'] ?? '');
            $railRole = ['user' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Admin'][(string) ($other['role'] ?? 'user')] ?? 'Member';
            ?>
            <div class="dm-rail-id">
                <?= $this->partial('partials/monogram', ['name' => $railOtherName, 'username' => $railOtherUser !== '' ? $railOtherUser : $railOtherName, 'avatar_path' => $other['avatar_path'] ?? null, 'gilt' => true]) ?>
                <h2 class="dm-rail-name"><?= $e($railOtherName) ?></h2>
                <?php if ($railOtherUser !== ''): ?><span class="dm-rail-handle">@<?= $e($railOtherUser) ?></span><?php endif; ?>
                <?= $this->partial('partials/dm_presence', ['user_id' => $other['id'] ?? 0, 'state' => $presence_states[(int) ($other['id'] ?? 0)] ?? 'offline', 'separator' => false]) ?>
                <span class="dm-tier-pill"><?= $e($railRole) ?></span>
            </div>

            <div class="dm-rail-sec">
                <h3>About</h3>
                <ul class="dm-rail-meta">
                    <?php if ($other !== null && ($other['created_at'] ?? '') !== ''): ?>
                        <li><span class="k">Joined</span><span class="v"><?= $e(human_datetime($other['created_at'])) ?></span></li>
                    <?php endif; ?>
                    <?php if ($other !== null && isset($other['reputation'])): ?>
                        <li><span class="k">Regard</span><span class="v"><?= (int) $other['reputation'] ?></span></li>
                    <?php endif; ?>
                </ul>
            </div>

            <div class="dm-rail-sec">
                <h3>This conversation</h3>
                <div class="dm-rail-actions">
                    <form class="dm-rail-form" method="post" action="/messages/<?= $railId ?>/mute">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="muted" value="<?= $muteNext ?>">
                        <button class="dm-rail-btn" type="submit"><?= $this->partial('partials/icon', ['name' => 'bell-off']) ?><span><?= $e($muteLabel) ?></span></button>
                    </form>
                    <?php if ($railOtherUser !== ''): ?>
                        <a class="dm-rail-btn" href="/u/<?= $e($railOtherUser) ?>"><?= $this->partial('partials/icon', ['name' => 'user']) ?><span>View profile</span></a>
                        <?php if (!empty($other_is_blocked)): ?>
                            <form class="dm-rail-form" method="post" action="/u/<?= $e($railOtherUser) ?>/block">
                                <?= $this->csrfField() ?>
                                <input type="hidden" name="return" value="/messages/<?= $railId ?>">
                                <input type="hidden" name="intent" value="unblock">
                                <button class="dm-rail-btn" type="submit"><?= $this->partial('partials/icon', ['name' => 'ban']) ?><span>Unblock <?= $e($railOtherName) ?></span></button>
                            </form>
                        <?php else: ?>
                            <?= $this->partial('partials/dm_confirm', [
                                'summary_class' => 'dm-rail-btn danger',
                                'icon' => 'ban',
                                'label' => 'Block ' . $railOtherName,
                                'note' => '@' . $railOtherUser . ' can no longer message you or mention you. Any follow between you ends, and unblocking does not restore it.',
                                'note_id' => 'dm-block-note-rail-' . $railId,
                                'action' => '/u/' . $railOtherUser . '/block',
                                'fields' => ['return' => '/messages/' . $railId, 'intent' => 'block'],
                                'confirm' => 'Block @' . $railOtherUser,
                                'danger' => true,
                            ]) ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</aside>
