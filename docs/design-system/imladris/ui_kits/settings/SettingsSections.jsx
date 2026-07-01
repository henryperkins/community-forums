/* Settings kit — the section panes. Each is a faithful recreation of an
   account/*.php template, composed from design-system primitives and the
   lapidary forms register. window.RBSettingsSections maps key → component. */
(function () {
  const S = () => window.RBSettings;
  const DS = () => window.ImladrisDesignSystem_c3e027;

  const Star = ({ s = 11 }) => (
    <svg viewBox="0 0 100 100" width={s} height={s} aria-hidden="true" style={{ display: 'block' }}><path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" /></svg>
  );
  const swatch = (cls) => <span className={'theme-swatch ' + cls}><span className="sw-bg" /><span className="sw-card" /><span className="sw-accent" /></span>;

  /* ── Profile ──────────────────────────────────────────────────────────── */
  function Profile() {
    const { Input, Textarea, Button, Monogram } = DS();
    const u = S().user;
    return (
      <>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Avatar</span>
          <div className="avatar-row">
            <Monogram name={u.name} username={u.username} size="xl" gilt />
            <div className="avatar-actions">
              <Button variant="secondary" size="sm" className="file-btn" icon={<svg className="btn-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" /><path d="M17 8l-5-5-5 5" /><path d="M12 3v12" /></svg>}>Upload avatar</Button>
              <p className="avatar-hint">PNG, JP, GIF or WebP. A gilt ring marks members of high regard.</p>
              <button className="linkbtn muted" type="button">Remove avatar</button>
            </div>
          </div>
        </section>

        <section className="scribe-panel">
          <span className="scribe-panel-head">Identity</span>
          <div className="stacked" style={{ gap: 14, width: '100%' }}>
            <label className="field"><span>Email <span className="muted">(not editable in this version)</span></span><Input className="input-engraved" type="email" defaultValue={u.email} disabled /></label>
            <div className="field-grid" style={{ width: '100%' }}>
              <label className="field"><span>Display name</span><Input className="input-engraved" defaultValue={u.name} maxLength={64} /></label>
              <label className="field"><span>Pronouns</span><Input className="input-engraved" placeholder="they/them" maxLength={32} /></label>
              <label className="field"><span>Location</span><Input className="input-engraved" placeholder="Rivendell" maxLength={64} /></label>
              <label className="field"><span>Website</span><Input className="input-engraved" type="url" placeholder="https://example.com" /></label>
            </div>
            <label className="field"><span>Bio <span className="muted">(Markdown supported)</span></span><Textarea className="textarea-engraved" rows={4} defaultValue="Keeper of the Red Book; I read the machine and write down what it says." maxLength={1000} /></label>
            <label className="field"><span>Signature <span className="muted">(shown under your posts, max 3 lines)</span></span><Textarea className="textarea-engraved" rows={3} defaultValue="“The diff is small; the audit trail must be whole.”" maxLength={500} /></label>
          </div>
        </section>

        <section className="scribe-panel">
          <span className="scribe-panel-head">Custom profile fields</span>
          <p className="pane-intro">Add up to three public facts. Labels to 40 characters; values to 160.</p>
          {[['Allegiance', 'The Last Homely House'], ['Tongue', 'Sindarin, Quenya'], ['', '']].map((row, i) => (
            <div className="field-row" key={i} style={{ marginBottom: 9 }}>
              <span className="row-bullet" aria-hidden="true" />
              <input className="row-input" defaultValue={row[0]} placeholder="Label" style={{ flex: '0 0 32%' }} aria-label={'Custom label ' + (i + 1)} />
              <span style={{ color: 'var(--border-strong)' }}>·</span>
              <input className="row-input" defaultValue={row[1]} placeholder="Value" aria-label={'Custom value ' + (i + 1)} />
            </div>
          ))}
        </section>

        <Button>Save changes</Button>
      </>
    );
  }

  /* ── Security ─────────────────────────────────────────────────────────── */
  function Security() {
    const { Input, Button } = DS();
    const [setup, setSetup] = React.useState(false);
    const [enabled, setEnabled] = React.useState(false);
    return (
      <>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Password</span>
          <div className="stacked" style={{ width: '100%' }}>
            <label className="field"><span>Current password</span><Input className="input-engraved" type="password" autoComplete="current-password" /></label>
            <div className="field-grid" style={{ width: '100%' }}>
              <label className="field"><span>New password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
              <label className="field"><span>Confirm new password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
            </div>
            <Button>Change password</Button>
          </div>
        </section>

        <section className="scribe-panel">
          <span className="scribe-panel-head">Two-factor authentication</span>
          {enabled ? (
            <>
              <p className="pane-intro" style={{ marginBottom: 8 }}>Enabled · {S().recoveryCodes.length} recovery codes remaining. Keep these somewhere safe — each works once.</p>
              <h3>Recovery codes</h3>
              <ul className="code-list">{S().recoveryCodes.map((c) => <li key={c}><code>{c}</code></li>)}</ul>
              <div style={{ display: 'flex', gap: 10, marginTop: 16, flexWrap: 'wrap' }}>
                <Button variant="secondary" size="sm">Rotate recovery codes</Button>
                <Button variant="danger" size="sm" onClick={() => { setEnabled(false); setSetup(false); }}>Disable two-factor</Button>
              </div>
            </>
          ) : setup ? (
            <div className="totp-setup">
              <p className="muted" style={{ margin: 0 }}>Scan the cipher with your authenticator, then enter the 6-digit code it shows.</p>
              <div style={{ display: 'flex', gap: 15, alignItems: 'center', flexWrap: 'wrap' }}>
                <span className="qr-stub" aria-hidden="true"><svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" strokeWidth="1.5"><rect x="3" y="3" width="7" height="7" /><rect x="14" y="3" width="7" height="7" /><rect x="3" y="14" width="7" height="7" /><path d="M14 14h3v3h-3zM20 14v7M14 20h3" /></svg></span>
                <label className="field" style={{ flex: 1, minWidth: 180 }}><span>Authenticator secret</span><Input className="input-engraved" readOnly value="IMLA DRIS 7K2F 9QD4 H1PB" /></label>
              </div>
              <label className="field"><span>6-digit code</span><Input className="input-engraved" inputMode="numeric" autoComplete="one-time-code" placeholder="000000" style={{ maxWidth: 160 }} /></label>
              <div style={{ display: 'flex', gap: 10 }}>
                <Button onClick={() => setEnabled(true)}>Verify and enable</Button>
                <Button variant="ghost" onClick={() => setSetup(false)}>Cancel</Button>
              </div>
            </div>
          ) : (
            <>
              <p className="pane-intro">Not enabled. A second factor keeps your seat at the council secure even if your password is lost.</p>
              <Button onClick={() => setSetup(true)}>Start setup</Button>
            </>
          )}
        </section>
      </>
    );
  }

  /* ── Privacy ──────────────────────────────────────────────────────────── */
  function Privacy() {
    const { Button } = DS();
    return (
      <section className="scribe-panel">
        <span className="scribe-panel-head">Privacy</span>
        <div className="stacked" style={{ width: '100%' }}>
          <label className="field"><span>Profile visibility</span>
            <select className="input"><option>Public — anyone can view</option><option>Members only — signed-in members</option></select>
          </label>
          <label className="field"><span>Allow direct messages from</span>
            <select className="input"><option>Everyone</option><option defaultValue>Members</option><option>No one</option></select>
          </label>
          <div className="toggle-stack">
            <label className="gem-field"><input type="checkbox" className="gem-check gem-leaf" defaultChecked /><span>Show when I'm online<span className="gem-sub">A leaf marks your presence beside your name.</span></span></label>
            <label className="gem-field"><input type="checkbox" className="gem-check gem-gold" /><span>Hide me from leaderboards<span className="gem-sub">You still earn regard; you just won't be ranked publicly.</span></span></label>
            <label className="gem-field"><input type="checkbox" className="gem-check gem-river" defaultChecked /><span>Let others find me by email</span></label>
          </div>
          <Button>Save privacy settings</Button>
        </div>
      </section>
    );
  }

  /* ── Appearance ───────────────────────────────────────────────────────── */
  function Appearance() {
    const { ChoiceCard, Switch, Button } = DS();
    return (
      <>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Appearance</span>
          <div className="field"><span>Theme</span>
            <div className="choice-cards" style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: 10 }}>
              <ChoiceCard name="theme" title="Parchment" desc="Warm paper — daylight" swatch={swatch('swatch-parchment')} defaultChecked />
              <ChoiceCard name="theme" title="Twilight" desc="Evergreen night" swatch={swatch('swatch-twilight')} />
              <ChoiceCard name="theme" title="System" desc="Match your device" swatch={swatch('swatch-system')} />
            </div>
          </div>
          <div className="field" style={{ marginTop: 16 }}><span>Density</span>
            <div className="choice-cards" style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: 10 }}>
              <ChoiceCard name="density" title="Comfortable" desc="A card per topic — for reading" defaultChecked />
              <ChoiceCard name="density" title="Compact" desc="One line per topic — for triage" />
            </div>
          </div>
          <label className="field" style={{ marginTop: 16, maxWidth: 220 }}><span>Font size</span>
            <select className="input"><option>Small</option><option defaultValue>Medium</option><option>Large</option></select>
          </label>
          <div style={{ marginTop: 14 }}><Switch label="Reduce motion and animations" /></div>
          <div style={{ marginTop: 16 }}><Button>Save appearance</Button></div>
        </section>
        <section className="card" style={{ display: 'flex', flexWrap: 'wrap', gap: 12, alignItems: 'center', justifyContent: 'space-between' }}>
          <p className="muted" style={{ margin: 0, maxWidth: '44ch' }}>Download a copy of your appearance, reading, and composing preferences, or reset them to defaults.</p>
          <div style={{ display: 'flex', gap: 10 }}>
            <Button variant="secondary" size="sm">Export preferences</Button>
            <Button variant="ghost" size="sm">Reset to defaults</Button>
          </div>
        </section>
      </>
    );
  }

  /* ── Reading ──────────────────────────────────────────────────────────── */
  function Reading() {
    const { Button } = DS();
    return (
      <section className="scribe-panel">
        <span className="scribe-panel-head">Reading</span>
        <div className="stacked" style={{ width: '100%' }}>
          <div className="field-grid" style={{ width: '100%' }}>
            <label className="field"><span>Threads per page</span><select className="input"><option>20</option><option>25</option><option>50</option><option>100</option></select></label>
            <label className="field"><span>Posts per page</span><select className="input"><option>10</option><option>20</option><option>40</option></select></label>
          </div>
          <label className="field" style={{ maxWidth: 260 }}><span>Default thread sort</span><select className="input"><option>Last post</option><option>Newest</option><option>Most replies</option></select></label>
          <div className="toggle-stack">
            <label className="gem-field"><input type="checkbox" className="gem-check gem-leaf" defaultChecked /><span>Show signatures</span></label>
            <label className="gem-field"><input type="checkbox" className="gem-check gem-leaf" defaultChecked /><span>Show avatars</span></label>
            <label className="gem-field"><input type="checkbox" className="gem-check gem-leaf" defaultChecked /><span>Show reactions</span></label>
          </div>
          <Button>Save reading preferences</Button>
        </div>
      </section>
    );
  }

  /* ── Composing ────────────────────────────────────────────────────────── */
  function Composing() {
    const { Switch, Button } = DS();
    return (
      <section className="scribe-panel">
        <span className="scribe-panel-head">Composing</span>
        <p className="pane-intro">These control how the shared Markdown composer behaves for new topics, replies, direct messages, and edits.</p>
        <div style={{ display: 'flex', flexDirection: 'column', gap: 14 }}>
          <Switch label="Press Enter to send (Shift+Enter for a new line)" />
          <Switch label="Show a live preview while composing" defaultChecked />
          <Switch label="Continue lists and quotes on the next line" defaultChecked />
        </div>
        <div style={{ marginTop: 16 }}><Button>Save composing preferences</Button></div>
      </section>
    );
  }

  /* ── Drafts ───────────────────────────────────────────────────────────── */
  function Drafts() {
    return (
      <section className="card">
        <h2>Drafts</h2>
        <p className="pane-intro">Unsent topics and replies are kept here for 30 days.</p>
        <ul className="row-list">
          {S().drafts.map((d, i) => (
            <li className="list-row" key={i}>
              <div className="list-row-main">
                <span className="list-row-title">{d.title}</span>
                <span className="list-row-sub">#{d.board} · saved {d.when}</span>
              </div>
              <div className="list-row-actions">
                <button className="linkbtn" type="button">Resume</button>
                <button className="linkbtn danger" type="button">Discard</button>
              </div>
            </li>
          ))}
        </ul>
      </section>
    );
  }

  /* ── Notifications ────────────────────────────────────────────────────── */
  function Notifications() {
    const { Button } = DS();
    return (
      <>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Daily digest</span>
          <div className="field-grid" style={{ width: '100%' }}>
            <label className="field"><span>Timezone</span><select className="input"><option>UTC</option><option defaultValue>Europe / Rivendell</option><option>America / New York</option></select></label>
            <label className="field"><span>Digest hour (local)</span><select className="input"><option>Off</option><option defaultValue>08:00</option><option>18:00</option></select></label>
          </div>
          <div style={{ marginTop: 14 }}><Button>Save digest settings</Button></div>
        </section>
        <section className="card">
          <h2>Your subscriptions</h2>
          <ul className="row-list">
            {S().subscriptions.map((s, i) => (
              <li className="list-row" key={i}>
                <div className="list-row-main">
                  <a className="list-row-title" href="#" onClick={(e) => e.preventDefault()}>{s.label}</a>
                  <span className="list-row-sub">{s.freq}{s.email ? ' · email' : ''}</span>
                </div>
                <div className="list-row-actions"><button className="linkbtn danger" type="button">Unsubscribe</button></div>
              </li>
            ))}
          </ul>
        </section>
      </>
    );
  }

  /* ── Connections ──────────────────────────────────────────────────────── */
  function Connections() {
    return (
      <section className="card">
        <h2>Connected accounts</h2>
        <p className="pane-intro">Link Google, GitHub, or Apple to sign in faster. Email/password always stays available.</p>
        <ul className="row-list">
          {S().providers.map((p) => (
            <li className="list-row" key={p.name}>
              <span className="provider-mark" aria-hidden="true">{p.name[0]}</span>
              <div className="list-row-main">
                <span className="list-row-title">{p.name}</span>
                {p.linked ? <span className="list-row-sub">{p.email}</span> : null}
              </div>
              <div className="list-row-actions">
                {p.linked ? <><span className="pill pill-online">Connected</span><button className="linkbtn danger" type="button">Disconnect</button></>
                  : p.configured ? <a className="btn btn-secondary btn-small" href="#" onClick={(e) => e.preventDefault()}>Connect</a>
                  : <span className="muted">Not available</span>}
              </div>
            </li>
          ))}
        </ul>
      </section>
    );
  }

  /* ── Sessions ─────────────────────────────────────────────────────────── */
  function Sessions() {
    const { Button } = DS();
    return (
      <section className="card">
        <div className="list-head">
          <h2>Active sessions &amp; devices</h2>
          <Button variant="secondary" size="sm">Log out of all other devices</Button>
        </div>
        <ul className="row-list">
          {S().sessions.map((s) => (
            <li className="list-row" key={s.id}>
              <div className="list-row-main">
                <span className="list-row-title" style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>{s.ua}{s.current ? <span className="pill pill-online">This device</span> : null}</span>
                <span className="list-row-sub">IP {s.ip} · last active {s.last}</span>
              </div>
              {!s.current ? <div className="list-row-actions"><button className="linkbtn danger" type="button">Sign out</button></div> : null}
            </li>
          ))}
        </ul>
      </section>
    );
  }

  /* ── Blocks ───────────────────────────────────────────────────────────── */
  function Blocks() {
    const { Monogram } = DS();
    return (
      <section className="card">
        <h2>Blocked users</h2>
        <p className="pane-intro">Blocked members can't message or @mention you, and their notifications to you are suppressed.</p>
        <ul className="row-list">
          {S().blocks.map((b) => (
            <li className="list-row" key={b.username}>
              <Monogram name={b.name} username={b.username} size="sm" />
              <div className="list-row-main">
                <a className="list-row-title" href="#" onClick={(e) => e.preventDefault()}>{b.name}</a>
                <span className="list-row-sub">@{b.username}</span>
              </div>
              <div className="list-row-actions"><button className="linkbtn" type="button">Unblock</button></div>
            </li>
          ))}
        </ul>
      </section>
    );
  }

  /* ── Boards ───────────────────────────────────────────────────────────── */
  function Boards() {
    const [boards, setBoards] = React.useState(() => JSON.parse(JSON.stringify(S().boards)));
    const toggle = (ci, bi, key) => setBoards((prev) => prev.map((c, x) => x !== ci ? c : { ...c, items: c.items.map((b, y) => y !== bi ? b : { ...b, [key]: !b[key] }) }));
    return (
      <section className="card">
        <h2>Organize your boards</h2>
        <p className="pane-intro">Favorite boards rise to the top; muted boards are hidden from your sidebar and unread counts.</p>
        {boards.map((c, ci) => (
          <div key={c.cat}>
            <h3 className="board-cat">{c.cat}</h3>
            <ul className="row-list" style={{ marginTop: 0 }}>
              {c.items.map((b, bi) => (
                <li className="list-row" key={b.name}>
                  <div className="list-row-main"><span className="list-row-title"><span className="hash">#</span>{b.name}</span></div>
                  <div className="list-row-actions">
                    <button className={'toggle-link' + (b.fav ? ' on' : '')} type="button" onClick={() => toggle(ci, bi, 'fav')}>{b.fav ? '★ Favorited' : '☆ Favorite'}</button>
                    <button className={'toggle-link' + (b.muted ? ' on' : '')} type="button" onClick={() => toggle(ci, bi, 'muted')}>{b.muted ? 'Muted' : 'Mute'}</button>
                  </div>
                </li>
              ))}
            </ul>
          </div>
        ))}
      </section>
    );
  }

  /* ── Account (lifecycle) ──────────────────────────────────────────────── */
  function Lifecycle() {
    const { Input, Button } = DS();
    return (
      <>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Export account data</span>
          <p className="pane-intro">Download a JSON archive of your profile, preferences, subscriptions, notifications, posts, direct messages, and related audit rows.</p>
          <Button variant="secondary">Download account export</Button>
        </section>
        <section className="scribe-panel">
          <span className="scribe-panel-head">Deactivate account</span>
          <p className="pane-intro">Deactivation is reversible. Your seat stays sign-in capable, but counsel and posts are blocked until you reactivate.</p>
          <div className="stacked" style={{ width: '100%' }}>
            <label className="field" style={{ maxWidth: 320 }}><span>Current password</span><Input className="input-engraved" type="password" autoComplete="current-password" /></label>
            <Button variant="secondary">Deactivate account</Button>
          </div>
        </section>
        <section className="card danger-zone">
          <h2>Delete account</h2>
          <p className="pane-intro">Deletion starts a 30-day grace period. During it your account is write-blocked and you can cancel. Public counsel is preserved under a deleted-user identity while your PII is purged.</p>
          <div className="stacked" style={{ width: '100%' }}>
            <label className="field" style={{ maxWidth: 320 }}><span>Current password</span><Input className="input-engraved" type="password" autoComplete="current-password" /></label>
            <Button variant="danger">Request account deletion</Button>
          </div>
        </section>
      </>
    );
  }

  window.RBSettingsSections = {
    account: { label: 'Profile', icon: 'user', group: 'Account', render: Profile },
    security: { label: 'Security', icon: 'shield', group: 'Account', render: Security },
    privacy: { label: 'Privacy', icon: 'eye', group: 'Account', render: Privacy },
    appearance: { label: 'Appearance', icon: 'sun', group: 'Reading & writing', render: Appearance },
    preferences: { label: 'Reading', icon: 'book', group: 'Reading & writing', render: Reading },
    composing: { label: 'Composing', icon: 'pen', group: 'Reading & writing', render: Composing },
    drafts: { label: 'Drafts', icon: 'file', group: 'Reading & writing', render: Drafts },
    notifications: { label: 'Notifications', icon: 'bell', group: 'Council', render: Notifications },
    connections: { label: 'Connections', icon: 'link', group: 'Council', render: Connections },
    sessions: { label: 'Sessions', icon: 'monitor', group: 'Council', render: Sessions },
    blocks: { label: 'Blocks', icon: 'ban', group: 'Council', render: Blocks },
    boards: { label: 'Boards', icon: 'hash', group: 'Council', render: Boards },
    lifecycle: { label: 'Account', icon: 'archive', group: 'Council', render: Lifecycle },
  };
})();
