# Тесты (PHP-стенд) — архивная папка

Эта папка — часть исходной поставки с Google Диска. На Диске она была пуста
(фильтр Диска не принял часть файлов, см. `README.md` в корне, п.3).

**Актуальные тесты:** [`implementation/tests/`](../implementation/tests/) —
`domain.php`, `sections.php`, `attendance.php`, `coin-purchase.php`,
`concurrency.php`, `rankings.php`, `wayforpay.php`, `production.php`,
`staging-safety.php` (PHP, sqlite) и `ui.cjs`, `rating-ui.cjs`, `staging-e2e.cjs` (Node/Playwright).

Запуск: `tools/php-test.sh implementation/tests/domain.php` (нужен PHP 8.3+ с `pdo_sqlite`).
В CI тесты гоняются workflow `portal.yml`.
