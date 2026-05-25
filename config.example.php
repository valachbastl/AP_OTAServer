<?php
    // TIMEZONE – nastavit dle lokality serveru (seznam: https://www.php.net/manual/en/timezones.php)
    define('APP_TIMEZONE', 'Europe/Prague');
    date_default_timezone_set(APP_TIMEZONE);

    // PATHS
    define('DB_PATH',       dirname(__FILE__) . '/data/ota.db');
    define('FIRMWARE_DIR',  dirname(__FILE__) . '/data/firmware');
    define('DOCS_DIR',      dirname(__FILE__) . '/data/docs');

    // SESSION
    define('SESSION_NAME',  'ota_session');
    define('SESSION_LIFE',  3600 * 8);

    // LOGS – počet dní uchovávání historie událostí zařízení (0 = nemazat)
    define('EVENT_RETENTION_DAYS', 30);

    // SECURITY
    // APP_SECRET: náhodný 64-znakový řetězec — použit pro OTA autentizaci
    define('APP_SECRET',     'CHANGE_ME_random_64_char_secret_string_here_xxxxxxxxxxxxxxxxxxxxxxxx');
    // OTA_AUTH: true = ESP musí posílat hlavičku X-OTA-Key: <APP_SECRET>
    define('OTA_AUTH',       false);

    // TRUSTED_PROXY: 'none'       = přímé připojení (nebo Cloudflare DNS only – šedý mráček)
    //                'cloudflare' = Cloudflare proxy (oranžový mráček) – čte CF-Connecting-IP
    //                'proxy'      = vlastní reverse proxy (nginx, Traefik…) – čte X-Forwarded-For
    define('TRUSTED_PROXY',  'none');