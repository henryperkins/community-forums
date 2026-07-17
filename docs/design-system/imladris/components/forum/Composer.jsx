import React from 'react';

function monoClass(seed) {
  const s = String(seed || '');
  let h = 0;
  for (let i = 0; i < s.length; i++) h = (h + s.charCodeAt(i)) % 10;
  return `mono-${h}`;
}
function initials(label) {
  const p = String(label || '').trim().split(/\s+/).filter(Boolean);
  if (!p.length) return '?';
  return (p.length === 1 ? p[0].slice(0, 2) : p[0][0] + p[1][0]).toUpperCase();
}

/* The production toolbar contract (composer.js at the inspected commit):
   order, labels, shortcuts, group breaks, narrow-screen essentials, and the
   exact Lucide-register icon paths. Do not reorder or redraw. */
const TOOLBAR_ORDER = ['bold', 'italic', 'strike', 'code', 'quote', 'h2', 'list', 'orderedList', 'codeblock', 'spoiler', 'link'];
const TOOLBAR_ACTIONS = {
  bold: { label: 'Bold', shortcut: 'B' },
  italic: { label: 'Italic', shortcut: 'I' },
  strike: { label: 'Strike' },
  code: { label: 'Inline code', shortcut: 'E' },
  quote: { label: 'Quote' },
  h2: { label: 'Heading' },
  list: { label: 'Bullet list' },
  orderedList: { label: 'Numbered list' },
  codeblock: { label: 'Code block' },
  spoiler: { label: 'Spoiler' },
  link: { label: 'Link', shortcut: 'K' },
};
const GROUP_BREAKS = { code: true, spoiler: true };
const ESSENTIAL = { bold: true, italic: true, list: true, link: true };
const OVERFLOW_ORDER = ['strike', 'code', 'quote', 'h2', 'orderedList', 'codeblock', 'spoiler'];
const ICON_PATHS = {
  bold: ['M8 5h5a3 3 0 0 1 0 6H8z', 'M8 11h6a4 4 0 0 1 0 8H8z'],
  italic: ['M10 5h7', 'M7 19h7', 'M14 5 10 19'],
  strike: ['M6 7h10', 'M5 12h14', 'M8 17h8'],
  code: ['m9 8-4 4 4 4', 'm15 8 4 4-4 4'],
  quote: ['M6 7h5v5H7v5', 'M14 7h5v5h-4v5'],
  h2: ['M5 6v12', 'M13 6v12', 'M5 12h8', 'M16 10c0-2 4-2 4 0 0 2-4 3-4 6h5'],
  list: ['M9 7h10', 'M9 12h10', 'M9 17h10', 'M5 7h.01', 'M5 12h.01', 'M5 17h.01'],
  orderedList: ['M5 6h1v3', 'M5 13c2-1 2 2 0 3h2', 'M10 7h9', 'M10 12h9', 'M10 17h9'],
  codeblock: ['M5 6h14v12H5z', 'm9 10-2 2 2 2', 'm6-4 2 2-2 2'],
  spoiler: ['M3 12s3-5 9-5 9 5 9 5-3 5-9 5-9-5-9-5', 'M12 10a2 2 0 1 0 0 4 2 2 0 0 0 0-4'],
  link: ['M10 14 8.5 15.5a3 3 0 0 1-4-4L7 9a3 3 0 0 1 4 0', 'm14 10 1.5-1.5a3 3 0 0 1 4 4L17 15a3 3 0 0 1-4 0', 'm9 15 6-6'],
};

function ActionIcon({ k }) {
  return (
    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
      {(ICON_PATHS[k] || []).map((d, i) => <path key={i} d={d} />)}
    </svg>
  );
}

/**
 * Composer — the shared composer shell (COMPOSER.md v0.8; composer_shell.php).
 * One contained box serving all four mounts — `context` reply / new_thread /
 * dm / edit — with the identical feature surface: the engraved icon formatting
 * row (toggled by Aa, persisted in the product), attach ＋ / emoji 😊, the
 * identity line ("as **Name**"), the Anonymous chip where a board allows it,
 * the Preview toggle, and the circular quill send. Below the box: draft state,
 * anonymous disclosure, and the near-limit counter. Wrapper differences (a
 * Title + board picker for new_thread, recipients for dm) mount via `header`.
 *
 * This is the presentational design reference: in production the textarea is
 * canonical Markdown (WYSIWYG mounts over it when `rich_composer` +
 * `wysiwyg_composer` are enabled, and everything works with no JS), every form
 * carries a CSRF token + a fresh idempotency key, and send performs a full
 * navigation (optimistic send remains deferred — ADR 0020).
 */
export function Composer({
  context = 'reply',
  placeholder = 'Add your counsel…',
  maxLength = 20000,
  value,
  defaultValue,
  onChange,
  submitLabel = 'Reply',
  identity,               // display name for the "as …" line; omit to hide
  identitySeed,           // monogram hash seed (defaults to identity)
  showAvatar = true,      // honors the user's show_avatars preference
  allowAnonymous = false,
  anonymousChecked = false,
  anonymousDisclosure = 'Your name is hidden from other members; moderators can still see it.',
  toolbarOpen = true,     // the Aa row state (production default: open)
  activeFormats,          // e.g. ['bold'] — aria-pressed specimens
  error,                  // field error shown inside the box, above the input
  uploads,                // [{ name, thumb, status, progress, failed, alt }]
  draftSaved = false,
  count,                  // "18,204 / 20,000" — shown near the limit
  countOver = false,
  previewOpen = false,
  previewContent,         // rendered server-preview HTML (same pipeline as posts)
  submitting = false,
  disabled = false,
  disabledNotice,         // e.g. "This topic was locked while you were writing. Your draft is kept."
  header,                 // wrapper slot above the box (Title field, recipients…)
  actionsStart,
  actionsEnd,
  className = '',
  onSubmit,
  ...rest
}) {
  const [fmtOpen, setFmtOpen] = React.useState(!!toolbarOpen);
  const [moreOpen, setMoreOpen] = React.useState(false);
  const [anon, setAnon] = React.useState(!!anonymousChecked);
  const [showPreview, setShowPreview] = React.useState(!!previewOpen);
  const active = new Set(activeFormats || []);
  const cls = ['composer', 'composer-shell', submitting ? 'is-submitting' : '', className].filter(Boolean).join(' ');
  const seed = identitySeed || identity;
  return (
    <form className={cls} data-composer-context={context} aria-busy={submitting || undefined} onSubmit={onSubmit} {...rest}>
      {header}
      <div className="composer-box">
        <div className="composer-format-slot">
          <div className="composer-toolbar" role="toolbar" aria-label="Formatting" hidden={!fmtOpen}>
            {TOOLBAR_ORDER.map((k) => {
              const a = TOOLBAR_ACTIONS[k];
              const sc = a.shortcut ? ' (Ctrl+' + a.shortcut + ')' : '';
              return (
                <React.Fragment key={k}>
                  <button type="button" className={'composer-toolbar-action' + (ESSENTIAL[k] ? ' is-essential' : '')}
                    aria-label={a.label + sc} data-tip={a.label + (a.shortcut ? ' · Ctrl+' + a.shortcut : '')}
                    aria-keyshortcuts={a.shortcut ? 'Control+' + a.shortcut + ' Meta+' + a.shortcut : undefined}
                    aria-pressed={active.has(k)} disabled={disabled}>
                    <ActionIcon k={k} />
                  </button>
                  {GROUP_BREAKS[k] ? <span className="composer-toolbar-sep" aria-hidden="true"></span> : null}
                </React.Fragment>
              );
            })}
            <span className="composer-more-wrap">
              <button type="button" className="composer-more-toggle" aria-label="More formatting"
                aria-expanded={moreOpen} onClick={() => setMoreOpen(!moreOpen)} disabled={disabled}>＋</button>
            </span>
          </div>
          {moreOpen ? (
            <div className="composer-format-overflow" role="group" aria-label="More formatting">
              {OVERFLOW_ORDER.map((k) => (
                <button type="button" key={k} className="composer-overflow-action" aria-pressed={active.has(k)}
                  onClick={() => setMoreOpen(false)}>{TOOLBAR_ACTIONS[k].label}</button>
              ))}
            </div>
          ) : null}
        </div>
        {error ? <p className="field-error">{error}</p> : null}
        <textarea className="composer-input" rows={4} maxLength={maxLength} placeholder={placeholder}
          value={value} defaultValue={defaultValue} onChange={onChange} disabled={disabled} required />
        <div className="composer-upload-tray" aria-live="polite">
          {(uploads || []).map((u, i) => (
            <div key={i} className={'composer-upload-card' + (u.failed ? ' is-failed' : '')}>
              {u.thumb ? <img className="composer-upload-thumb" src={u.thumb} alt="" /> : <span className="composer-upload-thumb" aria-hidden="true"></span>}
              <div className="composer-upload-meta">
                <span className="composer-upload-name">{u.name}</span>
                <span className="composer-upload-status">{u.status}</span>
              </div>
              {u.progress != null ? <progress max={100} value={u.progress}></progress> : null}
              {u.alt != null ? <input className="input" defaultValue={u.alt} placeholder="Describe this image (alt text)" aria-label="Alt text" /> : null}
              <div className="composer-upload-actions">
                <button type="button" className="btn btn-secondary btn-small">Up</button>
                <button type="button" className="btn btn-secondary btn-small">Down</button>
                <button type="button" className="btn btn-secondary btn-small">Remove</button>
              </div>
            </div>
          ))}
        </div>
        <div className="composer-actions-bar">
          <div className="composer-actions-start">
            <button type="button" className="composer-format-toggle" aria-label="Formatting"
              aria-expanded={fmtOpen} onClick={() => setFmtOpen(!fmtOpen)} disabled={disabled}>Aa</button>
            <button type="button" className="composer-attach-toggle" aria-label="Attach images" title="Attach images" disabled={disabled}>＋</button>
            <button type="button" className="composer-emoji-toggle" aria-label="Emoji" aria-haspopup="dialog" disabled={disabled}>😊</button>
            {actionsStart}
            {identity ? (
              <span className="composer-identity" dir="auto">
                {showAvatar ? <span className={'monogram ' + monoClass(seed)} aria-hidden="true">{initials(identity)}</span> : null}
                <span className="composer-identity-copy">as <strong>{identity}</strong></span>
              </span>
            ) : null}
            {allowAnonymous ? (
              <span className="composer-anonymous-chip">
                <input type="checkbox" id="composer-anon" checked={anon} onChange={(e) => setAnon(e.target.checked)} disabled={disabled} />
                <label htmlFor="composer-anon">Anonymous</label>
              </span>
            ) : null}
          </div>
          <div className="composer-actions-end">
            {actionsEnd}
            <button type="button" className="composer-preview-toggle" aria-label="Preview"
              aria-expanded={showPreview} onClick={() => setShowPreview(!showPreview)} disabled={disabled}>Preview</button>
            <button type="submit" className="btn composer-send" aria-label={submitLabel} disabled={disabled || submitting}>
              <span aria-hidden="true">✒</span>
            </button>
          </div>
        </div>
      </div>
      <div className="composer-meta-row">
        <span className="composer-meta-draft">
          {draftSaved ? <>Draft saved · <button type="button" className="linkbtn composer-discard" aria-label="Discard draft">Discard</button></> : null}
        </span>
        {allowAnonymous ? <span className="composer-anonymous-disclosure">{anonymousDisclosure}</span> : <span></span>}
        {count != null ? <span className={'composer-count' + (countOver ? ' over' : '')}>{count}</span> : null}
      </div>
      {showPreview ? <div className="composer-preview formatted-content" aria-live="polite">{previewContent}</div> : null}
      {disabledNotice ? <p className="composer-meta-row" role="status">{disabledNotice}</p> : null}
      <span className="sr-only" role="status" aria-live="polite">{submitting ? 'Sending…' : ''}</span>
    </form>
  );
}
