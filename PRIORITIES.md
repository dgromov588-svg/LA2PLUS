# Приоритеты задач LA2PLUS

> Источник: [CHECKLIST.md](CHECKLIST.md) от 19.11.2026 · Обновлено: 19.11.2026
> **P0** — блокирует релиз/деньги/безопасность, делать сейчас. **P1** — важно, в ближайший спринт. **P2** — желательно, по возможности.
> Статусы: `TODO` · `DONE` · `PARTIAL` · `BLOCKED` (нужен владелец / хостинг / сертификат / настройки GitHub)

## P0 — критично

| # | Задача | Почему | Статус |
|---|---|---|---|
| P0-1 | Прогнать PHP/Node-тесты и `php -l` на PHP 8.3/8.4, зафиксировать отчёт в `docs/verification/` | Без этого нельзя гарантировать кабинет и платежи | PARTIAL — PHP 8.3 `php -l`, 9 sqlite-тестов и `node --check` пройдены в CI (`44605512093`, отчёт [`docs/verification/portal-ci-2026-11-19.json`](docs/verification/portal-ci-2026-11-19.json)); браузерные Playwright-тесты (`ui.cjs`, `rating-ui.cjs`, `staging-e2e.cjs`) остаются за стендом/стейджингом |
| P0-2 | Подтвердить на production, какие шлюзы включены и ключи заданы (NOWPayments/Trybit/WayForPay) | В репозитории только код; ошибка = потеря платежей или утечка ключей | BLOCKED — нужен доступ к админке/хостингу (`PAYMENTS.md`) |
| P0-3 | Ручной E2E: вход персонажа в игру на игровом сервере | `smoke`/`fullcheck` игру не запускают | BLOCKED — нужен игровой сервер |

Команды для локального прогона (на машине с PHP 8.3+/8.4, `pdo_sqlite`, Playwright):

```sh
for f in $(find implementation -name '*.php'); do php -l "$f" || exit 1; done
for t in domain sections attendance coin-purchase rankings wayforpay production staging-safety; do
  php "implementation/tests/$t.php" || exit 1
done
php implementation/tests/concurrency.php   # нужен pcntl (Linux)
python3 tools/build_portal_release.py
```

## P1 — важно (порядок выполнения)

| # | Задача | Статус |
|---|---|---|
| P1-1 | `LICENSE` (proprietary) + `SECURITY.md` + `CONTRIBUTING.md` | DONE 19.11.2026 |
| P1-2 | CI для PHP/портала: `.github/workflows/portal.yml` (`php -l` + 9 sqlite-тестов + `node --check` + паритет `logo.png` + ASCII-пути + `release-manifest.json`) | DONE 19.11.2026 (зелёный прогон `44605512093`) |
| P1-3 | `.editorconfig`, `CODEOWNERS`, шаблоны Issue/PR, Dependabot | DONE 19.11.2026 |
| P1-4 | Улучшить `tools/php-test.sh` (понятная ошибка без PHP/`work/php-runtime`) + `04 — Тесты (PHP-стенд)/README.md` | DONE 19.11.2026 |
| P1-5 | Удалить merged-ветки (`feat/cabinet-market-rating`, `arena/379e23e4-la2plus`); включить branch protection на `main` и `delete_branch_on_merge` | PARTIAL — обе ветки удалены на `origin`; branch protection и `delete_branch_on_merge` вернули `403 Resource not accessible by integration`, включаются владельцем в Settings GitHub |
| P1-6 | CI-сканер секретов `.github/workflows/security.yml` (`gitleaks` v8.30.1) + нативные GitHub Secret Scanning / Push Protection | PARTIAL — `gitleaks` CI прошёл (`44605512129`, 0 утечек); Secret Scanning в Settings (`security_and_analysis: null`, API вернул `403`) за владельцем |
| P1-7 | Разграничить дубли: `01/` и `08/` помечены архивами (`README.md`), `implementation/` — единый канон; паритет `logo.png` (`//go:embed` vs веб) проверяется в CI (`cmp -s`) | DONE 19.11.2026 (контроль в `portal.yml`, архив `00–08` сохранён без потери истории) |
| P1-8 | Authenticode-подпись установщика (нужен сертификат владельца) | BLOCKED — сертификат |
| P1-9 | IPN/webhook: проверить идемпотентность, подпись, replay-защиту и контрольную оплату на стейджинге | BLOCKED — стейджинг `new.la2plus.com` недоступен из песочницы |
| P1-10 | Проверить PWA на production после деплоя кабинета | BLOCKED — `la2plus.com` недоступен из песочницы |

## P2 — желательно

| # | Задача | Статус |
|---|---|---|
| P2-1 | `CHANGELOG.md` (1.2.0, игровой выпуск 2026.11.18.1, кабинет, wiki) | DONE 19.11.2026 |
| P2-2 | Обновить `docs/CONTINUATION.md` (ветка `main`, PR #4 вместо #3, ссылки на чек-лист/приоритеты) | DONE 19.11.2026 |
| P2-3 | Первый GitHub Release | DONE 19.11.2026 ([`v1.2.0`](https://github.com/dgromov588-svg/LA2PLUS/releases/tag/v1.2.0) на `main`) |
| P2-4 | Проверить `deploy-hosting.sh`, `.htaccess`, `ThirdPartyNotices.txt` и сверку отсутствовавших с Диска файлов (`00 — Начни отсюда/README.md`) | DONE 19.11.2026 |
| P2-5 | Оценка Git LFS (весь pack `.git` = 8.26 MiB — LFS избыточен) и ASCII-имена в активных каталогах (проверяется в `portal.yml`) | DONE 19.11.2026 |
| P2-6 | Пометить `08 — Шлюзы NOWPayments и Trybit/` как архив, актуальный код — в `implementation/` | DONE 19.11.2026 (`08 — Шлюзы NOWPayments и Trybit/README.md`) |

## Журнал выполнения

- 19.11.2026: выполнены P1-1…P1-4, P1-7, P2-1…P2-6; удалены устаревшие ветки `feat/cabinet-market-rating` и `arena/379e23e4-la2plus`; создан первый релиз [`v1.2.0`](https://github.com/dgromov588-svg/LA2PLUS/releases/tag/v1.2.0); пройдены CI-прогоны `Portal (PHP)` (`44605512093`, PHP 8.3 `php -l` + 9 sqlite-тестов + JS + `logo.png` + ASCII + manifest) и `Secret scan (gitleaks)` (`44605512129`, 0 утечек). Остаток за владельцем/стендами: branch protection / `delete_branch_on_merge` / Secret Scanning (API 403), Playwright E2E, production-платежи, IPN/PWA на живых доменах, Authenticode и вход в игру.
