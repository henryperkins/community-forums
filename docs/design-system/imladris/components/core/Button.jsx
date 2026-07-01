import React from 'react';

const VARIANT_CLASS = {
  primary: '',
  secondary: 'btn-secondary',
  ghost: 'btn-ghost',
  accent: 'btn-accent',
  danger: 'btn-danger',
};

/**
 * Button — the Imladris action control. Lapidary Marcellus label, sentence
 * case. Primary is evergreen; accent is mallorn-gold (reserve for the single
 * most-wanted action, e.g. Follow); secondary is a parchment outline; ghost is
 * a quiet wash. Renders an <a> when `href` is given, otherwise a <button>.
 */
export function Button({
  variant = 'primary',
  size = 'md',
  href,
  icon,                 // optional leading SVG/element
  iconAfter,            // optional trailing element
  disabled = false,
  className = '',
  children,
  ...rest
}) {
  const cls = [
    'btn',
    VARIANT_CLASS[variant] || '',
    size === 'sm' ? 'btn-small' : '',
    className,
  ].filter(Boolean).join(' ');

  const content = (
    <>
      {icon ? <span className="btn-icon-wrap" aria-hidden="true" style={{ display: 'inline-flex' }}>{icon}</span> : null}
      {children != null ? <span>{children}</span> : null}
      {iconAfter ? <span aria-hidden="true" style={{ display: 'inline-flex' }}>{iconAfter}</span> : null}
    </>
  );

  if (href && !disabled) {
    return (
      <a href={href} className={cls} {...rest}>{content}</a>
    );
  }
  return (
    <button type={rest.type || 'button'} className={cls} disabled={disabled} aria-disabled={disabled || undefined} {...rest}>
      {content}
    </button>
  );
}
