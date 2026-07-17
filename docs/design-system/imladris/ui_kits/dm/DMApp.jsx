/* Messages kit — app shell. ONE reading room: the conversation list, the open
   conversation, and a collapsible details rail. New message is a dialog OVER
   the room (never a co-equal screen); confirms (leave / block / report) reuse
   the same dialog; a small toast acknowledges quiet actions. Holds all state. */
(function () {
  function clone(x) { return JSON.parse(JSON.stringify(x)); }
  const isMobile = () => !!(window.matchMedia && window.matchMedia('(max-width: 900px)').matches);

  function Empty({ onNew }) {
    const { EightPointStar, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <section className="dm-threadpane">
        <div className="dm-empty">
          <div className="dm-empty-inner">
            <span className="star"><EightPointStar size={54} /></span>
            <h2>Choose a letter to read</h2>
            <p>Your private counsel opens here, beside the list. Pick a conversation, or begin a new one.</p>
            <Button onClick={onNew}>New message</Button>
          </div>
        </div>
      </section>
    );
  }

  function DMApp() {
    const Topbar = window.DMTopbar;
    const NavRail = window.DMNavRail;
    const ConvoList = window.DMConvoList;
    const Thread = window.DMThread;
    const InfoRail = window.DMInfoRail;
    const Modal = window.DMModal;
    const ComposeForm = window.DMComposeForm;
    const ConfirmBody = window.DMConfirmBody;
    const RBDM = window.RBDM;

    const [convos, setConvos] = React.useState(() => RBDM.conversations.map(clone));
    const [activeId, setActiveId] = React.useState(RBDM.conversations[0].id);
    const [filter, setFilter] = React.useState('All');
    const [query, setQuery] = React.useState('');
    const [reply, setReply] = React.useState('');
    const [railOpen, setRailOpen] = React.useState(false);      // details rail — opens on demand (nav rail now grounds the view)
    const [railMobile, setRailMobile] = React.useState(false);  // mobile overlay
    const [reading, setReading] = React.useState(false);        // mobile single-pane
    const [overlay, setOverlay] = React.useState(null);
    const [toast, setToast] = React.useState(null);
    const toastTimer = React.useRef(null);

    // Mark the first conversation read on first paint.
    React.useEffect(() => {
      setConvos((prev) => prev.map((c) => c.id === RBDM.conversations[0].id ? { ...c, unread: false } : c));
    }, []);

    const active = convos.find((c) => c.id === activeId) || null;

    function showToast(msg) {
      setToast(msg);
      if (toastTimer.current) clearTimeout(toastTimer.current);
      toastTimer.current = setTimeout(() => setToast(null), 2600);
    }
    function open(id) {
      setActiveId(id); setReply(''); setReading(true); setRailMobile(false);
      setConvos((prev) => prev.map((c) => c.id === id ? { ...c, unread: false } : c));
    }
    function send() {
      const body = reply.trim();
      if (!body || !active) return;
      const msg = { id: Date.now(), from: RBDM.me, time: 'just now', body };
      setConvos((prev) => prev.map((c) => c.id === active.id
        ? { ...c, messages: [...c.messages, msg], preview: body, read: false, time: 'just now' }
        : c));
      setReply('');
    }
    function updateActive(fn) {
      setConvos((prev) => prev.map((c) => c.id === activeId ? fn(c) : c));
    }
    function leaveConvo(id) {
      setConvos((prev) => {
        const rest = prev.filter((c) => c.id !== id);
        if (id === activeId) { setActiveId(rest[0] ? rest[0].id : null); setReading(false); }
        return rest;
      });
      setRailMobile(false);
      showToast('You left the conversation.');
    }
    function toggleRail() { if (isMobile()) setRailMobile((v) => !v); else setRailOpen((v) => !v); }
    function openRail() { if (isMobile()) setRailMobile(true); else setRailOpen(true); }
    function closeRail() { setRailMobile(false); if (!isMobile()) setRailOpen(false); }
    const confirm = (spec) => setOverlay({ type: 'confirm', ...spec });

    function startConversation({ to, title, body }) {
      const names = to.split(',').map((s) => s.trim().replace(/^@/, '')).filter(Boolean);
      const id = Date.now();
      const first = { id: id + 1, from: RBDM.me, time: 'just now', body: body.trim() };
      let convo;
      if (names.length > 1) {
        convo = {
          id, kind: 'group', title: (title || '').trim() || names.join(', '), unread: false, time: 'just now', read: false,
          members: [{ username: RBDM.me, role: 'owner' }, ...names.map((n) => ({ username: n, role: 'member' }))],
          preview: body.trim(), messages: [first],
        };
      } else {
        convo = { id, kind: 'direct', other: names[0] || 'someone', unread: false, time: 'just now', read: false, preview: body.trim(), messages: [first] };
      }
      setConvos((prev) => [convo, ...prev]);
      setActiveId(id); setReading(true); setRailMobile(false); setOverlay(null);
      showToast('Your counsel has been sent.');
    }

    const railShown = !!active && (railOpen || railMobile);
    const shellClass = 'dm-shell'
      + (railOpen && active ? ' has-rail' : '')
      + (railMobile && active ? ' rail-open' : '')
      + (reading ? ' reading' : '');

    return (
      <div className="app-root">
        <Topbar />
        <div className={shellClass}>
          <NavRail onNewMessage={() => setOverlay({ type: 'compose' })} />
          <ConvoList
            conversations={convos} activeId={activeId} onOpen={open}
            onNew={() => setOverlay({ type: 'compose' })}
            filter={filter} onFilter={setFilter} query={query} onQuery={setQuery} />

          {active ? (
            <Thread
              convo={active} onBack={() => setReading(false)}
              railOpen={railOpen || railMobile} onToggleRail={toggleRail} onOpenRail={openRail}
              onUpdateConvo={updateActive} onConfirm={confirm} onLeaveConvo={leaveConvo} onToast={showToast}
              replyValue={reply} onReplyChange={setReply} onSend={send} />
          ) : (
            <Empty onNew={() => setOverlay({ type: 'compose' })} />
          )}

          {railShown ? (
            <InfoRail convo={active} onClose={closeRail} onUpdateConvo={updateActive}
              onConfirm={confirm} onLeaveConvo={leaveConvo} onToast={showToast} />
          ) : null}
        </div>

        {overlay && overlay.type === 'compose' ? (
          <Modal onClose={() => setOverlay(null)}>
            <ComposeForm onClose={() => setOverlay(null)} onSend={startConversation} />
          </Modal>
        ) : null}
        {overlay && overlay.type === 'confirm' ? (
          <Modal onClose={() => setOverlay(null)}>
            <ConfirmBody {...overlay} onClose={() => setOverlay(null)} />
          </Modal>
        ) : null}

        {toast ? <div className="dm-toast" role="status">{toast}</div> : null}

        {!reading ? (
          <nav className="dm-tabbar" aria-label="Primary">
            <a className="dm-tab" href="../retroboards/index.html">
              <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>Home
            </a>
            <button type="button" className="dm-tab">
              <svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/></svg>Inbox
            </button>
            <button type="button" className="dm-tab dm-tab-fab" onClick={() => setOverlay({ type: 'compose' })} aria-label="New message">
              <span><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
            </button>
            <button type="button" className="dm-tab is-active" aria-current="page">
              <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>Messages
            </button>
            <button type="button" className="dm-tab">
              <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4 21v-1a6 6 0 0 1 12 0v1"/></svg>You
            </button>
          </nav>
        ) : null}
      </div>
    );
  }

  window.DMApp = DMApp;
})();
