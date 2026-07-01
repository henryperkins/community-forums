/* Messages kit — app shell. Two-pane reading room: the conversation list beside
   the open letter (or the new-message composer). Holds open/read/reply state. */
(function () {
  function clone(x) { return JSON.parse(JSON.stringify(x)); }

  function Empty() {
    const { EightPointStar } = window.ImladrisDesignSystem_c3e027;
    return (
      <section className="dm-threadpane">
        <div className="dm-empty">
          <div className="dm-empty-inner">
            <span className="star"><EightPointStar size={54} /></span>
            <h2>Choose a letter to read</h2>
            <p>Your private counsel opens here, beside the list. Pick a conversation, or begin a new message.</p>
          </div>
        </div>
      </section>
    );
  }

  function DMApp() {
    const Topbar = window.DMTopbar;
    const ConvoList = window.DMConvoList;
    const Thread = window.DMThread;
    const Compose = window.DMCompose;
    const RBDM = window.RBDM;

    const [convos, setConvos] = React.useState(() => RBDM.conversations.map(clone));
    const [activeId, setActiveId] = React.useState(RBDM.conversations[0].id);
    const [mode, setMode] = React.useState('thread');   // thread | compose
    const [filter, setFilter] = React.useState('All');
    const [reply, setReply] = React.useState('');
    const [reading, setReading] = React.useState(false); // mobile single-pane

    // Mark the first conversation read on first paint.
    React.useEffect(() => {
      setConvos((prev) => prev.map((c) => c.id === RBDM.conversations[0].id ? { ...c, unread: false } : c));
    }, []);

    const active = convos.find((c) => c.id === activeId) || null;

    function open(id) {
      setActiveId(id); setMode('thread'); setReply(''); setReading(true);
      setConvos((prev) => prev.map((c) => c.id === id ? { ...c, unread: false } : c));
    }
    function send() {
      const body = reply.trim();
      if (!body || !active) return;
      const msg = { id: Date.now(), from: RBDM.me, time: 'just now', body };
      setConvos((prev) => prev.map((c) => c.id === active.id
        ? { ...c, messages: [...c.messages, msg], preview: body }
        : c));
      setReply('');
    }

    let right;
    if (mode === 'compose') right = <Compose onBack={() => setMode('thread')} onSend={() => setMode('thread')} />;
    else if (active) right = <Thread convo={active} onBack={() => setReading(false)} replyValue={reply} onReplyChange={setReply} onSend={send} />;
    else right = <Empty />;

    return (
      <div className="app-root">
        <Topbar />
        <div className={'dm-shell' + (reading ? ' reading' : '')}>
          <ConvoList
            conversations={convos} activeId={mode === 'thread' ? activeId : null}
            onOpen={open} onNew={() => { setMode('compose'); setReading(true); }}
            filter={filter} onFilter={setFilter}
          />
          {right}
        </div>
      </div>
    );
  }

  window.DMApp = DMApp;
})();
