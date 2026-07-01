import React from 'react';

/**
 * Composer — the reply / new-topic card. A "Posting as …" identity strip, an
 * optional Markdown toolbar, the serif Textarea, and an actions row with the
 * green send button (and a char counter). Pass `toolbar` to show the format
 * controls, or compose your own children.
 */
export function Composer({
  postingAs,
  placeholder = 'Add to the discussion…',
  toolbar = true,
  sendLabel = 'Reply',
  value,
  defaultValue,
  onChange,
  count,                 // optional "n / max" string for the counter
  className = '',
  ...rest
}) {
  return (
    <form className={['composer', className].filter(Boolean).join(' ')} {...rest}>
      {postingAs ? (
        <div className="composer-id">Posting as <strong style={{ color: 'var(--text-strong)', fontWeight: 'var(--weight-semibold)' }}>{postingAs}</strong></div>
      ) : null}
      {toolbar ? (
        <div className="composer-toolbar" role="toolbar" aria-label="Formatting">
          <button type="button" aria-label="Bold" style={{ fontWeight: 700 }}>B</button>
          <button type="button" aria-label="Italic" style={{ fontStyle: 'italic' }}>I</button>
          <button type="button" aria-label="Strikethrough" style={{ textDecoration: 'line-through' }}>S</button>
          <span className="composer-toolbar-sep" aria-hidden="true" />
          <button type="button" aria-label="List">List</button>
          <button type="button" aria-label="Quote">Quote</button>
          <button type="button" aria-label="Code">Code</button>
          <span className="composer-toolbar-sep" aria-hidden="true" />
          <button type="button" aria-label="Link">Link</button>
          <button type="button" aria-label="Mention">@</button>
        </div>
      ) : null}
      <textarea
        className="textarea"
        rows={4}
        placeholder={placeholder}
        value={value}
        defaultValue={defaultValue}
        onChange={onChange}
      />
      <div className="composer-actions">
        <button type="submit" className="btn">{sendLabel}</button>
        {count != null ? <span className="composer-count">{count}</span> : null}
      </div>
    </form>
  );
}
