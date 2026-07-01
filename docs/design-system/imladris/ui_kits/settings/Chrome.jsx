/* Settings kit — chrome: slim top bar + the lapidary settings subnav. */
(function () {
  function Topbar({ user, onBrand }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram } = DS;
    return (
      <header className="topbar">
        <div className="topbar-inner">
          <span className="brand" onClick={onBrand}>
            <EightPointStar size={26} />
            <span className="brand-name">RetroBoards</span>
          </span>
          <span className="topbar-spacer" />
          <span className="topbar-user">
            <Monogram name={user.name} username={user.username} size="sm" presence="online" />
            <span className="topbar-name">{user.name}</span>
          </span>
        </div>
      </header>
    );
  }

  // The settings subnav. Order + labels mirror partials/settings_nav.php.
  const NAV = [
    { key: 'account', label: 'Profile' },
    { key: 'security', label: 'Security' },
    { key: 'privacy', label: 'Privacy' },
    { key: 'appearance', label: 'Appearance' },
    { key: 'preferences', label: 'Reading' },
    { key: 'composing', label: 'Composing' },
    { key: 'drafts', label: 'Drafts' },
    { key: 'notifications', label: 'Notifications' },
    { key: 'connections', label: 'Connections' },
    { key: 'sessions', label: 'Sessions' },
    { key: 'blocks', label: 'Blocks' },
    { key: 'boards', label: 'Boards' },
    { key: 'lifecycle', label: 'Account' },
  ];

  function SettingsNav({ active, onNav }) {
    return (
      <nav className="subnav" aria-label="Settings sections">
        {NAV.map((it) => (
          <a key={it.key} className={active === it.key ? 'active' : ''} aria-current={active === it.key ? 'page' : undefined}
            onClick={(e) => { e.preventDefault(); onNav(it.key); }} href={'#' + it.key}>{it.label}</a>
        ))}
      </nav>
    );
  }

  window.SETTopbar = Topbar;
  window.SETNav = SettingsNav;
  window.SET_NAV = NAV;
})();
