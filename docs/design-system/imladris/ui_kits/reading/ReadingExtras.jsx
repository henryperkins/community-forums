/* Reading-surfaces kit — Notifications, Connections, and full-page Compose.
   Faithful to templates/notifications.php, profile/connections.php, compose.php. */
(function () {
  const RB = () => window.RB;
  const nameOf = (u) => (RB().users[u] ? RB().users[u].name : (u || 'Someone'));

  const NOTIF_ICON = {
    reply: ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    new_thread: ['M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z'],
    mention: ['M12 12m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M16 8v5a3 3 0 0 0 6 0v-1a10 10 0 1 0-4 8'],
    reaction: ['M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z'],
    follow: ['M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2', 'M9 11m-4 0a4 4 0 1 0 8 0a4 4 0 1 0 -8 0', 'M19 8v6', 'M22 11h-6'],
    badge: ['M12 15m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0', 'M8.2 13.9 7 22l5-3 5 3-1.2-8.1'],
    solved: ['M22 11.1V12a10 10 0 1 1-5.9-9.1', 'M22 4 12 14.01l-3-3'],
    dm: ['M4 4h16a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z', 'm22 6-10 7L2 6'],
    mod: ['M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z'],
    announcement: ['M3 11l18-5v12L3 14v-3z', 'M11.6 16.8a3 3 0 1 1-5.8-1.6'],
  };
  function verb(n) {
    const a = nameOf(n.actor);
    switch (n.type) {
      case 'reply': return a + ' replied';
      case 'new_thread': return a + ' started a thread';
      case 'new_post': return a + ' posted';
      case 'mention': return a + ' mentioned you';
      case 'reaction': return a + ' reacted to your post';
      case 'follow': return a + ' followed you';
      case 'badge': return 'You earned a badge';
      case 'solved': return 'Your answer was accepted';
      case 'dm': return a + ' sent you a message';
      case 'mod': return 'A moderator action affects you';
      case 'announcement': return 'Announcement';
      default: return 'Notification';
    }
  }

  /* ── Notifications ────────────────────────────────────────────────────── */
  function Notifications({ notifications, onOpen, onMarkAll, onClear }) {
    const unread = notifications.filter((n) => !n.isRead).length;
    return (
      <div className="read-pad notifications-view">
        <header className="board-header">
          <h1>Notifications {unread > 0 ? <span className="badge">{unread} unread</span> : null}</h1>
          {notifications.length ? (
            <div className="notif-actions">
              <button className="linkbtn" type="button" onClick={onMarkAll}>Mark all read</button>
              <button className="linkbtn danger" type="button" onClick={onClear}>Clear all</button>
            </div>
          ) : null}
        </header>
        {notifications.length === 0 ? (
          <p className="muted empty">No notifications yet.</p>
        ) : (
          <ul className="notif-list">
            {notifications.map((n) => (
              <li key={n.id} className={'notif-row' + (n.isRead ? '' : ' notif-unread')}>
                <button className="notif-link" type="button" onClick={() => onOpen(n.id)}>
                  <span className="notif-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true">{(NOTIF_ICON[n.type] || NOTIF_ICON.reply).map((d, i) => <path key={i} d={d} />)}</svg>
                  </span>
                  <span className="notif-body">
                    <span className="notif-text">{verb(n)}</span>
                    {n.threadTitle ? <span className="notif-thread">— {n.threadTitle}</span> : null}
                  </span>
                  <span className="notif-time">{n.time}</span>
                  <span className={'notif-dot' + (n.isRead ? ' is-read' : '')} aria-hidden="true" />
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>
    );
  }

  /* ── Connections — followers / following ──────────────────────────────── */
  function Connections({ mode, onMode }) {
    const { Monogram } = window.ImladrisDesignSystem_c3e027;
    const conn = window.RBReading.connections;
    const [removed, setRemoved] = React.useState(() => new Set());
    const isFollowers = mode === 'followers';
    const people = (isFollowers ? conn.followers : conn.following).filter((u) => !removed.has(u));
    const profile = RB().users[conn.profile];

    return (
      <div className="read-pad connections">
        <header className="board-header">
          <h1>{isFollowers ? 'Followers' : 'Following'} <span className="muted">· <a href="#" onClick={(e) => e.preventDefault()}>@{profile.username}</a></span></h1>
        </header>
        <nav className="inbox-tabs conn-tabs" aria-label="Connections">
          <button className={'inbox-tab' + (isFollowers ? ' is-active' : '')} onClick={() => onMode('followers')}>Followers</button>
          <button className={'inbox-tab' + (!isFollowers ? ' is-active' : '')} onClick={() => onMode('following')}>Following</button>
        </nav>
        {people.length === 0 ? (
          <p className="muted empty">{isFollowers ? 'No followers yet.' : 'Not following anyone yet.'}</p>
        ) : (
          <ul className="people-list">
            {people.map((u) => {
              const p = RB().users[u];
              return (
                <li className="person-row" key={u}>
                  <Monogram name={p.name} username={p.username} />
                  <a className="person-name" href="#" onClick={(e) => e.preventDefault()}>{p.name}</a>
                  <span className="handle">@{p.username}</span>
                  <span className="person-rep">{RB().fmt(p.rep)} rep</span>
                  {isFollowers ? <button className="linkbtn danger" type="button" onClick={() => setRemoved((s) => new Set(s).add(u))}>Remove</button> : null}
                </li>
              );
            })}
          </ul>
        )}
      </div>
    );
  }

  /* ── Compose — full-page new topic ────────────────────────────────────── */
  function Compose({ onDone }) {
    const boards = RB().categories.flatMap((c) => c.boards);
    const [board, setBoard] = React.useState(boards[0].slug);
    const [title, setTitle] = React.useState('');
    const [body, setBody] = React.useState('');
    const [anon, setAnon] = React.useState(false);
    return (
      <div className="read-pad">
        <div className="card compose-page">
          <h1>New topic</h1>
          <form className="composer stacked" onSubmit={(e) => { e.preventDefault(); onDone(); }}>
            <p className="md-hint">Markdown supported — <strong>**bold**</strong>, <em>*italic*</em>, <code>`code`</code>, <code>||spoiler||</code>, and <code>![alt](image)</code> after uploading.</p>
            <label className="field">
              <span>Board</span>
              <select className="input" value={board} onChange={(e) => setBoard(e.target.value)}>
                {boards.map((b) => <option key={b.slug} value={b.slug}>#{b.name}</option>)}
              </select>
            </label>
            <label className="field">
              <span>Title</span>
              <input className="input" type="text" maxLength={160} value={title} onChange={(e) => setTitle(e.target.value)} required />
            </label>
            <label className="field">
              <span>Body</span>
              <textarea className="input composer-input" rows={8} maxLength={20000} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write your topic…" required />
            </label>
            <label className="checkline">
              <input type="checkbox" checked={anon} onChange={(e) => setAnon(e.target.checked)} />
              <span>Post anonymously <span className="muted">(only on boards that allow it; your name stays visible to moderators)</span></span>
            </label>
            <div className="form-actions">
              <button className="btn" type="submit" disabled={!title.trim() || !body.trim()}>Create topic</button>
              <button className="btn btn-ghost" type="button" onClick={onDone}>Cancel</button>
            </div>
          </form>
        </div>
      </div>
    );
  }

  window.RBReadingExtras = { Notifications, Connections, Compose };
})();
