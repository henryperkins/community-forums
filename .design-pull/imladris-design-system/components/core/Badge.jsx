import React from 'react';

// Role/author badges. OP and Wiki use the base green badge; Staff is gold so it
// never reads as the green OP badge; muted is neutral; solved is the outlined
// accepted-answer marker.
const VARIANT = {
  op:     { cls: '', label: 'OP' },
  wiki:   { cls: '', label: 'Wiki' },
  staff:  { cls: 'badge-staff', label: 'Staff' },
  muted:  { cls: 'badge-muted', label: null },
  solved: { cls: 'badge-solved', label: 'Solved' },
};

/**
 * Badge — a role / author marker shown inline with a name (OP, Staff, Wiki) or
 * a small outlined status (solved). For topic-status pills in the inbox use
 * Chip; for identity/presence use Pill.
 */
export function Badge({ variant = 'op', className = '', children, ...rest }) {
  const v = VARIANT[variant] || VARIANT.op;
  const cls = ['badge', v.cls, className].filter(Boolean).join(' ');
  return <span className={cls} {...rest}>{children != null ? children : v.label}</span>;
}
