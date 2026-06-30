import * as React from 'react';

export interface PillProps extends React.HTMLAttributes<HTMLSpanElement> {
  /** 'default' (sunken neutral), 'admin' (evergreen fill), 'online' (success). */
  tone?: 'default' | 'admin' | 'online';
  children?: React.ReactNode;
}

/** A small lapidary-caps identity/presence token (Guest, Admin, Online). */
export function Pill(props: PillProps): JSX.Element;
