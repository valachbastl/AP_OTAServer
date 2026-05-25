<?php
requireLogin();
$activePage = 'firmware';

$groups = getGroups();

$selGroup = $_GET['g']  ?? '';
$selType  = $_GET['t']  ?? '';
$selComp  = $_GET['c']  ?? '';
$selHw    = $_GET['hw'] ?? '';
$selTab   = in_array($_GET['tab'] ?? '', ['fw','docs']) ? $_GET['tab'] : 'fw';
$pageTitle = $selTab === 'docs' ? __('fw_tab_docs') : __('fw_title');

$types  = $selGroup ? getDeviceTypes($selGroup) : [];
$comps  = ($selGroup && $selType) ? getComponents($selGroup, $selType) : [];
$hws    = ($selGroup && $selType && $selComp) ? getHwVersions($selGroup, $selType, $selComp) : [];
$fwList = ($selGroup && $selType && $selComp && $selHw) ? getFirmwareList($selGroup, $selType, $selComp, $selHw) : [];

$nextVersion = '';
if (!empty($fwList)) {
    $existing  = array_column($fwList, 'fw_version');
    $yy        = date('y');
    $m         = (int)date('n');
    $prefix    = $yy . '.' . $m . '.';
    $patches   = array_filter(array_map(fn($v) => str_starts_with($v, $prefix) ? (int)substr($v, strlen($prefix)) : -1, $existing), fn($n) => $n >= 0);
    $nextVersion = $prefix . ($patches ? max($patches) + 1 : 0);
} else {
    $nextVersion = date('y') . '.' . (int)date('n') . '.0';
}

$tp = '&tab=' . urlencode($selTab);

$hwBaseUrl = '?page=firmware'
    . ($selGroup ? '&g=' . urlencode($selGroup) : '')
    . ($selType  ? '&t=' . urlencode($selType)  : '')
    . ($selComp  ? '&c=' . urlencode($selComp)  : '')
    . ($selHw    ? '&hw=' . urlencode($selHw)   : '');

include dirname(__FILE__) . '/header.php';
?>

<meta name="csrf-token" content="<?= e(csrfToken()) ?>">

<div class="row g-4">

    <!-- ── Levý panel: hierarchie ────────────────────────────────────── -->
    <div class="col-12 col-lg-4 col-xl-3">

        <!-- Skupiny -->
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-folder2 me-1"></i><?= e(__('fw_groups')) ?></span>
                <?php if (isAdmin()): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddGroup">
                    <i class="bi bi-plus"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($groups)): ?>
                    <div class="list-group-item text-muted small py-3 text-center"><?= e(__('fw_no_groups')) ?></div>
                <?php else: ?>
                <?php foreach ($groups as $g): ?>
                    <a href="?page=firmware&g=<?= urlencode($g['slug']) ?><?= $tp ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between
                              <?= $selGroup === $g['slug'] ? 'active' : '' ?>">
                        <span><i class="bi bi-folder me-2"></i><?= e($g['name']) ?></span>
                        <?php if (isAdmin()): ?>
                        <div class="d-flex gap-1 flex-shrink-0 ms-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-rename-action="rename_group"
                                    data-rename-name="<?= e($g['name']) ?>"
                                    data-rename-params='{"slug":"<?= e($g['slug']) ?>"}'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    data-confirm-delete="<?= e(sprintf(__('confirm_delete_group'), $g['name'])) ?>"
                                    data-action="delete_group" data-params='{"slug":"<?= e($g['slug']) ?>"}'>
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Typy zařízení -->
        <?php if ($selGroup): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-cpu me-1"></i><?= e(__('fw_device_types')) ?></span>
                <?php if (isAdmin()): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddType">
                    <i class="bi bi-plus"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($types)): ?>
                    <div class="list-group-item text-muted small py-3 text-center"><?= e(__('fw_no_types')) ?></div>
                <?php else: ?>
                <?php foreach ($types as $t): ?>
                    <a href="?page=firmware&g=<?= urlencode($selGroup) ?>&t=<?= urlencode($t['slug']) ?><?= $tp ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between
                              <?= $selType === $t['slug'] ? 'active' : '' ?>">
                        <span><?= e($t['name']) ?></span>
                        <?php if (isAdmin()): ?>
                        <div class="d-flex gap-1 flex-shrink-0 ms-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-rename-action="rename_type"
                                    data-rename-name="<?= e($t['name']) ?>"
                                    data-rename-params='{"group_slug":"<?= e($selGroup) ?>","slug":"<?= e($t['slug']) ?>"}'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    data-confirm-delete="<?= e(sprintf(__('confirm_delete_type'), $t['name'])) ?>"
                                    data-action="delete_type"
                                    data-params='{"group_slug":"<?= e($selGroup) ?>","slug":"<?= e($t['slug']) ?>"}'>
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Komponenty -->
        <?php if ($selType): ?>
        <div class="card shadow-sm mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-puzzle me-1"></i><?= e(__('fw_components')) ?></span>
                <?php if (isAdmin()): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddComp">
                    <i class="bi bi-plus"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($comps)): ?>
                    <div class="list-group-item text-muted small py-3 text-center"><?= e(__('fw_no_components')) ?></div>
                <?php else: ?>
                <?php foreach ($comps as $c): ?>
                    <a href="?page=firmware&g=<?= urlencode($selGroup) ?>&t=<?= urlencode($selType) ?>&c=<?= urlencode($c['slug']) ?><?= $tp ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between
                              <?= $selComp === $c['slug'] ? 'active' : '' ?>">
                        <span><?= e($c['name']) ?></span>
                        <?php if (isAdmin()): ?>
                        <div class="d-flex gap-1 flex-shrink-0 ms-2">
                            <button class="btn btn-sm btn-outline-secondary"
                                    data-rename-action="rename_comp"
                                    data-rename-name="<?= e($c['name']) ?>"
                                    data-rename-params='{"group_slug":"<?= e($selGroup) ?>","type_slug":"<?= e($selType) ?>","slug":"<?= e($c['slug']) ?>"}'>
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-sm btn-outline-danger"
                                    data-confirm-delete="<?= e(sprintf(__('confirm_delete_comp'), $c['name'])) ?>"
                                    data-action="delete_component"
                                    data-params='{"group_slug":"<?= e($selGroup) ?>","type_slug":"<?= e($selType) ?>","slug":"<?= e($c['slug']) ?>"}'>
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- HW verze -->
        <?php if ($selComp): ?>
        <div class="card shadow-sm">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span class="fw-semibold small"><i class="bi bi-layers me-1"></i><?= e(__('fw_hw_versions')) ?></span>
                <?php if (isAdmin()): ?>
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#modalAddHw">
                    <i class="bi bi-plus"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="list-group list-group-flush">
                <?php if (empty($hws)): ?>
                    <div class="list-group-item text-muted small py-3 text-center"><?= e(__('fw_no_hw')) ?></div>
                <?php else: ?>
                <?php foreach ($hws as $hw): ?>
                    <a href="?page=firmware&g=<?= urlencode($selGroup) ?>&t=<?= urlencode($selType) ?>&c=<?= urlencode($selComp) ?>&hw=<?= urlencode($hw['slug']) ?><?= $tp ?>"
                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between
                              <?= $selHw === $hw['slug'] ? 'active' : '' ?>">
                        <span><?= e($hw['slug']) ?></span>
                        <?php if (isAdmin()): ?>
                        <button class="btn btn-sm btn-outline-danger ms-2 flex-shrink-0"
                                data-confirm-delete="<?= e(sprintf(__('confirm_delete_hw'), $hw['slug'])) ?>"
                                data-action="delete_hw"
                                data-params='{"group_slug":"<?= e($selGroup) ?>","type_slug":"<?= e($selType) ?>","comp_slug":"<?= e($selComp) ?>","slug":"<?= e($hw['slug']) ?>"}'>
                            <i class="bi bi-trash3"></i>
                        </button>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- ── Pravý panel ───────────────────────────────────────────────── -->
    <div class="col-12 col-lg-8 col-xl-9">

        <?php if (!$selHw): ?>
            <div class="card shadow-sm">
                <div class="card-body text-center text-muted py-5">
                    <i class="bi bi-arrow-left-circle display-6 d-block mb-2 opacity-25"></i>
                    <?= e(__('fw_select_hint')) ?>
                </div>
            </div>
        <?php else: ?>

            <!-- Tabs -->
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item">
                    <a class="nav-link <?= $selTab === 'fw' ? 'active' : '' ?>"
                       href="<?= $hwBaseUrl ?>&tab=fw">
                        <i class="bi bi-file-earmark-binary me-1"></i><?= e(__('fw_tab_firmware')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $selTab === 'docs' ? 'active' : '' ?>"
                       href="<?= $hwBaseUrl ?>&tab=docs">
                        <i class="bi bi-folder2-open me-1"></i><?= e(__('fw_tab_docs')) ?>
                    </a>
                </li>
            </ul>

            <?php if ($selTab === 'fw'): ?>

                <!-- ── Firmware tab ─────────────────────────────────── -->

                <?php if (isAdmin()): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-cloud-upload me-1"></i><?= e(__('fw_upload_title')) ?>
                        <small class="text-muted fw-normal ms-2">
                            <?= e($selGroup) ?> / <?= e($selType) ?> / <?= e($selComp) ?> / <code><?= e($selHw) ?></code>
                        </small>
                    </div>
                    <div class="card-body">
                        <form id="uploadForm" enctype="multipart/form-data">
                            <input type="hidden" name="group"     value="<?= e($selGroup) ?>">
                            <input type="hidden" name="type"      value="<?= e($selType) ?>">
                            <input type="hidden" name="component" value="<?= e($selComp) ?>">
                            <input type="hidden" name="hw"        value="<?= e($selHw) ?>">

                            <div class="upload-zone mb-3" id="uploadZone">
                                <input type="file" id="fwFile" name="fw_file" accept=".bin">
                                <i class="bi bi-file-earmark-binary display-6 text-muted opacity-50 d-block mb-2"></i>
                                <div class="fw-semibold"><?= e(__('fw_dropzone_main')) ?></div>
                                <div class="text-muted small"><?= e(__('fw_dropzone_sub')) ?></div>
                                <div id="uploadFileName" class="mt-2 fw-semibold text-primary d-none"></div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label for="fwVersion" class="form-label small fw-semibold text-muted"><?= e(__('fw_version_label')) ?></label>
                                    <input type="text" id="fwVersion" name="fw_version"
                                           class="form-control fw-mono"
                                           placeholder="<?= e($nextVersion) ?>"
                                           pattern="^\d{2}\.\d{1,2}\.\d+$"
                                           value="<?= e($nextVersion) ?>" required>
                                    <div class="form-text"><?= __('fw_version_format', $nextVersion) ?></div>
                                </div>
                                <div class="col-sm-6">
                                    <label for="fwNotes" class="form-label small fw-semibold text-muted"><?= e(__('label_notes')) ?></label>
                                    <input type="text" id="fwNotes" name="notes" class="form-control" placeholder="<?= e(__('fw_notes_ph')) ?>">
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-upload me-1"></i><?= e(__('fw_btn_upload')) ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-list-ul me-1"></i><?= e(__('fw_history')) ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($fwList)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-inbox d-block mb-1 opacity-25 fs-2"></i>
                                <?= e(__('fw_no_firmware')) ?>
                            </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><?= e(__('label_version')) ?></th>
                                        <th><?= e(__('label_size')) ?></th>
                                        <th class="d-none d-md-table-cell"><?= e(__('label_notes')) ?></th>
                                        <th class="d-none d-sm-table-cell"><?= e(__('label_uploaded')) ?></th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($fwList as $i => $fw): ?>
                                    <tr>
                                        <td>
                                            <code class="fw-bold"><?= e($fw['fw_version']) ?></code>
                                            <?php if ($i === 0): ?>
                                                <span class="badge bg-success ms-1"><?= e(__('badge_latest')) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?= e(formatBytes($fw['file_size'])) ?></td>
                                        <td class="small text-muted d-none d-md-table-cell"><?= e($fw['notes'] ?? '—') ?></td>
                                        <td class="small text-muted text-nowrap d-none d-sm-table-cell"><?= e(date('d.m.Y H:i', strtotime($fw['uploaded_at']))) ?></td>
                                        <td class="text-end">
                                            <div class="d-flex gap-1 justify-content-end">
                                                <a href="./ajax/download_firmware.php?id=<?= $fw['id'] ?>"
                                                   class="btn btn-sm btn-outline-secondary" download>
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <?php if (isAdmin()): ?>
                                                <button class="btn btn-sm btn-outline-danger"
                                                        data-confirm-delete="<?= e(sprintf(__('confirm_delete_fw'), $fw['fw_version'])) ?>"
                                                        data-action="delete_firmware"
                                                        data-params='{"id":<?= $fw['id'] ?>}'>
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            <?php else: ?>

                <!-- ── Dokumentace tab ──────────────────────────────── -->

                <?php if (isAdmin()): ?>
                <div class="card shadow-sm mb-4">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-cloud-upload me-1"></i><?= e(__('doc_upload_title')) ?>
                        <small class="text-muted fw-normal ms-2">
                            <?= e($selGroup) ?> / <?= e($selType) ?> / <?= e($selComp) ?> / <code><?= e($selHw) ?></code>
                        </small>
                    </div>
                    <div class="card-body">
                        <form id="uploadDocForm" enctype="multipart/form-data">
                            <input type="hidden" name="entity_type" value="hw_version">
                            <input type="hidden" name="group" value="<?= e($selGroup) ?>">
                            <input type="hidden" name="type"  value="<?= e($selType) ?>">
                            <input type="hidden" name="comp"  value="<?= e($selComp) ?>">
                            <input type="hidden" name="hw"    value="<?= e($selHw) ?>">

                            <div class="upload-zone mb-3" id="docUploadZone">
                                <input type="file" id="docFile" name="doc_file"
                                       accept=".pdf,.png,.jpg,.jpeg,.zip,.sch,.kicad_sch,.kicad_pcb,.kicad_pro,.kicad_mod">
                                <i class="bi bi-folder2-open display-6 text-muted opacity-50 d-block mb-2"></i>
                                <div class="fw-semibold"><?= e(__('doc_dropzone_main')) ?></div>
                                <div class="text-muted small"><?= e(__('doc_dropzone_sub')) ?></div>
                                <div id="docUploadFileName" class="mt-2 fw-semibold text-primary d-none"></div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted"><?= e(__('label_category')) ?></label>
                                    <select id="docCategory" name="category" class="form-select">
                                        <option value="schema"><?= e(__('doc_cat_schema')) ?></option>
                                        <option value="pcb"><?= e(__('doc_cat_pcb')) ?></option>
                                        <option value="platformio"><?= e(__('doc_cat_platformio')) ?></option>
                                        <option value="photo"><?= e(__('doc_cat_photo')) ?></option>
                                        <option value="other"><?= e(__('doc_cat_other')) ?></option>
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="form-label small fw-semibold text-muted"><?= e(__('label_notes')) ?></label>
                                    <input type="text" id="docNotes" name="notes" class="form-control" placeholder="<?= e(__('label_notes_ph')) ?>">
                                </div>
                            </div>
                            <div class="text-center">
                                <button type="submit" class="btn btn-primary" id="btnUploadDoc">
                                    <i class="bi bi-upload me-1"></i><?= e(__('doc_upload_title')) ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <?php $hwDocs = getDocumentsForHw($selGroup, $selType, $selComp, $selHw); ?>
                <div class="card shadow-sm">
                    <div class="card-header fw-semibold">
                        <i class="bi bi-folder2-open me-1"></i><?= e(__('doc_list_title')) ?>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($hwDocs)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="bi bi-folder2 d-block mb-1 opacity-25 fs-2"></i>
                                <?= e(__('doc_no_docs')) ?>
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
                                        <th class="d-none d-lg-table-cell"><?= e(__('label_uploaded')) ?></th>
                                        <?php if (isAdmin()): ?><th></th><?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($hwDocs as $doc): ?>
                                    <tr>
                                        <td>
                                            <a href="./ajax/download_doc.php?id=<?= $doc['id'] ?>" class="text-decoration-none">
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
                                        <td class="small text-muted d-none d-lg-table-cell"><?= e(date('d.m.Y H:i', strtotime($doc['uploaded_at']))) ?></td>
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

            <?php endif; ?>

        <?php endif; ?>
    </div>
</div>

<!-- ── Modály pro přidání ─────────────────────────────────────────────── -->
<?php if (isAdmin()): ?>

<div class="modal fade" id="modalAddGroup" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-folder-plus me-2"></i><?= e(__('modal_new_group')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddGroup">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_name')) ?></label>
                        <input type="text" name="name" class="form-control" placeholder="<?= e(__('modal_group_ph')) ?>" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_slug')) ?></label>
                        <input type="text" name="slug" class="form-control fw-mono" placeholder="home" pattern="[a-z0-9\-]+" required>
                        <div class="form-text"><?= e(__('modal_slug_hint')) ?></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i><?= e(__('modal_btn_add')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddType" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-cpu me-2"></i><?= e(__('modal_new_type')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddType">
                <input type="hidden" name="group_slug" value="<?= e($selGroup) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_name')) ?></label>
                        <input type="text" name="name" class="form-control" placeholder="<?= e(__('modal_type_ph')) ?>" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_slug')) ?></label>
                        <input type="text" name="slug" class="form-control fw-mono" placeholder="control-unit" pattern="[a-z0-9\-]+" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i><?= e(__('modal_btn_add')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddComp" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-puzzle me-2"></i><?= e(__('modal_new_comp')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddComp">
                <input type="hidden" name="group_slug" value="<?= e($selGroup) ?>">
                <input type="hidden" name="type_slug"  value="<?= e($selType) ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_name')) ?></label>
                        <input type="text" name="name" class="form-control" placeholder="<?= e(__('modal_comp_ph')) ?>" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_slug')) ?></label>
                        <input type="text" name="slug" class="form-control fw-mono" placeholder="board" pattern="[a-z0-9\-]+" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i><?= e(__('modal_btn_add')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddHw" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-layers me-2"></i><?= e(__('modal_new_hw')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddHw">
                <input type="hidden" name="group_slug" value="<?= e($selGroup) ?>">
                <input type="hidden" name="type_slug"  value="<?= e($selType) ?>">
                <input type="hidden" name="comp_slug"  value="<?= e($selComp) ?>">
                <div class="modal-body">
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('modal_slug_hw')) ?></label>
                        <input type="text" name="slug" class="form-control fw-mono" placeholder="1.0" pattern="[a-z0-9\-\.]+" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i><?= e(__('modal_btn_add')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalRename" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><?= e(__('modal_rename_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formRename">
                <div class="modal-body">
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_name')) ?></label>
                        <input type="text" id="renameInput" name="name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i><?= e(__('modal_rename_btn')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php endif; ?>

<?php
$extraJs = <<<'JS'
<script>
// ── Dokument upload (drag-drop) ───────────────────────────────────────────────
(function () {
    const zone      = document.getElementById('docUploadZone');
    const fileInput = document.getElementById('docFile');
    const form      = document.getElementById('uploadDocForm');
    if (!zone) return;

    let pendingFile = null;

    zone.addEventListener('click', () => fileInput.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files[0]) { pendingFile = e.dataTransfer.files[0]; handleDocFile(pendingFile); }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) { pendingFile = fileInput.files[0]; handleDocFile(pendingFile); }
    });

    function handleDocFile(file) {
        document.getElementById('docUploadFileName').textContent = file.name + ' (' + fmtBytes(file.size) + ')';
        document.getElementById('docUploadFileName').classList.remove('d-none');
    }

    form && form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn  = document.getElementById('btnUploadDoc');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + Lang.uploading;
        const fd = new FormData(form);
        if (pendingFile) fd.set('doc_file', pendingFile);
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) fd.set('csrf_token', csrf.content);
        try {
            const res  = await fetch('./ajax/upload_doc.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) { showToast(Lang.docUploaded); setTimeout(() => location.reload(), 1000); }
            else showToast(data.error || Lang.uploadError, 'danger');
        } catch { showToast(Lang.networkError, 'danger'); }
        finally   { btn.disabled = false; btn.innerHTML = orig; }
    });
})();

// ── Automaticky slug z názvu ──────────────────────────────────────────────────
['Group','Type','Comp','Hw'].forEach(id => {
    const form = document.getElementById('formAdd' + id);
    if (!form) return;
    const nameIn = form.querySelector('[name="name"]');
    const slugIn = form.querySelector('[name="slug"]');
    if (nameIn && slugIn) {
        nameIn.addEventListener('input', () => {
            if (!slugIn.dataset.manual)
                slugIn.value = nameIn.value.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-|-$/g,'');
        });
        slugIn.addEventListener('input', () => slugIn.dataset.manual = '1');
    }
    form.addEventListener('submit', async e => {
        e.preventDefault();
        const data = Object.fromEntries(new FormData(form));
        const res  = await apiPost('./ajax/api.php', { action: 'add_' + id.toLowerCase(), ...data });
        if (res.ok) { showToast(Lang.added); setTimeout(() => location.reload(), 800); }
        else showToast(res.error || Lang.error, 'danger');
    });
});

// ── Přejmenování skupiny / typu / komponenty ─────────────────────────────────
(function () {
    const modal  = document.getElementById('modalRename');
    if (!modal) return;
    const form   = document.getElementById('formRename');
    const nameIn = document.getElementById('renameInput');
    let pending  = {};

    document.querySelectorAll('[data-rename-action]').forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            pending.action = this.dataset.renameAction;
            pending.params = JSON.parse(this.dataset.renameParams || '{}');
            nameIn.value = this.dataset.renameName || '';
            modal.addEventListener('shown.bs.modal', () => { nameIn.focus(); nameIn.select(); }, { once: true });
            bootstrap.Modal.getOrCreateInstance(modal).show();
        });
    });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        const name = nameIn.value.trim();
        if (!name) return;
        const res = await apiPost('./ajax/api.php', { action: pending.action, name, ...pending.params });
        if (res.ok) {
            bootstrap.Modal.getInstance(modal).hide();
            showToast(Lang.renamed);
            setTimeout(() => location.reload(), 800);
        } else showToast(res.error || Lang.error, 'danger');
    });
})();

function fmtBytes(b) {
    if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
    if (b >= 1024)    return (b / 1024).toFixed(1) + ' KB';
    return b + ' B';
}
</script>
JS;

include dirname(__FILE__) . '/footer.php';
?>
