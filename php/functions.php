<?php
require_once dirname(__FILE__) . '/db.php';
require_once dirname(__FILE__) . '/totp.php';

// ── JAZYK ─────────────────────────────────────────────────────────────────────

function availableLangs(): array {
    static $langs = null;
    if ($langs === null) {
        $langs = [];
        foreach (glob(dirname(__FILE__) . '/../lng/*.php') ?: [] as $file) {
            $code = basename($file, '.php');
            $data = require $file;
            $langs[$code] = $data['lang_name'] ?? strtoupper($code);
        }
        ksort($langs);
        if (empty($langs)) $langs = ['cs' => 'Čeština'];
    }
    return $langs;
}

function currentLang(): string {
    static $lang = null;
    if ($lang === null) {
        $supported = array_keys(availableLangs());
        $cookie    = $_COOKIE['ota_lang'] ?? 'cs';
        $lang      = in_array($cookie, $supported, true) ? $cookie : ($supported[0] ?? 'cs');
    }
    return $lang;
}

function __(string $key, mixed ...$args): string {
    static $strings = null;
    if ($strings === null) {
        $file    = dirname(__FILE__) . '/../lng/' . currentLang() . '.php';
        $strings = file_exists($file) ? require $file : [];
    }
    $str = $strings[$key] ?? $key;
    return $args ? sprintf($str, ...$args) : $str;
}

// ── RATE LIMITING ─────────────────────────────────────────────────────────────

function clientIp(): string {
    $proxy = defined('TRUSTED_PROXY') ? TRUSTED_PROXY : 'none';
    if ($proxy === 'cloudflare') {
        $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '';
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    } elseif ($proxy === 'proxy') {
        $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $ip = trim(explode(',', $forwarded)[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) return $ip;
    }
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function isLoginBlocked(string $ip): bool {
    $db   = getDB();
    $stmt = $db->prepare(
        "SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND created_at > datetime('now', 'localtime', '-15 minutes')"
    );
    $stmt->execute([$ip]);
    return (int)$stmt->fetchColumn() >= 5;
}

function recordLoginFailure(string $ip): void {
    $db = getDB();
    $db->prepare('INSERT INTO login_attempts (ip_address) VALUES (?)')->execute([$ip]);
    $db->prepare("DELETE FROM login_attempts WHERE created_at < datetime('now', 'localtime', '-15 minutes')")->execute();
}

function clearLoginAttempts(string $ip): void {
    $db = getDB();
    $db->prepare('DELETE FROM login_attempts WHERE ip_address = ?')->execute([$ip]);
}

// ── SESSION ──────────────────────────────────────────────────────────────────

function startSecureSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name(SESSION_NAME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFE,
        'path'     => '/',
        'secure'   => isset($_SERVER['HTTPS']) || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'),
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function isLoggedIn(): bool {
    startSecureSession();
    return !empty($_SESSION['user_id']) && !empty($_SESSION['user_role']);
}

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === 'admin';
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: index.php?page=login');
        exit;
    }
}

function requireAdmin(): void {
    requireLogin();
    if (!isAdmin()) {
        header('Location: index.php?page=dashboard');
        exit;
    }
}

function sessionLogin(array $user): void {
    startSecureSession();
    session_regenerate_id(true);
    $_SESSION['user_id']       = $user['id'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['user_fullname'] = $user['full_name'];
    $_SESSION['user_role']     = $user['role'];
}

function sessionLogout(): void {
    startSecureSession();
    $_SESSION = [];
    session_destroy();
}

function currentUser(): array {
    return [
        'id'        => $_SESSION['user_id']       ?? 0,
        'username'  => $_SESSION['user_username'] ?? '',
        'full_name' => $_SESSION['user_fullname'] ?? '',
        'role'      => $_SESSION['user_role']     ?? '',
    ];
}

// ── CSRF ─────────────────────────────────────────────────────────────────────

function csrfToken(): string {
    startSecureSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(string $token): bool {
    startSecureSession();
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrfToken()) . '">';
}

// ── UŽIVATELÉ ─────────────────────────────────────────────────────────────────

function getUserByUsername(string $username): array|false {
    $db   = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE username = ?');
    $stmt->execute([$username]);
    return $stmt->fetch();
}

function getAllUsers(): array {
    return getDB()->query('SELECT id, username, full_name, role, totp_secret IS NOT NULL AS has_totp, created_at FROM users ORDER BY role DESC, username')->fetchAll();
}

function createUser(string $username, string $fullName, string $role): bool {
    try {
        $db   = getDB();
        $stmt = $db->prepare('INSERT INTO users (username, full_name, role) VALUES (?, ?, ?)');
        return $stmt->execute([slugify($username), $fullName, $role]);
    } catch (PDOException) {
        return false;
    }
}

function deleteUser(int $id): bool {
    $db   = getDB();
    $stmt = $db->prepare('DELETE FROM users WHERE id = ? AND username != "admin"');
    return $stmt->execute([$id]);
}

function updateUser(int $id, string $fullName, string $role): bool {
    try {
        $db = getDB();
        $db->prepare('UPDATE users SET full_name = ? WHERE id = ?')->execute([$fullName, $id]);
        $db->prepare('UPDATE users SET role = ? WHERE id = ? AND username != "admin"')->execute([$role, $id]);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function resetUserTotp(int $id): bool {
    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET totp_secret = NULL, backup_codes = NULL WHERE id = ?');
    return $stmt->execute([$id]);
}

function saveUserTotp(int $id, string $secret, array $hashedBackupCodes): bool {
    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET totp_secret = ?, backup_codes = ? WHERE id = ?');
    return $stmt->execute([$secret, json_encode($hashedBackupCodes), $id]);
}

function consumeBackupCode(array $user, string $input): bool {
    if (empty($user['backup_codes'])) return false;
    $codes = json_decode($user['backup_codes'], true);
    $index = TOTP::verifyBackupCode($input, $codes);
    if ($index === -1) return false;
    array_splice($codes, $index, 1);
    $db   = getDB();
    $stmt = $db->prepare('UPDATE users SET backup_codes = ? WHERE id = ?');
    $stmt->execute([json_encode($codes), $user['id']]);
    return true;
}

// ── SKUPINY / TYPY / KOMPONENTY / HW ─────────────────────────────────────────

function getGroups(): array {
    return getDB()->query('SELECT * FROM groups_list ORDER BY slug')->fetchAll();
}

function getDeviceTypes(string $groupSlug): array {
    $stmt = getDB()->prepare('SELECT * FROM device_types WHERE group_slug = ? ORDER BY slug');
    $stmt->execute([$groupSlug]);
    return $stmt->fetchAll();
}

function getComponents(string $groupSlug, string $typeSlug): array {
    $stmt = getDB()->prepare('SELECT * FROM components WHERE group_slug = ? AND type_slug = ? ORDER BY slug');
    $stmt->execute([$groupSlug, $typeSlug]);
    return $stmt->fetchAll();
}

function getHwVersions(string $groupSlug, string $typeSlug, string $compSlug): array {
    $stmt = getDB()->prepare('SELECT * FROM hw_versions WHERE group_slug = ? AND type_slug = ? AND comp_slug = ? ORDER BY slug');
    $stmt->execute([$groupSlug, $typeSlug, $compSlug]);
    return $stmt->fetchAll();
}

function addGroup(string $slug, string $name): bool {
    try {
        $stmt = getDB()->prepare('INSERT INTO groups_list (slug, name) VALUES (?, ?)');
        return $stmt->execute([$slug, $name]);
    } catch (PDOException) { return false; }
}

function addDeviceType(string $groupSlug, string $slug, string $name): bool {
    try {
        $stmt = getDB()->prepare('INSERT INTO device_types (group_slug, slug, name) VALUES (?, ?, ?)');
        return $stmt->execute([$groupSlug, $slug, $name]);
    } catch (PDOException) { return false; }
}

function addComponent(string $groupSlug, string $typeSlug, string $slug, string $name): bool {
    try {
        $stmt = getDB()->prepare('INSERT INTO components (group_slug, type_slug, slug, name) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$groupSlug, $typeSlug, $slug, $name]);
    } catch (PDOException) { return false; }
}

function addHwVersion(string $groupSlug, string $typeSlug, string $compSlug, string $slug): bool {
    try {
        $stmt = getDB()->prepare('INSERT INTO hw_versions (group_slug, type_slug, comp_slug, slug) VALUES (?, ?, ?, ?)');
        return $stmt->execute([$groupSlug, $typeSlug, $compSlug, $slug]);
    } catch (PDOException) { return false; }
}

function deleteGroup(string $slug): bool {
    $stmt = getDB()->prepare('DELETE FROM groups_list WHERE slug = ?');
    return $stmt->execute([$slug]);
}

function deleteDeviceType(string $groupSlug, string $slug): bool {
    $stmt = getDB()->prepare('DELETE FROM device_types WHERE group_slug = ? AND slug = ?');
    return $stmt->execute([$groupSlug, $slug]);
}

function deleteComponent(string $groupSlug, string $typeSlug, string $slug): bool {
    $stmt = getDB()->prepare('DELETE FROM components WHERE group_slug = ? AND type_slug = ? AND slug = ?');
    return $stmt->execute([$groupSlug, $typeSlug, $slug]);
}

function deleteHwVersion(string $groupSlug, string $typeSlug, string $compSlug, string $slug): bool {
    $stmt = getDB()->prepare('DELETE FROM hw_versions WHERE group_slug = ? AND type_slug = ? AND comp_slug = ? AND slug = ?');
    return $stmt->execute([$groupSlug, $typeSlug, $compSlug, $slug]);
}

function renameGroup(string $slug, string $name): bool {
    $stmt = getDB()->prepare('UPDATE groups_list SET name = ? WHERE slug = ?');
    return $stmt->execute([$name, $slug]);
}

function renameDeviceType(string $groupSlug, string $slug, string $name): bool {
    $stmt = getDB()->prepare('UPDATE device_types SET name = ? WHERE group_slug = ? AND slug = ?');
    return $stmt->execute([$name, $groupSlug, $slug]);
}

function renameComponent(string $groupSlug, string $typeSlug, string $slug, string $name): bool {
    $stmt = getDB()->prepare('UPDATE components SET name = ? WHERE group_slug = ? AND type_slug = ? AND slug = ?');
    return $stmt->execute([$name, $groupSlug, $typeSlug, $slug]);
}

// ── FIRMWARE ──────────────────────────────────────────────────────────────────

function getFirmware(int $id): array|false {
    $stmt = getDB()->prepare('SELECT * FROM firmware WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function getFirmwareList(string $g, string $t, string $c, string $hw): array {
    $stmt = getDB()->prepare(
        'SELECT * FROM firmware WHERE group_slug=? AND type_slug=? AND component_slug=? AND hw_version=?
         ORDER BY uploaded_at DESC'
    );
    $stmt->execute([$g, $t, $c, $hw]);
    return $stmt->fetchAll();
}

function getLatestFirmware(string $g, string $t, string $c, string $hw): array|false {
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $rows  = getDB()->query(
            'SELECT f.* FROM firmware f
             INNER JOIN (
                 SELECT group_slug, type_slug, component_slug, hw_version, MAX(uploaded_at) AS max_at
                 FROM firmware
                 GROUP BY group_slug, type_slug, component_slug, hw_version
             ) latest ON f.group_slug     = latest.group_slug
                     AND f.type_slug      = latest.type_slug
                     AND f.component_slug = latest.component_slug
                     AND f.hw_version     = latest.hw_version
                     AND f.uploaded_at    = latest.max_at'
        )->fetchAll();
        foreach ($rows as $row) {
            $cache["{$row['group_slug']}/{$row['type_slug']}/{$row['component_slug']}/{$row['hw_version']}"] = $row;
        }
    }
    return $cache["$g/$t/$c/$hw"] ?? false;
}

function deleteFirmware(int $id): bool {
    $db   = getDB();
    $stmt = $db->prepare('SELECT file_path FROM firmware WHERE id = ?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) return false;
    $file = FIRMWARE_DIR . '/' . $row['file_path'];
    if (file_exists($file)) unlink($file);
    $stmt = $db->prepare('DELETE FROM firmware WHERE id = ?');
    return $stmt->execute([$id]);
}

// ── ZAŘÍZENÍ ──────────────────────────────────────────────────────────────────

function getAllDevices(): array {
    return getDB()->query('SELECT * FROM devices ORDER BY group_slug, type_slug, label, device_key')->fetchAll();
}

function getDevice(string $deviceKey): array|false {
    $stmt = getDB()->prepare('SELECT * FROM devices WHERE device_key = ?');
    $stmt->execute([$deviceKey]);
    return $stmt->fetch();
}

function upsertDevice(string $deviceKey, string $g, string $t, string $c, string $hw, string $fwVersion, int $uptime, int $interval): void {
    $db       = getDB();
    $existing = getDevice($deviceKey);
    $ip       = clientIp();
    $now      = date('Y-m-d H:i:s');

    if (!$existing) {
        $stmt = $db->prepare(
            'INSERT INTO devices (device_key, group_slug, type_slug, component_slug, hw_version, fw_version, uptime, last_seen, check_interval)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$deviceKey, $g, $t, $c, $hw, $fwVersion, $uptime, $now, $interval]);
        logDeviceEvent($deviceKey, 'checkin', $fwVersion, $ip);
        return;
    }

    $eventType = 'checkin';
    if ($existing['fw_version'] !== $fwVersion) {
        $eventType = 'fw_changed';
    }

    $stmt = $db->prepare(
        'UPDATE devices SET fw_version=?, uptime=?, last_seen=?, check_interval=?, group_slug=?, type_slug=?, component_slug=?, hw_version=?
         WHERE device_key=?'
    );
    $stmt->execute([$fwVersion, $uptime, $now, $interval, $g, $t, $c, $hw, $deviceKey]);
    logDeviceEvent($deviceKey, $eventType, $fwVersion, $ip);
}

function recordDeviceDownload(string $deviceKey, string $fwVersion): void {
    $db   = getDB();
    $stmt = $db->prepare('UPDATE devices SET last_download_at=?, last_download_fw=? WHERE device_key=?');
    $stmt->execute([date('Y-m-d H:i:s'), $fwVersion, $deviceKey]);
    logDeviceEvent($deviceKey, 'download', $fwVersion, clientIp());
}

function updateDeviceLabel(string $deviceKey, string $label): bool {
    $stmt = getDB()->prepare('UPDATE devices SET label=? WHERE device_key=?');
    return $stmt->execute([$label, $deviceKey]);
}

function updateDeviceSettings(string $deviceKey, int $interval, int $tolerance, int $monitoringDisabled): bool {
    $stmt = getDB()->prepare('UPDATE devices SET check_interval=?, tolerance=?, monitoring_disabled=? WHERE device_key=?');
    return $stmt->execute([$interval, $tolerance, $monitoringDisabled, $deviceKey]);
}

function deleteDevice(string $deviceKey): bool {
    $db   = getDB();
    $docs = $db->prepare('SELECT id FROM documents WHERE entity_type=\'device\' AND device_key=?');
    $docs->execute([$deviceKey]);
    foreach ($docs->fetchAll() as $doc) deleteDocument($doc['id']);
    $stmt = $db->prepare('DELETE FROM device_events WHERE device_key=?');
    $stmt->execute([$deviceKey]);
    $stmt = $db->prepare('DELETE FROM devices WHERE device_key=?');
    return $stmt->execute([$deviceKey]);
}

function getDeviceEvents(string $deviceKey, int $limit = 50): array {
    $stmt = getDB()->prepare(
        'SELECT * FROM device_events WHERE device_key=? ORDER BY created_at DESC LIMIT ?'
    );
    $stmt->execute([$deviceKey, $limit]);
    return $stmt->fetchAll();
}

function logDeviceEvent(string $deviceKey, string $type, string $fwVersion, string $ip): void {
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO device_events (device_key, event_type, fw_version, ip_address, created_at) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$deviceKey, $type, $fwVersion, $ip, date('Y-m-d H:i:s')]);

    $days = defined('EVENT_RETENTION_DAYS') ? (int)EVENT_RETENTION_DAYS : 365;
    if ($days > 0) {
        $db->prepare("DELETE FROM device_events WHERE created_at < datetime('now', 'localtime', ? || ' days')")
           ->execute(["-$days"]);
    }
}

function deviceStatus(array $device): string {
    if (empty($device['last_seen'])) return 'never';

    $interval  = (int)$device['check_interval'];
    $tolerance = (int)$device['tolerance'];
    $now       = time();

    if (!(int)($device['monitoring_disabled'] ?? 0)) {
        $lastSeen = strtotime($device['last_seen']);
        if (($now - $lastSeen) > ($interval + $tolerance)) return 'offline';
    }

    $latest = getLatestFirmware(
        $device['group_slug'], $device['type_slug'],
        $device['component_slug'], $device['hw_version']
    );

    if (!$latest) return 'online';

    $latestFw   = $latest['fw_version'];
    $currentFw  = $device['fw_version'] ?? '';
    $downloadFw = $device['last_download_fw'] ?? '';
    $downloadAt = $device['last_download_at'] ? strtotime($device['last_download_at']) : 0;

    if ($currentFw === $latestFw) return 'ok';

    if ($downloadFw === $latestFw && $downloadAt > 0 && ($now - $downloadAt) < ($interval + $tolerance * 2)) {
        return 'installing';
    }

    return 'update';
}

function deviceStatusBadge(string $status): string {
    $t = static fn(string $key) => '<span class="d-none d-sm-inline ms-1">' . e(__($key)) . '</span>';
    return match($status) {
        'ok'         => '<span class="badge bg-success"><i class="bi bi-check-circle"></i>'       . $t('status_ok')         . '</span>',
        'update'     => '<span class="badge bg-warning text-dark"><i class="bi bi-arrow-up-circle"></i>' . $t('status_update') . '</span>',
        'installing' => '<span class="badge bg-info text-dark"><i class="bi bi-arrow-repeat"></i>' . $t('status_installing') . '</span>',
        'offline'    => '<span class="badge bg-danger"><i class="bi bi-wifi-off"></i>'             . $t('status_offline')    . '</span>',
        'never'      => '<span class="badge bg-secondary"><i class="bi bi-question-circle"></i>'  . $t('status_never')      . '</span>',
        default      => '<span class="badge bg-secondary"><i class="bi bi-circle"></i>'           . $t('status_online')     . '</span>',
    };
}

// ── DOKUMENTY ─────────────────────────────────────────────────────────────────

function getDocumentsForHw(string $g, string $t, string $c, string $hw): array {
    $stmt = getDB()->prepare(
        "SELECT * FROM documents WHERE entity_type='hw_version'
         AND group_slug=? AND type_slug=? AND comp_slug=? AND hw_version=?
         ORDER BY category, uploaded_at DESC"
    );
    $stmt->execute([$g, $t, $c, $hw]);
    return $stmt->fetchAll();
}

function getDocumentsForDevice(string $deviceKey): array {
    $stmt = getDB()->prepare(
        "SELECT * FROM documents WHERE entity_type='device' AND device_key=?
         ORDER BY category, uploaded_at DESC"
    );
    $stmt->execute([$deviceKey]);
    return $stmt->fetchAll();
}

function getDocument(int $id): array|false {
    $stmt = getDB()->prepare('SELECT * FROM documents WHERE id=?');
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function deleteDocument(int $id): bool {
    $db   = getDB();
    $stmt = $db->prepare('SELECT filename FROM documents WHERE id=?');
    $stmt->execute([$id]);
    $row  = $stmt->fetch();
    if (!$row) return false;
    $file = DOCS_DIR . '/' . $row['filename'];
    if (file_exists($file)) unlink($file);
    $stmt = $db->prepare('DELETE FROM documents WHERE id=?');
    return $stmt->execute([$id]);
}

function docCategoryLabel(string $cat): string {
    return match($cat) {
        'schema'     => __('doc_cat_schema'),
        'pcb'        => __('doc_cat_pcb'),
        'platformio' => __('doc_cat_platformio'),
        'photo'      => __('doc_cat_photo'),
        default      => __('doc_cat_other'),
    };
}

function docCategoryIcon(string $cat): string {
    return match($cat) {
        'schema'     => 'bi-diagram-3',
        'pcb'        => 'bi-layers',
        'platformio' => 'bi-code-slash',
        'photo'      => 'bi-image',
        default      => 'bi-file-earmark',
    };
}

function docSafeName(string $original): string {
    $ext  = strtolower(pathinfo($original, PATHINFO_EXTENSION));
    $base = pathinfo($original, PATHINFO_FILENAME);
    $base = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $base);
    $base = trim($base, '_') ?: 'file';
    return date('Ymd_His') . '_' . $base . '.' . $ext;
}

// ── POMOCNÉ ───────────────────────────────────────────────────────────────────

function sanitizeDeviceKey(string $raw): string {
    return strtolower(preg_replace('/[^a-fA-F0-9]/', '', $raw));
}

function slugify(string $input): string {
    $input = strtolower(trim($input));
    $input = preg_replace('/[^a-z0-9\-\.]/', '-', $input);
    $input = preg_replace('/-+/', '-', $input);
    return trim($input, '-');
}

function validateSlug(string $slug): bool {
    return (bool)preg_match('/^[a-z0-9][a-z0-9\-\.]*$/', $slug);
}

function formatMac(string $key): string {
    return implode(':', str_split(strtoupper($key), 2));
}

function formatBytes(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}

function formatUptime(int $seconds): string {
    $d = intdiv($seconds, 86400);
    $h = intdiv($seconds % 86400, 3600);
    $m = intdiv($seconds % 3600, 60);
    if ($d > 0) return "{$d}d {$h}h {$m}m";
    if ($h > 0) return "{$h}h {$m}m";
    return "{$m}m";
}

function jsonResponse(array $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
