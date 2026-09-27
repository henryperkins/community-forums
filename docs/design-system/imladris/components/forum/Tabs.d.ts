import * as React from 'react';

export interface TabItem {
  label: string;
  value: string;
  /** links mode: the section's real URL. Default `#value`. */
  href?: string;
}

export interface TabsProps extends Omit<React.HTMLAttributes<HTMLElement>, 'onChange'> {
  /** Items as {label, value, href?} or bare strings. */
  items: Array<TabItem | string>;
  /** Active value. */
  value?: string;
  /** Called with the picked value and the click event. In `links` mode, call
   *  event.preventDefault() to switch in place instead of navigating. */
  onChange?: (value: string, event: React.MouseEvent<HTMLElement>) => void;
  /** pill (filters) · segment (Hall/Watch) · underline (sort/profile). */
  variant?: 'pill' | 'segment' | 'underline';
  /**
   * What the control says it is. Default 'tabs'.
   * tabs   = a tablist over panels (role="tab", aria-selected)
   * links  = page sections at real URLs (<nav>, <a>, aria-current="page")
   * toggle = a filter or an order (role="group", buttons with aria-pressed)
   */
  mode?: 'tabs' | 'links' | 'toggle';
  /** underline only: set the row on a full-width hairline rule. */
  ruled?: boolean;
}

/** The Imladris tab set — pill, segment or underline, as tabs, links or toggles. */
export function Tabs(props: TabsProps): JSX.Element;
