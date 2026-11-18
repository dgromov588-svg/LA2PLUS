<?php

/**
 * Календарь событий: сезонный календарь наград за активность.
 *
 * Устройство:
 *  - сезон покупается один раз за Coin of Luck с кошелька игрока (общий кошелёк
 *    мультиаккаунта учитывается через WalletService);
 *  - сезон длится N дней от покупки, каждый день открывает по одной награде;
 *  - награда — предметы в игру: игрок выбирает персонажа, предметы попадают в
 *    items_delayed, и Lucera отдаёт их при следующем входе персонажа
 *    (так же работает выдача предметов из панели ГМ);
 *  - пропущенные дни НЕ сгорают: забрать можно любой уже открывшийся день
 *    в течение всего сезона;
 *  - повторный клик «Забрать» предмет второй раз не выдаст (уникальный ключ
 *    на пару «сезон + день»).
 */
class CalendarService
{
    const CLAIM_TABLE = '{{calendar_claims}}';

    /** @var array|null кеш наград в рамках запроса */
    private static $_rewards;

    /* ------------------------------------------------------------------ */
    /* Настройки                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * Параметры сезона.
     *
     * @return array
     */
    public static function getConfig($userId = null)
    {
        $price = (float) config('calendar.price');
        $days = (int) config('calendar.days');

        $price=$price>0?$price:100.0;
        if (class_exists('PortalRuntime') && PortalRuntime::enabled() && !user()->isGuest) {
            $discount=PortalRuntime::discount($userId===null?(int)user()->getId():(int)$userId);
            $price=PortalMoney::discounted((int)round($price*100),$discount,0)/100;
        }
        return [
            'price' => $price,
            'days' => $days > 0 ? $days : 30,
            'currency' => 'Coin of Luck',
        ];
    }

    /**
     * Награды по дням: день => [['item_id' => .., 'count' => .., 'enchant' => ..], …].
     *
     * Можно переопределить настройкой `calendar.rewards` (JSON), чтобы менять
     * состав наград без правки кода.
     *
     * @return array
     */
    public static function getRewards()
    {
        if (self::$_rewards !== null) {
            return self::$_rewards;
        }

        $custom = trim((string) config('calendar.rewards'));

        if ($custom !== '') {
            $parsed = json_decode($custom, true);

            if (is_array($parsed) && $parsed) {
                $rewards = [];

                foreach ($parsed as $day => $items) {
                    $rewards[(int) $day] = self::normalizeItems($items);
                }

                if ($rewards) {
                    return self::$_rewards = $rewards;
                }
            }
        }

        $config = self::getConfig();

        // Базовый набор наград (каждый элемент повторяется каждые 6 дней)
        $template = [
            1 => [['item_id' => 57, 'count' => 1000000]],     // Adena
            2 => [['item_id' => 1467, 'count' => 1000]],      // Soulshot (S-Grade)
            3 => [['item_id' => 3952, 'count' => 1000]],      // Blessed Spiritshot (S-Grade)
            4 => [['item_id' => 1539, 'count' => 10]],        // Greater Healing Potion
            5 => [['item_id' => 1540, 'count' => 10]],        // Quick Healing Potion
            6 => [['item_id' => 959, 'count' => 1]],          // Scroll: Enchant Weapon (S-Grade)
        ];

        // Премиум-дни: 7, 14, 21 и последний день сезона
        $premiumDays = [7, 14, 21, (int) $config['days']];
        $premiumItems = [
            ['item_id' => 960, 'count' => 2],              // Scroll: Enchant Armor (S-Grade)
            ['item_id' => 959, 'count' => 1],              // Scroll: Enchant Weapon (S-Grade)
            ['item_id' => 57, 'count' => 5000000],         // Adena
        ];

        $rewards = [];

        for ($day = 1; $day <= (int) $config['days']; $day++) {
            $base = $template[(($day - 1) % 6) + 1];
            $multiplier = $day > 6 ? 2 : 1;   // со второго круга награда больше
            $items = [];

            foreach ($base as $item) {
                $items[] = [
                    'item_id' => (int) $item['item_id'],
                    'count' => (int) $item['count'] * $multiplier,
                    'enchant' => 0,
                ];
            }

            if (in_array($day, $premiumDays, true)) {
                foreach ($premiumItems as $item) {
                    $items[] = [
                        'item_id' => (int) $item['item_id'],
                        'count' => (int) $item['count'],
                        'enchant' => 0,
                    ];
                }
            }

            $rewards[$day] = $items;
        }

        return self::$_rewards = $rewards;
    }

    /**
     * Премиум-дни сезона.
     *
     * @return int[]
     */
    public static function getPremiumDays()
    {
        $config = self::getConfig();

        return [7, 14, 21, (int) $config['days']];
    }

    /**
     * Приводит описание награды к списку предметов.
     *
     * @param mixed $items
     *
     * @return array
     */
    private static function normalizeItems($items)
    {
        $list = [];

        if (!is_array($items)) {
            return $list;
        }

        foreach ($items as $item) {
            if (is_array($item) && isset($item['item_id'])) {
                $list[] = [
                    'item_id' => (int) $item['item_id'],
                    'count' => max(1, (int) (isset($item['count']) ? $item['count'] : 1)),
                    'enchant' => max(0, (int) (isset($item['enchant']) ? $item['enchant'] : 0)),
                ];
            }
        }

        return $list;
    }

    /**
     * Названия предметов: [item_id => name].
     *
     * @param int[] $itemIds
     *
     * @return array
     */
    public static function getItemNames(array $itemIds)
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        if (!$itemIds) {
            return [];
        }

        try {
            $rows = db()->createCommand(
                "SELECT item_id, name FROM {{all_items}} WHERE item_id IN (" . implode(',', $itemIds) . ")"
            )->queryAll();
        } catch (Exception $e) {
            return [];
        }

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['item_id']] = $row['name'];
        }

        return $names;
    }

    /**
     * Иконки предметов: [item_id => url].
     *
     * @param int[] $itemIds
     *
     * @return array
     */
    public static function getItemIcons(array $itemIds)
    {
        $itemIds = array_values(array_unique(array_map('intval', $itemIds)));

        if (!$itemIds) {
            return [];
        }

        try {
            $rows = db()->createCommand(
                "SELECT item_id, icon FROM {{all_items}} WHERE item_id IN (" . implode(',', $itemIds) . ")"
            )->queryAll();
        } catch (Exception $e) {
            return [];
        }

        $root = Yii::getPathOfAlias('webroot');
        $icons = [];

        foreach ($rows as $row) {
            $file = 'images/items/' . strtolower((string) $row['icon']) . '.jpg';
            $icons[(int) $row['item_id']] = is_file($root . '/' . $file) ? '/' . $file : '';
        }

        return $icons;
    }

    /**
     * Данные награды с названиями и иконками — для шаблона.
     *
     * @param array $items
     *
     * @return array
     */
    public static function decorateItems(array $items)
    {
        $ids = array_column($items, 'item_id');
        $names = self::getItemNames($ids);
        $icons = self::getItemIcons($ids);

        foreach ($items as &$item) {
            $item['name'] = isset($names[$item['item_id']]) ? $names[$item['item_id']] : ('Предмет #' . $item['item_id']);
            $item['icon'] = isset($icons[$item['item_id']]) ? $icons[$item['item_id']] : '';
        }

        unset($item);

        return $items;
    }

    /* ------------------------------------------------------------------ */
    /* Сезон игрока                                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Действующий сезон игрока.
     *
     * @param int $userId
     *
     * @return array|null
     */
    public static function getActiveSeason($userId)
    {
        $row = db()->createCommand(
            "SELECT * FROM {{calendar_seasons}} WHERE user_id = :u AND ends_at > :now ORDER BY id DESC LIMIT 1"
        )->queryRow(true, [':u' => (int) $userId, ':now' => date('Y-m-d H:i:s')]);

        return $row ? $row : null;
    }

    /**
     * Последний сезон игрока (включая закончившийся).
     *
     * @param int $userId
     *
     * @return array|null
     */
    public static function getLastSeason($userId)
    {
        $row = db()->createCommand(
            "SELECT * FROM {{calendar_seasons}} WHERE user_id = :u ORDER BY id DESC LIMIT 1"
        )->queryRow(true, [':u' => (int) $userId]);

        return $row ? $row : null;
    }

    /**
     * Купить сезон.
     *
     * @param int $userId
     *
     * @return array ['season_id' => int, 'already' => bool]
     * @throws CException
     */
    public static function buy($userId)
    {
        $userId = (int) $userId;
        $config = self::getConfig($userId);

        $active = self::getActiveSeason($userId);

        if ($active) {
            return ['season_id' => (int) $active['id'], 'already' => true];
        }

        $balance = WalletService::getBalance($userId);

        if ($balance + 0.001 < $config['price']) {
            throw new CException(sprintf(
                'Недостаточно средств: календарь стоит %d %s, на кошельке %s. Пополните баланс.',
                (int) $config['price'],
                $config['currency'],
                formatCurrency($balance)
            ));
        }

        $tr = db()->beginTransaction();

        try {
            db()->createCommand('SELECT user_id FROM {{user_profiles}} WHERE user_id=:u FOR UPDATE')->queryScalar([':u'=>WalletService::ownerId($userId)]);
            $active=self::getActiveSeason($userId);
            if ($active) { $tr->commit(); return ['season_id'=>(int)$active['id'],'already'=>true]; }
            if ($config['price']>0) WalletService::debit($userId, $config['price'], ['comment' => 'Покупка календаря событий']);

            db()->createCommand()->insert('{{calendar_seasons}}', [
                'user_id' => $userId,
                'price' => $config['price'],
                'days' => $config['days'],
                'starts_at' => date('Y-m-d H:i:s'),
                'ends_at' => date('Y-m-d H:i:s', strtotime('+' . (int) $config['days'] . ' days')),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            $seasonId = (int) db()->getLastInsertID();
            $tr->commit();
        } catch (Exception $e) {
            $tr->rollback();

            throw $e;
        }

        if (class_exists('RoleService')) {
            RoleService::logAction('calendar.buy', user()->get('login'), [
                'season_id' => $seasonId,
                'price' => $config['price'],
            ]);
        }

        return ['season_id' => $seasonId, 'already' => false];
    }

    /**
     * Состояние дней сезона.
     *
     * @param array $season
     *
     * @return array ['days' => [...], 'claimed' => int, 'open' => int, 'finished' => bool]
     */
    public static function getDays(array $season)
    {
        $rewards = self::getRewards();
        $claimed = self::getClaimedDays((int) $season['id']);
        $premiumDays = self::getPremiumDays();

        $start = strtotime($season['starts_at']);
        $elapsed = (int) floor((time() - $start) / 86400) + 1;
        $finished = strtotime($season['ends_at']) <= time();
        $days = [];

        foreach ($rewards as $day => $items) {
            $days[$day] = [
                'day' => $day,
                'items' => $items,
                'open' => !$finished && $day <= $elapsed && $day <= (int) $season['days'],
                'claimed' => isset($claimed[$day]),
                'claimed_at' => isset($claimed[$day]) ? $claimed[$day] : null,
                'premium' => in_array($day, $premiumDays, true),
            ];
        }

        return [
            'days' => $days,
            'claimed' => count($claimed),
            'open' => $finished ? 0 : min(max($elapsed, 0), (int) $season['days']),
            'finished' => $finished,
        ];
    }

    /**
     * Забранные дни сезона: [day => claimed_at].
     *
     * @param int $seasonId
     *
     * @return array
     */
    public static function getClaimedDays($seasonId)
    {
        try {
            $rows = db()->createCommand(
                "SELECT day, claimed_at FROM " . self::CLAIM_TABLE . " WHERE season_id = :s"
            )->queryAll(true, [':s' => (int) $seasonId]);
        } catch (Exception $e) {
            return [];
        }

        $days = [];

        foreach ($rows as $row) {
            $days[(int) $row['day']] = $row['claimed_at'];
        }

        return $days;
    }

    /**
     * Забрать награду дня: выдаёт предметы выбранному персонажу.
     *
     * @param int    $userId
     * @param int    $day
     * @param string $charName
     *
     * @return array ['items' => [...], 'char_name' => string, 'day' => int]
     * @throws CException
     */
    public static function claimDay($userId, $day, $charName)
    {
        $userId = (int) $userId;
        $day = (int) $day;
        $charName = trim((string) $charName);

        $season = self::getActiveSeason($userId);

        if (!$season) {
            throw new CException('Календарь не куплен или сезон уже закончился.');
        }

        $state = self::getDays($season);

        if (!isset($state['days'][$day])) {
            throw new CException('Такого дня в календаре нет.');
        }

        if (!$state['days'][$day]['open']) {
            throw new CException('Этот день ещё не открылся.');
        }

        if ($state['days'][$day]['claimed']) {
            throw new CException('Награда за этот день уже получена.');
        }

        $char = self::findCharacter($userId, $charName);

        if (!$char) {
            throw new CException('Персонаж не найден на вашем игровом аккаунте. Выберите персонажа из списка.');
        }

        $items = $state['days'][$day]['items'];

        if (!$items) {
            throw new CException('Для этого дня не настроена награда.');
        }

        try {
            $l2 = l2('gs', user()->getGsId())->connect();
        } catch (Exception $e) {
            throw new CException('Игровой сервер сейчас недоступен, попробуйте позже.');
        }

        $tr = db()->beginTransaction();

        try {
            // Занимаем день: уникальный ключ (season_id, day) не даст забрать дважды
            db()->createCommand()->insert(self::CLAIM_TABLE, [
                'season_id' => (int) $season['id'],
                'user_id' => $userId,
                'day' => $day,
                'char_id' => (int) $char['char_id'],
                'char_name' => (string) $char['char_name'],
                'items' => json_encode($items, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
                'claimed_at' => date('Y-m-d H:i:s'),
            ]);

            if (class_exists('PortalRuntime') && PortalRuntime::enabled() && PortalRuntime::store()->enabled('warehouse')) {
                foreach ($items as $i=>$item) PortalRuntime::store()->grantItem($userId,(int)user()->getGsId(),[
                    'item_id'=>$item['item_id'],'quantity'=>$item['count'],'enchant'=>$item['enchant']??0,'name'=>$item['name']??('Предмет #'.$item['item_id']),
                ],'calendar:'.$season['id'].':'.$day.':'.$i);
            } else self::deliverItems($l2, (int) $char['char_id'], $items);

            $tr->commit();
        } catch (CDbException $e) {
            $tr->rollback();

            if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
                throw new CException('Награда за этот день уже получена.');
            }

            throw $e;
        } catch (Exception $e) {
            $tr->rollback();

            throw $e;
        }

        $items = self::decorateItems($items);

        if (class_exists('RoleService')) {
            RoleService::logAction('calendar.claim', user()->get('login'), [
                'season_id' => (int) $season['id'],
                'day' => $day,
                'char_name' => (string) $char['char_name'],
            ]);
        }

        return ['items' => $items, 'char_name' => (string) $char['char_name'], 'day' => $day];
    }

    /**
     * Кладёт предметы в items_delayed — сервер отдаст их при входе персонажа.
     *
     * @param Lineage $l2
     * @param int     $charId
     * @param array   $items
     *
     * @return void
     * @throws CException
     */
    private static function deliverItems($l2, $charId, array $items)
    {
        $db = $l2->getDb();

        try {
            $columns = array_keys($db->getSchema()->getTable('items_delayed')->columns);
        } catch (Exception $e) {
            throw new CException('Эта сборка сервера не поддерживает выдачу предметов через сайт.');
        }

        $charIdField = isset($columns['charId']) && !isset($columns['owner_id']) ? 'charId' : 'owner_id';
        $who = (string) (user()->get('login') ?: 'site');
        $inserted = 0;

        foreach ($items as $item) {
            $data = [
                $charIdField => (int) $charId,
                'item_id' => (int) $item['item_id'],
                'count' => (int) $item['count'],
            ];

            $extra = [
                'enchant_level' => (int) $item['enchant'],
                'attribute' => -1,
                'attribute_level' => -1,
                'flags' => 0,
                'payment_status' => 0,
                'payment_id' => 0,
                'variationId1' => 0,
                'variationId2' => 0,
                'description' => mb_substr('Календарь: ' . $who, 0, 255),
            ];

            foreach ($extra as $field => $value) {
                if (in_array($field, $columns, true)) {
                    $data[$field] = $value;
                }
            }

            $db->createCommand()->insert('items_delayed', $data);
            $inserted++;
        }

        if ($inserted !== count($items)) {
            throw new CException('Сервер принял не все предметы. Попробуйте ещё раз.');
        }
    }

    /**
     * Персонаж игрока по имени (в пределах его активного игрового аккаунта).
     *
     * @param int    $userId
     * @param string $charName
     *
     * @return array|null
     */
    public static function findCharacter($userId, $charName)
    {
        $login = AccountLinkService::activeGameLogin($userId);

        if ($login === '' || $charName === '') {
            return null;
        }

        try {
            $db = l2('gs', user()->getGsId())->connect()->getDb();
            $table = $db->getSchema()->getTable('characters');
            $idField = isset($table->columns['obj_Id']) ? 'obj_Id' : 'charId';

            return $db->createCommand(
                "SELECT " . $idField . " AS char_id, char_name FROM {{characters}} WHERE account_name = :a AND char_name = :n LIMIT 1"
            )->queryRow(true, [':a' => $login, ':n' => $charName]) ?: null;
        } catch (Exception $e) {
            Yii::log('CalendarService::findCharacter — ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'calendar');

            return null;
        }
    }

    /**
     * Персонажи игрока — для выпадающего списка.
     *
     * @param int $userId
     *
     * @return array
     */
    public static function getCharacters($userId)
    {
        $login = AccountLinkService::activeGameLogin($userId);

        if ($login === '') {
            return [];
        }

        try {
            $db = l2('gs', user()->getGsId())->connect()->getDb();
            $table = $db->getSchema()->getTable('characters');
            $idField = isset($table->columns['obj_Id']) ? 'obj_Id' : 'charId';

            return $db->createCommand(
                "SELECT " . $idField . " AS char_id, char_name FROM {{characters}} WHERE account_name = :a ORDER BY " . $idField
            )->queryAll(true, [':a' => $login]);
        } catch (Exception $e) {
            Yii::log('CalendarService::getCharacters — ' . $e->getMessage(), CLogger::LEVEL_ERROR, 'calendar');

            return [];
        }
    }
}
