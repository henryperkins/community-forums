/* RetroBoards — sidebar rail. Quick filters, board categories, who's online. */
(function () {
  const ICON = {
    inbox: ['M22 12h-6l-2 3h-4l-2-3H2', 'M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z'],
    mentions: ['M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'],
    watching: ['M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z'],
    drafts: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6'],
    top: ['M8 21h8', 'M12 17v4', 'M7 4h10v4a5 5 0 0 1-10 0z', 'M5 4H3v2a3 3 0 0 0 3 3', 'M19 4h2v2a3 3 0 0 1-3 3'],
  };
  function Ic({ name }) {
    return (
      <svg className="rail-ic" viewBox="0 0 24 24" aria-hidden="true">
        {ICON[name].map((d, i) => <path key={i} d={d} />)}
        {name === 'mentions' ? <circle cx="12" cy="12" r="4" /> : null}
        {name === 'watching' ? <circle cx="12" cy="12" r="3" /> : null}
      </svg>
    );
  }

  function Rail({ view, board, onFilter, onBoard, user }) {
    const RB = window.RB;
    const filters = [
      { key: 'inbox', label: 'Inbox', count: 2 },
      { key: 'mentions', label: 'Mentions', count: 2 },
      { key: 'watching', label: 'Watching' },
      { key: 'drafts', label: 'Drafts' },
      { key: 'top', label: 'Top contributors' },
    ];
    return (
      <aside className="sidebar">
        {user ? (
          <ul className="rail-filters">
            {filters.map((f) => {
              const active = (f.key === 'top' && view === 'leaderboard')
                || (f.key === 'inbox' && view === 'inbox' && !board)
                || (view === f.key);
              return (
                <li key={f.key}>
                  <button className={'rail-filter' + (active ? ' active' : '')} onClick={() => onFilter(f.key)}>
                    <Ic name={f.key} />
                    <span>{f.label}</span>
                    {f.count ? <span className="rail-count">{f.count}</span> : null}
                  </button>
                </li>
              );
            })}
          </ul>
        ) : null}

        {RB.categories.map((cat) => (
          <div className="nav-cat" key={cat.name}>
            <span className="nav-cat-name">{cat.name}</span>
            <ul className="nav-boards">
              {cat.boards.map((b) => (
                <li key={b.slug}>
                  <button className={board === b.slug ? 'active' : ''} onClick={() => onBoard(b.slug)}>
                    <span className="hash">#</span><span>{b.name}</span>
                    <span className="rail-count-soft">{b.count}</span>
                  </button>
                </li>
              ))}
            </ul>
          </div>
        ))}

        <section className="presence-widget">
          <h2 className="presence-title">Online · 4</h2>
          <ul className="presence-list">
            {['galadriel', 'elrond', 'arwen', 'erestor'].map((u) => (
              <li key={u}><span className="dot" />{RB.users[u].name}</li>
            ))}
          </ul>
        </section>
      </aside>
    );
  }

  window.RBRail = Rail;
})();
