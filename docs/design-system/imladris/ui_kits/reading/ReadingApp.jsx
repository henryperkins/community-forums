/* Reading-surfaces kit — app shell. Topbar + sidebar chrome around a routed
   main pane: home · feed · search · tags · tag · notifications · compose ·
   connections. Notifications live here so the bell count stays in sync. */
(function () {
  function ReadingApp() {
    const { Sidebar, Topbar } = window.RBReadingChrome;
    const S = window.RBReadingSurfaces;
    const X = window.RBReadingExtras;

    const [route, setRoute] = React.useState('home');
    const [ctx, setCtx] = React.useState({});            // per-route params (tag slug, search query, conn mode)
    const [feedView, setFeedView] = React.useState('following');
    const [notifs, setNotifs] = React.useState(() => window.RBReading.notifications.map((n) => ({ ...n })));

    const unread = notifs.filter((n) => !n.isRead).length;

    function go(next, params) {
      setRoute(next);
      setCtx(params || {});
      if (next === 'connections' && (!params || !params.mode)) setCtx({ mode: 'followers' });
      window.scrollTo(0, 0);
    }

    let pane;
    if (route === 'home') pane = <S.Home onRoute={go} />;
    else if (route === 'feed') pane = <S.Feed view={feedView} onView={setFeedView} />;
    else if (route === 'search') pane = <S.Search query={ctx.query || ''} onSearch={(q) => go('search', { query: q })} />;
    else if (route === 'tags') pane = <S.Tags onRoute={go} />;
    else if (route === 'tag') pane = <S.TagShow ctx={ctx} onRoute={go} />;
    else if (route === 'notifications') pane = (
      <X.Notifications
        notifications={notifs}
        onOpen={(id) => setNotifs((p) => p.map((n) => n.id === id ? { ...n, isRead: true } : n))}
        onMarkAll={() => setNotifs((p) => p.map((n) => ({ ...n, isRead: true })))}
        onClear={() => setNotifs([])}
      />
    );
    else if (route === 'compose') pane = <X.Compose onDone={() => go('home')} />;
    else if (route === 'connections') pane = <X.Connections mode={ctx.mode || 'followers'} onMode={(m) => setCtx({ mode: m })} />;
    else pane = <S.Home onRoute={go} />;

    return (
      <div className="app-root">
        <Topbar route={route} onRoute={go} unread={unread} query={ctx.query || ''} />
        <div className="app-shell">
          <Sidebar route={route} onRoute={go} />
          <main className="main read-main" id="main">{pane}</main>
        </div>
      </div>
    );
  }

  window.RBReadingApp = ReadingApp;
})();
