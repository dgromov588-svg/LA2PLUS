# Политика безопасности LA2PLUS

## Сообщение об уязвимости

Не публикуйте уязвимости в Issues. Сообщайте приватно владельцу репозитория
через GitHub → **Security → Report a vulnerability** (Private Vulnerability
Reporting) или прямым сообщением владельцу `dgromov588-svg`.

Укажите: затронутый компонент (`implementation/`, `launcher/`, шлюз платежей),
шаги воспроизведения, влияние и, если возможно, PoC. Не эксплуатируйте
уязвимость на production (`la2plus.com`) и не получайте доступ к чужим данным.

## Что не является уязвимостью

- Отсутствие Authenticode-подписи установщика Windows (известно, см. `launcher/README.md`).
- Демонстрационный режим на `new.la2plus.com` (виртуальные деньги, тестовые данные).

## Секреты

Ключ подписи Ed25519 (`signing-seed.txt`), ключи шлюзов (`LA2PLUS_TRYBIT_*`,
`LA2PLUS_GAME_BRIDGE_SECRET`, `nowpayments.*`) и доступы cPanel хранятся
**только вне репозитория**. Не коммитьте `.env`, `credentials.json`,
`staging.json`, `database.php` с реквизитами и любые `*.local.json`.
