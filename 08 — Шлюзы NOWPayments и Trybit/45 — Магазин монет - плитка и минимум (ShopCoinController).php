<?php

/**
 * LA2PLUS — магазин Coin of Luck: показ страницы и создание заявки.
 *
 * Маршруты (protected/config/main.php):
 *   cabinet/deposit/shop         → cabinet/deposit/shop        (страница магазина)
 *   cabinet/deposit/shop-create  → cabinet/deposit/shopCreate  (создание заявки, JSON)
 *
 * Заявка создаётся ровно как в старом флоу пополнения (таблица transactions),
 * поэтому дальше работают уже проверенные обработчики:
 *   /deposit/wayforpay-pay  и  /deposit/utopay-pay
 * Зачисление делает DepositCreditService (идемпотентно), логика начисления
 * баланса и партнёрских процентов не меняется.
 */
// Константы PAYMENT_SYSTEM_* и вспомогательные классы шлюзов
Yii::import('application.modules.deposit.extensions.Deposit.*');

class ShopCoinController extends CabinetBaseController
{
    /**
     * Бонусная сетка магазина: [порог, процент].
     *
     * @return int
     */
    public static function bonusPercent($amount)
    {
        $amount = (int) $amount;

        if ($amount >= 1000) {
            return 25;
        }

        if ($amount >= 400) {
            return 20;
        }

        if ($amount >= 300) {
            return 15;
        }

        if ($amount >= 200) {
            return 10;
        }

        if ($amount >= 1) {
            return 5;
        }

        return 0;
    }

    /**
     * Данные для страницы магазина (используется и в /cabinet/deposit, и в /cabinet/deposit/shop).
     *
     * @return array
     */
    public static function shopData()
    {
        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');
        Yii::import('application.modules.deposit.extensions.Deposit.libs.ManualLink');

        // --- WayForPay: только разрешённые настройкой способы -----------------
        $wfpMethods = [];

        try {
            $wfp = new Wayforpay();

            if ($wfp->isEnabled()) {
                $allowed = array_filter(array_map('trim', explode(';', (string) config('wayforpay.payment_systems'))));

                foreach (Wayforpay::getPaymentSystemsList() as $id => $name) {
                    if ($allowed && !in_array($id, $allowed, true)) {
                        continue;
                    }

                    // Показываем то, что реально доступно в один клик
                    if (!in_array($id, ['card', 'googlePay', 'applePay', 'privat24', 'qrCode'], true)) {
                        continue;
                    }

                    $wfpMethods[$id] = $name;
                }
            }
        } catch (Exception $e) {
            Yii::log('shop coin: wayforpay init error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'shop-coin');
        }

        // --- UTOPAY: международные методы и крипта ---------------------------
        $utopayProviders = [];
        $utopayCoins = [];

        try {
            $utopay = new Utopay();

            if ($utopay->isEnabled()) {
                $utopayProviders = $utopay->getProviders($utopay->toChargeAmount(100));
                $utopayCoins = (int) config('utopay.crypto_allow') === 1 ? $utopay->getCryptoCoins() : [];
            }
        } catch (Exception $e) {
            Yii::log('shop coin: utopay init error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'shop-coin');
        }

        // --- Новые шлюзы: Trybit (крипта), PayOp (мир), PayPalych (карты РФ, СБП) ---
        $gateways = [];

        
        try {
            if ((new Nowpayments())->isEnabled()) {
                $gateways[] = [
                    'id' => 'nowpayments',
                    'title' => 'NOWPayments',
                    'hint' => 'USDT, BTC, ETH, TON, TRX — 150+ монет',
                    'region' => 'CRYPTO',
                    'icon' => 'coin',
                ];
            }
        } catch (Exception $e) {
            Yii::log('shop coin: nowpayments: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'shop-coin');
        }

        try {
            if ((new Trybit())->isEnabled()) {
                $gateways[] = [
                    'id' => 'trybit',
                    'title' => 'Trybit',
                    'hint' => 'BTC, USDT, ETH, TON',
                    'region' => 'CRYPTO',
                    'icon' => 'coin',
                ];
            }
        } catch (Exception $e) {
            Yii::log('shop coin: trybit: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'shop-coin');
        }

        try {
            if ((new Payop())->isEnabled()) {
                $gateways[] = [
                    'id' => 'payop',
                    'title' => 'Международные карты',
                    'hint' => 'PayOp · Visa, Mastercard и доступные цифровые кошельки',
                    'region' => 'GLOBAL',
                    'icon' => 'globe',
                ];
            }
        } catch (Exception $e) {
            Yii::log('shop coin: payop: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'shop-coin');
        }

        try {
            if ((new Paypalych())->isEnabled()) {
                $gateways[] = [
                    'id' => 'paypalych',
                    'title' => 'PayPalych',
                    'hint' => 'Карты РФ, СБП',
                    'region' => 'RU',
                    'icon' => 'card',
                ];
                $gateways[] = [
                    'id' => 'paypalych_sbp',
                    'title' => 'СБП QR',
                    'hint' => 'Система быстрых платежей',
                    'region' => 'RU',
                    'icon' => 'qr',
                ];
            }
        } catch (Exception $e) {
            Yii::log('shop coin: paypalych: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'shop-coin');
        }

        // Ручные способы: показываем только если владелец задал ссылку
        foreach (['international' => ['International Payment', 'Visa/Mastercard', 'GLOBAL', 'card'],
                  'sbp_qr' => ['СБП QR', 'Система быстрых платежей', 'RU', 'qr'],
                  'erip' => ['BY ERIP', 'QR code', 'BELARUS', 'qr']] as $key => $meta) {
            try {
                $manual = new ManualLink($key);

                if ($manual->isEnabled()) {
                    $gateways[] = [
                        'id' => 'manual:' . $key,
                        'title' => $manual->getTitle() !== '' ? $manual->getTitle() : $meta[0],
                        'hint' => $manual->getHint() !== '' ? $manual->getHint() : $meta[1],
                        'region' => $meta[2],
                        'icon' => $meta[3],
                    ];
                }
            } catch (Exception $e) {
                Yii::log('shop coin: manual ' . $key . ': ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'shop-coin');
            }
        }

        $rate = 1.0;

        try {
            $rate = (float) config('utopay.rate');
            $rate = $rate > 0 ? $rate : 1.0;
        } catch (Exception $e) {
            $rate = 1.0;
        }

        // Минимальная сумма по способам оплаты. Площадка берёт от 1 USD,
        // у крипто-шлюзов свои пороги — их и отдаём в шаблон, чтобы игрок
        // не попадал на ошибку после ввода суммы.
        $payMins = [
            'utopay:trc20/usdt' => 10,
            'gateway:nowpayments' => 5,
            'gateway:trybit' => 1,
            'utopay:stripe' => 1,
        ];

        foreach ($utopayCoins as $coin) {
            if (!empty($coin['id']) && !empty($coin['minimum'])) {
                $payMins['utopay:' . $coin['id']] = (float) $coin['minimum'];
            }
        }

        foreach ($utopayProviders as $provider) {
            if (!empty($provider['id']) && !empty($provider['minimum_amount'])) {
                $payMins['utopay:' . $provider['id']] = (float) $provider['minimum_amount'];
            }
        }

        return [
            'wfpMethods' => $wfpMethods,
            'utopayProviders' => $utopayProviders,
            'utopayCoins' => $utopayCoins,
            'gateways' => $gateways,
            'currency' => 'USD',
            'rate' => $rate,
            'payMins' => $payMins,
        ];
    }

    /**
     * Отдельный адрес магазина (оставлен как альтернативный вход).
     */
    public function actionShop()
    {
        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        $this->render('//cabinet/deposit/shop-coin', self::shopData());
    }

    /**
     * POST: создать заявку на покупку монет и вернуть адрес боевого обработчика.
     */
    public function actionShopCreate()
    {
        if (!request()->getIsPostRequest()) {
            $this->json(['ok' => false, 'error' => 'method'], 405);
        }

        if (user()->isGuest) {
            $this->json(['ok' => false, 'error' => 'auth'], 403);
        }

        $amount = (int) request()->getPost('amount', 0);
        $group = (string) request()->getPost('pay_group', '');
        $payId = trim((string) request()->getPost('pay_id', ''));
        $character = trim((string) request()->getPost('character', ''));
        // Страна и валюта — контекст игрока. Сумма и начисление остаются в USD.
        $country = strtoupper(trim((string) request()->getPost('country', '')));
        $displayCurrency = strtoupper(trim((string) request()->getPost('display_currency', 'USD')));
        $countries = ['UA','US','GB','DE','PL','FR','ES','IT','PT','NL','BE','AT','IE','GR','CY','LT','LV','EE','FI','SK','SI','HR','MT','LU','KZ','TR','BR','GE','MD','AM','AZ','CA','AU','CH','AE','IN','MX','RO','CZ','RU','BY','IL','OTHER'];
        $currencies = ['USD','EUR','UAH','GBP','PLN','KZT','TRY','BRL','GEL','MDL','AMD','AZN','CAD','AUD','CHF','AED','INR','MXN','RON','CZK'];
        if (($country !== '' && !in_array($country, $countries, true)) || !in_array($displayCurrency, $currencies, true)) {
            $this->json(['ok' => false, 'error' => 'locale', 'message' => 'Выберите страну и валюту из списка.'], 422);
        }


        if ($amount < 1) {
            $this->json(['ok' => false, 'error' => 'min'], 422);
        }

        if ($payId === '' || !in_array($group, ['wayforpay', 'utopay', 'gateway'], true)) {
            $this->json(['ok' => false, 'error' => 'method'], 422);
        }

        // Принимаем только тот способ, который сейчас включён и был показан игроку.
        // Это не даёт вручную подставить выключенный или неизвестный provider.
        $shop = self::shopData();
        $allowedMethods = [];

        foreach ((array) $shop['wfpMethods'] as $id => $title) {
            $allowedMethods['wayforpay:' . $id] = true;
        }
        foreach ((array) $shop['utopayProviders'] as $provider) {
            if (!empty($provider['id'])) {
                $allowedMethods['utopay:' . $provider['id']] = true;
            }
        }
        foreach ((array) $shop['utopayCoins'] as $coin) {
            if (!empty($coin['id'])) {
                $allowedMethods['utopay:' . $coin['id']] = true;
            }
        }
        foreach ((array) $shop['gateways'] as $gateway) {
            if (!empty($gateway['id'])) {
                $allowedMethods['gateway:' . $gateway['id']] = true;
            }
        }

        $mins = isset($shop['payMins']) ? (array) $shop['payMins'] : [];

        $key = $group . ':' . $payId;

        if (empty($allowedMethods[$key])) {
            $this->json(['ok' => false, 'error' => 'method', 'message' => 'Этот способ оплаты сейчас недоступен.'], 422);
        }

        $methodMin = isset($mins[$key]) ? (float) $mins[$key] : 1;

        if ($amount < $methodMin) {
            $this->json([
                'ok' => false,
                'error' => 'min',
                'min' => $methodMin,
                'message' => 'Минимальная сумма для этого способа — ' . $methodMin . ' USD',
            ], 422);
        }

        if ($amount > 100000) {
            $this->json(['ok' => false, 'error' => 'max'], 422);
        }

        // Сколько монет игрок получает: сумма + бонус. Бонус начисляем сразу,
        // заявка создаётся уже на итоговое количество монет.
        $percent = self::bonusPercent($amount);
        $bonus = (int) floor($amount * $percent / 100);
        $coins = $amount + $bonus;

        $gsId = (int) user()->getGsId();
        $gs = Gs::model()->findByPk($gsId);

        if (!$gs) {
            $this->json(['ok' => false, 'error' => 'server'], 422);
        }

        try {
            db()->createCommand()->insert('{{transactions}}', [
                'payment_system' => $group === 'wayforpay' ? Deposit::PAYMENT_SYSTEM_WAYFORPAY : Deposit::PAYMENT_SYSTEM_UTOPAY, // для gateway-группы номер некритичен: маршрут берётся из params['gateway']
                'user_id' => user()->getId(),
                'sum' => $amount,          // сколько игрок платит в валюте сайта
                'count' => $coins,         // сколько монет получит (с бонусом)
                'status' => 0,
                'user_ip' => userIp(),
                'params' => json_encode([
                    'shop' => 'coin',
                    'amount' => $amount,
                    'bonus_percent' => $percent,
                    'bonus' => $bonus,
                    'coins' => $coins,
                    'character' => $character,
                    'country' => $country,
                    'display_currency' => $displayCurrency,
                    'pay_group' => $group,
                    'pay_id' => $payId,
                    'gateway' => $group === 'gateway' ? preg_replace('/^manual:/', 'manual', $payId) : null,
                    'gateway_method' => $group === 'gateway' ? $payId : null,
                ], JSON_UNESCAPED_UNICODE),
                'gs_id' => $gsId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $txId = (int) db()->getLastInsertID();

            Yii::log('shop coin: заявка #' . $txId . ' — ' . $coins . ' монет (' . $amount . ' + ' . $percent . '%)', CLogger::LEVEL_INFO, 'shop-coin');

            $this->json([
                'ok' => true,
                'tx_id' => $txId,
                'coins' => $coins,
                'action' => $group === 'wayforpay'
                    ? $this->createUrl('/deposit/wayforpay-pay')
                    : ($group === 'gateway' ? $this->createUrl('/deposit/pay') : $this->createUrl('/deposit/utopay-pay')),
            ]);
        } catch (Throwable $e) {
            Yii::log('shop coin: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'shop-coin');

            $this->json(['ok' => false, 'error' => 'db: ' . $e->getMessage()], 500);
        }
    }

    /**
     * @param array $data
     * @param int $status
     */
    private function json(array $data, $status = 200)
    {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code($status);

        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        app()->end();
    }
}
