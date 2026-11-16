<?php
/**
 * Вебхук NOWPayments (IPN) — точка входа.
 *
 * Адрес IPN в кабинете NOWPayments → Store Settings → IPN URL:
 *   https://la2plus.com/deposit-nowpayments.php
 *
 * Отсюда запрос уходит в приложение (index.php → deposit/gateway/nowpayments-webhook):
 * проверка CSRF для этого адреса отключена, подпись проверяется в контроллере.
 */

require __DIR__ . '/index.php';
