import React from 'react';

/**
 * The ten admin areas, in console order. `dir`/`file` let the tier resolve a
 * real relative href from one template folder to its sibling, so the nav
 * actually navigates instead of miming it.
 */
export const ADMIN_AREAS = [
  { key: 'overview',      label: 'Overview',      dir: 'admin-overview',      file: 'AdminOverview.dc.html' },
  { key: 'content',       label: 'Content',       dir: 'admin-content',       file: 'AdminContent.dc.html' },
  { key: 'people',        label: 'People',        dir: 'admin-people',        file: 'AdminPeople.dc.html' },
  { key: 'members',       label: 'Members',       dir: 'admin-members',       file: 'AdminMembers.dc.html' },
  { key: 'appearance',    label: 'Appearance',    dir: 'admin-appearance',    file: 'AdminAppearance.dc.html' },
  { key: 'notifications', label: 'Notifications', dir: 'admin-notifications', file: 'AdminNotifications.dc.html' },
  { key: 'integrations',  label: 'Integrations',  dir: 'admin-integrations',  file: 'AdminIntegrations.dc.html' },
  { key: 'packages',      label: 'Packages',      dir: 'admin-packages',      file: 'AdminPackages.dc.html' },
  { key: 'features',      label: 'Features',      dir: 'admin-features',      file: 'AdminFeatures.dc.html' },
  { key: 'settings',      label: 'Settings',      dir: 'admin-settings',      file: 'AdminSettings.dc.html' },
];

/**
 * The house mark is never redrawn here — it is the system's own EightPointStar,
 * resolved off the namespace at render time (the bundle assigns exports after
 * every module has evaluated, so module-scope capture would come up empty).
 * This is the same composition every ui_kit topbar uses.
 */
function Mark() {
  const Star = ((typeof window !== 'undefined' && window.ImladrisDesignSystem_c3e027) || {}).EightPointStar;
  return Star ? <Star size={24} /> : null;
}

/**
 * AdminNav — the unifying chrome for every Admin template.
 *
 * Two rows, one sticky block: the identity row (mark · exit · mode pill) and
 * the area tier listing all ten admin areas. The tier uses the PILL register —
 * the same idiom the forum topbar uses for primary nav — so it never reads as a
 * second copy of a page's own underline sub-tabs.
 */
export function AdminNav({
  area,
  areas = ADMIN_AREAS,
  backHref = '#',
  backLabel = 'Back to the council',
  modeLabel = 'Admin mode',
  onNavigate,
  className = '',
  ...rest
}) {
  return (
    <div className={['admin-bar', className].filter(Boolean).join(' ')} {...rest}>
      <div className="admin-bar-id">
        <span className="admin-bar-brand"><Mark /><span className="admin-bar-wordmark">Imladris</span></span>
        <a className="admin-bar-exit" href={backHref}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
          {backLabel}
        </a>
        {modeLabel ? <span className="admin-bar-mode">{modeLabel}</span> : null}
      </div>
      <nav className="admin-tier" aria-label="Admin areas">
        {areas.map((a) => {
          const active = a.key === area;
          const cls = ['admin-tier-item', active ? 'is-active' : ''].filter(Boolean).join(' ');
          if (onNavigate) {
            return (
              <button key={a.key} type="button" className={cls} aria-current={active ? 'page' : undefined} onClick={() => onNavigate(a.key)}>{a.label}</button>
            );
          }
          return (
            <a key={a.key} className={cls} aria-current={active ? 'page' : undefined} href={active ? undefined : `../${a.dir}/${a.file}`}>{a.label}</a>
          );
        })}
      </nav>
    </div>
  );
}
