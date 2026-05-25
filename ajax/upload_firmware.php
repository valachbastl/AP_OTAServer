<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../php/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn() || !isAdmin()) {
    echo json_encode(['ok' => false, 'error' => __('err_access_denied')]);
    exit;
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verifyCsrf($csrf)) {
    echo json_encode(['ok' => false, 'error' => __('err_invalid_csrf')]);
    exit;
}

$group     = slugify($_POST['group']     ?? '');
$type      = slugify($_POST['type']      ?? '');
$component = slugify($_POST['component'] ?? '');
$hw        = $_POST['hw']        ?? '';
$fwVersion = trim($_POST['fw_version'] ?? '');
$notes     = trim($_POST['notes']      ?? '');

if (!preg_match('/^\d{2}\.\d{1,2}\.\d+$/', $fwVersion)) {
    echo json_encode(['ok' => false, 'error' => __('err_invalid_version', date('y') . '.' . (int)date('n') . '.0')]);
    exit;
}

foreach (['group' => $group, 'type' => $type, 'component' => $component] as $name => $val) {
    if (!validateSlug($val)) {
        echo json_encode(['ok' => false, 'error' => __('err_invalid_slug', $name)]);
        exit;
    }
}

if (!validateSlug(str_replace('.', '', $hw))) {
    echo json_encode(['ok' => false, 'error' => __('err_invalid_hw_version')]);
    exit;
}

if (empty($_FILES['fw_file']) || $_FILES['fw_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => __('err_no_file')]);
    exit;
}

$file = $_FILES['fw_file'];

if (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'bin') {
    echo json_encode(['ok' => false, 'error' => __('err_unsupported_ext', 'bin')]);
    exit;
}

if ($file['size'] < 100) {
    echo json_encode(['ok' => false, 'error' => __('err_file_too_small')]);
    exit;
}

if ($file['size'] > 4 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => __('err_file_too_large', 4)]);
    exit;
}

$relPath = $group . '/' . $type . '/' . $component . '/' . $hw;
$absDir  = FIRMWARE_DIR . '/' . $relPath;

if (!is_dir($absDir)) {
    if (!mkdir($absDir, 0755, true)) {
        echo json_encode(['ok' => false, 'error' => __('err_cannot_create_dir')]);
        exit;
    }
}

$filename = $fwVersion . '.bin';
$absFile  = $absDir . '/' . $filename;
$relFile  = $relPath . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $absFile)) {
    echo json_encode(['ok' => false, 'error' => __('err_cannot_save_file')]);
    exit;
}

try {
    $db   = getDB();
    $stmt = $db->prepare(
        'INSERT INTO firmware (group_slug, type_slug, component_slug, hw_version, fw_version, file_path, file_size, notes, uploaded_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$group, $type, $component, $hw, $fwVersion, $relFile, $file['size'], $notes ?: null, date('Y-m-d H:i:s')]);
} catch (PDOException) {
    @unlink($absFile);
    echo json_encode(['ok' => false, 'error' => __('err_db_error')]);
    exit;
}

echo json_encode(['ok' => true, 'version' => $fwVersion]);
