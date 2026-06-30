import React from 'react';

/**
 * Input — a serif text field with a gold focus halo. `pill` makes the rounded
 * search-bar style. Wraps in a labelled field when `label` is given.
 */
export function Input({ pill = false, label, id, className = '', ...rest }) {
  const input = (
    <input
      id={id}
      className={['input', pill ? 'input-pill' : '', className].filter(Boolean).join(' ')}
      {...rest}
    />
  );
  if (label) {
    return (
      <label className="field" htmlFor={id}>
        <span className="field-label">{label}</span>
        {input}
      </label>
    );
  }
  return input;
}
