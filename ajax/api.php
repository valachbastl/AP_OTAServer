<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../php/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['ok' => false, 'error' => __('err_access_denied')]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrf($csrf)) {
    echo json_encode(['ok' => false, 'error' => __('err_invalid_csrf')]);
    exit;
}

$action = $_POST['action'] ?? '';

// ── Akce dostupné všem přihlášeným ───────────────────────────────────────────
if ($action === 'reset_own_totp') {
    $user = currentUser();
    resetUserTotp($user['id']);
    $_SESSION['setup_username'] = $user['username'];
    echo json_encode(['ok' => true]);
    exit;
}

// ── Akce vyžadující admin ─────────────────────────────────────────────────────
if (!isAdmin()) {
    echo json_encode(['ok' => false, 'error' => __('err_admin_required')]);
    exit;
}

match ($action) {

    'add_group' => (function () {
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!validateSlug($slug) || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => addGroup($slug, $name)]);
    })(),

    'add_type' => (function () {
        $g    = slugify($_POST['group_slug'] ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$g || !validateSlug($slug) || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => addDeviceType($g, $slug, $name)]);
    })(),

    'add_comp' => (function () {
        $g    = slugify($_POST['group_slug'] ?? '');
        $t    = slugify($_POST['type_slug']  ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$g || !$t || !validateSlug($slug) || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => addComponent($g, $t, $slug, $name)]);
    })(),

    'add_hw' => (function () {
        $g    = slugify($_POST['group_slug'] ?? '');
        $t    = slugify($_POST['type_slug']  ?? '');
        $c    = slugify($_POST['comp_slug']  ?? '');
        $slug = trim($_POST['slug'] ?? '');
        if (!$g || !$t || !$c || !validateSlug(str_replace('.', '', $slug))) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => addHwVersion($g, $t, $c, $slug)]);
    })(),

    'rename_group' => (function () {
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$slug || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => renameGroup($slug, $name)]);
    })(),

    'rename_type' => (function () {
        $g    = slugify($_POST['group_slug'] ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$g || !$slug || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => renameDeviceType($g, $slug, $name)]);
    })(),

    'rename_comp' => (function () {
        $g    = slugify($_POST['group_slug'] ?? '');
        $t    = slugify($_POST['type_slug']  ?? '');
        $slug = slugify($_POST['slug'] ?? '');
        $name = trim($_POST['name'] ?? '');
        if (!$g || !$t || !$slug || empty($name)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        echo json_encode(['ok' => renameComponent($g, $t, $slug, $name)]);
    })(),

    'delete_group' => (function () {
        $slug = slugify($_POST['slug'] ?? '');
        echo json_encode(['ok' => deleteGroup($slug)]);
    })(),

    'delete_type' => (function () {
        echo json_encode(['ok' => deleteDeviceType(slugify($_POST['group_slug'] ?? ''), slugify($_POST['slug'] ?? ''))]);
    })(),

    'delete_component' => (function () {
        echo json_encode(['ok' => deleteComponent(slugify($_POST['group_slug'] ?? ''), slugify($_POST['type_slug'] ?? ''), slugify($_POST['slug'] ?? ''))]);
    })(),

    'delete_hw' => (function () {
        echo json_encode(['ok' => deleteHwVersion(
            slugify($_POST['group_slug'] ?? ''),
            slugify($_POST['type_slug']  ?? ''),
            slugify($_POST['comp_slug']  ?? ''),
            trim($_POST['slug'] ?? '')
        )]);
    })(),

    'delete_firmware' => (function () {
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode(['ok' => $id > 0 && deleteFirmware($id)]);
    })(),

    'set_label' => (function () {
        $key   = sanitizeDeviceKey($_POST['device_key'] ?? '');
        $label = trim(substr($_POST['label'] ?? '', 0, 100));
        echo json_encode(['ok' => updateDeviceLabel($key, $label)]);
    })(),

    'update_device_settings' => (function () {
        $key                = sanitizeDeviceKey($_POST['device_key'] ?? '');
        $interval           = max(30, min(86400, (int)($_POST['interval'] ?? 300)));
        $tolerance          = max(10, min(3600, (int)($_POST['tolerance'] ?? 120)));
        $monitoringDisabled = isset($_POST['monitoring_disabled']) ? 1 : 0;
        echo json_encode(['ok' => updateDeviceSettings($key, $interval, $tolerance, $monitoringDisabled)]);
    })(),

    'delete_device' => (function () {
        $key = sanitizeDeviceKey($_POST['device_key'] ?? '');
        echo json_encode(['ok' => deleteDevice($key)]);
    })(),

    'delete_document' => (function () {
        $id = (int)($_POST['id'] ?? 0);
        echo json_encode(['ok' => $id > 0 && deleteDocument($id)]);
    })(),

    'add_user' => (function () {
        $username  = slugify($_POST['username']  ?? '');
        $fullName  = trim($_POST['full_name'] ?? '');
        $role      = in_array($_POST['role'] ?? '', ['admin', 'viewer']) ? $_POST['role'] : 'viewer';
        if (!validateSlug($username) || empty($fullName)) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        $ok = createUser($username, $fullName, $role);
        echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => __('err_user_exists')]);
    })(),

    'update_user' => (function () {
        $id       = (int)($_POST['user_id'] ?? 0);
        $fullName = trim($_POST['full_name'] ?? '');
        $role     = in_array($_POST['role'] ?? '', ['admin', 'viewer']) ? $_POST['role'] : null;
        if ($id <= 0 || empty($fullName) || !$role) { echo json_encode(['ok' => false, 'error' => __('err_invalid_data')]); return; }
        $ok = updateUser($id, $fullName, $role);
        if ($ok && $id === (int)currentUser()['id']) {
            $_SESSION['user_fullname'] = $fullName;
            if (currentUser()['username'] !== 'admin') {
                $_SESSION['user_role'] = $role;
            }
        }
        echo json_encode(['ok' => $ok]);
    })(),

    'delete_user' => (function () {
        $id = (int)($_POST['user_id'] ?? 0);
        echo json_encode(['ok' => $id > 0 && deleteUser($id)]);
    })(),

    'reset_totp' => (function () {
        $id = (int)($_POST['user_id'] ?? 0);
        echo json_encode(['ok' => $id > 0 && resetUserTotp($id)]);
    })(),

    default => (function () { echo json_encode(['ok' => false, 'error' => __('err_unknown_action')]); exit; })(),
};
