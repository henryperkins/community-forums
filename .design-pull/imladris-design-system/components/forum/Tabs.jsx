import React from 'react';

const VARIANT = {
  pill:      { wrap: 'inbox-tabs', item: 'inbox-tab' },     // filter pills (All / Unread / Starred / Mine)
  segment:   { wrap: 'segmented',  item: 'segmented-item' }, // segmented control (Hall / Watch)
  underline: { wrap: 'text-tabs',  item: 'text-tab' },       // underline tabs (Active / Newest · profile tabs)
};

/**
 * Tabs — the Imladris tab set in three registers:
 *   · pill      — inbox filter pills (gold-fill active)
 *   · segment   — a segmented toggle (Hall / Watch density)
 *   · underline — quiet underline tabs (sort, profile sections)
 * Controlled via `value` + `onChange`.
 */
export function Tabs({ items = [], value, onChange, variant = 'pill', className = '', ...rest }) {
  const v = VARIANT[variant] || VARIANT.pill;
  return (
    <div className={[v.wrap, className].filter(Boolean).join(' ')} role="tablist" {...rest}>
      {items.map((it) => {
        const val = typeof it === 'string' ? it : it.value;
        const label = typeof it === 'string' ? it : it.label;
        const active = val === value;
        return (
          <button
            key={val}
            type="button"
            role="tab"
            aria-selected={active}
            className={[v.item, active ? 'is-active' : ''].filter(Boolean).join(' ')}
            onClick={() => onChange && onChange(val)}
          >
            {label}
          </button>
        );
      })}
    </div>
  );
}
