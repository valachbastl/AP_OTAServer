# AP OTA Server

A simple and secure OTA (Over-The-Air) server for managing ESP device firmware. Built with plain PHP (no frameworks), SQLite database, and Bootstrap 5 UI.

> Released under the [MIT License](LICENSE).

## Features

- **Firmware management** – hierarchical structure: group → device type → component → HW version → firmware; rename and delete at every level; download binaries from the UI
- **Automatic device registration** – ESP registers itself on first check-in (identified by MAC address)
- **Monitoring** – device status overview, uptime, firmware versions, offline detection with configurable interval and tolerance; offline detection can be disabled per device; dashboard auto-refreshes every 30 s
- **Update policy per device** – freeze a device on its current version (disable updates) to test new firmware on selected units only; downgrade to an older version number is blocked by default and can be enabled per device for development
- **Filtering** – filter devices by group and device type in both the dashboard and device list; stat cards reflect the active filter
- **Event history** – log of check-ins, downloads and firmware updates with configurable retention
- **Versioning** – format `YY.M.PATCH` (e.g. `26.6.0`)
- **User management** – admin and viewer roles; admin can add, delete and edit users (name and role)
- **Server clock** – live server time with timezone displayed in the topbar on every page (login included); useful for diagnosing TOTP issues caused by clock drift
- **Security** – TOTP authentication (Google Authenticator), CSRF protection, IP rate limiting, prepared statements
- **Multi-language** – CS, EN, RU, ZH (auto-detected from the `lng/` directory)
- **Dark / light mode** – preference saved per browser; sidebar, background and cards have distinct contrast levels in dark mode

## Requirements

- PHP 8.1+
- SQLite (PDO extension)
- Apache with `mod_rewrite` (HTTPS recommended)

## Installation

1. Copy files to your server
2. Copy `config.example.php` to `config.php`
3. Generate a random 64-character `APP_SECRET` and set it in `config.php`
4. Make the `data/` directory writable
5. Open the app in a browser → the authenticator pairing wizard starts automatically

> **First run:** on a fresh installation the database is created automatically and a single default user `admin` (role: administrator) is seeded **without TOTP**. The first time you open the app, this account waits to be paired — the wizard generates the TOTP secret and backup codes. There is no default password; access is protected solely by the authenticator pairing.

### Directory permissions (Linux / shared hosting)
```bash
chmod 755 data/
chown www-data:www-data data/
```

## Configuration (`config.php`)

| Constant              | Default           | Description |
|-----------------------|-------------------|-------------|
| `APP_SECRET`          | —                 | Random 64-character string (required) |
| `APP_TIMEZONE`        | `'Europe/Prague'` | PHP timezone — affects all timestamps ([list](https://www.php.net/manual/en/timezones.php)) |
| `EVENT_RETENTION_DAYS`| `30`              | Days to keep device event history (0 = keep forever) |
| `OTA_AUTH`            | `false`           | Require `X-OTA-Key` header from ESP devices |
| `TRUSTED_PROXY`       | `'none'`          | `'none'` / `'cloudflare'` / `'proxy'` |

### TRUSTED_PROXY
- `'none'` – direct connection or Cloudflare DNS only (grey cloud)
- `'cloudflare'` – Cloudflare proxy (orange cloud), reads `CF-Connecting-IP`
- `'proxy'` – custom reverse proxy (nginx, Traefik…), reads `X-Forwarded-For`

## OTA endpoint for ESP

```
GET /ota.php?group=home&type=control-unit&component=board&hw=1.0
            &device=aabbccddeeff&fw=26.6.0&uptime=3600&interval=3600
```

| Parameter   | Description                      | Example        |
|-------------|----------------------------------|----------------|
| `group`     | Device group                     | `home`         |
| `type`      | Device type                      | `control-unit` |
| `component` | Component (chip)                 | `board`        |
| `hw`        | Hardware version                 | `1.0`          |
| `device`    | MAC address (no separators)      | `aabbccddeeff` |
| `fw`        | Current firmware version         | `26.6.0`       |
| `uptime`    | Uptime in seconds                | `3600`         |
| `interval`  | Check-in interval in seconds     | `3600`         |

**Responses:**
- `200` plain text → latest version number (ESP decides whether to download)
- `200` binary → with `&download=1`, firmware binary
- `403` → invalid `X-OTA-Key` (when `OTA_AUTH = true`)
- `404` → no firmware found for this combination, or no update available (device frozen / would be a downgrade)

> When updates are disabled for a device, or the latest firmware would be a downgrade and downgrade is not allowed, the endpoint returns the device's **own current version** in step 1 (so the ESP sees no difference and skips the download) and `404` on a forced `&download=1`.

### ESP-IDF / PlatformIO

Use [AP_OTAUpdater](https://github.com/valachbastl/AP_OTAUpdater) — see its README for usage and examples.

## Project structure

```
AP_OTAServer/
├── config.php              # Configuration (not in git)
├── config.example.php      # Example configuration
├── index.php               # Router
├── ota.php                 # ESP OTA endpoint
├── php/
│   ├── db.php              # SQLite init and schema
│   ├── functions.php       # Shared functions
│   └── totp.php            # TOTP implementation
├── inc/
│   ├── header.php          # HTML header + sidebar
│   ├── footer.php          # HTML footer
│   ├── iLogin.php          # Login page
│   ├── iSetup.php          # First run – authenticator pairing
│   ├── iDashboard.php      # Dashboard
│   ├── iFirmware.php       # Firmware management
│   ├── iDevices.php        # Device management
│   ├── iUsers.php          # User management
│   ├── iAccount.php        # Account settings
│   ├── color-modes.php     # Theme switcher
│   ├── lang-switcher.php   # Language switcher
│   └── topbar-controls.php # Topbar controls
├── ajax/
│   ├── api.php               # AJAX API
│   ├── dashboard_data.php    # JSON endpoint for live dashboard refresh
│   ├── upload_firmware.php   # Firmware upload
│   ├── upload_doc.php        # Document upload
│   ├── download_firmware.php # Firmware binary download
│   └── download_doc.php      # Document download
├── lng/
│   ├── cs.php              # Czech
│   ├── en.php              # English
│   ├── ru.php              # Russian
│   └── zh.php              # Chinese (Simplified)
├── css/                    # Bootstrap 5.3.8 + Bootstrap Icons 1.13.1 + custom styles
├── js/                     # Bootstrap JS + custom scripts
├── fonts/                  # MiSans Latin
└── data/                   # SQLite database + firmware + documents (not in git)
```

## Author

Petr Adámek

## License

Released under the [MIT License](LICENSE). © 2026 Petr Adámek
