/* Messages kit — the open conversation (centre pane). One header (identity +
   a details toggle + a single ··· overflow), the message stream as grouped
   "letters" (consecutive messages share an author line; theirs read plain,
   mine wear the one gold plate), a per-message hover ··· (copy / report), an
   inline report form, reference cards, a read receipt, and a calm composer.
   All secondary controls live in menus or the details rail — nothing shouts. */
(function () {
  const { useState, useRef, useEffect } = React;
  const Icons = window.DMIcons;
  const Menu = window.DMMenu;

  function groupRuns(messages) {
    const out = [];
    let cur = null;
    messages.forEach((m) => {
      if (cur && cur.from === m.from) cur.items.push(m);
      else { cur = { from: m.from, items: [m] }; out.push(cur); }
    });
    return out;
  }
  const label = (code) => code.charAt(0).toUpperCase() + code.slice(1).replace(/_/g, ' ');

  function Thread(props) {
    const { convo, onBack, railOpen, onToggleRail, onOpenRail, onUpdateConvo, onConfirm, onLeaveConvo, onToast, replyValue, onReplyChange, onSend } = props;
    const DS = window.ImladrisDesignSystem_c3e027;
    const { Monogram } = DS;
    const RBDM = window.RBDM;
    const me = RBDM.users[RBDM.me];
    const [reportingId, setReportingId] = useState(null);
    const scrollRef = useRef(null);
    const taRef = useRef(null);

    const U = (n) => RBDM.users[n] || { username: n, name: n, presence: 'offline' };
    const isGroup = convo.kind === 'group';
    const other = isGroup ? null : U(convo.other);
    const title = isGroup ? convo.title : other.name;
    const seed = isGroup ? ('group-' + convo.id) : convo.other;
    const active = isGroup ? convo.members.filter((m) => !m.left) : [];
    const isOwner = isGroup && (convo.members.find((m) => m.role === 'owner') || {}).username === RBDM.me;
    const muted = !!convo.muted;

    useEffect(() => { setReportingId(null); }, [convo.id]);

    // Pin to the newest letter.
    useEffect(() => {
      const el = scrollRef.current; if (!el) return;
      const pin = () => { el.scrollTop = el.scrollHeight; };
      pin(); requestAnimationFrame(pin);
      if (document.fonts && document.fonts.ready) document.fonts.ready.then(pin);
      const t = setTimeout(pin, 250);
      return () => clearTimeout(t);
    }, [convo.id, convo.messages.length]);

    // Auto-grow the composer.
    useEffect(() => {
      const el = taRef.current; if (!el) return;
      el.style.height = 'auto';
      el.style.height = Math.min(el.scrollHeight, 148) + 'px';
    }, [replyValue]);

    function copy(text) {
      try { navigator.clipboard && navigator.clipboard.writeText(text); } catch (e) { /* sandbox */ }
      onToast('Copied to clipboard.');
    }
    const toggleMute = () => onUpdateConvo((c) => ({ ...c, muted: !c.muted }));

    const menuItems = isGroup ? [
      { label: muted ? 'Unmute conversation' : 'Mute conversation', icon: Icons.Mute(), onClick: toggleMute },
      isOwner ? { sep: true } : null,
      isOwner ? { label: 'Rename group', icon: Icons.Rename(), onClick: onOpenRail } : null,
      isOwner ? { label: 'Add member', icon: Icons.AddUser(), onClick: onOpenRail } : null,
      { sep: true },
      { label: 'Leave group', icon: Icons.Leave(), danger: true, onClick: () => onConfirm({
          title: 'Leave ' + convo.title + '?',
          body: 'You will stop receiving this counsel. An owner can add you again later.',
          confirmLabel: 'Leave group', danger: true, onConfirm: () => onLeaveConvo(convo.id),
        }) },
    ] : [
      { label: muted ? 'Unmute conversation' : 'Mute conversation', icon: Icons.Mute(), onClick: toggleMute },
      { sep: true },
      { label: 'View profile', icon: Icons.User(), onClick: onOpenRail },
      { label: 'Block ' + other.name, icon: Icons.Block(), danger: true, onClick: () => onConfirm({
          title: 'Block ' + other.name + '?',
          body: other.name + ' will no longer be able to send you private counsel. You can undo this from settings.',
          confirmLabel: 'Block', danger: true, onConfirm: () => onToast(other.name + ' is blocked.'),
        }) },
      { label: 'Report conversation', icon: Icons.Flag(), danger: true, onClick: () => onConfirm({
          title: 'Report this conversation?',
          body: 'The wardens will review the recent messages in this counsel.',
          confirmLabel: 'Report', danger: true, onConfirm: () => onToast('Reported to the wardens.'),
        }) },
    ];

    const groups = groupRuns(convo.messages);
    const last = convo.messages[convo.messages.length - 1];
    const lastMine = last && last.from === RBDM.me;
    const receipt = lastMine ? (last.time === 'just now' ? 'Sent' : (convo.read ? 'Read' : 'Delivered')) : null;

    return (
      <section className="dm-threadpane">
        <header className="dm-thread-head">
          <button className="dm-back" onClick={onBack} aria-label="Back to messages">{Icons.Chevron()}</button>
          <div className="dm-thread-id">
            <Monogram name={title} username={seed} size="md" gilt presence={other ? other.presence : undefined} />
            <div>
              <div className="dm-thread-eyebrow">{Icons.Lock()}{isGroup ? 'Private group' : 'Private counsel'}</div>
              <h1 className="dm-thread-title">{title}</h1>
              <p className="dm-thread-sub">
                {isGroup ? (
                  <>{active.length} in counsel{muted ? ' · muted' : ''}</>
                ) : (
                  <>@{other.username} · {other.presence}{muted ? ' · muted' : ''}</>
                )}
              </p>
            </div>
          </div>
          <div className="dm-thread-actions">
            <button type="button" className={'dm-iconbtn' + (railOpen ? ' is-active' : '')}
              onClick={onToggleRail} title={isGroup ? 'Members & details' : 'Details'}
              aria-label={isGroup ? 'Members and details' : 'Details'} aria-pressed={railOpen}>
              {isGroup ? Icons.Users() : Icons.Panel()}
            </button>
            <Menu align="right"
              button={({ toggle, open }) => (
                <button type="button" className={'dm-iconbtn' + (open ? ' is-active' : '')} onClick={toggle} aria-label="More actions">{Icons.More()}</button>
              )}
              items={menuItems} />
          </div>
        </header>

        <div className="dm-scroll" ref={scrollRef}>
          <div className="dm-scroll-inner">
            <div className="dm-day"><span className="dm-day-label">{Icons.Lock()} Private — only those named here can read</span></div>
            {groups.map((g, gi) => {
              const mine = g.from === RBDM.me;
              const from = U(g.from);
              return (
                <div key={gi} className={'dm-group' + (mine ? ' mine' : '')}>
                  {!mine ? (
                    <span className="dm-mono-col"><Monogram name={from.name} username={from.username} size="sm" /></span>
                  ) : null}
                  <div className="dm-msgs">
                    <div className="dm-ghead">
                      <span className="dm-name">{mine ? 'You' : from.name}</span>
                      {isGroup && !mine && from.tier ? <span className="dm-rank">{from.tier}</span> : null}
                      <span className="dm-gtime">{g.items[0].time}</span>
                    </div>
                    {g.items.map((m) => (
                      <React.Fragment key={m.id}>
                        <div className="dm-line">
                          <div className="dm-body">
                            {m.quote ? (
                              <blockquote className="dm-quote">
                                <span className="dm-quote-who">{(RBDM.users[m.quote.from] || {}).name || m.quote.from}</span>
                                {m.quote.text}
                              </blockquote>
                            ) : null}
                            <p>{m.body}</p>
                          </div>
                          <span className="dm-line-menu">
                            <Menu align={mine ? 'left' : 'right'}
                              button={({ toggle }) => (
                                <button type="button" className="dm-dotbtn" onClick={toggle} aria-label="Message actions">{Icons.More()}</button>
                              )}
                              items={mine ? [
                                { label: 'Copy text', icon: Icons.Copy(), onClick: () => copy(m.body) },
                              ] : [
                                { label: 'Copy text', icon: Icons.Copy(), onClick: () => copy(m.body) },
                                { sep: true },
                                { label: 'Report message', icon: Icons.Flag(), danger: true, onClick: () => setReportingId(m.id) },
                              ]} />
                          </span>
                        </div>
                        {m.refs ? (
                          <div className="reference-cards" aria-label="Referenced content">
                            {m.refs.map((r, i) => (
                              <a key={i} className="reference-card" href={r.url} onClick={(e) => e.preventDefault()}>
                                <span className="ref-type">{r.type}</span>
                                <strong>{r.title}</strong>
                                {r.meta ? <span className="ref-meta">{r.meta}</span> : null}
                              </a>
                            ))}
                          </div>
                        ) : null}
                        {reportingId === m.id ? (
                          <form className="dm-report-form" onSubmit={(e) => { e.preventDefault(); setReportingId(null); onToast('Message reported to the wardens.'); }}>
                            <select className="input-small" aria-label="Reason">
                              {RBDM.reportReasons.map((rc) => <option key={rc} value={rc}>{label(rc)}</option>)}
                            </select>
                            <input className="input-small" style={{ flex: 1, minWidth: 120 }} placeholder="Details (optional)" maxLength={255} />
                            <DS.Button size="sm" variant="danger" type="submit">Report</DS.Button>
                            <DS.Button size="sm" variant="ghost" type="button" onClick={() => setReportingId(null)}>Cancel</DS.Button>
                          </form>
                        ) : null}
                      </React.Fragment>
                    ))}
                  </div>
                </div>
              );
            })}
            {receipt ? (
              <span className="dm-receipt">{receipt === 'Read' ? Icons.Check() : null}{receipt}</span>
            ) : null}
          </div>
        </div>

        <div className="dm-composer">
          <div className="dm-composer-inner">
            <div className="dm-composer-row">
              <Monogram name={me.name} username={me.username} size="sm" />
              <div className="dm-composer-main">
                <div className="dm-composer-field">
                  <textarea ref={taRef} rows={1} value={replyValue} maxLength={5000}
                    onChange={(e) => onReplyChange(e.target.value)}
                    onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onSend(); } }}
                    placeholder="Write your counsel…" aria-label="Write a message" />
                  <button type="button" className="dm-send" disabled={!replyValue.trim()} onClick={onSend} aria-label="Send">{Icons.Send()}</button>
                </div>
                <div className="dm-composer-meta">
                  <span className="dm-composer-hint">Enter to send · Shift + Enter for a new line</span>
                  <span className="dm-composer-count">{(replyValue ? replyValue.length : 0)} / 5000</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>
    );
  }
  window.DMThread = Thread;
})();
