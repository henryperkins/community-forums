/* Reading-surfaces kit — shell chrome (topbar + sidebar). Faithful to the real
   partials/topbar.php + partials/sidebar.php: brand · search · New topic · bell ·
   identity, and Home / Inbox / Messages / Following / Tags / Top + boards + presence.
   Routes this kit owns switch the main pane; the rest are real cross-kit links. */
(function () {
  const ic = (paths, extra) => (
    <svg className="rail-ic" viewBox="0 0 24 24" aria-hidden="true">
      {paths.map((d, i) => <path key={i} d={d} />)}
      {extra}
    </svg>
  );
  const ICON = {
    home: [['M3 11.5 12 4l9 7.5'], ['M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9']],
    inbox: [['M22 12h-6l-2 3h-4l-2-3H2'], ['M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z']],
    messages: [['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z']],
    following: [['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2'], ['M22 21v-2a4 4 0 0 0-3-3.87'], ['M16 3.13a4 4 0 0 1 0 7.75']],
    tags: [['M20.59 13.41 13.42 20.6a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z']],
    top: [['M8 21h8'], ['M12 17v4'], ['M7 4h10v4a5 5 0 0 1-10 0z'], ['M5 4H3v2a3 3 0 0 0 3 3'], ['M19 4h2v2a3 3 0 0 1-3 3']],
  };

  function Sidebar({ route, onRoute }) {
    const RB = window.RB;
    const filters = [
      { key: 'inbox',     label: 'Inbox',            icon: 'inbox',     href: '../retroboards/index.html' },
      { key: 'messages',  label: 'Messages',         icon: 'messages',  href: '../dm/index.html' },
      { key: 'feed',      label: 'Following',        icon: 'following' },
      { key: 'tags',      label: 'Tags',             icon: 'tags' },
      { key: 'top',       label: 'Top contributors', icon: 'top',       href: '../retroboards/index.html' },
    ];
    return (
      <aside className="sidebar" id="sidebar-nav">
        <button className={'sidebar-home' + (route === 'home' ? ' active' : '')} onClick={() => onRoute('home')}>
          {ic(ICON.home.flat())}<span>Home</span>
        </button>

        <nav className="rail-filters-nav" aria-label="Quick filters">
          <ul className="rail-filters">
            {filters.map((f) => (
              <li key={f.key}>
                {f.href ? (
                  <a className="rail-filter" href={f.href}>{ic(ICON[f.icon].flat())}<span>{f.label}</span></a>
                ) : (
                  <button className={'rail-filter' + (route === f.key ? ' active' : '')} onClick={() => onRoute(f.key)}>
                    {ic(ICON[f.icon].flat())}<span>{f.label}</span>
                  </button>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <nav aria-label="Boards">
          {RB.categories.map((cat) => (
            <div className="nav-cat" key={cat.name}>
              <span className="nav-cat-name">{cat.name}</span>
              <ul className="nav-boards">
                {cat.boards.map((b) => (
                  <li key={b.slug}>
                    <button onClick={() => onRoute('home')}>
                      <span className="hash">#</span><span>{b.name}</span>
                    </button>
                  </li>
                ))}
              </ul>
            </div>
          ))}
        </nav>

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

  function Topbar({ route, onRoute, unread, query }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram } = DS;
    const me = window.RB.users[window.RB.currentUserKey];
    const [q, setQ] = React.useState(query || '');
    React.useEffect(() => { setQ(query || ''); }, [query]);

    return (
      <header className="topbar">
        <div className="topbar-inner">
          <button className="nav-toggle" type="button" aria-label="Open navigation">
            <svg className="nav-toggle-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M3 6h18M3 12h18M3 18h18" /></svg>
          </button>
          <span className="brand" onClick={() => onRoute('home')}>
            <EightPointStar size={26} />
            <span className="brand-name">RetroBoards</span>
          </span>

          <form className="topbar-search" role="search" onSubmit={(e) => { e.preventDefault(); onRoute('search', { query: q }); }}>
            <input className="input input-pill" type="search" value={q} onChange={(e) => setQ(e.target.value)}
              placeholder="Search the council…" aria-label="Search the council" />
          </form>

          <div className="topbar-right">
            <button className="topbar-cta" type="button" onClick={() => onRoute('compose')}>
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14" /></svg>
              <span>New topic</span>
            </button>
            <button className="topbar-link bell" onClick={() => onRoute('notifications')} title="Notifications" aria-current={route === 'notifications' ? 'page' : undefined}>
              <svg className="bell-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
              {unread > 0 ? <span className="bell-count">{unread}</span> : null}
            </button>
            <button className="topbar-user" onClick={() => onRoute('connections', { mode: 'followers' })} title="Your connections">
              <Monogram name={me.name} username={me.username} size="sm" presence="online" />
              <span className="topbar-name">{me.name}</span>
            </button>
            <a className="topbar-link" href="../admin/index.html" title="Admin">Admin</a>
            <a className="topbar-link" href="../settings/index.html" title="Settings">
              <svg className="topbar-ic" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="3" /><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z" /></svg>
            </a>
            <button className="topbar-logout" type="button">Log out</button>
          </div>
        </div>
      </header>
    );
  }

  window.RBReadingChrome = { Sidebar, Topbar };
})();
