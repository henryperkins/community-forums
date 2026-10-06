import React from 'react';

/**
 * The eleven admin areas, in console order.
 *
 * `Moderation` sits at tier index 1 (ADR 0024 decision 2): the /mod/* queues and
 * /admin/moderation are live, flag-gated, tested functionality that this system
 * had no area for, and index 1 preserves the other ten areas' relative order
 * while matching ADMIN.md §9.2's "Moderation second".
 *
 * `dir`/`file` let the tier resolve a real relative href from one template
 * folder to its sibling, so the nav actually navigates instead of miming it.
 * `adminOnly` marks an area a board moderator cannot open — see `role` below.
 */
export const ADMIN_AREAS = [
  { key: 'overview',      label: 'Overview',      dir: 'admin-overview',      file: 'AdminOverview.dc.html' },
  { key: 'moderation',    label: 'Moderation',    dir: 'admin-moderation',    file: 'AdminModeration.dc.html', moderatorOk: true },
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

function Monogram({ name, username }) {
  const M = ((typeof window !== 'undefined' && window.ImladrisDesignSystem_c3e027) || {}).Monogram;
  // Seeded by the handle, as ForumNav's seat is: seeded by the display name
  // instead, the same operator changes colour on entering the console.
  return M ? <M name={name} username={username || name} size="sm" /> : null;
}

/**
 * AdminNav — the unifying chrome for every Admin template.
 *
 * Two rows, one sticky block: the identity row (mark · exit · operator cluster ·
 * mode pill) and the area tier listing every area the viewer can open. The tier
 * uses the PILL register — the same idiom the forum topbar uses for primary nav
 * — so it never reads as a second copy of a page's own underline sub-tabs.
 *
 * The operator cluster (search · bell · monogram · sign out) is the right-hand
 * pattern the member screen already uses (AccountSettings: monogram · name ·
 * Log out). An operator losing one-click sign-out and notification visibility on
 * entering the console is a functional regression, so the cluster travels with
 * the chrome rather than being dropped by it.
 *
 * It gets compact before it gets cramped (admin UI audit, 2026-08-08): at 900px
 * the search field, the username and the sign-out *word* are visually hidden
 * while the page body is still in its desktop layout, so the row never wraps
 * sign-out beneath a truncated name. Sign-out therefore carries both a glyph
 * and a text label — the glyph is inert at desktop width and is what remains
 * once the word goes — and an explicit aria-label, so its accessible name
 * survives the label being hidden.
 */
export function AdminNav({
  area,
  areas = ADMIN_AREAS,
  backHref = '../board-index/BoardIndex.dc.html',
  backLabel = 'Back to the council',
  role = 'admin',
  viewer = null,
  notificationCount = 0,
  notificationsHref = '../board-index/BoardIndex.dc.html',
  showSearch = true,
  modeLabel,
  onNavigate,
  className = '',
  ...rest
}) {
  // Least privilege: /admin/* needs an admin, /mod/* only a moderator. Showing a
  // board moderator ten areas that all deny them is show-and-deny, so the tier
  // is reduced to what they can actually open.
  const isAdmin = role !== 'moderator';
  const tierAreas = isAdmin ? areas : areas.filter((a) => a.moderatorOk);
  const mode = modeLabel === undefined ? (isAdmin ? 'Admin mode' : 'Moderation') : modeLabel;
  const bell = Number(notificationCount) || 0;

  return (
    <div className={['admin-bar', className].filter(Boolean).join(' ')} {...rest}>
      <div className="admin-bar-id">
        <span className="admin-bar-brand"><Mark /><span className="admin-bar-wordmark">Imladris</span></span>
        <a className="admin-bar-exit" href={backHref}>
          <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" aria-hidden="true"><path d="M15 18l-6-6 6-6" /></svg>
          {backLabel}
        </a>
        <div className="admin-bar-right">
          {showSearch ? (
            <form className="admin-bar-search" role="search" onSubmit={(e) => e.preventDefault()}>
              <input className="input input-small" type="search" name="q" placeholder="Search…" aria-label="Search" />
            </form>
          ) : null}
          {viewer ? (
            <React.Fragment>
              <a className="admin-bar-bell" href={notificationsHref} aria-label={bell > 0 ? `Notifications, ${bell} unread` : 'Notifications'}>
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" /><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" /></svg>
                {bell > 0 ? <span className="admin-bar-bell-count" aria-hidden="true">{bell > 99 ? '99+' : bell}</span> : null}
              </a>
              <a className="admin-bar-user" href={viewer.href || '../user-profile/UserProfile.dc.html'} aria-label={viewer.name}>
                <Monogram name={viewer.name} username={viewer.username} />
                <span className="admin-bar-username">{viewer.name}</span>
              </a>
              <button className="admin-bar-signout" type="button" aria-label="Log out">
                <svg viewBox="0 0 24 24" width="17" height="17" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true"><path d="M15 17l5-5-5-5" /><path d="M20 12H9" /><path d="M12 20H5V4h7" /></svg>
                <span className="admin-bar-signout-label">Log out</span>
              </button>
            </React.Fragment>
          ) : null}
          {mode ? <span className="admin-bar-mode">{mode}</span> : null}
        </div>
      </div>
      <nav className="admin-tier" aria-label="Admin areas">
        {tierAreas.map((a) => {
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
