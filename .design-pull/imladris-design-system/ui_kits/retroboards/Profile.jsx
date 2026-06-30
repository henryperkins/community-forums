/* RetroBoards — member profile (twilight identity cover). */
(function () {
  function Profile({ userKey, onBack }) {
    const DS = window.ImladrisDesignSystem_c3e027;
    const { EightPointStar, Monogram, Button, Tabs } = DS;
    const RB = window.RB;
    const u = RB.users[userKey] || RB.users.erestor;
    const [tab, setTab] = React.useState('Overview');
    const activity = [
      { ic: 'check', text: 'Answered ', link: 'Who changed what — and can you prove the rollback?', tail: ' in #audit-trails' },
      { ic: 'star', text: 'Earned the ', link: 'Trusted Answerer', tail: ' mark of esteem' },
      { ic: 'msg', text: 'Opened ', link: 'On exposing capability before we are asked', tail: ' in #capability-disclosure' },
    ];
    return (
      <div className="screen-pad">
        <div className="profile-screen">
          <button className="breadcrumb" onClick={onBack} style={{ marginBottom: 10 }}>
            <svg viewBox="0 0 24 24" width="13" height="13" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><path d="M15 18l-6-6 6-6" /></svg>
            Back to inbox
          </button>

          <div className="profile-cover">
            <span className="profile-cover-star" aria-hidden="true"><EightPointStar size={196} variant="watermark" style={{ opacity: 1, width: 196, height: 196 }} /></span>
            <span className="profile-avatar">
              <Monogram name={u.name} username={u.username} size="xl" gilt />
              <span className="presence-dot" aria-hidden="true" />
            </span>
            <div className="profile-id">
              <h1 className="profile-name">{u.name} <span className="profile-tier">{u.tier}</span></h1>
              <p className="profile-handle">@{u.username} · {u.title}</p>
              <p className="profile-meta">Joined Third Age, 2021 · Imladris</p>
              <dl className="profile-stats">
                <div><dt>Followers</dt><dd>418</dd></div>
                <div><dt>Following</dt><dd>112</dd></div>
                <div><dt>Posts</dt><dd>1,204</dd></div>
              </dl>
            </div>
            <div className="profile-aside">
              <div className="profile-rep">
                <span className="profile-rep-value"><span className="star-marker">✦</span>{u.rep.toLocaleString()}</span>
                <span className="profile-rep-label">Commends earned</span>
              </div>
              <div style={{ display: 'flex', gap: 8 }}>
                <Button variant="accent">Follow</Button>
                <Button variant="secondary">Message</Button>
              </div>
            </div>
          </div>

          <div className="profile-badges">
            <p className="profile-badges-label">Marks of esteem</p>
            <ul className="badge-row">
              {RB.badges.map((b) => (
                <li key={b.label} className={'badge-chip' + (b.locked ? ' is-locked' : '')}>
                  {b.locked
                    ? <span aria-hidden="true" style={{ display: 'inline-flex', color: 'var(--text-muted)' }}><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" /><path d="M7 11V7a5 5 0 0 1 10 0v4" /></svg></span>
                    : <span className="b-dot" aria-hidden="true" />}
                  {b.label}{b.locked ? ' · locked' : ''}
                </li>
              ))}
            </ul>
          </div>

          <Tabs variant="underline" items={['Overview', 'Threads', 'Posts', 'Commends']} value={tab} onChange={setTab} className="profile" />
          <ul style={{ listStyle: 'none', padding: 0, margin: 0 }}>
            {activity.map((a, i) => (
              <li key={i} style={{ display: 'flex', gap: 12, alignItems: 'flex-start', padding: '12px 0', borderTop: '1px solid var(--border-hair)' }}>
                <span style={{ flex: '0 0 auto', width: 30, height: 30, borderRadius: 8, background: 'var(--surface-sunken)', display: 'inline-flex', alignItems: 'center', justifyContent: 'center', color: 'var(--gold-ink)' }}>
                  {a.ic === 'star' ? <span style={{ color: 'var(--star)' }}>✦</span>
                    : <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">{a.ic === 'check' ? <path d="M20 6L9 17l-5-5" /> : <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z" />}</svg>}
                </span>
                <span style={{ color: 'var(--text-body)' }}>{a.text}<a href="#" onClick={(e) => e.preventDefault()} style={{ fontWeight: 600 }}>{a.link}</a>{a.tail}</span>
              </li>
            ))}
          </ul>
        </div>
      </div>
    );
  }

  window.RBProfile = Profile;
})();
