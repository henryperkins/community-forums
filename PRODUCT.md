# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

RetroBoards serves **two first-class audiences**, confirmed as equally real. Neither is designed as an afterthought, and every surface names which one it serves.

**Members** — people reading and posting in a community they belong to, arriving either from search (as guests evaluating whether to join) or from a habit of checking in. Their job is triage and contribution: find what changed since last time, read a conversation to its point, and add to it. The docs recognise four member-facing roles, cumulative in capability:

| Role | Who | Job |
|---|---|---|
| **Guest** | Unauthenticated, often from search | Read threads, evaluate the community, understand how to join |
| **User** | Registered member | Post topics and counsel, react, star, DM, build identity and regard |
| **Moderator** | Trusted member, scoped to one or more boards | Keep boards healthy: pin, lock, move, delete, handle reports |
| **Admin / Staff** | Site operator | Everything: boards, categories, roles, settings, all moderation |

Board authority is separate from global role: a member can moderate one board without moderating the site.

**Operators** — people who install and run their own RetroBoards on their own hardware. They are not a hypothetical audience: the operator surface is a large, specified part of the product (`ADMIN.md`, ~68KB), covering setup, the moderation console, features and flags, packages, identity providers, integrations, secrets, invitations, and security response. `forum.candidary.online` is the owner's own install and the reference deployment, not the whole product.

The member/operator distinction is a design constraint, not a hierarchy: when the two conflict on one surface, that surface's own audience decides, and neither is quietly starved.

## Product Purpose

RetroBoards is self-hostable forum software presented as a **Community Inbox**: a durable knowledge space with the speed and warmth of chat, the triage power of email, and the long-term structure of a forum. The default authenticated home is not a forum homepage — it is a personalised forum inbox.

It exists because forum software makes people learn forum conventions before they can participate, and because the communities worth keeping are the ones whose conversations stay findable years later. RetroBoards maps classic forum concepts onto interfaces people already use daily — boards read as channels, topics read as an email inbox, posts read as a message stream — so a first-time visitor can read, find, and post with no forum-specific learning curve.

Success is measured on community health, not feature count (PRODUCT_DESIGN §12): activation (share of new registrations that make a first post, and time-to-first-post), guest-to-member conversion, weekly active posters, share of new topics answered within 24h, D7/D30 return rate, reports per 1,000 posts, and time to resolve them. Targets are set against a baseline once instrumentation lands; the install is new, so no baseline exists yet.

**Distribution: open source.** Confirmed. The project is intended to be released publicly for others to run, with no commercial gate.

> **Open decision — do not assume an answer.** The license is not yet chosen or applied. `composer.json` currently declares `"license": "proprietary"` and the repository has no `LICENSE` file; both contradict the open-source intent and need a deliberate decision before any public release. Future work must not state or imply a license, nor describe the project as already open source, until this is resolved.

## Positioning

Three lineages combine, and the combination is the claim a neighbouring product could not truthfully copy:

- **Discourse bones** — durable topics, categories, tags, search, solved answers, moderation, canonical knowledge.
- **Slack polish** — immediacy, mentions, reactions, lightweight replies, a rich composer, conversational warmth.
- **Outlook discipline** — triage, split-pane reading, unread state, focused filters, keyboard-driven density.

The durable unit is the **topic**: the inbox is personal, the topic is durable, and the composer is immediate.

Two further positions are load-bearing:

- **Own your data.** A plain PHP/MySQL stack on a single VPS, exportable data, and no required external service for any core function. Anything the project might outgrow — email, search, media storage, the feed, AI generation, output moderation, AI transport — sits behind a replaceable interface (DECISIONS §2), so a single box today becomes a bigger setup later without a rewrite.
- **Evidence-bound AI, not an oracle.** Thread Intelligence generates Living Briefs for public topics only, auto-published solely after local evidence, schema, content, and moderation checks, with member-visible provenance linking every claim back to source posts. Personal "since you last read" context stays deterministic. Curator edits are authoritative input to later refreshes, the last good output survives a failure, and behaviour with no credentials configured is deterministic and non-AI. The register is "AI proposes; the council approves."

## Operating Context

- **Deployment.** Designed for a single VPS running PHP 8.2+ (with `pdo_mysql`, `mbstring`, `dom`, `openssl`, `curl`) and MySQL 8 / MariaDB 10.6+. A fresh install redirects to `/setup`, where the operator creates the first admin, names the community, and gets starter boards. The reference deployment additionally runs on Cloudflare Workers at `forum.candidary.online`.
- **Background work.** Cron-scheduled PHP CLI workers do the deferred work: notification email, daily digests, IP anonymisation, attachment sweeps, package registry refresh and digest verification, webhook delivery, link previews, and the Thread Intelligence pass. Anything that can be deferred is deferred to a worker rather than done in the request.
- **Configuration.** Every post-MVP subsystem sits behind a feature flag with an operator-reversible override; staged-rollout posture lives in config, not flags. Secrets — including the AI credential — are environment-configured, never stored in the database, and never shown in the UI.
- **Moderation as routine.** A reports queue, per-board moderators, an audited `moderation_log` row for every action, appeals, anti-abuse content scoring that defaults to observe-only and is capped at hold rather than auto-block, IP capture with a 90-day retention window, and admin-only audited access to it.
- **How the project is run.** Spec-driven, with a strict precedence chain: `DECISIONS.md` wins on any conflict, then `PRODUCT_DESIGN.md`, then `SCHEMA.md`, then the surface specs (`USER.md` / `ADMIN.md` / `COMMUNITY.md` / `COMPOSER.md`). `README.md` is an orientation pointer, not authority. Delivery runs as seven phases, each split into Gate A and Gate B with entry and exit gates and a carryover ledger. Deferrals are recorded as ADRs in `docs/adr/` rather than silently dropped, and proof artifacts land in `docs/evidence/`.
- **"Done" requires evidence (PRODUCT_DESIGN §13).** Adding a column or a table is not shipping a feature; behaviour must be enforced and tested. UI-visible work needs Playwright or browser evidence in addition to PHPUnit. Inert schema is not evidence.

## Capabilities and Constraints

**Shipped and available.** Auth (password, OAuth via Google/Apple/GitHub, WebAuthn passkeys, TOTP, generic OIDC providers); topics, posts, edit and delete, soft delete; reactions, stars, subscriptions, unread tracking; notifications with @mentions and a bell inbox; MySQL FULLTEXT search; direct messages including group DMs; a unified rich hybrid-Markdown composer with attachments; per-board moderators, reports, appeals, audit log; profiles, reputation, badges, leaderboards; tags, expanded feeds, topic workflow and triage; a signed package registry with declarative theme packages and an install/update lifecycle; a database-backed capability resolver; invitations, encrypted service secrets, read-only API tokens, outbound webhooks, first-party hooks; link previews (available but inert until an operator opts a board in *and* allowlists a host); and Thread Intelligence — Living Briefs plus deterministic return context.

**Hard technical constraints future work must not break:**

- **Progressive enhancement is a floor, not a preference.** Every flow works as server-rendered HTML and forms first. JavaScript only decorates, via specific JSON endpoints and `data-*` hooks. Live composer preview re-uses the exact same server render pipeline — there is no client Markdown engine.
- **Strict CSP with no `'unsafe-inline'`.** No inline `<script>` or `<style>` anywhere. Enhancement JS lives in external files or the page silently breaks.
- **Short-polling only.** No WebSockets. SSE is a later option, not a current mechanism.
- **Server-rendered, crawlable URLs.** Every view has a real, shareable URL, and the product is fully readable without JavaScript. Forums live and die by search traffic.
- **One column at 860px and below.** The sidebar becomes a slide-in drawer, and a conversation supplies a back link to the list it came from.
- **No application framework.** Vanilla PHP 8.2+ with a hand-written kernel, a hand-wired container, and a micro-router — hand-rolled by choice, to own every line and run anywhere.
- **Single community per install.** Multi-tenancy is a later architectural consideration, deliberately not built into today's tables.
- **Anonymity is a render-time mask, never stored masked**, and it applies to logged-in members on boards that allow it. There is no unaccountable guest posting: an account is always required to post.

**Terminology.** Board (`#channel`), Category, Thread / Topic, Post / Reply, OP, Reaction, Star, Subscription, Presence, Postbit, Join-bar. The product-facing vocabulary layered on top is recorded under Brand Commitments.

**Explicitly undecided — record, do not invent:** the open-source license; the product name; the SMTP/email provider; whether the preserved retro skin ever ships as a switchable theme; whether search stays on MySQL FULLTEXT or moves to Meilisearch at scale.

## Brand Commitments

**Binding.** The **Imladris** design language and its lexicon are settled, and future work preserves both rather than reinterpreting them. Imladris is specified in `docs/design-system/imladris/` (README, `imladris-spec.md`, `PRODUCTION.md`, `manifest.json`, tokens and components); production tokens ship in the generated Imladris CSS, and `public/assets/app.css` is the authoritative token and component CSS inside the app.

- **Identity.** Parchment-and-evergreen surfaces, a single mallorn-gold accent, an eight-pointed elven star, set entirely in serif type. The intent is a councillor's hall — considered, literary, quietly premium — not a toy social app. Twilight is the night register.
- **Voice.** Elevated, plain, and council-minded; Tolkien-adjacent without cosplay. Warm but serious, never breezy. No hype, no exclamation marks, no startup-speak. Sentence case everywhere; the only uppercase is a typographic device, not shouting. The reader is *you*; the community is *we / the council*.
- **Lexicon** (binding): reply becomes **counsel**, community becomes **the council**, like or upvote becomes **commend**, reputation becomes **regard**, badges become **marks of esteem**, leaderboard becomes **top contributors**, tiers are Member, Veteran, Loremaster, Legend. The reaction set is Commend, Kindled, Seconded, Illuminating.
- **Emoji.** Not in UI chrome — status is always a word plus a colour, never an emoji. Authored content is different: members use emoji in posts, and the composer ships emoji tooling as product features.
- **The project's own line**, which governs how the product talks about itself: *status is verified, not asserted; outcomes resolve into artifacts; testimony never outranks the work.*

**Not binding.** The **name "RetroBoards" is still a placeholder** and may change. `PRODUCT_DESIGN.md` §1 calls it swappable, and the reference deployment already runs under a different domain. Future work must not treat the name as fixed, build identity around the word itself, or add naming claims.

## Evidence on Hand

Real, in-repo, and usable — future work cites these rather than inventing equivalents:

- **A live deployment** at `forum.candidary.online` (Cloudflare Workers), whose deployment runbook and worktree are the only correct place to deploy from.
- **Browser and accessibility evidence** in `docs/evidence/` — dozens of captured runs, including `imladris-forum-surfaces-production`, the admin and account slices, `dm-reimagine`, backup/restore rehearsals, and the Phase 5 Gate A closeout index at `docs/evidence/phase5/gate-a-closeout.md`.
- **A Playwright suite** (`tests/browser/`, roughly 40 specs) covering forum surfaces, admin and moderation consoles, composer, profiles, passkeys, providers, packages, invitations, appeals, group DMs, and link previews, plus `a11y.spec.ts` and `field-error-a11y.spec.ts`.
- **A PHPUnit suite** that drives the real kernel in-process (`App::handle()`) as a cookie-jar HTTP client.
- **Brand assets** in `docs/design-system/imladris/assets/`: the elven star and commend star SVGs, self-hosted OFL-licensed WOFF2 fonts (Cormorant Garamond, Marcellus, EB Garamond, JetBrains Mono), and the mood references `mood-hall.png` and `mood-elements.png`.
- **A decision record** — `docs/adr/` for every deferral, `PHASE_5_STATUS.md` for current state, `CHANGELOG.md` for history.

**Absences future work must not fabricate:** there are no customers, testimonials, case studies, press, pricing, user counts, or usability-test results. Success-metric *targets* do not exist yet — only the metrics and their definitions. There is no PHPUnit CI; the only CI workflow runs the Playwright evidence capture, so "the suite is green" is a local claim that has to be earned locally.

## Product Principles

1. **Familiarity is the feature.** When in doubt, do what Slack, Gmail, or Discord already taught the visitor. A borrowed pattern costs a new member nothing to learn, and learnability is a measured goal, not a nicety.
2. **Read first, ask later.** Guests read everything. Friction appears only at the moment of contribution, and it is explained in context rather than enforced at the door.
3. **The floor is HTML.** Every flow works server-rendered with no JavaScript, and enhancement is genuinely additive. This is what keeps the product crawlable, resilient, and cheap to self-host — three things that together decide whether a community survives.
4. **Evidence outranks assertion.** Nothing is done because it exists in the schema or in a template. Behaviour is enforced, tested, and — when it is visible — captured in the browser. The project's own line applies to the project's own work.
5. **Both audiences are real.** A member surface that treats the operator console as an afterthought, and an operator surface that treats members as rows in a table, are the same failure. Each surface serves its audience with full weight.
6. **Ownable by design.** Anything the project might outgrow sits behind a replaceable interface, and nothing core requires an external service. Self-hostable is a promise to the operator, not a deployment detail.

## Accessibility & Inclusion

- **Target: WCAG 2.0 and 2.1, Level A and AA**, enforced in capturable form — `tests/browser/a11y.spec.ts` runs axe with `wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa`.
- Semantic HTML with ARIA landmarks for the three panes, full keyboard navigation, and explicit focus management for the drawer and the account menu.
- Theme tokens were chosen for AA contrast in both the day and twilight registers; contrast is a token-level obligation, not a per-surface fix.
- `prefers-reduced-motion: reduce` is honoured in the stylesheet and must remain honoured by anything new.
- Anonymity, IP retention limits, minimal PII (email only), self-service export and deletion, and no third-party trackers by default are inclusion commitments as much as privacy ones.
