import React from 'react';

// The system lens — the glyph ForumNav's search entry draws.
const SEARCH_GLYPH = (
  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.9" strokeLinecap="round" aria-hidden="true" focusable="false">
    <circle cx="11" cy="11" r="7" /><path d="M20 20l-3.6-3.6" />
  </svg>
);

/**
 * Input — a serif text field with a gold focus halo. `pill` makes the rounded
 * search-bar style. `icon` sets a decorative glyph in the leading padding
 * ('search' draws the system lens; any node works). Wraps in a labelled field
 * when `label` is given.
 */
export function Input({ pill = false, label, icon, id, className = '', ...rest }) {
  let control = (
    <input
      id={id}
      className={['input', pill ? 'input-pill' : '', className].filter(Boolean).join(' ')}
      {...rest}
    />
  );
  if (icon) {
    control = (
      <span className="input-affix">
        <span className="input-affix-icon" aria-hidden="true">{icon === 'search' ? SEARCH_GLYPH : icon}</span>
        {control}
      </span>
    );
  }
  if (label) {
    return (
      <label className="field" htmlFor={id}>
        <span className="field-label">{label}</span>
        {control}
      </label>
    );
  }
  return control;
}
