# ADR 0009: Advanced theming and custom CSS policy

**Date:** 2026-06-30
**Status:** Accepted as the implementation gate for advanced theming.

## Context

Phase 3 core branding is complete, but retro skin, guarded custom CSS, and logo
variants remain open. `ADMIN.md` allows token-driven theming and raw CSS only
behind an advanced warning. Phase 5 later introduces package-distributed themes.
This ADR defines the local operator-controlled version without opening package
distribution.

## Decision

- Advanced theming is local operator configuration, not a public package system.
- The first completion slice ships preset selection, retro skin, light/dark logo
  variants, a constrained token editor, preview, cache busting, safe-mode reset,
  and audit rows.
- Raw custom CSS stays behind a dark `custom_css` feature flag and an advanced
  confirmation. It is site-wide, admin-only, and can be disabled without deleting
  saved CSS.
- Custom CSS validation rejects `@import`, `javascript:`, `expression(`, remote
  URLs, data URLs outside approved image assets, and selectors targeting admin
  destructive-action affordances by ID.
- Accessibility checks must warn on color contrast regressions before saving
  token changes. Warnings can be overridden only by admins and are audited.
- Theme packages, remote fonts, trackers, JavaScript, template replacement, and
  public marketplace distribution remain Phase 5+ work.

## Consequences

- Code should extend the existing branding controller/service rather than create
  a second theming subsystem.
- Browser evidence must include safe-mode recovery and mobile rendering.
- Tests must cover CSS rejection, flag-off behavior, audit rows, contrast
  warnings, and logo variant fallback.
