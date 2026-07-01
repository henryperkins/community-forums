/* RetroBoards — top bar. Brand mark + search + bell + identity (guest vs member). */
(function () {
  function Topbar({ user, onBrand, onProfile, onLogout, onToggleAuth }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Input, Pill, Button } = DS;
    return (
      <header className="topbar">
        <div className="topbar-inner">
          <span className="brand" onClick={onBrand}>
            <EightPointStar size={26} />
            <span className="brand-name">RetroBoards</span>
          </span>

          <form className="topbar-search" onSubmit={(e) => e.preventDefault()} role="search">
            <Input pill type="search" placeholder="Search the council…" aria-label="Search the council" />
          </form>

          <div className="topbar-right">
            {user ? (
              <>
                <span className="bell" title="Notifications">
                  <svg className="bell-ic" viewBox="0 0 24 24" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                  <span className="bell-dot" aria-hidden="true" />
                </span>
                <span className="topbar-user" onClick={onProfile}>
                  <DS.Monogram name={user.name} username={user.username} size="sm" presence="online" />
                  <span className="topbar-name">{user.name}</span>
                </span>
                <svg className="topbar-ic" viewBox="0 0 24 24" aria-hidden="true" title="Settings"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H2a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 3.6 8a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H8a1.65 1.65 0 0 0 1-1.51V2a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V8a1.65 1.65 0 0 0 1.51 1H22a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                <button className="topbar-logout" onClick={onLogout}>Log out</button>
              </>
            ) : (
              <>
                <Pill>Guest</Pill>
                <button className="topbar-logout" onClick={onToggleAuth}>Log in</button>
                <Button size="sm" onClick={onToggleAuth}>Sign up</Button>
              </>
            )}
          </div>
        </div>
      </header>
    );
  }

  window.RBTopbar = Topbar;
})();
