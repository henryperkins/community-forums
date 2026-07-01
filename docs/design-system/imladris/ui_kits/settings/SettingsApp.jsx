/* Settings kit — app shell. Slim topbar + sticky rail subnav + content pane. */
(function () {
  const ICON = {
    user: ['M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2', 'M12 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8z'],
    shield: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    eye: ['M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7z', 'M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z'],
    sun: ['M12 17a5 5 0 1 0 0-10 5 5 0 0 0 0 10z', 'M12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4'],
    book: ['M4 19.5A2.5 2.5 0 0 1 6.5 17H20', 'M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z'],
    pen: ['M12 19l7-7 3 3-7 7-3-3z', 'M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z', 'M2 2l7.586 7.586'],
    file: ['M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z', 'M14 2v6h6'],
    bell: ['M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9', 'M10.3 21a1.94 1.94 0 0 0 3.4 0'],
    link: ['M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71', 'M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71'],
    monitor: ['M20 3H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h16a1 1 0 0 0 1-1V4a1 1 0 0 0-1-1z', 'M8 21h8M12 17v4'],
    ban: ['M4.9 4.9l14.2 14.2', 'M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20z'],
    hash: ['M4 9h16M4 15h16M10 3L8 21M16 3l-2 18'],
    archive: ['M21 8v13H3V8', 'M1 3h22v5H1zM10 12h4'],
  };
  function Ic({ name }) {
    return <svg viewBox="0 0 24 24" aria-hidden="true">{(ICON[name] || []).map((d, i) => <path key={i} d={d} />)}</svg>;
  }

  function App() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram } = DS;
    const SECT = window.RBSettingsSections;
    const u = window.RBSettings.user;
    const keys = Object.keys(SECT);
    const [active, setActive] = React.useState('account');

    // Group the rail (preserve declaration order within each group).
    const groups = [];
    keys.forEach((k) => {
      const g = SECT[k].group;
      let bucket = groups.find((x) => x.name === g);
      if (!bucket) { bucket = { name: g, items: [] }; groups.push(bucket); }
      bucket.items.push(k);
    });

    const Section = SECT[active].render;

    return (
      <div className="app-root">
        <header className="topbar">
          <div className="topbar-inner">
            <a className="brand" href="../retroboards/index.html"><EightPointStar size={26} /><span className="brand-name">RetroBoards</span></a>
            <a className="topbar-back" href="../retroboards/index.html"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg> Back to the inbox</a>
            <div className="topbar-right">
              <span className="topbar-user"><Monogram name={u.name} username={u.username} size="sm" presence="online" /><span className="topbar-name">{u.name}</span></span>
              <button className="topbar-logout" type="button">Log out</button>
            </div>
          </div>
        </header>

        <div className="settings-screen">
          <div className="settings-head">
            <span className="eyebrow">Your seat at the council</span>
            <h1>Account settings</h1>
          </div>
          <div className="settings">
            <nav className="settings-rail" aria-label="Settings sections">
              {groups.map((g) => (
                <React.Fragment key={g.name}>
                  <span className="settings-rail-cat">{g.name}</span>
                  {g.items.map((k) => (
                    <button key={k} className={'rail-link' + (k === active ? ' active' : '')} aria-current={k === active ? 'page' : undefined} onClick={() => setActive(k)}>
                      <Ic name={SECT[k].icon} />{SECT[k].label}
                    </button>
                  ))}
                </React.Fragment>
              ))}
            </nav>
            <div className="settings-pane" key={active}>
              <Section />
            </div>
          </div>
        </div>
      </div>
    );
  }

  window.RBSettingsApp = App;
})();
