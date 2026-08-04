import * as React from 'react';

export interface AdminArea {
  /** Stable key, e.g. 'content'. Matched against `area` to mark the active tab. */
  key: string;
  label: string;
  /** Template folder name under templates/, e.g. 'admin-content'. */
  dir: string;
  /** Entry file inside that folder, e.g. 'AdminContent.dc.html'. */
  file: string;
}

/** The ten admin areas in console order. */
export const ADMIN_AREAS: AdminArea[];

export interface AdminNavProps extends React.HTMLAttributes<HTMLDivElement> {
  /** Key of the active area — the only prop a template must set. */
  area: string;
  /** Override the area list (defaults to ADMIN_AREAS). */
  areas?: AdminArea[];
  /** Where "Back to the council" goes. */
  backHref?: string;
  backLabel?: string;
  /** Right-hand mode pill; pass '' or null to hide. */
  modeLabel?: string | null;
  /** Supply to render tabs as buttons and handle routing yourself; omitted, the
   *  tier renders real relative <a> hrefs to the sibling admin templates. */
  onNavigate?: (key: string) => void;
}

/** The unifying admin chrome: identity row plus the ten-area pill tier. */
export function AdminNav(props: AdminNavProps): JSX.Element;
