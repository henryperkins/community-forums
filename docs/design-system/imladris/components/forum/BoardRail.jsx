import React from 'react';

/**
 * The named route glyphs. Routes are passed as data from a template, so the
 * icon travels as a name rather than a node — a template cannot pass an SVG.
 */
const ROUTE_ICONS = {
  home: 'M3 11.5 12 4l9 7.5|M5 10v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9',
  inbox: 'M22 12h-6l-2 3h-4l-2-3H2|M5.5 5.5 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.4-6.5A2 2 0 0 0 16.8 4H7.2a2 2 0 0 0-1.7 1.5z',
  messages: 'M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z',
  drafts: 'M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z|M14 2v6h6',
  following: 'M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2|M22 21v-2a4 4 0 0 0-3-3.87|M16 3.13a4 4 0 0 1 0 7.75',
  trophy: 'M8 21h8|M12 17v4|M7 4h10v4a5 5 0 0 1-10 0z|M5 4H3v2a3 3 0 0 0 3 3|M19 4h2v2a3 3 0 0 1-3 3',
};

function RouteIcon({ name }) {
  const d = ROUTE_ICONS[name];
  if (!d) return null;
  return (
    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {d.split('|').map((p, i) => <path key={i} d={p} />)}
      {name === 'following' ? <circle cx="9" cy="7" r="4" /> : null}
    </svg>
  );
}

/**
 * BoardRail — the shared board rail, the member's fixed point.
 *
 * Boards and nothing else, plus whatever a surface hangs beneath them (the
 * public roster on the index, nothing on a board). Every member-facing
 * template that has a rail renders THIS rail, so the board a member clicked
 * on the index is in the same place, at the same width, in the same order,
 * once they get where they are going.
 *
 * Why this exists: the rail was copy-pasted per template and drifted — Board
 * index paid 20px of top padding and carried the roster, Board page paid 18px
 * and carried a "Forum inbox" row instead, Thread view had no rail at all.
 * The active board was marked with a gold left border on one screen and not
 * marked at all on another, so a member could not tell where they were from
 * the one element that is supposed to tell them.
 *
 * `activeBoard` is matched against each board's `key`. Boards carry their own
 * `href` (default: the Board page template), so the rail navigates for real;
 * pass `onNavigate` to intercept and route in-page instead.
 *
 * The footer slot is rail furniture, not surface content: every member surface
 * that has a rail hangs the same roster + "See everyone" there, because a block
 * that appears and disappears as you click through is the same defect as a
 * board list that changes. Leaderboard is the one exception — its rail owns a
 * `routes` list instead — and Compose's picker keeps the roster too.
 */
export function BoardRail({
  categories = [],
  activeBoard,
  onNavigate,
  boardHref,
  open = true,
  routes = null,
  activeNote,
  ariaLabel = 'Boards',
  children,
  className = '',
  ...rest
}) {
  if (!open) return null;

  const hrefFor = (b) => {
    if (b.href) return b.href;
    if (boardHref) return typeof boardHref === 'function' ? boardHref(b) : boardHref;
    return '../board-page/BoardPage.dc.html';
  };

  return (
    <nav className={['board-rail', className].filter(Boolean).join(' ')} aria-label={ariaLabel} {...rest}>
      {routes && routes.length ? (
        <div className="board-rail-routes">
          {routes.map((r, i) => (
            <a key={r.key || i}
              className={['board-rail-route', r.active ? 'is-active' : ''].filter(Boolean).join(' ')}
              href={r.active ? undefined : (r.href || '#')}
              aria-current={r.active ? 'page' : undefined}>
              <RouteIcon name={r.icon} />
              <span className="board-rail-route-text">
                <span className="board-rail-route-label">{r.label}</span>
                {r.sub ? <span className="board-rail-route-sub">{r.sub}</span> : null}
              </span>
            </a>
          ))}
        </div>
      ) : null}

      {categories.map((cat, ci) => (
        <React.Fragment key={cat.key || cat.name || ci}>
          <span className="board-rail-cat">{cat.name}</span>
          {(cat.boards || []).map((b, bi) => {
            const active = b.key !== undefined && b.key === activeBoard;
            const cls = ['board-rail-item', active ? 'is-active' : '', b.locked ? 'is-locked' : ''].filter(Boolean).join(' ');
            const unread = Number(b.unread) || 0;
            const label = b.unreadLabel || (unread ? `${unread} unread` : undefined);
            const pill = unread > 0
              ? <span className="board-rail-unread" title={label} aria-label={label}>{unread}</span>
              : null;
            // A board you may not post to is shown and not pickable, rather
            // than quietly missing — so it is a <span>, not a dead link.
            if (b.locked) {
              return (
                <span key={b.key || b.name || bi} className={cls} title={b.lockedTitle || 'You cannot post here'}>
                  <span className="board-rail-name">{b.name}</span>
                  {pill}
                  <svg className="board-rail-lock" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><rect x="4" y="11" width="16" height="10" rx="2" /><path d="M8 11V7a4 4 0 018 0v4" /></svg>
                </span>
              );
            }
            return (
              <a key={b.key || b.name || bi} className={cls}
                href={active ? undefined : hrefFor(b)}
                aria-current={active ? 'page' : undefined}
                onClick={onNavigate ? (e) => { e.preventDefault(); onNavigate(b.key, b); } : undefined}>
                <span className="board-rail-name">{b.name}</span>
                {pill}
                {b.tag ? <span className="board-rail-tag">{b.tag}</span> : null}
                {active && activeNote ? <span className="board-rail-note">{activeNote}</span> : null}
              </a>
            );
          })}
        </React.Fragment>
      ))}

      {children ? <div className="board-rail-foot">{children}</div> : null}
    </nav>
  );
}
