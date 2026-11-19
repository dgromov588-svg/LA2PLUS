# Приоритеты задач LA2PLUS

> Источник: [CHECKLIST.md](CHECKLIST.md) от 19.11.2026 · Обновлено: 19.11.2026
> **P0** — блокирует релиз/деньги/безопасность, делать сейчас. **P1** — важно, в ближайший спринт. **P2** — желательно, по возможности.
> Статусы: `TODO` · `DONE` · `BLOCKED` (нужен владелец / хостинг / PHP-рантайм / настройки GitHub)

## P0 — критично

| # | Задача | Почему | Статус |
|---|---|---|---|
| P0-1 | Прогнать PHP/Node-тесты и `php -l` на PHP 8.3/8.4, зафиксировать отчёт в `docs/verification/` | Без этого нельзя гарантировать кабинет и платежи | BLOCKED — в песочнице нет PHP/Go; CI добавлен (`portal.yml`), локальный прогон за владельцем |
| P0-2 | Подтвердить на production, какие шлюзы включены и ключи заданы (NOWPayments/Trybit/WayForPay) | В репозитории только код; ошибка = потеря платежей или утечка ключей | BLOCKED — нужен доступ к админке/хостингу (`PAYMENTS.md`) |
| P0-3 | Ручной E2E: вход персонажа в игру на игровом сервере | `smoke`/`fullcheck` игру не запускают | BLOCKED — нужен игровой сервер |

Команды для P0-1 (на машине с PHP 8.3+ и `pdo_sqlite`):

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
| P1-2 | CI для PHP/портала: `.github/workflows/portal.yml` (`php -l` + 9 sqlite-тестов + `node --check` + `release-manifest.json`) | DONE 19.11.2026 |
| P1-3 | `.editorconfig`, `CODEOWNERS`, шаблоны Issue/PR, Dependabot | DONE 19.11.2026 |
| P1-4 | Улучшить `tools/php-test.sh` (понятная ошибка без PHP/`work/php-runtime`) + `04 — Тесты (PHP-стенд)/README.md` | DONE 19.11.2026 |
| P1-5 | Branch protection на `main` + удалить merged-ветки `feat/cabinet-market-rating`, `arena/379e23e4-la2plus` и включить `delete_branch_on_merge` | TODO — только владелец в настройках GitHub |
| P1-6 | CI-сканер секретов `.github/workflows/security.yml` (`gitleaks`) + включить GitHub Secret Scanning / Push Protection | PARTIAL — `security.yml` добавлен и 206 файлов проверены (0 утечек); Secret Scanning в GitHub (`security_and_analysis: null`) за владельцем |
| P1-7 | Дубли: `views.js` (`01.3/` архивный vs `implementation/` актуальный — уже разошлись), `panel.php` (дубль внутри `01.2/` и `07/`), `logo.png` (`launcher/assets/` нужен для `//go:embed`, веб-оригинал в `implementation/…/design/`) | PARTIAL — картина зафиксирована; удалить или оставить архивные дубли решает владелец |
| P1-8 | Authenticode-подпись установщика (нужен сертификат владельца) | BLOCKED — сертификат |
| P1-9 | IPN/webhook: проверить идемпотентность, подпись, replay-защиту и контрольную оплату на стейджинге | TODO — стейджинг `new.la2plus.com` |
| P1-10 | Проверить PWA на production после деплоя кабинета | TODO — production |

## P2 — желательно

| # | Задача | Статус |
|---|---|---|
| P2-1 | `CHANGELOG.md` (1.2.0, игровой выпуск 2026.11.18.1, кабинет, wiki) | DONE 19.11.2026 |
| P2-2 | Обновить `docs/CONTINUATION.md` (ветка `main`, PR #4 вместо #3, ссылки на чек-лист/приоритеты) | DONE 19.11.2026 |
| P2-3 | Первый GitHub Release | TODO — владелец |
| P2-4 | Проверить `deploy-hosting.sh` и `.htaccess` на утечки/права доступа (платежи `PortalPayments`/`PortalTrybit`/`PortalCoinPayments`/`PortalWayforpay` уже покрыты в `domain.php`/`production.php`/`wayforpay.php`) | DONE 19.11.2026 (`deploy-hosting.sh` читает секреты только из env; `.htaccess` сверены; `phpstan` отложен — нужны стабы Yii1) |
| P2-5 | Git LFS / вынос скриншотов и бинарников, ASCII-имена для новых файлов | TODO (правило зафиксировано в `CONTRIBUTING.md`) |
| P2-6 | Пометить `08 — Шлюзы NOWPayments и Trybit/` как архив, актуальный код — в `implementation/` | DONE 19.11.2026 (`08 — Шлюзы NOWPayments и Trybit/README.md`) |

## Журнал выполнения

- 19.11.2026: составлены приоритеты из `CHECKLIST.md`; выполнены P1-1…P1-4, P2-1, P2-2, P2-4, P2-6 и CI-часть P1-6 (`security.yml` + проверка 206 файлов на секреты, 0 утечек). P0-1…P0-3, P1-5, настройки Secret Scanning (P1-6), P1-7…P1-10, P2-3, P2-5 требуют PHP-рантайма, хостинга или прав владельца в GitHub.
