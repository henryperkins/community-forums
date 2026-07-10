<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Thread Intelligence'); ?>
<div class="admin thread-intelligence-admin">
    <header class="admin-head">
        <span>
            <span class="eyebrow">Operations</span>
            <h1>Thread Intelligence</h1>
        </span>
        <span class="pill pill-admin">Admin mode</span>
    </header>

    <?= $this->partial('admin/_nav', ['active' => 'thread_intelligence', 'features' => $features ?? []]) ?>

    <div class="admin-pane">
        <?php if (!empty($dashboard['warnings'])): ?>
            <section class="card ti-attention" aria-labelledby="ti-warnings-heading">
                <h2 id="ti-warnings-heading">Needs attention</h2>
                <ul>
                    <?php foreach ($dashboard['warnings'] as $warning): ?><li><?= $e($warning) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <section class="admin-dashboard-grid" aria-label="Thread Intelligence status">
            <div class="card queue-card is-static">
                <span class="queue-card-head">Product flags</span>
                <strong class="queue-card-count"><?= !empty($dashboard['flags']['community_memory']) && !empty($dashboard['flags']['automated_context']) ? '2' : (int) !empty($dashboard['flags']['community_memory']) + (int) !empty($dashboard['flags']['automated_context']) ?></strong>
                <span class="queue-card-detail">community memory <?= !empty($dashboard['flags']['community_memory']) ? 'on' : 'off' ?> · automated context <?= !empty($dashboard['flags']['automated_context']) ? 'on' : 'off' ?></span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Provider</span>
                <strong class="queue-card-count"><?= !empty($dashboard['credential_ready']) ? 'Ready' : 'Not ready' ?></strong>
                <span class="queue-card-detail"><?= $e($dashboard['provider_label']) ?> · <?= !empty($dashboard['provider']['blocked']) ? 'latched' : 'available' ?></span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Worker</span>
                <strong class="queue-card-count"><?= $e($dashboard['heartbeat']['classification']) ?></strong>
                <span class="queue-card-detail"><?= $e($dashboard['heartbeat']['status'] ?? 'never run') ?></span>
            </div>
            <div class="card queue-card is-static">
                <span class="queue-card-head">Generation</span>
                <strong class="queue-card-count"><?= !empty($dashboard['pause']['paused']) ? 'Paused' : 'Running' ?></strong>
                <span class="queue-card-detail">Global provider egress brake</span>
            </div>
        </section>

        <section class="card ti-controls" aria-labelledby="ti-controls-heading">
            <h2 id="ti-controls-heading">Recovery controls</h2>
            <?php if (!empty($dashboard['pause']['paused'])): ?>
                <form class="inline" method="post" action="/admin/thread-intelligence/generation/resume">
                    <?= $this->csrfField() ?><button class="btn btn-small" type="submit">Resume generation</button>
                </form>
            <?php else: ?>
                <form class="inline" method="post" action="/admin/thread-intelligence/generation/pause">
                    <?= $this->csrfField() ?><button class="btn btn-small" type="submit">Pause generation</button>
                </form>
            <?php endif; ?>
            <form class="inline" method="post" action="/admin/thread-intelligence/provider/retry">
                <?= $this->csrfField() ?><button class="btn btn-small" type="submit">Retry provider configuration</button>
            </form>
            <p class="muted">Provider retry clears only the current health latch. Configure credentials outside this page.</p>
        </section>

        <?php
        $budget = $dashboard['budget'];
        $usedCalls = (int) $budget['used_calls'] + (int) $budget['reserved_calls'];
        $usedTokens = (int) $budget['used_input_tokens'] + (int) $budget['reserved_input_tokens'];
        ?>
        <section class="card ti-budget" aria-labelledby="ti-budget-heading">
            <h2 id="ti-budget-heading">Daily budget</h2>
            <label>Calls <?= $usedCalls ?> of <?= (int) $budget['call_limit'] ?>
                <progress max="<?= max(1, (int) $budget['call_limit']) ?>" value="<?= min($usedCalls, max(1, (int) $budget['call_limit'])) ?>"><?= $usedCalls ?></progress>
            </label>
            <label>Input tokens <?= $usedTokens ?> of <?= (int) $budget['input_token_limit'] ?>
                <progress max="<?= max(1, (int) $budget['input_token_limit']) ?>" value="<?= min($usedTokens, max(1, (int) $budget['input_token_limit'])) ?>"><?= $usedTokens ?></progress>
            </label>
            <p class="muted">Resets <?= $e($budget['next_reset_at']) ?> UTC</p>
        </section>

        <section class="admin-dashboard-grid" aria-label="Queue states">
            <?php foreach ($dashboard['queue'] as $state => $count): ?>
                <div class="card queue-card is-static">
                    <span class="queue-card-head"><?= $e(str_replace('_', ' ', ucfirst((string) $state))) ?></span>
                    <strong class="queue-card-count"><?= (int) $count ?></strong>
                    <span class="queue-card-detail">thread<?= (int) $count === 1 ? '' : 's' ?></span>
                </div>
            <?php endforeach; ?>
        </section>

        <section class="card">
            <h2>Generation contract</h2>
            <dl class="ti-metadata">
                <div><dt>Model</dt><dd><code><?= $e($dashboard['model']) ?></code></dd></div>
                <div><dt>Reasoning effort</dt><dd><?= $e($dashboard['reasoning_effort']) ?></dd></div>
                <div><dt>Prompt version</dt><dd><code><?= $e($dashboard['prompt_version']) ?></code></dd></div>
            </dl>
        </section>

        <section class="card">
            <h2>Recent generation evidence</h2>
            <?php if (empty($dashboard['recent_generations'])): ?>
                <p class="muted">No generation attempts have been recorded.</p>
            <?php else: ?>
                <div class="table-scroll" tabindex="0" role="region" aria-label="Recent redacted generation attempts">
                    <table class="audit">
                        <thead><tr><th>ID</th><th>Thread</th><th>Status</th><th>Requested</th><th>Contract</th><th>Evidence</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php foreach ($dashboard['recent_generations'] as $generation): ?>
                            <tr>
                                <td>#<?= (int) $generation['id'] ?></td>
                                <td>
                                    <?php if ($generation['thread_link'] !== null): ?>
                                        <a href="<?= $e($generation['thread_link']['url']) ?>"><?= $e($generation['thread_link']['title']) ?></a>
                                    <?php else: ?>
                                        Thread #<?= (int) $generation['thread_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="state state-<?= in_array($generation['status'], ['published', 'succeeded'], true) ? 'active' : 'pending' ?>"><?= $e($generation['status']) ?></span></td>
                                <td><?= $e($generation['requested_at']) ?> UTC</td>
                                <td><code><?= $e($generation['model'] ?? '—') ?></code><br><?= $e($generation['reasoning_effort'] ?? '—') ?> · <code><?= $e($generation['prompt_version'] ?? '—') ?></code></td>
                                <td>
                                    <details class="ti-evidence">
                                        <summary>Redacted details</summary>
                                        <p>Trigger <code><?= $e($generation['trigger_code']) ?></code> · retry <?= (int) $generation['retry_number'] ?> · window <?= (int) $generation['window_number'] ?></p>
                                        <?php if ($generation['failure_code'] !== null): ?><p>Failure <code><?= $e($generation['failure_code']) ?></code><?= $generation['failure_message'] !== null ? ' · ' . $e($generation['failure_message']) : '' ?></p><?php endif; ?>
                                        <?php if (!empty($generation['source_links'])): ?>
                                            <p>Sources:
                                                <?php foreach ($generation['source_links'] as $source): ?><a href="<?= $e($source['url']) ?>">Post #<?= (int) $source['id'] ?></a> <?php endforeach; ?>
                                            </p>
                                        <?php endif; ?>
                                        <?php if (!empty($generation['candidate_links'])): ?>
                                            <p>Candidates:
                                                <?php foreach ($generation['candidate_links'] as $candidate): ?><a href="<?= $e($candidate['url']) ?>">Thread #<?= (int) $candidate['id'] ?> · <?= $e($candidate['title']) ?></a> <?php endforeach; ?>
                                            </p>
                                        <?php endif; ?>
                                        <p>Usage: input <?= $e($generation['usage']['input_count'] ?? '—') ?> · output <?= $e($generation['usage']['output_count'] ?? '—') ?> · reasoning <?= $e($generation['usage']['reasoning_count'] ?? '—') ?> · cached <?= $e($generation['usage']['cached_count'] ?? '—') ?></p>
                                    </details>
                                </td>
                                <td class="ti-actions">
                                    <form method="post" action="/admin/thread-intelligence/threads/<?= (int) $generation['thread_id'] ?>/retry"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Retry</button></form>
                                    <form method="post" action="/admin/thread-intelligence/threads/<?= (int) $generation['thread_id'] ?>/reconcile"><?= $this->csrfField() ?><button class="linkbtn" type="submit">Reconcile</button></form>
                                    <form method="post" action="/admin/thread-intelligence/threads/<?= (int) $generation['thread_id'] ?>/<?= !empty($generation['thread_paused']) ? 'resume' : 'pause' ?>"><?= $this->csrfField() ?><button class="linkbtn" type="submit"><?= !empty($generation['thread_paused']) ? 'Resume' : 'Pause' ?></button></form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>
