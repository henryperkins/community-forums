/* Auth UI kit — the six gate views (login, register, forgot, reset, mfa,
   verify), faithful to templates/auth/*. A top-right switcher (a kit
   affordance, not part of the product) jumps between them. */
(function () {
  function Brand() {
    const { EightPointStar } = window.ImladrisDesignSystem_c3e027;
    return <span className="auth-brand"><EightPointStar size={30} /><span className="auth-brand-name">RetroBoards</span></span>;
  }

  const OAUTH = [
    { name: 'Google', glyph: 'G' },
    { name: 'GitHub', glyph: 'GH' },
    { name: 'Apple', glyph: '' },
  ];
  function OAuth() {
    return (
      <div className="oauth-buttons">
        <p className="oauth-sep">or sign in with</p>
        <div className="oauth-row">
          {OAUTH.map((p) => (
            <a key={p.name} className="btn-oauth" href="#" onClick={(e) => e.preventDefault()}>
              <span className="oauth-glyph" aria-hidden="true">{p.glyph}</span>{p.name}
            </a>
          ))}
        </div>
      </div>
    );
  }

  function Login({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <div className="auth-card">
        <span className="auth-eyebrow">Welcome back to the council</span>
        <h1>Log in</h1>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('mfa'); }}>
          <label className="field"><span>Email</span><Input className="input-engraved" type="email" autoComplete="username" defaultValue="erestor@imladris.council" autoFocus /></label>
          <label className="field"><span>Password</span><Input className="input-engraved" type="password" autoComplete="current-password" /></label>
          <Button type="submit">Log in</Button>
        </form>
        <OAuth />
        <div className="auth-links">
          <p><a href="#" onClick={(e) => { e.preventDefault(); go('forgot'); }}>Forgot your password?</a></p>
          <p>New here? <a href="#" onClick={(e) => { e.preventDefault(); go('register'); }}>Create an account</a>.</p>
        </div>
      </div>
    );
  }

  function Register({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <div className="auth-card wide">
        <span className="auth-eyebrow">Take a seat at the table</span>
        <h1>Create your account</h1>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('verifyPending'); }}>
          <label className="field"><span>Username</span><Input className="input-engraved" maxLength={32} autoFocus /></label>
          <label className="field"><span>Display name <span className="muted">(optional)</span></span><Input className="input-engraved" maxLength={64} /></label>
          <label className="field"><span>Email</span><Input className="input-engraved" type="email" autoComplete="username" /></label>
          <label className="field"><span>Password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
          <label className="field"><span>Confirm password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
          <Button type="submit">Sign up</Button>
        </form>
        <div className="auth-links"><p>Already have an account? <a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Log in</a>.</p></div>
      </div>
    );
  }

  function Forgot({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    const [sent, setSent] = React.useState(false);
    return (
      <div className="auth-card">
        <h1>Reset your password</h1>
        {sent ? (
          <>
            <p className="auth-lede" style={{ marginTop: 8 }}>If an account exists for that email, we've sent a link to choose a new password. The link is valid for a limited time.</p>
            <div className="auth-links">
              <p>Didn't get it? Check your spam folder, or <a href="#" onClick={(e) => { e.preventDefault(); setSent(false); }}>try again</a>.</p>
              <p><a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Back to log in</a></p>
            </div>
          </>
        ) : (
          <>
            <p className="auth-lede">Enter your account's email address and we'll send you a link to choose a new password.</p>
            <form className="auth-form" onSubmit={(e) => { e.preventDefault(); setSent(true); }}>
              <label className="field"><span>Email</span><Input className="input-engraved" type="email" autoComplete="username" autoFocus /></label>
              <Button type="submit">Send reset link</Button>
            </form>
            <div className="auth-links"><p><a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Back to log in</a></p></div>
          </>
        )}
      </div>
    );
  }

  function Reset({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <div className="auth-card">
        <h1>Choose a new password</h1>
        <p className="auth-lede">Pick something only you would know. You'll use it next time you log in.</p>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('login'); }}>
          <label className="field"><span>New password</span><Input className="input-engraved" type="password" autoComplete="new-password" autoFocus /></label>
          <label className="field"><span>Confirm new password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
          <Button type="submit">Update password</Button>
        </form>
      </div>
    );
  }

  function Mfa({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <div className="auth-card">
        <span className="auth-eyebrow">One more ward</span>
        <h1>Two-factor verification</h1>
        <p className="auth-lede">Enter the code from your authenticator, or a one-time recovery code.</p>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('verified'); }}>
          <label className="field"><span>Authenticator or recovery code</span><Input className="input-engraved" inputMode="numeric" autoComplete="one-time-code" placeholder="000000" autoFocus /></label>
          <Button type="submit">Verify</Button>
        </form>
        <div className="auth-links"><p><a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Back to log in</a></p></div>
      </div>
    );
  }

  function VerifyPending({ go }) {
    return (
      <div className="auth-card">
        <div className="auth-emblem"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h16v16H4z" fill="none" /><path d="M22 6l-10 7L2 6" /><path d="M2 6h20v12H2z" /></svg></div>
        <h1>Confirm your email</h1>
        <p className="auth-lede">We've sent a confirmation link to your inbox. Verifying keeps your account recoverable and unlocks your <em>Welcome</em> mark of esteem.</p>
        <div className="auth-links">
          <p>Already confirmed? <a href="#" onClick={(e) => { e.preventDefault(); go('verified'); }}>Continue</a>.</p>
          <p><a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Back to log in</a></p>
        </div>
      </div>
    );
  }

  function Verified({ go }) {
    return (
      <div className="auth-card">
        <div className="auth-emblem"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10" /><path d="M8 12l3 3 5-6" /></svg></div>
        <h1>Email verified</h1>
        <p className="auth-lede">Thanks — your email address is confirmed. Your seat at the council is ready.</p>
        <div className="auth-links"><p><a href="../retroboards/index.html">Go to the community →</a></p></div>
      </div>
    );
  }

  /* Passkey key glyph (bow + blade + teeth — simple shapes only). */
  function PasskeyGlyph() {
    return <span className="passkey-glyph" aria-hidden="true"><svg viewBox="0 0 24 24"><circle cx="9" cy="12" r="4" /><path d="M13 12h7" /><path d="M17 12v3" /><path d="M20 12v2" /></svg></span>;
  }

  /* Passkey sign-in — the login gate with passkeys.js enhancement revealed
     (login.php `.passkey-signin`, shown when the browser supports WebAuthn). */
  function PasskeySignin({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    const [waiting, setWaiting] = React.useState(false);
    return (
      <div className="auth-card">
        <span className="auth-eyebrow">Welcome back to the council</span>
        <h1>Log in</h1>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('mfa'); }}>
          <label className="field"><span>Email</span><Input className="input-engraved" type="email" autoComplete="username" defaultValue="erestor@imladris.council" /></label>
          <label className="field"><span>Password</span><Input className="input-engraved" type="password" autoComplete="current-password" /></label>
          <Button type="submit">Log in</Button>
        </form>
        <div className="passkey-signin" data-waiting={waiting ? '1' : undefined}>
          <button type="button" className="btn btn-secondary passkey-btn" aria-busy={waiting || undefined} onClick={() => setWaiting((v) => !v)}>
            <PasskeyGlyph />{waiting ? 'Waiting for your passkey…' : 'Sign in with a passkey'}
          </button>
          {waiting ? <p className="passkey-status" role="status">Use your device screen lock or security key to continue. <a href="#" onClick={(e) => { e.preventDefault(); setWaiting(false); }}>Cancel</a></p> : null}
        </div>
        <OAuth />
        <div className="auth-links">
          <p><a href="#" onClick={(e) => { e.preventDefault(); go('forgot'); }}>Forgot your password?</a></p>
          <p>New here? <a href="#" onClick={(e) => { e.preventDefault(); go('register'); }}>Create an account</a>.</p>
        </div>
      </div>
    );
  }

  /* Passkey step-up — a fresh-check re-authentication ceremony for a sensitive
     action (security.php `data-passkey-stepup-btn`, used when there is no
     password). Confirms with a passkey, then returns to the action. */
  function StepUp({ go }) {
    const [done, setDone] = React.useState(false);
    return (
      <div className="auth-card">
        <div className="auth-emblem ward"><svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="4" width="14" height="16" rx="2" /><circle cx="12" cy="10" r="3" /><path d="M12 13v4" /></svg></div>
        <span className="auth-eyebrow">One more ward</span>
        <h1>Confirm it's you</h1>
        {done ? (
          <>
            <p className="auth-lede" style={{ color: 'var(--success)' }}>Confirmed with your passkey. You can finish the change now.</p>
            <div className="auth-links"><p><a href="#" onClick={(e) => { e.preventDefault(); go('verified'); }}>Continue →</a></p></div>
          </>
        ) : (
          <>
            <p className="auth-lede">This sensitive change needs a fresh check. Confirm with the passkey on this device to continue.</p>
            <button type="button" className="btn passkey-btn" onClick={() => setDone(true)}><PasskeyGlyph />Confirm with a passkey</button>
            <div className="auth-links"><p><a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Use your password instead</a></p></div>
          </>
        )}
      </div>
    );
  }

  /* Invited registration — the sign-up gate reached from an invitation link
     (register.php with a valid invite: the acceptance notice, the bound
     invitation context, and the "Accept invitation" submit label). */
  function Invited({ go }) {
    const { Input, Button } = window.ImladrisDesignSystem_c3e027;
    return (
      <div className="auth-card wide">
        <span className="auth-eyebrow">Take a seat at the table</span>
        <h1>Create your account</h1>
        <p className="notice" role="status">You've been invited to join this community. Complete the form to accept your invitation.</p>
        <p className="invite-chip"><span className="invite-chip-label">Invitation</span> bound to <strong>nimrodel@example.com</strong> · from <strong>@elrond</strong></p>
        <form className="auth-form" onSubmit={(e) => { e.preventDefault(); go('verified'); }}>
          <label className="field"><span>Username</span><Input className="input-engraved" maxLength={32} autoFocus /></label>
          <label className="field"><span>Display name <span className="muted">(optional)</span></span><Input className="input-engraved" maxLength={64} /></label>
          <label className="field"><span>Email</span><Input className="input-engraved" type="email" autoComplete="username" defaultValue="nimrodel@example.com" /></label>
          <label className="field"><span>Password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
          <label className="field"><span>Confirm password</span><Input className="input-engraved" type="password" autoComplete="new-password" /></label>
          <Button type="submit">Accept invitation</Button>
        </form>
        <div className="auth-links"><p>Already have an account? <a href="#" onClick={(e) => { e.preventDefault(); go('login'); }}>Log in</a>.</p></div>
      </div>
    );
  }

  const VIEWS = {
    login: Login, register: Register, forgot: Forgot, reset: Reset,
    mfa: Mfa, verifyPending: VerifyPending, verified: Verified,
    passkey: PasskeySignin, stepUp: StepUp, invited: Invited,
  };
  const SWITCH = [
    ['login', 'Log in'], ['passkey', 'Passkey'], ['stepUp', 'Step-up'], ['register', 'Sign up'], ['invited', 'Invited'],
    ['forgot', 'Forgot'], ['reset', 'Reset'], ['mfa', 'MFA'], ['verifyPending', 'Verify'], ['verified', 'Verified'],
  ];

  function App() {
    const { EightPointStar } = window.ImladrisDesignSystem_c3e027;
    const [view, setView] = React.useState('login');
    const View = VIEWS[view];
    return (
      <div className="auth-stage">
        <span className="auth-stage-star" aria-hidden="true"><EightPointStar size={760} variant="watermark" style={{ opacity: 1, width: 760, height: 760 }} /></span>
        <nav className="auth-switch" aria-label="Auth views (kit demo)">
          {SWITCH.map(([k, label]) => (
            <button key={k} className={view === k ? 'active' : ''} onClick={() => setView(k)}>{label}</button>
          ))}
        </nav>
        <Brand />
        <View go={setView} />
        <p className="auth-colophon">Et Eärello Endorenna utúlien.</p>
      </div>
    );
  }

  window.RBAuthApp = App;
})();
