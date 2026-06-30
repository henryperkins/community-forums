/* Moderation kit — the four triage panes. Faithful to the mod/* templates,
   composed from design-system primitives. Each is a component fed queue state
   + handlers by the shell. */
(function () {
  const DS = () => window.ImladrisDesignSystem_c3e027;
  const check = <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>;

  /* ── Reports queue (mod/reports) ──────────────────────────────────────── */
  function Reports({ reports, onAct }) {
    const { Badge, Tag, Button } = DS();
    const M = window.RBMod;
    const live = reports.filter((r) => !r.done || r.done === 'claimed');
    return (
      <section className="mod-pane">
        <header className="board-header">
          <h1>Reports queue</h1>
          <p className="muted">Open and claimed reports in your scope. Claim one to take it off the shared pile, then resolve or dismiss.</p>
        </header>
        {reports.length === 0 ? (
          <p className="muted empty">No open reports. Nice and quiet.</p>
        ) : (
          <ul className="report-list">
            {reports.map((r) => {
              const urgent = r.reason_code === 'harassment';
              const claimed = r.done === 'claimed';
              const closed = r.done === 'resolved' || r.done === 'dismissed';
              const status = closed ? r.done : (claimed ? 'claimed' : r.status);
              return (
                <li key={r.id} className={'report-row' + (urgent && !closed ? ' is-urgent' : (r.status === 'open' && !closed ? ' is-open' : ''))}>
                  <div className="report-head">
                    <Badge variant={status === 'triaged' || claimed ? 'op' : 'muted'}>{status}</Badge>
                    {r.reason_code ? <Tag>{M.reasonLabels[r.reason_code] || r.reason_code}</Tag> : null}
                    <span className="muted">by {r.reporter_username} · {r.created_at}</span>
                  </div>

                  {r.post ? (
                    <p className="report-target"><a href="#" onClick={(e) => e.preventDefault()}>{r.post.thread_title}</a></p>
                  ) : (
                    <p className="report-target"><em>{r.dm.conversation_title} · message #{r.dm.message_id} from {r.dm.sender_display} (@{r.dm.sender_username})</em></p>
                  )}
                  <blockquote className="report-excerpt">{(r.post ? r.post.body : r.dm.body)}</blockquote>
                  {r.reason ? <p className="report-note">{r.reason}</p> : null}

                  <div className="report-actions">
                    {closed ? (
                      <span className="resolved-tag">{check} {r.done === 'resolved' ? 'Resolved' : 'Dismissed'}</span>
                    ) : (
                      <>
                        {!claimed ? <button className="linkbtn" onClick={() => onAct(r.id, 'claimed')}>Claim</button> : <span className="muted" style={{ fontFamily: 'var(--font-label)', fontSize: '.78rem' }}>Claimed by you</span>}
                        <button className="linkbtn" onClick={() => onAct(r.id, 'resolved')}>Resolve</button>
                        <button className="linkbtn" onClick={() => onAct(r.id, 'dismissed')}>Dismiss</button>
                      </>
                    )}
                  </div>
                </li>
              );
            })}
          </ul>
        )}
      </section>
    );
  }

  /* ── Approval hold (mod/approvals) ────────────────────────────────────── */
  function Approvals({ approvals, onResolve }) {
    const { Button } = DS();
    const t = approvals.threads, p = approvals.posts;
    return (
      <section className="mod-pane">
        <header className="board-header">
          <h1>Approval queue</h1>
          <p className="muted">Content held by anti-abuse rules or board approval. Approving publishes it and runs the normal counters and notifications; rejecting removes it.</p>
        </header>

        <div className="card">
          <h2>Topics awaiting approval</h2>
          {t.length === 0 ? <p className="muted">No topics are awaiting approval.</p> : (
            <ul className="approval-list">
              {t.map((x) => (
                <li key={x.id} className="approval-item">
                  <div className="approval-meta">
                    <strong>{x.title}</strong>
                    <span className="muted">by @{x.author_username} in #{x.board_slug} · {x.created_at} UTC</span>
                  </div>
                  <div className="approval-actions">
                    <Button size="sm" onClick={() => onResolve('thread', x.id)}>Approve</Button>
                    <Button size="sm" variant="secondary" onClick={() => onResolve('thread', x.id)}>Reject</Button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>

        <div className="card">
          <h2>Replies awaiting approval</h2>
          {p.length === 0 ? <p className="muted">No replies are awaiting approval.</p> : (
            <ul className="approval-list">
              {p.map((x) => (
                <li key={x.id} className="approval-item">
                  <div className="approval-meta">
                    <a href="#" onClick={(e) => e.preventDefault()}>{x.thread_title}</a>
                    <span className="muted">reply by @{x.author_username} in #{x.board_slug} · {x.created_at} UTC</span>
                    <p>{x.body}</p>
                  </div>
                  <div className="approval-actions">
                    <Button size="sm" onClick={() => onResolve('post', x.id)}>Approve</Button>
                    <Button size="sm" variant="secondary" onClick={() => onResolve('post', x.id)}>Reject</Button>
                  </div>
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>
    );
  }

  /* ── Appeals review (mod/appeals) ─────────────────────────────────────── */
  function Appeals({ appeals, onResolve }) {
    const { Badge, Textarea, Button } = DS();
    const M = window.RBMod;
    return (
      <section className="mod-pane">
        <header className="board-header">
          <h1>Appeals queue</h1>
          <p className="muted">Open appeals in your moderation scope. Record an outcome and a note; the appellant is notified.</p>
        </header>
        {appeals.length === 0 ? (
          <p className="muted empty">No open appeals.</p>
        ) : (
          <ul className="report-list">
            {appeals.map((a) => (
              <li key={a.id} className={'report-row' + (a.done ? '' : ' is-open')}>
                <div className="report-head">
                  <Badge variant={a.done ? 'muted' : 'op'}>{a.done ? a.outcome : a.status}</Badge>
                  <span className="muted">by {a.appellant_username} · {a.created_at}</span>
                </div>
                <p className="report-target">{a.target_type} #{a.target_id} · {a.original_action}</p>
                {a.target_summary ? <blockquote className="report-excerpt">{a.target_summary}</blockquote> : null}
                <p style={{ margin: '0 0 4px', lineHeight: 1.55 }}>{a.reason}</p>

                {a.done ? (
                  <p className="resolution-note"><strong>Resolution:</strong> {a.note || ('Marked ' + a.outcome + '.')}</p>
                ) : (
                  <AppealResolver outcomes={M.outcomes} onResolve={(outcome, note) => onResolve(a.id, outcome, note)} />
                )}
              </li>
            ))}
          </ul>
        )}
      </section>
    );
  }

  function AppealResolver({ outcomes, onResolve }) {
    const { Textarea, Button } = DS();
    const [outcome, setOutcome] = React.useState(outcomes[0]);
    const [note, setNote] = React.useState('');
    return (
      <form className="appeal-resolve" onSubmit={(e) => { e.preventDefault(); onResolve(outcome, note); }}>
        <label className="field">
          <span>Outcome</span>
          <select className="input" value={outcome} onChange={(e) => setOutcome(e.target.value)}>
            {outcomes.map((o) => <option key={o} value={o}>{o.charAt(0).toUpperCase() + o.slice(1)}</option>)}
          </select>
        </label>
        <label className="field">
          <span>Resolution note</span>
          <Textarea rows={2} value={note} onChange={(e) => setNote(e.target.value)} placeholder="What you decided, and why." />
        </label>
        <Button type="submit" size="sm">Resolve appeal</Button>
      </form>
    );
  }

  /* ── Member appeal view (appeals/index) — what the appellant sees ─────── */
  function MemberAppeal() {
    const { Badge, Textarea, Button } = DS();
    const M = window.RBMod;
    const e = M.myAppeals.eligible;
    const has = e.posts.length || e.logs.length;
    return (
      <section className="mod-pane">
        <header className="board-header">
          <h1>Appeals</h1>
          <p className="muted">The member's own view. Appeal forms appear beside each eligible moderation action; resolved appeals show the outcome and note.</p>
        </header>

        {has ? (
          <div className="card">
            <h2>Appealable actions</h2>
            <ul className="report-list" style={{ marginTop: 8 }}>
              {e.posts.map((post) => (
                <li key={'p' + post.id} className="report-row">
                  <div className="report-head">
                    <Badge variant="muted">post removed</Badge>
                    <span className="muted"><a href="#" onClick={(ev) => ev.preventDefault()} style={{ color: 'var(--brand)' }}>{post.thread_title}</a> · {post.deleted_at}</span>
                  </div>
                  <blockquote className="report-excerpt">{post.body}</blockquote>
                  <form className="appeal-form" onSubmit={(ev) => ev.preventDefault()}>
                    <label>Reason</label>
                    <Textarea rows={3} maxLength={2000} placeholder="Why this should be reconsidered." required />
                    <Button type="submit" size="sm">Submit appeal</Button>
                  </form>
                </li>
              ))}
              {e.logs.map((log) => (
                <li key={'l' + log.id} className="report-row">
                  <div className="report-head">
                    <Badge variant="muted">{log.action}</Badge>
                    <span className="muted">{log.created_at}</span>
                  </div>
                  {log.reason ? <blockquote className="report-excerpt">{log.reason}</blockquote> : null}
                  <form className="appeal-form" onSubmit={(ev) => ev.preventDefault()}>
                    <label>Reason</label>
                    <Textarea rows={3} maxLength={2000} placeholder="Why this should be reconsidered." required />
                    <Button type="submit" size="sm">Submit appeal</Button>
                  </form>
                </li>
              ))}
            </ul>
          </div>
        ) : null}

        <div className="card">
          <h2>Your appeals</h2>
          {M.myAppeals.submitted.length === 0 ? (
            <p className="muted">No appeals yet. Appeal forms appear next to eligible moderation actions.</p>
          ) : (
            <ul className="report-list" style={{ marginTop: 8 }}>
              {M.myAppeals.submitted.map((a) => (
                <li key={a.id} className="report-row">
                  <div className="report-head">
                    <Badge variant={a.status === 'reversed' || a.status === 'modified' ? 'op' : 'muted'}>{a.status}</Badge>
                    <span className="muted">{a.target_type} #{a.target_id} · {a.created_at}</span>
                  </div>
                  {a.target_summary ? <p className="report-target" style={{ fontSize: '.98rem' }}>{a.target_summary}</p> : null}
                  <blockquote className="report-excerpt">{a.reason}</blockquote>
                  {a.resolution_note ? <p className="resolution-note"><strong>Resolution:</strong> {a.resolution_note}</p> : null}
                </li>
              ))}
            </ul>
          )}
        </div>
      </section>
    );
  }

  window.RBModSections = { Reports, Approvals, Appeals, MemberAppeal };
})();
