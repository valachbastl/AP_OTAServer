<?php
require_once dirname(__FILE__) . '/../config.php';
require_once dirname(__FILE__) . '/../php/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) { echo json_encode(['ok' => false, 'error' => 'Unauthorized']); exit; }

$devices = getAllDevices();
$total   = count($devices);
$online  = $offline = $update = $installing = 0;

$rows = [];
foreach ($devices as $d) {
    $status = deviceStatus($d);
    if ($status === 'offline' || $status === 'never') $offline++;
    elseif ($status === 'update')     $update++;
    elseif ($status === 'installing') $installing++;
    else $online++;

    $latest    = getLatestFirmware($d['group_slug'], $d['type_slug'], $d['component_slug'], $d['hw_version']);
    $latestFw  = $latest['fw_version'] ?? null;
    $currentFw = $d['fw_version'] ?? '';

    $rows[] = [
        'key'            => $d['device_key'],
        'status'         => $status,
        'badge'          => deviceStatusBadge($status),
        'fw'             => $currentFw ?: '—',
        'latest_fw'      => $latestFw,
        'latest_differs' => $latestFw !== null && $latestFw !== $currentFw,
        'uptime'         => $d['uptime'] ? formatUptime((int)$d['uptime']) : '—',
        'last_seen_short' => $d['last_seen'] ? date('d.m. H:i', strtotime($d['last_seen'])) : '—',
        'last_seen'       => $d['last_seen'] ? date('d.m.Y H:i', strtotime($d['last_seen'])) : '—',
    ];
}

echo json_encode([
    'ok'      => true,
    'stats'   => compact('total', 'online', 'offline', 'update', 'installing'),
    'devices' => $rows,
]);
