import React from 'react';

const TONE_CLASS = {
  note:   '',                  // gold rule on a brand wash (the default)
  info:   'doc-callout-info',
  warn:   'doc-callout-warn',
  danger: 'doc-callout-danger',
  quiet:  'doc-callout-quiet',
};

/**
 * Callout — an aside for notes, acceptance criteria, flows, and warnings.
 * `tone` recolours the rule + wash (note · info · warn · danger · quiet);
 * `variant="panel"` swaps the gold left-rule for a full hairline box (for keyed
 * cards and flows). A tracked `label` and optional display `title` sit above
 * the body.
 */
export function Callout({
  tone = 'note',
  variant = 'rule',
  label,
  title,
  className = '',
  children,
  ...rest
}) {
  const cls = [
    'doc-callout',
    TONE_CLASS[tone] || '',
    variant === 'panel' ? 'is-panel' : '',
    className,
  ].filter(Boolean).join(' ');
  return (
    <aside className={cls} {...rest}>
      {label ? <div className="doc-callout-label">{label}</div> : null}
      {title ? <div className="doc-callout-title">{title}</div> : null}
      <div className="doc-callout-body">{children}</div>
    </aside>
  );
}
