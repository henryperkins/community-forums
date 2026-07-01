import * as React from 'react';

export interface StarButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  /** Whether the viewer has starred this topic. */
  active?: boolean;
  /** Override the label (default "Star" / "Starred"). */
  label?: string;
  /** Optional trailing count. */
  count?: number | string;
}

/** The "Star this topic" pill (personal bookmark); gold when active. */
export function StarButton(props: StarButtonProps): JSX.Element;
