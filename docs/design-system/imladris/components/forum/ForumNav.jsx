import React from 'react';

/**
 * The member-facing surfaces, in topbar order.
 *
 * `dir`/`file` resolve a real relative href from one template folder to its
 * sibling — the same mechanism ADMIN_AREAS uses — so the bar actually navigates
 * instead of miming it. Board index and Forum inbox had working links; Board
 * page and Thread view had `href="#"` or no nav at all, which is how a member
 * ended up with three different ideas of where "the top of the product" is.
 */
export const FORUM_SURFACES = [
  { key: 'boards',   label: 'Boards',   dir: 'board-index',  file: 'BoardIndex.dc.html' },
  { key: 'inbox',    label: 'Inbox',    dir: 'forum-inbox',  file: 'ForumInbox.dc.html' },
  // No dir/file: there is no messages template yet, so the pill renders inert
  // rather than being aliased to another surface. Two differently-labelled
  // pills landing on one screen — with the OTHER one's pill then highlighted —
  // is worse than a pill that plainly does nothing. Give it a dir and file the
  // day the template exists and it starts working with no other change.
  { key: 'messages', label: 'Messages' },
];

/** House mark off the namespace — brand marks are never redrawn (AdminNav does the same). */
function Mark() {
  const Star = ((typeof window !== 'undefined' && window.ImladrisDesignSystem_c3e027) || {}).EightPointStar;
  return Star ? <Star size={24} /> : null;
}

function Avatar({ name, username, size, presence }) {
  const M = ((typeof window !== 'undefined' && window.ImladrisDesignSystem_c3e027) || {}).Monogram;
  return M ? <M name={name} username={username || name} size={size || 30} presence={presence} /> : null;
}

/**
 * The pane-visibility glyphs: an outlined panel with the shown pane filled.
 * 'rail' fills the left band, 'pane' the right — the same drawing either way,
 * so a surface with two panes reads as one control group and not two ideas.
 */
function ToggleIcon({ icon, on }) {
  const fill = icon === 'pane'
    ? <rect x="9.8" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5" />
    : <rect x="2.2" y="3.2" width="4" height="9.6" rx="1" fill="currentColor" opacity=".5" />;
  return (
    <svg width="16" height="16" viewBox="0 0 16 16" aria-hidden="true">
      <rect x="1.6" y="2.6" width="12.8" height="10.8" rx="1.6" fill="none" stroke="currentColor" strokeWidth="1.3" />
      {on ? fill : null}
    </svg>
  );
}

/**
 * ForumNav — the unifying chrome for every member-facing template.
 *
 * The counterpart to AdminNav on the community side. One row, one register,
 * identical on every app route: house lockup · surface pills · search ·
 * rail toggle · New topic · the viewer.
 *
 * Why this exists: the four member templates each hand-rolled this bar and
 * drifted apart on every axis at once — 62px against 58px, the mark in
 * `--accent` against `--green-700`, three surface pills against two against
 * none, a real search link against a dead `<span>` against a working palette,
 * a hand-rolled initials circle against the Monogram component. Nothing a
 * member reaches for stayed in the same place between screens, so moving from
 * the index to a board to a topic read as three unrelated products. The bar is
 * now one component: the click targets cannot drift because there is only one
 * copy of them.
 *
 * `surface` is the only prop a template must set. Everything else degrades:
 * no `viewer` renders the guest sign-in, no `onToggleRail` drops the toggle
 * (Thread view has no rail to toggle), no `onSearch` falls back to a real href
 * to the Search template. `viewer.presence` ('online' | 'away') puts the
 * viewer's own leaf on the seat; leave it off and no leaf is drawn — a member
 * who switched presence off must not see one beside their own name, which is
 * the control's own promise (ADR 0031).
 *
 * ⌘K and ⌘B are bound here rather than per-template, which is the point of
 * putting them in shared chrome — a shortcut that works on two screens out of
 * four is worse than none.
 */
export function ForumNav({
  surface,
  surfaces = FORUM_SURFACES,
  inboxCount = 0,
  searchHref = '../search/Search.dc.html',
  searchLabel = 'Search the council…',
  onSearch,
  searchOpen = false,
  showSearch = true,
  railOpen = true,
  onToggleRail,
  toggles = null,
  composeHref = '../compose/Compose.dc.html',
  composeLabel = 'New topic',
  onCompose,
  showCompose = true,
  viewer = null,
  signInHref = '#',
  children = null,
  className = '',
  ...rest
}) {
  const count = Number(inboxCount) || 0;

  React.useEffect(() => {
    function onKey(e) {
      const mod = e.metaKey || e.ctrlKey;
      if (!mod) return;
      const k = (e.key || '').toLowerCase();
      if (k === 'k') {
        e.preventDefault();
        if (onSearch) onSearch();
        else if (searchHref) window.location.href = searchHref;
      } else if (k === 'b' && onToggleRail) {
        e.preventDefault();
        onToggleRail();
      }
    }
    window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [onSearch, searchHref, onToggleRail]);

  const Button = ((typeof window !== 'undefined' && window.ImladrisDesignSystem_c3e027) || {}).Button;

  return (
    <div className={['forum-bar', className].filter(Boolean).join(' ')} {...rest}>
      <a className="forum-bar-brand" href={surfaces[0].dir ? `../${surfaces[0].dir}/${surfaces[0].file}` : undefined} aria-label="Imladris — the council">
        <Mark />
        <span className="forum-bar-wordmark">Imladris</span>
      </a>

      <nav className="forum-bar-surfaces" aria-label="Primary">
        {surfaces.map((s) => {
          const active = s.key === surface;
          // An entry with no dir/file has nowhere to go yet: rendered as a
          // span, so it is neither a dead link nor a lie about where it leads.
          const routable = !!(s.dir && s.file);
          const cls = ['forum-bar-surface', active ? 'is-active' : '', routable ? '' : 'is-inert'].filter(Boolean).join(' ');
          const pill = s.key === 'inbox' && count > 0
            ? <span className="forum-bar-count">{count}</span>
            : null;
          if (!routable) {
            return <span key={s.key} className={cls} aria-disabled="true">{s.label}{pill}</span>;
          }
          return (
            <a key={s.key} className={cls} aria-current={active ? 'page' : undefined}
              href={active ? undefined : `../${s.dir}/${s.file}`}>
              {s.label}{pill}
            </a>
          );
        })}
      </nav>

      {showSearch ? (
      <span className="forum-bar-searchwrap">
        {onSearch ? (
          <button type="button" className="forum-bar-search" onClick={onSearch}
            aria-label="Search the council — Command K" aria-expanded={!!searchOpen} aria-haspopup="dialog">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
            <span className="forum-bar-search-label">{searchLabel}</span>
            <span className="forum-bar-kbd">⌘K</span>
          </button>
        ) : (
          <a className="forum-bar-search" href={searchHref} aria-label="Search the council — Command K">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
            <span className="forum-bar-search-label">{searchLabel}</span>
            <span className="forum-bar-kbd">⌘K</span>
          </a>
        )}
        {/* The palette slot. A surface that owns a ⌘K palette passes it as
            children and it anchors to this field — which is why the wrapper is
            the positioned element rather than the control. */}
        {children}
      </span>
      ) : null}

      <span className="forum-bar-right">
        {(toggles && toggles.length
          ? toggles
          : (onToggleRail ? [{ key: 'rail', icon: 'rail', on: !!railOpen, onClick: onToggleRail, title: railOpen ? 'Hide the board rail (⌘B)' : 'Show the board rail (⌘B)', label: railOpen ? 'Hide the board rail' : 'Show the board rail' }] : [])
        ).map((t) => (
          <button key={t.key} type="button"
            className={['forum-bar-railtoggle', t.on ? 'is-on' : ''].filter(Boolean).join(' ')}
            onClick={t.onClick} aria-pressed={!!t.on} title={t.title} aria-label={t.label || t.title}>
            <ToggleIcon icon={t.icon} on={t.on} />
          </button>
        ))}

        <span className="forum-bar-divider" aria-hidden="true" />

        {showCompose ? (
          <span className="forum-bar-compose">
            {Button ? (
              <Button size="sm" href={onCompose ? undefined : composeHref} onClick={onCompose}>{composeLabel}</Button>
            ) : null}
          </span>
        ) : null}

        {viewer ? (
          <a className="forum-bar-user" href={viewer.href || '../user-profile/UserProfile.dc.html'} aria-label={viewer.name}>
            <Avatar name={viewer.name} username={viewer.username} size={30} presence={viewer.presence === 'online' || viewer.presence === 'away' ? viewer.presence : undefined} />
            <span className="forum-bar-username">{viewer.name}</span>
          </a>
        ) : (
          <a className="forum-bar-signin" href={signInHref}>Log in</a>
        )}
      </span>
    </div>
  );
}
