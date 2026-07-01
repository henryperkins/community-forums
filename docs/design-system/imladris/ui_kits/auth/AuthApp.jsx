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

  const VIEWS = {
    login: Login, register: Register, forgot: Forgot, reset: Reset,
    mfa: Mfa, verifyPending: VerifyPending, verified: Verified,
  };
  const SWITCH = [
    ['login', 'Log in'], ['register', 'Sign up'], ['forgot', 'Forgot'],
    ['reset', 'Reset'], ['mfa', 'MFA'], ['verifyPending', 'Verify'], ['verified', 'Verified'],
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
