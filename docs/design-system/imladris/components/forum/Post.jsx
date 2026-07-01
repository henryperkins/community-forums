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

/**
 * Post — one message in a conversation. A decorated identity column (gilt
 * avatar with presence + a stacked regard plinth) sits beside a head row
 * (name + tier + OP/Staff/Wiki badges + time), a signature line (handle ·
 * title), the body, and reactions. `op`/`accepted` gild the avatar; `accepted`
 * adds the green answer plate; `grouped` drops the repeated identity for a
 * consecutive same-author reply.
 */
export function Post({
  author,
  authorSeed,
  authorHref,
  authorTier,           // 'Member' | 'Veteran' | 'Loremaster' | 'Legend'
  handle,               // @handle (signature line)
  authorTitle,          // the member's title / signature (e.g. "Lady of the Wood")
  presence,             // true | 'online' | 'away' | 'offline'
  time,
  edited = false,
  op = false,
  staff = false,
  wiki = false,
  accepted = false,
  grouped = false,
  rep,                  // regard (commends earned) — the avatar plinth
  reactions,
  children,
  className = '',
  ...rest
}) {
  const cls = [
    'post',
    op ? 'post-op' : '',
    accepted ? 'post-accepted' : '',
    grouped ? 'post-grouped' : '',
    className,
  ].filter(Boolean).join(' ');
  const seed = authorSeed || author;
  const gilt = op || accepted;

  const mono = (
    <span className={['monogram', 'monogram-lg', monoClass(seed), gilt ? 'monogram-gilt' : ''].filter(Boolean).join(' ')} aria-hidden="true">{initials(author)}</span>
  );
  const dotColor = presence === 'away' ? 'var(--amber)' : presence === 'offline' ? 'var(--ink-300)' : 'var(--presence)';
  const avatar = presence ? (
    <span className="avatar-wrap">{mono}<span className="presence-dot" style={{ background: dotColor }} aria-hidden="true" /></span>
  ) : mono;

  const hasSign = handle || authorTitle;

  return (
    <div className={cls} {...rest}>
      {grouped ? (
        <span className="post-avatar-spacer" aria-hidden="true" />
      ) : (
        <div className="post-avatar">
          {avatar}
          {rep != null ? (
            <span className="regard-block">
              <span className="regard-n"><span className="star-marker" aria-hidden="true">✦</span>{rep}</span>
              <span className="regard-label">Commends</span>
            </span>
          ) : null}
        </div>
      )}
      <div className="post-main">
        {accepted ? (
          <p className="accepted-flag">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 6L9 17l-5-5" /></svg>
            Marked as the answer
            <span className="star-marker" aria-hidden="true" style={{ marginLeft: 2 }}>✦</span>
          </p>
        ) : null}
        {!grouped ? (
          <>
            <div className="post-head">
              {authorHref
                ? <a className="post-author" href={authorHref}>{author}</a>
                : <span className="post-author">{author}</span>}
              {authorTier ? <span className={`tier ${tierClass(authorTier)}`}>{authorTier}</span> : null}
              {op ? <span className="badge">OP</span> : null}
              {wiki ? <span className="badge">Wiki</span> : null}
              {staff ? <span className="badge badge-staff">Staff</span> : null}
              {time ? <span className="post-time">{time}{edited ? ' · edited' : ''}</span> : null}
            </div>
            {hasSign ? (
              <p className="post-sign">
                {handle ? <span className="sign-handle">@{handle}</span> : null}
                {handle && authorTitle ? ' · ' : null}
                {authorTitle ? <span className="sign-title">{authorTitle}</span> : null}
              </p>
            ) : null}
          </>
        ) : (
          time ? <div className="post-head"><span className="post-time" style={{ marginLeft: 0 }}>{time}{edited ? ' · edited' : ''}</span></div> : null
        )}
        <div className="post-body">{children}</div>
        {reactions ? <div className="reactions">{reactions}</div> : null}
      </div>
    </div>
  );
}
