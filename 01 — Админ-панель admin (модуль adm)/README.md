# Админ-панель `/admin` (модуль `adm`) — архив поставки от 16.11.2026

Эта папка содержит исходную выгрузку модуля `adm` с Google Диска от 16.11.2026 (см. корневой `README.md`).
Файлы здесь **не нужно редактировать напрямую**: часть из них уже разошлась с боевым кодом (в частности `10 — ... (views).js` и `06 — ... (panel).php`).

**Единый актуальный источник правды — [`implementation/`](../implementation/):**
- PHP-контроллеры панели (`AdmApiController.php`, `AdmBaseController.php`): [`implementation/public_html/cms-private/protected/modules/adm/`](../implementation/public_html/cms-private/protected/modules/adm/)
- Шаблон панели (`panel.php`, с `BrandLogo` и актуальной разметкой): [`implementation/public_html/cms-private/protected/modules/adm/views/adm/panel.php`](../implementation/public_html/cms-private/protected/modules/adm/views/adm/panel.php)
- JS панели (`api.js`, `views.js`, `shell.js`): [`implementation/public_html/cms-private/protected/modules/adm/assets/js/`](../implementation/public_html/cms-private/protected/modules/adm/assets/js/)

Примечание по дублям:
- `01.2 — Шаблон страницы/06 — Шаблон страницы панели (panel).php` байт-в-байт совпадает с `07 — Установка приложением - исходники (PWA)/39 — Панель управления - шаблон без кнопки установки (panel).php` (оба — архивный снимок от 16.11.2026).
- Логотип [`implementation/public_html/design/assets/images/logo.png`](../implementation/public_html/design/assets/images/logo.png) является веб-оригиналом; копия в [`launcher/assets/logo.png`](../launcher/assets/logo.png) нужна для `//go:embed` и проверяется на совпадение в CI (`.github/workflows/portal.yml`).
