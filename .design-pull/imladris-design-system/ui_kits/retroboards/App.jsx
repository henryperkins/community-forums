/* RetroBoards — app shell. Routes between the Community Inbox, profile, and
   the leaderboard; holds auth (guest/member), star and reply state. */
(function () {
  const Topbar = window.RBTopbar;
  const Rail = window.RBRail;
  const Inbox = window.RBInbox;
  const Conversation = window.RBConversation;
  const Profile = window.RBProfile;
  const Leaderboard = window.RBLeaderboard;

  function clone(t) { return JSON.parse(JSON.stringify(t)); }

  function App() {
    const RB = window.RB;
    const [user, setUser] = React.useState(RB.users[RB.currentUserKey]);
    const [view, setView] = React.useState('inbox');     // inbox | profile | leaderboard
    const [scope, setScope] = React.useState('inbox');    // inbox | mentions | watching | drafts
    const [board, setBoard] = React.useState(null);
    const [threads, setThreads] = React.useState(() => RB.threads.map(clone));
    const [activeId, setActiveId] = React.useState(RB.threads[0].id);
    const [profileKey, setProfileKey] = React.useState(RB.currentUserKey);
    const [density, setDensity] = React.useState('Hall');
    const [filter, setFilter] = React.useState('All');
    const [sort, setSort] = React.useState('Active');
    const [starred, setStarred] = React.useState(() => new Set(RB.threads.filter((t) => t.starred).map((t) => t.id)));
    const [reply, setReply] = React.useState('');
    const [mobileReading, setMobileReading] = React.useState(false);

    // Derive the visible thread list.
    let list = threads.slice();
    if (board) list = list.filter((t) => t.board === board);
    if (scope === 'mentions') list = list.filter((t) => t.unread);
    else if (scope === 'watching') list = list.filter((t) => starred.has(t.id));
    else if (scope === 'drafts') list = [];
    if (filter === 'Unread') list = list.filter((t) => t.unread);
    else if (filter === 'Starred') list = list.filter((t) => starred.has(t.id));
    else if (filter === 'Mine') list = list.filter((t) => t.author === (user && user.username));
    if (sort === 'Newest') list = list.slice().sort((a, b) => b.id - a.id);
    else if (sort === 'Unanswered') list = list.filter((t) => t.replies === 0);

    // Reflect live star state onto the rows.
    list = list.map((t) => ({ ...t, starred: starred.has(t.id) }));

    const activeThread = threads.find((t) => t.id === activeId) || null;
    const shownThread = (view === 'inbox' && activeThread) ? { ...activeThread, starred: starred.has(activeThread.id) } : activeThread;

    function openThread(id) { setActiveId(id); setMobileReading(true); }
    function goInboxFilter(key) {
      if (key === 'top') { setView('leaderboard'); return; }
      setView('inbox'); setBoard(null); setScope(key); setMobileReading(false);
    }
    function goBoard(slug) {
      setView('inbox'); setScope('inbox'); setBoard(slug); setMobileReading(false);
      const first = threads.find((t) => t.board === slug);
      if (first) setActiveId(first.id);
    }
    function toggleStar(id) {
      setStarred((prev) => { const n = new Set(prev); n.has(id) ? n.delete(id) : n.add(id); return n; });
    }
    function sendReply() {
      const body = reply.trim();
      if (!body || !activeThread) return;
      setThreads((prev) => prev.map((t) => t.id === activeThread.id
        ? { ...t, replies: t.replies + 1, posts: [...t.posts, { author: user.username, time: 'just now', rep: String(user.rep), body, reactions: [] }] }
        : t));
      setReply('');
    }

    return (
      <div className="app-root">
        <Topbar
          user={user}
          onBrand={() => { setView('inbox'); setBoard(null); setScope('inbox'); }}
          onProfile={() => { setProfileKey(user ? user.username : RB.currentUserKey); setView('profile'); }}
          onLogout={() => setUser(null)}
          onToggleAuth={() => setUser(RB.users[RB.currentUserKey])}
        />

        {view === 'inbox' ? (
          <div className="app-shell" style={{ maxWidth: 'none' }}>
            <Rail view={scope} board={board} user={user} onFilter={goInboxFilter} onBoard={goBoard} />
            <div className="inbox-shell">
              <Inbox
                board={board} threads={list} density={density} onDensity={setDensity}
                filter={filter} onFilter={setFilter} sort={sort} onSort={setSort}
                activeId={activeId} onOpen={openThread} user={user}
                onNewTopic={() => {}} hiddenOnMobile={mobileReading}
              />
              <Conversation
                thread={shownThread} user={user}
                onBack={() => setMobileReading(false)}
                starred={shownThread ? starred.has(shownThread.id) : false}
                onStar={() => shownThread && toggleStar(shownThread.id)}
                replyValue={reply} onReplyChange={setReply} onSend={sendReply}
                isOpenMobile={mobileReading}
              />
            </div>
          </div>
        ) : (
          <div className="app-shell">
            <Rail view={view === 'leaderboard' ? 'top' : scope} board={board} user={user} onFilter={goInboxFilter} onBoard={goBoard} />
            <main style={{ minWidth: 0 }}>
              {view === 'profile'
                ? <Profile userKey={profileKey} onBack={() => { setView('inbox'); }} />
                : <Leaderboard onOpenProfile={(k) => { setProfileKey(k); setView('profile'); }} />}
            </main>
          </div>
        )}
      </div>
    );
  }

  window.RBApp = App;
})();
