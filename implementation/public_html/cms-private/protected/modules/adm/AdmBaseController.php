<?php

/**
 * LA2PLUS • админка v4: снимок состояния и действия.
 *
 * SPA в /admin/ просит один снимок (actionState) и дальше живёт им: так же,
 * как прототип жил объектом в localStorage. Отличие одно — всё настоящее:
 * игроки и балансы из MySQL, персонажи и аккаунты из базы Lucera2,
 * аналитика из visit_log.
 *
 * Запись (выдача предмета, бан, роли, баланс, ответ в тикете) проходит здесь
 * же и пишется в журнал через RoleService::logAction.
 */
class AdmBaseController extends AdmApiController
{
    /* ================================================================== */
    /* Снимок состояния                                                    */
    /* ================================================================== */

    /**
     * Всё, что нужно интерфейсу, одним ответом.
     */
    public function actionState()
    {
        /* Чтение снимка: разделы внутри отдаются по правам, но сам вход
           в панель тоже проверяем — так у каждого действия есть строка
           с правом, и карта действий совпадает с кодом один в один. */
        $this->need($this->permissionOf('state'));

        $me = $this->me();

        $this->ok(array(
            'me' => $me,
            'cabinetSections' => RoleService::isSiteOwner() ? $this->cabinetSections() : array(),
            'server' => $this->snapshot(),
            'players' => $this->has('users.view') ? $this->players() : array(),
            'accounts' => $this->has('server.view') ? $this->accounts() : array(),
            'characters' => $this->has('server.view') ? $this->characters() : array(),
            'clans' => $this->has('server.view') ? $this->clans() : array(),
            'payments' => $this->has('finance.view') ? $this->payments() : array(),
            'walletOps' => $this->has('finance.view') ? $this->walletOps() : array(),
            'tickets' => $this->has('tickets.view') ? $this->tickets() : array(),
            'log' => $this->has('log.view') ? $this->log() : array(),
            'itemQueue' => $this->has('characters.view') ? $this->itemQueue() : array(),
            'bans' => $this->has('server.view') ? $this->bans() : array(),
            'settings' => $this->has('settings.view') || $this->has('finance.view') ? $this->settings() : array('gateways' => array(), 'bonuses' => array()),
            'staff' => $this->has('panel.view') ? $this->staff() : array(),
            'owners' => RoleService::getOwnerIds(),
            /* Единая карта «действие → право»: интерфейс больше не держит свой
               список, а спрашивает сервер, что чем защищено. */
            'actions' => AdmApiController::actionPermissions(),
            'analytics' => $this->has('analytics.view') ? $this->analytics() : null,
            'roles' => $this->rolesMeta(),
            'builtAt' => time() * 1000,
        ));
    }

    /**
     * Настройки проекта: сохранение из панели.
     *
     * Значения живут в ca-config.php/params.php. Запись туда из веб-панели —
     * отдельная задача с бэкапом файла, поэтому здесь отказ с объяснением,
     * а не «сохранено» на экране при неизменном конфиге.
     */
    private function cabinetSections()
    {
        if(!class_exists('PortalRuntime')||!PortalRuntime::enabled())return array();
        $sections=PortalRuntime::sections();$rows=array();
        foreach(CabinetSections::catalog() as $key=>$info)$rows[]=array('key'=>$key,'label'=>$info['label'][0],'description'=>$info['description'],'enabled'=>$sections->enabled($key));
        return $rows;
    }
    public function actionCabinetSectionToggle()
    {
        $this->need($this->permissionOf('cabinetSectionToggle'));
        if(!RoleService::isSiteOwner())$this->fail('Только владелец проекта.',403);
        if(!request()->getIsPostRequest())$this->fail('Требуется POST.',405);
        if(!PortalRuntime::enabled())$this->fail('Новый кабинет не включён.',503);
        $data=$this->input();
        try{
            $result=PortalRuntime::sections()->set((int)user()->getId(),(string)($data['section']??''),(string)($data['enabled']??''));
            if($result['changed'])RoleService::logAction('cabinet.section.changed',$result['section'],array('enabled'=>$result['enabled']));
            $this->ok(array('section'=>$result['section'],'enabled'=>$result['enabled'],'changed'=>$result['changed']));
        }catch(PDOException $e){Yii::log('Cabinet section setting failed',CLogger::LEVEL_ERROR,'adm');$this->fail('Не удалось сохранить настройку.',500);}
        catch(Throwable $e){$this->fail($e->getMessage(),400);}
    }

    public function actionSettingsSave()
    {
        $this->need('settings.view');
        if(!request()->getIsPostRequest())$this->fail('Требуется POST.',405);
        $allowed=array('trybit.allow','trybit.test','nowpayments.allow','utopay.rate','wayforpay.allow','wayforpay.test','payop.allow','paypalych.allow','utopay.allow','manual.allow','payments.debug','donation.free_week.until');
        $input=$this->input();$values=array();
        foreach($allowed as $key){
            $name='cfg_'.str_replace('.','_',$key);
            if(!array_key_exists($name,$input)||$input[$name]===null)continue;
            if(!is_scalar($input[$name]))$this->fail('Неверное значение настройки.',400);
            $value=is_bool($input[$name])?($input[$name]?'1':'0'):trim((string)$input[$name]);
            if(preg_match('/\.(allow|test|debug)$/D',$key)&&!in_array($value,array('0','1'),true))$this->fail('Переключатель должен быть 0 или 1.',400);
            if($key==='utopay.rate'&&(!preg_match('/^[0-9]{1,5}(?:\.[0-9]{1,4})?$/D',$value)||(float)$value<=0))$this->fail('Цена CoL: положительное число, не больше четырёх знаков после точки.',400);
            if($key==='donation.free_week.until'&&$value!==''&&(!preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}(?::[0-9]{2})?$/D',$value)||strtotime($value)===false))$this->fail('Неверная дата окончания акции.',400);
            $values[$key]=$value;
        }
        $changes=array();$transaction=db()->beginTransaction();
        try{
            foreach($values as $key=>$value){
                $row=db()->createCommand('SELECT id,value FROM {{config}} WHERE param=:p LIMIT 1')->queryRow(true,array(':p'=>$key));
                if(!$row||(string)$row['value']===$value)continue;
                db()->createCommand()->update('{{config}}',array('value'=>$value,'updated_at'=>date('Y-m-d H:i:s')),'id=:id',array(':id'=>(int)$row['id']));
                $changes[$key]=array('from'=>$row['value'],'to'=>$value);
            }
            $transaction->commit();
        }catch(Throwable $e){$transaction->rollback();Yii::log('Admin settings update failed',CLogger::LEVEL_ERROR,'adm');$this->fail('Не удалось сохранить настройки.',500);}
        if($changes){DbConfig::flushCache();RoleService::logAction('settings.save','config',array('changes'=>$changes));}
        $this->ok(array('changed'=>$changes,'message'=>$changes?'Настройки сохранены':'Изменений нет'));
    }

    /**
     * Пульс шапки: онлайн и открытые тикеты. Дёшево, раз в 30 секунд.
     */
    public function actionPulse()
    {
        $this->need($this->permissionOf('pulse'));

        $online = 0;
        $ticketsOpen = 0;

        try {
            $online = (int) $this->game()->getDb()
                ->createCommand('SELECT COUNT(*) FROM characters WHERE online = 1')->queryScalar();
        } catch (Exception $e) {
            $online = 0;
        }

        try {
            $ticketsOpen = (int) db()->createCommand('SELECT COUNT(*) FROM {{tickets}} WHERE status = 0')->queryScalar();
        } catch (Exception $e) {
            $ticketsOpen = 0;
        }

        $this->ok(array('online' => $online, 'ticketsOpen' => $ticketsOpen, 'at' => time() * 1000));
    }

    /**
     * Кто я: логин, роль, права.
     *
     * @return array
     */
    protected function me()
    {
        $user = db()->createCommand(
            'SELECT user_id, login, email FROM {{users}} WHERE user_id = :u LIMIT 1'
        )->queryRow(true, array(':u' => (int) user()->getId()));

        return array(
            'id' => (int) user()->getId(),
            'login' => $user ? $user['login'] : '',
            'email' => $user ? $user['email'] : '',
            'role' => $this->role,
            'roleName' => $this->roleName($this->role),
            'isOwner' => RoleService::isSiteOwner(),
            'canManageOwners' => RoleService::canManageOwners(),
            'perms' => $this->perms,
            'gsId' => (int) user()->getGsId(),
            'lsId' => (int) user()->getLsId(),
            'balance' => WalletService::getBalance(user()->getId()),
        );
    }

    /**
     * Сводка по серверу для пульта и шапки.
     *
     * @return array
     */
    protected function snapshot()
    {
        $users = (int) db()->createCommand('SELECT COUNT(*) FROM {{users}}')->queryScalar();
        /* Баланс игроков — только кошельки живых аккаунтов. На сайте есть
           профили удалённых пользователей (в users их уже нет): их монеты
           лежат отдельной цифрой, иначе пульт расходится со списком игроков. */
        $balance = (float) db()->createCommand(
            'SELECT SUM(p.balance) FROM {{user_profiles}} p JOIN {{users}} u ON u.user_id = p.user_id'
        )->queryScalar();

        $orphan = db()->createCommand(
            'SELECT COUNT(*) AS n, SUM(p.balance) AS s FROM {{user_profiles}} p
             LEFT JOIN {{users}} u ON u.user_id = p.user_id
             WHERE u.user_id IS NULL'
        )->queryRow(true);
        $ticketsOpen = (int) db()->createCommand('SELECT COUNT(*) FROM {{tickets}} WHERE status = 0')->queryScalar();

        $online = 0;
        $charactersOnline = 0;
        $characters = 0;
        $accounts = 0;
        $clans = 0;
        $gameOk = false;

        try {
            $gdb = $this->game()->getDb();
            $characters = (int) $gdb->createCommand('SELECT COUNT(*) FROM characters')->queryScalar();
            $charactersOnline = (int) $gdb->createCommand('SELECT COUNT(*) FROM characters WHERE online = 1')->queryScalar();
            $accounts = (int) $gdb->createCommand('SELECT COUNT(*) FROM accounts')->queryScalar();

            try {
                $clans = (int) $gdb->createCommand('SELECT COUNT(*) FROM clan_data')->queryScalar();
            } catch (Exception $e) {
                $clans = 0;
            }

            $gameOk = true;
        } catch (Exception $e) {
            $gameOk = false;
        } catch (Throwable $e) {
            $gameOk = false;
        }

        try {
            $online = AnalyticsService::getOnlineCount(15);
        } catch (Exception $e) {
            $online = 0;
        }

        /* «Всего аккаунтов сайта» — users, а не accounts игрового сервера:
           раньше пульт показывал 192 аккаунта при 173 пользователях. */
        $accountsTotal = (int) db()->createCommand('SELECT COUNT(*) FROM {{users}}')->queryScalar();

        $payToday = (float) db()->createCommand(
            'SELECT SUM(count) FROM {{transactions}} WHERE status = 1 AND created_at >= :d'
        )->queryScalar(array(':d' => date('Y-m-d 00:00:00')));

        return array(
            'users' => $users,
            'players' => $users,
            'accountsTotal' => isset($accountsTotal) ? $accountsTotal : 0,
            'balance' => round($balance, 2),
            'balanceOrphan' => round((float) (isset($orphan['s']) ? $orphan['s'] : 0), 2),
            'walletOrphans' => (int) (isset($orphan['n']) ? $orphan['n'] : 0),
            'ticketsOpen' => $ticketsOpen,
            'online' => $online,
            'characters' => $characters,
            'charactersOnline' => $charactersOnline,
            'accounts' => $accounts,
            'clans' => $clans,
            'gameOk' => $gameOk,
            'payToday' => round((float) $payToday, 2),
            'gameVersion' => 'Lucera2',
        );
    }

    /* ================================================================== */
    /* Игроки                                                              */
    /* ================================================================== */

    /**
     * Игроки сайта с балансом, ролью и числом аккаунтов.
     *
     * @return array
     */
    protected function players()
    {
        $rows = db()->createCommand(
            'SELECT u.user_id, u.login, u.email, u.activated, u.role AS site_role, u.created_at,
                    u.registration_ip, p.balance, p.phone
             FROM {{users}} u
             LEFT JOIN {{user_profiles}} p ON p.user_id = u.user_id
             ORDER BY u.user_id ASC
             LIMIT 3000'
        )->queryAll();

        $roles = $this->staffMap();
        $owners = array_flip(array_map('intval', RoleService::getOwnerIds()));

        $accountsByUser = array();

        foreach (db()->createCommand('SELECT user_id, game_login FROM {{account_links}} WHERE status = 1')->queryAll() as $link) {
            $accountsByUser[(int) $link['user_id']][] = $link['game_login'];
        }

        $lastLogin = array();

        foreach (db()->createCommand(
            'SELECT user_id, MAX(created_at) AS last_at FROM {{users_auth_logs}} GROUP BY user_id'
        )->queryAll() as $row) {
            $lastLogin[(int) $row['user_id']] = $row['last_at'];
        }

        $out = array();

        foreach ($rows as $r) {
            $id = (int) $r['user_id'];
            $role = isset($roles[$id]) ? $roles[$id] : 'user';

            if (isset($owners[$id])) {
                $role = RoleService::ROLE_OWNER;
            }

            $out[] = array(
                'id' => $id,
                'login' => $r['login'],
                'email' => $r['email'],
                'balance' => round((float) $r['balance'], 2),
                'role' => $role === 'user' ? null : $role,
                'createdAt' => $this->ms($r['created_at']),
                'lastLogin' => isset($lastLogin[$id]) ? $this->ms($lastLogin[$id]) : null,
                'ip' => $r['registration_ip'],
                'status' => (int) $r['activated'] === 1 ? 'active' : 'blocked',
                'groups' => 0,
                'accounts' => isset($accountsByUser[$id]) ? $accountsByUser[$id] : array(),
            );
        }

        return $out;
    }

    /* ================================================================== */
    /* Игровой сервер                                                      */
    /* ================================================================== */

    /**
     * Игровые аккаунты Lucera2.
     *
     * @return array
     */
    protected function accounts()
    {
        try {
            $db = $this->game()->getDb();

            $rows = $db->createCommand(
                'SELECT a.login, a.lastactive, a.accessLevel, a.lastIP, a.ban_expire, a.l2email,
                        (SELECT COUNT(*) FROM characters c WHERE c.account_name = a.login) AS chars,
                        (SELECT COUNT(*) FROM characters c WHERE c.account_name = a.login AND c.online = 1) AS online_now
                 FROM accounts a
                 ORDER BY a.lastactive DESC
                 LIMIT 2000'
            )->queryAll();
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $links = array();

        foreach (db()->createCommand('SELECT game_login, user_id FROM {{account_links}} WHERE status = 1')->queryAll() as $l) {
            $links[strtolower($l['game_login'])] = (int) $l['user_id'];
        }

        $out = array();

        foreach ($rows as $r) {
            $until = $this->banUntil($r['ban_expire']);

            $out[] = array(
                'login' => $r['login'],
                'playerId' => isset($links[strtolower($r['login'])]) ? $links[strtolower($r['login'])] : null,
                'lastLogin' => $this->ms($r['lastactive']) ? $this->ms($r['lastactive']) : ((int) $r['lastactive'] * 1000),
                'ip' => $r['lastIP'],
                'accessLevel' => (int) $r['accessLevel'],
                'banned' => $until !== null,
                'banUntil' => $until,
                'chars' => (int) $r['chars'],
                'online' => (int) $r['online_now'] > 0,
            );
        }

        return $out;
    }

    /**
     * Персонажи Lucera2 с кланами.
     *
     * @return array
     */
    protected function characters()
    {
        try {
            $db = $this->game()->getDb();

            /*
             * Схема Lucera2 отличается от классической L2J: название клана
             * лежит в clan_subpledges (type = 0 — основной клан), а уровень и
             * класс персонажа — в character_subclasses (active = 1 — текущий).
             */
            $clanNames = array();

            try {
                foreach ($db->createCommand('SELECT clan_id, name FROM clan_subpledges WHERE type = 0')->queryAll() as $c) {
                    $clanNames[(int) $c['clan_id']] = $c['name'];
                }
            } catch (Exception $e) {
                $clanNames = array();
            }

            $subs = array();

            try {
                foreach ($db->createCommand(
                    'SELECT char_obj_id, level, class_id FROM character_subclasses WHERE active = 1'
                )->queryAll() as $s) {
                    $subs[(int) $s['char_obj_id']] = $s;
                }
            } catch (Exception $e) {
                $subs = array();
            }

            $rows = $db->createCommand(
                'SELECT obj_Id, char_name, account_name, clanid, pvpkills, pkkills, karma, online, lastAccess
                 FROM characters
                 ORDER BY online DESC, obj_Id DESC
                 LIMIT 3000'
            )->queryAll();
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $out = array();

        foreach ($rows as $r) {
            $clanId = (int) $r['clanid'];

            $objId = (int) $r['obj_Id'];
            $sub = isset($subs[$objId]) ? $subs[$objId] : null;

            $out[] = array(
                'id' => $objId,
                'name' => $r['char_name'],
                'account' => $r['account_name'],
                'playerId' => null,
                'level' => $sub ? (int) $sub['level'] : 0,
                'cls' => $sub ? $this->className($sub['class_id']) : '',
                'classId' => $sub ? (int) $sub['class_id'] : 0,
                'clan' => $clanId > 0 && isset($clanNames[$clanId]) ? $clanNames[$clanId] : '',
                'pvp' => (int) $r['pvpkills'],
                'pk' => (int) $r['pkkills'],
                'karma' => (int) $r['karma'],
                'online' => (int) $r['online'] === 1,
                'lastSeen' => (int) $r['lastAccess'] * 1000,
            );
        }

        return $out;
    }

    /**
     * Кланы: состав, лидер, репутация, замок.
     *
     * @return array
     */
    protected function clans()
    {
        try {
            $db = $this->game()->getDb();

            $rows = $db->createCommand(
                'SELECT clan_id, clan_level, reputation_score, hasCastle, ally_id
                 FROM clan_data ORDER BY reputation_score DESC LIMIT 500'
            )->queryAll();

            // Имена кланов и лидеры — в clan_subpledges, союз — в ally_data.
            $names = array();
            $leaders = array();

            foreach ($db->createCommand('SELECT clan_id, name, leader_id FROM clan_subpledges WHERE type = 0')->queryAll() as $c) {
                $names[(int) $c['clan_id']] = $c['name'];
                $leaders[(int) $c['clan_id']] = (int) $c['leader_id'];
            }

            $leaderNames = array();

            foreach ($db->createCommand(
                'SELECT obj_Id, char_name FROM characters WHERE obj_Id IN (' .
                (count($leaders) ? implode(',', array_map('intval', $leaders)) : '0') . ')'
            )->queryAll() as $c) {
                $leaderNames[(int) $c['obj_Id']] = $c['char_name'];
            }

            $allies = array();

            try {
                foreach ($db->createCommand('SELECT ally_id, ally_name FROM ally_data')->queryAll() as $a) {
                    $allies[(int) $a['ally_id']] = $a['ally_name'];
                }
            } catch (Exception $e) {
                $allies = array();
            }

            $members = array();

            foreach ($db->createCommand('SELECT clanid, COUNT(*) c FROM characters WHERE clanid > 0 GROUP BY clanid')->queryAll() as $m) {
                $members[(int) $m['clanid']] = (int) $m['c'];
            }
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $castles = array(1 => 'Гиран', 2 => 'Дион', 3 => 'Годдард', 4 => 'Иннадрил', 5 => 'Орен',
            6 => 'Аден', 7 => 'Руна', 8 => 'Шуттгарт', 9 => 'Глудио', 10 => 'Хейн');

        $out = array();

        foreach ($rows as $r) {
            $castleId = (int) $r['hasCastle'];

            $clanId = (int) $r['clan_id'];
            $leaderId = isset($leaders[$clanId]) ? $leaders[$clanId] : 0;

            $out[] = array(
                'id' => $clanId,
                'name' => isset($names[$clanId]) ? $names[$clanId] : ('Клан #' . $clanId),
                'leader' => isset($leaderNames[$leaderId]) ? $leaderNames[$leaderId] : '',
                'level' => (int) $r['clan_level'],
                'members' => isset($members[(int) $r['clan_id']]) ? $members[(int) $r['clan_id']] : 0,
                'rep' => (int) $r['reputation_score'],
                'castle' => $castleId > 0 && isset($castles[$castleId]) ? $castles[$castleId] : '',
                'ally' => isset($allies[(int) $r['ally_id']]) ? $allies[(int) $r['ally_id']] : '',
                'created' => null,
            );
        }

        return $out;
    }

    /**
     * Действующие баны игровых аккаунтов.
     *
     * @return array
     */
    protected function bans()
    {
        try {
            $rows = $this->game()->getDb()->createCommand(
                'SELECT login, ban_expire, lastIP FROM accounts WHERE ban_expire > :now ORDER BY ban_expire DESC LIMIT 500'
            )->queryAll(false, array(':now' => time()));
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $out = array();

        foreach ($rows as $r) {
            $out[] = array(
                'id' => $r['login'],
                'login' => $r['login'],
                'until' => (int) $r['ban_expire'] * 1000,
                'reason' => '',
                'by' => '',
                'at' => null,
                'ip' => $r['lastIP'],
            );
        }

        return $out;
    }

    /* ================================================================== */
    /* Деньги                                                              */
    /* ================================================================== */

    /**
     * Заявки на пополнение.
     *
     * @return array
     */
    protected function payments()
    {
        $rows = db()->createCommand(
            'SELECT t.id, t.payment_system, t.user_id, t.sum, t.count, t.status, t.created_at, t.updated_at, t.params, u.login
             FROM {{transactions}} t
             LEFT JOIN {{users}} u ON u.user_id = t.user_id
             ORDER BY t.id DESC LIMIT 500'
        )->queryAll();

        $systems = array('0' => 'Не указан', '5' => 'WayForPay', '6' => 'UTOPAY');

        $out = array();

        foreach ($rows as $r) {
            $status = (int) $r['status'];

            $out[] = array(
                'id' => (int) $r['id'],
                'player' => $r['login'] !== null ? $r['login'] : ('#' . $r['user_id']),
                'playerId' => (int) $r['user_id'],
                'coins' => (int) $r['count'],
                'usd' => round((float) $r['sum'], 2),
                'gateway' => isset($systems[(string) $r['payment_system']]) ? $systems[(string) $r['payment_system']] : ('Шлюз ' . $r['payment_system']),
                'status' => $status === 1 ? 'paid' : ($status === 2 ? 'canceled' : 'pending'),
                'createdAt' => $this->ms($r['created_at']),
                'updatedAt' => $this->ms($r['updated_at']),
                'invoice' => 'TX-' . $r['id'],
                'testMode' => false,
                'txList' => array(),
                'credited' => $status === 1 ? (int) $r['count'] : 0,
                'note' => (string) $r['params'],
            );
        }

        return $out;
    }

    /**
     * Очередь выдачи предметов: строки items_delayed, которые сервер ещё не
     * отдал персонажу. Это и есть «что выдано и ждёт входа в игру».
     *
     * @return array
     */
    protected function itemQueue()
    {
        try {
            $rows = $this->gameDb()->createCommand(
                'SELECT payment_id, owner_id, item_id, count, enchant_level, payment_status, description
                 FROM items_delayed
                 WHERE payment_status = 1
                 ORDER BY payment_id DESC LIMIT 200'
            )->queryAll();
        } catch (Exception $e) {
            return array();
        } catch (Throwable $e) {
            return array();
        }

        $names = array();

        try {
            foreach ($this->gameDb()->createCommand(
                'SELECT obj_Id, char_name, account_name FROM characters WHERE obj_Id IN (' .
                (count($rows) ? implode(',', array_map(function ($r) { return (int) $r['owner_id']; }, $rows)) : '0') . ')'
            )->queryAll() as $c) {
                $names[(int) $c['obj_Id']] = $c;
            }
        } catch (Exception $e) {
            $names = array();
        }

        $out = array();

        foreach ($rows as $r) {
            $owner = (int) $r['owner_id'];
            $who = isset($names[$owner]) ? $names[$owner] : null;

            $out[] = array(
                'id' => (int) $r['payment_id'],
                'login' => $who ? $who['account_name'] : ('#' . $owner),
                'char' => $who ? $who['char_name'] : ('obj_Id ' . $owner),
                'itemId' => (int) $r['item_id'],
                'itemName' => 'ID ' . $r['item_id'],
                'count' => (int) $r['count'],
                'enchant' => (int) $r['enchant_level'],
                'reason' => (string) $r['description'],
                'at' => time() * 1000,
                'done' => false,
            );
        }

        return $out;
    }

    /**
     * Движение по кошелькам.
     *
     * @return array
     */
    protected function walletOps()
    {
        $rows = db()->createCommand(
            'SELECT l.id, l.owner_id, l.user_id, l.type, l.amount, l.balance_after, l.comment, l.created_at, u.login
             FROM {{wallet_ledger}} l
             LEFT JOIN {{users}} u ON u.user_id = l.user_id
             ORDER BY l.id DESC LIMIT 300'
        )->queryAll();

        $out = array();

        foreach ($rows as $r) {
            $out[] = array(
                'id' => (int) $r['id'],
                'player' => $r['login'] !== null ? $r['login'] : ('#' . $r['user_id']),
                'type' => $r['type'],
                'amount' => round((float) $r['amount'], 2),
                'comment' => $r['comment'],
                'by' => 'система',
                'at' => $this->ms($r['created_at']),
                'after' => round((float) $r['balance_after'], 2),
            );
        }

        return $out;
    }

    /* ================================================================== */
    /* Тикеты                                                              */
    /* ================================================================== */

    /**
     * Тикеты с перепиской.
     *
     * @return array
     */
    protected function tickets()
    {
        $rows = db()->createCommand(
            'SELECT t.id, t.user_id, t.category_id, t.priority, t.title, t.status, t.created_at, t.updated_at,
                    t.char_name, u.login, c.title AS category
             FROM {{tickets}} t
             LEFT JOIN {{users}} u ON u.user_id = t.user_id
             LEFT JOIN {{tickets_categories}} c ON c.id = t.category_id
             ORDER BY t.id DESC LIMIT 300'
        )->queryAll();

        $answers = array();

        foreach (db()->createCommand('SELECT ticket_id, text, user_id, created_at FROM {{tickets_answers}} ORDER BY id ASC')->queryAll() as $a) {
            $answers[(int) $a['ticket_id']][] = $a;
        }

        $staff = $this->staffMap();

        $out = array();

        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $messages = array();

            foreach (isset($answers[$id]) ? $answers[$id] : array() as $a) {
                $authorId = (int) $a['user_id'];
                $messages[] = array(
                    'by' => isset($staff[$authorId]) ? $this->loginOf($authorId) : ($r['login'] ? $r['login'] : 'игрок'),
                    'staff' => isset($staff[$authorId]),
                    'at' => $this->ms($a['created_at']),
                    'text' => $a['text'],
                );
            }

            $out[] = array(
                'id' => $id,
                'player' => $r['login'] !== null ? $r['login'] : ('#' . $r['user_id']),
                'playerId' => (int) $r['user_id'],
                'subject' => $r['title'],
                'category' => $r['category'],
                'charName' => $r['char_name'],
                'status' => (int) $r['status'] === 1 ? 'closed' : 'open',
                'priority' => (int) $r['priority'] >= 2 ? 'high' : 'normal',
                'createdAt' => $this->ms($r['created_at']),
                'updatedAt' => $this->ms($r['updated_at']),
                'messages' => $messages,
            );
        }

        return $out;
    }

    /**
     * Логин по ID пользователя.
     *
     * @param int $userId
     *
     * @return string
     */
    protected function loginOf($userId)
    {
        static $cache = array();

        $userId = (int) $userId;

        if (!isset($cache[$userId])) {
            $user = db()->createCommand(
                'SELECT user_id, login, email FROM {{users}} WHERE user_id = :u LIMIT 1'
            )->queryRow(true, array(':u' => $userId));
            $cache[$userId] = $user ? $user['login'] : ('#' . $userId);
        }

        return $cache[$userId];
    }

    /* ================================================================== */
    /* Журнал, команда, аналитика                                          */
    /* ================================================================== */

    /**
     * Журнал действий: staff_log + смена ролей + кошельки.
     *
     * @return array
     */
    protected function log()
    {
        $out = array();

        foreach (db()->createCommand('SELECT id, actor_id, actor_role, action, target_login, params, ip, created_at FROM {{staff_log}} ORDER BY id DESC LIMIT 300')->queryAll() as $r) {
            $out[] = array(
                'id' => 's' . $r['id'],
                'at' => $this->ms($r['created_at']),
                'actor' => $this->loginOf($r['actor_id']),
                'actorRole' => $r['actor_role'],
                'action' => $r['action'],
                'target' => $r['target_login'],
                'details' => (string) $r['params'],
                'ip' => $r['ip'],
                'source' => 'staff',
            );
        }

        foreach (db()->createCommand('SELECT id, user_id, role_before, role_after, actor_id, comment, created_at FROM {{role_log}} ORDER BY id DESC LIMIT 100')->queryAll() as $r) {
            $out[] = array(
                'id' => 'r' . $r['id'],
                'at' => $this->ms($r['created_at']),
                'actor' => $this->loginOf($r['actor_id']),
                'actorRole' => '',
                'action' => 'Смена роли',
                'target' => $this->loginOf($r['user_id']),
                'details' => $r['role_before'] . ' → ' . $r['role_after'] . ($r['comment'] !== '' ? ' · ' . $r['comment'] : ''),
                'ip' => '',
                'source' => 'roles',
            );
        }

        usort($out, function ($a, $b) {
            return (int) $b['at'] - (int) $a['at'];
        });

        return array_slice($out, 0, 300);
    }

    /**
     * Команда проекта.
     *
     * @return array
     */
    protected function staff()
    {
        $map = $this->staffMap();
        $owners = array_flip(array_map('intval', RoleService::getOwnerIds()));

        $out = array();

        foreach ($map as $userId => $role) {
            $user = db()->createCommand(
                'SELECT user_id, login, email FROM {{users}} WHERE user_id = :u LIMIT 1'
            )->queryRow(true, array(':u' => $userId));

            if (!$user) {
                continue;
            }

            $out[] = array(
                'id' => (int) $userId,
                'login' => $user ? $user['login'] : ('#' . $userId),
                'email' => $user ? $user['email'] : '',
                'role' => isset($owners[(int) $userId]) ? RoleService::ROLE_OWNER : $role,
                'isOwner' => isset($owners[(int) $userId]),
                'balance' => WalletService::getBalance($userId),
            );
        }

        return $out;
    }

    /**
     * Настройки проекта (только чтение: их правит владелец в конфиге).
     *
     * @return array
     */
    protected function settings()
    {
        /*
         * Настройки читаются из таблицы config (ghtweb_config), там же их
         * правит экран «Настройки». Раньше панель смотрела в config() и
         * всегда показывала «0 из 5 шлюзов» — цифры были не из базы.
         */
        $values = array();

        try {
            foreach (db()->createCommand('SELECT param, value FROM {{config}}')->queryAll() as $r) {
                $values[$r['param']] = $r['value'];
            }
        } catch (Exception $e) {
            $values = array();
        }

        $gateways = array();

        $map = array(
            'trybit' => array('Trybit (крипта)', 'USD'),
            'nowpayments' => array('NOWPayments (крипта)', 'USD'),
            'wayforpay' => array('WayForPay (карта)', 'USD'),
            'utopay' => array('UTOPAY (USDT)', 'USD'),
            'payop' => array('PayOp', 'USD'),
            'paypalych' => array('PayPalych (карты РФ, СБП)', 'RUB'),
        );

        foreach ($map as $key => $meta) {
            $note = '';

            if (!empty($values[$key . '.test'])) {
                $note = 'Включён тестовый режим — реальные деньги не списываются';
            }

            if(in_array($key,array('trybit','nowpayments'),true)&&class_exists('PortalCoinPayments'))$note.=($note?' · ':'').'Новый магазин CoL: '.(PortalCoinPayments::ready($key)?'подключён':'ожидает подключения');
            $gateways[] = array(
                'key' => $key,
                'name' => $meta[0],
                'allow' => !empty($values[$key . '.allow']),
                'test' => !empty($values[$key . '.test']),
                'rate' => isset($values[$key . '.rate']) ? (float) $values[$key . '.rate'] : 1,
                'currency' => isset($values[$key . '.currency']) ? $values[$key . '.currency'] : $meta[1],
                'min' => isset($values[$key . '.min']) ? (int) $values[$key . '.min'] : ($key==='nowpayments'?5:1),
                'note' => $note,
            );
        }

        return array(
            'gateways' => $gateways,
            'bonuses' => array(
                array('from' => 1, 'to' => 9, 'percent' => 0),
                array('from' => 10, 'to' => 199, 'percent' => 5),
                array('from' => 200, 'to' => 299, 'percent' => 10),
                array('from' => 300, 'to' => 399, 'percent' => 15),
                array('from' => 400, 'to' => 999, 'percent' => 20),
                array('from' => 1000, 'to' => null, 'percent' => 25),
            ),
            'minDeposit' => 1,
            'rate' => isset($values['utopay.rate'])?(float)$values['utopay.rate']:1,
            'freeUntil' => isset($values['donation.free_week.until'])
                ? str_replace(' ', 'T', substr($values['donation.free_week.until'], 0, 16))
                : '',
            'manualAllow' => !empty($values['manual.allow']),
        );
    }

    /**
     * Метаданные ролей для матрицы «кто что может».
     *
     * @return array
     */
    protected function rolesMeta()
    {
        return array(
            'list' => RoleService::getRoleList(),
            'short' => RoleService::getShortNames(),
            'ranks' => RoleService::getRanks(),
            'notes' => RoleService::getRoleNotes(),
            'matrix' => RoleService::getMatrix(),
            'denied' => RoleService::getDenied(),
            'titles' => RoleService::getPermissionTitles(),
        );
    }

    /**
     * Аналитика посещаемости из visit_log.
     *
     * @return array
     */
    protected function analytics()
    {
        $days = (int) $this->inp('days', 30);

        if ($days < 7 || $days > 90) {
            $days = 30;
        }

        $from = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
        $to = date('Y-m-d');

        $out = array('from' => $from, 'to' => $to, 'days' => $days);

        try {
            $summary = AnalyticsService::getSummary($from, $to);
        } catch (Exception $e) {
            $summary = array();
        }

        try {
            $daily = AnalyticsService::getDailySeries($from, $to);
        } catch (Exception $e) {
            $daily = array();
        }

        try {
            $hourly = AnalyticsService::getHourlySeries(24);
        } catch (Exception $e) {
            $hourly = array();
        }

        try {
            $topPages = AnalyticsService::getTopPages($from, $to, 12);
        } catch (Exception $e) {
            $topPages = array();
        }

        try {
            $sources = AnalyticsService::getSources($from, $to);
        } catch (Exception $e) {
            $sources = array();
        }

        try {
            $devices = AnalyticsService::getBreakdown($from, $to);
        } catch (Exception $e) {
            $devices = array();
        }

        try {
            $visits = AnalyticsService::getVisits(array('date_from' => $from, 'date_to' => $to, 'limit' => 60));
        } catch (Exception $e) {
            $visits = array();
        }

        try {
            $online = AnalyticsService::getOnlineCount(15);
        } catch (Exception $e) {
            $online = 0;
        }

        $out['summary'] = $summary;
        $out['online'] = $online;
        $out['daily'] = $this->normalizeDaily($daily);
        $out['hourly'] = $this->normalizeHourly($hourly);
        $out['topPages'] = $this->normalizeTopPages($topPages);
        $out['sources'] = $this->normalizeSources($sources);
        $out['devices'] = $this->normalizeDevices($devices);
        $out['visits'] = $this->normalizeVisits($visits);

        return $out;
    }

    /**
     * Ряды по дням.
     *
     * AnalyticsService::getDailySeries() отдаёт {days: [...], totals: {...}},
     * а не плоский список, — берём days. Строки уже содержат date, visitors,
     * visits, views, registered, payments.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeDaily($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['days']) ? (array) $rows['days'] : $rows;
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r) || empty($r['date'])) {
                continue;
            }

            $out[] = array(
                'date' => (string) $r['date'],
                'label' => isset($r['label']) ? (string) $r['label'] : (string) $r['date'],
                'visitors' => isset($r['visitors']) ? (int) $r['visitors'] : 0,
                'views' => isset($r['views']) ? (int) $r['views'] : 0,
                'visits' => isset($r['visits']) ? (int) $r['visits'] : 0,
                'registered' => isset($r['registered']) ? (int) $r['registered'] : 0,
                'logins' => isset($r['logins']) ? (int) $r['logins'] : 0,
                'payments' => isset($r['payments']) ? (int) $r['payments'] : 0,
            );
        }

        return $out;
    }

    /**
     * Ряды по часам → {hour, visitors, views}.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeHourly($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['hours']) ? (array) $rows['hours'] : $rows;
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = array(
                'hour' => isset($r['hour']) ? (string) $r['hour'] : '',
                'label' => isset($r['label']) ? (string) $r['label'] : (isset($r['hour']) ? (string) $r['hour'] : ''),
                'visitors' => isset($r['visitors']) ? (int) $r['visitors'] : 0,
                'views' => isset($r['views']) ? (int) $r['views'] : 0,
                'hits' => isset($r['hits']) ? (int) $r['hits'] : 0,
            );
        }

        return $out;
    }

    /**
     * Топ страниц.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeTopPages($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['pages']) ? (array) $rows['pages'] : $rows;
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = array(
                'page' => isset($r['url']) ? (string) $r['url'] : (isset($r['page']) ? (string) $r['page'] : ''),
                'views' => isset($r['views']) ? (int) $r['views'] : (isset($r['hits']) ? (int) $r['hits'] : 0),
                'uniq' => isset($r['visitors']) ? (int) $r['visitors'] : (isset($r['uniq']) ? (int) $r['uniq'] : 0),
            );
        }

        return $out;
    }

    /**
     * Источники: {source, title, visits, viewers, share}.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeSources($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['sources']) ? (array) $rows['sources'] : $rows;
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r)) {
                continue;
            }

            $out[] = array(
                'source' => isset($r['source']) ? (string) $r['source'] : '',
                'title' => isset($r['title']) ? (string) $r['title'] : (isset($r['source']) ? (string) $r['source'] : ''),
                'visits' => isset($r['views']) ? (int) $r['views'] : (isset($r['visits']) ? (int) $r['visits'] : 0),
                'people' => isset($r['visitors']) ? (int) $r['visitors'] : 0,
                'share' => isset($r['share']) ? (float) $r['share'] : 0,
            );
        }

        return $out;
    }

    /**
     * Устройства: {device: [...], browser: [...], os: [...]}.
     *
     * Экрану нужен плоский список устройств с долей в процентах, поэтому
     * считаем долю здесь, а не полагаемся на форму ответа сервиса.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeDevices($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['device']) ? (array) $rows['device'] : $rows;

        $total = 0;

        foreach ($list as $r) {
            if (is_array($r) && isset($r['visitors'])) {
                $total += (int) $r['visitors'];
            }
        }

        $names = array('desktop' => 'Компьютер', 'mobile' => 'Телефон', 'tablet' => 'Планшет', 'bot' => 'Бот', '' => 'Неизвестно');
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r)) {
                continue;
            }

            $key = isset($r['title']) ? (string) $r['title'] : (isset($r['device']) ? (string) $r['device'] : '');
            $people = isset($r['visitors']) ? (int) $r['visitors'] : (isset($r['visits']) ? (int) $r['visits'] : 0);

            $out[] = array(
                'device' => $key,
                'title' => isset($names[$key]) ? $names[$key] : $key,
                'visits' => $people,
                'views' => isset($r['views']) ? (int) $r['views'] : 0,
                'share' => $total > 0 ? round($people * 100 / $total) : 0,
            );
        }

        return $out;
    }

    /**
     * Последние визиты: сервис отдаёт {rows: [...], total: n}.
     *
     * @param array $rows
     *
     * @return array
     */
    protected function normalizeVisits($rows)
    {
        $rows = (array) $rows;
        $list = isset($rows['rows']) ? (array) $rows['rows'] : $rows;
        $out = array();

        foreach ($list as $r) {
            if (!is_array($r) || !empty($r['is_bot'])) {
                continue;
            }

            $src = isset($r['source']) ? (string) $r['source'] : '';

            $out[] = array(
                'at' => isset($r['created_at']) ? $this->ms($r['created_at']) : null,
                'user' => isset($r['login']) && $r['login'] !== '' ? (string) $r['login'] : 'гость',
                'page' => isset($r['url']) ? (string) $r['url'] : '',
                'source' => $src === 'internal' ? 'внутри сайта' : ($src === 'referral' ? 'другой сайт' : 'прямой заход'),
                'device' => isset($r['device']) ? (string) $r['device'] : '',
                'ip' => isset($r['ip']) ? (string) $r['ip'] : '',
                'online' => false,
            );
        }

        return $out;
    }

    /* ================================================================== */
    /* Справочники                                                         */
    /* ================================================================== */

    /**
     * Карта user_id → роль из таблицы ролей.
     *
     * @return array
     */
    protected function staffMap()
    {
        $map = array();

        foreach ((array) RoleService::getStaff() as $row) {
            if (isset($row['user_id'])) {
                $map[(int) $row['user_id']] = $row['role'];
            }
        }

        return $map;
    }

    /**
     * Название роли.
     *
     * @param string $role
     *
     * @return string
     */
    protected function roleName($role)
    {
        $roles = RoleService::getRoleList();

        return isset($roles[$role]) ? $roles[$role] : $role;
    }

    /**
     * Название класса персонажа (Lucera2: classid).
     *
     * @param int $classId
     *
     * @return string
     */
    protected function className($classId)
    {
        static $names = array(
            0 => 'Fighter', 1 => 'Warrior', 2 => 'Gladiator', 3 => 'Warlord', 4 => 'Knight',
            5 => 'Paladin', 6 => 'Dark Avenger', 7 => 'Rogue', 8 => 'Treasure Hunter', 9 => 'Hawkeye',
            10 => 'Mage', 11 => 'Wizard', 12 => 'Sorcerer', 13 => 'Necromancer', 14 => 'Warlock',
            15 => 'Cleric', 16 => 'Bishop', 17 => 'Prophet', 18 => 'Elven Fighter', 19 => 'Elven Knight',
            20 => 'Temple Knight', 21 => 'Swordsinger', 22 => 'Elven Scout', 23 => 'Plainswalker',
            24 => 'Silver Ranger', 25 => 'Elven Mage', 26 => 'Elven Wizard', 27 => 'Spellsinger',
            28 => 'Elemental Summoner', 29 => 'Oracle', 30 => 'Elder', 31 => 'Dark Fighter',
            32 => 'Palus Knight', 33 => 'Shillien Knight', 34 => 'Bladedancer', 35 => 'Assassin',
            36 => 'Abyss Walker', 37 => 'Phantom Ranger', 38 => 'Dark Mage', 39 => 'Dark Wizard',
            40 => 'Spellhowler', 41 => 'Phantom Summoner', 42 => 'Shillien Oracle', 43 => 'Shillien Elder',
            44 => 'Orc Fighter', 45 => 'Orc Raider', 46 => 'Destroyer', 47 => 'Monk', 48 => 'Tyrant',
            49 => 'Orc Mage', 50 => 'Shaman', 51 => 'Overlord', 52 => 'Warcryer', 53 => 'Dwarven Fighter',
            54 => 'Scavenger', 55 => 'Bounty Hunter', 56 => 'Artisan', 57 => 'Warsmith',
            88 => 'Duelist', 89 => 'Dreadnought', 90 => 'Phoenix Knight', 91 => 'Hell Knight',
            92 => 'Sagittarius', 93 => 'Adventurer', 94 => 'Archmage', 95 => 'Soultaker',
            96 => 'Arcana Lord', 97 => 'Cardinal', 98 => 'Hierophant', 99 => 'Eva Templar',
            100 => 'Sword Muse', 101 => 'Wind Rider', 102 => 'Moonlight Sentinel', 103 => 'Mystic Muse',
            104 => 'Elemental Master', 105 => 'Eva Saint', 106 => 'Shillien Templar', 107 => 'Spectral Dancer',
            108 => 'Ghost Hunter', 109 => 'Ghost Sentinel', 110 => 'Storm Screamer', 111 => 'Spectral Master',
            112 => 'Shillien Saint', 113 => 'Titan', 114 => 'Grand Khavatari', 115 => 'Dominator',
            116 => 'Doomcryer', 117 => 'Fortune Seeker', 118 => 'Maestro',
        );

        $classId = (int) $classId;

        return isset($names[$classId]) ? $names[$classId] : ('class ' . $classId);
    }

    /* ================================================================== */

    /**
     * Правка игрока: e-mail, блокировка входа на сайт, пароль.
     */
    public function actionPlayerSave()
    {
        $this->need('users.edit');

        $id = (int) $this->inp('id', 0);
        $user = db()->createCommand(
            'SELECT user_id, login, email, activated FROM {{users}} WHERE user_id = :u LIMIT 1'
        )->queryRow(true, array(':u' => $id));

        if (!$user) {
            $this->fail('Игрок не найден.', 404);
        }

        if (RoleService::isSiteOwner($id) && !RoleService::isSiteOwner()) {
            $this->fail('Владельца проекта может править только владелец.', 403);
        }

        $email = trim((string) $this->inp('email', $user['email']));
        $activated = $this->inp('activated', null);

        $update = array();
        $changed = array();

        if ($email !== '' && $email !== $user['email']) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->fail('E-mail выглядит некорректно.');
            }

            $update['email'] = $email;
            $changed[] = 'email';
        }

        if ($activated !== null) {
            $flag = (int) $activated ? 1 : 0;

            if ($flag !== (int) $user['activated']) {
                $update['activated'] = $flag;
                $changed[] = 'activated';
            }
        }

        if ($update) {
            $update['updated_at'] = date('Y-m-d H:i:s');
            db()->createCommand()->update('{{users}}', $update, 'user_id = :u', array(':u' => $id));

            RoleService::logAction('users.edit', $user['login'], array(
                'fields' => $changed,
                'email' => $email,
                'activated' => isset($update['activated']) ? $update['activated'] : (int) $user['activated'],
            ));
        }

        $this->ok(array('changed' => $changed, 'login' => $user['login']));
    }

    /**
     * Роль игрока (таблица ролей; «владелец» здесь не выдаётся).
     */
    public function actionRoleSet()
    {
        $this->need($this->permissionOf('roleSet'));
        /* Владельцу роль не меняем: он и так владелец, а понижение через
           таблицу ролей раньше давало «снял доступ у владельца». */
        $id = (int) $this->inp('id', 0);

        if ($id > 0 && RoleService::isSiteOwner($id)) {
            $this->fail('Владелец проекта не меняется через роли. Список владельцев — protected/config/params.php.', 403);
        }

        /*
         * Панель исторически зовёт роль «player» и «accountant», а RoleService —
         * «user» и «finance». Без перевода сервер отвечал «Неизвестная роль»,
         * и снятие доступа в «Сотрудниках» не срабатывало. Принимаем оба
         * написания, наружу отдаём серверное.
         */
        $role = strtolower(trim((string) $this->inp('role', 'user')));
        $aliases = array(
            'player' => RoleService::ROLE_USER,
            'accountant' => RoleService::ROLE_FINANCE,
        );

        if (isset($aliases[$role])) {
            $role = $aliases[$role];
        }

        $comment = trim((string) $this->inp('comment', ''));

        $target = db()->createCommand(
            'SELECT user_id, login FROM {{users}} WHERE user_id = :u LIMIT 1'
        )->queryRow(true, array(':u' => $id));

        if (!$target) {
            $this->fail('Игрок не найден.', 404);
        }

        if ($role === RoleService::ROLE_OWNER) {
            $this->fail('Владелец проекта выдаётся в разделе «Владельцы», а не ролью.', 403);
        }

        $check = RoleService::canChangeRole($id, $role);

        if (!$check['ok']) {
            $this->fail($check['error'], 403);
        }

        $done = RoleService::setRole($id, $role, user()->getId(), $comment);

        if (!$done) {
            $this->fail('Не удалось сохранить роль.');
        }

        $this->ok(array(
            'id' => $id,
            'login' => $target['login'],
            'role' => RoleService::roleOf($id),
        ));
    }

    /**
     * Создать игрока (логин, e-mail, пароль).
     */
    public function actionPlayerCreate()
    {
        $this->need('users.edit');

        $login = trim((string) $this->inp('login', ''));
        $email = trim((string) $this->inp('email', ''));
        $password = (string) $this->inp('password', '');
        $balance = round((float) $this->inp('balance', 0), 2);

        if (!preg_match('/^[a-zA-Z0-9_\-\.]{3,32}$/', $login)) {
            $this->fail('Логин: 3–32 символа, латиница, цифры, _ - .');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->fail('E-mail выглядит некорректно.');
        }

        if (strlen($password) < 6) {
            $this->fail('Пароль минимум 6 символов.');
        }

        $exists = db()->createCommand(
            'SELECT user_id FROM {{users}} WHERE login = :l OR email = :e LIMIT 1'
        )->queryRow(true, array(':l' => $login, ':e' => $email));

        if ($exists) {
            $this->fail('Такой логин или e-mail уже занят.');
        }

        /*
         * Создание идёт тем же путём, что регистрация на сайте:
         * Users::create() + игровой insertAccount(). Иначе получится профиль
         * без аккаунта на сервере — игрок не сможет войти в игру.
         */
        $l2 = $this->game();
        $gameTransaction = null;

        try {
            $gameTransaction = $l2->getDb()->beginTransaction();

            $user = Users::create($login, $email, 1, (int) user()->getLsId(), Users::ROLE_DEFAULT);
            $user->created_at = date('Y-m-d H:i:s');
            $user->updated_at = date('Y-m-d H:i:s');

            if (!$user->save(false)) {
                throw new CException('Не удалось создать профиль: ' . implode(', ', $user->getErrors()));
            }

            $userId = (int) $user->getPrimaryKey();

            $l2->insertAccount($login, $password);

            if ($gameTransaction) {
                $gameTransaction->commit();
            }
        } catch (Exception $e) {
            if ($gameTransaction) {
                $gameTransaction->rollback();
            }

            try {
                $this->gameDb()->createCommand('DELETE FROM accounts WHERE login = :l')->execute(array(':l' => $login));
            } catch (Exception $cleanup) {
                Yii::log('adm: не удалось откатить игровой аккаунт — ' . $cleanup->getMessage(), CLogger::LEVEL_WARNING, __METHOD__);
            }

            $this->fail('Игрок не создан: ' . $e->getMessage(), 500);
        }

        // Кошелёк: строка профиля и стартовый баланс через WalletService
        try {
            db()->createCommand()->insert('{{user_profiles}}', array(
                'user_id' => $userId,
                'balance' => 0,
            ));
        } catch (Exception $e) {
            Yii::log('adm: профиль кошелька не создан — ' . $e->getMessage(), CLogger::LEVEL_WARNING, __METHOD__);
        }

        if ($balance > 0) {
            WalletService::credit($userId, $balance, array('comment' => 'Стартовый баланс при создании игрока'));
        }

        RoleService::logAction('users.create', $login, array('email' => $email, 'balance' => $balance));

        $this->ok(array('id' => $userId, 'login' => $login, 'gameLogin' => $login));
    }

    /**
     * Ручное начисление / списание баланса.
     */
    public function actionBalance()
    {
        $this->need('finance.balance');

        $login = trim((string) $this->inp('login', ''));
        $amount = round(abs((float) str_replace(',', '.', (string) $this->inp('amount', 0))), 2);
        $operation = $this->inp('operation', 'credit') === 'debit' ? 'debit' : 'credit';
        $comment = trim((string) $this->inp('comment', ''));

        if ($amount <= 0) {
            $this->fail('Сумма должна быть больше нуля.');
        }

        $target = $this->findUser($login);

        if (!$target) {
            $this->fail('Пользователь не найден.', 404);
        }

        try {
            if ($operation === 'debit') {
                WalletService::debit($target->user_id, $amount, array(
                    'comment' => $comment !== '' ? $comment : 'Ручное списание администрацией',
                ));
            } else {
                WalletService::credit($target->user_id, $amount, array(
                    'comment' => $comment !== '' ? $comment : 'Ручное начисление администрацией',
                ));
            }
        } catch (Exception $e) {
            // «Недостаточно средств» и прочие правила кошелька — это ответ
            // интерфейсу, а не падение сервера.
            $this->fail($e->getMessage(), 400);
        }

        RoleService::logAction('finance.' . $operation, $target->login, array(
            'amount' => $amount,
            'comment' => $comment,
        ));

        $this->ok(array(
            'login' => $target->login,
            'balance' => WalletService::getBalance($target->user_id),
            'operation' => $operation,
            'amount' => $amount,
        ));
    }


    /* ================================================================== */

    /**
     * Выдать предмет персонажу (Lucera2: items + items_external).
     *
     * Причина обязательна: выдача без причины — это то, что потом ищут в
     * журнале.
     */
    public function actionGiveItem()
    {
        $this->need('gm.give_item');

        $login = trim((string) $this->inp('login', ''));
        $charName = trim((string) $this->inp('char', $this->inp('char_name', '')));
        $itemId = (int) $this->inp('itemId', $this->inp('item_id', 0));
        $count = max(1, (int) $this->inp('count', 1));
        $enchant = max(0, min(30, (int) $this->inp('enchant', 0)));
        $reason = trim((string) $this->inp('reason', $this->inp('comment', '')));

        if ($login === '' || $charName === '') {
            $this->fail('Укажите игровой аккаунт и персонажа.');
        }

        if ($itemId <= 0) {
            $this->fail('Укажите ID предмета.');
        }

        if ($count > 1000000) {
            $this->fail('Максимум 1 000 000 за операцию.');
        }

        $character = $this->resolveCharacter($login, $charName);

        if (!$character) {
            $this->fail('Персонаж не найден на сервере.', 404);
        }

        /*
         * Выдача идёт в items_delayed — штатную очередь Lucera2: сервер сам
         * выдаёт предмет персонажу при следующем входе. Прямой INSERT в items
         * ломает object_id на живом сервере, а items_external, который
         * использовала старая панель, в этой сборке просто нет.
         */
        try {
            $this->gameDb()->createCommand()->insert('items_delayed', array(
                'owner_id' => (int) $character['obj_Id'],
                'item_id' => $itemId,
                'count' => $count,
                'enchant_level' => $enchant,
                'variationId1' => 0,
                'variationId2' => 0,
                'attribute' => -1,
                'attribute_level' => -1,
                'flags' => 0,
                'payment_status' => 1,
                'description' => 'admin:' . (string) user()->getLogin() . ': ' . $reason,
            ));
        } catch (Exception $e) {
            $this->fail('Игровая база отклонила выдачу: ' . $e->getMessage(), 500);
        }

        RoleService::logAction('gm.give_item', $login, array(
            'char_name' => $charName,
            'char_id' => (int) $character['obj_Id'],
            'item_id' => $itemId,
            'count' => $count,
            'enchant' => $enchant,
            'reason' => $reason,
        ));

        $this->ok(array(
            'login' => $login,
            'char' => $charName,
            'itemId' => $itemId,
            'count' => $count,
            'enchant' => $enchant,
            'note' => 'Предмет выдан: сервер подхватит его при следующем входе персонажа.',
        ));
    }

    /**
     * Бан / разбан игрового аккаунта.
     */
    public function actionBan()
    {
        $mode = $this->inp('mode', 'ban') === 'unban' ? 'unban' : 'ban';

        $this->need($mode === 'ban' ? 'gm.ban_game' : 'gm.unban_game');

        $login = trim((string) $this->inp('login', ''));
        $hours = max(1, min(24 * 365, (int) $this->inp('hours', 24)));
        $reason = trim((string) $this->inp('reason', ''));

        if ($login === '') {
            $this->fail('Укажите игровой аккаунт.');
        }

        $account = $this->gameAccount($login);

        if (!$account) {
            $this->fail('Игровой аккаунт не найден.', 404);
        }

        $until = $mode === 'ban' ? time() + $hours * 3600 : 0;

        try {
            $this->gameDb()->createCommand()->update(
                'accounts',
                array('ban_expire' => $until),
                'login = :l',
                array(':l' => $login)
            );
        } catch (Exception $e) {
            $this->fail('Не удалось записать бан: ' . $e->getMessage(), 500);
        }

        RoleService::logAction('gm.ban_game', $login, array(
            'mode' => $mode,
            'hours' => $mode === 'ban' ? $hours : 0,
            'reason' => $reason,
            'until' => $mode === 'ban' ? date('Y-m-d H:i:s', $until) : '',
        ));

        $this->ok(array(
            'login' => $login,
            'mode' => $mode,
            'until' => $mode === 'ban' ? $until * 1000 : null,
        ));
    }


    /* ================================================================== */

    /**
     * Ответ в тикет и/или смена статуса.
     */
    public function actionTicketReply()
    {
        $this->need('tickets.reply');

        $ticketId = (int) $this->inp('id', 0);
        $text = trim((string) $this->inp('text', ''));
        $close = (bool) $this->inp('close', false);
        $open = (bool) $this->inp('open', false);

        $ticket = db()->createCommand('SELECT * FROM {{tickets}} WHERE id = :id')->queryRow(true, array(':id' => $ticketId));

        if (!$ticket) {
            $this->fail('Тикет не найден.', 404);
        }

        if ($text === '' && !$close && !$open) {
            $this->fail('Пустой ответ отправлять не нужно.');
        }

        if ($close && !$this->has('tickets.close')) {
            $this->fail('Закрывать тикеты вашей роли нельзя.', 403);
        }

        if ($text !== '') {
            db()->createCommand()->insert('{{tickets_answers}}', array(
                'ticket_id' => $ticketId,
                'text' => $text,
                'user_id' => (int) user()->getId(),
                'created_at' => date('Y-m-d H:i:s'),
            ));
        }

        db()->createCommand()->update('{{tickets}}', array(
            'status' => $close ? 1 : ($open ? 0 : (int) $ticket['status']),
            'new_message_for_user' => 1,
            'new_message_for_admin' => 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array(':id' => $ticketId));

        RoleService::logAction(
            $close ? 'tickets.close' : ($open ? 'tickets.reopen' : 'tickets.reply'),
            '#' . $ticketId,
            array('text' => mb_substr($text, 0, 200))
        );

        $this->ok(array('id' => $ticketId, 'closed' => $close, 'open' => $open));
    }


    /* ================================================================== */

    /**
     * Очередь выдачи: отметить «выдано» или отменить строку.
     *
     * Строка живёт в items_delayed. «Выдано» = payment_status 0 (сервер уже
     * отдал), «отменить» = удалить строку, пока персонаж не вошёл.
     */
    public function actionItemQueue()
    {
        $this->need('gm.give_item');

        $id = (int) $this->inp('id', 0);
        $act = $this->inp('act', 'done') === 'cancel' ? 'cancel' : 'done';

        if ($id <= 0) {
            $this->fail('Не указана строка очереди.');
        }

        $row = $this->gameDb()->createCommand(
            'SELECT payment_id, owner_id, item_id, count FROM items_delayed WHERE payment_id = :i LIMIT 1'
        )->queryRow(true, array(':i' => $id));

        if (!$row) {
            $this->fail('Строка очереди не найдена.', 404);
        }

        if ($act === 'cancel') {
            $this->gameDb()->createCommand('DELETE FROM items_delayed WHERE payment_id = :i')->execute(array(':i' => $id));
            RoleService::logAction('gm.item_cancel', '#' . $id, array('item_id' => (int) $row['item_id'], 'count' => (int) $row['count']));
            $this->ok(array('id' => $id, 'removed' => true));

            return;
        }

        $this->gameDb()->createCommand()->update(
            'items_delayed',
            array('payment_status' => 0),
            'payment_id = :i',
            array(':i' => $id)
        );

        RoleService::logAction('gm.item_done', '#' . $id, array('item_id' => (int) $row['item_id'], 'count' => (int) $row['count']));

        $this->ok(array('id' => $id, 'done' => true));
    }

    /**
     * Подтвердить или отменить заявку на пополнение.
     *
     * Раньше кнопка «Начислить» писала только в память браузера: игрок
     * оставался без монет, а в журнале появлялась запись о выдаче.
     * Здесь — реальная запись: статус заявки + кошелёк, одной транзакцией.
     */
    public function actionPaymentSet()
    {
        $this->need('finance.edit');

        $id = (int) $this->inp('id', 0);
        $act = $this->inp('act', 'credit') === 'cancel' ? 'cancel' : 'credit';
        $comment = trim((string) $this->inp('comment', ''));

        $row = db()->createCommand(
            'SELECT * FROM {{transactions}} WHERE id = :id LIMIT 1'
        )->queryRow(true, array(':id' => $id));

        if (!$row) {
            $this->fail('Заявка не найдена.', 404);
        }

        if ((int) $row['status'] !== 0) {
            $this->fail('Заявка уже обработана: статус «' . ((int) $row['status'] === 1 ? 'оплачена' : 'отменена') . '».', 409);
        }

        if ($act === 'cancel') {
            db()->createCommand()->update('{{transactions}}', array(
                'status' => 2,
                'updated_at' => date('Y-m-d H:i:s'),
            ), 'id = :id', array(':id' => $id));

            RoleService::logAction('finance.cancel', '#' . $id, array(
                'user_id' => (int) $row['user_id'],
                'sum' => $row['sum'],
                'comment' => $comment,
            ));

            $this->ok(array('id' => $id, 'status' => 'canceled'));

            return;
        }

        $coins = (int) $row['count'];

        if ($coins <= 0) {
            $this->fail('В заявке нет суммы к начислению.', 400);
        }

        try {
            WalletService::credit((int) $row['user_id'], $coins, array(
                'comment' => $comment !== '' ? $comment : ('Пополнение по заявке #' . $id . ' подтверждено в панели'),
                'order_id' => 'tx-' . $id,
            ));
        } catch (Exception $e) {
            $this->fail('Кошелёк не принял начисление: ' . $e->getMessage(), 400);
        }

        db()->createCommand()->update('{{transactions}}', array(
            'status' => 1,
            'updated_at' => date('Y-m-d H:i:s'),
        ), 'id = :id', array(':id' => $id));

        RoleService::logAction('finance.credit', '#' . $id, array(
            'user_id' => (int) $row['user_id'],
            'coins' => $coins,
            'sum' => $row['sum'],
            'comment' => $comment,
        ));

        $this->ok(array(
            'id' => $id,
            'status' => 'paid',
            'coins' => $coins,
            'balance' => WalletService::getBalance((int) $row['user_id']),
        ));
    }

    /**
     * Выдать или снять владельца проекта.
     */
    public function actionOwner()
    {
        $this->need($this->permissionOf('owner'));

        $act = $this->inp('act', 'grant') === 'revoke' ? 'revoke' : 'grant';
        $userId = (int) $this->inp('user_id', $this->inp('id', 0));
        $comment = trim((string) $this->inp('comment', ''));

        $result = $act === 'grant'
            ? RoleService::grantOwner($userId, user()->getId(), $comment)
            : RoleService::revokeOwner($userId, user()->getId(), $comment);

        if (!$result['ok']) {
            $this->fail($result['error'], 403);
        }

        $this->ok(array(
            'owners' => RoleService::getOwnerIds(),
            'count' => RoleService::countOwners(),
        ));
    }


    /* ================================================================== */

    /**
     * Создать игровой аккаунт Lucera2 и привязать его к профилю сайта.
     *
     * Пароль шифруется так же, как его понимает сервер (Lineage::passwordEncrypt),
     * иначе игрок не сможет войти в игру.
     *
     * @param string $login
     * @param string $password
     *
     * @return string|null логин игрового аккаунта
     */
    protected function createGameAccount($login, $password)
    {
        $db = $this->gameDb();

        $exists = $db->createCommand('SELECT login FROM accounts WHERE login = :l LIMIT 1')
            ->queryRow(true, array(':l' => $login));

        if ($exists) {
            return null;
        }

        $l2 = $this->game();

        $db->createCommand()->insert('accounts', array(
            'login' => $login,
            'password' => $l2->passwordEncrypt($password),
            'lastactive' => time(),
            'accessLevel' => 0,
            'lastIP' => '127.0.0.1',
            'ban_expire' => 0,
        ));

        db()->createCommand()->insert('{{account_links}}', array(
            'group_id' => 0,
            'user_id' => (string) user()->getId(),
            'owner_id' => (string) user()->getId(),
            'game_login' => $login,
            'gs_id' => (int) user()->getGsId(),
            'status' => 1,
            'note' => 'создан в админке',
            'added_by' => (string) user()->getId(),
            'created_at' => date('Y-m-d H:i:s'),
        ));

        return $login;
    }

    /**
     * Сменить пароль игрового аккаунта.
     */
    public function actionGamePassword()
    {
        $this->need('users.edit');

        if (!request()->getIsPostRequest()) {
            $this->fail('Для сброса пароля требуется POST-запрос.', 405);
        }

        $login = trim((string) $this->inp('login', ''));
        $password = (string) $this->inp('password', '');

        if (!preg_match('/^[\x21-\x7E]{6,16}$/D', $password)) {
            $this->fail('Пароль: 6–16 символов, латиница, цифры и знаки, без пробелов.');
        }

        if ($password !== (string) $this->inp('passwordConfirm', '')) {
            $this->fail('Пароли не совпадают.');
        }

        $account = $this->gameAccount($login);
        if (!$account) {
            $this->fail('Игровой аккаунт не найден.', 404);
        }

        if ((int) $account['accessLevel'] > 0 && !RoleService::isSiteOwner()) {
            $this->fail('Пароль игрового администратора может сбросить только владелец.', 403);
        }

        // Нельзя получить доступ к аккаунту старшего сотрудника через сброс.
        $targets = db()->createCommand(
            'SELECT DISTINCT u.user_id FROM {{users}} u
             LEFT JOIN {{account_links}} a ON a.user_id = u.user_id AND a.status = 1
             WHERE (u.login = :login AND u.ls_id = :ls) OR a.game_login = :linked'
        )->queryColumn(array(':login' => $login, ':ls' => (int) user()->getLsId(), ':linked' => $login));
        foreach ($targets as $targetId) {
            if ((int) $targetId !== (int) user()->getId()
                && !RoleService::isSiteOwner()
                && RoleService::rankOf(RoleService::roleOf($targetId)) >= RoleService::rankOf($this->role)) {
                $this->fail('Нельзя сбросить пароль аккаунта сотрудника с равной или более высокой ролью.', 403);
            }
        }

        $this->gameDb()->createCommand()->update(
            'accounts',
            array('password' => $this->game()->passwordEncrypt($password)),
            'login = :l',
            array(':l' => $login)
        );

        RoleService::logAction('users.password', $login, array('game_login' => $login));

        $this->ok(array('login' => $login));
    }

    /**
     * Персонаж на сервере по аккаунту и имени (или только по имени).
     *
     * @param string $login
     * @param string $charName
     *
     * @return array|null
     */
    protected function resolveCharacter($login, $charName)
    {
        $db = $this->gameDb();

        /*
         * getField() может вернуть имя с префиксом таблицы ("characters.obj_Id"),
         * а запрос идёт с алиасом c — оставляем только имя колонки.
         */
        $idField = $this->game()->getField('characters.char_id');
        $idField = substr(strrchr('.' . $idField, '.'), 1);

        /*
         * Уровень персонажа в Lucera2 лежит в character_subclasses
         * (active = 1), в characters такой колонки нет.
         */
        $select = 'SELECT c.' . $idField . ' AS obj_Id, c.char_name, c.account_name, c.online, c.clanid,
                          COALESCE(s.level, 0) AS level
                   FROM characters c
                   LEFT JOIN character_subclasses s ON s.char_obj_id = c.' . $idField . ' AND s.active = 1';

        if ($login !== '' && $charName !== '') {
            $row = $db->createCommand(
                $select . ' WHERE c.account_name = :l AND c.char_name = :n LIMIT 1'
            )->queryRow(true, array(':l' => $login, ':n' => $charName));

            if ($row) {
                return $row;
            }
        }

        if ($charName !== '') {
            return $db->createCommand(
                $select . ' WHERE c.char_name = :n LIMIT 1'
            )->queryRow(true, array(':n' => $charName));
        }

        return null;
    }
}
