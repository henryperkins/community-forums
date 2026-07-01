import React from 'react';

const TONE_CLASS = { default: '', admin: 'pill-admin', online: 'pill-online' };

/**
 * Pill — a small lapidary-caps status token (e.g. "Guest", "Admin", "Online").
 * Distinct from Badge (role) and Chip (topic status); Pill is for identity /
 * presence states.
 */
export function Pill({ tone = 'default', className = '', children, ...rest }) {
  const cls = ['pill', TONE_CLASS[tone] || '', className].filter(Boolean).join(' ');
  return <span className={cls} {...rest}>{children}</span>;
}
