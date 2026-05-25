<?php $curLang = currentLang(); $allLangs = availableLangs(); ?>
<div class="dropdown">
    <button class="btn btn-sm btn-outline-secondary py-1 dropdown-toggle d-flex align-items-center gap-1"
            type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <svg class="bi my-1" width="1em" height="1em" style="fill:currentColor">
            <use href="#translate"></use>
        </svg>
        <span class="visually-hidden"><?= e(strtoupper($curLang)) ?></span>
    </button>
    <ul class="dropdown-menu dropdown-menu-end shadow">
        <?php foreach ($allLangs as $code => $name): ?>
        <li>
            <button type="button" class="dropdown-item d-flex align-items-center gap-2<?= $code === $curLang ? ' active' : '' ?>"
                    onclick="setOtaLang('<?= e($code) ?>')">
                <span class="text-muted small fw-mono"><?= e(strtoupper($code)) ?></span>
                <?= e($name) ?>
                <svg class="bi ms-auto <?= $code === $curLang ? '' : 'd-none' ?>" width="1em" height="1em" style="fill:currentColor"><use href="#check2"></use></svg>
            </button>
        </li>
        <?php endforeach; ?>
    </ul>
</div>
