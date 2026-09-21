<?php /** @var \App\Core\View $this */ ?>
<time class="<?= $e($class ?? '') ?>" datetime="<?= $e(\App\Support\DmTime::iso($at ?? null)) ?>" title="<?= $e(($at ?? '') . ' UTC') ?>" data-dm-time="<?= $e($mode ?? 'relative') ?>"><?= $e(\App\Support\DmTime::label($at ?? null, $mode ?? 'relative')) ?></time>
