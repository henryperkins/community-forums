/* System surfaces kit — the chrome-less product pages: setup wizard, error
   pages (incl. database-down), privacy content page, unsubscribe confirm, and
   the gated-profile stub. Faithful to templates/{setup/wizard, errors/error,
   privacy, unsubscribe, profile/gated}.php. A top-right switcher (kit
   affordance, not part of the product) jumps between them. */
(function () {
  const DS = () => window.ImladrisDesignSystem_c3e027;

  function Brand() {
    const { EightPointStar } = DS();
    return <span className="sys-brand"><EightPointStar size={26} /><span className="sys-brand-name">RetroBoards</span></span>;
  }

  /* ── Setup wizard (setup/wizard.php) ──────────────────────────────────── */
  function Setup() {
    const { Input, Button } = DS();
    return (
      <div className="sys-card setup">
        <h1>Welcome — let's set up your community</h1>
        <p className="muted">Create the first administrator account and name your community. You can change everything later.</p>
        <form className="stacked" onSubmit={(e) => e.preventDefault()}>
          <fieldset className="field-group">
            <legend>Community</legend>
            <label className="field"><span>Community name</span><Input maxLength={80} autoFocus /></label>
          </fieldset>
          <fieldset className="field-group">
            <legend>Administrator account</legend>
            <label className="field"><span>Username</span><Input maxLength={32} /></label>
            <label className="field"><span>Email</span><Input type="email" /></label>
            <label className="field"><span>Password</span><Input type="password" autoComplete="new-password" /></label>
            <label className="field"><span>Confirm password</span><Input type="password" autoComplete="new-password" /></label>
          </fieldset>
          <p className="muted">A starter set of categories and boards will be created automatically.</p>
          <Button type="submit">Create my community</Button>
        </form>
      </div>
    );
  }

  /* ── Error pages (errors/error.php) ───────────────────────────────────── */
  const ERRORS = {
    '404': { code: 404, msg: "We couldn't find that page. It may have moved, or never existed." },
    '403': { code: 403, msg: "You don't have permission to view this page.", mod: true },
    '500': { code: 500, msg: 'Something went wrong on our end. The council has been notified.' },
    '503': { code: 503, msg: 'The community is temporarily unavailable while the database is unreachable. Please try again in a few moments.', db: true },
  };
  function ErrorPage() {
    const [s, setS] = React.useState('404');
    const e = ERRORS[s];
    return (
      <>
        <div className="kit-note">
          <span>Status:</span>
          {Object.keys(ERRORS).map((k) => (
            <button key={k} type="button" className={'linkbtn' + (k === s ? ' is-active' : '')} onClick={() => setS(k)}>{k}{ERRORS[k].db ? ' · database-down' : ''}</button>
          ))}
        </div>
        <div className="sys-card error-card">
          <h1>{e.code}</h1>
          <p>{e.msg}</p>
          {e.mod ? <p><a className="btn btn-secondary" href="#" onClick={(ev) => ev.preventDefault()}>Moderation queue <span className="mod-count">3</span></a></p> : null}
          <p><a className="btn" href={e.db ? '#' : '../retroboards/index.html'} onClick={e.db ? (ev) => ev.preventDefault() : undefined}>{e.db ? 'Try again' : 'Back to home'}</a></p>
        </div>
      </>
    );
  }

  /* ── Privacy content page (privacy.php) ───────────────────────────────── */
  function Privacy() {
    return (
      <article className="content-page">
        <h1>Privacy</h1>
        <section aria-labelledby="ti-h">
          <h2 id="ti-h">Thread intelligence</h2>
          <p>Eligible public post text may be processed by OpenAI to prepare living summaries and explanations for related public discussions.</p>
          <p>Private and hidden content is excluded, and account metadata is not included in these requests.</p>
          <p>Provider storage is disabled by the application request. Member-facing pages show the resulting brief and its current sources, but do not expose model or runtime evidence.</p>
        </section>
      </article>
    );
  }

  /* ── Unsubscribe (unsubscribe.php) ────────────────────────────────────── */
  function Unsubscribe() {
    const { Button } = DS();
    const [state, setState] = React.useState('confirm');
    const email = 'arwen@imladris.council';
    return (
      <div className="sys-card">
        <h1>Email preferences</h1>
        {state === 'confirm' ? (
          <>
            <p>Unsubscribe <strong>{email}</strong> from RetroBoards notification emails?</p>
            <Button onClick={() => setState('done')}>Unsubscribe</Button>
          </>
        ) : state === 'done' ? (
          <>
            <p>Done — <strong>{email}</strong> will no longer receive notification emails.</p>
            <p className="muted">Changed your mind?</p>
            <Button variant="secondary" onClick={() => setState('resub')}>Re-subscribe</Button>
          </>
        ) : (
          <p><strong>{email}</strong> has been re-subscribed to notification emails.</p>
        )}
      </div>
    );
  }

  /* ── Gated profile (profile/gated.php) ────────────────────────────────── */
  function ProfileGated() {
    const { Button } = DS();
    return (
      <div className="sys-gated">
        <h1>@saruman</h1>
        <p>This member limits their profile to signed-in members.</p>
        <Button size="sm" href="../auth/index.html">Log in to view</Button>
      </div>
    );
  }

  const VIEWS = { setup: Setup, error: ErrorPage, privacy: Privacy, unsubscribe: Unsubscribe, gated: ProfileGated };
  const SWITCH = [['setup', 'Setup wizard'], ['error', 'Error'], ['privacy', 'Privacy'], ['unsubscribe', 'Unsubscribe'], ['gated', 'Profile gated']];

  function App() {
    const [v, setV] = React.useState('setup');
    const View = VIEWS[v];
    return (
      <div className="sys-stage">
        <nav className="sys-switch" aria-label="System pages (kit demo)">
          {SWITCH.map(([k, l]) => <button key={k} className={v === k ? 'active' : ''} onClick={() => setV(k)}>{l}</button>)}
        </nav>
        <Brand />
        <View />
      </div>
    );
  }

  window.RBSystemApp = App;
})();
