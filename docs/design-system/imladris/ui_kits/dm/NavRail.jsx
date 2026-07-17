/* Messages kit — product nav rail (left-most column). Grounds Messages INSIDE
   the forum chrome, mirroring the flagship inbox: Home / Inbox / Messages
   (active) / Following / Drafts, then a quiet "Direct" section. This is the
   consolidation move — DMs read as one place in the product, not a floating
   island. Static chrome; the active item is Messages. */
(function () {
  const item = (icon, label, opts) => ({ icon, label, ...(opts || {}) });

  function NavRail({ onNewMessage }) {
    // Lucide-register glyphs, matching the forum inbox nav exactly.
    const I = {
      home:      <svg viewBox="0 0 24 24"><path d="M3 11.5 12 4l9 7.5"/><path d="M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9"/></svg>,
      inbox:     <svg viewBox="0 0 24 24"><path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z"/></svg>,
      messages:  <svg viewBox="0 0 24 24"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>,
      following: <svg viewBox="0 0 24 24"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>,
      drafts:    <svg viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></svg>,
    };
    return (
      <nav className="dm-navrail" aria-label="Primary">
        <a className="dm-nav-item" href="../retroboards/index.html">
          <span className="dm-nav-ic">{I.home}</span><span>Home</span>
        </a>
        <a className="dm-nav-item" href="#inbox" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.inbox}</span><span>Inbox</span>
          <span className="dm-nav-count">7</span>
        </a>
        <a className="dm-nav-item is-active" href="#messages" aria-current="page" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.messages}</span><span>Messages</span>
          <span className="dm-nav-dot" aria-hidden="true" />
        </a>
        <a className="dm-nav-item" href="#following" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.following}</span><span>Following</span>
        </a>
        <a className="dm-nav-item" href="#drafts" onClick={(e) => e.preventDefault()}>
          <span className="dm-nav-ic">{I.drafts}</span><span>Drafts</span>
        </a>

        <div className="dm-nav-sec">
          <div className="dm-nav-sec-head">Direct</div>
          <button type="button" className="dm-nav-compose" onClick={onNewMessage}>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
            New message
          </button>
        </div>
      </nav>
    );
  }
  window.DMNavRail = NavRail;
})();
