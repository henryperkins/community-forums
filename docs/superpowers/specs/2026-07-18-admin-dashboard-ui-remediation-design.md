# Admin Dashboard UI Remediation Design

**Date:** 2026-07-18
**Status:** Approved by the implementation brief in this task
**Authority:** `DECISIONS.md` → `DESIGN.md` → `ADMIN.md`, with the task brief defining this remediation's domain-page information architecture.

## Outcome

`/admin` becomes an operational landing page. Configuration moves to the admin page that owns it, every validation failure re-renders that page with the draft intact, and the shared admin navigation becomes a grouped desktop rail plus a progressively enhanced mobile drawer. The implementation remains vanilla PHP, server-rendered HTML, external CSS/JavaScript, and fully usable without JavaScript.

## Service ownership

`AdminSettingsService` owns the general and moderation settings view models, validation, persistence, transactions, `WriteGate` enforcement, and setting-change audit rows. Its public contract is:

```php
public function generalModel(array $overlay = []): array;
public function moderationModel(array $overlay = []): array;
public function updateSiteName(User $admin, string $name): void;
public function updateRegistration(User $admin, string $mode): void;
public function updateAntiAbuse(User $admin, array $input): void;
```

Registration writes only `registration_mode`. Anti-abuse writes only `antiabuse_mode` and `antiabuse_blocked_words`. Site name writes only `site_name`. Every method checks that the actor is an admin, applies `WriteGate`, validates before mutation, and writes its settings plus one precise audit entry in one transaction. The combined settings mutation is deleted from `AdminService` and the former combined audit record is split into registration and anti-abuse records whose before/after payloads contain only owned keys.

`AdminDashboardService` owns operational data only. `summary()` returns `queue_cards`, `activity_cards`, `attention`, and `audit`. Queue cards include `status` with one of `attention`, `clear`, or `unavailable`. Settings, custom emoji, cumulative audit totals, and dashboard form state do not enter this model.

`CustomEmojiService::pageModel(array $overlay = [])` owns the emoji catalogue and 422 overlay model. The existing create/enable/disable URLs remain stable, but all responses return to `/admin/custom-emoji` and invalid creation renders that page at 422 with per-field errors and all typed values preserved.

## Routes and pages

- `GET /admin/settings` renders site name and registration as independent forms.
- `POST /admin/site` remains stable and updates only site name.
- `POST /admin/settings/registration` updates only registration.
- `GET /admin/moderation` renders anti-abuse mode and blocked words.
- `POST /admin/moderation` updates only anti-abuse settings.
- `GET /admin/custom-emoji` renders creation plus the catalogue.
- Existing custom-emoji mutation URLs remain unchanged.
- The obsolete combined `POST /admin/settings` route is removed and therefore returns 404.

Guests retain the normal login redirect and non-admin users receive 403. The moderation page returns 404 when `anti_abuse` is disabled; the emoji page and its mutations return 404 when `custom_emoji` is disabled. CSRF continues to apply to every POST.

## Shared admin workspace

`templates/admin/_nav.php` contains one data model and one rendered navigation tree for every admin page:

- Dashboard: Dashboard
- Moderation: Reports, Approvals, Audit log, Anti-abuse
- Content: Boards & categories, Tags
- People: Users, Roles, Invitations, Badge rules
- Appearance: Branding, Themes, Custom emoji
- Notifications: Email, Announcements
- Integrations: Packages, Registry trust, Webhooks, API tokens, Sign-in providers, Extensions
- Settings: General & registration, Feature flags, Thread Intelligence

Each entry keeps its current URL, active-page semantics, and feature availability. Disabled destinations are non-links with the existing explanation, “Disabled until the feature flag is enabled.”

At widths above 860px, `.admin` is a grid with a full-width `.admin-head`, a persistent 224px navigation rail, and a `minmax(0, 1fr)` `.admin-pane`. At 860px and below, no-JavaScript rendering keeps the grouped navigation expanded in normal flow above the pane. With JavaScript active, a 44px `Admin sections` control promotes the same navigation tree into an off-canvas drawer with a dedicated scrim and close control.

The enhancement manages `aria-expanded`, `aria-hidden`/`inert`, initial focus, Tab containment, Escape, close/scrim/link actions, focus restoration, body scroll lock, and cleanup when the viewport crosses 860px. It does not replace or interfere with the global member-navigation drawer.

## Operational dashboard

The dashboard order is fixed:

1. Introduction
2. Queue health
3. Needs attention
4. Community today
5. Recent activity

Queue health contains Reports, Approval hold, Email failures, and Thread Intelligence when enabled. Community today contains New users today and Active now. The cumulative Audit and ambiguous Users cards are removed. The full audit-log link sits in the Recent activity heading.

The Recent activity table remains semantic and sits in a labelled, keyboard-focusable horizontal scroll region. At mobile widths it gains the exact cue “Scroll for Target and Reason” and a right-edge fade. JavaScript removes the cue/fade when no overflow exists or the region reaches its horizontal end.

## Visual contract

The current Imladris runtime remains authoritative: the existing dark surfaces, serif display/body typography, compact label face, gold accent, density, borders, radii, Admin mode pill, and global member navigation do not change. The desktop concept is `C:\Users\htper\.codex\generated_images\019f763b-bf2b-7710-9885-6f29b67eeba8\exec-c7459e04-9e88-4c74-b317-28f78ae9dc31.png`; the mobile open-drawer concept is `C:\Users\htper\.codex\generated_images\019f763b-bf2b-7710-9885-6f29b67eeba8\exec-4c9ae8c6-ea5f-4e68-8aa7-57734071724e.png`. The task brief controls exact copy, data, and feature state where generated text is approximate.

## Failure handling and compatibility

All writes remain normal forms, authorized and CSRF-protected. A `ValidationException` renders the owning GET page at 422 with field errors and typed values. Successful writes redirect to the owning page with a status flash. There is no migration, schema change, new dependency, JSON API change, or feature-default change.

## Evidence

PHP integration tests pin authorization, feature gates, route removal, write isolation, validation preservation, redirects, audit payloads, navigation grouping/active/disabled state, dashboard order/card statuses, and absence of dashboard configuration.

A focused Playwright suite covers 1280×800 and 390×844 desktop/mobile behavior, no-JS reachability, focus containment/restoration, Escape/scrim/link closure, 44px controls, table scrolling/fade cleanup, keyboard traversal, console cleanliness, and zero serious/critical axe violations. The existing settings 422 browser journey moves from `/admin` to `/admin/settings`. Final verification follows `DESIGN.md` §13.
