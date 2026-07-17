/* Messages kit — conversation list (left pane). One tidy header (title + the
   single round "new message" invitation), a quiet search, an All / Unread
   filter, then the rows: monogram, name, one-line preview, a lone gold unread
   dot. No stacked sub-headers, no per-row boxes. */
(function () {
  const Icons = window.DMIcons;

  function ConvoList({ conversations, activeId, onOpen, onNew, filter, onFilter, query, onQuery }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram } = DS;
    const RBDM = window.RBDM;

    const U = (n) => RBDM.users[n] || { name: n, presence: undefined };
    const q = query.trim().toLowerCase();
    const shown = conversations.filter((c) => {
      if (filter === 'Unread' && !c.unread) return false;
      if (!q) return true;
      const name = c.kind === 'group' ? c.title : U(c.other).name;
      return (name + ' ' + c.preview).toLowerCase().includes(q);
    });
    const unreadCount = conversations.filter((c) => c.unread).length;

    return (
      <aside className="dm-listpane">
        <div className="dm-listpane-head">
          <div className="dm-listpane-top">
            <span>
              <span className="eyebrow">{Icons.Lock()}Private counsel</span>
              <h1>Messages</h1>
            </span>
            <button type="button" className="dm-new-btn" onClick={onNew} title="New message" aria-label="New message">
              {Icons.Plus()}
            </button>
          </div>

          <div className="dm-search">
            {Icons.Search()}
            <input type="search" value={query} onChange={(e) => onQuery(e.target.value)}
              placeholder="Search messages…" aria-label="Search messages" />
          </div>

          <div className="dm-filter">
            <div className="dm-chips" role="tablist" aria-label="Filter conversations">
              {['All', 'Unread'].map((f) => (
                <button key={f} type="button" role="tab" aria-selected={filter === f}
                  className={'dm-chip' + (filter === f ? ' is-active' : '')} onClick={() => onFilter(f)}>{f}</button>
              ))}
            </div>
            <span className="dm-count">{unreadCount ? unreadCount + ' unread' : 'All read'}</span>
          </div>
        </div>

        {shown.length === 0 ? (
          <p className="dm-list-empty">{q ? 'No letters match your search.' : 'No conversations here yet.'}</p>
        ) : (
          <ul className="dm-list">
            {shown.map((c) => {
              const isGroup = c.kind === 'group';
              const other = isGroup ? c.title : U(c.other).name;
              const seed = isGroup ? ('group-' + c.id) : c.other;
              const presence = isGroup ? undefined : U(c.other).presence;
              return (
                <li key={c.id}>
                  <button type="button"
                    className={'dm-row' + (c.id === activeId ? ' active' : '') + (c.unread ? ' is-unread' : '')}
                    onClick={() => onOpen(c.id)}>
                    <Monogram name={other} username={seed} size="md" presence={presence} gilt={isGroup} />
                    <span className="dm-row-top">
                      <span className="dm-other">{other}</span>
                    </span>
                    <span className="dm-time">{c.time}</span>
                    <span className="dm-preview">{c.preview}</span>
                    {c.unread ? <span className="dm-unread-dot" aria-label="Unread" /> : null}
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
