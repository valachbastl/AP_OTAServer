<?php
requireAdmin();
$pageTitle  = __('usr_title');
$activePage = 'users';

$users = getAllUsers();
include dirname(__FILE__) . '/header.php';
?>

<meta name="csrf-token" content="<?= e(csrfToken()) ?>">

<div class="d-flex align-items-center justify-content-between mb-4">
    <h5 class="mb-0"><i class="bi bi-people me-2"></i><?= e(__('usr_title')) ?></h5>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalAddUser">
        <i class="bi bi-person-plus me-1"></i><?= e(__('usr_add')) ?>
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th><?= e(__('usr_col_user')) ?></th>
                        <th class="d-none d-sm-table-cell"><?= e(__('usr_col_fullname')) ?></th>
                        <th><?= e(__('usr_col_role')) ?></th>
                        <th>
                            <span class="d-sm-none">Auth</span>
                            <span class="d-none d-sm-inline"><?= e(__('usr_col_totp')) ?></span>
                        </th>
                        <th class="d-none d-md-table-cell"><?= e(__('usr_col_created')) ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                    <tr class="user-row"
                        data-user-id="<?= $u['id'] ?>"
                        data-username="<?= e($u['username']) ?>"
                        data-full-name="<?= e($u['full_name']) ?>"
                        data-role="<?= e($u['role']) ?>">
                        <td class="fw-semibold">
                            <?= e($u['username']) ?>
                            <?php if ($u['username'] === currentUser()['username']): ?>
                                <span class="badge bg-primary ms-1"><?= e(__('badge_me')) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="d-none d-sm-table-cell"><?= e($u['full_name']) ?></td>
                        <td>
                            <span class="badge <?= $u['role'] === 'admin' ? 'bg-danger' : 'bg-secondary' ?>">
                                <?= $u['role'] === 'admin' ? e(__('role_admin')) : e(__('role_viewer')) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($u['has_totp']): ?>
                                <span class="badge bg-success">
                                    <i class="bi bi-shield-check"></i>
                                    <span class="d-none d-sm-inline ms-1"><?= e(__('usr_totp_paired')) ?></span>
                                </span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">
                                    <i class="bi bi-shield-exclamation"></i>
                                    <span class="d-none d-sm-inline ms-1"><?= e(__('usr_totp_unpaired')) ?></span>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted d-none d-md-table-cell"><?= e(date('d.m.Y', strtotime($u['created_at']))) ?></td>
                        <td class="text-end">
                            <div class="d-flex gap-1 justify-content-end">
                                <button class="btn btn-sm btn-outline-warning"
                                        title="<?= e(__('acc_totp_reset')) ?>"
                                        data-confirm="<?= e(sprintf(__('confirm_reset_totp'), $u['username'])) ?>"
                                        data-action="reset_totp"
                                        data-params='{"user_id":<?= $u['id'] ?>}'>
                                    <i class="bi bi-arrow-counterclockwise"></i>
                                </button>
                                <?php if ($u['username'] !== 'admin' && $u['username'] !== currentUser()['username']): ?>
                                <button class="btn btn-sm btn-outline-danger"
                                        title="<?= e(__('btn_delete')) ?>"
                                        data-confirm="<?= e(sprintf(__('confirm_delete_user'), $u['username'])) ?>"
                                        data-action="delete_user"
                                        data-params='{"user_id":<?= $u['id'] ?>}'>
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
    </div>
</div>

<div class="modal fade" id="modalEditUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i><?= e(__('usr_edit_title')) ?>: <span id="editUsername" class="fw-mono"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formEditUser">
                <input type="hidden" id="editUserId" name="user_id">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_full_name')) ?></label>
                        <input type="text" id="editFullName" name="full_name" class="form-control" placeholder="<?= e(__('modal_fullname_ph')) ?>" required>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_role')) ?></label>
                        <select id="editRole" name="role" class="form-select">
                            <option value="viewer"><?= e(__('usr_role_viewer')) ?></option>
                            <option value="admin"><?= e(__('usr_role_admin')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check me-1"></i><?= e(__('btn_save')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalAddUser" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i><?= e(__('usr_new_title')) ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formAddUser">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_full_name')) ?></label>
                        <input type="text" name="full_name" class="form-control" placeholder="<?= e(__('modal_fullname_ph')) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_username')) ?></label>
                        <input type="text" name="username" class="form-control fw-mono"
                               placeholder="novak" pattern="[a-z0-9\-]+" required>
                        <div class="form-text"><?= e(__('usr_slug_hint')) ?></div>
                    </div>
                    <div class="mb-1">
                        <label class="form-label small fw-semibold text-muted"><?= e(__('label_role')) ?></label>
                        <select name="role" class="form-select">
                            <option value="viewer"><?= e(__('usr_role_viewer')) ?></option>
                            <option value="admin"><?= e(__('usr_role_admin')) ?></option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><?= e(__('btn_cancel')) ?></button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-plus me-1"></i><?= e(__('btn_add')) ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$extraJs = <<<'JS'
<script>
const editModal = new bootstrap.Modal(document.getElementById('modalEditUser'));

document.querySelectorAll('tr.user-row').forEach(row => {
    row.addEventListener('click', function (e) {
        if (e.target.closest('button')) return;
        document.getElementById('editUserId').value          = this.dataset.userId;
        document.getElementById('editFullName').value        = this.dataset.fullName;
        document.getElementById('editUsername').textContent  = this.dataset.username;
        const roleEl = document.getElementById('editRole');
        roleEl.value    = this.dataset.role;
        roleEl.disabled = this.dataset.username === 'admin';
        editModal.show();
    });
});

document.getElementById('formEditUser').addEventListener('submit', async e => {
    e.preventDefault();
    const res = await apiPost('./ajax/api.php', {
        action:    'update_user',
        user_id:   document.getElementById('editUserId').value,
        full_name: document.getElementById('editFullName').value,
        role:      document.getElementById('editRole').value,
    });
    if (res.ok) { showToast(Lang.saved); setTimeout(() => location.reload(), 900); }
    else showToast(res.error || Lang.error, 'danger');
});

document.getElementById('formAddUser').addEventListener('submit', async e => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.target));
    const res  = await apiPost('./ajax/api.php', { action: 'add_user', ...data });
    if (res.ok) { showToast(Lang.userAdded); setTimeout(() => location.reload(), 900); }
    else showToast(res.error || Lang.error, 'danger');
});

document.querySelectorAll('[data-action="reset_totp"]').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!await confirmModal(this.dataset.confirm, Lang.reset, 'btn-warning')) return;
        const res = await apiPost('./ajax/api.php', { action: 'reset_totp', ...JSON.parse(this.dataset.params) });
        if (res.ok) { showToast(Lang.totpReset); setTimeout(() => location.reload(), 900); }
        else showToast(res.error || Lang.error, 'danger');
    });
});

document.querySelectorAll('[data-action="delete_user"]').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!await confirmModal(this.dataset.confirm)) return;
        const res = await apiPost('./ajax/api.php', { action: 'delete_user', ...JSON.parse(this.dataset.params) });
        if (res.ok) { showToast(Lang.deleted); this.closest('tr').remove(); }
        else showToast(res.error || Lang.error, 'danger');
    });
});
</script>
JS;

include dirname(__FILE__) . '/footer.php';
?>
