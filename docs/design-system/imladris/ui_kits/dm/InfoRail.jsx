/* Messages kit — details rail (right pane, collapsible). Everything that used
   to be scattered across the thread (the inline members card, mute / leave,
   owner tools, block / report) is re-homed here into one calm, titled column.
   Direct: the person (gilt monogram, tier, joined, presence) + quiet actions.
   Group: the members list with roles + owner tools, mute, leave. */
(function () {
  const { useState } = React;
  const Icons = window.DMIcons;

  function InfoRail({ convo, onClose, onUpdateConvo, onConfirm, onLeaveConvo, onToast }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram, Switch, Input, Button } = DS;
    const RBDM = window.RBDM;
    const isGroup = convo.kind === 'group';
    const muted = !!convo.muted;
    const u = (name) => RBDM.users[name] || { username: name, name: name, presence: 'offline', joined: '—', tier: 'Member' };
    const isOwner = isGroup && (convo.members.find((m) => m.role === 'owner') || {}).username === RBDM.me;

    const [newMember, setNewMember] = useState('');
    const [rename, setRename] = useState(convo.title || '');

    const toggleMute = () => onUpdateConvo((c) => ({ ...c, muted: !c.muted }));

    function addMember(e) {
      e.preventDefault();
      const name = newMember.trim().replace(/^@/, '');
      if (!name) return;
      if (convo.members.some((m) => m.username === name && !m.left)) { onToast('@' + name + ' is already in counsel.'); setNewMember(''); return; }
      onUpdateConvo((c) => ({ ...c, members: [...c.members.filter((m) => m.username !== name), { username: name, role: 'member' }] }));
      onToast('Added @' + name + ' to the counsel.');
      setNewMember('');
    }
    function doRename(e) {
      e.preventDefault();
      const t = rename.trim(); if (!t) return;
      onUpdateConvo((c) => ({ ...c, title: t }));
      onToast('Group renamed.');
    }
    const removeMember = (name) => onUpdateConvo((c) => ({ ...c, members: c.members.map((m) => m.username === name ? { ...m, left: true } : m) }));
    const makeOwner = (name) => onUpdateConvo((c) => ({ ...c, members: c.members.map((m) => (
      m.username === name ? { ...m, role: 'owner' } : (m.role === 'owner' ? { ...m, role: 'member' } : m)
    )) }));

    const other = isGroup ? null : u(convo.other);

    return (
      <aside className="dm-inforail">
        <div className="dm-rail-head">
          <span className="eyebrow">{isGroup ? 'Members & details' : 'Details'}</span>
          <button type="button" className="dm-iconbtn" onClick={onClose} aria-label="Close details">{Icons.Close()}</button>
        </div>

        <div className="dm-rail-body">
          {isGroup ? (
            <>
              <div className="dm-rail-id">
                <Monogram name={convo.title} username={'group-' + convo.id} size="xl" gilt />
                <h2 className="dm-rail-name">{convo.title}</h2>
                <span className="dm-rail-handle">{convo.members.filter((m) => !m.left).length} in counsel</span>
              </div>

              <div className="dm-rail-sec">
                <h3>Members</h3>
                <ul className="dm-members">
                  {convo.members.map((m) => {
                    const usr = u(m.username);
                    const meRow = m.username === RBDM.me;
                    const canManage = isOwner && !m.left && !meRow && m.role !== 'owner';
                    return (
                      <li key={m.username} className={'dm-member' + (m.left ? ' is-left' : '')}>
                        <Monogram name={usr.name} username={m.username} size="sm" presence={m.left ? undefined : usr.presence} />
                        <span className="m-id">
                          <span className="m-name">{usr.name}{meRow ? ' (you)' : ''}</span>
                          <span className="m-handle">@{m.username}</span>
                        </span>
                        {m.role === 'owner' ? <span className="m-role">Owner</span>
                          : m.left ? <span className="m-role left">Left</span> : null}
                        {canManage ? (
                          <span className="dm-member-tools">
                            <button type="button" className="dm-linkbtn" onClick={() => makeOwner(m.username)}>Make owner</button>
                            <button type="button" className="dm-linkbtn danger" onClick={() => removeMember(m.username)}>Remove</button>
                          </span>
                        ) : null}
                      </li>
                    );
                  })}
                </ul>
              </div>

              {isOwner ? (
                <div className="dm-rail-sec">
                  <h3>Owner tools</h3>
                  <form className="dm-owner-tool" onSubmit={addMember}>
                    <Input value={newMember} onChange={(e) => setNewMember(e.target.value)} placeholder="username" maxLength={32} aria-label="Add member" />
                    <Button size="sm" variant="secondary" type="submit">Add</Button>
                  </form>
                  <form className="dm-owner-tool" onSubmit={doRename}>
                    <Input value={rename} onChange={(e) => setRename(e.target.value)} maxLength={120} aria-label="Rename group" />
                    <Button size="sm" variant="secondary" type="submit">Rename</Button>
                  </form>
                </div>
              ) : null}

              <div className="dm-rail-sec">
                <h3>This conversation</h3>
                <div className="dm-rail-toggle"><Switch label={muted ? 'Muted' : 'Mute conversation'} checked={muted} onChange={toggleMute} /></div>
                <div className="dm-rail-actions">
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Leave ' + convo.title + '?',
                    body: 'You will stop receiving this counsel. An owner can add you again later.',
                    confirmLabel: 'Leave group', danger: true, onConfirm: () => onLeaveConvo(convo.id),
                  })}>{Icons.Leave()} Leave group</button>
                </div>
              </div>
            </>
          ) : (
            <>
              <div className="dm-rail-id">
                <Monogram name={other.name} username={other.username} size="xl" gilt presence={other.presence} />
                <h2 className="dm-rail-name">{other.name}</h2>
                <span className="dm-rail-handle">@{other.username}</span>
                <span className="dm-tier-pill">{other.tier}</span>
              </div>

              <div className="dm-rail-sec">
                <h3>About</h3>
                <ul className="dm-rail-meta">
                  <li><span className="k">Presence</span><span className="v">{other.presence}</span></li>
                  <li><span className="k">Joined</span><span className="v">{other.joined}</span></li>
                </ul>
              </div>

              <div className="dm-rail-sec">
                <h3>This conversation</h3>
                <div className="dm-rail-toggle"><Switch label={muted ? 'Muted' : 'Mute conversation'} checked={muted} onChange={toggleMute} /></div>
                <div className="dm-rail-actions">
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Block ' + other.name + '?',
                    body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
                    confirmLabel: 'Block', danger: true, onConfirm: () => onToast(other.name + ' is blocked.'),
                  })}>{Icons.Block()} Block {other.name}</button>
                  <button type="button" className="dm-rail-btn danger" onClick={() => onConfirm({
                    title: 'Report this conversation?',
                    body: 'The wardens will review the recent messages in this counsel.',
                    confirmLabel: 'Report', danger: true, onConfirm: () => onToast('Reported to the wardens.'),
                  })}>{Icons.Flag()} Report conversation</button>
                </div>
              </div>
            </>
          )}
        </div>
      </aside>
    );
  }
  window.DMInfoRail = InfoRail;
})();
