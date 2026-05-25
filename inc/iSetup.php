<?php
$error = '';
$step  = 'pair';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['step'] ?? '') === 'pair') {
    $secret = $_POST['secret']  ?? '';
    $code   = preg_replace('/\D/', '', $_POST['code'] ?? '');

    if (TOTP::verify($secret, $code)) {
        $backup = TOTP::generateBackupCodes(8);
        saveUserTotp($setupUser['id'], $secret, $backup['hashed']);
        unset($_SESSION['setup_username']);
        sessionLogin($setupUser);
        $_SESSION['setup_backup_plain'] = $backup['plain'];
        $step = 'backup';
    } else {
        $error = __('setup_err_code');
    }
}

if ($step === 'backup' && !empty($_SESSION['setup_backup_plain'])) {
    $backupCodes = $_SESSION['setup_backup_plain'];
    unset($_SESSION['setup_backup_plain']);
}

if ($step === 'pair') {
    $secret = TOTP::generateSecret();
    $otpUri = TOTP::getOtpAuthUri($secret, $setupUser['username'], APP_NAME);
}
?>
<!doctype html>
<html lang="<?= e(currentLang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(__('setup_title')) ?> – AP OTA Server</title>
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

<div class="auth-wrapper p-3 flex-grow-1" style="min-height:0">
    <div class="auth-card card shadow-lg p-4">

        <div class="text-center mb-4">
            <div class="auth-logo mb-1">
                <i class="bi bi-cpu-fill text-primary"></i> AP <span>OTA</span>
            </div>
            <p class="text-muted small mb-0"><?= e(__('setup_subtitle')) ?></p>
        </div>

        <?php if ($step === 'backup' && !empty($backupCodes)): ?>

            <div class="alert alert-warning d-flex gap-2 align-items-start">
                <i class="bi bi-exclamation-triangle-fill flex-shrink-0 mt-1"></i>
                <div>
                    <strong><?= e(__('setup_backup_title')) ?></strong><br>
                    <?= e(__('setup_backup_body')) ?>
                </div>
            </div>

            <div class="backup-codes-grid mb-4">
                <?php foreach ($backupCodes as $code): ?>
                    <div class="backup-code text-center"><?= e($code) ?></div>
                <?php endforeach; ?>
            </div>

            <a href="index.php?page=dashboard" class="btn btn-primary w-100">
                <i class="bi bi-check-circle me-1"></i><?= e(__('setup_backup_done')) ?>
            </a>

        <?php else: ?>

            <ol class="text-muted small mb-3 ps-3">
                <li><?= __('setup_step1') ?></li>
                <li><?= __('setup_step2') ?></li>
                <li><?= __('setup_step3') ?></li>
            </ol>

            <div class="text-center mb-3">
                <div id="qrcode" class="d-inline-block p-2 bg-white rounded"></div>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-semibold text-muted"><?= e(__('setup_secret_label')) ?></label>
                <div class="totp-secret d-flex align-items-center gap-2">
                    <span class="flex-grow-1 user-select-all"><?= e($secret) ?></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary flex-shrink-0"
                            onclick="navigator.clipboard.writeText('<?= e($secret) ?>');this.innerHTML='<i class=\'bi bi-check\'></i>';setTimeout(()=>this.innerHTML='<i class=\'bi bi-clipboard\'></i>',1500)">
                        <i class="bi bi-clipboard"></i>
                    </button>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger py-2 small"><i class="bi bi-x-circle me-1"></i><?= e($error) ?></div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="step" value="pair">
                <input type="hidden" name="secret" value="<?= e($secret) ?>">
                <div class="mb-3">
                    <label for="code" class="form-label small fw-semibold text-muted"><?= e(__('setup_code_label')) ?></label>
                    <input type="text" id="code" name="code"
                           class="form-control form-control-lg text-center fw-bold"
                           maxlength="6" pattern="\d{6}" inputmode="numeric"
                           autocomplete="one-time-code" placeholder="000000" autofocus required>
                </div>
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-shield-lock me-1"></i><?= e(__('setup_btn_verify')) ?>
                </button>
            </form>

        <?php endif; ?>

    </div>
</div>

<script src="./js/bootstrap/5.3.8/bootstrap.bundle.min.js"></script>
<?php if ($step === 'pair'): ?>
<script src="./js/qrcode.min.js"></script>
<script>
(function() {
    var qr = qrcode(0, 'M');
    qr.addData(<?= json_encode($otpUri) ?>);
    qr.make();
    document.getElementById('qrcode').innerHTML = qr.createSvgTag(4, 4);
    document.getElementById('code').focus();
})();
</script>
<?php endif; ?>
</body>
</html>
