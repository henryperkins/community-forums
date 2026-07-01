/* Admin Console kit — app shell. Topbar + admin-head + horizontal subnav +
   section routing (Users drills into a user record). */
(function () {
  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram, Pill } = DS;
    const SECT = window.RBAdminSections;
    const UserRecord = window.RBAdminUserRecord;
    const a = window.RBAdmin.admin;
    const keys = Object.keys(SECT);
    const [active, setActive] = React.useState('dashboard');
    const [userId, setUserId] = React.useState(null);

    const showingUser = active === 'users' && userId != null;
    const Section = SECT[active].render;

    return (
      <div className="app-root">
        <header className="topbar">
          <div className="topbar-inner">
            <a className="brand" href="../retroboards/index.html"><EightPointStar size={26} /><span className="brand-name">RetroBoards</span></a>
            <a className="topbar-back" href="../retroboards/index.html"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg> Back to the inbox</a>
            <div className="topbar-right">
              <span className="topbar-user"><Monogram name={a.name} username={a.username} size="sm" presence="online" gilt /><span className="topbar-name">{a.name}</span></span>
            </div>
          </div>
        </header>

        <div className="admin">
          <div className="admin-head">
            <span>
              <span className="eyebrow">Operator's desk</span>
              <h1>Admin console</h1>
            </span>
            <Pill tone="admin">Admin mode</Pill>
          </div>

          <nav className="admin-subnav" aria-label="Admin sections">
            {keys.map((k) => (
              <button key={k} className={k === active ? 'active' : ''} aria-current={k === active ? 'page' : undefined}
                onClick={() => { setActive(k); setUserId(null); }}>{SECT[k].label}</button>
            ))}
          </nav>

          <div className="admin-pane" key={active + (userId || '')}>
            {showingUser
              ? <UserRecord userId={userId} back={() => setUserId(null)} />
              : active === 'users'
                ? <Section openUser={(id) => setUserId(id)} />
                : <Section />}
          </div>
        </div>
      </div>
    );
  }

  window.RBAdminApp = App;
})();
