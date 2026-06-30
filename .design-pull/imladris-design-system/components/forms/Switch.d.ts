import * as React from 'react';

export interface SwitchProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  /** Inline label text to the right of the control. */
  label?: string;
}

/** Preference toggle — evergreen track, parchment knob. */
export function Switch(props: SwitchProps): JSX.Element;
