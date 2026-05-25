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

$doc = getDocument($id);
if (!$doc) {
    http_response_code(404);
    exit(__('err_not_found'));
}

$absFile = DOCS_DIR . '/' . $doc['filename'];
if (!file_exists($absFile)) {
    http_response_code(404);
    exit(__('err_file_missing'));
}

$ascii   = preg_replace('/[^a-zA-Z0-9._\-]/', '_', $doc['original_name']);
$encoded = rawurlencode($doc['original_name']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . $encoded);
header('Content-Length: ' . filesize($absFile));
header('Cache-Control: private, no-cache');
readfile($absFile);
exit;
