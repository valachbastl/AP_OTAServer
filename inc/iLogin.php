<?php
$error = '';
$step  = 'username';
$ip    = clientIp();

if (isset($_GET['cancel_login'])) {
    unset($_SESSION['login_username']);
    header('Location: index.php');
    exit;
}

if (isLoginBlocked($ip)) {
    $error = __('login_err_rate_limit');
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username    = trim($_POST['username'] ?? '');
    $code        = preg_replace('/\D/', '', $_POST['code'] ?? '');
    $backupIn    = trim($_POST['backup_code'] ?? '');
    $resetBackup = trim($_POST['reset_backup'] ?? '');

    if (!empty($username) && empty($code) && empty($backupIn) && empty($resetBackup)) {
        $user = getUserByUsername($username);
        if ($user && !empty($user['totp_secret'])) {
            $_SESSION['login_username'] = $username;
            $step = 'code';
        } elseif ($user) {
            $_SESSION['setup_username'] = $username;
            header('Location: index.php');
            exit;
        } else {
            recordLoginFailure($ip);
            $error = __('login_err_not_found');
        }
    } elseif (!empty($_SESSION['login_username'])) {
        $user = getUserByUsername($_SESSION['login_username']);

        if (!empty($resetBackup) && $user) {
            if (consumeBackupCode($user, $resetBackup)) {
                resetUserTotp($user['id']);
                unset($_SESSION['login_username']);
                clearLoginAttempts($ip);
                $_SESSION['setup_username'] = $user['username'];
                header('Location: index.php');
                exit;
            } else {
                recordLoginFailure($ip);
                $error = __('login_err_wrong_backup');
                $step  = 'code';
            }
        } else {
            $ok = false;
            if (!empty($code) && $user) {
                $ok = TOTP::verify($user['totp_secret'], $code);
            } elseif (!empty($backupIn) && $user) {
                $ok = consumeBackupCode($user, $backupIn);
            }

            if ($ok) {
                unset($_SESSION['login_username']);
                clearLoginAttempts($ip);
                sessionLogin($user);
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                recordLoginFailure($ip);
                $error = __('login_err_wrong_code');
                $step  = 'code';
            }
        }
    }
} elseif (!empty($_SESSION['login_username'])) {
    $step = 'code';
}
?>
<!doctype html>
<html lang="<?= e(currentLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('login_title')) ?> – AP OTA Server</title>
    <link rel="icon" type="image/svg+xml" href="./favicon.svg">
    <link rel="stylesheet" href="./css/bootstrap/5.3.8/bootstrap.min.css">
    <link rel="stylesheet" href="./css/bootstrap-icons/1.13.1/bootstrap-icons.min.css">
    <link rel="stylesheet" href="./css/app.css?v=<?= filemtime(__DIR__ . '/../css/app.css') ?>">
    <script src="./js/color-modes.js?v=<?= filemtime(__DIR__ . '/../js/color-modes.js') ?>"></script>
</head>
<body class="bg-body-tertiary d-flex flex-column auth-page">

<header class="ota-topbar bg-body-tertiary border-bottom d-flex align-items-center justify-content-end px-3">
    <?php include dirname(__FILE__) . '/topbar-controls.php'; ?>
</header>

<div class="auth-wrapper p-3 flex-grow-1">
    <div class="auth-card card shadow-lg p-4">

        <div class="text-center mb-4">
            <div class="auth-logo mb-1">
                <i class="bi bi-cpu-fill text-primary"></i> AP <span>OTA</span>
            </div>
            <p class="text-muted small mb-0"><?= e(__('login_subtitle')) ?></p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="bi bi-x-circle me-1"></i><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($step === 'username'): ?>

            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label small fw-semibold text-muted"><?= e(__('login_username')) ?></label>
                    <input type="text" id="username" name="username" class="form-control"
                           autocomplete="username" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <?= e(__('login_continue')) ?> <i class="bi bi-arrow-right ms-1"></i>
                </button>
            </form>

        <?php else: ?>

            <div class="alert alert-info py-2 small d-flex align-items-center gap-2">
                <i class="bi bi-person-circle"></i>
                <?= e(__('login_signing_as')) ?> <strong><?= e($_SESSION['login_username'] ?? '') ?></strong>
                <a href="index.php?cancel_login=1" class="ms-auto text-muted" title="<?= e(__('login_change_user')) ?>"><i class="bi bi-x"></i></a>
            </div>

            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabTotp">
                        <i class="bi bi-phone me-1"></i><?= e(__('login_tab_totp')) ?>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabBackup">
                        <i class="bi bi-key me-1"></i><?= e(__('login_tab_backup')) ?>
                    </button>
                </li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabTotp">
                    <form method="POST">
                        <input type="hidden" name="username" value="<?= e($_SESSION['login_username'] ?? '') ?>">
                        <div class="mb-3">
                            <label for="code" class="form-label small fw-semibold text-muted"><?= e(__('login_totp_label')) ?></label>
                            <input type="text" id="code" name="code"
                                   class="form-control form-control-lg text-center fw-bold"
                                   maxlength="6" pattern="\d{6}" inputmode="numeric"
                                   autocomplete="one-time-code" placeholder="000000" autofocus required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-shield-check me-1"></i><?= e(__('login_btn_signin')) ?>
                        </button>
                    </form>
                </div>
                <div class="tab-pane fade" id="tabBackup">
                    <form method="POST">
                        <input type="hidden" name="username" value="<?= e($_SESSION['login_username'] ?? '') ?>">
                        <div class="mb-3">
                            <label for="backup_code" class="form-label small fw-semibold text-muted"><?= e(__('login_backup_label')) ?></label>
                            <input type="text" id="backup_code" name="backup_code"
                                   class="form-control text-center fw-mono"
                                   autocomplete="off" placeholder="XXXX-XXXX" required>
                            <div class="form-text"><?= e(__('login_backup_hint')) ?></div>
                        </div>
                        <button type="submit" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-key me-1"></i><?= e(__('login_btn_backup')) ?>
                        </button>
                    </form>
                </div>
            </div>

        <?php endif; ?>

    </div>
</div>

<script src="./js/bootstrap/5.3.8/bootstrap.bundle.min.js"></script>
</body>
</html>
