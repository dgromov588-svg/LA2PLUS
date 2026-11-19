<?php

$backendUrl = 'backend'; // Ссылка на админку (не менять!!!)

// Возвраты с платёжных шлюзов: WayForPay может прислать покупателя POST-формой
// (без CSRF-токена), поэтому для этих адресов проверку CSRF отключаем.
// Сами зачисления всё равно проверяются подписью шлюза в контроллере.
$requestPath = (string) parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '', PHP_URL_PATH);
$isPaymentReturn = (bool) preg_match('#^/deposit/(wayforpay-(return|cancel)|wayforpay-shop-(return|cancel))/?#', $requestPath);

// Приёмники уведомлений шлюзов: POST без CSRF-токена (Trybit, PayOp, PayPalych, NOWPayments).
// Подлинность проверяется подписью шлюза внутри контроллера.
$isGatewayWebhook = (bool) preg_match('#^/deposit-(trybit|payop|paypalych|nowpayments)(\.php)?/?$#', $requestPath);

Yii::setPathOfAlias('themes', __DIR__ . '/../../themes');
Yii::setPathOfAlias('TaggedCache', __DIR__ . '/../extensions/yii-cache-tag-dependency');

return [
    'basePath' => dirname(__FILE__) . DIRECTORY_SEPARATOR . '..',
    'name' => 'GHTWEB v5',
    'sourceLanguage' => '00',
    'language' => 'ru',

    'aliases' => [
        'app' => 'application',
        'widgets' => 'application.widgets',
        'modules' => 'application.modules',
        'validators' => 'application.validators',
    ],

    //'basePath' => 'app',

    // preloading 'log' component
    'preload' => ['log'],

    'import' => [
        'application.helpers.*',
        'application.models.*',
        'application.components.*',
        'application.l2j.AbstractQuery',
    ],

    'modules' => [

        'register',
        'login',
        'forgottenPassword',
        'news',
        'page',
        'cabinet',
        // Админ-панель сотрудников: страница /admin и JSON /adm/*
        'adm' => [
            'class' => 'application.modules.adm.AdmModule',
        ],
        'index',
        'backend',
        'deposit',
        'utopayWebhook',
        'wayforpayWebhook',
        'stats',
        'gallery',
        'install',

    ],

    // application components
    'components' => [

        'clientScript' => [
            'scriptMap' => [
                'jquery.js' => false,
            ],
        ],

        'widgetFactory' => [

            //'enableSkin' => TRUE,
            //'skinPath' => 'widgets',

            'widgets' => [

                'CCaptcha' => [
                    'clickableImage' => true,
                    'showRefreshButton' => false,
                    'imageOptions' => [
                        'title' => 'Refresh',
                    ],
                ],

                // Настройки для виджета пагинации
                'CLinkPager' => [
                    'header' => '',
                    'footer' => '',
                    'hiddenPageCssClass' => 'disabled',
                    'firstPageLabel' => '&lt;&lt;',
                    'prevPageLabel' => '&lt;',
                    'nextPageLabel' => '&gt;',
                    'lastPageLabel' => '&gt&gt',
                    'maxButtonCount' => 7,
                    //'cssFile'             => '/css/pagination.css',
                    'id' => 'pagination',
                    'selectedPageCssClass' => 'active',
                    'htmlOptions' => [
                        'class' => 'pagination pagination-sm',
                    ]
                    //'internalPageCssClass' => '',
                ],

                // Настройки для виджета breadcrumb
                'CBreadcrumbs' => [
                    'tagName' => 'ul',
                    'separator' => '',
                    'activeLinkTemplate' => '<li><a href="{url}">{label}</a></li>',
                    'inactiveLinkTemplate' => '<li>{label}</li>',
                    'htmlOptions' => [
                        'class' => 'breadcrumb',
                    ],
                ],
            ],
        ],

        'notify' => [
            'class' => 'Notify',
        ],

        'config' => [
            'class' => 'DbConfig',
        ],

        'cache' => [
            'class' => 'system.caching.CFileCache',
        ],

        'user' => [
            'class' => 'WebUser',
            'allowAutoLogin' => true,
            'autoRenewCookie' => true,
            'identityCookie' => ['name' => 'gw_user'],
            'loginUrl' => ['/login/default/index'],
        ],

        'admin' => [
            'class' => 'WebAdmin',
            'allowAutoLogin' => true,
            'autoRenewCookie' => true,
            'identityCookie' => ['name' => 'gw_admin'],
            'loginUrl' => ['/' . $backendUrl . '/login/index'],
        ],

        'authManager' => [
            'class' => 'CDbAuthManager',
            'connectionId' => 'db',
        ],

        'securityManager' => [
            'hashAlgorithm' => 'sha256',
        ],

        'request' => [
            // Для возврата с платёжного шлюза токена нет — см. $isPaymentReturn выше
            'enableCsrfValidation' => !($isPaymentReturn || $isGatewayWebhook),
            'csrfTokenName' => 'GHTWEB_CSRF_TOKEN',
        ],

        'urlManager' => [
            'urlFormat' => 'path',
            'showScriptName' => false,
            'urlSuffix' => '/',
            'rules' => [

                // ------------------- [Frontend] -------------------

                // Вебхук UTOPAY (POST, без CSRF — см. protected/config/ca-config.php)
                'deposit-utopay.php' => 'utopayWebhook/utopayhook/index',
                'deposit-utopay' => 'utopayWebhook/utopayhook/index',

                // Вебхук WayForPay (POST serviceUrl, без CSRF — см. public_html/index.php)
                'deposit-wayforpay.php' => 'wayforpayWebhook/wayforpayhook/index',
                'deposit-wayforpay' => 'wayforpayWebhook/wayforpayhook/index',

                '' => 'index/default/index',

                // База знаний
                'wiki' => 'index/default/wiki',

                // Полный рейтинг
                'rating' => 'index/default/rating',

                // Deposit (обработка платежа)
                'deposit/result' => 'deposit/default/index',

                // Статические страницы
                'page/<page_name:[a-z0-9-_]+>' => 'page/default/index',

                // Новости
                'news' => 'news/default/index',
                'news/<slug:[a-z0-9-_]+>' => 'news/default/detail',

                // Регистрация
                'register' => 'register/default/index',
                'register/<_hash:\w{32}>' => 'register/default/activated',

                // Авторизация
                'login' => 'login/default/index',

                // Восстановление пароля
                'forgotten-password' => 'forgottenPassword/default/index',
                'forgotten-password/<hash:[a-z0-9\_]+>' => 'forgottenPassword/default/step2',

                // Дополнительные платёжные системы: Trybit, PayOp, PayPalych, ручные способы
                'deposit/pay' => 'deposit/gateway/pay',
                'deposit/pay-return' => 'deposit/gateway/return',
                'deposit/pay-cancel' => 'deposit/gateway/cancel',
                'deposit/pay-status' => 'deposit/gateway/status',
                'deposit-trybit.php' => 'deposit/gateway/trybitWebhook',
                'deposit-trybit' => 'deposit/gateway/trybitWebhook',
                'deposit-payop.php' => 'deposit/gateway/payopWebhook',
                'deposit-payop' => 'deposit/gateway/payopWebhook',
                'deposit-paypalych.php' => 'deposit/gateway/paypalychWebhook',
                'deposit-paypalych' => 'deposit/gateway/paypalychWebhook',
                'deposit-paypalych-refund' => 'deposit/gateway/paypalychRefund',
                'deposit-paypalych-chargeback' => 'deposit/gateway/paypalychChargeback',

                // Установщик настроек платёжных систем (только админ, разовый запуск)
                'payments-setup/tx-check' => 'backend/paymentsSetup/txCheck',
                'payments-setup/shop-render' => 'backend/paymentsSetup/shopRender',
                'payments-setup/config-dump' => 'backend/paymentsSetup/configDump',
                'payments-setup/gateway-probe' => 'backend/paymentsSetup/gatewayProbe',
                // Служебные адреса для подключения платёжных систем (только админ).
                // Оставлены минимально нужные: проверка настроек, прогон шлюзов,
                // включение PayPalych, предпросмотр магазина. Опасные удалены
                // после настройки — вход под игроком, правка и удаление заявок.
                'payments-setup/donation-setup' => 'backend/paymentsSetup/donationSetup',

                // Магазин Coin of Luck (новый дизайн)
                'cabinet/deposit/shop' => 'cabinet/shopCoin/shop',
                'cabinet/deposit/shop-create' => 'cabinet/shopCoin/shopCreate',

                // Deposit
                'cabinet/deposit' => 'cabinet/deposit/index',
                'cabinet/deposit/processed' => 'cabinet/deposit/processed',
                'cabinet/deposit/payment-status' => 'cabinet/deposit/paymentStatus',
                'cabinet/deposit/currency-rates' => 'cabinet/deposit/currencyRates',
                'cabinet/deposit/transfer' => 'cabinet/deposit/transfer',

                // UTOPAY: создание платежа, вебхук, возврат покупателя, статус
                'deposit/utopay-pay' => 'deposit/utopayGateway/pay',
                'deposit/utopay-callback' => 'deposit/utopayGateway/callback',
                'deposit/utopay-return' => 'deposit/utopayGateway/return',
                'deposit/utopay-cancel' => 'deposit/utopayGateway/cancel',
                'deposit/utopay-status' => 'deposit/utopayGateway/status',

                // Прямая оплата набора в магазине картой/криптой
                'cabinet/shop-utopay/<category_link:([a-zA-Z0-9\-]+)>/buy' => 'cabinet/utopayShop/buy',
                'deposit/<action:(result|success|fail)>' => 'deposit/default/<action>',
                'cabinet/deposit/sms-list' => 'cabinet/deposit/getSmsNumberList',

                // WayForPay: оплата пополнения, возврат покупателя, статус
                'deposit/wayforpay-pay' => 'deposit/wayforpayGateway/pay',
                'deposit/wayforpay-return' => 'deposit/wayforpayGateway/return',
                'deposit/wayforpay-cancel' => 'deposit/wayforpayGateway/cancel',
                'deposit/wayforpay-status' => 'deposit/wayforpayGateway/status',

                // WayForPay: прямая оплата набора в магазине
                'cabinet/wayforpay-shop/<category_link:([a-zA-Z0-9\-]+)>/buy' => 'deposit/wayforpayShop/buy',
                'deposit/wayforpay-shop-return' => 'deposit/wayforpayShop/return',
                'deposit/wayforpay-shop-cancel' => 'deposit/wayforpayShop/cancel',
                'deposit/wayforpay-shop-status' => 'deposit/wayforpayShop/status',

                // Logout
                'logout' => 'cabinet/default/logout',


                // Статистика
                'stats/<gs_id:\d+>/<type:([a-z\-]+)>/<clan_id:\w+>' => 'stats/default/index',
                'stats/<gs_id:\d+>/<type:\w+>' => 'stats/default/index',
                'stats/<gs_id:\d+>' => 'stats/default/index',
                'stats' => 'stats/default/index',


                // Cabinet
                'cabinet' => 'cabinet/default/index',

                // Календарь событий: витрина, покупка, получение награды
                'cabinet/calendar' => 'cabinet/default/calendar',
                'cabinet/calendar/buy' => 'cabinet/default/calendarBuy',
                'cabinet/calendar/claim' => 'cabinet/default/calendarClaim',

                // Админка сотрудников. Единственный вход — короткий /admin;
                // он отдаёт панель adm (новый интерфейс). Старая панель
                // (cabinet/panel + модуль panel) снята с маршрутов.
                'admin' => 'adm/page/index',
                'admin/asset' => 'adm/page/asset',
                'adm/<action:[a-zA-Z]+>' => 'adm/adm/<action>',
                // Старые закладки панели v2 ведут в новую панель.
                'admin/panelUsers' => 'adm/page/index',
                'admin/panelAccounts' => 'adm/page/index',
                'admin/panelCharacters' => 'adm/page/index',
                'admin/panelClans' => 'adm/page/index',
                'admin/panelGame' => 'adm/page/index',
                'admin/panelFinance' => 'adm/page/index',
                'admin/panelStaff' => 'adm/page/index',
                'admin/panelLog' => 'adm/page/index',
                'admin/panelAnalytics' => 'adm/page/index',
                'admin/panelOwners' => 'adm/page/index',
                // Разделы SPA открываются по прямому адресу: /admin/tickets,
                // /admin/players и т. д. Разбор — в AdmPageController.
                'admin/<section:[a-z0-9]+>' => 'adm/page/index',

                // Тикеты в панели сотрудников: список, карточка, закрытие.
                // Адреса совпадают с теми, что строят шаблоны (cabinet/ticketsAdmin/...).
                'cabinet/ticketsAdmin/staffIndex' => 'cabinet/ticketsAdmin/staffIndex',
                'cabinet/ticketsAdmin/staffView/<ticket_id:\d+>' => 'cabinet/ticketsAdmin/staffView',
                'cabinet/ticketsAdmin/staffClose/<ticket_id:\d+>' => 'cabinet/ticketsAdmin/staffClose',
                // Карточки и служебные адреса старой панели больше не нужны:
                // разделы живут внутри /admin (SPA).

                // Персонажи
                'cabinet/characters' => 'cabinet/characters/index',
                'cabinet/characters/<char_id:([0-9]+)>/<action:(view|teleport)>' => 'cabinet/characters/<action>',

                // Смена пароля от аккаунта
                'cabinet/change-password' => 'cabinet/changePassword/index',

                // Безопасность
                'cabinet/security' => 'cabinet/security/index',

                // Рефералы
                'cabinet/referals' => 'cabinet/referals/index',

                // История платежей
                'cabinet/transaction-history' => 'cabinet/transactionHistory/index',

                // История авторизаций
                'cabinet/auth-history' => 'cabinet/authHistory/index',

                // Магазин
                'cabinet/shop/<category_link:([a-zA-Z0-9\-]+)>' => 'cabinet/shop/index',
                'cabinet/shop' => 'cabinet/shop/index',
                'cabinet/shop/<category_link:([a-zA-Z0-9\-]+)>/buy' => 'cabinet/shop/buy',

                // Тикеты
                'cabinet/tickets' => 'cabinet/tickets/index',
                'cabinet/tickets/add' => 'cabinet/tickets/add',
                'cabinet/tickets/<ticket_id:\d+>/<action:(view|close)>' => 'cabinet/tickets/<action>',

                // Бонусы
                'cabinet/bonuses' => 'cabinet/bonuses/index',
                'cabinet/bonuses/bonus-code' => 'cabinet/bonuses/bonusCode',

                // Личные сообщения
                'cabinet/messages' => 'cabinet/messages/index',
                'cabinet/messages/<id:\d+>/detail' => 'cabinet/messages/detail',

                // Галерея
                'gallery' => 'gallery/default/index',


                // ------------------- [Backend] -------------------
                $backendUrl => 'backend/default/index',

                // Авторизация
                $backendUrl . '/login' => 'backend/login/index',

                // Выход из админки
                $backendUrl . '/logout' => 'backend/login/logout',

                // Очистка кэша
                $backendUrl . '/clear-cache' => 'backend/default/clearCache',

                // Инфа о item
                $backendUrl . '/get-item-info' => 'backend/default/getItemInfo',

                // Игровые сервера
                $backendUrl . '/game-servers' => 'backend/gameServers/index',
                $backendUrl . '/game-servers/<gs_id:\d+>/edit' => 'backend/gameServers/form',
                $backendUrl . '/game-servers/add' => 'backend/gameServers/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/<action:(del|allow)>' => 'backend/gameServers/<action>',

                // Магазин
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories' => 'backend/gamesServerShopCategories/index',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/edit' => 'backend/gamesServerShopCategories/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/add' => 'backend/gamesServerShopCategories/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/<action:allow|del>' => 'backend/gamesServerShopCategories/<action>',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs' => 'backend/gamesServerShopCategoriesPacks/index',

                // Магазин - наборы
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/edit' => 'backend/gamesServerShopCategoriesPacks/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/add' => 'backend/gamesServerShopCategoriesPacks/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/<action:allow|del>' => 'backend/gamesServerShopCategoriesPacks/<action>',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/del-img' => 'backend/gamesServerShopCategoriesPacks/delImg',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/items' => 'backend/gamesServerShopCategoriesPacksItems/index',

                // Магазин - наборы - предметы
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/items/<item_id:\d+>/edit' => 'backend/gamesServerShopCategoriesPacksItems/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/items/add' => 'backend/gamesServerShopCategoriesPacksItems/form',
                $backendUrl . '/game-servers/<gs_id:\d+>/shop-categories/<category_id:\d+>/packs/<pack_id:\d+>/items/<item_id:\d+>/<action:del|allow>' => 'backend/gamesServerShopCategoriesPacksItems/<action>',


                // Логин сервера
                $backendUrl . '/login-servers' => 'backend/loginServers/index',
                $backendUrl . '/login-servers/<ls_id:\d+>/edit' => 'backend/loginServers/form',
                $backendUrl . '/login-servers/add' => 'backend/loginServers/form',
                $backendUrl . '/login-servers/<ls_id:\d+>/<action:(del|allow|accounts)>' => 'backend/loginServers/<action>',

                // Игровые аккаунты на логине
                // $backendUrl . '/login-servers/<ls_id:\d+>/<action:(edit|del|shop)>' => 'backend/loginServers/<action>',

                // Юзеры
                $backendUrl . '/users' => 'backend/users/index',
                $backendUrl . '/users/add' => 'backend/users/add',
                $backendUrl . '/users/<user_id:\d+>/<action:(view|referals)>' => 'backend/users/<action>',
                $backendUrl . '/users/<user_id:\d+>/auth-history' => 'backend/users/authHistory',
                $backendUrl . '/users/<user_id:\d+>/delete-bonus/<bonus_id:\d+>' => 'backend/users/delBonus',
                $backendUrl . '/users/<user_id:\d+>/add-bonus' => 'backend/users/addBonus',
                $backendUrl . '/users/<user_id:\d+>/add-message' => 'backend/users/addMessage',
                $backendUrl . '/users/<user_id:\d+>/item-purchase' => 'backend/users/itemPurchaseLog',
                $backendUrl . '/users/<user_id:\d+>/transaction-history' => 'backend/users/transactionHistory',
                $backendUrl . '/users/<user_id:\d+>/edit-data' => 'backend/users/editData',

                // Бонусы, предметы в бонусе
                $backendUrl . '/bonuses/<bonus_id:\d+>/items' => 'backend/bonusesItems/index',
                $backendUrl . '/bonuses/<bonus_id:\d+>/items/<item_id:\d+>/edit' => 'backend/bonusesItems/form',
                $backendUrl . '/bonuses/<bonus_id:\d+>/items-create' => 'backend/bonusesItems/form',
                $backendUrl . '/bonuses/<bonus_id:\d+>/items/<item_id:\d+>/del' => 'backend/bonusesItems/del',
                $backendUrl . '/bonuses/<bonus_id:\d+>/items/<item_id:\d+>/allow' => 'backend/bonusesItems/allow',

                // Коды для бонусов
                $backendUrl . '/bonuses/generate-code' => 'backend/bonusCodes/generateCode',

                $backendUrl . '/bonuses/codes' => 'backend/bonusCodes/index',
                $backendUrl . '/bonuses/codes/<id:\d+>/edit' => 'backend/bonusCodes/form',
                $backendUrl . '/bonuses/codes/add' => 'backend/bonusCodes/form',
                $backendUrl . '/bonuses/codes/<id:\d+>/allow' => 'backend/bonusCodes/allow',
                $backendUrl . '/bonuses/codes/<id:\d+>/del' => 'backend/bonusCodes/del',

                // Тикеты
                $backendUrl . '/tickets/<id:\d+>/edit' => 'backend/tickets/edit',

                // Тикеты - категории
                $backendUrl . '/tickets/categories' => 'backend/ticketsCategories/index',
                $backendUrl . '/tickets/categories/add' => 'backend/ticketsCategories/form',
                $backendUrl . '/tickets/categories/<id:\d+>/edit' => 'backend/ticketsCategories/form',
                $backendUrl . '/tickets/categories/<id:\d+>/del' => 'backend/ticketsCategories/del',
                $backendUrl . '/tickets/categories/<id:\d+>/allow' => 'backend/ticketsCategories/allow',

                // admin
                $backendUrl . '/admins/add' => 'backend/admins/add',
                $backendUrl . '/admins/<id:\d+>/edit' => 'backend/admins/edit',


                // Общие правила
                $backendUrl . '/<controller:\w+>' => 'backend/<controller>/index',
                $backendUrl . '/<controller:\w+>/<id:\d+>/edit' => 'backend/<controller>/form',
                $backendUrl . '/<controller:\w+>/add' => 'backend/<controller>/form',
                $backendUrl . '/<controller:\w+>/<id:\d+>/<method:(del|allow)>' => 'backend/<controller>/<method>',
                //$backendUrl . '/<controller:\w+>/<method:(del|edit|allow)>' => 'backend/<controller>/<method>',
            ],
        ],

        // DB
        'db' => require __DIR__ . '/database.php',

        'errorHandler' => [
            'errorAction' => 'index/default/error',
        ],

        'log' => [
            'class' => 'CLogRouter',
            'routes' => [
                [
                    'class' => 'CFileLogRoute',
                    'levels' => 'info, error, warning, vardump',
                ],
                [
                    'class' => 'CWebLogRoute',
                    'levels' => 'error, warning, trace, notice',
                    'categories' => 'application',
                    'enabled' => false,
                ],
                [
                    'class' => 'CProfileLogRoute',
                    'levels' => 'profile',
                    'enabled' => false,
                ],
            ],
        ],
    ],

    'params' => require __DIR__ . '/params.php',
];
