/* Moderation kit — app shell. Topbar + mod-head + horizontal subnav with live
   queue counts + section routing. Holds the triage state so claim/resolve/
   approve actions update the queues and the counts. */
(function () {
  function clone(x) { return JSON.parse(JSON.stringify(x)); }

  function ModApp() {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram } = DS;
    const SECT = window.RBModSections;
    const M = window.RBMod;
    const mod = M.moderator;

    const [active, setActive] = React.useState('reports');
    const [reports, setReports] = React.useState(() => clone(M.reports));
    const [approvals, setApprovals] = React.useState(() => clone(M.approvals));
    const [appeals, setAppeals] = React.useState(() => clone(M.appeals));

    // Reports: claim keeps the row; resolve/dismiss closes it.
    function actReport(id, kind) {
      setReports((prev) => prev.map((r) => r.id === id ? { ...r, done: kind } : r));
    }
    // Approvals: approve or reject removes the held item from the queue.
    function resolveApproval(type, id) {
      setApprovals((prev) => ({
        threads: type === 'thread' ? prev.threads.filter((x) => x.id !== id) : prev.threads,
        posts: type === 'post' ? prev.posts.filter((x) => x.id !== id) : prev.posts,
      }));
    }
    // Appeals: record an outcome + note; row stays, marked resolved.
    function resolveAppeal(id, outcome, note) {
      setAppeals((prev) => prev.map((a) => a.id === id ? { ...a, done: true, outcome, note } : a));
    }

    const openReports = reports.filter((r) => !r.done || r.done === 'claimed').length;
    const urgentReports = reports.some((r) => r.reason_code === 'harassment' && (!r.done || r.done === 'claimed'));
    const pendingApprovals = approvals.threads.length + approvals.posts.length;
    const openAppeals = appeals.filter((a) => !a.done).length;

    const NAV = [
      { key: 'reports', label: 'Reports', count: openReports, urgent: urgentReports },
      { key: 'approvals', label: 'Approval hold', count: pendingApprovals },
      { key: 'appeals', label: 'Appeals', count: openAppeals },
      { key: 'member', label: 'Member view', count: null },
    ];

    let pane;
    if (active === 'reports') pane = <SECT.Reports reports={reports} onAct={actReport} />;
    else if (active === 'approvals') pane = <SECT.Approvals approvals={approvals} onResolve={resolveApproval} />;
    else if (active === 'appeals') pane = <SECT.Appeals appeals={appeals} onResolve={resolveAppeal} />;
    else pane = <SECT.MemberAppeal />;

    return (
      <div className="app-root">
        <header className="topbar">
          <div className="topbar-inner">
            <a className="brand" href="../retroboards/index.html"><EightPointStar size={26} /><span className="brand-name">RetroBoards</span></a>
            <a className="topbar-back" href="../retroboards/index.html"><svg viewBox="0 0 24 24"><path d="M15 18l-6-6 6-6" /></svg> <span>Back to the inbox</span></a>
            <div className="topbar-right">
              <span className="topbar-user"><Monogram name={mod.name} username={mod.username} size="sm" presence="online" gilt /><span className="topbar-name">{mod.name}</span></span>
            </div>
          </div>
        </header>

        <div className="mod">
          <div className="mod-head">
            <span>
              <span className="eyebrow">The warden's table</span>
              <h1>Moderation</h1>
            </span>
            <span className="pill mod-pill">Moderator</span>
          </div>

          <nav className="mod-subnav" aria-label="Moderation queues">
            {NAV.map((n) => (
              <button key={n.key} className={n.key === active ? 'active' : ''} aria-current={n.key === active ? 'page' : undefined}
                onClick={() => setActive(n.key)}>
                {n.label}
                {n.count != null && n.count > 0 ? <span className={'mod-count' + (n.urgent ? ' is-urgent' : '')}>{n.count}</span> : null}
              </button>
            ))}
          </nav>

          <div className="mod-pane-wrap" key={active}>{pane}</div>
        </div>
      </div>
    );
  }

  window.RBModApp = ModApp;
})();
