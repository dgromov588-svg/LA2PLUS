# Шлюзы NOWPayments и Trybit — архив поставки от 16.11.2026

Эта папка — исходная выгрузка патчей с Google Диска от 16.11.2026 (см. корневой `README.md`).
Некоторые файлы здесь уже устарели и разошлись с текущей кодовой базой (например, `DepositController.php` и правила доступа `.htaccess`).

**Актуальный код портала и платежей находится в [`implementation/`](../implementation/):**
- Обзор и правила боевых платежей: [`implementation/PAYMENTS.md`](../implementation/PAYMENTS.md)
- Платежные компоненты: `PortalPayments.php`, `PortalTrybit.php`, `PortalCoinPayments.php`, `PortalWayforpay.php` в [`implementation/public_html/cms-private/protected/components/`](../implementation/public_html/cms-private/protected/components/)
- Актуальный контроллер пополнения: [`DepositController.php`](../implementation/public_html/cms-private/protected/modules/cabinet/controllers/DepositController.php)
- Образец `.htaccess` стейджинга (заголовки и запрет логов/бэкапов): [`implementation/staging/public.htaccess`](../implementation/staging/public.htaccess)

Править нужно файлы в `implementation/`, а не в этой архивной папке.
