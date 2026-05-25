<?php
define('APP_NAME',    'AP OTA Server');
define('APP_VERSION', '1.0.0');

require_once dirname(__FILE__) . '/config.php';
require_once dirname(__FILE__) . '/php/functions.php';

header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

startSecureSession();

// ── Logout ────────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'logout') {
    sessionLogout();
    header('Location: index.php');
    exit;
}

// ── Inicializace DB + schema ──────────────────────────────────────────────────
$db = getDB();

// ── Bootstrap: admin bez TOTP musí projít setup jako první ───────────────────
$admin = getUserByUsername('admin');
if ($admin && empty($admin['totp_secret'])) {
    $_SESSION['setup_username'] = 'admin';
}

// ── Setup flow: uživatel identifikován, ale chybí mu TOTP ────────────────────
if (!empty($_SESSION['setup_username'])) {
    $setupUser = getUserByUsername($_SESSION['setup_username']);
    if ($setupUser && empty($setupUser['totp_secret'])) {
        include dirname(__FILE__) . '/inc/iSetup.php';
        exit;
    }
    unset($_SESSION['setup_username']);
}

// ── Přihlašovací stránka ──────────────────────────────────────────────────────
if (!isLoggedIn()) {
    include dirname(__FILE__) . '/inc/iLogin.php';
    exit;
}

// ── Chráněné stránky ──────────────────────────────────────────────────────────
$page    = $_GET['page'] ?? 'dashboard';
$allowed = ['dashboard', 'firmware', 'devices', 'account'];
if (isAdmin()) $allowed[] = 'users';

if (!in_array($page, $allowed)) $page = 'dashboard';

include dirname(__FILE__) . '/inc/i' . ucfirst($page) . '.php';
