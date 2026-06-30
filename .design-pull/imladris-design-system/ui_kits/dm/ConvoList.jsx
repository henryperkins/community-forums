/* Messages kit — conversation list (left pane). Direct + group rows with
   monogram, last-message preview, unread marker, and a "New message" action. */
(function () {
  function ConvoList({ conversations, activeId, onOpen, onNew, filter, onFilter }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Button, Tabs, Monogram } = DS;
    const RBDM = window.RBDM;

    const shown = conversations.filter((c) => filter === 'Unread' ? c.unread : true);

    return (
      <aside className="dm-listpane">
        <div className="dm-listpane-head">
          <div className="dm-listpane-top">
            <span>
              <span className="eyebrow">Private counsel</span>
              <h1>Messages</h1>
            </span>
            <Button size="sm" onClick={onNew}
              icon={<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M12 5v14M5 12h14" /></svg>}>
              New message
            </Button>
          </div>
          <div className="dm-listpane-filters">
            <Tabs variant="segment" items={['All', 'Unread']} value={filter} onChange={onFilter} />
          </div>
        </div>

        {shown.length === 0 ? (
          <p className="dm-list-empty">No conversations here yet.</p>
        ) : (
          <ul className="dm-list">
            {shown.map((c) => {
              const isGroup = c.kind === 'group';
              const other = isGroup ? c.title : RBDM.users[c.other].name;
              const seed = isGroup ? ('group-' + c.id) : c.other;
              const presence = isGroup ? undefined : RBDM.users[c.other].presence;
              const groupMeta = isGroup
                ? c.members.filter((m) => !m.left).map((m) => RBDM.users[m.username].name).join(', ')
                : null;
              return (
                <li key={c.id}>
                  <button type="button"
                    className={'dm-row' + (c.id === activeId ? ' active' : '') + (c.unread ? ' is-unread' : '')}
                    onClick={() => onOpen(c.id)}>
                    <Monogram name={other} username={seed} size="md" presence={presence} gilt={isGroup} />
                    <span className="dm-row-top">
                      {c.unread ? <span className="unread-dot" aria-label="Unread" /> : null}
                      <span className="dm-other">{other}</span>
                    </span>
                    <span className="dm-time">{c.time}</span>
                    <span className="dm-preview">{c.preview}</span>
                    {groupMeta ? <span className="dm-group-meta">{groupMeta}</span> : null}
                  </button>
                </li>
              );
            })}
          </ul>
        )}
      </aside>
    );
  }
  window.DMConvoList = ConvoList;
})();
