<?php
requireLogin();
$pageTitle  = __('dash_title');
$activePage = 'dashboard';

$devices    = getAllDevices();
$total      = count($devices);
$online     = 0; $offline = 0; $update = 0; $installing = 0;
$dashGroups = array_values(array_unique(array_column($devices, 'group_slug')));
$dashTypes  = array_values(array_unique(array_column($devices, 'type_slug')));
sort($dashGroups); sort($dashTypes);

foreach ($devices as $d) {
    $s = deviceStatus($d);
    if ($s === 'offline' || $s === 'never') $offline++;
    elseif ($s === 'update')     $update++;
    elseif ($s === 'installing') $installing++;
    else $online++;
}

include dirname(__FILE__) . '/header.php';
?>

<meta name="csrf-token" content="<?= e(csrfToken()) ?>">

<!-- Stat cards -->
<div class="row row-cols-2 row-cols-xl-5 g-3 mb-4">
    <div class="col">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-hdd-network"></i>
                </div>
                <div>
                    <div class="stat-value" id="stat-total"><?= $total ?></div>
                    <div class="stat-label"><?= e(__('stat_total')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value" id="stat-online"><?= $online ?></div>
                    <div class="stat-label"><?= e(__('stat_online')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                    <i class="bi bi-wifi-off"></i>
                </div>
                <div>
                    <div class="stat-value" id="stat-offline"><?= $offline ?></div>
                    <div class="stat-label"><?= e(__('stat_offline')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-arrow-up-circle"></i>
                </div>
                <div>
                    <div class="stat-value" id="stat-update"><?= $update ?></div>
                    <div class="stat-label"><?= e(__('stat_update')) ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="col">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="stat-icon bg-info bg-opacity-10 text-info">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="stat-value" id="stat-installing"><?= $installing ?></div>
                    <div class="stat-label"><?= e(__('stat_installing')) ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tabulka zařízení -->
<div class="card shadow-sm">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h5 class="mb-0"><i class="bi bi-hdd-network me-2"></i><?= e(__('nav_devices')) ?></h5>
        <div class="d-flex align-items-center gap-2">
            <span id="dash-updated" class="badge bg-secondary bg-opacity-10 text-secondary fw-normal d-none" style="font-size:0.75rem">
                <i class="bi bi-arrow-clockwise me-1"></i><span id="dash-updated-time"></span>
            </span>
            <a href="index.php?page=devices" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-gear me-1"></i><?= e(__('btn_management')) ?>
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <?php if (empty($devices)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-hdd-network display-6 d-block mb-2 opacity-25"></i>
                <?= e(__('dash_no_devices')) ?><br>
                <small><?= e(__('dash_no_devices_hint')) ?></small>
            </div>
        <?php else: ?>
        <?php if (count($dashGroups) > 1 || count($dashTypes) > 1): ?>
        <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center">
            <?php if (count($dashGroups) > 1): ?>
            <select id="dashFilterGroup" class="form-select form-select-sm" style="width:auto">
                <option value=""><?= e(__('filter_all_groups')) ?></option>
                <?php foreach ($dashGroups as $g): ?>
                <option value="<?= e($g) ?>"><?= e($g) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <?php if (count($dashTypes) > 1): ?>
            <select id="dashFilterType" class="form-select form-select-sm" style="width:auto">
                <option value=""><?= e(__('filter_all_types')) ?></option>
                <?php foreach ($dashTypes as $t): ?>
                <option value="<?= e($t) ?>"><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= e(__('label_status')) ?></th>
                        <th><?= e(__('nav_devices')) ?></th>
                        <th class="d-none d-sm-table-cell"><?= e(__('dash_col_fw_current')) ?></th>
                        <th class="d-none d-md-table-cell"><?= e(__('dash_col_fw_available')) ?></th>
                        <th class="d-none d-lg-table-cell"><?= e(__('dash_col_group_type_comp')) ?></th>
                        <th class="d-none d-xl-table-cell"><?= e(__('label_hw_version')) ?></th>
                        <th class="d-none d-xl-table-cell"><?= e(__('label_uptime')) ?></th>
                        <th>
                            <span class="d-sm-none"><?= e(__('label_last_seen_short')) ?></span>
                            <span class="d-none d-sm-inline"><?= e(__('label_last_seen')) ?></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($devices as $d):
                        $status  = deviceStatus($d);
                        $latest  = getLatestFirmware($d['group_slug'], $d['type_slug'], $d['component_slug'], $d['hw_version']);
                    ?>
                    <tr data-device-key="<?= e($d['device_key']) ?>" data-group="<?= e($d['group_slug']) ?>" data-type="<?= e($d['type_slug']) ?>" data-status="<?= $status ?>">
                        <td data-dash="badge"><?= deviceStatusBadge($status) ?></td>
                        <td>
                            <span class="fw-semibold"><?= e($d['label'] ?: formatMac($d['device_key'])) ?></span><?= devicePolicyBadges($d) ?>
                            <?php if ($d['label']): ?>
                                <div class="text-muted" style="font-size:0.72rem"><code><?= e(formatMac($d['device_key'])) ?></code></div>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-sm-table-cell" data-dash="fw"><code><?= e($d['fw_version'] ?? '—') ?></code></td>
                        <td class="d-none d-md-table-cell" data-dash="latest_fw">
                            <?php if ($latest): ?>
                                <code class="<?= $latest['fw_version'] !== ($d['fw_version'] ?? '') ? 'text-warning' : '' ?>">
                                    <?= e($latest['fw_version']) ?>
                                </code>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small d-none d-lg-table-cell">
                            <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($d['group_slug']) ?></span>
                            <?= e($d['type_slug']) ?> / <?= e($d['component_slug']) ?>
                        </td>
                        <td class="d-none d-xl-table-cell"><code><?= e($d['hw_version']) ?></code></td>
                        <td class="small d-none d-xl-table-cell" data-dash="uptime"><?= $d['uptime'] ? e(formatUptime($d['uptime'])) : '—' ?></td>
                        <td class="small text-muted" data-dash="last_seen">
                            <?php if ($d['last_seen']): $ts = strtotime($d['last_seen']); ?>
                            <span class="d-sm-none"><?= e(date('d.m. H:i', $ts)) ?></span>
                            <span class="d-none d-sm-inline"><?= e(date('d.m.Y H:i', $ts)) ?></span>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
(function () {
    const INTERVAL = 30000;

    function recountStats() {
        let total = 0, online = 0, offline = 0, update = 0, installing = 0;
        document.querySelectorAll('tr[data-device-key]').forEach(row => {
            if (row.style.display === 'none') return;
            total++;
            const s = row.dataset.status;
            if (s === 'offline' || s === 'never') offline++;
            else if (s === 'update')     update++;
            else if (s === 'installing') installing++;
            else online++;
        });
        const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
        set('stat-total', total); set('stat-online', online); set('stat-offline', offline);
        set('stat-update', update); set('stat-installing', installing);
    }

    async function poll() {
        try {
            const res  = await fetch('./ajax/dashboard_data.php');
            const data = await res.json();
            if (!data.ok) return;

            // Pokud přibylo / ubylo zařízení → reload
            const rows = document.querySelectorAll('tr[data-device-key]');
            if (rows.length !== data.devices.length) { location.reload(); return; }

            // Řádky tabulky
            data.devices.forEach(d => {
                const row = document.querySelector(`tr[data-device-key="${d.key}"]`);
                if (!row) return;

                row.dataset.status = d.status;

                const badge    = row.querySelector('[data-dash="badge"]');
                const fw       = row.querySelector('[data-dash="fw"]');
                const latestFw = row.querySelector('[data-dash="latest_fw"]');
                const uptime   = row.querySelector('[data-dash="uptime"]');
                const lastSeen = row.querySelector('[data-dash="last_seen"]');

                if (badge)    badge.innerHTML = d.badge;
                if (fw)       fw.innerHTML    = '<code>' + d.fw + '</code>';
                if (latestFw) latestFw.innerHTML = d.latest_fw
                    ? '<code class="' + (d.latest_differs ? 'text-warning' : '') + '">' + d.latest_fw + '</code>'
                    : '<span class="text-muted">—</span>';
                if (uptime)   uptime.textContent   = d.uptime;
                if (lastSeen) lastSeen.innerHTML =
                    '<span class="d-sm-none">' + d.last_seen_short + '</span>' +
                    '<span class="d-none d-sm-inline">' + d.last_seen + '</span>';
            });

            recountStats();

            // Indikátor poslední aktualizace
            const ind  = document.getElementById('dash-updated');
            const time = document.getElementById('dash-updated-time');
            if (ind && time) {
                time.textContent = new Date().toLocaleTimeString();
                ind.classList.remove('d-none');
            }
        } catch {}
    }

    setInterval(poll, INTERVAL);

    // Klik na řádek → detail zařízení
    document.querySelectorAll('tr[data-device-key]').forEach(row => {
        row.addEventListener('click', function () {
            location.href = 'index.php?page=devices&key=' + encodeURIComponent(this.dataset.deviceKey) + '&from=dashboard';
        });
    });

    // Filtry skupiny / typu
    const dashSelGroup = document.getElementById('dashFilterGroup');
    const dashSelType  = document.getElementById('dashFilterType');
    function applyDashFilter() {
        const g = dashSelGroup ? dashSelGroup.value : '';
        const t = dashSelType  ? dashSelType.value  : '';
        document.querySelectorAll('tr[data-device-key]').forEach(row => {
            row.style.display = (!g || row.dataset.group === g) && (!t || row.dataset.type === t) ? '' : 'none';
        });
        recountStats();
    }
    if (dashSelGroup) dashSelGroup.addEventListener('change', applyDashFilter);
    if (dashSelType)  dashSelType.addEventListener('change', applyDashFilter);
})();
</script>
JS;

include dirname(__FILE__) . '/footer.php';
?>
