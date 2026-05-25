<?php
requireLogin();
$pageTitle  = __('acc_title');
$activePage = '';

$me = currentUser();
include dirname(__FILE__) . '/header.php';
?>

<meta name="csrf-token" content="<?= e(csrfToken()) ?>">

<div class="row justify-content-center">
    <div class="col-12 col-md-6 col-lg-5">

        <div class="card shadow-sm mb-3">
            <div class="card-header fw-semibold"><i class="bi bi-person-circle me-1"></i><?= e(__('acc_info')) ?></div>
            <div class="card-body">
                <table class="table table-sm table-borderless mb-0 small">
                    <tr>
                        <th class="text-muted" style="width:35%"><?= e(__('label_full_name')) ?></th>
                        <td><?= e($me['full_name']) ?></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= e(__('label_username')) ?></th>
                        <td><code><?= e($me['username']) ?></code></td>
                    </tr>
                    <tr>
                        <th class="text-muted"><?= e(__('label_role')) ?></th>
                        <td>
                            <span class="badge <?= $me['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $me['role'] === 'admin' ? e(__('role_admin')) : e(__('role_viewer')) ?>
                            </span>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-warning border-opacity-25">
            <div class="card-header fw-semibold small"><i class="bi bi-shield-lock me-1"></i><?= e(__('acc_totp')) ?></div>
            <div class="card-body">
                <p class="small text-muted mb-3"><?= e(__('acc_totp_desc')) ?></p>
                <button class="btn btn-outline-warning w-100" id="btnResetOwnTotp">
                    <i class="bi bi-arrow-counterclockwise me-1"></i><?= e(__('acc_totp_reset')) ?>
                </button>
            </div>
        </div>

    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
document.getElementById('btnResetOwnTotp').addEventListener('click', async function () {
    if (!await confirmModal(Lang.confirmResetOwnTotp || '?', Lang.reset, 'btn-warning')) return;
    const res = await apiPost('./ajax/api.php', { action: 'reset_own_totp' });
    if (res.ok) {
        window.location = 'index.php';
    } else {
        showToast(res.error || Lang.error, 'danger');
    }
});
</script>
JS;

include dirname(__FILE__) . '/footer.php';
?>
