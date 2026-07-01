/* ──────────────────────────────────────────────────────────────────────────
   Feature-activation pages — shared chrome (React, via Babel)
   Icons, the topbar, the segmented control, a theme hook, and the page
   scaffold. Exported to window so each themed page's own babel script can use
   them (separate <script type="text/babel"> blocks don't share scope).
   ────────────────────────────────────────────────────────────────────────── */
(function () {
  const { useState, useEffect } = React;

  /* stroke icon helper — Lucide weight */
  const stroke = (d, s) => (
    <svg viewBox="0 0 24 24" width={s || 16} height={s || 16} fill="none" stroke="currentColor"
         strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{d}</svg>
  );

  const Icons = {
    check:    (s) => stroke(<path d="M20 6L9 17l-5-5" />, s),
    plus:     (s) => stroke(<path d="M12 5v14M5 12h14" />, s),
    x:        (s) => stroke(<path d="M18 6 6 18M6 6l12 12" />, s),
    chevron:  (s) => stroke(<path d="M6 9l6 6 6-6" />, s),
    chevronR: (s) => stroke(<path d="M9 6l6 6-6 6" />, s),
    clock:    (s) => stroke(<g><circle cx="12" cy="12" r="9" /><path d="M12 7v5l3 2" /></g>, s),
    lock:     (s) => stroke(<g><rect x="4" y="11" width="16" height="9" rx="2" /><path d="M8 11V8a4 4 0 0 1 8 0v3" /></g>, s),
    folder:   (s) => stroke(<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2V18a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />, s),
    folderOpen:(s)=> stroke(<path d="M3 7a2 2 0 0 1 2-2h4l2 2.5h8a2 2 0 0 1 2 2M3 7v11a2 2 0 0 0 2 2h13.5a2 2 0 0 0 1.9-1.4L22 11H6.5a2 2 0 0 0-1.9 1.4z" />, s),
    bookmark: (s) => stroke(<path d="M6 4h12a1 1 0 0 1 1 1v15l-7-4-7 4V5a1 1 0 0 1 1-1z" />, s),
    star:     (s) => stroke(<path d="M12 3l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 18.8 6.1 21l1.2-6.5L2.5 9.9 9 9z" />, s),
    filter:   (s) => stroke(<path d="M4 5h16l-6 7v6l-4 2v-8z" />, s),
    grip:     (s) => stroke(<g><circle cx="9" cy="6" r="0.6" /><circle cx="15" cy="6" r="0.6" /><circle cx="9" cy="12" r="0.6" /><circle cx="15" cy="12" r="0.6" /><circle cx="9" cy="18" r="0.6" /><circle cx="15" cy="18" r="0.6" /></g>, s),
    dots:     (s) => stroke(<g><circle cx="5" cy="12" r="0.7" /><circle cx="12" cy="12" r="0.7" /><circle cx="19" cy="12" r="0.7" /></g>, s),
    hash:     (s) => stroke(<path d="M9 3 7 21M17 3l-2 18M4 8.5h16M3 15.5h16" />, s),
    pencil:   (s) => stroke(<g><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></g>, s),
    pin:      (s) => stroke(<path d="M9 3h6l-1 7 3 3v2H7v-2l3-3z M12 15v6" />, s),
    arrowRight:(s)=> stroke(<path d="M5 12h14M13 6l6 6-6 6" />, s),
    search:   (s) => stroke(<g><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></g>, s),
    history:  (s) => stroke(<g><path d="M3 12a9 9 0 1 0 3-6.7L3 8" /><path d="M3 4v4h4" /><path d="M12 8v4l3 2" /></g>, s),
    link:     (s) => stroke(<g><path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1.5 1.5" /><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1.5-1.5" /></g>, s),
    quote:    (s) => stroke(<path d="M7 7H4v6h5V9c0 2-1 3-3 3M19 7h-3v6h5V9c0 2-1 3-3 3" />, s),
    split:    (s) => stroke(<g><path d="M6 3v6a3 3 0 0 0 3 3h6a3 3 0 0 1 3 3v6" /><path d="M3 6l3-3 3 3" /><path d="M15 21l3-3 3 3" transform="translate(0 -3)" /></g>, s),
    merge:    (s) => stroke(<g><path d="M6 21v-6a3 3 0 0 1 3-3h6a3 3 0 0 0 3-3V3" /><path d="M3 18l3 3 3-3" /><path d="M15 6l3-3 3 3" /></g>, s),
    snooze:   (s) => stroke(<g><circle cx="12" cy="13" r="8" /><path d="M9 10h6l-6 6h6" transform="scale(0.6) translate(8 8.5)" /><path d="M5 4l3-2M19 4l-3-2" /></g>, s),
    user:     (s) => stroke(<g><circle cx="12" cy="8" r="4" /><path d="M4 21a8 8 0 0 1 16 0" /></g>, s),
    escalate: (s) => stroke(<path d="M12 19V5M5 12l7-7 7 7" />, s),
  };

  /* brand stars */
  const EightStar = ({ size }) => (
    <svg viewBox="0 0 100 100" width={size} height={size} fill="currentColor" aria-hidden="true">
      <path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z" />
    </svg>
  );
  const CommendStar = ({ size }) => (
    <svg viewBox="0 0 100 100" width={size || 13} height={size || 13} fill="currentColor" aria-hidden="true">
      <path d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" />
    </svg>
  );

  function Segmented({ value, onChange, options }) {
    return (
      <div className="segmented" role="group">
        {options.map((o) => {
          const val = typeof o === 'string' ? o : o.value;
          const label = typeof o === 'string' ? o : o.label;
          return (
            <button key={val} className={value === val ? 'is-on' : ''}
              aria-pressed={value === val} onClick={() => onChange(val)}>{label}</button>
          );
        })}
      </div>
    );
  }

  function useTheme() {
    const [theme, setTheme] = useState('light');
    useEffect(() => {
      if (theme === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
      else document.documentElement.removeAttribute('data-theme');
    }, [theme]);
    return [theme, setTheme];
  }

  /* the page topbar with flag chips + theme toggle */
  function Topbar({ eyebrow, flags, theme, setTheme }) {
    return (
      <div className="fa-topbar">
        <span className="fa-star"><EightStar size={22} /></span>
        <span className="fa-brand">RetroBoards</span>
        <span className="fa-sep" />
        <span className="fa-eyebrow">{eyebrow || 'Feature activation'}</span>
        <span className="fa-spacer" />
        <span className="fa-flag-row">
          {(flags || []).map((f) => {
            const name = typeof f === 'string' ? f : f.name;
            const blocked = typeof f === 'object' && f.blocked;
            return <span key={name} className={'fa-flag' + (blocked ? ' is-blocked' : '')}>flag: {name}</span>;
          })}
        </span>
        <Segmented value={theme} onChange={setTheme}
          options={[{ value: 'light', label: 'Parchment' }, { value: 'dark', label: 'Twilight' }]} />
      </div>
    );
  }

  /* lede block — eyebrow, title, body (children), optional feature ledger */
  function Lede({ eyebrow, title, children, ledger }) {
    return (
      <div className="fa-lede">
        <span className="fa-lede-star"><EightStar size={150} /></span>
        <p className="fa-lede-eyebrow">{eyebrow}</p>
        <h1 className="fa-lede-title">{title}</h1>
        <p className="fa-lede-body">{children}</p>
        {ledger ? (
          <div className="fa-ledger">
            {ledger.map((l) => (
              <span className="fa-ledger-item" key={l.flag}>
                <span className="fa-ledger-dot" />
                <span className="fa-ledger-name">{l.flag}</span>
                <span className="fa-ledger-desc">{l.desc}</span>
              </span>
            ))}
          </div>
        ) : null}
      </div>
    );
  }

  function Spec({ num, label, title, note, children }) {
    return (
      <section className="spec">
        <div className="spec-kicker"><span className="spec-num">{num}</span> <span className="spec-label">{label}</span></div>
        <h2 className="spec-title">{title}</h2>
        <p className="spec-note">{note}</p>
        <div className="spec-frame">{children}</div>
      </section>
    );
  }

  function FrameBar({ route }) {
    return (
      <div className="frame-bar">
        <span className="frame-dots"><span className="frame-dot" /><span className="frame-dot" /><span className="frame-dot" /></span>
        <span className="frame-route">{route}</span>
      </div>
    );
  }

  Object.assign(window, {
    FA: { Icons, stroke, EightStar, CommendStar, Segmented, useTheme, Topbar, Lede, Spec, FrameBar },
  });
})();
