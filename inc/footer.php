        </main>
        <!-- /ota-content -->

        <footer class="border-top py-2 px-3 text-center text-muted" style="font-size:0.75rem">
            AP OTA Server v<?= APP_VERSION ?> &nbsp;&middot;&nbsp; &copy; <?= $footerYear ?> Petr Adámek
        </footer>

    </div>
    <!-- /ota-main -->

</div>
<!-- /ota-wrapper -->

<!-- Edit label modal -->
<div class="modal fade" id="editLabelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h6 class="modal-title"><i class="bi bi-pencil me-2"></i><?= e(__('modal_device_label')) ?></h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="editLabelInput" class="form-control" placeholder="<?= e(__('modal_device_label')) ?>">
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                <button type="button" class="btn btn-primary btn-sm" id="editLabelOk"><i class="bi bi-check me-1"></i><?= e(__('btn_save')) ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Shared confirm modal -->
<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-body pt-4 pb-2 text-center px-4">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-1 d-block mb-3"></i>
                <p id="confirmModalMsg" class="mb-0 fw-semibold"></p>
            </div>
            <div class="modal-footer justify-content-center border-0 pb-4 gap-2">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                <button type="button" class="btn btn-danger" id="confirmModalOk"><?= e(__('btn_confirm')) ?></button>
            </div>
        </div>
    </div>
</div>

<script src="./js/bootstrap/5.3.8/bootstrap.bundle.min.js"></script>
<script>
window.Lang = <?= json_encode([
    'added'         => __('js_added'),
    'saved'         => __('js_saved'),
    'renamed'       => __('js_renamed'),
    'deleted'       => __('js_deleted'),
    'uploading'     => __('js_uploading'),
    'docUploaded'   => __('js_doc_uploaded'),
    'fwUploaded'    => __('js_fw_uploaded'),
    'uploadError'   => __('js_upload_error'),
    'networkError'  => __('js_network_error'),
    'userAdded'     => __('js_user_added'),
    'totpReset'     => __('js_totp_reset'),
    'settingsSaved' => __('js_settings_saved'),
    'error'         => __('js_error'),
    'mustBeBin'            => __('js_must_be_bin'),
    'reset'                => __('js_reset'),
    'confirmResetOwnTotp'  => __('confirm_reset_own_totp'),
], JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="./js/app.js?v=<?= filemtime(__DIR__ . '/../js/app.js') ?>"></script>
<?php if (!empty($extraJs)): ?>
<?= $extraJs ?>
<?php endif; ?>
</body>
</html>
