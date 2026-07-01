# Phase 5 threat model - Theme phishing

**Status:** Recorded 2026-07-01 - pending owner review
**Sources:** PHASE_5_PLAN section 9 theme verification, preview isolation, CSP/cache, safe mode, rollback scenarios; section 12 UI and theme risks.
**Fixture index:** `fixtures.json`, enforced by `tests/Unit/Core/ThreatModelIndexTest.php`.

## Scope and assets

Declarative theme packages, tokens, generated stylesheets, preview state, asset
scanning, contrast checks, cache digests, safe mode, and rollback. The protected
asset is that a theme cannot impersonate core controls, exfiltrate data, or make
the operator unable to recover the site UI.

## Threats

| ID | Threat | Required negative fixture | Owner |
|---|---|---|---|
| TM-TH-01 | Theme tokens create deceptive overlays or fake controls. | Token package attempting selector/overlay constructs rejected. | Inc4 |
| TM-TH-02 | Theme CSS imports remote trackers or mixed content. | Remote url()/@import/tracker vectors rejected; built CSS free of external URLs. | Inc4 |
| TM-TH-03 | Asset upload hides script in SVG or polyglot bytes. | SVG-with-script and polyglot assets neutralized by scan. | Inc4 |
| TM-TH-04 | Low-contrast palette becomes the site default. | Sub-threshold contrast token set cannot become site default. | Inc4 |
| TM-TH-05 | Hostile theme traps operator away from recovery UI. | Safe mode renders system theme while a hostile theme is active. | Inc4 |
| TM-TH-06 | Preview leaks to other users or persists unexpectedly. | Preview session changes nothing for a second user. | Inc4 |
| TM-TH-07 | Rollback serves stale or attacker stylesheet bytes. | After LKG rollback the served stylesheet digest equals the LKG digest. | Inc4 |

## Residual risk

Purely aesthetic deception cannot be eliminated entirely. Gate A limits theme
authority to declarative tokens and scanned assets, keeps strict CSP, and
requires safe mode and last-known-good rollback.
