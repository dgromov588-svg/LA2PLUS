<?php

error_reporting(-1);
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
ini_set('session.use_strict_mode', '1');
if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
    ini_set('session.cookie_secure', '1');
}

// The web server must load the bundled private-directory access rules.
if (getenv('LA2PLUS_PRIVATE_GUARD') !== '1' && getenv('REDIRECT_LA2PLUS_PRIVATE_GUARD') !== '1') {
    http_response_code(503);
    header('Content-Type: text/plain; charset=UTF-8');
    exit('Не подключены правила защиты CMS. См. INSTALL-PUBLIC-HTML.md.');
}
$rootPath = __DIR__ . '/cms-private';
$protectedPath = $rootPath . '/protected';

// Проверка установлена ли CMS
if (is_dir($protectedPath . '/modules/install') && !is_file($protectedPath . '/config/lock') && strpos($_SERVER['REQUEST_URI'], 'install') === false) {
    header('Location: /install/');
    exit;
}
// ---------------

// Путь к папке с framework
$yii = $rootPath . '/framework/yii.php';

if (!is_file($yii)) {
    die('Неправильно указан путь до папки <b>framework</b>');
}

// Чтобы включить режим разработки добавьте свой IP в массив тот что снизу, к примеру: ['127.0.0.1']
if (getenv('APP_DEBUG') === '1') {
    define('YII_DEBUG', true);

    $config = $protectedPath . '/config/main-dev.php';
} else {
    error_reporting(0);

    define('YII_DEBUG', false);

    $config = $protectedPath . '/config/main.php';
}

// Запись в лог имени файла и номера строки
define('YII_TRACE_LEVEL', 1);

require_once $protectedPath . '/helpers/global.php';

require_once $rootPath . '/vendor/autoload.php';

// Проверка владения доменом для Trybit: тег в <head><meta charset="utf-8"> всех страниц сайта.
// Значение приходит от Trybit при добавлении проекта (один раз, при подключении мерчанта).
if (!defined('LA2PLUS_TRYBIT_VERIFICATION')) {
    define('LA2PLUS_TRYBIT_VERIFICATION', 'smr9zdz2crwmrkz2t26w78sr03x99r1h');
}

require_once $yii;

// Вебхук UTOPAY (/deposit-utopay.php) — POST от платёжной системы без CSRF-токена.
// Флаг нужно снять ДО создания приложения: обработчик CSRF вешается на onBeginRequest
// в момент инициализации компонента request.
$isUtopayHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-utopay') === 0;

// Вебхук WayForPay (/deposit-wayforpay.php) — то же самое: POST от платёжной системы.
$isWayforpayHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-wayforpay') === 0;

// Вебхуки дополнительных платёжных систем: Trybit, PayOp, PayPalych.
$isTrybitHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-trybit') === 0;
$isPayopHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-payop') === 0;
$isPaypalychHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-paypalych') === 0;

// Вебхук NOWPayments (/deposit-nowpayments.php) — IPN об оплате криптой.
$isNowpaymentsHook = isset($_SERVER['REQUEST_URI']) && strpos((string) $_SERVER['REQUEST_URI'], '/deposit-nowpayments') === 0;

if ($isUtopayHook || $isWayforpayHook || $isTrybitHook || $isPayopHook || $isPaypalychHook || $isNowpaymentsHook) {
    $webhookConfig = require $config;
    $webhookConfig['components']['request']['enableCsrfValidation'] = false;
    $config = $webhookConfig;
}

$app = Yii::createWebApplication($config);

CHtml::setModelNameConverter(function($model) {
    $reflector = new ReflectionClass($model);
    return $reflector->getShortName();
});

// Вебхук обрабатываем напрямую, минуя разбор URL: адрес с .php не должен
// зависеть от правил urlManager и суффикса «/».
if ($isUtopayHook) {
    $app->runController('utopayWebhook/utopayhook/index');
    $app->end();
}

if ($isWayforpayHook) {
    $app->runController('wayforpayWebhook/wayforpayhook/index');
    $app->end();
}

if ($isNowpaymentsHook) {
    $app->runController('deposit/gateway/nowpaymentsWebhook');
    $app->end();
}

if ($isTrybitHook || $isPayopHook || $isPaypalychHook) {
    // PayPalych присылает три разных вида уведомлений на разные адреса:
    // оплату, возврат и чарджбэк. Разводим их до выбора обработчика, иначе
    // возврат и чарджбэк попадали в проверку подписи оплаты и получали 400.
    if (strpos((string) $_SERVER['REQUEST_URI'], '/deposit-paypalych-chargeback') === 0) {
        $action = 'paypalychChargeback';
    } elseif (strpos((string) $_SERVER['REQUEST_URI'], '/deposit-paypalych-refund') === 0) {
        $action = 'paypalychRefund';
    } elseif ($isTrybitHook) {
        $action = 'trybitWebhook';
    } elseif ($isPayopHook) {
        $action = 'payopWebhook';
    } else {
        $action = 'paypalychWebhook';
    }

    $app->runController('deposit/gateway/' . $action);
    $app->end();
}

$app->run();
