<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../php/functions.php';

if (!isLoggedIn()) {
    http_response_code(403);
    exit(__('err_access_denied'));
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    exit(__('err_missing_id'));
}

$fw = getFirmware($id);
if (!$fw) {
    http_response_code(404);
    exit(__('err_not_found'));
}

$absFile = FIRMWARE_DIR . '/' . $fw['file_path'];
if (!file_exists($absFile)) {
    http_response_code(404);
    exit(__('err_file_missing'));
}

$filename = $fw['fw_version'] . '.bin';

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($absFile));
header('Cache-Control: private, no-cache');
readfile($absFile);
exit;
