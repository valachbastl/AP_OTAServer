<?php
/**
 * OTA endpoint pro ESP zařízení
 *
 * Krok 1 – zjistit nejnovější verzi (ESP rozhodne samo):
 *   GET /ota.php?group=&type=&component=&hw=&device=&fw=&uptime=&interval=
 *   → 200  plain text "26.5.1"
 *   → 404  žádný firmware na serveru
 *
 * Krok 2 – stáhnout binárku:
 *   GET /ota.php?...&download=1
 *   → 200  binární data
 */

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/php/functions.php';

if (defined('OTA_AUTH') && OTA_AUTH) {
    $key = $_SERVER['HTTP_X_OTA_KEY'] ?? '';
    if (!hash_equals(APP_SECRET, $key)) {
        http_response_code(403);
        exit('Unauthorized');
    }
}

$required = ['group', 'type', 'component', 'hw', 'device', 'fw'];
foreach ($required as $key) {
    if (empty($_GET[$key])) {
        http_response_code(400);
        exit('Missing: ' . $key);
    }
}

$group     = slugify($_GET['group']);
$type      = slugify($_GET['type']);
$component = slugify($_GET['component']);
$hw        = slugify($_GET['hw']);
$deviceKey = strtolower(preg_replace('/[^a-fA-F0-9]/', '', $_GET['device']));
$fwVersion = trim($_GET['fw']);
$uptime    = max(0, (int)($_GET['uptime'] ?? 0));
$interval  = max(30, min(86400, (int)($_GET['interval'] ?? 300)));
$download  = isset($_GET['download']);

if (strlen($deviceKey) < 6 || strlen($deviceKey) > 17) {
    http_response_code(400);
    exit('Invalid device key');
}

upsertDevice($deviceKey, $group, $type, $component, $hw, $fwVersion, $uptime, $interval);

$device = getDevice($deviceKey);
$latest = getLatestFirmware($group, $type, $component, $hw);

if (!$latest) {
    http_response_code(404);
    exit('No firmware found');
}

// Per-zařízení politika aktualizací
//   updates_disabled = 1  → zařízení zmrazeno, nikdy nedostane jiný FW (test nového FW jen na vybraných kusech)
//   allow_downgrade  = 0  → nenabízet starší verzi, než zařízení právě běží (výchozí stav)
$blocked = false;
if ((int)($device['updates_disabled'] ?? 0)) {
    $blocked = true;
} elseif (!(int)($device['allow_downgrade'] ?? 0)
          && version_compare($latest['fw_version'], $fwVersion, '<')) {
    $blocked = true;
}

// Pokud je aktualizace blokovaná, tváříme se, že žádná novější verze není:
//   krok 1 vrátí verzi, kterou zařízení hlásí → ESP vidí shodu a nestahuje
//   krok 2 (download) odmítneme, aby to nešlo obejít přidáním &download=1
if ($blocked) {
    if ($download) {
        http_response_code(404);
        exit('No update available');
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $fwVersion;
    exit;
}

// Krok 1: vrátit číslo nejnovější verze, ESP rozhodne samo
if (!$download) {
    header('Content-Type: text/plain; charset=utf-8');
    echo $latest['fw_version'];
    exit;
}

// Krok 2: odeslat binárku
$filePath = FIRMWARE_DIR . '/' . $latest['file_path'];

if (!file_exists($filePath)) {
    http_response_code(500);
    exit('Firmware file missing on server');
}

recordDeviceDownload($deviceKey, $latest['fw_version']);

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="firmware.bin"');
header('Content-Length: ' . filesize($filePath));
header('X-OTA-Version: ' . $latest['fw_version']);
header('Cache-Control: no-cache, no-store');

readfile($filePath);
exit;
