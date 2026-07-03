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
 * the same rail). $current_user and $e are ambient — the View shares them with
 * every template/partial, not passed explicitly.
 */
$railId = (int) $conversation_id;
$railIsGroup = !empty($is_group);
$railMuted = !empty($muted);
$muteLabel = $railMuted ? 'Unmute conversation' : 'Mute conversation';
$muteNext = $railMuted ? '0' : '1';
$railLabel = $rail_label ?? ($railIsGroup ? 'Members & details' : 'Details');
?>
<aside class="dm-inforail" id="dm-rail" aria-label="<?= $e($railLabel) ?>">
    <div class="dm-rail-head">
        <span class="eyebrow"><?= $e($railLabel) ?></span>
        <button type="button" class="dm-iconbtn" data-rail-close aria-label="Close details"><?= $this->partial('partials/icon', ['name' => 'x']) ?></button>
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
                            <?= $this->partial('partials/monogram', ['name' => $pName, 'username' => (string) $p['username']]) ?>
                            <span class="m-id">
                                <span class="m-name"><?= $e($pName) ?><?= $pMe ? ' (you)' : '' ?></span>
                                <span class="m-handle">@<?= $e($p['username']) ?></span>
                            </span>
                            <?php if ($pOwner): ?>
                                <span class="m-role">Owner</span>
                            <?php elseif ($pLeft): ?>
                                <span class="m-role left">Left</span>
                            <?php endif; ?>
                            <?php if ($canManage): ?>
                                <span class="dm-member-tools">
                                    <form class="inline" method="post" action="/messages/<?= $railId ?>/transfer">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                        <button class="dm-linkbtn" type="submit">Make owner</button>
                                    </form>
                                    <form class="inline" method="post" action="/messages/<?= $railId ?>/members/remove">
                                        <?= $this->csrfField() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $p['user_id'] ?>">
                                        <button class="dm-linkbtn danger" type="submit">Remove</button>
                                    </form>
                                </span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <?php if (!empty($is_owner)): ?>
                <div class="dm-rail-sec">
                    <h3>Owner tools</h3>
                    <form class="dm-owner-tool" method="post" action="/messages/<?= $railId ?>/members">
                        <?= $this->csrfField() ?>
                        <input class="input input-small" type="text" name="username" maxlength="32" placeholder="username" aria-label="Add member" required>
                        <button class="btn btn-small" type="submit">Add</button>
                    </form>
                    <form class="dm-owner-tool" method="post" action="/messages/<?= $railId ?>/rename">
                        <?= $this->csrfField() ?>
                        <input class="input input-small" type="text" name="title" maxlength="120" value="<?= $e($conversation['title'] ?? '') ?>" aria-label="Rename group" required>
                        <button class="btn btn-small" type="submit">Rename</button>
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
                    <form class="dm-rail-form" method="post" action="/messages/<?= $railId ?>/members/remove">
                        <?= $this->csrfField() ?>
                        <input type="hidden" name="user_id" value="<?= $current_user !== null ? (int) $current_user->id() : 0 ?>">
                        <button class="dm-rail-btn danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'log-out']) ?><span>Leave group</span></button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <?php
            $railOtherName = $other === null ? 'Unknown' : (($other['display_name'] ?? '') !== '' ? $other['display_name'] : $other['username']);
            $railOtherUser = (string) ($other['username'] ?? '');
            $railRole = ['user' => 'Member', 'moderator' => 'Moderator', 'admin' => 'Admin'][(string) ($other['role'] ?? 'user')] ?? 'Member';
            ?>
            <div class="dm-rail-id">
                <?= $this->partial('partials/monogram', ['name' => $railOtherName, 'username' => $railOtherUser !== '' ? $railOtherUser : $railOtherName, 'gilt' => true]) ?>
                <h2 class="dm-rail-name"><?= $e($railOtherName) ?></h2>
                <?php if ($railOtherUser !== ''): ?><span class="dm-rail-handle">@<?= $e($railOtherUser) ?></span><?php endif; ?>
                <span class="dm-tier-pill"><?= $e($railRole) ?></span>
            </div>

            <div class="dm-rail-sec">
                <h3>About</h3>
                <ul class="dm-rail-meta">
                    <?php if ($other !== null && ($other['created_at'] ?? '') !== ''): ?>
                        <li><span class="k">Joined</span><span class="v"><?= $e(human_datetime($other['created_at'])) ?></span></li>
                    <?php endif; ?>
                    <?php if ($other !== null && isset($other['reputation'])): ?>
                        <li><span class="k">Reputation</span><span class="v"><?= (int) $other['reputation'] ?></span></li>
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
                        <form class="dm-rail-form" method="post" action="/u/<?= $e($railOtherUser) ?>/block">
                            <?= $this->csrfField() ?>
                            <input type="hidden" name="return" value="/messages/<?= $railId ?>">
                            <button class="dm-rail-btn danger" type="submit"><?= $this->partial('partials/icon', ['name' => 'ban']) ?><span><?= !empty($other_is_blocked) ? 'Unblock ' : 'Block ' ?><?= $e($railOtherName) ?></span></button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</aside>
