# Changelog

## [1.1.0] - 2026-06-16

### Politika aktualizací per zařízení
- **Zakázat aktualizace** per zařízení — zmrazení na aktuální verzi i když je k dispozici novější firmware; ESP při kontrole dostane vlastní verzi a nestahuje. Umožňuje otestovat nový FW jen na vybraných kusech, než se nasadí do celého parku.
- **Zákaz downgrade** (výchozí stav) — server nenabídne firmware se starším číslem verze, než zařízení právě běží; porovnání přes `version_compare`. Per zařízení lze povolit pro vývoj a testování (přepínač „Povolit downgrade").
- Obě omezení se vynucují i v kroku stažení (`&download=1`), nelze je obejít.
- Migrace: nové sloupce `updates_disabled` a `allow_downgrade` se automaticky přidají i do stávající databáze.

### Licence
- Projekt vydán pod licencí MIT (`LICENSE`).

## [1.0.0] - 2026-05-28

První veřejné vydání.

### Správa firmware
- Hierarchická struktura: skupina → typ zařízení → komponenta → HW verze → firmware
- Upload `.bin` souborů s automatickým rozpoznáním verze ze souboru
- Stažení firmware binárky přímo z UI (pro všechny přihlášené uživatele)
- Přejmenování skupin, typů zařízení a komponent
- Smazání celé větve včetně všech podřízených dat
- Verzování ve formátu `YY.M.PATCH` (např. `26.5.0`)
- Správa dokumentace (PDF, PNG, JPG, ZIP, KiCad) per HW verze

### OTA endpoint
- Dvoukrokový protokol: GET verze → porovnání → GET `&download=1`
- Automatická registrace zařízení při prvním volání (identifikace přes MAC adresu)
- Volitelná autentizace ESP zařízení přes `X-OTA-Key` hlavičku (`OTA_AUTH`)

### Monitoring zařízení
- Přehled stavu: aktuální, čeká na aktualizaci, instaluje, offline, nikdy nepřihlášeno
- Detekce offline zařízení podle konfigurovatelného intervalu a tolerance per zařízení
- Volitelné vypnutí offline detekce per zařízení — pro zařízení s manuální nebo nepravidelnou kontrolou
- Historie checkinů, stahování a instalací s konfigurovatelnou retencí (`EVENT_RETENTION_DAYS`)
- Editace štítků a nastavení monitorování per zařízení

### Bezpečnost
- TOTP dvoufaktorová autentizace (kompatibilní s Google Authenticator)
- CSRF ochrana na všech formulářích a AJAX voláních
- IP-based rate limiting přihlášení (5 pokusů / 15 minut, SQLite backend)
- Prepared statements proti SQL injection
- `SameSite=Strict; HttpOnly` session cookie, `Secure` flag s detekcí reverse proxy
- HSTS, CSP, `X-Robots-Tag` hlavičky přes `.htaccess`
- Blokování přímého přístupu do `inc/`, `php/`, `lng/`

### Konfigurace
- `TRUSTED_PROXY`: `'none'` / `'cloudflare'` / `'proxy'` pro správné získání IP klienta
- `APP_TIMEZONE`: PHP timezone pro správné zobrazení a ukládání časových razítek

### Uživatelé
- Role správce a pozorovatel; správce spravuje uživatele, firmware a zařízení; pozorovatel spravuje jen vlastní TOTP
- Správce může přidávat, mazat a editovat jméno a roli libovolného uživatele
- Reset TOTP párování per uživatel

### UI
- Bootstrap 5.3 + Bootstrap Icons + MiSans Latin font
- Tmavý / světlý / automatický režim s odlišeným kontrastem sidebaru, pozadí a karet
- Serverový čas v topbaru (HH:MM:SS + timezone) s živým JS tickerem — viditelné na přihlašovací i přihlášené stránce; ikona a TZ zkratka se na mobilu skryjí
- Responzivní — sidebar se skryje na mobilech, tabulky přizpůsobují viditelné sloupce a zkracují texty pod `sm`
- Klikatelné řádky tabulky — celý řádek naviguje na detail zařízení nebo otevírá edit modal
- Tlačítko Zpět v detailu zařízení se vrací na správnou stránku podle kontextu (dashboard nebo seznam zařízení)
- Přepínač jazyka: CS, EN, RU, ZH
- Dashboard se automaticky obnovuje každých 30 s (stat karty, stavy zařízení, verze FW, uptime, last seen) bez reloadu stránky
- Filtry podle skupiny a typu zařízení v seznamu zařízení i na dashboardu; stat karty reagují na aktivní filtr
