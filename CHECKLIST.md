# Чек-лист по репозиторию LA2PLUS

> Дата проверки: **19 ноября 2026** · Ветка: `arena/f2d897d8-la2plus` (от `main` @ `df1564e`) · Рабочее дерево чистое
> Репозиторий: `dgromov588-svg/LA2PLUS` (публичный) · Файлов в Git: **206** · PHP: 94 · Go: 18 · Markdown: 19
> Как пользоваться: `- [x]` — выполнено, `- [ ]` — нужно сделать. Приоритеты: **P0** — критично, **P1** — важно, **P2** — желательно.

---

## 1. Git и GitHub

- [x] Рабочее дерево чистое, ветка `arena/f2d897d8-la2plus` синхронизирована с `main` (`df1564e`, merge PR #4)
- [x] История PR: #1 импорт с Google Диска, #2 кабинет/биржа/рейтинг, #3 кабинет+лаунчер+wiki, #4 cabinet-market-rating — все MERGED
- [x] Remote `origin` настроен, `main` — ветка по умолчанию
- [x] **P1** Удалены устаревшие merged-ветки на `origin` (`feat/cabinet-market-rating`, `arena/379e23e4-la2plus` — у обеих `ahead_by: 0`); на `origin` остались только `main` и рабочая `arena/f2d897d8-la2plus`
- [ ] **P1** Включить branch protection на `main` и `delete_branch_on_merge: true` (API интеграции вернул `403 Resource not accessible by integration` — нужно включить владельцу в Settings GitHub)
- [x] **P2** Dependabot включён: `.github/dependabot.yml` (gomod `/launcher`, github-actions)
- [x] **P2** Добавлены `.github/CODEOWNERS`, шаблоны Issue/PR (`.github/ISSUE_TEMPLATE/`, `PULL_REQUEST_TEMPLATE.md`)
- [x] **P2** Создан первый GitHub Release [`v1.2.0`](https://github.com/dgromov588-svg/LA2PLUS/releases/tag/v1.2.0) на `main` (`df1564e`) с описанием, SHA-256 и ссылками на установщик и сборку `44533258228` (владелец может прикрепить `.exe` прямо к релизу при желании)

## 2. Структура репозитория

- [x] Корень понятный: `README.md`, `AGENTS.md`, `.gitignore`, `docs/`, `implementation/`, `launcher/`, `tools/`, `.github/`
- [x] Нумерованные папки `00–08` — архив поставки с Google Диска, описаны в `README.md` (60 файлов, MD5 60/60)
- [x] Актуальный код отделён от архива: `implementation/public_html/` (overlay на PHP/Yii CMS) + `launcher/` (Go)
- [x] **P1** Папка `04 — Тесты (PHP-стенд)/` — добавлен `README.md` со ссылкой на актуальные `implementation/tests/` (папка оставлена как архив)
- [x] **P1** Разграничены дубли и зафиксирован единый источник правды: `implementation/` — канон для `views.js`, `panel.php` и портала; добавлен `01 — Админ-панель…/README.md`, в CI (`portal.yml`) добавлена проверка байтового равенства `logo.png` (`implementation/…` vs `launcher/assets/logo.png` для `//go:embed`)
- [x] **P2** Проверено именование: 0 не-ASCII путей и пробелов в активных каталогах (`implementation/`, `launcher/`, `tools/`, `.github/`, `docs/`); проверка добавлена в `portal.yml` и закреплена в `CONTRIBUTING.md` (`00–08` оставлены нетронутыми ради ссылок)
- [x] **P2** Оценены скриншоты и размер репозитория: весь `.git` pack весит **8.26 MiB** (`06 — Скриншоты 390×844/` = 5.1 MiB) — перенос в Git LFS сейчас нецелесообразен (переписывать историю ради 8 МиБ не нужно; вернуться к вопросу при росте >50–100 МБ)

## 3. Документация

- [x] `README.md` — структура, примечания по переносу с Диска, ссылка на `docs/CONTINUATION.md`
- [x] `AGENTS.md` — правила бренда, кабинета, PIN, навигации (обязательны к соблюдению)
- [x] `docs/CONTINUATION.md` — актуальное состояние, хостинг, процедура публикации лаунчера, откат на 1.1.1
- [x] `implementation/README.md`, `BRAND.md`, `CABINET-SECTIONS.md`, `COIN-SHOP.md`, `GAME-BRIDGE.md`, `PAYMENTS.md`
- [x] `launcher/README.md` — сборка, публикация, проверки, publisher
- [x] `docs/verification/*.json` — 4 отчёта проверок (кабинет 97+54+27, wiki 30, лаунчер full-release, installer 1.2.0)
- [x] **P1** Добавлен `LICENSE` (proprietary, all rights reserved)
- [x] **P1** Добавлены `SECURITY.md` и `CONTRIBUTING.md`
- [x] **P2** Добавлен `CHANGELOG.md` (1.2.0, игровой выпуск 2026.11.18.1, кабинет, wiki)
- [x] **P2** `docs/CONTINUATION.md` обновлён: ветка `main`, PR #4, ссылки на `PRIORITIES.md`/`CHECKLIST.md`

## 4. PHP / Кабинет (`implementation/`)

- [x] 72 PHP-файла в `implementation/`: компоненты (26), модули `adm`/`cabinet`/`deposit`/`login`/`register`, тема `la2plus`
- [x] Бренд: `BrandLogo::html()` + `BrandLogo::STYLES`, канон. логотип `design/assets/images/logo.png`, 3:1, reduced-motion
- [x] Разделы кабинета через `CabinetSections`/`CabinetNavigation`, 12 переключателей владельца, серверная блокировка отключённых разделов
- [x] Соблюдены запреты `AGENTS.md`: нет PIN, нет карточки скидки, нет Android/iOS-промптов, нет `target="_blank"` для навигации сайта, левая боковая панель + мобильное меню
- [x] `release-manifest.json` + `tools/build_portal_release.py` — воспроизводимая сборка overlay (`la2plus-portal.zip` + SHA-256), без контакта с хостингом
- [x] Стейджинг изолирован: `implementation/staging/` (отдельные БД, `StagingGuard`/`StagingConfig`, демо-флаги выключены в production)
- [x] **P0** `php -l` по всем PHP в `implementation/` и все 9 автономных sqlite-тестов пройдены на PHP 8.3 в CI (`Portal (PHP)` run `44605512093`, отчёт `docs/verification/portal-ci-2026-11-19.json`)
- [ ] **P0** Прогнать браузерные Playwright-тесты (`ui.cjs`, `rating-ui.cjs` локально с PHP-стендом и `staging-e2e.cjs` на `new.la2plus.com`) и при необходимости локальный прогон на PHP 8.4 (`tools/php-test.sh`)
- [x] **P1** `tools/php-test.sh` улучшен: fallback на системный `php` + понятная ошибка с инструкцией по установке
- [x] **P1** Добавлен CI `.github/workflows/portal.yml`: `php -l`, sqlite-тесты, `node --check`, проверка `release-manifest.json`
- [x] **P1** Добавлен `.editorconfig` (UTF-8, LF, trim); статический анализ (`phpstan`) пока не добавлялся (overlay на Yii1 требует стабов фреймворка)
- [x] **P2** Проведена сверка отсутствовавших с Диска файлов (`README.md` п.3 / `00 — Где что лежит…`) с `implementation/`: `AdmBaseController.php`, `GatewayController.php`, `deposit/index.php`, `landing.php` восстановлены в `implementation/`; `PaymentsSetupController.php` намеренно исключён (`tools/sync_live.py`) — сводка в `00 — Начни отсюда/README.md`

## 5. Лаунчер (`launcher/`, Go)

- [x] Go-модуль `la2plus/launcher`, зависимости зафиксированы в `go.mod`/`go.sum`, Go 1.27.4 + NSIS 3.11 в CI
- [x] Подпись Ed25519, SHA-256 каждого файла, HTTP Range с докачкой, ретраи 408/425/429/5xx, блоки 8 МиБ, 2 потока, `Retry-After`
- [x] Полная проверка 1.2.0: 4113 файлов, 12 636 449 582 байт, 10 инжектированных обрывов, 1247 сек — `docs/verification/launcher-full-release-2026-11-19.json`
- [x] Установщик 1.2.0: SHA-256 `61f9ebcb…88435e`, 5 860 664 байт, stable-ссылка `/downloads/LA2PLUS-Setup.exe` обновлена
- [x] CI зелёный: `go test -race ./internal/...`, GUI smoke (96/120/144/192 DPI), установка/удаление, `fullcheck` по `[full-release]`/dispatch
- [ ] **P1** Установщик без Authenticode-подписи (отмечено в `launcher/README.md`) — нужен сертификат владельца; Ed25519 не заменяет подпись издателя Windows
- [ ] **P1** Реальный вход персонажа в игру не проверялся (только `smoke`/`fullcheck` без запуска игры) — ручной E2E на игровом сервере
- [x] **P2** Проверено: `launcher/dist/` в `.gitignore`, `launcher/cmd/notices/main.go` генерирует `ThirdPartyNotices.txt` в CI, а `launcher/installer/setup.nsi` включает его в установщик и удаляет при деинсталляции

## 6. Тесты

- [x] Go: `manifest_test.go`, `engine_test.go`, `network_test.go`, `locked_windows_test.go` — гоняются в CI с `-race`
- [x] PHP/Node: `implementation/tests/` — `domain.php`, `sections.php`, `attendance.php`, `coin-purchase.php`, `concurrency.php`, `rankings.php`, `wayforpay.php`, `production.php`, `staging-safety.php`, `ui.cjs`, `rating-ui.cjs`, `staging-e2e.cjs`
- [x] Отчёты проверок сохранены в `docs/verification/` (см. п.3)
- [x] **P0** Автономные PHP-тесты (9 sqlite) + `php -l` + `node --check` пройдены в CI на PHP 8.3 (`Portal (PHP)` `44605512093`, `docs/verification/portal-ci-2026-11-19.json`)
- [ ] **P0** Прогнать браузерные Playwright-тесты (`ui.cjs`, `rating-ui.cjs`, `staging-e2e.cjs`) в окружении с Playwright/Chromium и доступом к стенду/стейджингу
- [x] **P1** PHP/Node-тесты подключены к CI (`.github/workflows/portal.yml`: `php -l`, 9 sqlite-тестов, `node --check`; браузерные Playwright `ui.cjs`/`rating-ui.cjs`/`staging-e2e.cjs` запускаются вручную/на стейджинге)
- [x] **P2** Покрытие платёжных компонентов: `PortalPayments` (`domain.php`), `PortalTrybit` и `PortalCoinPayments` (`production.php`), `PortalWayforpay` (`wayforpay.php`); реальное списание остаётся вне автотестов

## 7. CI/CD и деплой

- [x] `.github/workflows/windows-launcher.yml` — push на `launcher/**`, `workflow_dispatch(full_release)`, триггер `[full-release]` в коммите
- [x] `tools/build_portal_release.py` — локальная сборка overlay, `tools/sync_live.py` — сверка с живым сайтом
- [x] Процедура публикации задокументирована: новый ZIP → publisher → подписанный `stable.json` → атомарная замена `LA2PLUS-Setup.exe` + `.sha256`, старые архивы не перезаписывать
- [x] **P1** Добавлен workflow `portal.yml` (линт + тесты + проверка манифеста)
- [x] **P1** Проверка `release-manifest.json` добавлена в `portal.yml` (`build_portal_release.py` + `git diff --exit-code`)
- [x] **P2** `05 — Деплой и сборка/42 — Выкладка на хостинг (deploy-hosting).sh` проверен (19.11.2026): секреты читаются только из env (`FTP_*`, `SSH_*`, `CPANEL_*`), захардкоженных паролей нет; не коммитить `.env`

## 8. Безопасность и секреты

- [x] Скан 19.11.2026: захардкоженных секретов не найдено — ключи только из env/настроек (`LA2PLUS_TRYBIT_*`, `LA2PLUS_GAME_BRIDGE_SECRET`, `nowpayments.api_key`/`ipn_secret`)
- [x] Ключ подписи Ed25519 только на хостинге вне webroot (`/home/laplusco/launcher-private/signing-seed.txt`), в Git его нет
- [x] `.gitignore` покрывает `work/`, `implementation/baseline/`, `implementation/test-output/`, `*.local.json`, `launcher/dist/`, `*.syso`
- [x] CSRF, проверка почты, подтверждение пароля/кода, rate-limit — в коде кабинета; стейджинг принудительно отключает платежи/SMTP
- [x] **P1** CI-сканер секретов `.github/workflows/security.yml` (`gitleaks` v8.30.1 с `fetch-depth: 0`) добавлен и прошёл на GitHub Actions (run `44605512129`, 0 утечек) + локальный скан 206 файлов
- [ ] **P1** Включить нативные GitHub Secret Scanning / Push Protection в Settings → Code security (`security_and_analysis: null`; PATCH API вернул `403 Resource not accessible by integration`)
- [x] **P2** Проверены `.htaccess`: `08/.../51 — Правила доступа (htaccess).txt` закрывает `cms-private/`, скрытые файлы и `.php` кроме `index.php`/`/deposit-*.php`; `implementation/staging/public.htaccess` дополнительно блокирует `error_log|ini|log|bak|sql|sqlite|db|env|jsonl` и ставит security-заголовки (на production важно оставить доступным `launcher/stable.json`)

## 9. Платежи и интеграции

- [x] NOWPayments (`Nowpayments.php`, HMAC-SHA512 IPN), Trybit (`PortalTrybit.php`), Wayforpay (`PortalWayforpay.php` + webhook) — код в репозитории
- [x] Флаги готовности разделены: `bridge_verified`, `topup_enabled`, `net_topup_verified` — интерфейс не включает платежи автоматически
- [ ] **P0** Подтвердить на production, какие шлюзы реально включены и ключи заданы (в репозитории только код, не конфиг) — сверить с `PAYMENTS.md` и админкой
- [ ] **P1** IPN/webhook: проверить идемпотентность, подпись, replay-защиту для каждого шлюза на стейджинге
- [x] **P2** `08 — Шлюзы NOWPayments и Trybit/README.md` добавлен: папка помечена как архив от 16.11.2026 со ссылками на актуальный код в `implementation/`

## 10. PWA, фронт и бренд

- [x] PWA: `service-worker.js`, `manifest.json`, `offline.html`, иконки 180/192/512/maskable — в `07 — Установка приложением…/`
- [x] Стейджинг: network-only service worker (авторизованные страницы не кешируются)
- [x] CSS/JS кабинета: `portal*.css`, `coin-shop.css`, `rating-v2.css`, `portal.js`, `coin-shop.js` — в `implementation/…/design/`
- [ ] **P1** Проверить PWA на production (установка, offline, обновление SW) после последнего деплоя кабинета
- [ ] **P2** Мобильная вёрстка 390×844 подтверждена скриншотами из `06 — Скриншоты…/` — держать эталоны актуальными при изменениях UI

## 11. Качество и гигиена

- [x] `TODO/FIXME/XXX/HACK` в коде не найдено (grep 19.11.2026)
- [x] Крупнейшие файлы в норме: `logo.png` 1.9 МБ ×2, скриншоты <1 МБ, `views.js` 160 КБ — бинарников >10 МБ нет
- [x] **P1** Добавлен `.editorconfig` (UTF-8, LF, trim); существующие CRLF в архивных файлах не переписывались
- [x] **P2** Проверено на 8.26 MiB pack: перенос бинарников в Git LFS сейчас не нужен; политика зафиксирована в `CONTRIBUTING.md`
- [x] **P2** Унификация именования: все активные пути проверены (100% ASCII без пробелов), контроль добавлен в `portal.yml` и `CONTRIBUTING.md` (`00–08` не переименовываются)

## 12. Что сделать в первую очередь

| Приоритет | Задача |
|---|---|
| **P0** | Браузерные Playwright-тесты (`ui.cjs`, `rating-ui.cjs`, `staging-e2e.cjs`); PHP 8.3 `php -l` и 9 sqlite-тестов уже пройдены в CI (`44605512093`) |
| **P0** | Подтвердить включённые платёжные шлюзы и ключи на production |
| **P1** | Включить branch protection на `main`, `delete_branch_on_merge` и GitHub Secret Scanning в Settings (API интеграции вернул 403; старые ветки удалены, `gitleaks` CI `44605512129` зелёный) |
| **P1** | CI для PHP/портала (`portal.yml`: PHP 8.3 линт + тесты + манифест + `logo.png` + ASCII) — DONE (`44605512093`) |
| **P1** | Единый источник правды (`implementation/` канон, `01/` и `08/` помечены архивами, паритет `logo.png` в CI) — DONE |
| **P1** | Authenticode-подпись установщика; ручной E2E входа в игру |
| **P2** | `LICENSE`, `SECURITY.md`, `CONTRIBUTING.md`, `CODEOWNERS`, шаблоны Issue/PR, Dependabot, `.editorconfig`, `CHANGELOG.md`, GitHub Release `v1.2.0` — DONE |

---

*Чек-лист составлен по состоянию репозитория на 19.11.2026. Отмечайте пункты по мере выполнения и обновляйте дату.*
