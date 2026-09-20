<?php /** @var \App\Core\View $this */ ?>
    <?php foreach ($groups as $group): ?>
        <div class="settings-rail-group">
            <span class="settings-rail-title"><?= $e($group['label']) ?></span>
            <?php foreach ($group['items'] as $item): ?>
                <?php if (isset($item['feature']) && empty($features[$item['feature']])): ?>
                    <?php continue; ?>
                <?php endif; ?>
                <?php $isActive = $active === $item['key']; ?>
                <a class="settings-rail-link<?= $isActive ? ' is-active' : '' ?>" data-settings-key="<?= $e($item['key']) ?>" href="<?= $e($item['href']) ?>"<?= $isActive ? ' aria-current="page"' : '' ?>><?= $this->partial('partials/icon', ['name' => $item['icon']]) ?><span><?= $e($item['label']) ?></span></a>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
    <?php if (!empty($features['product_tour'])): ?>
        <button class="linkbtn subnav-action" type="button" data-tour-replay>Replay tour</button>
    <?php endif; ?>
