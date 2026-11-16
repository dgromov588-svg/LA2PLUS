<?php

/**
 * Пополнение баланса Coin of Luck.
 *
 * Страница показывает:
 *  - доступные способы оплаты (карта / крипта) — те же плитки, что в магазине;
 *  - ГЛАВНОЕ действие: перевод монет с баланса в игру (на персонажа),
 *    механика как у банкомата: списали монеты → выдали адену через items_delayed;
 *  - шапку-объявление недели (настройка shop.free_week).
 *
 * URL: /cabinet/deposit
 */
class DepositController extends CabinetBaseController
{
    /** Сколько монет за 1 единицу адены при переводе в игру. */
    public static function coinsPerAdena()
    {
        $rate = (float) config('transfer.coins_per_adena');

        return $rate > 0 ? $rate : 1.0;
    }

    /**
     * Досверяет неоплаченные заявки последних суток со шлюзами.
     *
     * Нужно на случай, когда игрок оплатил и закрыл вкладку: постбэк мог
     * не дойти (сбой сети, блокировка), и без этого монеты бы не пришли.
     * Ошибки шлюзов не должны ломать страницу — всё глушим в лог.
     *
     * @return void
     */
    private function syncPendingPayments()
    {
        try {
            $rows = db()->createCommand(
                'SELECT `id`, `params` FROM `{{transactions}}` WHERE `user_id` = ' . (int) user()->getId()
                . ' AND `status` != ' . (int) Transactions::STATUS_SUCCESS
                . " AND `created_at` > '" . date('Y-m-d H:i:s', time() - 86400) . "'"
                . ' ORDER BY `id` DESC LIMIT 5'
            )->queryAll();
        } catch (Throwable $e) {
            return;
        }

        if (!$rows) {
            return;
        }

        try {
            Yii::import('application.modules.deposit.controllers.GatewayController');
            Yii::import('application.modules.deposit.controllers.UtopayGatewayController');

            $gateway = new GatewayController('gateway');
            $utopay = new UtopayGatewayController('utopayGateway');

            foreach ($rows as $row) {
                $params = json_decode((string) $row['params'], true);
                $params = is_array($params) ? $params : [];

                try {
                    // Приоритет у шлюза, которым реально создавался счёт:
                    // order_id UTOPAY остаётся в params даже после смены способа.
                    $hasGateway = !empty($params['external_id'])
                        && !empty($params['gateway'])
                        && $params['gateway'] !== 'utopay';

                    if ($hasGateway) {
                        $gateway->syncPayment((int) $row['id']);
                    } elseif (!empty($params['order_id'])) {
                        $utopay->syncPayment('deposit', (int) $row['id']);
                    }
                } catch (Throwable $e) {
                    Yii::log('deposit: sync #' . $row['id'] . ' error: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'deposit');
                }
            }
        } catch (Throwable $e) {
            Yii::log('deposit: sync init error: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'deposit');
        }
    }

    public function actionIndex()
    {
        Yii::import('application.modules.cabinet.controllers.ShopCoinController');

        $this->syncPendingPayments();

        $userId = user()->getId();
        $gsId = (int) user()->getGsId();

        $shop = [];

        try {
            $shop = ShopCoinController::shopData();
        } catch (Throwable $e) {
            Yii::log('deposit: shop init error: ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'deposit');
        }

        // --- персонажи игрока: кому можно перевести адену ---
        $characters = [];

        try {
            $l2 = l2('gs', $gsId)->connect();

            $command = $l2->getDb()->createCommand();
            $command->where('account_name = :account_name', [':account_name' => user()->getGameLogin()]);

            $characters = $l2->characters($command)->queryAll();
        } catch (Throwable $e) {
            Yii::log('deposit: characters error: ' . $e->getMessage(), CLogger::LEVEL_WARNING, 'deposit');
        }

        // Номер свежей неоплаченной заявки — страница сама спросит её статус
        // сразу после возврата с оплаты (монеты приходят без перезагрузки).
        $pendingTx = 0;

        try {
            $pendingTx = (int) db()->createCommand(
                'SELECT `id` FROM `{{transactions}}` WHERE `user_id` = ' . (int) $userId
                . ' AND `status` != ' . (int) Transactions::STATUS_SUCCESS
                . " AND `created_at` > '" . date('Y-m-d H:i:s', time() - 6 * 3600) . "'"
                . ' ORDER BY `id` DESC LIMIT 1'
            )->queryScalar();
        } catch (Throwable $e) {
            $pendingTx = 0;
        }

        $this->render('//cabinet/deposit/index', [
            'wfpMethods' => isset($shop['wfpMethods']) ? $shop['wfpMethods'] : [],
            'utopayProviders' => isset($shop['utopayProviders']) ? $shop['utopayProviders'] : [],
            'utopayCoins' => isset($shop['utopayCoins']) ? $shop['utopayCoins'] : [],
            'gateways' => isset($shop['gateways']) ? $shop['gateways'] : [],
            'currency' => isset($shop['currency']) ? $shop['currency'] : 'USD',
            'rate' => isset($shop['rate']) ? $shop['rate'] : 1.0,
            'pendingTx' => $pendingTx,
            'characters' => $characters,
            'adenaCoins' => self::coinsPerAdena(),
            'isPaymentReady' => $this->isPaymentReady(),
        ]);
    }

    /**
     * Готов ли приём денег: если ни один шлюз не включён — показываем
     * объявление «игра без доната», а не кнопки, которые приведут в тупик.
     *
     * @return bool
     */
    public function isPaymentReady()
    {
        if (isset($GLOBALS['la2plus_payment_probe'])) {
            return (bool) $GLOBALS['la2plus_payment_probe'];
        }

        Yii::import('application.modules.deposit.extensions.Deposit.libs.*');

        try {
            if ((new Trybit())->isEnabled()) {
                return true;
            }
        } catch (Throwable $e) {
            // игнорируем
        }

        try {
            if ((new Nowpayments())->isEnabled()) {
                return true;
            }
        } catch (Throwable $e) {
            // игнорируем
        }

        try {
            $utopay = new Utopay();

            if ((int) config('utopay.allow') === 1 && $utopay->isEnabled()) {
                return true;
            }
        } catch (Throwable $e) {
            // игнорируем
        }

        try {
            if ((new Paypalych())->isEnabled() || (new Payop())->isEnabled()) {
                return true;
            }
        } catch (Throwable $e) {
            // игнорируем
        }

        return false;
    }

    /**
     * POST: перевести монеты в игру (на персонажа).
     */
    public function actionTransfer()
    {
        if (!request()->getIsPostRequest()) {
            $this->redirect(['index']);
        }

        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        $charId = (int) request()->getPost('char_id', 0);
        $coins = round((float) str_replace(',', '.', (string) request()->getPost('coins', '')), 2);
        $perAdena = self::coinsPerAdena();

        try {
            if ($charId <= 0) {
                throw new Exception('Выберите персонажа.');
            }

            if ($coins <= 0) {
                throw new Exception('Укажите количество монет.');
            }

            $userId = user()->getId();

            // 1) списываем монеты с кошелька (внутри — проверка остатка и журнал)
            WalletService::debit($userId, $coins, [
                'comment' => 'Перевод в игру, персонаж #' . $charId,
                'order_id' => 'game-' . $charId,
            ]);

            $adena = (int) floor($coins / $perAdena);

            // 2) проверяем, что персонаж принадлежит этому аккаунту
            $l2 = l2('gs', user()->getGsId())->connect();

            $charIdField = $l2->getField('characters.char_id');
            $command = $l2->getDb()->createCommand();
            $command->where('account_name = :a AND ' . $charIdField . ' = :c', [
                ':a' => user()->getGameLogin(),
                ':c' => $charId,
            ]);

            $character = $l2->characters($command)->queryRow();

            if (!$character) {
                // монеты уже списаны — возвращаем, чтобы не пропали
                WalletService::credit($userId, $coins, [
                    'comment' => 'Возврат: персонаж #' . $charId . ' не найден',
                    'order_id' => 'game-refund',
                ]);

                throw new Exception('Персонаж не найден в вашем аккаунте. Монеты возвращены.');
            }

            // 3) выдаём адену игроку
            self::deliverAdena($l2, $charId, $adena);

            Yii::log('перевод в игру: игрок ' . $userId . ', персонаж ' . $charId . ', '
                . $coins . ' монет → ' . $adena . ' адены', CLogger::LEVEL_INFO, 'deposit');

            user()->setFlash(FlashConst::MESSAGE_SUCCESS, sprintf(
                'Монеты переведены. На персонажа %s зачислено %s адены.',
                isset($character['char_name']) ? $character['char_name'] : ('#' . $charId),
                number_format($adena, 0, '.', ' ')
            ));
        } catch (Exception $e) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, $e->getMessage());
        }

        $this->redirect(['index']);
    }

    /**
     * Кладёт адену в items_delayed — сервер выдаст её при входе персонажа.
     *
     * Состав колонок у сборок разный (owner_id / charId), поэтому пишем только
     * те поля, которые реально есть в таблице.
     *
     * @param object $l2     подключение к игровой БД
     * @param int    $charId
     * @param int    $adena
     *
     * @throws CException
     */
    private static function deliverAdena($l2, $charId, $adena)
    {
        $db = $l2->getDb();

        try {
            $columns = array_keys($db->getSchema()->getTable('items_delayed')->columns);
        } catch (Exception $e) {
            throw new CException('Эта сборка сервера не поддерживает выдачу предметов через сайт.');
        }

        $charIdField = in_array('owner_id', $columns, true) ? 'owner_id' : 'charId';

        $data = [
            $charIdField => (int) $charId,
            'item_id' => 57,
            'count' => (int) $adena,
        ];

        $extra = [
            'enchant_level' => 0,
            'attribute' => -1,
            'attribute_level' => -1,
            'flags' => 0,
            'payment_status' => 0,
            'payment_id' => 0,
            'variationId1' => 0,
            'variationId2' => 0,
            'description' => mb_substr('Пополнение баланса: Coin of Luck → адена', 0, 255),
        ];

        foreach ($extra as $field => $value) {
            if (in_array($field, $columns, true)) {
                $data[$field] = $value;
            }
        }

        $db->createCommand()->insert('items_delayed', $data);
    }
}
