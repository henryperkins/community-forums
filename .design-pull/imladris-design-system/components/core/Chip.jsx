import React from 'react';

// Topic-status chips. Each maps to a status class; the inbox row also carries a
// matching coloured left-rule (see ThreadRow). Text label is required so status
// is never carried by colour alone.
const STATUS = {
  solved:        { cls: 'chip-solved',        label: 'Solved' },
  needs:         { cls: 'chip-needs',         label: 'Needs answer' },
  needs_answer:  { cls: 'chip-needs',         label: 'Needs answer' },
  decision_made: { cls: 'chip-decision_made', label: 'Decision' },
  pinned:        { cls: 'chip-pinned',        label: 'Pinned' },
  locked:        { cls: 'chip-locked',        label: 'Locked' },
  archived:      { cls: 'chip-archived',      label: 'Archived' },
};

/**
 * Chip — a topic-status pill (Solved, Needs answer, Decision, Pinned, Locked,
 * Archived). Always carries a word, never colour alone. Pass `icon` to prepend
 * a glyph (e.g. a Lucide circle-check); the inbox usually shows text only.
 */
export function Chip({ status = 'solved', icon, className = '', children, ...rest }) {
  const s = STATUS[status] || STATUS.solved;
  const cls = ['chip', s.cls, className].filter(Boolean).join(' ');
  return (
    <span className={cls} {...rest}>
      {icon ? <span aria-hidden="true" style={{ display: 'inline-flex' }}>{icon}</span> : null}
      {children != null ? children : s.label}
    </span>
  );
}
