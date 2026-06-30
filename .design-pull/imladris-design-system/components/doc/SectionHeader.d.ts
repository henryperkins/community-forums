import * as React from 'react';

export interface SectionHeaderProps extends React.HTMLAttributes<HTMLElement> {
  /** Section number, e.g. "§5" or "5.1". Joined to `kicker` with a middot. */
  number?: React.ReactNode;
  /** Section label, e.g. "Screens & flows". */
  kicker?: React.ReactNode;
  /** The display heading. */
  title?: React.ReactNode;
  /** Optional italic standfirst beneath the title. */
  dek?: React.ReactNode;
  /** 'section' (gold kicker, h2) or 'sub' (quiet ink kicker, h3). Default 'section'. */
  level?: 'section' | 'sub';
  /** Override the heading tag (defaults to h2 / h3 by level). */
  as?: keyof JSX.IntrinsicElements;
}

/** A numbered section (or sub-section) header. */
export function SectionHeader(props: SectionHeaderProps): JSX.Element;
