import React from 'react';

const VARIANT = {
  pill:      { wrap: 'inbox-tabs', item: 'inbox-tab' },     // filter pills (All / Unread / Starred / Mine)
  segment:   { wrap: 'segmented',  item: 'segmented-item' }, // segmented control (Hall / Watch)
  underline: { wrap: 'text-tabs',  item: 'text-tab' },       // underline tabs (Active / Newest · profile tabs)
};

/**
 * Tabs — the Imladris tab set in three registers:
 *   · pill      — inbox filter pills (evergreen-fill active)
 *   · segment   — a segmented toggle (Hall / Watch density)
 *   · underline — quiet underline tabs (sort, profile sections); `ruled` sets
 *                 the row on a full-width hairline, the page-section idiom
 * and three modes, which decide what the control says it is:
 *   · tabs   — a tablist over panels: role="tab", aria-selected (default)
 *   · links  — page sections that are real URLs: <nav>, <a href>, and
 *              aria-current="page" on the one you are on
 *   · toggle — a filter or an order: role="group", buttons with aria-pressed
 * Controlled via `value` + `onChange(value, event)`. In `links` mode, call
 * event.preventDefault() inside onChange to switch in place.
 */
export function Tabs({ items = [], value, onChange, variant = 'pill', mode = 'tabs', ruled = false, className = '', ...rest }) {
  const v = VARIANT[variant] || VARIANT.pill;
  const cls = [v.wrap, ruled && v === VARIANT.underline ? 'is-ruled' : '', className].filter(Boolean).join(' ');
  const Wrap = mode === 'links' ? 'nav' : 'div';
  const role = mode === 'links' ? undefined : mode === 'toggle' ? 'group' : 'tablist';
  return (
    <Wrap className={cls} role={role} {...rest}>
      {items.map((it) => {
        const val = typeof it === 'string' ? it : it.value;
        const label = typeof it === 'string' ? it : it.label;
        const active = val === value;
        const itemCls = [v.item, active ? 'is-active' : ''].filter(Boolean).join(' ');
        const pick = (e) => { if (onChange) onChange(val, e); };
        if (mode === 'links') {
          const href = (typeof it === 'object' && it.href) || '#' + val;
          return <a key={val} href={href} className={itemCls} aria-current={active ? 'page' : undefined} onClick={pick}>{label}</a>;
        }
        if (mode === 'toggle') {
          return <button key={val} type="button" className={itemCls} aria-pressed={active} onClick={pick}>{label}</button>;
        }
        return <button key={val} type="button" role="tab" aria-selected={active} className={itemCls} onClick={pick}>{label}</button>;
      })}
    </Wrap>
  );
}
