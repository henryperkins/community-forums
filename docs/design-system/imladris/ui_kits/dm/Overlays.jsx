/* Messages kit — shared overlay bits: the popover menu (header ··· and the
   per-message hover ···), the modal shell + its two bodies (new-message and a
   generic confirm), and a small Lucide-style icon set. Exposed on window. */
(function () {
  const { useState, useEffect, useRef } = React;
  const DS = window.ImladrisDesignSystem_c3e027;

  /* ── Icons (Lucide register, stroke ~1.8) ─────────────────────────────── */
  const svg = (children, extra) => (
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
      strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round" {...extra}>{children}</svg>
  );
  const Icons = {
    Plus:    () => svg(<path d="M12 5v14M5 12h14" />),
    Search:  () => svg(<><circle cx="11" cy="11" r="7" /><path d="M21 21l-4.3-4.3" /></>),
    Chevron: () => svg(<path d="M15 18l-6-6 6-6" />),
    More:    () => (<svg viewBox="0 0 24 24" width="16" height="16" style={{ fill: 'currentColor', stroke: 'none' }}><circle cx="5" cy="12" r="1.7" /><circle cx="12" cy="12" r="1.7" /><circle cx="19" cy="12" r="1.7" /></svg>),
    Panel:   () => svg(<><rect x="3" y="4" width="18" height="16" rx="2" /><path d="M15 4v16" /></>),
    Mute:    () => svg(<><path d="M11 5 6 9H2v6h4l5 4z" /><path d="M22 9l-6 6M16 9l6 6" /></>),
    Bell:    () => svg(<><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></>),
    User:    () => svg(<><path d="M6 21v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2" /><circle cx="12" cy="7" r="4" /></>),
    Users:   () => svg(<><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M22 21v-2a4 4 0 0 0-3-3.87" /><path d="M16 3.13a4 4 0 0 1 0 7.75" /></>),
    Rename:  () => svg(<><path d="M12 20h9" /><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z" /></>),
    AddUser: () => svg(<><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" /><circle cx="9" cy="7" r="4" /><path d="M19 8v6M22 11h-6" /></>),
    Leave:   () => svg(<><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" /><path d="M16 17l5-5-5-5" /><path d="M21 12H9" /></>),
    Block:   () => svg(<><circle cx="12" cy="12" r="9" /><path d="M5.6 5.6l12.8 12.8" /></>),
    Flag:    () => svg(<><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z" /><line x1="4" y1="22" x2="4" y2="15" /></>),
    Copy:    () => svg(<><rect x="9" y="9" width="12" height="12" rx="2" /><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1" /></>),
    Close:   () => svg(<path d="M18 6 6 18M6 6l12 12" />),
    Check:   () => svg(<path d="M20 6 9 17l-5-5" />),
    Lock:    () => svg(<><rect x="4.5" y="10.5" width="15" height="10" rx="2" /><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5" /></>),
    Send:    () => svg(<><path d="M12 19V5" /><path d="M5 12l7-7 7 7" /></>),
  };

  /* ── Popover menu ─────────────────────────────────────────────────────── */
  /* `button` is a render-prop: ({ open, toggle }) => node.
     `items`: [{ label, icon, onClick, danger } | { sep:true }] */
  function Menu({ button, items, align }) {
    const [open, setOpen] = useState(false);
    const ref = useRef(null);
    useEffect(() => {
      if (!open) return;
      const onDoc = (e) => { if (ref.current && !ref.current.contains(e.target)) setOpen(false); };
      const onKey = (e) => { if (e.key === 'Escape') setOpen(false); };
      document.addEventListener('mousedown', onDoc);
      document.addEventListener('keydown', onKey);
      return () => { document.removeEventListener('mousedown', onDoc); document.removeEventListener('keydown', onKey); };
    }, [open]);
    return (
      <span className="dm-menu-wrap" ref={ref}>
        {button({ open, toggle: () => setOpen((o) => !o) })}
        {open ? (
          <div className={'dm-menu-pop ' + (align === 'left' ? 'to-left' : 'to-right')} role="menu">
            {items.filter(Boolean).map((it, i) => it.sep ? (
              <div key={i} className="dm-menu-sep" />
            ) : (
              <button key={i} type="button" role="menuitem"
                className={'dm-menu-item' + (it.danger ? ' danger' : '')}
                onClick={() => { setOpen(false); it.onClick && it.onClick(); }}>
                {it.icon}{it.label}
              </button>
            ))}
          </div>
        ) : null}
      </span>
    );
  }

  /* ── Modal shell ──────────────────────────────────────────────────────── */
  function Modal({ onClose, children }) {
    useEffect(() => {
      const onKey = (e) => { if (e.key === 'Escape') onClose(); };
      document.addEventListener('keydown', onKey);
      return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);
    return (
      <div className="dm-scrim" onMouseDown={(e) => { if (e.target === e.currentTarget) onClose(); }}>
        <div className="dm-dialog" role="dialog" aria-modal="true">{children}</div>
      </div>
    );
  }

  /* New-message body (mirrors dm/new.php: recipients → group, title, body) */
  function ComposeForm({ onClose, onSend }) {
    const { Input, Textarea, Button } = DS;
    const [to, setTo] = useState('');
    const [title, setTitle] = useState('');
    const [body, setBody] = useState('');
    const isGroup = to.includes(',');
    return (
      <form onSubmit={(e) => { e.preventDefault(); onSend && onSend({ to, title, body }); }}>
        <div className="dm-dialog-head">
          <span><span className="eyebrow">Private counsel</span><h2>New message</h2></span>
          <button type="button" className="dm-dialog-close" onClick={onClose} aria-label="Close">{Icons.Close()}</button>
        </div>
        <div className="dm-dialog-body">
          <Input label="To" value={to} onChange={(e) => setTo(e.target.value)} placeholder="username, username" maxLength={255} autoFocus />
          <p className="field-hint">Separate usernames with commas to open a group counsel.</p>
          {isGroup ? (
            <Input label="Group title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder="Optional" maxLength={120} />
          ) : null}
          <Textarea label="Message" rows={5} value={body} onChange={(e) => setBody(e.target.value)} placeholder="Write your counsel…" maxLength={5000} />
        </div>
        <div className="dm-dialog-foot">
          <Button type="submit" disabled={!to.trim() || !body.trim()}>Send message</Button>
          <Button type="button" variant="ghost" onClick={onClose}>Cancel</Button>
        </div>
      </form>
    );
  }

  /* Generic confirm (leave / block / report conversation) */
  function ConfirmBody({ title, body, confirmLabel, danger, onConfirm, onClose }) {
    const { Button } = DS;
    return (
      <>
        <div className="dm-dialog-head">
          <span><h2>{title}</h2></span>
          <button type="button" className="dm-dialog-close" onClick={onClose} aria-label="Close">{Icons.Close()}</button>
        </div>
        <div className="dm-dialog-body"><p>{body}</p></div>
        <div className="dm-dialog-foot">
          <Button variant={danger ? 'danger' : 'primary'} onClick={() => { onClose(); onConfirm && onConfirm(); }}>{confirmLabel || 'Confirm'}</Button>
          <Button variant="ghost" onClick={onClose}>Cancel</Button>
        </div>
      </>
    );
  }

  window.DMIcons = Icons;
  window.DMMenu = Menu;
  window.DMModal = Modal;
  window.DMComposeForm = ComposeForm;
  window.DMConfirmBody = ConfirmBody;
})();
