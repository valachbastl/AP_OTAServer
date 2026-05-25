'use strict';

// ── Sidebar toggle (mobile) ───────────────────────────────────────────────────
(function () {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!toggle) return;

    function openSidebar() {
        sidebar.classList.add('show');
        overlay.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeSidebar() {
        sidebar.classList.remove('show');
        overlay.classList.remove('show');
        document.body.style.overflow = '';
    }

    toggle.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);
})();

// ── Toast helper ─────────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = 9999;
        document.body.appendChild(container);
    }
    const id   = 'toast-' + Date.now();
    const icon = type === 'success' ? 'bi-check-circle-fill' : type === 'danger' ? 'bi-x-circle-fill' : 'bi-info-circle-fill';
    container.insertAdjacentHTML('beforeend', `
        <div id="${id}" class="toast align-items-center text-bg-${type} border-0" role="alert">
            <div class="d-flex">
                <div class="toast-body d-flex align-items-center gap-2">
                    <i class="bi ${icon}"></i> ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
            </div>
        </div>`);
    const el = document.getElementById(id);
    new bootstrap.Toast(el, { delay: 3500 }).show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

// ── AJAX helper ───────────────────────────────────────────────────────────────
async function apiPost(url, data) {
    const form = new FormData();
    const csrf = document.querySelector('meta[name="csrf-token"]');
    if (csrf) form.append('csrf_token', csrf.content);
    for (const [k, v] of Object.entries(data)) form.append(k, v);
    const res  = await fetch(url, { method: 'POST', body: form });
    return res.json();
}

// ── Firmware upload ───────────────────────────────────────────────────────────
(function () {
    const zone      = document.getElementById('uploadZone');
    const fileInput = document.getElementById('fwFile');
    const versionIn = document.getElementById('fwVersion');
    const form      = document.getElementById('uploadForm');
    if (!zone) return;

    let pendingFile = null;

    // Drag & drop
    zone.addEventListener('click', () => fileInput.click());
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
    zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
    zone.addEventListener('drop', e => {
        e.preventDefault();
        zone.classList.remove('dragover');
        if (e.dataTransfer.files[0]) {
            pendingFile = e.dataTransfer.files[0];
            handleFile(pendingFile);
        }
    });
    fileInput.addEventListener('change', () => {
        if (fileInput.files[0]) {
            pendingFile = fileInput.files[0];
            handleFile(pendingFile);
        }
    });

    function handleFile(file) {
        if (!file.name.endsWith('.bin')) {
            showToast((window.Lang && window.Lang.mustBeBin) || 'Soubor musí být .bin', 'danger');
            return;
        }
        document.getElementById('uploadFileName').textContent = file.name + ' (' + formatBytes(file.size) + ')';
        document.getElementById('uploadFileName').classList.remove('d-none');
        suggestVersion(file);
    }

    // Skenování binárky pro verzi formátu YY.M.PATCH
    function suggestVersion(file) {
        const reader = new FileReader();
        reader.onload = function (e) {
            const buf   = new Uint8Array(e.target.result);
            const text  = new TextDecoder('latin1').decode(buf);
            // hledáme vzor XX.M.N kde XX = 2-místný rok
            const match = text.match(/\b(2\d)\.(1[0-2]|[1-9])\.(\d+)\b/);
            if (match) {
                versionIn.value = match[0];
                versionIn.classList.add('is-valid');
            } else {
                // předvyplnit z aktuálního data jako návrh
                const now = new Date();
                const yy  = String(now.getFullYear()).slice(-2);
                const m   = now.getMonth() + 1;
                versionIn.value       = yy + '.' + m + '.0';
                versionIn.placeholder = yy + '.' + m + '.0';
            }
        };
        // Číst jen prvních 256 KB (verze bývá na začátku binárky)
        reader.readAsArrayBuffer(file.slice(0, 262144));
    }

    // Odeslání
    form && form.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn  = form.querySelector('[type=submit]');
        const orig = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>' + ((window.Lang && window.Lang.uploading) || 'Nahrávám…');

        const fd = new FormData(form);
        if (pendingFile) fd.set('fw_file', pendingFile);
        const csrf = document.querySelector('meta[name="csrf-token"]');
        if (csrf) fd.set('csrf_token', csrf.content);

        try {
            const res  = await fetch('./ajax/upload_firmware.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.ok) {
                const msg = window.Lang ? window.Lang.fwUploaded.replace('%s', data.version) : 'Firmware ' + data.version + ' nahrán';
                showToast(msg);
                setTimeout(() => location.reload(), 1200);
            } else {
                showToast(data.error || (window.Lang && window.Lang.uploadError) || 'Chyba při nahrávání', 'danger');
            }
        } catch {
            showToast((window.Lang && window.Lang.networkError) || 'Chyba sítě', 'danger');
        } finally {
            btn.disabled  = false;
            btn.innerHTML = orig;
        }
    });

    function formatBytes(b) {
        if (b >= 1048576) return (b / 1048576).toFixed(1) + ' MB';
        if (b >= 1024)    return (b / 1024).toFixed(1) + ' KB';
        return b + ' B';
    }
})();

// ── Label edit modal ──────────────────────────────────────────────────────────
function promptModal(current) {
    return new Promise(resolve => {
        const el    = document.getElementById('editLabelModal');
        const input = document.getElementById('editLabelInput');
        const okBtn = document.getElementById('editLabelOk');
        input.value = current;
        const bsModal = bootstrap.Modal.getOrCreateInstance(el);
        let confirmed = false;

        function onOk() { confirmed = true; bsModal.hide(); }
        function onKeydown(e) { if (e.key === 'Enter') onOk(); }
        function onHide() {
            okBtn.removeEventListener('click', onOk);
            input.removeEventListener('keydown', onKeydown);
            resolve(confirmed ? input.value : null);
        }

        okBtn.addEventListener('click', onOk, { once: true });
        input.addEventListener('keydown', onKeydown);
        el.addEventListener('hidden.bs.modal', onHide, { once: true });
        el.addEventListener('shown.bs.modal', () => { input.focus(); input.select(); }, { once: true });
        bsModal.show();
    });
}

// ── Inline label edit ─────────────────────────────────────────────────────────
document.querySelectorAll('[data-edit-label]').forEach(btn => {
    btn.addEventListener('click', async function () {
        const key     = this.dataset.editLabel;
        const cell    = this.closest('tr').querySelector('[data-label-cell]');
        const current = cell.dataset.labelValue || '';
        const val     = await promptModal(current);
        if (val === null) return;
        const res = await apiPost('./ajax/api.php', { action: 'set_label', device_key: key, label: val.trim() });
        if (res.ok) {
            showToast((window.Lang && window.Lang.saved) || 'Uloženo');
            setTimeout(() => location.reload(), 2000);
        } else {
            showToast(res.error || (window.Lang && window.Lang.error) || 'Chyba', 'danger');
        }
    });
});

// ── Confirm modal ─────────────────────────────────────────────────────────────
function confirmModal(message, okLabel = 'Smazat', okClass = 'btn-danger') {
    return new Promise(resolve => {
        const el    = document.getElementById('confirmModal');
        const okBtn = document.getElementById('confirmModalOk');
        document.getElementById('confirmModalMsg').textContent = message;
        okBtn.className  = 'btn ' + okClass;
        okBtn.textContent = okLabel;
        const bsModal = bootstrap.Modal.getOrCreateInstance(el);
        let confirmed = false;

        okBtn.addEventListener('click', () => { confirmed = true; bsModal.hide(); }, { once: true });
        el.addEventListener('hidden.bs.modal', () => resolve(confirmed), { once: true });
        bsModal.show();
    });
}

// ── Confirm delete ────────────────────────────────────────────────────────────
document.querySelectorAll('[data-confirm-delete]').forEach(btn => {
    btn.addEventListener('click', async function (e) {
        e.preventDefault();
        e.stopPropagation();
        const msg = this.dataset.confirmDelete || 'Opravdu smazat?';
        if (!await confirmModal(msg)) return;
        const action = this.dataset.action;
        const params = JSON.parse(this.dataset.params || '{}');
        const res    = await apiPost('./ajax/api.php', { action, ...params });
        if (res.ok) {
            showToast((window.Lang && window.Lang.deleted) || 'Smazáno');
            const row = this.closest('tr');
            if (row) row.remove();
            else location.reload();
        } else {
            showToast(res.error || (window.Lang && window.Lang.error) || 'Chyba', 'danger');
        }
    });
});

// ── Next suggested firmware version ──────────────────────────────────────────
function suggestNextVersion(existingVersions) {
    const now = new Date();
    const yy  = String(now.getFullYear()).slice(-2);
    const m   = now.getMonth() + 1;
    const prefix = yy + '.' + m + '.';
    const patches = existingVersions
        .filter(v => v.startsWith(prefix))
        .map(v => parseInt(v.slice(prefix.length), 10))
        .filter(n => !isNaN(n));
    const next = patches.length > 0 ? Math.max(...patches) + 1 : 0;
    return prefix + next;
}

// Exportovat pro inline použití
window.suggestNextVersion = suggestNextVersion;
window.showToast          = showToast;
window.apiPost            = apiPost;
window.confirmModal       = confirmModal;
