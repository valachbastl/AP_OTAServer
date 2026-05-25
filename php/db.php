<?php
function getDB(): PDO {
    static $db = null;
    if ($db !== null) return $db;

    $dir = dirname(DB_PATH);
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $db = new PDO('sqlite:' . DB_PATH);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA journal_mode=WAL');
    $db->exec('PRAGMA foreign_keys=ON');

    initSchema($db);
    return $db;
}

function initSchema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS users (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT UNIQUE NOT NULL,
            full_name    TEXT NOT NULL,
            role         TEXT NOT NULL DEFAULT 'viewer',
            totp_secret  TEXT,
            backup_codes TEXT,
            created_at   DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS groups_list (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            slug       TEXT UNIQUE NOT NULL,
            name       TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS device_types (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            group_slug TEXT NOT NULL,
            slug       TEXT NOT NULL,
            name       TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now', 'localtime')),
            UNIQUE(group_slug, slug)
        );

        CREATE TABLE IF NOT EXISTS components (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_slug  TEXT NOT NULL,
            type_slug   TEXT NOT NULL,
            slug        TEXT NOT NULL,
            name        TEXT NOT NULL,
            created_at  DATETIME DEFAULT (datetime('now', 'localtime')),
            UNIQUE(group_slug, type_slug, slug)
        );

        CREATE TABLE IF NOT EXISTS hw_versions (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            group_slug  TEXT NOT NULL,
            type_slug   TEXT NOT NULL,
            comp_slug   TEXT NOT NULL,
            slug        TEXT NOT NULL,
            created_at  DATETIME DEFAULT (datetime('now', 'localtime')),
            UNIQUE(group_slug, type_slug, comp_slug, slug)
        );

        CREATE TABLE IF NOT EXISTS firmware (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            group_slug    TEXT NOT NULL,
            type_slug     TEXT NOT NULL,
            component_slug TEXT NOT NULL,
            hw_version    TEXT NOT NULL,
            fw_version    TEXT NOT NULL,
            file_path     TEXT NOT NULL,
            file_size     INTEGER NOT NULL DEFAULT 0,
            notes         TEXT,
            uploaded_at   DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS devices (
            id               INTEGER PRIMARY KEY AUTOINCREMENT,
            device_key       TEXT UNIQUE NOT NULL,
            group_slug       TEXT NOT NULL,
            type_slug        TEXT NOT NULL,
            component_slug   TEXT NOT NULL,
            hw_version       TEXT NOT NULL,
            label            TEXT,
            fw_version       TEXT,
            uptime           INTEGER DEFAULT 0,
            last_seen        DATETIME,
            check_interval   INTEGER DEFAULT 300,
            tolerance        INTEGER DEFAULT 120,
            last_download_at     DATETIME,
            last_download_fw     TEXT,
            monitoring_disabled  INTEGER NOT NULL DEFAULT 0,
            created_at           DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS device_events (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            device_key  TEXT NOT NULL,
            event_type  TEXT NOT NULL,
            fw_version  TEXT,
            ip_address  TEXT,
            created_at  DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE TABLE IF NOT EXISTS documents (
            id            INTEGER PRIMARY KEY AUTOINCREMENT,
            entity_type   TEXT NOT NULL,
            group_slug    TEXT,
            type_slug     TEXT,
            comp_slug     TEXT,
            hw_version    TEXT,
            device_key    TEXT,
            category      TEXT NOT NULL DEFAULT 'other',
            original_name TEXT NOT NULL,
            filename      TEXT NOT NULL,
            file_size     INTEGER NOT NULL DEFAULT 0,
            mime_type     TEXT NOT NULL,
            notes         TEXT,
            uploaded_at   DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE INDEX IF NOT EXISTS idx_firmware_lookup
            ON firmware(group_slug, type_slug, component_slug, hw_version, uploaded_at);

        CREATE INDEX IF NOT EXISTS idx_events_device
            ON device_events(device_key, created_at);

        CREATE INDEX IF NOT EXISTS idx_documents_hw
            ON documents(entity_type, group_slug, type_slug, comp_slug, hw_version);

        CREATE INDEX IF NOT EXISTS idx_documents_device
            ON documents(entity_type, device_key);

        CREATE TABLE IF NOT EXISTS login_attempts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            ip_address TEXT NOT NULL,
            created_at DATETIME DEFAULT (datetime('now', 'localtime'))
        );

        CREATE INDEX IF NOT EXISTS idx_login_attempts_ip
            ON login_attempts(ip_address, created_at);
    ");

    // Migrace: přidat sloupec monitoring_disabled (ignoruje chybu, pokud již existuje)
    try { $db->exec("ALTER TABLE devices ADD COLUMN monitoring_disabled INTEGER NOT NULL DEFAULT 0"); } catch (\PDOException $e) {}

    // Výchozí admin uživatel (bez TOTP – spáruje se při prvním spuštění)
    $existing = $db->query("SELECT id FROM users WHERE username='admin'")->fetch();
    if (!$existing) {
        $db->exec("INSERT INTO users (username, full_name, role) VALUES ('admin', 'Administrator', 'admin')");
    }
}
