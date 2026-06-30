import React from 'react';

/**
 * ChoiceCard — a large radio "card" for picking one of a small set (theme,
 * density). Selecting fills it with the brand wash + an inner ring. Pass a
 * `swatch` node (e.g. a theme preview) above the title.
 */
export function ChoiceCard({
  name,
  value,
  checked,
  defaultChecked,
  onChange,
  title,
  desc,
  swatch,
  className = '',
  ...rest
}) {
  return (
    <label className={['choice-card', className].filter(Boolean).join(' ')}>
      <input
        type="radio"
        name={name}
        value={value}
        checked={checked}
        defaultChecked={defaultChecked}
        onChange={onChange}
        {...rest}
      />
      {swatch ? <span aria-hidden="true">{swatch}</span> : null}
      <span className="choice-card-title">{title}</span>
      {desc ? <span className="choice-card-desc">{desc}</span> : null}
    </label>
  );
}
