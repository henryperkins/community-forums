<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'Thread Intelligence'); $this->section('variant', 'admin'); ?>
<?= $this->partial('admin/_console', ['area' => 'settings', 'tab' => 'thread_intelligence', 'pane_class' => 'thread-intelligence-admin']) ?>
        <?php if (!empty($dashboard['warnings'])): ?>
            <section class="card ti-attention" aria-labelledby="ti-warnings-heading">
                <h2 id="ti-warnings-heading">Needs attention</h2>
                <ul>
                    <?php foreach ($dashboard['warnings'] as $warning): ?><li><?= $e($warning) ?></li><?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <p class="ti-intro">Automated context for long topics. Staff set the terms; the model proposes and local validation decides. Everything it writes is evidenced below, and the egress brake is one button away.</p>

<?php
        // .queue-card's left rule defaults to --success, so a card that renders a
        // non-success fact has to say so or the rail reads "all green" while the
        // page is telling the operator something is wrong. attention = a fault to
        // act on; unavailable = deliberately off, not broken.
        $flagsOn = !empty($dashboard['flags']['community_memory']) && !empty($dashboard['flags']['automated_context']);
        $heartbeatState = (string) $dashboard['heartbeat']['classification'];
        $workerStatus = match ($heartbeatState) {
            'healthy', 'running' => '',
            'never_run' => ' queue-status-unavailable',
            default => ' queue-status-attention',
        };
        $heartbeatLabels = [
            'never_run' => 'Never run',
            'invalid' => 'Invalid',
            'running' => 'Running',
            'interrupted' => 'Interrupted',
            'attention' => 'Needs attention',
            'stale' => 'Stale',
            'healthy' => 'Healthy',
        ];
        $heartbeatCompleted = human_datetime($dashboard['heartbeat']['completed_at'] ?? null);
        $heartbeatStarted = human_datetime($dashboard['heartbeat']['started_at'] ?? null);
        $heartbeatDetail = $heartbeatCompleted !== ''
            ? 'Last run ' . $heartbeatCompleted
            : ($heartbeatStarted !== '' ? 'Started ' . $heartbeatStarted : match ($heartbeatState) {
                'invalid' => 'Heartbeat record is invalid',
                default => 'No completed run',
            });
        ?>
        <section class="ti-status-grid" aria-label="Thread Intelligence status">
            <div class="card queue-card is-static ti-status-card<?= $flagsOn ? '' : ' queue-status-unavailable' ?>" data-ti-status="product-flags">
                <span class="ti-status-label">Product flags</span>
                <strong class="ti-status-value"><?= $flagsOn ? '2' : (int) !empty($dashboard['flags']['community_memory']) + (int) !empty($dashboard['flags']['automated_context']) ?></strong>
                <span class="ti-status-detail">community memory <?= !empty($dashboard['flags']['community_memory']) ? 'on' : 'off' ?> · automated context <?= !empty($dashboard['flags']['automated_context']) ? 'on' : 'off' ?></span>
            </div>
            <div class="card queue-card is-static ti-status-card<?= !empty($dashboard['provider']['blocked']) ? ' queue-status-attention' : (!empty($dashboard['credential_ready']) ? '' : ' queue-status-unavailable') ?>" data-ti-status="provider">
                <span class="ti-status-label">Provider</span>
                <strong class="ti-status-value"><?= !empty($dashboard['credential_ready']) ? 'Ready' : 'Not ready' ?></strong>
                <span class="ti-status-detail"><?= $e($dashboard['provider_label']) ?> · <?= !empty($dashboard['provider']['blocked']) ? 'latched' : 'available' ?></span>
            </div>
            <div class="card queue-card is-static ti-status-card<?= $workerStatus ?>" data-ti-status="heartbeat">
                <span class="ti-status-label">Heartbeat</span>
                <strong class="ti-status-value"><?= $e($heartbeatLabels[$heartbeatState] ?? 'Needs attention') ?></strong>
                <span class="ti-status-detail"><?= $e($heartbeatDetail) ?></span>
            </div>
            <div class="card queue-card is-static ti-status-card<?= !empty($dashboard['pause']['paused']) ? ' queue-status-unavailable' : '' ?>" data-ti-status="generation">
                <span class="ti-status-label">Generation</span>
                <strong class="ti-status-value"><?= !empty($dashboard['pause']['paused']) ? 'Paused' : 'Running' ?></strong>
                <span class="ti-status-detail">Global provider egress brake</span>
            </div>
        </section>

        <section class="card ti-controls" aria-labelledby="ti-controls-heading">
            <h2 id="ti-controls-heading">Recovery controls</h2>
            <div class="ti-controls-actions">
                <?php if (!empty($dashboard['pause']['paused'])): ?>
                    <form class="inline" method="post" action="/admin/thread-intelligence/generation/resume">
                        <?= $this->csrfField() ?><button class="btn btn-small btn-secondary" type="submit">Resume generation</button>
                    </form>
                <?php else: ?>
                    <form class="inline" method="post" action="/admin/thread-intelligence/generation/pause">
                        <?= $this->csrfField() ?><button class="btn btn-small btn-secondary" type="submit">Pause generation</button>
                    </form>
                <?php endif; ?>
                <form class="inline" method="post" action="/admin/thread-intelligence/provider/retry">
                    <?= $this->csrfField() ?><button class="btn btn-small btn-secondary" type="submit">Retry provider configuration</button>
                </form>
            </div>
            <p class="muted">Provider retry clears only the current health latch. Configure credentials outside this page.</p>
        </section>

        <?php
        $budget = $dashboard['budget'];
        $usedCalls = (int) $budget['used_calls'] + (int) $budget['reserved_calls'];
        $usedTokens = (int) $budget['used_input_tokens'] + (int) $budget['reserved_input_tokens'];
        $callLimit = (int) $budget['call_limit'];
        $tokenLimit = (int) $budget['input_token_limit'];
        $budgetResetAt = (string) $budget['next_reset_at'];
        $budgetResetTimestamp = strtotime($budgetResetAt . ' UTC');
        $budgetReset = $budgetResetTimestamp === false ? $budgetResetAt : gmdate('Y-m-d H:i', $budgetResetTimestamp);
        ?>
        <section class="card ti-budget" aria-labelledby="ti-budget-heading">
            <h2 id="ti-budget-heading">Daily budget</h2>
            <label class="ti-budget-row ti-budget-calls">
                <span class="ti-budget-row-head">
                    <span class="ti-budget-label">Calls</span>
                    <span class="ti-budget-value"><?= number_format($usedCalls) ?> of <?= number_format($callLimit) ?></span>
                </span>
                <progress max="<?= max(1, $callLimit) ?>" value="<?= min($usedCalls, max(1, $callLimit)) ?>"><?= $usedCalls ?></progress>
            </label>
            <label class="ti-budget-row ti-budget-tokens">
                <span class="ti-budget-row-head">
                    <span class="ti-budget-label">Input tokens</span>
                    <span class="ti-budget-value"><?= number_format($usedTokens) ?> of <?= number_format($tokenLimit) ?></span>
                </span>
                <progress max="<?= max(1, $tokenLimit) ?>" value="<?= min($usedTokens, max(1, $tokenLimit)) ?>"><?= $usedTokens ?></progress>
            </label>
            <p class="ti-budget-reset">Resets <?= $e($budgetReset) ?> UTC</p>
        </section>

        <?php $queueStates = ['idle', 'queued', 'running', 'retry', 'dead', 'review_required']; ?>
        <section class="ti-queue-section" aria-labelledby="ti-queue-heading">
            <h2 id="ti-queue-heading">Queue states</h2>
            <div class="ti-queue-grid">
                <?php foreach ($queueStates as $state): ?>
                    <?php $count = (int) ($dashboard['queue'][$state] ?? 0); ?>
                    <div class="card ti-queue-card" data-ti-queue-state="<?= $e($state) ?>">
                        <strong class="ti-queue-count"><?= $count ?></strong>
                        <span class="ti-queue-label"><?= $e(ucfirst(str_replace('_', ' ', $state))) ?></span>
                        <span class="ti-queue-unit">thread<?= $count === 1 ? '' : 's' ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <div class="ti-contract-grid">
            <section class="card ti-contract-card">
                <h2>Generation contract</h2>
                <dl class="ti-metadata">
                    <div><dt>Model</dt><dd><code><?= $e($dashboard['model']) ?></code></dd></div>
                    <div><dt>Reasoning effort</dt><dd><?= $e($dashboard['reasoning_effort']) ?></dd></div>
                    <div><dt>Prompt version</dt><dd><code><?= $e($dashboard['prompt_version']) ?></code></dd></div>
                </dl>
            </section>

            <?php $evidenceCount = count($dashboard['recent_generations']); ?>
            <section class="card ti-evidence-card">
                <div class="ti-evidence-head">
                    <h2>Recent generation evidence</h2>
                    <span class="ti-evidence-count"><?= $evidenceCount ?> run<?= $evidenceCount === 1 ? '' : 's' ?></span>
                </div>
            <?php if (empty($dashboard['recent_generations'])): ?>
                <div class="ti-evidence-empty state-empty">
                    <h3>No generation attempts</h3>
                    <p>No generation attempts have been recorded.</p>
                </div>
            <?php else: ?>
                <div class="table-scroll table-scroll-wide" tabindex="0" role="region" aria-label="Recent redacted generation attempts">
                    <table class="audit">
                        <thead>
                            <tr>
                                <th scope="col">ID</th>
                                <th scope="col">When</th>
                                <th scope="col">Topic</th>
                                <th scope="col">Outcome</th>
                                <th scope="col" class="numeric">Input tokens</th>
                                <th scope="col">Contract</th>
                                <th scope="col">Evidence</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($dashboard['recent_generations'] as $generation): ?>
                            <?php
                            $generationStatus = (string) $generation['status'];
                            $outcomeRegister = match ($generationStatus) {
                                'published', 'succeeded' => 'done',
                                'failed', 'dead', 'rejected', 'review_required' => 'attention',
                                default => 'neutral',
                            };
                            $outcomeClass = match ($outcomeRegister) {
                                'done' => ' state-active',
                                'attention' => ' state-failed',
                                default => '',
                            };
                            $inputTokens = $generation['usage']['input_count'] ?? null;
                            $requestedAt = (string) $generation['requested_at'];
                            $requestedTimestamp = strtotime($requestedAt . ' UTC');
                            $requestedShort = $requestedTimestamp === false ? $requestedAt : gmdate('j M H:i', $requestedTimestamp);
                            $requestedIso = $requestedTimestamp === false ? $requestedAt : gmdate('Y-m-d\TH:i:s\Z', $requestedTimestamp);
                            $requestedFull = $requestedTimestamp === false ? $requestedAt . ' UTC' : human_datetime($requestedAt);
                            ?>
                            <tr>
                                <td>#<?= (int) $generation['id'] ?></td>
                                <td class="ti-evidence-time"><time datetime="<?= $e($requestedIso) ?>" title="<?= $e($requestedFull) ?>"><?= $e($requestedShort) ?></time></td>
                                <td>
                                    <?php if ($generation['thread_link'] !== null): ?>
                                        <a href="<?= $e($generation['thread_link']['url']) ?>"><?= $e($generation['thread_link']['title']) ?></a>
                                    <?php else: ?>
                                        Thread #<?= (int) $generation['thread_id'] ?>
                                    <?php endif; ?>
                                </td>
                                <td><span class="state<?= $outcomeClass ?>" data-ti-outcome="<?= $e($outcomeRegister) ?>" data-ti-generation-status="<?= $e($generationStatus) ?>"><?= $e(ucfirst(str_replace('_', ' ', $generationStatus))) ?></span></td>
                                <td class="numeric mono"><?= $inputTokens === null ? '—' : number_format((int) $inputTokens) ?></td>
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
<?= $this->partial('admin/_console_end') ?>
