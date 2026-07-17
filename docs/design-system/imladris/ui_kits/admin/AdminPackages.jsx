/* Admin Console kit — production-parity Packages pane (admin/packages.php,
   package_detail.php, package_plan.php, package_consent.php,
   package_security.php, package_publisher.php). Self-contained drill-in via
   local state. Registers onto window.RBAdminParity. */
(function () {
  const A = () => window.RBAdmin;
  const DS = () => window.ImladrisDesignSystem_c3e027;

  function Packages() {
    const { Input, Textarea, Button } = DS();
    const P = A().packages;
    const [view, setView] = React.useState('catalogue');
    const [pkgId, setPkgId] = React.useState(null);
    const [pubId, setPubId] = React.useState(null);
    const go = (v, id) => { if (id != null) setPkgId(id); setView(v); };
    const det = pkgId != null ? P.detail[pkgId] : null;

    /* ── Catalogue ─────────────────────────────────────────────────────── */
    if (view === 'catalogue') {
      return (
        <>
          <div className="kit-note"><span>Security &amp; publishers:</span><button className="linkbtn" type="button" onClick={() => setView('security')}>Package security response →</button></div>
          <p className="muted">Staff browse of signed registry metadata. A signature proves byte provenance under a pinned key; install and enable still require review, consent, and local policy checks.</p>
          {P.registrySnapshots.filter((r) => !r.fresh).map((r) => (
            <p className="field-error" key={r.sourceId}>Stale snapshot: <strong>{r.sourceId}</strong> has no verified snapshot inside its freshness window ({r.expires ? 'expired ' + r.expires + ' UTC' : 'never fetched'}). Cached metadata below remains viewable. Run <code>php bin/console worker:registry-refresh</code>.</p>
          ))}
          <section className="card">
            <h2>Packages</h2>
            <div className="table-scroll table-scroll-wide" tabIndex={0} role="region" aria-label="Package catalogue">
              <table className="audit">
                <thead><tr><th>Package</th><th>Type</th><th>Install</th><th>Trust class</th><th>Latest</th><th>Compatibility</th><th>Advisory</th><th /></tr></thead>
                <tbody>
                  {P.list.map((p) => (
                    <tr key={p.id}>
                      <td><strong>{p.name}</strong><br /><code>{p.uid}</code> <span className="muted">via {p.registry} · {p.publisher}</span></td>
                      <td className="nowrap">{p.type}</td>
                      <td className="nowrap">{p.installState ? <span className="pill">{p.installState.charAt(0).toUpperCase() + p.installState.slice(1)}</span> : <span className="muted">-</span>}</td>
                      <td className="nowrap"><code>{p.trustClass}</code></td>
                      <td className="nowrap">{p.latest || <span className="muted">none stable</span>}</td>
                      <td className="nowrap">{p.compatible == null ? <span className="muted">n/a</span> : p.compatible ? <span className="pill">compatible</span> : <span className="pill">incompatible with this core</span>}</td>
                      <td className="nowrap">{p.blocked ? <span className="pill">locally blocked</span> : null}{p.advisoryStatus !== 'none' ? <span className="pill">{p.advisoryStatus}</span> : (!p.blocked ? <span className="muted">none</span> : null)}</td>
                      <td className="action-cell">{P.detail[p.id] ? <a href="#" onClick={(e) => { e.preventDefault(); go('detail', p.id); }}>Details</a> : <span className="muted">Details</span>}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </section>
        </>
      );
    }

    /* ── Security response ─────────────────────────────────────────────── */
    if (view === 'security') {
      const S = P.security;
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setView('catalogue')} style={{ alignSelf: 'flex-start' }}>← Package catalogue</button>
          <p className="muted">The emergency brake applies regardless of the package flag. Advisory ingest, acknowledgement, and the local blocklist live on the <a href="#" onClick={(e) => e.preventDefault()}>registry trust console</a>.</p>
          <section className="card">
            <h2>Emergency execution brake {S.executionDisabled ? <span className="pill pill-admin">disabled</span> : <span className="pill">live</span>}</h2>
            <p className="muted">{S.executionDisabled ? ('Package execution is halted: ' + S.affectedInstalls + ' integration install(s) paused. Operators can still view, revoke, export, and uninstall.') : ('Package-owned webhooks and credentials are live for ' + S.affectedInstalls + ' integration install(s).')}</p>
            <form className="inline-form" onSubmit={(e) => e.preventDefault()}>
              <Input placeholder="Reason (optional)" style={{ maxWidth: 220 }} />
              <Input type="password" placeholder="Your password" autoComplete="current-password" style={{ maxWidth: 150 }} />
              <Button size="sm">{S.executionDisabled ? 'Resume package execution' : 'Emergency-disable all packages'}</Button>
            </form>
          </section>
          <section className="card">
            <h2>Publishers</h2>
            <table className="audit">
              <thead><tr><th>Publisher</th><th>Status</th><th>Verified</th><th /></tr></thead>
              <tbody>
                {S.publishers.map((pub) => (
                  <tr key={pub.id}>
                    <td>{pub.displayName} <code>{pub.uid}</code></td>
                    <td>{pub.status}</td>
                    <td>{pub.verifiedAt ? pub.verifiedAt + ' UTC' : 'unverified'}</td>
                    <td>{P.publisherDetail[pub.id] ? <button className="linkbtn" type="button" onClick={() => { setPubId(pub.id); setView('publisher'); }}>Manage</button> : <span className="muted">Manage</span>}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </section>
          <section className="card">
            <h2>Advisories &amp; blocklist</h2>
            <p className="muted">{S.advisoriesCount} advisory record(s), {S.blocklistCount} local block(s). Ingest, acknowledge, and block on the registry trust console.</p>
          </section>
          <section className="card">
            <h2>Transparency log</h2>
            <table className="audit"><thead><tr><th>When</th><th>Event</th><th>Detail</th></tr></thead>
              <tbody>{S.transparency.map((r, i) => <tr key={i}><td className="nowrap mono">{r.when}</td><td><code>{r.event}</code></td><td>{r.detail}</td></tr>)}</tbody>
            </table>
          </section>
        </>
      );
    }

    /* ── Publisher trust ───────────────────────────────────────────────── */
    if (view === 'publisher') {
      const pub = P.publisherDetail[pubId];
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => setView('security')} style={{ alignSelf: 'flex-start' }}>← Package security</button>
          <h2 style={{ margin: 0 }}>{pub.displayName} <span className="pill">{pub.status}</span>{pub.verifiedAt ? <span className="pill">verified</span> : null}</h2>
          <p className="muted"><code>{pub.uid}</code>. Trust changes require your password. Suspension force-disables every install of this publisher's packages; reinstatement never silently re-enables them.</p>
          <section className="card">
            <h2>Status</h2>
            <div className="form-cell"><form className="inline-form" onSubmit={(e) => e.preventDefault()}><Input placeholder="Suspension reason" maxLength={255} style={{ maxWidth: 200 }} /><Input type="password" placeholder="Your password" autoComplete="current-password" style={{ maxWidth: 150 }} /><Button size="sm">Suspend publisher</Button></form></div>
          </section>
          <section className="card">
            <h2>Signing keys</h2>
            <table className="audit">
              <thead><tr><th>Key id</th><th>Status</th><th>Window</th><th>Fingerprint</th></tr></thead>
              <tbody>{pub.keys.map((k) => <tr key={k.id}><td className="nowrap"><code>{k.keyId}</code></td><td>{k.status}</td><td>{k.validFrom} to {k.validUntil}</td><td className="nowrap"><code>{k.fingerprint}</code></td></tr>)}</tbody>
            </table>
          </section>
          <section className="card">
            <h2>Packages &amp; review decisions</h2>
            {pub.packages.map((pk) => (
              <div key={pk.uid}><h3><code>{pk.uid}</code> <span className="pill">{pk.advisoryStatus}</span></h3>
                <ul className="plain-list">{pk.decisions.map((d, i) => <li key={i}>{d.decision} — <code>{d.digest}</code> ({d.source})</li>)}</ul>
              </div>
            ))}
          </section>
        </>
      );
    }

    /* ── Install plan ──────────────────────────────────────────────────── */
    if (view === 'plan') {
      const rel = det.releases[0];
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => go('detail', pkgId)} style={{ alignSelf: 'flex-start' }}>← {det.name}</button>
          <h2 style={{ margin: 0 }}>Install plan — {det.name} {rel.version}</h2>
          <section className="card">
            <h2>Install plan</h2>
            <p className="muted">Installing records provenance and permissions; nothing executes until you consent and enable.</p>
            <table className="audit"><tbody>
              <tr><th>Package</th><td>{det.name} <code>{det.uid}</code></td></tr>
              <tr><th>Version</th><td>{rel.version}</td></tr>
              <tr><th>Digest</th><td><code>{rel.digest}</code></td></tr>
              <tr><th>Registry</th><td>{det.registry ? det.registry.sourceId : 'local'}</td></tr>
              <tr><th>Review</th><td>{rel.review}</td></tr>
              <tr><th>Compatibility</th><td>{rel.compatible ? <span className="pill">compatible</span> : <span className="pill">incompatible</span>}</td></tr>
            </tbody></table>
          </section>
          <section className="card">
            <h2>Permission preview</h2>
            {det.permissions.length === 0 ? <p className="muted">No permissions declared.</p> : (
              <table className="audit"><thead><tr><th>Permission</th><th>Risk</th></tr></thead>
                <tbody>{det.permissions.map((p, i) => <tr key={i}><td>{p.label}<br /><code>{p.kind}:{p.key}</code></td><td>{p.risk}</td></tr>)}</tbody>
              </table>
            )}
          </section>
          <section className="card">
            <h2>Install</h2>
            <div className="stacked"><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm">Install (disabled until consent)</Button></div>
          </section>
        </>
      );
    }

    /* ── Consent ───────────────────────────────────────────────────────── */
    if (view === 'consent') {
      const pending = det.permissions.filter((p) => !p.granted);
      return (
        <>
          <button className="linkbtn" type="button" onClick={() => go('detail', pkgId)} style={{ alignSelf: 'flex-start' }}>← {det.name}</button>
          <h2 style={{ margin: 0 }}>Consent to permissions</h2>
          <section className="card">
            <h2>Pending grants</h2>
            {pending.length === 0 ? <p className="muted">No pending grants.</p> : (
              <table className="audit"><thead><tr><th>Permission</th><th>Risk</th></tr></thead>
                <tbody>{pending.map((p, i) => <tr key={i}><td>{p.label}<br /><code>{p.kind}:{p.key}</code></td><td>{p.risk}</td></tr>)}</tbody>
              </table>
            )}
          </section>
          <section className="card">
            <h2>Grant</h2>
            <div className="stacked"><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm">Grant and continue</Button></div>
          </section>
        </>
      );
    }

    /* ── Package detail ────────────────────────────────────────────────── */
    const inst = det.installed;
    const pendingCount = det.permissions.filter((p) => !p.granted).length;
    const notInstalled = !inst || inst.state === 'uninstalled';
    return (
      <>
        <button className="linkbtn" type="button" onClick={() => setView('catalogue')} style={{ alignSelf: 'flex-start' }}>← Package catalogue</button>
        <h2 style={{ margin: 0 }}>{det.name}</h2>
        <section className="card">
          <h2>Provenance</h2>
          <table className="audit"><tbody>
            <tr><th>Package identity</th><td><code>{det.uid}</code></td></tr>
            <tr><th>Pinned source</th><td>{det.registry ? det.registry.sourceId + ' (' + det.registry.baseUrl + ')' : 'local'}</td></tr>
            <tr><th>Type</th><td>{det.type}</td></tr>
            <tr><th>Trust class</th><td><code>{det.trustClass}</code>; trust is never implied by being listed</td></tr>
            <tr><th>Advisory status</th><td>{det.advisoryStatus}{det.blocked ? ' · locally blocked' : ''}</td></tr>
          </tbody></table>
        </section>

        <section className="card">
          <h2>Releases <span className="muted">(immutable: any changed byte is a new release)</span></h2>
          <div className="table-scroll table-scroll-wide" tabIndex={0} role="region" aria-label="Package releases">
            <table className="audit">
              <thead><tr><th>Version</th><th>Channel</th><th>Digest</th><th>Signed by</th><th>Review</th><th>Core range</th><th>Local review</th></tr></thead>
              <tbody>
                {det.releases.map((r) => (
                  <tr key={r.id}>
                    <td>{r.version}</td><td>{r.channel}</td>
                    <td><code>{r.digest.slice(0, 16)}…</code>{r.blocked ? <span className="pill">blocked</span> : null}</td>
                    <td>{r.signedKey ? <code>{r.signedKey}</code> : <span className="muted">snapshot-listed</span>}</td>
                    <td>{r.review}</td>
                    <td><code>{r.coreMin} - {r.coreMax}</code> {r.compatible ? <span className="pill">compatible</span> : <span className="pill">incompatible</span>}</td>
                    <td><select className="input input-small" defaultValue="approved"><option>approved</option><option>rejected</option><option>revoked</option></select></td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </section>

        <section className="card">
          <h2>Installation</h2>
          {notInstalled ? (
            <>
              <p className="muted">Create an install plan before any local state is written. Enabling happens only after install and permission consent.</p>
              <div className="form-actions"><Button size="sm" onClick={() => setView('plan')}>Install plan</Button></div>
            </>
          ) : (
            <>
              <table className="audit"><tbody>
                <tr><th>State</th><td><span className="pill">{inst.state.charAt(0).toUpperCase() + inst.state.slice(1)}</span></td></tr>
                <tr><th>Health</th><td>{inst.health}</td></tr>
                <tr><th>Version</th><td>{inst.version}</td></tr>
                <tr><th>Digest</th><td><code>{inst.digest.slice(0, 24)}…</code></td></tr>
                <tr><th>Pinned</th><td>{inst.pinned ? 'yes' : 'no'}</td></tr>
                <tr><th>Update policy</th><td>{inst.updatePolicy}</td></tr>
              </tbody></table>
              {pendingCount > 0 ? <p className="field-error">{pendingCount} permissions await consent. <a href="#" onClick={(e) => { e.preventDefault(); setView('consent'); }}>Review consent</a>.</p> : null}
              <div className="form-grid">
                {inst.state === 'installed' || inst.state === 'disabled' ? (
                  <form className="stacked" onSubmit={(e) => e.preventDefault()}><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm">Enable</Button></form>
                ) : null}
                {inst.state === 'enabled' ? <form className="stacked" onSubmit={(e) => e.preventDefault()}><Button size="sm" variant="secondary">Disable</Button></form> : null}
                <form className="stacked" onSubmit={(e) => e.preventDefault()}><Button size="sm" variant="secondary">{inst.pinned ? 'Unpin' : 'Pin'}</Button></form>
                <form className="stacked" onSubmit={(e) => e.preventDefault()}><label className="field"><span>Update policy</span><select className="input" defaultValue={inst.updatePolicy}><option value="manual">manual</option><option value="notify">notify</option></select></label><Button size="sm" variant="secondary">Save policy</Button></form>
                <form className="stacked" onSubmit={(e) => e.preventDefault()}><Button size="sm" variant="ghost">Export</Button></form>
                <form className="stacked" onSubmit={(e) => e.preventDefault()}><label className="field"><span>Current password</span><Input type="password" autoComplete="current-password" /></label><Button size="sm" variant="ghost">Uninstall</Button></form>
              </div>
              <h3>Permissions</h3>
              <table className="audit"><thead><tr><th>Permission</th><th>Risk</th><th>Granted</th></tr></thead>
                <tbody>{det.permissions.map((p, i) => <tr key={i}><td>{p.label}<br /><code>{p.kind}:{p.key}</code></td><td>{p.risk}</td><td>{p.granted ? 'yes' : 'pending'}</td></tr>)}</tbody>
              </table>
            </>
          )}
        </section>

        <section className="card">
          <h2>History</h2>
          {det.history.length === 0 ? <p className="muted">No lifecycle history recorded for this package.</p> : (
            <table className="audit"><thead><tr><th>Event</th><th>Versions</th><th>Digest</th><th>Detail</th><th>When</th></tr></thead>
              <tbody>{det.history.map((h, i) => <tr key={i}><td>{h.event}</td><td>{h.versions}</td><td><code>{h.digest}…</code></td><td>{h.detail || <span className="muted">—</span>}</td><td className="mono">{h.when} UTC</td></tr>)}</tbody>
            </table>
          )}
        </section>

        <section className="card">
          <h2>Advisories</h2>
          {det.advisories.length === 0 ? <p className="muted">No advisories recorded for this package.</p> : (
            <table className="audit"><thead><tr><th>Advisory</th><th>Severity</th><th>Action</th></tr></thead>
              <tbody>{det.advisories.map((a, i) => <tr key={i}><td><code>{a.uid}</code></td><td>{a.severity}</td><td><code>{a.action}</code></td></tr>)}</tbody>
            </table>
          )}
        </section>
      </>
    );
  }

  window.RBAdminParity = Object.assign(window.RBAdminParity || {}, {
    packages: { label: 'Packages', render: Packages },
  });
})();
