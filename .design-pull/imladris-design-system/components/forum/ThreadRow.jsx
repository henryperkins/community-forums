import React from 'react';

function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}
function initials(label) {
  const p = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}
function tierClass(tier) {
  return 'tier-' + String(tier || 'member').toLowerCase();
}
const Star = ({ size = 11 }) => (
  <svg viewBox="0 0 100 100" width={size} height={size} aria-hidden="true"><path fill="currentColor" d="M50 16 58.5 41.5 84 50 58.5 58.5 50 84 41.5 58.5 16 50 41.5 41.5Z" /></svg>
);

const STATUS_LABEL = {
  solved: 'Solved',
  needs_answer: 'Needs answer',
  decision_made: 'Decision',
};
const STATUS_CHIP = {
  solved: 'chip-solved',
  needs_answer: 'chip-needs',
  decision_made: 'chip-decision_made',
};

/**
 * ThreadRow — one topic in the Council Inbox. The author is a prominent byline:
 * a gilt-ringed avatar (with presence) beside a name + tier pill + regard
 * (commends earned). Below sits the status chips, the topic title, an optional
 * snippet, and an activity meta line. In compact ("Watch") density the byline
 * folds into the meta line. Put inside a <ul className="thread-list">.
 */
export function ThreadRow({
  title,
  href = '#',
  author,
  authorSeed,
  authorTier,           // 'Member' | 'Veteran' | 'Loremaster' | 'Legend'
  authorRep,            // the author's regard (commends earned) — shown in the byline
  authorHref,
  presence,             // true | 'online' | 'away' | 'offline'
  giltAuthor = false,
  board,
  boardName,
  showBoard = false,
  replies = 0,
  time,
  snippet,
  commends,             // commends on the topic (activity meta)
  status = 'open',
  pinned = false,
  locked = false,
  unread = false,
  starred = false,
  active = false,
  showAvatar = true,
  className = '',
  ...rest
}) {
  const statusSlug = status && status !== 'open' ? status : null;
  const cls = [
    'thread-row',
    unread ? 'thread-unread' : '',
    pinned ? 'thread-pinned' : '',
    locked ? 'thread-locked' : '',
    statusSlug ? `thread-status-${statusSlug}` : '',
    active ? 'is-active' : '',
    className,
  ].filter(Boolean).join(' ');

  const seed = authorSeed || author;
  const mono = (
    <span className={['monogram', monoClass(seed), giltAuthor ? 'monogram-gilt' : ''].filter(Boolean).join(' ')} aria-hidden="true">{initials(author)}</span>
  );
  const dotColor = presence === 'away' ? 'var(--amber)' : presence === 'offline' ? 'var(--ink-300)' : 'var(--presence)';
  const avatar = presence ? (
    <span className="avatar-wrap">{mono}<span className="presence-dot" style={{ background: dotColor }} aria-hidden="true" /></span>
  ) : mono;

  const AuthorName = authorHref ? 'a' : 'span';

  return (
    <li className={cls} {...rest}>
      {unread ? <span className="unread-dot" title="Unread" aria-label="Unread" /> : null}
      {showAvatar ? avatar : null}
      <div className="thread-row-main">
        {author ? (
          <div className="thread-byline">
            <AuthorName className="thread-author" href={authorHref || undefined}>{author}</AuthorName>
            {authorTier ? <span className={`tier ${tierClass(authorTier)}`}>{authorTier}</span> : null}
            {authorRep != null ? <span className="regard"><Star /> {authorRep}</span> : null}
          </div>
        ) : null}
        <div className="thread-row-chips">
          {pinned ? <span className="chip chip-pinned">Pinned</span> : null}
          {statusSlug ? <span className={`chip ${STATUS_CHIP[statusSlug] || ''}`}>{STATUS_LABEL[statusSlug] || statusSlug}</span> : null}
          {locked ? <span className="chip chip-locked">Locked</span> : null}
        </div>
        <a className="thread-title" href={href}>{title}</a>
        {snippet ? <p className="thread-snippet">{snippet}</p> : null}
        <span className="thread-meta">
          {showBoard && board ? <a className="thread-board" href={`/c/${board}`}><span className="hash">#</span>{boardName || board}</a> : null}
          {author ? <span className="thread-meta-author">{author}</span> : null}
          <span>{replies} {replies === 1 ? 'reply' : 'replies'}</span>
          {time ? <span>{time}</span> : null}
          {commends != null ? (
            <span style={{ display: 'inline-flex', alignItems: 'center', gap: 4, color: 'var(--star)' }}>
              <Star /><span className="reaction-n" style={{ color: 'var(--text-faint)' }}>{commends}</span>
            </span>
          ) : null}
        </span>
      </div>
      {starred ? <span className="thread-star" title="Starred" aria-label="Starred">★</span> : null}
    </li>
  );
}
