import React from 'react';

/**
 * Textarea — the serif multi-line field used by the composer and forms. Auto
 * gold focus halo; vertically resizable.
 */
export function Textarea({ label, id, rows = 4, className = '', ...rest }) {
  const ta = (
    <textarea
      id={id}
      rows={rows}
      className={['textarea', className].filter(Boolean).join(' ')}
      {...rest}
    />
  );
  if (label) {
    return (
      <label className="field" htmlFor={id}>
        <span className="field-label">{label}</span>
        {ta}
      </label>
    );
  }
  return ta;
}
