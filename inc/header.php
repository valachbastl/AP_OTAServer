<?php
// $pageTitle, $activePage must be set before including
$currentUser = currentUser();
$isAdmin     = isAdmin();
$footerYear  = date('Y') > 2026 ? '2026–' . date('Y') : '2026';
?>
<!doctype html>
<html lang="<?= e(currentLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'AP OTA Server') ?> – AP OTA Server</title>
    <link rel="icon" type="image/svg+xml" href="./favicon.svg">

    <link rel="stylesheet" href="./css/bootstrap/5.3.8/bootstrap.min.css">
    <link rel="stylesheet" href="./css/bootstrap-icons/1.13.1/bootstrap-icons.min.css">
    <link rel="stylesheet" href="./css/app.css?v=<?= filemtime(__DIR__ . '/../css/app.css') ?>">

    <script src="./js/color-modes.js?v=<?= filemtime(__DIR__ . '/../js/color-modes.js') ?>"></script>
</head>
<body>

<div class="ota-wrapper">

    <!-- Sidebar overlay (mobile) -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- ── Sidebar ───────────────────────────────────────────────────────── -->
    <nav class="ota-sidebar bg-dark text-white" id="sidebar">

        <a href="index.php?page=dashboard" class="sidebar-brand text-white d-flex align-items-center gap-2">
            <i class="bi bi-cpu-fill text-primary fs-4"></i>
            <div>
                AP OTA Server
                <small>v<?= APP_VERSION ?></small>
            </div>
        </a>

        <div class="px-2 py-2 flex-grow-1">
            <span class="sidebar-section"><?= e(__('nav_overview')) ?></span>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="index.php?page=dashboard"
                       class="nav-link text-white <?= ($activePage ?? '') === 'dashboard' ? 'active' : '' ?>">
                        <i class="bi bi-speedometer2"></i> <?= e(__('nav_dashboard')) ?>
                    </a>
                </li>
            </ul>

            <span class="sidebar-section mt-2"><?= e(__('nav_management')) ?></span>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a href="index.php?page=firmware&tab=fw"
                       class="nav-link text-white <?= ($activePage ?? '') === 'firmware' && ($_GET['tab'] ?? 'fw') === 'fw' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-binary"></i> <?= e(__('nav_firmware')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=firmware&tab=docs"
                       class="nav-link text-white <?= ($activePage ?? '') === 'firmware' && ($_GET['tab'] ?? '') === 'docs' ? 'active' : '' ?>">
                        <i class="bi bi-folder2-open"></i> <?= e(__('nav_docs')) ?>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="index.php?page=devices"
                       class="nav-link text-white <?= ($activePage ?? '') === 'devices' ? 'active' : '' ?>">
                        <i class="bi bi-hdd-network"></i> <?= e(__('nav_devices')) ?>
                    </a>
                </li>
                <?php if ($isAdmin): ?>
                <li class="nav-item">
                    <a href="index.php?page=users"
                       class="nav-link text-white <?= ($activePage ?? '') === 'users' ? 'active' : '' ?>">
                        <i class="bi bi-people"></i> <?= e(__('nav_users')) ?>
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>

        <div class="sidebar-footer">
            <a href="index.php?page=account" class="d-flex align-items-center gap-2 mb-2 text-white text-decoration-none opacity-90-hover">
                <div class="bg-primary rounded-circle d-flex align-items-center justify-content-center flex-shrink-0"
                     style="width:32px;height:32px;font-weight:700;font-size:0.85rem">
                    <?= strtoupper(substr($currentUser['username'], 0, 1)) ?>
                </div>
                <div style="line-height:1.2">
                    <div class="fw-semibold small"><?= e($currentUser['full_name']) ?></div>
                    <div class="opacity-50" style="font-size:0.7rem"><?= e(__('role_' . $currentUser['role'])) ?></div>
                </div>
            </a>
            <a href="index.php?action=logout" class="btn btn-outline-light btn-sm w-100">
                <i class="bi bi-box-arrow-right me-1"></i><?= e(__('nav_logout')) ?>
            </a>
        </div>
    </nav>

    <!-- ── Main ──────────────────────────────────────────────────────────── -->
    <div class="ota-main">

        <!-- Topbar -->
        <header class="ota-topbar bg-body-tertiary border-bottom d-flex align-items-center px-3 gap-3">
            <button class="btn btn-sm btn-outline-secondary d-xl-none" id="sidebarToggle">
                <i class="bi bi-list fs-5"></i>
            </button>
            <span class="page-title"><?= e($pageTitle ?? '') ?></span>
            <div class="ms-auto">
                <?php include dirname(__FILE__) . '/topbar-controls.php'; ?>
            </div>
        </header>

        <!-- Content -->
        <main class="ota-content">
