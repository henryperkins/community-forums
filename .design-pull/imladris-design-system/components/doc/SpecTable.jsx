import React from 'react';

/**
 * SpecTable — a bordered reference table with a sunken caps header, a serif
 * body, optional zebra rows, and node-capable cells (prose, mono, or a ✓ / —
 * mark via the .doc-yes / .doc-no / .doc-scoped utilities). `columns` carry an
 * optional `align` ('center' | 'right'); `rows` are objects keyed by
 * column.key, or arrays in column order.
 */
export function SpecTable({
  columns = [],
  rows = [],
  caption,
  zebra = true,
  className = '',
  ...rest
}) {
  const hasHead = columns.some((c) => c.label != null);
  const cellOf = (row, c, i) => (Array.isArray(row) ? row[i] : row[c.key]);
  const alignCls = (c) => (c.align ? 'is-' + c.align : '');
  return (
    <div className={['doc-table-wrap', className].filter(Boolean).join(' ')}>
      <table className={['doc-table', zebra ? 'is-zebra' : ''].filter(Boolean).join(' ')} {...rest}>
        {caption ? <caption>{caption}</caption> : null}
        {hasHead ? (
          <thead>
            <tr>
              {columns.map((c, i) => (
                <th key={c.key != null ? c.key : i} scope="col" className={alignCls(c)}>{c.label}</th>
              ))}
            </tr>
          </thead>
        ) : null}
        <tbody>
          {rows.map((row, ri) => (
            <tr key={ri}>
              {columns.map((c, ci) => (
                <td key={c.key != null ? c.key : ci} className={alignCls(c)}>{cellOf(row, c, ci)}</td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
