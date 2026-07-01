import React from 'react';

/**
 * Switch — a preference toggle. Evergreen track + parchment knob when on.
 * Pass `label` for the inline text, or use the bare control via `children`-less
 * form inside your own row.
 */
export function Switch({ checked, defaultChecked, onChange, label, id, disabled, className = '', ...rest }) {
  const control = (
    <input
      type="checkbox"
      id={id}
      className={['switch', className].filter(Boolean).join(' ')}
      checked={checked}
      defaultChecked={defaultChecked}
      onChange={onChange}
      disabled={disabled}
      role="switch"
      {...rest}
    />
  );
  if (label) {
    return (
      <label className="switchline" htmlFor={id}>
        {control}
        <span className="switch-text">{label}</span>
      </label>
    );
  }
  return control;
}
