<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Server extensions'); ?>
<div class="admin">
    <header class="admin-head">
        <h1>Server extensions</h1>
        <span class="pill pill-admin">Admin mode</span>
    </header>
    <nav class="subnav">
        <a href="/admin">Dashboard</a>
        <a class="active" href="/admin/extensions">Extensions</a>
    </nav>

    <?php
    $activationAreas = [
        [
            'num' => '01',
            'title' => 'Polls',
            'desc' => 'A votable poll in a topic and the builder that replaces the raw text outline.',
            'flags' => [['polls', 'Flip now']],
            'summary' => 'Flip-ready',
        ],
        [
            'num' => '02',
            'title' => 'Organizing the rail',
            'desc' => 'Folders for boards, saved filters as feeds, and folders for your bookmarks.',
            'flags' => [['bookmark_folders', 'Capture away'], ['board_folders', 'Design-ahead'], ['saved_feeds', 'Design-ahead']],
            'summary' => '3 flags',
        ],
        [
            'num' => '03',
            'title' => 'In the conversation',
            'desc' => 'Catch-up memory, inline topic references, and links that unfurl into cards.',
            'flags' => [['community_memory', 'Capture away'], ['content_references', 'Capture away'], ['link_previews', 'Blocked']],
            'summary' => '3 flags',
        ],
        [
            'num' => '04',
            'title' => "The moderator's tools",
            'desc' => 'A workflow bar to assign, snooze and escalate; tools to split and merge topics.',
            'flags' => [['topic_workflow', 'Flip now'], ['split_merge', 'Design-ahead']],
            'summary' => '2 flags',
        ],
        [
            'num' => '05',
            'title' => 'Account & identity',
            'desc' => 'Steward-defined profile fields, and drafts that live on your account.',
            'flags' => [['custom_profile_fields', 'Capture away'], ['server_drafts', 'Flip now']],
            'summary' => '2 flags',
        ],
    ];
    $activationClass = static function (string $status): string {
        return match ($status) {
            'Flip now' => 'ready',
            'Capture away' => 'near',
            'Blocked' => 'blocked',
            default => 'ahead',
        };
    };
    ?>
    <section class="feature-activation-index admin-pane">
        <div class="feature-activation-lede">
            <span class="eyebrow">Designed surfaces · 11 flags</span>
            <h2>The UI that was missing</h2>
            <p>These feature surfaces come from the Imladris handoff bundle. Each card names the live flag and its honest activation tier so the design stays tied to what the build can actually carry.</p>
        </div>
        <div class="feature-status-legend" aria-label="Feature activation status legend">
            <span><span class="feature-status-dot ready"></span><strong>Flip now</strong></span>
            <span><span class="feature-status-dot near"></span><strong>Capture away</strong></span>
            <span><span class="feature-status-dot blocked"></span><strong>Blocked</strong></span>
            <span><span class="feature-status-dot ahead"></span><strong>Design-ahead</strong></span>
        </div>
        <div class="feature-area-grid">
            <?php foreach ($activationAreas as $area): ?>
                <article class="feature-area-card">
                    <div class="feature-area-top">
                        <span class="feature-area-num"><?= $e($area['num']) ?></span>
                        <span class="feature-area-summary"><?= $e($area['summary']) ?></span>
                    </div>
                    <h3><?= $e($area['title']) ?></h3>
                    <p><?= $e($area['desc']) ?></p>
                    <ul class="feature-flag-list">
                        <?php foreach ($area['flags'] as [$flagName, $status]): ?>
                            <?php $enabled = !empty($features[$flagName]); ?>
                            <li class="<?= $enabled ? 'is-enabled' : '' ?>">
                                <span class="feature-status-dot <?= $activationClass($status) ?>"></span>
                                <code><?= $e($flagName) ?></code>
                                <span><?= $enabled ? 'Enabled' : $e($status) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="admin-pane">
    <section class="card">
        <h2>Sandbox probe</h2>
        <p>
            <strong><?= !empty($probe['supported']) ? 'available' : 'unavailable' ?></strong>
            <span class="muted"><?= $e((string) ($probe['adapter'] ?? 'unknown')) ?></span>
        </p>
        <?php if (!empty($probe['reason'])): ?><p class="muted"><?= $e((string) $probe['reason']) ?></p><?php endif; ?>
    </section>

    <section class="card">
        <h2>Global emergency disable</h2>
        <p class="muted">Server extension execution is controlled by the server-side <code>server_extensions</code> feature flag. Turning it off leaves core forum routes independent of extension code.</p>
    </section>

    <section class="card">
        <h2>Handlers</h2>
        <table class="audit">
            <thead><tr><th>Package</th><th>Handler</th><th>Status</th><th>Entrypoint</th></tr></thead>
            <tbody>
            <?php foreach ($handlers as $h): ?>
                <tr>
                    <td><?= $e((string) ($h['package_name'] ?? $h['package_uid'] ?? 'package')) ?></td>
                    <td><?= $e((string) $h['handler_key']) ?></td>
                    <td><?= $e((string) $h['status']) ?></td>
                    <td><code><?= $e((string) $h['entrypoint']) ?></code></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($handlers)): ?><tr><td colspan="4" class="muted">No server extension handlers installed.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Run history</h2>
        <table class="audit">
            <thead><tr><th>When</th><th>Handler</th><th>Status</th><th>Detail</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $run): ?>
                <tr>
                    <td><?= $e(human_datetime((string) $run['finished_at'])) ?></td>
                    <td><?= $e((string) $run['handler_key']) ?></td>
                    <td><?= $e((string) $run['status']) ?></td>
                    <td><?= $e((string) ($run['error'] ?? '')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($runs)): ?><tr><td colspan="4" class="muted">No extension runs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </section>
    </div>
</div>
