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

/**
 * ParticipantStack — overlapping small avatars for a topic's participants, with
 * an optional "+N" overflow. Used in the conversation header.
 *
 * A member entry may carry `href` (the avatar becomes a link to where that
 * person entered the conversation), `title` (the hover/AT description — their
 * post count, whether they opened it or wrote the answer) and `gilt` (the gold
 * ring that marks the opener). Bare names still work.
 */
export function ParticipantStack({ members = [], max = 5, extra, className = '', ...rest }) {
  const shown = members.slice(0, max);
  const overflow = extra != null ? extra : Math.max(0, members.length - shown.length);
  return (
    <span className={['participant-stack', className].filter(Boolean).join(' ')} {...rest}>
      {shown.map((m, i) => {
        const o = typeof m === 'string' ? { name: m } : m;
        const seed = o.username || o.name;
        const mono = (
          <span className={['monogram', 'monogram-sm', monoClass(seed)].join(' ')} aria-hidden="true">{initials(o.name)}</span>
        );
        if (!o.href) return <React.Fragment key={i}>{mono}</React.Fragment>;
        return (
          <a key={i} className={o.gilt ? 'is-opener' : undefined} href={o.href} title={o.title || o.name} aria-label={o.title || o.name}>{mono}</a>
        );
      })}
      {overflow > 0 ? <span className="participant-more">+{overflow}</span> : null}
    </span>
  );
}
