/* Admin Console kit — production-parity section panes (part 1):
   Feature flags, Thread Intelligence, Registry trust, Themes, Roles &
   capabilities, Sign-in providers, Invitations. Faithful recreations of the
   admin/*.php templates at RetroBoards @ 6d81da5. Packages live in
   AdminPackages.jsx. All register onto window.RBAdminParity. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;

  function QueueCard({ head, count, detail }) {
    return (
      <div className="card queue-card is-static">
        <span className="queue-card-head">{head}</span>
        <strong className="queue-card-count">{count}</strong>
        <span className="queue-card-detail">{detail}</span>
      </div>
    );
  }

  /* ── Feature flags (admin/features.php) ───────────────────────────────── */
  function Features() {
    const s = A().featureStats;
    const [corrupt, setCorrupt] = React.useState(false);
    return (
      <>
        <p className="pane-intro">Read-only view of the declared feature flags from <code>src/Core/FeatureFlags.php</code>, their configured overrides in <code>settings.features</code>, and the effective runtime state. The readiness column distinguishes rows that are not simply shipped — <strong>Ready for acceptance</strong>, <strong>Missing user UI</strong>, <strong>Missing admin operations</strong>, <strong>Safety-blocked</strong>, <strong>Operational configuration required</strong>, and <strong>Reserved (ADR 0018)</strong>. Enablement stays a deliberate <code>settings.features</code> write; there are intentionally no toggles here.</p>

        <div className="kit-note">
          <span>Kit demo — reveal the corrupt-overrides state:</span>
          <button className="linkbtn" type="button" onClick={() => setCorrupt((v) => !v)}>{corrupt ? 'Restore valid overrides' : 'Simulate corrupt settings.features'}</button>
        </div>
        {corrupt ? <p className="field-error">The <code>settings.features</code> value is not a JSON object, so all stored feature overrides are being ignored and code defaults are in effect. Rewrite it as a JSON object (see <code>docs/runbooks/operations.md</code> §2) to restore your overrides.</p> : null}

        <section className="admin-dashboard-grid" aria-label="Feature flag summary">
          <QueueCard head="Declared" count={s.declared} detail={s.declared + ' declared flags'} />
          <QueueCard head="Defaults" count={s.default_on} detail={s.default_on + ' default-on · ' + s.default_off + ' default-dark'} />
          <QueueCard head="Effective" count={s.effective_on} detail={s.effective_on + ' on · ' + s.effective_off + ' off'} />
          <QueueCard head="Overrides" count={s.overrides} detail={s.unknown_overrides + ' unknown override' + (s.unknown_overrides === 1 ? '' : 's')} />
        </section>

        {A().featureGroups.map((g) => (
          <section className="card" key={g.group}>
            <h2>{g.group}</h2>
            <div className="table-scroll" tabIndex={0} role="region" aria-label={g.group + ' feature flags'}>
              <table className="audit audit-flags">
                <thead><tr><th>Flag</th><th>Effective</th><th>Default</th><th>Override</th><th>Rollback / enablement note</th><th>Readiness / next step</th></tr></thead>
                <tbody>
                  {g.rows.map((r) => (
                    <tr key={r.flag}>
                      <td><code>{r.flag}</code></td>
                      <td><span className={'state ' + (r.effective ? 'state-active' : 'state-paused')}>{r.effective ? 'on' : 'off'}</span></td>
                      <td><span className={'state ' + (r.default ? 'state-active' : 'state-paused')}>{r.default ? 'default-on' : 'default-off'}</span></td>
                      <td>{r.override ? <span className={'state ' + r.override.cls}>{r.override.text}</span> : <span className="muted">none</span>}</td>
                      <td>{r.rollback}</td>
                      <td>
                        {r.readiness ? (
                          <>
                            <span className={'state ' + r.readiness.cls}>{r.readiness.status}</span>
                            <p className="muted">{r.readiness.note}{r.readiness.href ? <> <a href="#" onClick={(e) => e.preventDefault()}>{r.readiness.link}</a></> : null}</p>
                          </>
                        ) : <span className="muted">—</span>}
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        ))}

        <section className="card">
          <h2>Unknown overrides</h2>
          {A().unknownOverrides.length === 0 ? (
            <p className="muted">No undeclared keys are present in <code>settings.features</code>.</p>
          ) : (
            <>
              <p className="muted">These keys are present in <code>settings.features</code> but are not declared in <code>FeatureFlags::DEFAULTS</code>. Remove them unless they are part of an in-progress local patch.</p>
              <table className="audit">
                <thead><tr><th>Key</th><th>Cast value</th><th>Raw value</th></tr></thead>
                <tbody>{A().unknownOverrides.map((r) => (<tr key={r.flag}><td><code>{r.flag}</code></td><td>{r.valueText}</td><td><code>{r.rawValue}</code></td></tr>))}</tbody>
              </table>
            </>
          )}
        </section>
      </>
    );
  }

  /* ── Thread Intelligence (admin/thread_intelligence.php) ──────────────── */
  function ThreadIntelligence() {
    const d = A().ti;
    const usedCalls = d.budget.usedCalls + d.budget.reservedCalls;
    const usedTokens = d.budget.usedTokens + d.budget.reservedTokens;
    return (
      <div className="thread-intelligence-admin">
        {d.warnings.length ? (
          <section className="card ti-attention" aria-label="Needs attention">
            <h2>Needs attention</h2>
            <ul>{d.warnings.map((w, i) => <li key={i}>{w}</li>)}</ul>
          </section>
        ) : null}

        <section className="admin-dashboard-grid" aria-label="Thread Intelligence status">
          <QueueCard head="Product flags" count={(d.flags.community_memory ? 1 : 0) + (d.flags.automated_context ? 1 : 0)} detail={'community memory ' + (d.flags.community_memory ? 'on' : 'off') + ' · automated context ' + (d.flags.automated_context ? 'on' : 'off')} />
          <QueueCard head="Provider" count={d.credentialReady ? 'Ready' : 'Not ready'} detail={d.providerLabel + ' · ' + (d.providerBlocked ? 'latched' : 'available')} />
          <QueueCard head="Worker" count={d.heartbeat.classification} detail={d.heartbeat.status} />
          <QueueCard head="Generation" count={d.paused ? 'Paused' : 'Running'} detail="Global provider egress brake" />
        </section>

        <section className="card ti-controls" aria-label="Recovery controls">
          <h2>Recovery controls</h2>
          <button className="btn btn-small" type="button">{d.paused ? 'Resume generation' : 'Pause generation'}</button>
          <button className="btn btn-small" type="button">Retry provider configuration</button>
          <p className="muted">Provider retry clears only the current health latch. Configure credentials outside this page.</p>
        </section>

        <section className="card ti-budget" aria-label="Daily budget">
          <h2>Daily budget</h2>
          <label>Calls {usedCalls} of {d.budget.callLimit}<progress max={d.budget.callLimit} value={usedCalls}>{usedCalls}</progress></label>
          <label>Input tokens {usedTokens.toLocaleString()} of {d.budget.tokenLimit.toLocaleString()}<progress max={d.budget.tokenLimit} value={usedTokens}>{usedTokens}</progress></label>
          <p className="muted">Resets {d.budget.nextReset} UTC</p>
        </section>

        <section className="admin-dashboard-grid" aria-label="Queue states">
          {Object.keys(d.queue).map((k) => (
            <QueueCard key={k} head={k.replace(/_/g, ' ').replace(/^\w/, (c) => c.toUpperCase())} count={d.queue[k]} detail={'thread' + (d.queue[k] === 1 ? '' : 's')} />
          ))}
        </section>

        <section className="card">
          <h2>Generation contract</h2>
          <dl className="ti-metadata">
            <div><dt>Model</dt><dd><code>{d.model}</code></dd></div>
            <div><dt>Reasoning effort</dt><dd>{d.reasoningEffort}</dd></div>
            <div><dt>Prompt version</dt><dd><code>{d.promptVersion}</code></dd></div>
          </dl>
        </section>

        <section className="card">
          <h2>Recent generation evidence</h2>
          <div className="table-scroll" tabIndex={0} role="region" aria-label="Recent redacted generation attempts">
            <table className="audit">
              <thead><tr><th>ID</th><th>Thread</th><th>Status</th><th>Requested</th><th>Contract</th><th>Evidence</th><th>Actions</th></tr></thead>
              <tbody>
                {d.recent.map((g) => (
                  <tr key={g.id}>
                    <td>#{g.id}</td>
                    <td><a href="#" onClick={(e) => e.preventDefault()}>{g.thread}</a></td>
                    <td><span className={'state state-' + (g.status === 'published' ? 'active' : g.status === 'failed' ? 'failed' : 'pending')}>{g.status}</span></td>
                    <td className="mono">{g.requested} UTC</td>
                    <td><code>{g.model}</code><br />{g.effort} · <code>{g.prompt}</code></td>
                    <td>
                      <details className="ti-evidence">
                        <summary>Redacted details</summary>
                        <p>Trigger <code>{g.trigger}</code> · retry {g.retry} · window {g.window}</p>
                        {g.failure ? <p>Failure <code>{g.failure.code}</code> · {g.failure.message}</p> : null}
                        {g.sources.length ? <p>Sources: {g.sources.map((id) => <a key={id} href="#" onClick={(e) => e.preventDefault()}>Post #{id} </a>)}</p> : null}
                        {g.candidates.length ? <p>Candidates: {g.candidates.map((id) => <a key={id} href="#" onClick={(e) => e.preventDefault()}>Thread #{id} </a>)}</p> : null}
                        <p>Usage: input {g.usage.input} · output {g.usage.output} · reasoning {g.usage.reasoning} · cached {g.usage.cached}</p>
                      </details>
                    </td>
                    <td className="ti-actions"><button className="linkbtn" type="button">Retry</button><button className="linkbtn" type="button">Reconcile</button><button className="linkbtn" type="button">Pause</button></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>
      </div>
    );
  }

  /* ── Registry trust (admin/registries.php) ────────────────────────────── */
  function Registries() {
    const { Input, Textarea, Button } = DS();
    const r = A().registries;
    return (
      <>
        <p className="muted">The private signing root lives offline with the operator; this console pins, rotates, and revokes public keys only. Trust changes require your password. The local blocklist works regardless of registry state.</p>

        {r.list.map((reg) => (
          <section className="card" key={reg.id}>
            <h2>{reg.displayName} <code>{reg.sourceId}</code> <span className="pill">{reg.enabled ? 'enabled' : 'disabled'}</span></h2>
            <p className="muted">{reg.baseUrl}. {reg.snapshot ? ('Last verified snapshot ' + reg.snapshot.generated + ' UTC; expires ' + reg.snapshot.expires + ' UTC.') : 'No verified snapshot yet.'}</p>
            <div className="table-scroll table-scroll-wide" tabIndex={0} role="region" aria-label={'Signing keys for ' + reg.displayName}>
              <table className="audit">
                <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th>Fingerprint</th><th /></tr></thead>
                <tbody>
                  {reg.keys.map((k) => (
                    <tr key={k.id}>
                      <td className="nowrap"><code>{k.keyId}</code></td>
                      <td>{k.status}{k.revokedReason ? ' — ' + k.revokedReason : ''}</td>
                      <td className="nowrap">{k.validFrom} to {k.validUntil}</td>
                      <td className="nowrap"><code>{k.fingerprint}</code></td>
                      <td className="form-cell">{k.status !== 'revoked' ? (
                        <form className="inline-form" onSubmit={(e) => e.preventDefault()}>
                          <Input placeholder="Revocation reason" required style={{ maxWidth: 180 }} />
                          <Input type="password" placeholder="Your password" autoComplete="current-password" required style={{ maxWidth: 150 }} />
                          <Button size="sm" variant="secondary">Revoke</Button>
                        </form>
                      ) : <span className="muted">—</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <details className="admin-details"><summary>Pin a new public key</summary>
              <div className="stacked" style={{ marginTop: 12 }}>
                <label className="field"><span>Key id</span><Input maxLength={190} /></label>
                <label className="field"><span>Public key <span className="muted">(base64, 32 bytes)</span></span><Input /></label>
                <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
                <Button size="sm">Pin key</Button>
              </div>
            </details>
            <details className="admin-details"><summary>Apply a signed key rotation</summary>
              <div className="stacked" style={{ marginTop: 12 }}>
                <label className="field"><span>Rotation envelope JSON</span><Textarea rows={3} /></label>
                <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
                <Button size="sm">Apply rotation</Button>
              </div>
            </details>
            <form className="stacked" onSubmit={(e) => e.preventDefault()} style={{ marginTop: 6 }}>
              {reg.enabled
                ? <Button size="sm" variant="secondary">Disable registry (no password)</Button>
                : (<><label className="field"><span>Confirm your password to enable</span><Input type="password" autoComplete="current-password" /></label><Button size="sm">Enable registry</Button></>)}
            </form>
          </section>
        ))}

        <section className="card">
          <h2>Add a registry source</h2>
          <div className="stacked">
            <label className="field"><span>Source id</span><Input maxLength={190} /></label>
            <label className="field"><span>Display name</span><Input maxLength={190} /></label>
            <label className="field"><span>Base URL</span><Input type="url" /></label>
            <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
            <Button size="sm">Add registry (starts disabled)</Button>
          </div>
        </section>

        <section className="card">
          <h2>Local blocklist <span className="muted">(registry-independent)</span></h2>
          <div className="table-scroll table-scroll-wide" tabIndex={0} role="region" aria-label="Local blocklist entries">
            <table className="audit">
              <thead><tr><th>Digest</th><th>Package uid</th><th>Reason</th><th /></tr></thead>
              <tbody>
                {r.blocks.map((b) => (
                  <tr key={b.id}>
                    <td>{b.digest ? <code>{b.digest.slice(0, 16)}…</code> : '—'}</td>
                    <td>{b.uid ? <code>{b.uid}</code> : '—'}</td>
                    <td>{b.reason}</td>
                    <td className="form-cell"><form className="inline-form" onSubmit={(e) => e.preventDefault()}><Input type="password" placeholder="Your password" autoComplete="current-password" style={{ maxWidth: 150 }} /><Button size="sm" variant="secondary">Remove (re-enables)</Button></form></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="card">
          <h2>Advisories</h2>
          {r.advisories.length === 0 ? <p className="muted">None ingested.</p> : (
            <table className="audit">
              <thead><tr><th>Advisory</th><th>Package</th><th>Severity</th><th>Action</th><th>Acknowledged</th><th /></tr></thead>
              <tbody>
                {r.advisories.map((a) => (
                  <tr key={a.id}>
                    <td><code>{a.uid}</code></td>
                    <td>{a.pkgUid ? <code>{a.pkgUid}</code> : <span className="muted">unresolved</span>}</td>
                    <td>{a.severity}</td><td><code>{a.action}</code></td>
                    <td>{a.ack ? a.ack + ' UTC' : 'not yet'}</td>
                    <td>{a.ack ? null : <button className="linkbtn" type="button">Acknowledge</button>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </>
    );
  }

  /* ── Themes (admin/themes.php + theme_safe_mode.php) ──────────────────── */
  function Themes() {
    const { Input, Button } = DS();
    const t = A().themes;
    const [safe, setSafe] = React.useState(false);
    if (safe) {
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setSafe(false)} style={{ alignSelf: 'flex-start' }}>← Back to Themes</button>
          <section className="card">
            <h2>Theme safe mode <span className="pill pill-admin">Recovery</span></h2>
            {t.safeMode ? <p className="field-error">Safe mode is on. The built-in system theme is being served.</p> : <p className="muted">Safe mode is off.</p>}
          </section>
          <section className="card">
            <h2>Enter safe mode</h2>
            <Button size="sm">Enter safe mode</Button>
          </section>
          <section className="card">
            <h2>Exit safe mode</h2>
            <div className="stacked"><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm">Exit safe mode</Button></div>
          </section>
        </>
      );
    }
    return (
      <>
        <section className="card">
          <h2>Safe mode</h2>
          {t.safeMode ? <p className="field-error">Theme safe mode is on. The built-in system theme is being served.</p> : <p className="muted">Safe mode is off. Active package themes are eligible to serve.</p>}
          <p><a href="#" onClick={(e) => { e.preventDefault(); setSafe(true); }}>Open recovery page</a></p>
        </section>

        <section className="card">
          <h2>Active theme</h2>
          {t.active ? (
            <table className="audit"><tbody>
              <tr><th>Package</th><td><strong>{t.active.packageName}</strong><br /><code>{t.active.uid}</code></td></tr>
              <tr><th>Version</th><td>{t.active.version}</td></tr>
              <tr><th>CSS digest</th><td><code>{t.active.cssDigest}</code></td></tr>
              <tr><th>Install state</th><td>{t.active.installState}</td></tr>
              <tr><th>Activated</th><td>{t.active.activatedAt} UTC</td></tr>
            </tbody></table>
          ) : <p className="muted">No package theme is active.</p>}
          {t.lkg ? (
            <>
              <p className="muted">Last-known-good: <code>{t.lkg.cssDigest}</code> from {t.lkg.uid} {t.lkg.version}.</p>
              <div className="stacked"><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm" variant="secondary">Roll back</Button></div>
            </>
          ) : null}
        </section>

        <section className="card">
          <h2>Installed theme packages</h2>
          <div className="table-scroll" tabIndex={0} role="region" aria-label="Installed theme packages">
            <table className="audit">
              <thead><tr><th>Package</th><th>Version</th><th>State</th><th>Latest build</th><th>Actions</th></tr></thead>
              <tbody>
                {t.installs.map((i) => (
                  <tr key={i.id}>
                    <td><strong>{i.packageName}</strong><br /><code>{i.uid}</code></td>
                    <td>{i.version}</td>
                    <td><span className="pill">{i.state.charAt(0).toUpperCase() + i.state.slice(1)}</span></td>
                    <td>{i.latestBuild ? <code>{i.latestBuild}</code> : <span className="muted">not built</span>}</td>
                    <td className="action-cell">{i.state === 'enabled' ? <><button className="linkbtn" type="button">Preview</button> <button className="linkbtn" type="button">Activate</button></> : <a href="#" onClick={(e) => e.preventDefault()}>Enable it from Packages first</a>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="card">
          <h2>Preview</h2>
          {t.preview ? <p>Previewing <strong>{t.preview.packageName}</strong> in this admin session only.</p> : <p className="muted">No session preview is active.</p>}
        </section>
      </>
    );
  }

  /* ── Roles & capabilities (admin/roles.php + role_edit/simulator) ─────── */
  function Roles() {
    const { Input, Textarea, Button } = DS();
    const R = A().roles;
    const [view, setView] = React.useState('list');
    const [roleId, setRoleId] = React.useState(null);

    if (view === 'sim') {
      const sim = R.simulator; const res = sim.result;
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setView('list')} style={{ alignSelf: 'flex-start' }}>← Roles</button>
          <p className="muted">Runs <code>can(actor, capability, target, time)</code> on the <strong>real resolver</strong>. While <code>capabilities</code> is in shadow, answers predict the post-cutover decision; live requests still use legacy authority.</p>
          <section className="card">
            <h2>Simulate</h2>
            <div className="stacked">
              <label className="field"><span>Actor <span className="muted">(username, id, or guest)</span></span><Input defaultValue={sim.actor} /></label>
              <label className="field"><span>Capability</span><select className="input" defaultValue={sim.capability}>{Object.keys(R.catalogue).map((k) => <option key={k}>{k}</option>)}</select></label>
              <label className="field"><span>Board id <span className="muted">(optional target)</span></span><Input type="number" defaultValue={sim.boardId} className="input-small" /></label>
              <label className="field"><span>At <span className="muted">(optional, UTC)</span></span><Input placeholder="2026-07-15 12:00" /></label>
              <Button size="sm">Simulate</Button>
            </div>
          </section>
          <section className="card">
            <h2>Result</h2>
            <p><strong>{res.allowed ? 'Allowed' : 'Denied'}</strong> — <code>{res.capability}</code> for {res.actorLabel}{res.targetLabel ? ' on ' + res.targetLabel : ''}</p>
            <ul className="plain-list">
              <li>Decisive rule: <code>{res.source}</code></li>
              <li>Reason: {res.reason}</li>
              {res.roleKey ? <li>Via role: <code>{res.roleKey}</code> at {res.scopeType} #{res.scopeId}</li> : null}
            </ul>
          </section>
        </>
      );
    }

    if (view === 'edit') {
      const det = R.detail[roleId]; const isSystem = det.role.kind === 'system';
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setView('list')} style={{ alignSelf: 'flex-start' }}>← Roles</button>
          <p className="muted"><code>{det.role.roleKey}</code> — {isSystem ? 'Protected system anchor (decision #18), read-only.' : 'Custom role.'} Active assignments affected by changes: <strong>{det.impact}</strong>.</p>

          {isSystem ? (
            <section className="card"><h2>Capabilities held</h2><ul className="plain-list">{det.currentKeys.map((k) => <li key={k}><code>{k}</code></li>)}</ul></section>
          ) : (
            <section className="card">
              <h2>Edit definition</h2>
              <div className="stacked">
                <label className="field"><span>Name</span><Input defaultValue={det.role.name} maxLength={190} /></label>
                <label className="field"><span>Description <span className="muted">(optional)</span></span><Input defaultValue={det.role.description} maxLength={255} /></label>
                <fieldset className="events"><legend>Capabilities</legend>
                  {Object.entries(R.catalogue).map(([k, m]) => (
                    <label className="checkline" key={k}><input type="checkbox" defaultChecked={det.currentKeys.includes(k)} disabled={!m.enforced} /> <code>{k}</code> — {m.consent}{m.risk === 'high' ? <span className="pill">high risk</span> : null}{!m.enforced ? <span className="muted"> (not yet enforceable)</span> : null}</label>
                  ))}
                </fieldset>
                <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
                <Button size="sm">Save (bumps version)</Button>
              </div>
            </section>
          )}

          <section className="card">
            <h2>Clone into a new custom role</h2>
            <div className="stacked">
              <label className="field"><span>New role name</span><Input maxLength={190} /></label>
              <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
              <Button size="sm" variant="secondary">Clone</Button>
            </div>
          </section>

          {!isSystem ? (
            <>
              <section className="card">
                <h2>Assignments</h2>
                {det.assignments.length === 0 ? <p className="muted">No one has been assigned this role yet.</p> : (
                  <table className="audit">
                    <thead><tr><th>Member</th><th>Scope</th><th>Window</th><th>Status</th><th /></tr></thead>
                    <tbody>
                      {det.assignments.map((a) => (
                        <tr key={a.id}>
                          <td><a href="#" onClick={(e) => e.preventDefault()}>@{a.username}</a></td>
                          <td>{a.scopeType}{a.scopeName ? ' — ' + a.scopeName : ''}</td>
                          <td>{a.starts} → {a.ends}</td>
                          <td><span className={'state state-' + a.status}>{a.status}</span></td>
                          <td><button className="linkbtn danger" type="button">Revoke</button></td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                )}
              </section>
              <section className="card">
                <h2>Assign this role</h2>
                <div className="stacked">
                  <label className="field"><span>Member username</span><Input maxLength={32} /></label>
                  <label className="field"><span>Scope</span><select className="input"><option>Site-wide</option><option>A single board</option><option>A single category</option></select></label>
                  <label className="field"><span>Ends <span className="muted">(UTC, optional — blank never expires)</span></span><Input placeholder="YYYY-MM-DD HH:MM" /></label>
                  <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
                  <Button size="sm">Assign role</Button>
                </div>
              </section>
            </>
          ) : null}
        </>
      );
    }

    return (
      <>
        <p className="muted">Resolver posture: <strong>{R.mode}</strong> (<code>CAPABILITIES_MODE</code>). Under <code>shadow</code> the legacy rules decide and the resolver only shadow-compares; under <code>enforce</code> the resolver decides and fails closed. System roles are protected compatibility anchors and cannot be edited; clone one to adapt it.</p>
        <div className="kit-note"><span>Operator tools:</span><button className="linkbtn" type="button" onClick={() => setView('sim')}>Open permission simulator →</button></div>
        <section className="card">
          <h2>Roles</h2>
          <table className="audit">
            <thead><tr><th>Name</th><th>Key</th><th>Kind</th><th>Version</th><th>Capabilities</th><th>Active assignments</th><th /></tr></thead>
            <tbody>
              {R.rows.map((r) => (
                <tr key={r.id}>
                  <td>{r.name}</td><td><code>{r.roleKey}</code></td>
                  <td>{r.kind === 'system' ? 'Protected anchor' : 'Custom'}</td>
                  <td>v{r.version}</td><td className="tnum">{r.capabilityCount}</td><td className="tnum">{r.impact}</td>
                  <td>{R.detail[r.id] ? <a href="#" onClick={(e) => { e.preventDefault(); setRoleId(r.id); setView('edit'); }}>{r.kind === 'system' ? 'View / clone' : 'Edit'}</a> : <span className="muted">{r.kind === 'system' ? 'View / clone' : 'Edit'}</span>}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
        <section className="card">
          <h2>Create a custom role</h2>
          <div className="stacked">
            <label className="field"><span>Name</span><Input maxLength={190} /></label>
            <label className="field"><span>Description <span className="muted">(optional)</span></span><Input maxLength={255} /></label>
            <fieldset className="events"><legend>Capabilities <span className="muted">(delegable only; protected authority is never offered)</span></legend>
              {Object.entries(R.catalogue).map(([k, m]) => (
                <label className="checkline" key={k}><input type="checkbox" disabled={!m.enforced} /> <code>{k}</code> — {m.consent}{m.risk === 'high' ? <span className="pill">high risk</span> : null}{!m.enforced ? <span className="muted"> (not yet enforceable)</span> : null}</label>
              ))}
            </fieldset>
            <label className="field"><span>Confirm your password</span><Input type="password" autoComplete="current-password" /></label>
            <Button size="sm">Create role</Button>
          </div>
        </section>
      </>
    );
  }

  /* ── Sign-in providers (admin/providers.php + provider_disable.php) ────── */
  function Providers() {
    const { Input, Textarea, Button } = DS();
    const P = A().providers;
    const [disableId, setDisableId] = React.useState(null);

    if (disableId != null) {
      const tgt = P.disableTarget[disableId];
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setDisableId(null)} style={{ alignSelf: 'flex-start' }}>← Sign-in providers</button>
          <section className="card">
            <h2>Before you disable {tgt.displayName}</h2>
            <p>Disabling removes <strong>{tgt.displayName}</strong> from sign-in and blocks its <code>/auth/{tgt.providerKey}/…</code> flow. Linked identities are <strong>retained</strong> — re-enabling restores sign-in unchanged.</p>
            {tgt.soleAccounts.length === 0 ? <p className="muted">No accounts rely on this provider as their only sign-in method.</p> : (
              <>
                <p className="field-error" role="alert">{tgt.soleAccounts.length} account{tgt.soleAccounts.length === 1 ? '' : 's'} can sign in <strong>only</strong> through this provider (no password, no passkey, no other provider). They will be locked out until they use password reset on their listed email, or you re-enable the provider. Contact them first.</p>
                <table className="audit"><thead><tr><th>Account</th><th>Email</th></tr></thead><tbody>{tgt.soleAccounts.map((a) => <tr key={a.username}><td><a href="#" onClick={(e) => e.preventDefault()}>{a.username}</a></td><td className="mono">{a.email}</td></tr>)}</tbody></table>
              </>
            )}
            <div className="stacked" style={{ marginTop: 12 }}>
              <label className="field"><span>Your password <span className="muted">(re-authentication)</span></span><Input type="password" autoComplete="current-password" /></label>
              <div className="form-actions"><Button size="sm">Disable {tgt.displayName}</Button><Button size="sm" variant="secondary" onClick={() => setDisableId(null)}>Cancel</Button></div>
            </div>
          </section>
        </>
      );
    }

    return (
      <>
        <p className="muted">Generic OIDC providers are configuration, not code: a pinned HTTPS issuer, a client id, and a client secret stored only in the encrypted vault. New providers land <strong>disabled</strong> — run "Test connection", then enable. Builtin providers (Google, Apple, GitHub) are configured through environment variables and only shown here for visibility. Disabling never deletes linked identities.</p>
        <section className="card">
          <h2>Providers</h2>
          <div className="table-scroll table-scroll-wide" tabIndex={0} role="region" aria-label="Sign-in providers">
            <table className="audit">
              <thead><tr><th>Provider</th><th>Key</th><th>Type</th><th>Issuer</th><th>Health</th><th>Sole-method</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
                {P.rows.map((r) => {
                  const builtin = r.type !== 'generic_oidc';
                  return (
                    <tr key={r.id}>
                      <td>{r.displayName}</td><td><code>{r.providerKey}</code></td>
                      <td>{builtin ? 'Builtin (env config)' : 'Generic OIDC'}</td>
                      <td className="mono">{r.issuer || '—'}</td>
                      <td>{r.health}{r.healthCheckedAt ? <span className="muted"> {r.healthCheckedAt}</span> : null}</td>
                      <td className="tnum">{r.soleMethodCount}</td>
                      <td>{builtin ? (r.envConfigured ? 'Configured' : 'Not configured') : (r.isEnabled ? 'Enabled' : 'Disabled')}</td>
                      <td className="action-cell">
                        {builtin ? <span className="muted">Set <code>OAUTH_{r.providerKey.toUpperCase()}_*</code> env vars</span> : (
                          <>
                            <button className="linkbtn" type="button">Test connection</button>
                            {r.isEnabled ? <> <a href="#" onClick={(e) => { e.preventDefault(); if (P.disableTarget[r.id]) setDisableId(r.id); }}>Disable…</a></> : <> <button className="linkbtn" type="button">Enable</button></>}
                          </>
                        )}
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </section>
        <section className="card">
          <h2>Add an OIDC provider</h2>
          <div className="stacked">
            <label className="field"><span>Provider key</span><Input maxLength={32} pattern="[a-z0-9][a-z0-9_-]{1,31}" /><span className="field-error" style={{ color: 'var(--text-faint)' }}>Stable slug used in <code>/auth/{'{key}'}/…</code> URLs — it cannot be changed later.</span></label>
            <label className="field"><span>Display name</span><Input maxLength={190} /></label>
            <label className="field"><span>Issuer <span className="muted">(pinned)</span></span><Input type="url" placeholder="https://gitlab.com" /><span className="field-error" style={{ color: 'var(--text-faint)' }}>Discovery resolves from <code>{'{issuer}'}/.well-known/openid-configuration</code>; a trailing slash is significant.</span></label>
            <label className="field"><span>Client ID</span><Input maxLength={255} /></label>
            <label className="field"><span>Client secret</span><Input type="password" autoComplete="off" /><span className="field-error" style={{ color: 'var(--text-faint)' }}>Stored write-only in the encrypted vault (<code>service_secrets</code> must be enabled first).</span></label>
            <label className="field"><span>Claim map <span className="muted">(optional JSON)</span></span><Textarea rows={2} placeholder='{"email":"upn"}' /></label>
            <label className="field"><span>Your password <span className="muted">(re-authentication)</span></span><Input type="password" autoComplete="current-password" /></label>
            <Button size="sm">Add provider</Button>
          </div>
        </section>
      </>
    );
  }

  /* ── Invitations (admin/invitations.php) ──────────────────────────────── */
  function Invitations() {
    const { Input, Button } = DS();
    const I = A().invitations;
    const [issued, setIssued] = React.useState(false);
    return (
      <>
        {issued ? (
          <div className="flash flash-secret" role="status"><strong>Copy this invitation link now — it will not be shown again:</strong> <code>https://imladris.example/join/inv_7f3k9d2a77qd</code></div>
        ) : null}
        <section className="card">
          <h2>Issue an invitation</h2>
          <p className="muted">Invitations admit one member per use, expire automatically, and never grant staff or custom roles. Bind to an email address or a domain to restrict who can redeem.</p>
          <form className="stacked" onSubmit={(e) => { e.preventDefault(); setIssued(true); }}>
            <label className="field"><span>Bind to email <span className="muted">(optional)</span></span><Input type="email" maxLength={255} placeholder="person@example.com" /></label>
            <label className="field"><span>Bind to domain <span className="muted">(optional)</span></span><Input maxLength={190} placeholder="example.com" /></label>
            <label className="field"><span>Max uses <span className="muted">(1–{I.limits.maxUses}, default 1)</span></span><Input type="number" min={1} max={I.limits.maxUses} className="input-small" /></label>
            <label className="field"><span>Expires in days <span className="muted">(1–{I.limits.maxExpiryDays}, default {I.limits.defaultExpiryDays})</span></span><Input type="number" min={1} max={I.limits.maxExpiryDays} className="input-small" /></label>
            <label className="field"><span>Grant board membership <span className="muted">(optional)</span></span><select className="input"><option>No board grant</option>{I.boards.map((b) => <option key={b.id}>{b.name}</option>)}</select></label>
            <Button size="sm">Issue invitation</Button>
          </form>
        </section>
        <section className="card">
          <h2>Issued invitations</h2>
          {I.rows.length === 0 ? <p className="muted">No invitations have been issued yet.</p> : (
            <table className="audit">
              <thead><tr><th>Created</th><th>By</th><th>Binding</th><th>Uses</th><th>Expires</th><th>Status</th><th /></tr></thead>
              <tbody>
                {I.rows.map((r) => (
                  <tr key={r.id}>
                    <td className="mono">{r.created}</td><td>{r.creator}</td>
                    <td>{r.email ? r.email : r.domain ? '@' + r.domain : <span className="muted">any email</span>}</td>
                    <td className="tnum">{r.usedCount}/{r.maxUses}</td>
                    <td>{r.expires}</td>
                    <td><span className={'state state-' + (r.status === 'active' ? 'active' : r.status === 'revoked' ? 'revoked' : 'sent')}>{r.status}</span></td>
                    <td>{r.status === 'active' ? <button className="linkbtn danger" type="button">Revoke</button> : <span className="muted">—</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </section>
      </>
    );
  }

  window.RBAdminParity = Object.assign(window.RBAdminParity || {}, {
    features: { label: 'Feature flags', render: Features },
    threadIntelligence: { label: 'Thread Intelligence', render: ThreadIntelligence },
    registries: { label: 'Registry trust', render: Registries },
    themes: { label: 'Themes', render: Themes },
    roles: { label: 'Roles', render: Roles },
    providers: { label: 'Sign-in providers', render: Providers },
    invitations: { label: 'Invitations', render: Invitations },
  });
})();
