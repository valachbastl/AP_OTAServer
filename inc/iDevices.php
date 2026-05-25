<?php
requireLogin();
$pageTitle  = __('dev_title');
$activePage = 'devices';

$deviceKey = $_GET['key'] ?? '';
$device    = $deviceKey ? getDevice($deviceKey) : null;
$events    = $device ? getDeviceEvents($deviceKey, 100) : [];
$devices   = getAllDevices();

include dirname(__FILE__) . '/header.php';
?>

<meta name="csrf-token" content="<?= e(csrfToken()) ?>">

<?php if ($device): ?>

    <!-- ── Detail zařízení ────────────────────────────────────────────── -->
    <div class="mb-3">
        <?php $backUrl = ($_GET['from'] ?? '') === 'dashboard' ? 'index.php?page=dashboard' : 'index.php?page=devices'; ?>
        <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i><?= e(__('dev_back')) ?>
        </a>
    </div>

    <?php
    $status = deviceStatus($device);
    $latest = getLatestFirmware($device['group_slug'], $device['type_slug'], $device['component_slug'], $device['hw_version']);
    ?>

    <div class="row g-4">
        <div class="col-12 col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><?= e(__('dev_info')) ?></span>
                    <?= deviceStatusBadge($status) ?>
                </div>
                <div class="card-body">
                    <table class="table table-sm table-borderless mb-0 small">
                        <tr>
                            <th class="text-muted w-40"><?= e(__('label_mac')) ?></th>
                            <td><code><?= e(formatMac($device['device_key'])) ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_label')) ?></th>
                            <td id="labelCell" data-label-cell data-label-value="<?= e($device['label'] ?? '') ?>">
                                <span class="label-text"><?= e($device['label'] ?: '—') ?></span>
                                <?php if (isAdmin()): ?>
                                <button class="btn btn-link btn-sm p-0 ms-1 text-muted" data-edit-label="<?= e($deviceKey) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_group')) ?></th>
                            <td><?= e($device['group_slug']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_type')) ?></th>
                            <td><?= e($device['type_slug']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_component')) ?></th>
                            <td><?= e($device['component_slug']) ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_hw_version')) ?></th>
                            <td><code><?= e($device['hw_version']) ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_fw_current')) ?></th>
                            <td><code><?= e($device['fw_version'] ?? '—') ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_fw_latest')) ?></th>
                            <td><code><?= $latest ? e($latest['fw_version']) : '—' ?></code></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_uptime')) ?></th>
                            <td><?= $device['uptime'] ? e(formatUptime($device['uptime'])) : '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_last_seen')) ?></th>
                            <td><?= $device['last_seen'] ? e(date('d.m.Y H:i:s', strtotime($device['last_seen']))) : '—' ?></td>
                        </tr>
                        <tr>
                            <th class="text-muted"><?= e(__('label_registered')) ?></th>
                            <td><?= e(date('d.m.Y', strtotime($device['created_at']))) ?></td>
                        </tr>
                    </table>
                </div>
            </div>

            <?php if (isAdmin()): ?>
            <div class="card shadow-sm mb-3">
                <div class="card-header fw-semibold small"><?= e(__('dev_monitoring')) ?></div>
                <div class="card-body">
                    <form id="formDeviceSettings">
                        <input type="hidden" name="action" value="update_device_settings">
                        <input type="hidden" name="device_key" value="<?= e($deviceKey) ?>">
                        <div class="mb-2">
                            <label class="form-label small fw-semibold text-muted"><?= e(__('dev_interval')) ?></label>
                            <input type="number" name="interval" class="form-control form-control-sm"
                                   value="<?= (int)$device['check_interval'] ?>" min="30" max="86400">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-semibold text-muted"><?= e(__('dev_tolerance')) ?></label>
                            <input type="number" name="tolerance" class="form-control form-control-sm"
                                   value="<?= (int)$device['tolerance'] ?>" min="10" max="3600">
                        </div>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="monitoring_disabled" id="chkMonDisabled"
                                       <?= (int)($device['monitoring_disabled'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="chkMonDisabled">
                                    <?= e(__('dev_monitoring_disabled')) ?>
                                </label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-sm btn-primary w-100"><?= e(__('btn_save')) ?></button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-danger border-opacity-25">
                <div class="card-header fw-semibold small text-danger"><?= e(__('dev_danger_zone')) ?></div>
                <div class="card-body">
                    <button class="btn btn-sm btn-outline-danger w-100" id="btnDeleteDevice"
                            data-confirm="<?= e(sprintf(__('confirm_delete_device'), $device['label'] ?: $deviceKey)) ?>"
                            data-device-key="<?= e($deviceKey) ?>">
                        <i class="bi bi-trash3 me-1"></i><?= e(__('dev_delete')) ?>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pravý sloupec: dokumentace + log událostí -->
        <div class="col-12 col-lg-8">

            <!-- Dokumentace zařízení -->
            <?php $deviceDocs = getDocumentsForDevice($deviceKey); ?>
            <div class="card shadow-sm mb-4">
                <div class="card-header fw-semibold d-flex align-items-center justify-content-between">
                    <span><i class="bi bi-folder2-open me-1"></i><?= e(__('dev_install_docs')) ?></span>
                    <?php if (isAdmin()): ?>
                    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalUploadDevDoc">
                        <i class="bi bi-upload me-1"></i><?= e(__('btn_upload')) ?>
                    </button>
                    <?php endif; ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($deviceDocs)): ?>
                        <div class="text-center text-muted py-3 small">
                            <i class="bi bi-folder2 d-block mb-1 opacity-25 fs-3"></i>
                            <?= e(__('dev_no_install_docs')) ?>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th><?= e(__('label_file')) ?></th>
                                    <th><?= e(__('label_category')) ?></th>
                                    <th class="d-none d-sm-table-cell"><?= e(__('label_size')) ?></th>
                                    <th class="d-none d-md-table-cell"><?= e(__('label_notes')) ?></th>
                                    <?php if (isAdmin()): ?><th></th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deviceDocs as $doc): ?>
                                <tr>
                                    <td>
                                        <a href="./ajax/download_doc.php?id=<?= $doc['id'] ?>"
                                           class="text-decoration-none">
                                            <i class="bi <?= e(docCategoryIcon($doc['category'])) ?> me-1"></i><?= e($doc['original_name']) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary">
                                            <?= e(docCategoryLabel($doc['category'])) ?>
                                        </span>
                                    </td>
                                    <td class="small text-muted d-none d-sm-table-cell"><?= e(formatBytes($doc['file_size'])) ?></td>
                                    <td class="small text-muted d-none d-md-table-cell"><?= e($doc['notes'] ?? '—') ?></td>
                                    <?php if (isAdmin()): ?>
                                    <td class="text-end">
                                        <button class="btn btn-sm btn-outline-danger"
                                                data-confirm-delete="<?= e(sprintf(__('confirm_delete_doc'), $doc['original_name'])) ?>"
                                                data-action="delete_document"
                                                data-params='{"id":<?= $doc['id'] ?>}'>
                                            <i class="bi bi-trash3"></i>
                                        </button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Log událostí -->
            <div class="card shadow-sm">
                <div class="card-header fw-semibold">
                    <i class="bi bi-journal-text me-1"></i><?= e(__('dev_events')) ?>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($events)): ?>
                        <div class="text-center text-muted py-4 small"><?= e(__('dev_no_events')) ?></div>
                    <?php else: ?>
                    <div class="table-responsive" style="max-height:600px;overflow-y:auto">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th><?= e(__('label_time')) ?></th>
                                    <th><?= e(__('label_event')) ?></th>
                                    <th class="d-none d-sm-table-cell"><?= e(__('label_fw_version_col')) ?></th>
                                    <th class="d-none d-md-table-cell"><?= e(__('label_ip')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($events as $ev): ?>
                                <tr>
                                    <td class="small text-muted text-nowrap">
                                        <?= e(date('d.m.Y H:i:s', strtotime($ev['created_at']))) ?>
                                    </td>
                                    <td>
                                        <?php
                                        $badge = match($ev['event_type']) {
                                            'checkin'    => 'bg-secondary',
                                            'download'   => 'bg-warning text-dark',
                                            'fw_changed' => 'bg-success',
                                            default      => 'bg-secondary',
                                        };
                                        $label = match($ev['event_type']) {
                                            'checkin'    => __('event_checkin'),
                                            'download'   => __('event_download'),
                                            'fw_changed' => __('event_fw_changed'),
                                            default      => $ev['event_type'],
                                        };
                                        ?>
                                        <span class="badge <?= $badge ?>"><?= e($label) ?></span>
                                    </td>
                                    <td class="d-none d-sm-table-cell"><code><?= e($ev['fw_version'] ?? '—') ?></code></td>
                                    <td class="small text-muted d-none d-md-table-cell"><?= e($ev['ip_address'] ?? '—') ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php else: ?>

    <!-- ── Seznam zařízení ────────────────────────────────────────────── -->
    <?php
    $groups = array_values(array_unique(array_column($devices, 'group_slug')));
    $types  = array_values(array_unique(array_column($devices, 'type_slug')));
    sort($groups); sort($types);
    ?>
    <div class="card shadow-sm">
        <div class="card-header d-flex align-items-center justify-content-between">
            <h5 class="mb-0"><i class="bi bi-hdd-network me-2"></i><?= e(__('dev_all')) ?></h5>
            <span class="badge bg-secondary"><?= count($devices) ?></span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($devices)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-hdd-network display-6 d-block mb-2 opacity-25"></i>
                    <?= e(__('dev_no_devices')) ?><br>
                    <small><?= e(__('dev_no_devices_hint')) ?></small>
                </div>
            <?php else: ?>
            <?php if (count($groups) > 1 || count($types) > 1): ?>
            <div class="px-3 py-2 border-bottom d-flex flex-wrap gap-2 align-items-center">
                <?php if (count($groups) > 1): ?>
                <select id="filterGroup" class="form-select form-select-sm" style="width:auto">
                    <option value=""><?= e(__('filter_all_groups')) ?></option>
                    <?php foreach ($groups as $g): ?>
                    <option value="<?= e($g) ?>"><?= e($g) ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if (count($types) > 1): ?>
                <select id="filterType" class="form-select form-select-sm" style="width:auto">
                    <option value=""><?= e(__('filter_all_types')) ?></option>
                    <?php foreach ($types as $t): ?>
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
                            <th><?= e(__('dev_col_name')) ?></th>
                            <th class="d-none d-sm-table-cell"><?= e(__('dev_col_fw')) ?></th>
                            <th class="d-none d-md-table-cell"><?= e(__('label_mac')) ?></th>
                            <th class="d-none d-md-table-cell"><?= e(__('dev_col_last_seen')) ?></th>
                            <th class="d-none d-lg-table-cell"><?= e(__('dash_col_group_type_comp')) ?></th>
                            <th class="d-none d-xl-table-cell"><?= e(__('label_hw_version')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($devices as $d):
                            $s = deviceStatus($d);
                        ?>
                        <tr data-device-key="<?= e($d['device_key']) ?>" data-group="<?= e($d['group_slug']) ?>" data-type="<?= e($d['type_slug']) ?>">
                            <td><?= deviceStatusBadge($s) ?></td>
                            <td data-label-cell data-label-value="<?= e($d['label'] ?? '') ?>">
                                <span class="label-text"><?= e($d['label'] ?: '—') ?></span>
                                <?php if (isAdmin()): ?>
                                <button class="btn btn-link btn-sm p-0 ms-1 text-muted" data-edit-label="<?= e($d['device_key']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                            <td class="d-none d-sm-table-cell"><code><?= e($d['fw_version'] ?? '—') ?></code></td>
                            <td class="d-none d-md-table-cell"><code><?= e(formatMac($d['device_key'])) ?></code></td>
                            <td class="small text-muted d-none d-md-table-cell">
                                <?= $d['last_seen'] ? e(date('d.m.Y H:i', strtotime($d['last_seen']))) : '—' ?>
                            </td>
                            <td class="small d-none d-lg-table-cell">
                                <span class="badge bg-secondary bg-opacity-10 text-secondary"><?= e($d['group_slug']) ?></span>
                                <?= e($d['type_slug']) ?> / <?= e($d['component_slug']) ?>
                            </td>
                            <td class="d-none d-xl-table-cell"><code><?= e($d['hw_version']) ?></code></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
// Klik na řádek → detail zařízení
document.querySelectorAll('tbody tr[data-device-key]').forEach(row => {
    row.addEventListener('click', function (e) {
        if (e.target.closest('button')) return;
        location.href = 'index.php?page=devices&key=' + encodeURIComponent(this.dataset.deviceKey);
    });
});

// Filtry skupiny / typu v seznamu zařízení
(function () {
    const selGroup = document.getElementById('filterGroup');
    const selType  = document.getElementById('filterType');
    if (!selGroup && !selType) return;
    function applyFilter() {
        const g = selGroup ? selGroup.value : '';
        const t = selType  ? selType.value  : '';
        document.querySelectorAll('tbody tr[data-group]').forEach(row => {
            row.style.display = (!g || row.dataset.group === g) && (!t || row.dataset.type === t) ? '' : 'none';
        });
    }
    if (selGroup) selGroup.addEventListener('change', applyFilter);
    if (selType)  selType.addEventListener('change', applyFilter);
})();

// Upload dokumentu zařízení
(function () {
    const form = document.getElementById('formUploadDevDoc');
    if (!form) return;
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const btn  = document.getElementById('btnUploadDevDoc');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + Lang.uploading;
        const fd   = new FormData(form);
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) fd.set('csrf_token', csrf.content);
        try {
            const res  = await fetch('./ajax/upload_doc.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                showToast(Lang.docUploaded);
                bootstrap.Modal.getInstance(document.getElementById('modalUploadDevDoc')).hide();
                setTimeout(() => location.reload(), 1000);
            } else {
                showToast(data.error || Lang.uploadError, 'danger');
            }
        } catch { showToast(Lang.networkError, 'danger'); }
        finally   { btn.disabled = false; btn.innerHTML = orig; }
    });
})();

const settingsForm = document.getElementById('formDeviceSettings');
if (settingsForm) {
    settingsForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(settingsForm));
        const res  = await apiPost('./ajax/api.php', data);
        if (res.ok) showToast(Lang.settingsSaved);
        else showToast(res.error || Lang.error, 'danger');
    });
}

const btnDelete = document.getElementById('btnDeleteDevice');
if (btnDelete) {
    btnDelete.addEventListener('click', async function () {
        if (!await confirmModal(this.dataset.confirm)) return;
        const res = await apiPost('./ajax/api.php', { action: 'delete_device', device_key: this.dataset.deviceKey });
        if (res.ok) { showToast(Lang.deleted); setTimeout(() => window.location = 'index.php?page=devices', 1000); }
        else showToast(res.error || Lang.error, 'danger');
    });
}
</script>
JS;

if ($device && isAdmin()): ?>
<div class="modal fade" id="modalUploadDevDoc" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-upload me-2"></i><?= e(__('dev_upload_doc')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formUploadDevDoc" enctype="multipart/form-data">
                <input type="hidden" name="entity_type" value="device">
                <input type="hidden" name="device_key"  value="<?= e($deviceKey) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_file')) ?></label>
                        <input type="file" name="doc_file" class="form-control"
                               accept=".pdf,.png,.jpg,.jpeg,.zip,.sch,.kicad_sch,.kicad_pcb,.kicad_pro,.kicad_mod"
                               required>
                        <div class="form-text"><?= e(__('doc_file_hint')) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_category')) ?></label>
                        <select name="category" class="form-select">
                            <option value="photo"><?= e(__('dev_doc_cat_photo')) ?></option>
                            <option value="schema"><?= e(__('doc_cat_schema')) ?></option>
                            <option value="platformio"><?= e(__('doc_cat_platformio')) ?></option>
                            <option value="other"><?= e(__('doc_cat_other')) ?></option>
                        </select>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_notes')) ?></label>
                        <input type="text" name="notes" class="form-control"
                               placeholder="<?= e(__('label_notes_ph')) ?>">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary" id="btnUploadDevDoc">
                        <i class="bi bi-upload me-1"></i><?= e(__('btn_upload')) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php include dirname(__FILE__) . '/footer.php'; ?>
