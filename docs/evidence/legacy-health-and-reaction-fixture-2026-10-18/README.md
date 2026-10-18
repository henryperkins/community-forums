# Legacy health redirect and reaction fixture

Base: `917625e95201e8eac0a8aa4b393e35fceee89e7c`. The owner requested repair of
the two findings from the Inbox release, publication to main and production
deployment verification.

## Legacy hostname

`boards.hperkins.blog` resolved to four Google IPv4 addresses and four Google
IPv6 addresses. Its health path returned Google's 404. Both Cloudflare fallback
addresses returned the expected 301 to the canonical health URL with valid TLS
and `Cache-Control: no-store` when probed using the alias hostname. The existing
hostname-specific Worker route remained attached to `retroboards`.

The DNS repair replaced only those eight address records with a CNAME to
`saas-fallback.candidary.online.` at TTL 300. WordPress.com reported an active
domain, its nameservers in use and DNS-management permission. All 19 unrelated
records compare unchanged. Private full-zone snapshots are retained outside Git;
the published record includes only the changed records and comparison hashes.

The authoritative nameserver and public Cloudflare/Google resolvers confirmed
the CNAME. A cached old address and a direct-edge redirect establish different
facts; the final public receipt records ordinary HTTPS after propagation.
Cloudflare's Custom Hostnames API still reported quota/entitlement errors while
the existing TLS attachment and route worked. The repair reused that attachment.

## Hosted reaction-count mismatch

The original mobile test failed locally with exactly the hosted error:
`Expected: 9`, `Received: 11`, after adding the two allowed custom reactions
`party_desktop` and `party_mobile`. Earlier Gate A journeys create those entries
in the shared database. The product correctly adds them to the nine standard
choices; the test assumed a standard-only catalogue.

The fixed test snapshots the feature map, disables custom emoji for its standard
icon contract, and restores the exact map in `finally`. The custom records remain
intact. The assertions still require nine choices, nine distinct SVG shapes,
accessible names, 44px targets, at most three grid rows and a working Heart toggle.

The focused green run passed with both custom rows present, and its feature map
and custom-catalogue snapshot matched the before-state. Broader checks first run
the real desktop/mobile custom-emoji journeys, then the mobile-controls suite
against the same database in each browser engine.

Verification counts, source hashes and the scoped browser outcomes are recorded
in `verification.json` before publication. Deployment identity and the final
public redirect are verified separately after the main push. Browser evidence
uses local Linux engines and mobile emulation.

| Check | Result |
| --- | --- |
| Chromium | Two custom-emoji context journeys and 20 controls cases passed; four intentional project-specific skips |
| WebKit | Two custom-emoji context journeys and 20 controls cases passed; four intentional project-specific skips |
| Fixture preservation | Both custom entries remain enabled and the feature map is unchanged after each engine's controls suite |
| Full PHP suite | 3,233 tests; 25,653 assertions; one expected migration-rehearsal skip; exit 0 |
| Worker/asset tests | All 35 passed |
| Asset and Imladris checks | Current; the deployed manifest and all current bundle bytes are unchanged |
| Independent review | No actionable source, DNS or focused-hosted-recipe finding |

- [Verification and source hashes](verification.json)
- [Every browser outcome and skip](browser-runs.json)
- [Targeted DNS edit and unrelated-record comparison](dns-change.json)
- [Chromium fixture preservation](chromium-fixture-preservation.json) and [reaction tray](chromium-reaction-menu.png)
- [WebKit fixture preservation](webkit-fixture-preservation.json) and [reaction tray](webkit-reaction-menu.png)
