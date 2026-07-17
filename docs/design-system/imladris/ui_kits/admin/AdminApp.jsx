/* Admin Console kit — app shell. Topbar + admin-head + horizontal subnav +
   section routing. Sections come from RBAdminSections (core) + RBAdminParity
   (the eight P5/runtime consoles). Users drills into a user record; the
   parity sections manage their own drill-ins. The reserved "Extensions" entry
   renders in its production disabled state (server_extensions is a Gate-B
   reserved-dark flag; the nav shows it disabled with a note). */
(function () {
  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram, Pill } = DS;
    const SECT = Object.assign({}, window.RBAdminSections, window.RBAdminParity);
    const UserRecord = window.RBAdminUserRecord;
    const a = window.RBAdmin.admin;

    /* Production nav order (templates/admin/_nav.php). */
    const ORDER = [
      'dashboard', 'features', 'threadIntelligence', 'structure', 'users',
      'branding', 'tags', 'badgeRules', 'email', 'announcements',
      'apiTokens', 'webhooks', 'packages', 'registries', 'themes', 'roles',
      'providers', 'invitations',
    ];
    const DISABLED = [{ key: 'extensions', label: 'Extensions', note: 'Disabled until the feature flag is enabled' }];

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
            {ORDER.map((k) => (
              <button key={k} className={k === active ? 'active' : ''} aria-current={k === active ? 'page' : undefined}
                onClick={() => { setActive(k); setUserId(null); }}>{SECT[k].label}</button>
            ))}
            {DISABLED.map((d) => (
              <span key={d.key} className="subnav-item is-disabled" aria-disabled="true" title={d.note}>
                <span className="subnav-item-label">{d.label}</span>
                <span className="subnav-item-note">{d.note}</span>
              </span>
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
