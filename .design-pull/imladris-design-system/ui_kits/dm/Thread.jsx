/* Messages kit — the open conversation (right pane). Header (direct / group),
   the group-members panel with owner tools, the message stream with reference
   cards + report affordance, and the pinned composer. */
(function () {
  const chev = <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>;
  const label = (code) => code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' ');

  function Thread({ convo, onBack, replyValue, onReplyChange, onSend }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Composer, Monogram, Input, Button } = DS;
    const RBDM = window.RBDM;
    const scrollRef = React.useRef(null);
    React.useEffect(() => {
      const el = scrollRef.current;
      if (!el) return;
      const pin = () => { el.scrollTop = el.scrollHeight; };
      pin();
      requestAnimationFrame(pin);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(pin);
      const t = setTimeout(pin, 250);
      return () => clearTimeout(t);
    }, [convo.id, convo.messages.length]);
    const isGroup = convo.kind === 'group';
    const active = isGroup ? convo.members.filter((m) => !m.left) : [];
    const isOwner = isGroup && (convo.members.find((m) => m.role === 'owner') || {}).username === RBDM.me;
    const other = isGroup ? null : RBDM.users[convo.other];
    const title = isGroup ? convo.title : other.name;
    const seed = isGroup ? ('group-' + convo.id) : convo.other;

    return (
      <section className="dm-threadpane">
        <header className="dm-thread-head">
          <button className="breadcrumb" onClick={onBack}>{chev} Messages</button>
          <div className="dm-thread-title-row">
            <div className="dm-thread-id">
              <Monogram name={title} username={seed} size="lg" gilt presence={other ? other.presence : undefined} />
              <div>
                <h1 className="dm-thread-title">{isGroup ? title : <a href="#" onClick={(e) => e.preventDefault()}>{title}</a>}</h1>
                <p className="dm-thread-sub">
                  {isGroup ? (active.length + ' active members') : ('@' + other.username + ' · ' + other.presence)}
                </p>
              </div>
            </div>
            {isGroup ? (
              <div className="dm-thread-actions">
                <button className="dm-head-btn" type="button">
                  <svg viewBox="0 0 24 24"><path d="M11 5 6 9H2v6h4l5 4z" /><path d="M22 9l-6 6M16 9l6 6" /></svg> Mute
                </button>
                <button className="dm-head-btn danger" type="button">Leave</button>
              </div>
            ) : null}
          </div>
        </header>

        {isGroup ? (
          <section className="dm-group-panel">
            <h2>Members</h2>
            <ul className="dm-members">
              {convo.members.map((m) => (
                <li key={m.username} className={'dm-member' + (m.left ? ' is-left' : '')}>
                  <span className="handle">@{m.username}</span>
                  {m.role === 'owner' ? <span className="role">Owner</span> : null}
                  {m.left ? <span className="role">left</span> : null}
                  {isOwner && !m.left && m.role !== 'owner'
                    ? <><button className="linkbtn danger" type="button">Remove</button><button className="linkbtn" type="button">Make owner</button></>
                    : null}
                </li>
              ))}
            </ul>
            {isOwner ? (
              <div className="dm-owner-tools">
                <form className="dm-owner-tools" onSubmit={(e) => e.preventDefault()}>
                  <Input placeholder="username" maxLength={32} style={{ maxWidth: 150 }} />
                  <Button size="sm" variant="secondary">Add member</Button>
                </form>
                <form className="dm-owner-tools" onSubmit={(e) => e.preventDefault()}>
                  <Input defaultValue={convo.title} maxLength={120} style={{ maxWidth: 180 }} />
                  <Button size="sm" variant="secondary">Rename</Button>
                </form>
              </div>
            ) : null}
          </section>
        ) : null}

        <div className="dm-scroll" ref={scrollRef}>
          <span className="dm-day">Beginning of your counsel</span>
          {convo.messages.map((m) => {
            const mine = m.from === RBDM.me;
            const from = RBDM.users[m.from];
            return (
              <div key={m.id} className={'dm-message' + (mine ? ' dm-mine' : '')}>
                <div className="dm-message-head">
                  <span className="dm-author">{mine ? 'You' : from.name}</span>
                  <span className="post-time">{m.time}</span>
                </div>
                <div className="dm-bubble"><p>{m.body}</p></div>
                {m.refs ? (
                  <div className="reference-cards" aria-label="Referenced content">
                    {m.refs.map((r, i) => (
                      <a key={i} className="reference-card" href={r.url} onClick={(e) => e.preventDefault()}>
                        <span className="badge badge-muted">{r.type}</span>
                        <strong>{r.title}</strong>
                        {r.meta ? <span className="muted">{r.meta}</span> : null}
                      </a>
                    ))}
                  </div>
                ) : null}
                {!mine ? (
                  <details className="dm-report">
                    <summary>Report</summary>
                    <form className="dm-report-form" onSubmit={(e) => e.preventDefault()}>
                      <select className="input input-small" aria-label="Reason">
                        {RBDM.reportReasons.map((rc) => <option key={rc} value={rc}>{label(rc)}</option>)}
                      </select>
                      <Input placeholder="Details (optional)" maxLength={255} style={{ flex: 1, minWidth: 120 }} />
                      <Button size="sm" variant="danger">Report message</Button>
                    </form>
                  </details>
                ) : null}
              </div>
            );
          })}
        </div>

        <div className="dm-composer">
          <Composer toolbar={false} sendLabel="Send" placeholder="Write a message…"
            value={replyValue} onChange={(e) => onReplyChange(e.target.value)}
            count={(replyValue ? replyValue.length : 0) + ' / 5000'}
            onSubmit={(e) => { e.preventDefault(); onSend(); }} />
        </div>
      </section>
    );
  }
  window.DMThread = Thread;
})();
