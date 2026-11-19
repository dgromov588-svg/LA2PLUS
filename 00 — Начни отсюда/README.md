# Сверка архива «00 — Начни отсюда» с `implementation/`

В [`00 — Где что лежит и что исправлено (манифест).md`](00%20%E2%80%94%20%D0%93%D0%B4%D0%B5%20%D1%87%D1%82%D0%BE%20%D0%BB%D0%B5%D0%B6%D0%B8%D1%82%20%D0%B8%20%D1%87%D1%82%D0%BE%20%D0%B8%D1%81%D0%BF%D1%80%D0%B0%D0%B2%D0%BB%D0%B5%D0%BD%D0%BE%20%28%D0%BC%D0%B0%D0%BD%D0%B8%D1%84%D0%B5%D1%81%D1%82%29.md) и [`08 — Шлюзы NOWPayments и Trybit/00 — Шлюзы и страница пополнения - что выложено.md`](../08%20%E2%80%94%20%D0%A8%D0%BB%D1%8E%D0%B7%D1%8B%20NOWPayments%20%D0%B8%20Trybit/00%20%E2%80%94%20%D0%A8%D0%BB%D1%8E%D0%B7%D1%8B%20%D0%B8%20%D1%81%D1%82%D1%80%D0%B0%D0%BD%D0%B8%D1%86%D0%B0%20%D0%BF%D0%BE%D0%BF%D0%BE%D0%BB%D0%BD%D0%B5%D0%BD%D0%B8%D1%8F%20-%20%D1%87%D1%82%D0%BE%20%D0%B2%D1%8B%D0%BB%D0%BE%D0%B6%D0%B5%D0%BD%D0%BE.md) перечислены файлы, которые ранее не прошли фильтр Google Диска (см. корневой `README.md`, п. 3).

Сверка с текущим overlay `implementation/` (проверено 19.11.2026):

| Отсутствовало на Диске | Статус в репозитории (`implementation/`) |
|---|---|
| `AdmBaseController.php` (`05a`) | Восстановлен: `implementation/public_html/cms-private/protected/modules/adm/AdmBaseController.php` |
| `GatewayController.php` (`44`) | Восстановлен: `implementation/public_html/cms-private/protected/modules/deposit/controllers/GatewayController.php` |
| `cabinet/deposit/index.php` (`53`) | Восстановлен: `implementation/public_html/cms-private/themes/la2plus/views/cabinet/deposit/index.php` |
| `landing.php` (`36`) | Восстановлен: `implementation/public_html/cms-private/themes/la2plus/views/layouts/landing.php` |
| 5 вьюх кабинета | Заменены объединёнными шаблонами `views/cabinet/portal.php`, `portal-pages.php`, `portal-error.php`, `views/layouts/portal.php`, `auth.php` |
| `tests/php/run.php`, `bootstrap.php` | Заменены на автономный стенд `implementation/tests/` (15 файлов) и CI `.github/workflows/portal.yml` |
| `PaymentsSetupController.php` (`47`) | Не хранится в Git намеренно (`tools/sync_live.py` исключает `PaymentsSetup*` как модуль с реквизитами шлюзов) |
| `AnalyticsService.php`, `AnalyticsController.php`, миграция ролей, `admin.php`, `master.php`, файл `52` | Остаются на сервере вне overlay `implementation/` (не затрагиваются текущим пакетом деплоя) |
