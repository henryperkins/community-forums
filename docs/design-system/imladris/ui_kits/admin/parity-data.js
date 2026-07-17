/* Admin Console kit — seed data for the eight production-parity sections
   (feature flags, Thread Intelligence, packages, registry trust, themes,
   roles, sign-in providers, invitations). Faithful to the admin/*.php
   templates at RetroBoards @ 6d81da5. Merged onto window.RBAdmin. */
(function () {
  Object.assign(window.RBAdmin, {
    /* ── Feature flags (admin/features.php) ──────────────────────────────── */
    featureStats: { declared: 57, default_on: 49, default_off: 8, effective_on: 49, effective_off: 8, overrides: 2, unknown_overrides: 1 },
    featureGroups: [
      { group: 'Engagement & delivery', rows: [
        { flag: 'engagement', effective: true, default: true, override: null, rollback: 'Set false to hide reactions and regard; content unaffected.', readiness: null },
        { flag: 'notifications', effective: true, default: true, override: null, rollback: 'Set false to stop in-app notices; existing rows retained.', readiness: null },
        { flag: 'email', effective: true, default: true, override: null, rollback: 'Set false to drain the mail worker to a no-op.', readiness: { status: 'Operational configuration required', cls: 'state-pending', note: 'Sending domain SPF/DKIM must pass before broadcast.', href: '#', link: 'Email console' } },
        { flag: 'search', effective: true, default: true, override: null, rollback: 'Set false to fall back to unindexed board listing.', readiness: null },
        { flag: 'dms', effective: true, default: true, override: null, rollback: 'Set false to hide the messages room; threads retained.', readiness: null },
        { flag: 'presence', effective: true, default: true, override: null, rollback: 'Set false to stop presence beacons.', readiness: null },
      ] },
      { group: 'Content & composer', rows: [
        { flag: 'rich_composer', effective: true, default: true, override: null, rollback: 'Set false to serve the plain Markdown textarea.', readiness: null },
        { flag: 'wysiwyg_composer', effective: true, default: true, override: null, rollback: 'GA 2026-07-02. Set false to keep the source-mode textarea canonical.', readiness: null },
        { flag: 'server_drafts', effective: true, default: true, override: null, rollback: 'GA 2026-07-02 (ADR 0010). Set false to keep drafts client-only.', readiness: null },
        { flag: 'custom_emoji', effective: true, default: true, override: null, rollback: 'Set false to hide the custom set from the picker.', readiness: null },
        { flag: 'slash_giphy', effective: true, default: true, override: { cls: 'state-active', text: 'on' }, rollback: 'GA 2026-07-02; inert until a GIPHY key is configured.', readiness: { status: 'Operational configuration required', cls: 'state-pending', note: 'giphy_public_key is unset — the /giphy picker stays hidden.' } },
        { flag: 'community_memory', effective: true, default: true, override: null, rollback: 'Thread Intelligence GA 2026-07-12. Pause generation from Operations.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'Provider credential ready; worker healthy.', href: '#', link: 'Thread Intelligence' } },
        { flag: 'automated_context', effective: true, default: true, override: null, rollback: 'GA 2026-07-12. Companion to community_memory.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'Runs the automated related-context pass.', href: '#', link: 'Thread Intelligence' } },
      ] },
      { group: 'Platform · P5 Gate A (ADR 0018)', rows: [
        { flag: 'package_registry', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Set false to freeze catalogue reads.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'Trust keys pinned; refresh worker current.', href: '#', link: 'Packages' } },
        { flag: 'package_themes', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Falls back to the built-in system theme.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'One theme active; last-known-good recorded.', href: '#', link: 'Themes' } },
        { flag: 'capabilities', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Resolver posture is CAPABILITIES_MODE.', readiness: { status: 'Operational configuration required', cls: 'state-pending', note: 'Resolver in shadow — legacy rules still decide.', href: '#', link: 'Roles' } },
        { flag: 'passkeys', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Set false to hide passkey sign-in and enrolment.', readiness: null },
        { flag: 'provider_registry', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Generic OIDC providers are configuration, not code.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'Builtins visible; OIDC providers land disabled.', href: '#', link: 'Sign-in providers' } },
        { flag: 'invitations', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Set false to disable invite redemption.', readiness: null },
        { flag: 'service_secrets', effective: true, default: true, override: null, rollback: 'GA 2026-07-09. Encrypted vault for provider/integration secrets.', readiness: null },
      ] },
      { group: 'Implemented, default-dark', rows: [
        { flag: 'custom_css', effective: false, default: false, override: null, rollback: 'ADR 0009. Real UI exists behind the flag; enable to allow site CSS.', readiness: { status: 'Safety-blocked', cls: 'state-failed', note: 'Theme safe mode does not suppress /brand.css custom CSS, so the documented recovery path leaves broken CSS active.', href: '#', link: 'Custom CSS editor' } },
        { flag: 'group_dms', effective: false, default: false, override: null, rollback: 'Enable to allow multi-party direct messages.', readiness: { status: 'Ready for acceptance', cls: 'state-active', note: 'Member journey verified end-to-end on desktop and mobile; committed browser/no-JS/a11y evidence and the moderation runbook remain before enablement.', href: '#', link: 'Report queue' } },
        { flag: 'link_previews', effective: false, default: false, override: null, rollback: 'Enable to unfurl links in posts (fetch egress).', readiness: { status: 'Missing admin operations', cls: 'state-paused', note: 'The admin list surface, per-board opt-in, and author removal controls are absent.' } },
        { flag: 'expanded_files', effective: false, default: false, override: null, rollback: 'Enable to allow non-image attachments.', readiness: { status: 'Missing user UI', cls: 'state-paused', note: 'No member file chooser, no-JS upload form, quarantine states, or scanner outage workflow render yet.' } },
      ] },
      { group: 'Reserved · Gate B (no UI)', rows: [
        { flag: 'server_extensions', effective: false, default: false, override: null, rollback: 'Reserved. Enabling only unlocks the read-only Extensions probe.', readiness: { status: 'Reserved (ADR 0018)', cls: 'state-paused', note: 'No operator UI is shipped for this flag.' } },
        { flag: 'governance', effective: false, default: false, override: null, rollback: 'Reserved anchor; no behaviour.', readiness: { status: 'Reserved (ADR 0018)', cls: 'state-paused', note: 'Placeholder for a future policy engine.' } },
        { flag: 'service_principals', effective: false, default: false, override: null, rollback: 'Reserved anchor; no behaviour.', readiness: { status: 'Reserved (ADR 0018)', cls: 'state-paused', note: 'Placeholder for machine identities.' } },
        { flag: 'verified_links', effective: false, default: false, override: null, rollback: 'Reserved anchor; no behaviour.', readiness: { status: 'Reserved (ADR 0018)', cls: 'state-paused', note: 'Placeholder for domain verification.' } },
      ] },
    ],
    unknownOverrides: [
      { flag: 'legacy_beta_banner', valueText: 'true', rawValue: 'true' },
    ],

    /* ── Thread Intelligence (admin/thread_intelligence.php) ─────────────── */
    ti: {
      warnings: [],
      flags: { community_memory: true, automated_context: true },
      credentialReady: true, providerLabel: 'OpenAI · responses', providerBlocked: false,
      heartbeat: { classification: 'Healthy', status: 'last beat 40s ago' },
      paused: false,
      budget: { usedCalls: 312, reservedCalls: 6, callLimit: 2000, usedTokens: 486230, reservedTokens: 4200, tokenLimit: 3000000, nextReset: '2026-07-15 00:00' },
      queue: { pending: 4, in_progress: 1, published: 118, failed: 2 },
      model: 'gpt-5', reasoningEffort: 'medium', promptVersion: 'ti-brief@2026-07-12',
      recent: [
        { id: 8841, thread: 'Interpreting attention head #7', threadId: 1042, status: 'published', requested: '2026-07-14 08:12', model: 'gpt-5', effort: 'medium', prompt: 'ti-brief@2026-07-12', trigger: 'reply_burst', retry: 0, window: 3, failure: null, usage: { input: 12840, output: 940, reasoning: 610, cached: 8200 }, sources: [7731, 7742], candidates: [] },
        { id: 8840, thread: 'Eval harness flakiness', threadId: 1039, status: 'failed', requested: '2026-07-14 06:03', model: 'gpt-5', effort: 'medium', prompt: 'ti-brief@2026-07-12', trigger: 'schedule', retry: 1, window: 2, failure: { code: 'provider_timeout', message: 'upstream 30s deadline' }, usage: { input: 9120, output: 0, reasoning: 0, cached: 0 }, sources: [], candidates: [1044] },
      ],
    },

    /* ── Packages (admin/packages.php + package_*.php) ───────────────────── */
    packages: {
      registrySnapshots: [
        { sourceId: 'imladris-registry', fresh: true, expires: '2026-07-16 00:00' },
        { sourceId: 'community-mirror', fresh: false, expires: null },
      ],
      list: [
        { id: 1, name: 'Aurora', uid: 'imladris/aurora-theme', type: 'theme', installState: 'enabled', trustClass: 'community', latest: '1.4.2', compatible: true, blocked: false, advisoryStatus: 'none', registry: 'imladris-registry', publisher: 'Rivendell Atelier' },
        { id: 2, name: 'Anti-abuse scanner', uid: 'imladris/anti-abuse', type: 'integration', installState: 'installed', trustClass: 'first-party', latest: '3.1.0', compatible: true, blocked: false, advisoryStatus: 'none', registry: 'imladris-registry', publisher: 'Imladris Core' },
        { id: 3, name: 'Digest mailer', uid: 'imladris/digest', type: 'integration', installState: null, trustClass: 'community', latest: '0.9.0', compatible: true, blocked: false, advisoryStatus: 'advisory', registry: 'imladris-registry', publisher: 'Lindir Works' },
        { id: 4, name: 'Palantír embed', uid: 'thirdparty/palantir', type: 'integration', installState: null, trustClass: 'unverified', latest: '2.0.0', compatible: false, blocked: true, advisoryStatus: 'blocked', registry: 'community-mirror', publisher: 'unknown publisher' },
      ],
      detail: {
        1: {
          name: 'Aurora', uid: 'imladris/aurora-theme', type: 'theme', trustClass: 'community', advisoryStatus: 'none', blocked: false, registry: { sourceId: 'imladris-registry', baseUrl: 'https://registry.imladris.example' },
          releases: [
            { id: 14, version: '1.4.2', channel: 'stable', digest: 'a19f7c2e5b0d4411aa77', signedKey: 'atelier-2026', review: 'approved', coreMin: '1.0', coreMax: '*', compatible: true, advisory: 'none', blocked: false },
            { id: 12, version: '1.3.0', channel: 'stable', digest: '77c0d9be21aa0043fe18', signedKey: 'atelier-2026', review: 'approved', coreMin: '1.0', coreMax: '*', compatible: true, advisory: 'none', blocked: false },
          ],
          installed: { state: 'enabled', health: 'ok', version: '1.4.2', digest: 'a19f7c2e5b0d4411aa77c0', pinned: true, updatePolicy: 'notify' },
          permissions: [
            { label: 'Serve theme CSS', kind: 'render', key: 'theme.css', risk: 'low', granted: true },
            { label: 'Read branding tokens', kind: 'read', key: 'branding.tokens', risk: 'low', granted: true },
          ],
          history: [
            { event: 'enable', versions: '1.3.0 -> 1.4.2', digest: 'a19f7c2e5b0d', stage: '', detail: 'Consent re-granted', when: '2026-07-13 19:40' },
            { event: 'install', versions: '-> 1.3.0', digest: '77c0d9be21aa', stage: '', detail: '', when: '2026-07-01 09:10' },
          ],
          advisories: [],
        },
        2: {
          name: 'Anti-abuse scanner', uid: 'imladris/anti-abuse', type: 'integration', trustClass: 'first-party', advisoryStatus: 'none', blocked: false, registry: { sourceId: 'imladris-registry', baseUrl: 'https://registry.imladris.example' },
          releases: [
            { id: 31, version: '3.1.0', channel: 'stable', digest: 'be44aa019f7c2e5b0d44', signedKey: 'imladris-core', review: 'approved', coreMin: '1.0', coreMax: '*', compatible: true, advisory: 'none', blocked: false },
          ],
          installed: { state: 'installed', health: 'ok', version: '3.1.0', digest: 'be44aa019f7c2e5b0d4400', pinned: false, updatePolicy: 'manual' },
          permissions: [
            { label: 'Scan post content on create', kind: 'hook', key: 'post.create', risk: 'medium', granted: false },
            { label: 'Write moderation holds', kind: 'write', key: 'moderation.hold', risk: 'high', granted: false },
          ],
          history: [
            { event: 'install', versions: '-> 3.1.0', digest: 'be44aa019f7c', stage: '', detail: 'Awaiting consent', when: '2026-07-14 07:55' },
          ],
          advisories: [],
        },
      },
      security: {
        executionDisabled: false, affectedInstalls: 2,
        publishers: [
          { id: 1, displayName: 'Imladris Core', uid: 'pub/imladris-core', status: 'active', verifiedAt: '2026-06-20 00:00' },
          { id: 2, displayName: 'Rivendell Atelier', uid: 'pub/rivendell-atelier', status: 'active', verifiedAt: '2026-07-02 00:00' },
          { id: 3, displayName: 'Lindir Works', uid: 'pub/lindir-works', status: 'active', verifiedAt: null },
        ],
        transparency: [
          { when: '2026-07-13 19:40', event: 'package.enable', detail: 'imladris/aurora-theme 1.4.2' },
          { when: '2026-07-10 11:02', event: 'registry.key.pin', detail: 'atelier-2026' },
        ],
        advisoriesCount: 1, blocklistCount: 1,
      },
      publisherDetail: {
        2: {
          displayName: 'Rivendell Atelier', uid: 'pub/rivendell-atelier', status: 'active', verifiedAt: '2026-07-02 00:00',
          keys: [ { id: 5, keyId: 'atelier-2026', status: 'active', validFrom: '2026-01-01', validUntil: 'inf', fingerprint: 'c41d9a77e0b3f218' } ],
          packages: [ { uid: 'imladris/aurora-theme', advisoryStatus: 'none', decisions: [ { decision: 'approved', digest: 'a19f7c2e5b0d', source: 'local-review' } ] } ],
        },
      },
    },

    /* ── Registry trust (admin/registries.php) ───────────────────────────── */
    registries: {
      list: [
        { id: 1, sourceId: 'imladris-registry', displayName: 'Imladris registry', baseUrl: 'https://registry.imladris.example', enabled: true, snapshot: { generated: '2026-07-14 00:00', expires: '2026-07-16 00:00' },
          keys: [
            { id: 1, keyId: 'imladris-2026', status: 'active', validFrom: '2026-01-01', validUntil: 'inf', fingerprint: '9f2a7c41d0b3e881', revokedReason: null },
            { id: 2, keyId: 'imladris-2025', status: 'revoked', validFrom: '2025-01-01', validUntil: '2026-01-01', fingerprint: '11a0be77c2d94430', revokedReason: 'scheduled rotation' },
          ] },
        { id: 2, sourceId: 'community-mirror', displayName: 'Community mirror', baseUrl: 'https://mirror.example', enabled: false, snapshot: null,
          keys: [ { id: 3, keyId: 'mirror-2026', status: 'active', validFrom: '2026-03-01', validUntil: 'inf', fingerprint: '77e0b3f218c41d9a', revokedReason: null } ] },
      ],
      blocks: [ { id: 1, digest: 'deadbeef00c0ffee1122', uid: 'thirdparty/palantir', reason: 'incompatible + unverified publisher' } ],
      advisories: [ { id: 1, uid: 'ADV-2026-014', pkgUid: 'imladris/digest', severity: 'moderate', action: 'upgrade', ack: null } ],
    },

    /* ── Themes (admin/themes.php + theme_safe_mode.php) ─────────────────── */
    themes: {
      safeMode: false, forcedSafeMode: false,
      active: { packageName: 'Aurora', uid: 'imladris/aurora-theme', version: '1.4.2', cssDigest: 'a19f7c2e5b0d4411', installState: 'enabled', activatedAt: '2026-07-13 19:40' },
      lkg: { cssDigest: '77c0d9be21aa0043', uid: 'imladris/aurora-theme', version: '1.3.0' },
      installs: [
        { id: 1, packageName: 'Aurora', uid: 'imladris/aurora-theme', version: '1.4.2', state: 'enabled', latestBuild: 'a19f7c2e5b0d4411', packageId: 1 },
        { id: 2, packageName: 'Mithril', uid: 'imladris/mithril-theme', version: '0.8.0', state: 'installed', latestBuild: null, packageId: 5 },
      ],
      preview: null,
    },

    /* ── Roles & capabilities (admin/roles.php + role_edit/simulator) ────── */
    roles: {
      mode: 'shadow',
      rows: [
        { id: 1, name: 'Administrator', roleKey: 'admin', kind: 'system', version: 1, capabilityCount: 42, impact: 2 },
        { id: 2, name: 'Moderator', roleKey: 'moderator', kind: 'system', version: 1, capabilityCount: 18, impact: 3 },
        { id: 3, name: 'Member', roleKey: 'member', kind: 'system', version: 1, capabilityCount: 7, impact: 1240 },
        { id: 4, name: 'Board steward', roleKey: 'board_steward', kind: 'custom', version: 3, capabilityCount: 5, impact: 4 },
      ],
      catalogue: {
        'thread.lock': { consent: 'Lock and unlock threads', risk: 'normal', enforced: true },
        'post.hide': { consent: 'Hide posts pending review', risk: 'normal', enforced: true },
        'user.role_change': { consent: 'Change member roles', risk: 'high', enforced: true },
        'board.manage': { consent: 'Create, edit, and archive boards', risk: 'high', enforced: true },
        'badge.grant': { consent: 'Grant manual badges', risk: 'normal', enforced: false },
        'announcement.publish': { consent: 'Publish site banners', risk: 'normal', enforced: false },
      },
      detail: {
        4: {
          role: { id: 4, name: 'Board steward', roleKey: 'board_steward', kind: 'custom', version: 3, description: 'Keeps a single board tidy.' },
          currentKeys: ['thread.lock', 'post.hide'],
          impact: 4,
          assignments: [
            { id: 1, username: 'glorfindel', scopeType: 'board', scopeId: 21, scopeName: 'interpretability', starts: 'now', ends: 'no expiry', status: 'active' },
            { id: 2, username: 'arwen', scopeType: 'board', scopeId: 22, scopeName: 'evaluations', starts: '2026-07-01', ends: '2026-12-31', status: 'active' },
          ],
        },
        1: {
          role: { id: 1, name: 'Administrator', roleKey: 'admin', kind: 'system', version: 1, description: 'Protected system anchor.' },
          currentKeys: ['thread.lock', 'post.hide', 'user.role_change', 'board.manage'],
          impact: 2, assignments: [],
        },
      },
      boards: [ { id: 21, name: 'interpretability' }, { id: 22, name: 'evaluations' }, { id: 13, name: 'the-valley' } ],
      simulator: { actor: 'glorfindel', capability: 'thread.lock', boardId: '21', at: '', result: { allowed: true, capability: 'thread.lock', actorLabel: '@glorfindel', targetLabel: 'board #21 (interpretability)', source: 'role_assignment', reason: 'Role board_steward grants thread.lock at board #21', roleKey: 'board_steward', scopeType: 'board', scopeId: 21 } },
    },

    /* ── Sign-in providers (admin/providers.php + provider_disable.php) ───── */
    providers: {
      rows: [
        { id: 1, displayName: 'Google', providerKey: 'google', type: 'builtin', issuer: null, health: 'not checked', healthCheckedAt: null, soleMethodCount: 0, isEnabled: true, envConfigured: true },
        { id: 2, displayName: 'GitHub', providerKey: 'github', type: 'builtin', issuer: null, health: 'not checked', healthCheckedAt: null, soleMethodCount: 0, isEnabled: true, envConfigured: false },
        { id: 3, displayName: 'Council GitLab', providerKey: 'gitlab', type: 'generic_oidc', issuer: 'https://gitlab.com', health: 'ok', healthCheckedAt: '2h ago', soleMethodCount: 3, isEnabled: true, envConfigured: false },
        { id: 4, displayName: 'Numenor SSO', providerKey: 'numenor', type: 'generic_oidc', issuer: 'https://id.numenor.example', health: 'never', healthCheckedAt: null, soleMethodCount: 0, isEnabled: false, envConfigured: false },
      ],
      disableTarget: {
        3: { id: 3, displayName: 'Council GitLab', providerKey: 'gitlab', soleAccounts: [
          { username: 'lindir', email: 'lindir@imladris.council' },
          { username: 'gildor', email: 'gildor@imladris.council' },
          { username: 'melian', email: 'melian@imladris.council' },
        ] },
      },
    },

    /* ── Invitations (admin/invitations.php) ─────────────────────────────── */
    invitations: {
      limits: { maxUses: 100, maxExpiryDays: 365, defaultExpiryDays: 14 },
      boards: [ { id: 12, name: 'introductions' }, { id: 13, name: 'the-valley' } ],
      newInvitation: null,
      rows: [
        { id: 1, created: '2h ago', creator: 'elrond', email: 'nimrodel@example.com', domain: null, usedCount: 0, maxUses: 1, expires: 'in 14 days', status: 'active' },
        { id: 2, created: 'yesterday', creator: 'elrond', email: null, domain: 'lorien.example', usedCount: 3, maxUses: 25, expires: 'in 10 days', status: 'active' },
        { id: 3, created: '3 days ago', creator: 'galadriel', email: null, domain: null, usedCount: 1, maxUses: 1, expires: '—', status: 'redeemed' },
        { id: 4, created: 'last week', creator: 'elrond', email: 'saruman@isengard.example', domain: null, usedCount: 0, maxUses: 1, expires: 'expired', status: 'revoked' },
      ],
    },
  });
})();
