/* Organizing the rail — board_folders · saved_feeds · bookmark_folders.
   Imladris feature-activation design. Loaded via Babel from index.html. */
const { useState, useEffect, useRef } = React;
const DS = window.ImladrisDesignSystem_c3e027;
const { Button } = DS;

/* ── icons ──────────────────────────────────────────────────── */
const stroke = (d, s) => (
  <svg viewBox="0 0 24 24" width={s || 14} height={s || 14} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">{d}</svg>
);
const Chevron = ({ s }) => stroke(<path d="M6 9l6 6 6-6" />, s);
const Plus = ({ s }) => stroke(<path d="M12 5v14M5 12h14" />, s);
const XIcon = ({ s }) => stroke(<path d="M18 6 6 18M6 6l12 12" />, s);
const Check = ({ s }) => stroke(<path d="M20 6L9 17l-5-5" />, s);
const Grip = () => (
  <svg viewBox="0 0 16 16" width="13" height="13" fill="currentColor" aria-hidden="true">
    <circle cx="5" cy="4" r="1.25" /><circle cx="11" cy="4" r="1.25" /><circle cx="5" cy="8" r="1.25" /><circle cx="11" cy="8" r="1.25" /><circle cx="5" cy="12" r="1.25" /><circle cx="11" cy="12" r="1.25" />
  </svg>
);
const FeedGlyph = ({ s }) => stroke(<g><path d="M4 11a9 9 0 0 1 9 9" /><path d="M4 4a16 16 0 0 1 16 16" /><circle cx="5" cy="19" r="1.4" fill="currentColor" stroke="none" /></g>, s);
const FilterGlyph = ({ s }) => stroke(<path d="M3 5h18l-7 8v6l-4-2v-4z" />, s);
const FolderGlyph = ({ s }) => stroke(<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z" />, s);
const EightStar = ({ size }) => (
  <svg viewBox="0 0 100 100" width={size} height={size} fill="currentColor" aria-hidden="true">
    <path d="M50 3 63.8 16.7 83.2 16.8 83.3 36.2 97 50 83.3 63.8 83.2 83.2 63.8 83.3 50 97 36.2 83.3 16.8 83.2 16.7 63.8 3 50 16.7 36.2 16.8 16.8 36.2 16.7Z" />
  </svg>
);
const SmallStar = ({ s }) => (
  <svg viewBox="0 0 100 100" width={s || 13} height={s || 13} fill="currentColor" aria-hidden="true">
    <path d="M50 8 61 39 94 39 67 59 78 92 50 72 22 92 33 59 6 39 39 39Z" />
  </svg>
);
const Leaf = ({ s }) => stroke(<path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z" />, s);

const FEED_COLORS = ['var(--gold-500)', 'var(--accent-2)', 'var(--success)', 'var(--info)', 'var(--violet-500, #7c6bb0)'];

/* dimmed canvas placeholder so the rail reads as part of the app */
function Canvas({ children }) {
  return (
    <div className="shell-canvas">
      {children}
      <div className="canvas-skeleton" aria-hidden="true">
        {[88, 72, 64].map((w, i) => (
          <div className="sk-row" key={i}>
            <div className="sk-av" />
            <div className="sk-lines">
              <div className="sk-line" style={{ width: '46%' }} />
              <div className="sk-line" style={{ width: w + '%' }} />
              <div className="sk-line" style={{ width: (w - 18) + '%' }} />
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════
   01 · board_folders — collapsible, reorderable board folders
   ════════════════════════════════════════════════════════════ */
const SEED_FOLDERS = [
  { id: 'commons', name: 'The Commons', collapsed: false, boards: [
    { slug: 'announcements', name: 'announcements', count: 12 },
    { slug: 'introductions', name: 'introductions', count: 31 },
    { slug: 'the-valley', name: 'the-valley', count: 88 },
  ] },
  { id: 'vilya', name: 'Vilya · Expose', collapsed: false, boards: [
    { slug: 'interpretability', name: 'interpretability', count: 47 },
    { slug: 'evaluations', name: 'evaluations', count: 63 },
    { slug: 'capability-disclosure', name: 'capability-disclosure', count: 22 },
    { slug: 'audit-trails', name: 'audit-trails', count: 39 },
  ] },
];

function RailBoards() {
  const [folders, setFolders] = useState(SEED_FOLDERS);
  const [active, setActive] = useState('evaluations');
  const [organizing, setOrganizing] = useState(false);
  const seq = useRef(1);

  const toggle = (id) => setFolders((fs) => fs.map((f) => f.id === id ? { ...f, collapsed: !f.collapsed } : f));
  const rename = (id, name) => setFolders((fs) => fs.map((f) => f.id === id ? { ...f, name } : f));
  const removeFolder = (id) => setFolders((fs) => fs.filter((f) => f.id !== id));
  const addFolder = () => setFolders((fs) => [...fs, { id: 'new' + seq.current++, name: 'New folder', collapsed: false, boards: [] }]);

  return (
    <div className="shell">
      <nav className="rail" aria-label="Boards">
        <div className="rail-head">
          <span className="rail-head-title">Boards</span>
          <button className={'rail-organize' + (organizing ? ' is-on' : '')} onClick={() => setOrganizing((v) => !v)}>
            {organizing ? 'Done' : 'Organize'}
          </button>
        </div>

        {folders.map((f) => (
          <div className={'folder' + (f.collapsed ? ' is-collapsed' : '')} key={f.id}>
            <div className="folder-head" style={{ cursor: organizing ? 'default' : 'pointer' }}>
              {organizing
                ? <span className="folder-grip" title="Drag to reorder"><Grip /></span>
                : <button className="folder-chev" onClick={() => toggle(f.id)} aria-label={f.collapsed ? 'Expand' : 'Collapse'} aria-expanded={!f.collapsed} style={{ background: 'none', border: 0, padding: 0, cursor: 'pointer' }}><Chevron s={15} /></button>}
              {organizing ? (
                <span className="folder-name"><input value={f.name} onChange={(e) => rename(f.id, e.target.value)} aria-label="Folder name" /></span>
              ) : (
                <button className="folder-name" onClick={() => toggle(f.id)} style={{ background: 'none', border: 0, padding: 0, textAlign: 'left', cursor: 'pointer', color: 'inherit', letterSpacing: 'inherit', textTransform: 'inherit' }}>{f.name}</button>
              )}
              {organizing
                ? <button className="folder-chev" onClick={() => removeFolder(f.id)} aria-label="Remove folder" style={{ background: 'none', border: 0, padding: 0, cursor: 'pointer', color: 'var(--text-faint)' }}><XIcon s={13} /></button>
                : <span className="folder-count">{f.boards.length}</span>}
            </div>
            <div className="folder-body">
              <div className="folder-body-inner">
                <ul className="nav-boards">
                  {f.boards.map((b) => (
                    <li key={b.slug}>
                      <button className={'board-btn' + (active === b.slug ? ' active' : '')} onClick={() => setActive(b.slug)}>
                        {organizing ? <span className="board-grip"><Grip /></span> : <span className="hash">#</span>}
                        <span className="board-name">{b.name}</span>
                        <span className="board-count">{b.count}</span>
                      </button>
                    </li>
                  ))}
                  {f.boards.length === 0 ? <li style={{ padding: '4px 11px', fontStyle: 'italic', color: 'var(--text-faint)', fontSize: 13 }}>Drag boards here</li> : null}
                </ul>
              </div>
            </div>
          </div>
        ))}

        {organizing ? <button className="rail-add" onClick={addFolder}><Plus s={12} /> New folder</button> : null}
      </nav>
      <Canvas>
        <div className="canvas-filterbar">
          <span className="filter-label">Viewing</span>
          <span className="filter-chip"><span className="hash" style={{ color: 'var(--brand)' }}>#</span>{active}</span>
        </div>
      </Canvas>
    </div>
  );
}

/* ════════════════════════════════════════════════════════════
   02 · saved_feeds — save a filter as a named feed in the rail
   ════════════════════════════════════════════════════════════ */
const SEED_FEEDS = [
  { id: 'unsolved', name: 'Unsolved in evals', color: 'var(--gold-500)', count: 7 },
  { id: 'mine', name: 'Mentions of me', color: 'var(--accent-2)', count: 2 },
  { id: 'decisions', name: 'Decisions log', color: 'var(--success)', count: 4 },
];
const DRAFT_FILTER = [
  { label: 'Unsolved', kind: 'status' },
  { label: '#interpretability', kind: 'board' },
  { label: 'last 7 days', kind: 'time' },
];

function SaveFeed() {
  const [feeds, setFeeds] = useState(SEED_FEEDS);
  const [active, setActive] = useState('unsolved');
  const [saving, setSaving] = useState(false);
  const [name, setName] = useState('Open interpretability');
  const [color, setColor] = useState(FEED_COLORS[3]);
  const [justSaved, setJustSaved] = useState(null);

  const save = () => {
    const id = 'f' + Date.now();
    setFeeds((fs) => [...fs, { id, name: name.trim() || 'New feed', color, count: 5, fresh: true }]);
    setActive(id); setJustSaved(id); setSaving(false);
  };

  return (
    <div className="shell">
      <nav className="rail" aria-label="Feeds and boards">
        <div className="rail-section-label"><span className="leaf"><FeedGlyph s={13} /></span> Saved feeds</div>
        {feeds.map((f) => (
          <button key={f.id} className={'feed-btn' + (active === f.id ? ' active' : '') + (f.fresh ? ' feed-new' : '')} onClick={() => setActive(f.id)}>
            <span className="feed-dot" style={{ background: f.color }} />
            <span className="feed-name">{f.name}</span>
            <span className="feed-count">{f.count}</span>
          </button>
        ))}
        <div className="rail-divider" />
        <div className="rail-section-label">Boards</div>
        {['announcements', 'interpretability', 'evaluations', 'audit-trails'].map((b) => (
          <button key={b} className="board-btn" onClick={() => setActive(null)}>
            <span className="hash">#</span><span className="board-name">{b}</span>
          </button>
        ))}
      </nav>
      <Canvas>
        <div className="canvas-filterbar">
          <span className="filter-label">Filter</span>
          {DRAFT_FILTER.map((c) => (
            <span className="filter-chip" key={c.label}>{c.label}<span className="x"><XIcon s={12} /></span></span>
          ))}
          {!saving && !justSaved ? <Button variant="outline" size="sm" onClick={() => setSaving(true)}>Save as feed</Button> : null}
          {justSaved ? <span className="filter-chip" style={{ color: 'var(--on-done)', background: 'var(--surface-done)', borderColor: 'var(--green-200)' }}><Check s={12} /> Saved to rail</span> : null}
        </div>
        {saving ? (
          <div className="savefeed">
            <span className="savefeed-icon"><FeedGlyph s={15} /></span>
            <div className="savefeed-fields">
              <input className="ds-input" value={name} onChange={(e) => setName(e.target.value)} placeholder="Name this feed" autoFocus
                     style={{ flex: 1, minWidth: 140, font: 'inherit', fontFamily: 'var(--font-body), serif', fontSize: 15, color: 'var(--text-strong)', background: 'var(--surface-raised)', border: '1px solid var(--border-hair)', borderRadius: 'var(--radius-md)', padding: '8px 12px' }} />
              <span className="savefeed-color" role="radiogroup" aria-label="Feed colour">
                {FEED_COLORS.map((c) => (
                  <button key={c} className={'swatch' + (color === c ? ' is-on' : '')} style={{ background: c }} onClick={() => setColor(c)} aria-label="colour" aria-pressed={color === c} />
                ))}
              </span>
            </div>
            <div style={{ display: 'flex', gap: 8 }}>
              <Button variant="ghost" size="sm" onClick={() => setSaving(false)}>Cancel</Button>
              <Button variant="primary" size="sm" onClick={save}>Save feed</Button>
            </div>
          </div>
        ) : null}
      </Canvas>
    </div>
  );
}
