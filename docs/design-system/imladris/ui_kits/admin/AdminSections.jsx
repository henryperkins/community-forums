/* Admin Console kit — the section panes. Each is a faithful recreation of an
   admin/*.php template, composed from design-system primitives + .audit tables,
   .stat-cards, .flash, structure rows, and link-lists. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;

  /* ── Dashboard ────────────────────────────────────────────────────────── */
  function Dashboard() {
    const { Input, Textarea, Button } = DS();
    return (
      <>
        <section className="card">
          <h2>Site name</h2>
          <form className="inline-form" onSubmit={(e) => e.preventDefault()}>
            <Input defaultValue={A().siteName} maxLength={80} style={{ maxWidth: 280 }} />
            <Button size="sm">Update</Button>
          </form>
        </section>

        <section className="card">
          <h2>Trust &amp; safety</h2>
          <div className="stacked">
            <label className="field"><span>Registration</span>
              <select className="input"><option>Open</option><option>Closed (no new sign-ups)</option><option>Invite only</option></select>
            </label>
            <label className="field"><span>Anti-abuse enforcement</span>
              <select className="input"><option>Observe (log only)</option><option>Flag</option><option>Hold (queue for approval)</option><option>Block (reject)</option></select>
            </label>
            <label className="field"><span>Blocked words</span>
              <Textarea rows={3} placeholder="One word or phrase per line" defaultValue={"palantír-scam\nfree mithril"} />
              <span className="field-error" style={{ color: 'var(--text-faint)' }}>Case-insensitive; matched as substrings against new posts.</span>
            </label>
            <Button>Save settings</Button>
          </div>
        </section>

        <section className="card">
          <h2>Recent activity</h2>
          <table className="audit">
            <thead><tr><th>When</th><th>Actor</th><th>Action</th><th>Target</th><th>Reason</th></tr></thead>
            <tbody>
              {A().audit.map((r, i) => (
                <tr key={i}>
                  <td className="mono">{r.when}</td>
                  <td>{r.actor}</td>
                  <td><code>{r.action}</code></td>
                  <td className="mono">{r.target}</td>
                  <td>{r.reason || <span className="muted">—</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  /* ── Boards & categories (structure) ──────────────────────────────────── */
  function Structure() {
    const { Input, Button } = DS();
    return (
      <>
        {A().categories.map((c) => (
          <section className="card" key={c.id}>
            <div className="admin-cat-head">
              <form className="inline-form" onSubmit={(e) => e.preventDefault()}>
                <Input defaultValue={c.name} maxLength={64} style={{ maxWidth: 220 }} />
                <Button size="sm" variant="secondary">Save</Button>
              </form>
              <span className="admin-cat-actions">
                <button className="move-btn" type="button" aria-label="Move up">↑</button>
                <button className="move-btn" type="button" aria-label="Move down">↓</button>
                <button className="linkbtn danger" type="button">Delete category</button>
              </span>
            </div>
            <ul className="admin-board-list">
              {c.boards.map((b) => (
                <li className="admin-board-row" key={b.id}>
                  <span className="admin-board-name">
                    <span className="hash">#</span><b>{b.name}</b>
                    <span className="muted mono">/c/{b.slug}</span>
                    {b.visibility !== 'public' ? <span className="tag">{b.visibility}</span> : null}
                    {b.archived ? <span className="tag tag-archived">Archived</span> : null}
                    <span className="muted">· {b.threads} threads</span>
                  </span>
                  <span className="admin-board-actions">
                    <button className="move-btn" type="button" aria-label="Move up">↑</button>
                    <button className="move-btn" type="button" aria-label="Move down">↓</button>
                    <button className="linkbtn" type="button">Edit</button>
                    <button className="linkbtn" type="button">{b.archived ? 'Unarchive' : 'Archive'}</button>
                    <button className="linkbtn danger" type="button">Delete</button>
                  </span>
                </li>
              ))}
            </ul>
          </section>
        ))}

        <section className="card">
          <h2>Add a category</h2>
          <form className="inline-form" onSubmit={(e) => e.preventDefault()}>
            <Input placeholder="Category name" maxLength={64} style={{ maxWidth: 240 }} />
            <Button size="sm">Add category</Button>
          </form>
        </section>

        <section className="card">
          <h2>Add a board</h2>
          <div className="stacked">
            <label className="field"><span>Category</span><select className="input">{A().categories.map((c) => <option key={c.id}>#{c.name}</option>)}</select></label>
            <label className="field"><span>Name</span><Input maxLength={80} /></label>
            <label className="field"><span>Slug <span className="muted">(optional — derived from name)</span></span><Input maxLength={64} /></label>
            <label className="field"><span>Description</span><Input maxLength={255} /></label>
            <label className="field"><span>Visibility</span><select className="input"><option>Public</option><option>Hidden (unlisted)</option><option>Private (admins only)</option></select></label>
            <label className="field"><span>Assignment mode</span><select className="input"><option>Off</option><option>Members can assign themselves</option><option>Staff can assign members</option></select></label>
            <label className="checkline"><input type="checkbox" /> Allow anonymous posting</label>
            <label className="checkline"><input type="checkbox" defaultChecked /> Allow approved tags</label>
            <label className="checkline"><input type="checkbox" /> Allow wiki-style post editing</label>
            <Button size="sm">Add board</Button>
          </div>
        </section>
      </>
    );
  }

  /* ── Users ────────────────────────────────────────────────────────────── */
  function Users({ openUser }) {
    const { Input, Button, Monogram } = DS();
    return (
      <section className="card">
        <form className="inline-form" onSubmit={(e) => e.preventDefault()} style={{ marginBottom: 14 }}>
          <Input type="search" placeholder="Search username, name, or email" style={{ maxWidth: 320 }} />
          <Button size="sm" variant="secondary">Search</Button>
        </form>
        <table className="audit">
          <thead><tr><th>User</th><th>Role</th><th>State</th><th>Regard</th><th>Joined</th></tr></thead>
          <tbody>
            {A().users.map((u) => (
              <tr key={u.id}>
                <td>
                  <span style={{ display: 'inline-flex', alignItems: 'center', gap: 8 }}>
                    <Monogram name={u.display} username={u.username} size="sm" />
                    <a href="#" onClick={(e) => { e.preventDefault(); openUser(u.id); }}>{u.username}</a>
                    <span className="muted">{u.display}</span>
                  </span>
                </td>
                <td><span className={'role-pill role-' + u.role}>{u.role}</span></td>
                <td><span className={'state state-' + u.state}>{u.state}</span></td>
                <td className="tnum">{u.rep.toLocaleString()}</td>
                <td className="mono">{u.joined}</td>
              </tr>
            ))}
          </tbody>
        </table>
        <nav className="pager"><Button size="sm" variant="secondary" disabled>Previous</Button><Button size="sm" variant="secondary">Next</Button></nav>
      </section>
    );
  }

  /* ── User record (drill-in) ───────────────────────────────────────────── */
  function UserRecord({ userId, back }) {
    const { Input, Button, Monogram } = DS();
    const u = A().users.find((x) => x.id === userId) || A().users[0];
    return (
      <>
        <button className="linkbtn" type="button" onClick={back} style={{ alignSelf: 'flex-start', marginBottom: 2 }}>← All users</button>
        <section className="card">
          <h2 style={{ display: 'flex', alignItems: 'center', gap: 11 }}><Monogram name={u.display} username={u.username} size="lg" gilt /> {u.display} <span className="muted" style={{ fontFamily: 'var(--font-mono)', fontSize: '.9rem' }}>@{u.username}</span></h2>
          <dl className="id-stats">
            <div><dt>Role</dt><dd>{u.role}</dd></div>
            <div><dt>State</dt><dd>{u.state}</dd></div>
            <div><dt>Regard</dt><dd className="tnum">{u.rep.toLocaleString()}</dd></div>
            <div><dt>Profile</dt><dd><a href="#" onClick={(e) => e.preventDefault()}>View public profile</a></dd></div>
          </dl>
        </section>

        <section className="card">
          <h2>Cosmetic title</h2>
          <p className="pane-intro">Effective: <strong>{u.role === 'admin' ? 'Master of the House' : 'Loremaster of Imladris'}</strong> · Derived ladder: Legend</p>
          <div className="stacked">
            <label className="field"><span>Title override</span><Input maxLength={64} placeholder="(none)" /></label>
            <div className="form-actions"><Button size="sm">Save title</Button><Button size="sm" variant="ghost">Clear (revert to derived)</Button></div>
          </div>
        </section>

        <section className="card">
          <h2>Badges</h2>
          <h3>Grant a manual badge</h3>
          <div className="stacked">
            <label className="field"><span>Badge</span><select className="input">{A().badgeCatalogue.map((b) => <option key={b}>{b}</option>)}</select></label>
            <label className="field"><span>Reason (optional)</span><Input maxLength={255} /></label>
            <div className="form-actions"><Button size="sm">Grant badge</Button></div>
          </div>
          <h3>Held manual badges</h3>
          <ul className="link-list">
            <li><span aria-hidden="true" style={{ color: 'var(--star)' }}>✦</span> Trusted Answerer <button className="linkbtn muted spacer" type="button">Revoke</button></li>
            <li><span aria-hidden="true" style={{ color: 'var(--star)' }}>✦</span> Anniversary <button className="linkbtn muted spacer" type="button">Revoke</button></li>
          </ul>
        </section>
      </>
    );
  }

  /* ── Badge rules ──────────────────────────────────────────────────────── */
  function BadgeRules() {
    const { Input, Button } = DS();
    return (
      <>
        <section className="card">
          <h2>Create rule</h2>
          <div className="stacked">
            <label className="field"><span>Badge</span><select className="input">{A().badgeCatalogue.map((b) => <option key={b}>{b}</option>)}</select></label>
            <label className="field"><span>Rule</span><select className="input"><option>Post count</option><option>Thread count</option><option>Reputation</option><option>Solved answers</option></select></label>
            <label className="field"><span>Threshold</span><Input type="number" defaultValue="10" className="input-small" /></label>
            <label className="field"><span>Board scope</span><select className="input"><option>All boards</option>{A().categories.flatMap((c) => c.boards).map((b) => <option key={b.id}>{b.name}</option>)}</select></label>
            <Button>Create rule</Button>
          </div>
        </section>
        <section className="card">
          <h2>Rules</h2>
          <ul className="link-list">
            {A().badgeRules.map((r) => (
              <li key={r.id}>
                <strong>{r.badge}</strong>
                <span className="rule-meta">{r.rule} ≥ {r.threshold}{r.board ? ' · ' + r.board : ''}</span>
                <span className={'badge' + (r.enabled ? '' : ' badge-muted')}>{r.enabled ? 'Enabled' : 'Disabled'}</span>
                <span className="spacer" />
                <button className="linkbtn" type="button">Preview</button>
                <button className="linkbtn" type="button">Backfill</button>
                <button className="linkbtn muted" type="button">{r.enabled ? 'Disable' : 'Enable'}</button>
                <button className="linkbtn danger" type="button">Revoke awards</button>
              </li>
            ))}
          </ul>
        </section>
      </>
    );
  }

  /* ── Email delivery ───────────────────────────────────────────────────── */
  function Email() {
    const { Button } = DS();
    const q = A().emailQueue;
    return (
      <>
        <div className="flash"><strong>Sending is configured</strong> from <code>council@imladris.example</code>. The delivery worker drains queued mail.</div>
        <section className="card">
          <h2>Sending domain</h2>
          <p><strong>imladris.example</strong> <span className="muted mono">selector council</span></p>
          <p className="muted">SPF: pass · DKIM: pass · checked 2h ago</p>
          <Button size="sm" variant="secondary">Refresh SPF/DKIM status</Button>
        </section>
        <section className="card">
          <h2>Queue status</h2>
          <ul className="stat-cards">
            {Object.keys(q).map((k) => <li className="stat-card" key={k}><span className="stat-num tnum">{q[k]}</span><span className="stat-label">{k}</span></li>)}
          </ul>
        </section>
        <section className="card">
          <h2>Delivery log</h2>
          <table className="audit">
            <thead><tr><th>When</th><th>To</th><th>Kind</th><th>Status</th><th>Attempts</th><th>Subject</th><th>Action</th></tr></thead>
            <tbody>
              {A().deliveries.map((d, i) => (
                <tr key={i}>
                  <td className="mono">{d.when}</td><td className="mono">{d.to}</td><td>{d.kind}</td>
                  <td><span className={'state state-' + d.status}>{d.status}</span></td>
                  <td className="tnum">{d.attempts}</td><td>{d.subject}</td>
                  <td>{d.status === 'failed' ? <button className="linkbtn" type="button">Requeue</button> : <span className="muted">—</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  /* ── Webhooks ─────────────────────────────────────────────────────────── */
  function Webhooks() {
    const { Input, Button } = DS();
    return (
      <>
        <div className="flash flash-secret"><strong>Copy this signing secret now — it will not be shown again:</strong> <code>whsec_iml_3kf9d2a77qdh1pb42</code></div>
        <section className="card">
          <h2>Register an endpoint</h2>
          <div className="stacked">
            <label className="field"><span>Name</span><Input maxLength={80} /></label>
            <label className="field"><span>URL</span><Input type="url" placeholder="https://" /></label>
            <fieldset className="events">
              <legend>Events</legend>
              {Object.entries(A().webhookEvents).map(([ev, desc]) => (
                <label className="checkline" key={ev}><input type="checkbox" /> <code>{ev}</code> — {desc}</label>
              ))}
            </fieldset>
            <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
            <Button>Register endpoint</Button>
          </div>
        </section>
        <section className="card">
          <h2>Endpoints</h2>
          <table className="audit">
            <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Last status</th><th /></tr></thead>
            <tbody>
              {A().webhooks.map((w) => (
                <tr key={w.id}>
                  <td>{w.name}</td><td className="mono">{w.url}</td>
                  <td><span className={'state state-' + (w.active ? 'active' : 'paused')}>{w.active ? 'active' : 'paused'}</span></td>
                  <td className="tnum">{w.last}</td>
                  <td><a href="#" onClick={(e) => e.preventDefault()}>Manage</a></td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  /* ── API tokens ───────────────────────────────────────────────────────── */
  function ApiTokens() {
    const { Input, Button } = DS();
    return (
      <>
        <section className="card">
          <h2>Create a token</h2>
          <div className="stacked">
            <label className="field"><span>Name</span><Input maxLength={80} /></label>
            <fieldset className="events">
              <legend>Scopes</legend>
              {Object.entries(A().tokenScopes).map(([s, desc]) => (
                <label className="checkline" key={s}><input type="checkbox" /> <code>{s}</code> — {desc}</label>
              ))}
            </fieldset>
            <label className="field"><span>Expires in days <span className="muted">(optional)</span></span><Input type="number" className="input-small" /></label>
            <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
            <Button>Create token</Button>
          </div>
        </section>
        <section className="card">
          <h2>Tokens</h2>
          <table className="audit">
            <thead><tr><th>Name</th><th>Scopes</th><th>Created</th><th>Last used</th><th>Status</th><th /></tr></thead>
            <tbody>
              {A().tokens.map((t) => (
                <tr key={t.id}>
                  <td>{t.name}</td><td className="mono">{t.scopes}</td><td className="mono">{t.created}</td><td className="mono">{t.last}</td>
                  <td><span className={'state state-' + (t.revoked ? 'revoked' : 'active')}>{t.revoked ? 'revoked' : 'active'}</span></td>
                  <td>{t.revoked ? <span className="muted">—</span> : <button className="linkbtn danger" type="button">Revoke</button>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  /* ── Announcements ────────────────────────────────────────────────────── */
  function Announcements() {
    const { Textarea, Button } = DS();
    return (
      <>
        <section className="card">
          <h2>Current banner</h2>
          <p className="muted">No banner is currently shown.</p>
        </section>
        <section className="card">
          <h2>Publish a banner</h2>
          <div className="stacked">
            <label className="field"><span>Message</span><Textarea rows={3} maxLength={500} placeholder="A short notice for the whole council…" /></label>
            <label className="checkline"><input type="checkbox" defaultChecked /> Members can dismiss this banner</label>
            <label className="checkline"><input type="checkbox" /> Also send an in-app broadcast notification to all members</label>
            <label className="checkline"><input type="checkbox" /> Also queue an email broadcast to active members</label>
            <Button>Publish banner</Button>
          </div>
        </section>
      </>
    );
  }

  /* ── Tags ─────────────────────────────────────────────────────────────── */
  function Tags() {
    const { Input, Button } = DS();
    return (
      <>
        <section className="card">
          <h2>Add a tag</h2>
          <div className="stacked">
            <label className="field"><span>Name</span><Input maxLength={80} /></label>
            <label className="field"><span>Slug</span><Input maxLength={64} /></label>
            <label className="field"><span>Description</span><Input maxLength={255} /></label>
            <Button size="sm">Add tag</Button>
          </div>
        </section>
        <section className="card">
          <h2>Catalogue</h2>
          <ul className="admin-board-list">
            {A().tags.map((t) => (
              <li className="admin-board-row" key={t.id}>
                <span className="admin-board-name">
                  <b>{t.name}</b><span className="muted mono">/t/{t.slug}</span>
                  {t.visibility !== 'public' ? <span className="tag">{t.visibility}</span> : null}
                  <span className={'badge' + (t.enabled ? '' : ' badge-muted')}>{t.enabled ? 'Enabled' : 'Disabled'}</span>
                </span>
                <span className="admin-board-actions">
                  <button className="linkbtn" type="button">Edit</button>
                  <button className="linkbtn muted" type="button">Merge</button>
                </span>
              </li>
            ))}
          </ul>
        </section>
      </>
    );
  }

  /* ── Extensions ───────────────────────────────────────────────────────── */
  function Extensions() {
    return (
      <>
        <section className="card">
          <h2>Sandbox probe</h2>
          <p><strong>available</strong> <span className="muted mono">wasm-runtime</span></p>
          <p className="muted">Server extension execution is controlled by the <code>server_extensions</code> feature flag.</p>
        </section>
        <section className="card">
          <h2>Handlers</h2>
          <table className="audit">
            <thead><tr><th>Package</th><th>Handler</th><th>Status</th><th>Entrypoint</th></tr></thead>
            <tbody>
              {A().handlers.map((h, i) => (
                <tr key={i}><td className="mono">{h.pkg}</td><td className="mono">{h.handler}</td><td><span className="state state-active">{h.status}</span></td><td><code>{h.entrypoint}</code></td></tr>
              ))}
            </tbody>
          </table>
        </section>
        <section className="card">
          <h2>Run history</h2>
          <table className="audit">
            <thead><tr><th>When</th><th>Handler</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
              {A().runs.map((r, i) => (
                <tr key={i}><td className="mono">{r.when}</td><td className="mono">{r.handler}</td><td><span className={'state state-' + (r.status === 'ok' ? 'active' : 'failed')}>{r.status}</span></td><td>{r.detail || <span className="muted">—</span>}</td></tr>
              ))}
            </tbody>
          </table>
        </section>
      </>
    );
  }

  /* ── Branding ─────────────────────────────────────────────────────────── */
  function Branding() {
    const { Input, Textarea, Button } = DS();
    const [name, setName] = React.useState(A().siteName);
    const [primary, setPrimary] = React.useState('#2E4A3A');
    const [accent, setAccent] = React.useState('#C29A44');
    return (
      <section className="card">
        <h2>Branding</h2>
        <p className="pane-intro">Replace the placeholder name, colours, logo, and favicon with your community's own. Everything falls back to safe defaults if left blank.</p>
        <div className="brand-cols">
          <div className="stacked" style={{ width: '100%' }}>
            <label className="field"><span>Site name</span><Input value={name} onChange={(e) => setName(e.target.value)} maxLength={80} /></label>
            <label className="field"><span>Primary colour (hex)</span>
              <span className="swatch-input"><span className="swatch-chip" style={{ background: primary }} /><Input value={primary} onChange={(e) => setPrimary(e.target.value)} maxLength={7} className="input-small" /></span>
            </label>
            <label className="field"><span>Accent colour (hex)</span>
              <span className="swatch-input"><span className="swatch-chip" style={{ background: accent }} /><Input value={accent} onChange={(e) => setAccent(e.target.value)} maxLength={7} className="input-small" /></span>
            </label>
            <label className="field"><span>Default theme for signed-out visitors</span><select className="input"><option>System</option><option>Light</option><option>Dark</option></select></label>
            <label className="field"><span>Theme preset</span><select className="input"><option>Classic</option><option>Retro</option></select></label>
            <label className="field"><span>Logo</span><input type="file" className="input" /></label>
            <label className="field"><span>Favicon</span><input type="file" className="input" /></label>
            <label className="checkline"><input type="checkbox" /> Enable custom CSS</label>
            <label className="field"><span>Custom CSS</span><Textarea className="code-area" rows={5} placeholder="/* applies site-wide */" /></label>
            <Button>Save branding</Button>
          </div>
          <aside className="brand-preview">
            <p className="pane-intro" style={{ marginBottom: 8 }}>Live preview</p>
            <div className="brand-preview-shell">
              <div className="brand-preview-bar" style={{ background: primary }}><strong>{name || 'RetroBoards'}</strong><span>System</span></div>
              <div className="brand-preview-body">
                <a href="#" onClick={(e) => e.preventDefault()} style={{ color: primary }}>Sample link</a>
                <button className="btn" type="button" style={{ background: primary }}>Primary button</button>
                <span className="brand-preview-accent" style={{ color: accent, borderColor: accent }}>Accent marker</span>
              </div>
            </div>
          </aside>
        </div>
      </section>
    );
  }

  window.RBAdminSections = {
    dashboard: { label: 'Dashboard', render: Dashboard },
    structure: { label: 'Boards & categories', render: Structure },
    users: { label: 'Users', render: Users },
    badgeRules: { label: 'Badge rules', render: BadgeRules },
    tags: { label: 'Tags', render: Tags },
    email: { label: 'Email', render: Email },
    webhooks: { label: 'Webhooks', render: Webhooks },
    apiTokens: { label: 'API tokens', render: ApiTokens },
    announcements: { label: 'Announcements', render: Announcements },
    branding: { label: 'Branding', render: Branding },
  };
  window.RBAdminUserRecord = UserRecord;
})();
