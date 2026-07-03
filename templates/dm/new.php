<?php /** @var \App\Core\View $this */ ?>
<?php $this->layout('layout'); $this->section('title', 'New message'); ?>
<div class="dm-shell reading">
    <aside class="dm-listpane dm-return-pane" aria-label="Messages">
        <header class="dm-listpane-head">
            <div class="dm-listpane-top">
                <span>
                    <span class="eyebrow">Private counsel</span>
                    <h1>Messages</h1>
                </span>
            </div>
        </header>
        <div class="dm-empty-inner dm-return-copy">
            <span class="star" aria-hidden="true">✦</span>
            <p><a href="/messages">Back to all messages</a></p>
        </div>
    </aside>

    <section class="dm-threadpane">
        <div class="dm-compose">
            <div class="dm-compose-wrap">
                <p class="breadcrumb"><a href="/messages">← Messages</a></p>
                <span class="eyebrow">Private counsel</span>
                <h1>New message</h1>

                <form class="dm-form composer" method="post" action="/messages" data-composer-context="dm" data-composer-target-id="0">
                    <?= $this->csrfField() ?>
                    <?= $this->partial('partials/dm_compose_fields', [
                        'to' => $to,
                        'title' => $title ?? '',
                        'body' => $body,
                        'errors' => $errors,
                        'allow_groups' => $allowGroups ?? false,
                    ]) ?>
                    <div class="form-actions"><button class="btn" type="submit">Send message</button></div>
                </form>
            </div>
        </div>
    </section>
</div>
