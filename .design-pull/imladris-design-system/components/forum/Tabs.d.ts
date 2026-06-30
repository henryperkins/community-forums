import * as React from 'react';

export interface TabItem { label: string; value: string; }

export interface TabsProps extends Omit<React.HTMLAttributes<HTMLDivElement>, 'onChange'> {
  /** Items as {label, value} or bare strings. */
  items: Array<TabItem | string>;
  /** Active value. */
  value?: string;
  onChange?: (value: string) => void;
  /** pill (filters) · segment (Hall/Watch) · underline (sort/profile). */
  variant?: 'pill' | 'segment' | 'underline';
}

/** The Imladris tab set — pill, segment, or underline registers. */
export function Tabs(props: TabsProps): JSX.Element;
