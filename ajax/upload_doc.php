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

$entityType = $_POST['entity_type'] ?? '';
$category   = in_array($_POST['category'] ?? '', ['schema','pcb','platformio','photo','other'])
              ? $_POST['category'] : 'other';
$notes      = trim(substr($_POST['notes'] ?? '', 0, 500));

if ($entityType === 'hw_version') {
    $g  = slugify($_POST['group'] ?? '');
    $t  = slugify($_POST['type']  ?? '');
    $c  = slugify($_POST['comp']  ?? '');
    $hw = trim($_POST['hw'] ?? '');
    if (!$g || !$t || !$c || !$hw) {
        echo json_encode(['ok' => false, 'error' => __('err_invalid_hw_params')]);
        exit;
    }
    $subdir = 'hw/' . $g . '/' . $t . '/' . $c . '/' . $hw;
} elseif ($entityType === 'device') {
    $deviceKey = sanitizeDeviceKey($_POST['device_key'] ?? '');
    if (!$deviceKey) {
        echo json_encode(['ok' => false, 'error' => __('err_invalid_device')]);
        exit;
    }
    $subdir = 'devices/' . $deviceKey;
} else {
    echo json_encode(['ok' => false, 'error' => __('err_invalid_entity')]);
    exit;
}

if (empty($_FILES['doc_file']) || $_FILES['doc_file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'error' => __('err_no_file')]);
    exit;
}

$file     = $_FILES['doc_file'];
$origName = basename($file['name']);
$ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

$allowedExts = ['pdf','png','jpg','jpeg','zip','sch','kicad_sch','kicad_pcb','kicad_pro','kicad_mod'];
if (!in_array($ext, $allowedExts, true)) {
    echo json_encode(['ok' => false, 'error' => __('err_unsupported_ext', implode(', ', $allowedExts))]);
    exit;
}

if ($file['size'] > 50 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'error' => __('err_file_too_large', 50)]);
    exit;
}

if ($file['size'] < 1) {
    echo json_encode(['ok' => false, 'error' => __('err_file_empty')]);
    exit;
}

$safeFilename = $subdir . '/' . docSafeName($origName);
$absDir       = DOCS_DIR . '/' . $subdir;
$absFile      = DOCS_DIR . '/' . $safeFilename;

if (!is_dir($absDir) && !mkdir($absDir, 0755, true)) {
    echo json_encode(['ok' => false, 'error' => __('err_cannot_create_dir')]);
    exit;
}

if (!move_uploaded_file($file['tmp_name'], $absFile)) {
    echo json_encode(['ok' => false, 'error' => __('err_cannot_save_file')]);
    exit;
}

try {
    $db = getDB();
    if ($entityType === 'hw_version') {
        $stmt = $db->prepare(
            'INSERT INTO documents (entity_type, group_slug, type_slug, comp_slug, hw_version,
             category, original_name, filename, file_size, mime_type, notes, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['hw_version', $g, $t, $c, $hw,
                        $category, $origName, $safeFilename, $file['size'], $file['type'], $notes ?: null, date('Y-m-d H:i:s')]);
    } else {
        $stmt = $db->prepare(
            'INSERT INTO documents (entity_type, device_key,
             category, original_name, filename, file_size, mime_type, notes, uploaded_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute(['device', $deviceKey,
                        $category, $origName, $safeFilename, $file['size'], $file['type'], $notes ?: null, date('Y-m-d H:i:s')]);
    }
} catch (PDOException) {
    @unlink($absFile);
    echo json_encode(['ok' => false, 'error' => __('err_db_error')]);
    exit;
}

echo json_encode(['ok' => true]);
