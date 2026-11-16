<?php

/**
 * Роли сотрудников, права доступа и управление владельцами проекта.
 *
 * Владелец проекта определяется ТОЛЬКО списком app()->params['superAdminId'].
 * Роль в таблице {{roles}} владельцем не делает — иначе администратор мог бы
 * выдать себе владельца. Владельцев может быть несколько.
 *
 * Иерархия: у каждой роли есть ранг. Менять роль можно только у того, кто
 * строго ниже по рангу; владельца не снимает никто, кроме владельца, и только
 * через grantOwner()/revokeOwner(), а не через таблицу ролей.
 *
 * Код совместим с PHP 5.6+ (Yii 1.1): без стрелочных функций, без
 * nullsafe-оператора, без типизации свойств.
 */
class RoleService
{
    const ROLE_ADMIN = 'admin';
    const ROLE_MODER = 'moder';
    const ROLE_GM = 'gm';
    const ROLE_FINANCE = 'finance';
    const ROLE_USER = 'user';

    /** Псевдороль владельца: в таблице ролей не хранится */
    const ROLE_OWNER = 'owner';

    /** @var array кеш ролей в рамках запроса */
    private static $_cache = array();

    /** @var array|null кеш списка владельцев */
    private static $_owners = null;

    /* ------------------------------------------------------------------ */
    /* Роли и их описания                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Названия ролей для списков и бейджей.
     *
     * @return array
     */
    public static function getRoleList()
    {
        return array(
            self::ROLE_OWNER => 'Владелец проекта',
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_MODER => 'Модератор',
            self::ROLE_GM => 'ГМ (игровые действия)',
            self::ROLE_FINANCE => 'Бухгалтер (платежи)',
            self::ROLE_USER => 'Игрок (без доступа к панели)',
        );
    }

    /**
     * Роли, которые можно выдать через таблицу ролей (без владельца).
     *
     * @return array
     */
    public static function getAssignableRoles()
    {
        $list = self::getRoleList();

        unset($list[self::ROLE_OWNER]);

        return $list;
    }

    /**
     * Короткие названия — для матрицы прав и бейджей.
     *
     * @return array
     */
    public static function getShortNames()
    {
        return array(
            self::ROLE_OWNER => 'Владелец',
            self::ROLE_ADMIN => 'Администратор',
            self::ROLE_MODER => 'Модератор',
            self::ROLE_GM => 'ГМ',
            self::ROLE_FINANCE => 'Бухгалтер',
            self::ROLE_USER => 'Игрок',
        );
    }

    /**
     * Приведение псевдонимов ролей к каноническим ключам.
     *
     * Исторически клиент мог передавать 'player' вместо 'user'
     * или 'accountant' вместо 'finance'. Нормализация защищает
     * методы сервиса от падений на неизвестном ключе.
     *
     * @param string $role
     *
     * @return string
     */
    public static function normalizeRole($role)
    {
        $role = (string) $role;
        $aliases = array(
            'player' => self::ROLE_USER,
            'accountant' => self::ROLE_FINANCE,
        );

        return isset($aliases[$role]) ? $aliases[$role] : $role;
    }

    /**
     * Ранги ролей: чем больше, тем выше. Нужны для запрета «снять старшего».
     *
     * @return array
     */
    public static function getRanks()
    {
        return array(
            self::ROLE_OWNER => 100,
            self::ROLE_ADMIN => 80,
            self::ROLE_MODER => 60,
            self::ROLE_GM => 50,
            self::ROLE_FINANCE => 40,
            self::ROLE_USER => 0,
        );
    }

    /**
     * Ранг роли.
     *
     * @param string $role
     *
     * @return int
     */
    public static function rankOf($role)
    {
        $role = self::normalizeRole($role);
        $ranks = self::getRanks();

        return isset($ranks[$role]) ? (int) $ranks[$role] : 0;
    }

    /**
     * Пояснение «что может роль» — для карточек в разделе «Сотрудники».
     *
     * @return array
     */
    public static function getRoleNotes()
    {
        return array(
            self::ROLE_OWNER => 'Полный доступ. Единственная роль, которая выдаёт и снимает владельцев и меняет настройки проекта.',
            self::ROLE_ADMIN => 'Всё, кроме владельцев, ролей и настроек. Раздел «Сотрудники» — только просмотр.',
            self::ROLE_MODER => 'Игроки, тикеты, аналитика, чтение журнала. Без денег, игровых действий и ролей.',
            self::ROLE_GM => 'Выдача предметов, баны и разбаны, ответы в тикетах. Деньги и настройки недоступны.',
            self::ROLE_FINANCE => 'Заявки, начисления, возвраты, выгрузки. Игровые действия и роли недоступны.',
            self::ROLE_USER => 'Нет доступа к панели — только личный кабинет.',
        );
    }

    /**
     * Человекочитаемые названия прав.
     *
     * @return array
     */
    public static function getPermissionTitles()
    {
        return array(
            'panel.view' => 'Вход в панель',
            'analytics.view' => 'Аналитика сайта',
            'tickets.view' => 'Просмотр тикетов',
            'tickets.reply' => 'Ответы в тикетах',
            'tickets.close' => 'Закрытие тикетов',
            'users.view' => 'Поиск игроков',
            'users.characters' => 'Просмотр персонажей',
            'users.edit' => 'Правка игроков',
            'users.roles' => 'Выдача ролей',
            'roles.manage' => 'Раздел «Сотрудники»',
            'owner.manage' => 'Управление владельцами',
            'links.view' => 'Просмотр групп аккаунтов',
            'gm.give_item' => 'Выдача предметов',
            'gm.ban_game' => 'Бан аккаунта',
            'gm.unban_game' => 'Разбан аккаунта',
            'characters.view' => 'Просмотр игровых действий',
            'server.view' => 'Данные игрового сервера',
            'finance.view' => 'Просмотр платежей',
            'finance.edit' => 'Начисления и возвраты',
            'finance.refund' => 'Возвраты',
            'finance.balance' => 'Начисление баланса',
            'transfers.view' => 'Просмотр переводов',
            'settings.view' => 'Настройки проекта',
            'log.view' => 'Журнал действий',
        );
    }

    /* ------------------------------------------------------------------ */
    /* Матрица прав                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Матрица прав: роль => список разрешённых действий.
     *
     * @return array
     */
    public static function getMatrix()
    {
        return array(
            self::ROLE_OWNER => array('*', 'owner.manage', 'roles.manage', 'settings.view'),

            self::ROLE_ADMIN => array(
                'panel.view',
                'analytics.view',
                'tickets.view', 'tickets.reply', 'tickets.close',
                'users.view', 'users.characters', 'users.edit',
                'characters.view',
                'server.view',
                'links.view',
                'gm.give_item', 'gm.ban_game', 'gm.unban_game',
                'finance.view', 'finance.edit', 'finance.refund', 'finance.balance',
                'transfers.view',
                'log.view',
            ),

            self::ROLE_MODER => array(
                'panel.view',
                'analytics.view',
                'tickets.view', 'tickets.reply', 'tickets.close',
                'users.view', 'users.characters',
                'characters.view',
                'server.view',
                'links.view',
                'log.view',
            ),

            self::ROLE_GM => array(
                'panel.view',
                'tickets.view', 'tickets.reply',
                'users.view', 'users.characters',
                'gm.give_item', 'gm.ban_game', 'gm.unban_game',
                'characters.view',
                'server.view',
                'links.view',
                'log.view',
            ),

            self::ROLE_FINANCE => array(
                'panel.view',
                'analytics.view',
                'finance.view', 'finance.refund', 'finance.balance', 'finance.edit',
                'users.view', 'characters.view', 'server.view',
                'transfers.view',
                'log.view',
            ),

            self::ROLE_USER => array(),
        );
    }

    /**
     * Права, которые роли запрещены явно (для матрицы «кто что может»).
     *
     * @return array
     */
    public static function getDenied()
    {
        return array(
            self::ROLE_ADMIN => array('roles.manage', 'owner.manage', 'settings.view'),
            self::ROLE_MODER => array('finance.view', 'finance.edit', 'gm.give_item', 'owner.manage', 'roles.manage'),
            self::ROLE_GM => array('finance.view', 'finance.edit', 'owner.manage', 'roles.manage'),
            self::ROLE_FINANCE => array('gm.give_item', 'gm.ban_game', 'owner.manage', 'roles.manage'),
            self::ROLE_USER => array('panel.view'),
        );
    }

    /* ------------------------------------------------------------------ */
    /* Владельцы проекта                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Список ID владельцев проекта.
     *
     * @return int[]
     */
    public static function getOwnerIds()
    {
        if (self::$_owners !== null) {
            return self::$_owners;
        }

        $ids = array();

        foreach ((array) app()->params['superAdminId'] as $ownerId) {
            $ids[] = (int) $ownerId;
        }

        return self::$_owners = $ids;
    }

    /**
     * Владелец ли пользователь. Единственный источник истины — params.
     *
     * @param int|null $userId
     *
     * @return bool
     */
    public static function isSiteOwner($userId = null)
    {
        if ($userId === null) {
            if (user()->isGuest) {
                return false;
            }

            $userId = user()->getId();
        }

        return in_array((int) $userId, self::getOwnerIds(), true);
    }

    /**
     * Сколько владельцев сейчас.
     *
     * @return int
     */
    public static function countOwners()
    {
        return count(self::getOwnerIds());
    }

    /**
     * Может ли пользователь управлять владельцами. Только владелец.
     *
     * @param int|null $userId
     *
     * @return bool
     */
    public static function canManageOwners($userId = null)
    {
        return self::isSiteOwner($userId);
    }

    /**
     * Выдать владельца. Только владелец, только существующему игроку.
     *
     * @param int      $userId
     * @param int|null $actorId
     * @param string   $comment
     *
     * @return array array('ok' => bool, 'error' => string)
     */
    public static function grantOwner($userId, $actorId = null, $comment = '')
    {
        $userId = (int) $userId;
        $actorId = $actorId === null ? (int) user()->getId() : (int) $actorId;

        if (!self::isSiteOwner($actorId)) {
            return array('ok' => false, 'error' => 'Выдавать владельца может только владелец проекта.');
        }

        if (!Users::model()->exists('user_id = :u', array(':u' => $userId))) {
            return array('ok' => false, 'error' => 'Игрок не найден.');
        }

        if (self::isSiteOwner($userId)) {
            return array('ok' => false, 'error' => 'Этот игрок уже владелец проекта.');
        }

        $ownerIds = self::getOwnerIds();
        $ownerIds[] = $userId;

        if (!self::saveOwnerIds($ownerIds)) {
            return array('ok' => false, 'error' => 'Не удалось сохранить список владельцев (нет прав на params.php).');
        }

        self::logAction('Выдача владельца', self::loginOf($userId), array(
            'user_id' => $userId,
            'owners' => self::getOwnerIds(),
            'comment' => $comment,
        ), $actorId);

        return array('ok' => true, 'error' => '');
    }

    /**
     * Снять владельца. Только владелец и только если он не последний.
     *
     * @param int      $userId
     * @param int|null $actorId
     * @param string   $comment
     *
     * @return array array('ok' => bool, 'error' => string)
     */
    public static function revokeOwner($userId, $actorId = null, $comment = '')
    {
        $userId = (int) $userId;
        $actorId = $actorId === null ? (int) user()->getId() : (int) $actorId;

        if (!self::isSiteOwner($actorId)) {
            return array('ok' => false, 'error' => 'Снимать владельца может только владелец проекта.');
        }

        if (!self::isSiteOwner($userId)) {
            return array('ok' => false, 'error' => 'Этот игрок не владелец проекта.');
        }

        $ownerIds = self::getOwnerIds();

        if (count($ownerIds) <= 1) {
            return array('ok' => false, 'error' => 'Нельзя снять последнего владельца проекта.');
        }

        $keep = array();

        foreach ($ownerIds as $id) {
            if ((int) $id !== $userId) {
                $keep[] = (int) $id;
            }
        }

        if (!self::saveOwnerIds($keep)) {
            return array('ok' => false, 'error' => 'Не удалось сохранить список владельцев (нет прав на params.php).');
        }

        // Роль в таблице понижаем до администратора, чтобы не осталось «*»
        self::setRole($userId, self::ROLE_ADMIN, $actorId, 'снят с владельцев проекта');
        self::flushCache();

        self::logAction('Снятие владельца', self::loginOf($userId), array(
            'user_id' => $userId,
            'owners' => self::getOwnerIds(),
            'comment' => $comment,
        ), $actorId);

        return array('ok' => true, 'error' => '');
    }

    /**
     * Сохраняет список владельцев в protected/config/params.php.
     *
     * Меняется только строка 'superAdminId'; остальные параметры и комментарии
     * остаются как были. Перед записью делается резервная копия
     * params.php.bak-<дата>, запись идёт через временный файл с проверкой
     * синтаксиса (php -l), если CLI доступен.
     *
     * @param int[] $ids
     *
     * @return bool
     */
    public static function saveOwnerIds(array $ids)
    {
        $clean = array();

        foreach ($ids as $id) {
            $id = (int) $id;

            if ($id > 0 && !in_array($id, $clean, true)) {
                $clean[] = $id;
            }
        }

        if (!$clean) {
            return false;
        }

        $file = Yii::getPathOfAlias('application.config') . DIRECTORY_SEPARATOR . 'params.php';

        if (!is_file($file) || !is_writable($file)) {
            Yii::log('params.php недоступен для записи: ' . $file, CLogger::LEVEL_ERROR, __METHOD__);

            return false;
        }

        $source = file_get_contents($file);

        if ($source === false) {
            return false;
        }

        $line = "    'superAdminId' => [" . implode(', ', $clean) . "],";

        $pattern = "/^[ \t]*'superAdminId'[ \t]*=>[ \t]*\[[^\]]*\],?/m";
        $updated = preg_replace($pattern, $line, $source, 1, $count);

        if ($updated === null) {
            return false;
        }

        if (!$count) {
            $updated = preg_replace('/\n\];\s*$/', "\n\n" . $line . "\n];\n", $source);

            if ($updated === null || $updated === $source) {
                return false;
            }
        }

        $backup = $file . '.bak-' . date('Ymd-His');

        if (!@copy($file, $backup)) {
            Yii::log('Не удалось сделать резервную копию params.php', CLogger::LEVEL_ERROR, __METHOD__);

            return false;
        }

        $tmp = $file . '.tmp-' . getmypid();

        if (file_put_contents($tmp, $updated, LOCK_EX) === false) {
            return false;
        }

        if (!self::validateSyntax($updated)) {
            @unlink($tmp);
            Yii::log('params.php после правки не проходит проверку синтаксиса', CLogger::LEVEL_ERROR, __METHOD__);

            return false;
        }

        if (!@rename($tmp, $file)) {
            @unlink($tmp);

            return false;
        }

        @chmod($file, 0644);

        // В текущем запросе params уже прочитаны — обновляем их в памяти,
        // иначе новый владелец получит доступ только после перезагрузки страницы.
        self::$_owners = null;
        self::$_cache = array();
        app()->params['superAdminId'] = $clean;

        return true;
    }

    /**
     * Проверка синтаксиса PHP-кода перед записью файла.
     *
     * Три уровня, от точного к мягкому:
     *  1) php -l тем же бинарником, что выполняет сайт (PHP_BINARY);
     *  2) token_get_all() с флагом TOKEN_PARSE (PHP 7+) — ловит ParseError;
     *  3) если проверить нечем, считаем код верным: он собран из исходника,
     *     у которого заменена одна строка.
     *
     * @param string $code
     *
     * @return bool
     */
    protected static function validateSyntax($code)
    {
        if (function_exists('exec')) {
            $binary = defined('PHP_BINARY') && PHP_BINARY !== '' ? PHP_BINARY : '';

            if ($binary !== '' && @is_file($binary)) {
                // php -l читает файл, поэтому пишем временный
                $probe = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'la2plus-lint-' . getmypid() . '.php';

                if (@file_put_contents($probe, $code) !== false) {
                    $out = array();
                    $status = 1;

                    @exec(escapeshellarg($binary) . ' -l ' . escapeshellarg($probe) . ' 2>&1', $out, $status);
                    @unlink($probe);

                    return $status === 0;
                }
            }
        }

        if (function_exists('token_get_all') && defined('TOKEN_PARSE')) {
            try {
                token_get_all($code, TOKEN_PARSE);
            } catch (Exception $e) {
                return false;
            }
        }

        return true;
    }

    /**
     * Логин по ID (для журнала).
     *
     * @param int $userId
     *
     * @return string
     */
    protected static function loginOf($userId)
    {
        $login = db()->createCommand("SELECT login FROM {{users}} WHERE user_id = :u")
            ->queryScalar(array(':u' => (int) $userId));

        return ($login === false || $login === null) ? ('#' . (int) $userId) : (string) $login;
    }

    /* ------------------------------------------------------------------ */
    /* Роль пользователя                                                   */
    /* ------------------------------------------------------------------ */

    /**
     * Роль пользователя по ID.
     *
     * @param int|string $userId
     *
     * @return string
     */
    public static function roleOf($userId)
    {
        $userId = (int) $userId;

        if ($userId <= 0) {
            return self::ROLE_USER;
        }

        if (array_key_exists($userId, self::$_cache)) {
            return self::$_cache[$userId];
        }

        // Владелец — всегда владелец, что бы ни лежало в таблице ролей
        if (self::isSiteOwner($userId)) {
            return self::$_cache[$userId] = self::ROLE_OWNER;
        }

        $role = db()->createCommand("SELECT role FROM {{roles}} WHERE user_id = :u")
            ->queryScalar(array(':u' => $userId));

        $list = self::getRoleList();

        $role = $role && isset($list[$role]) && $role !== self::ROLE_OWNER
            ? (string) $role
            : self::ROLE_USER;

        return self::$_cache[$userId] = $role;
    }

    /**
     * Роль текущего игрока.
     *
     * @return string
     */
    public static function currentRole()
    {
        return user()->isGuest ? self::ROLE_USER : self::roleOf(user()->getId());
    }

    /**
     * Есть ли у пользователя право.
     *
     * @param string   $permission
     * @param int|null $userId
     *
     * @return bool
     */
    public static function can($permission, $userId = null)
    {
        // Владелец может всё
        if (self::isSiteOwner($userId)) {
            return true;
        }

        // Управление владельцами и настройки — только владелец
        if ($permission === 'owner.manage' || $permission === 'settings.view') {
            return false;
        }

        $role = $userId === null ? self::currentRole() : self::roleOf($userId);
        $matrix = self::getMatrix();

        if (!isset($matrix[$role])) {
            return false;
        }

        if (in_array('*', $matrix[$role], true)) {
            return true;
        }

        return in_array($permission, $matrix[$role], true);
    }

    /**
     * Может ли текущий игрок открыть панель.
     *
     * @return bool
     */
    public static function canAccessPanel()
    {
        return !user()->isGuest && self::can('panel.view');
    }

    /**
     * Можно ли изменить роль этому пользователю на указанную.
     *
     * Правила:
     *  — нужен roles.manage;
     *  — владельца не трогает никто (ни администратор, ни сам себе через роли);
     *  — менять можно только того, кто строго ниже по рангу;
     *  — выдать роль равную или выше своей нельзя;
     *  — «владелец» через этот механизм не выдаётся (только grantOwner()).
     *
     * @param int      $targetId
     * @param string   $newRole
     * @param int|null $actorId
     *
     * @return array array('ok' => bool, 'error' => string)
     */
    public static function canChangeRole($targetId, $newRole, $actorId = null)
    {
        $actorId = $actorId === null ? (int) user()->getId() : (int) $actorId;
        $targetId = (int) $targetId;
        $newRole = self::normalizeRole($newRole);

        if (!self::can('roles.manage', $actorId)) {
            return array('ok' => false, 'error' => 'Менять роли может владелец или администратор с правом «Выдача ролей».');
        }

        if (!isset(self::getRoleList()[$newRole])) {
            return array('ok' => false, 'error' => 'Неизвестная роль.');
        }

        if ($newRole === self::ROLE_OWNER) {
            return array('ok' => false, 'error' => 'Роль владельца через роли не выдаётся — только через управление владельцами.');
        }

        if (self::isSiteOwner($targetId)) {
            return array('ok' => false, 'error' => 'Владелец проекта не меняется через роли. Снять владельца может только владелец.');
        }

        if ($targetId === $actorId) {
            return array('ok' => false, 'error' => 'Нельзя менять собственную роль.');
        }

        $actorRank = self::rankOf(self::roleOf($actorId));
        $targetRank = self::rankOf(self::roleOf($targetId));

        if ($targetRank >= $actorRank) {
            return array('ok' => false, 'error' => 'Нельзя изменить роль того, кто равен вам или выше по рангу.');
        }

        if (self::rankOf($newRole) >= $actorRank) {
            return array('ok' => false, 'error' => 'Нельзя выдать роль, равную вашей или выше.');
        }

        return array('ok' => true, 'error' => '');
    }

    /**
     * Выдать роль (или снять, передав ROLE_USER).
     *
     * @param int      $userId
     * @param string   $role
     * @param int|null $actorId
     * @param string   $comment
     *
     * @return bool
     */
    public static function setRole($userId, $role, $actorId = null, $comment = '')
    {
        $role = self::normalizeRole($role);
        $list = self::getRoleList();

        if (!isset($list[$role]) || $role === self::ROLE_OWNER) {
            return false;
        }

        $userId = (int) $userId;
        $actorId = $actorId === null ? (int) user()->getId() : (int) $actorId;

        if ($userId <= 0) {
            return false;
        }

        // Владельца через таблицу ролей не понижаем: у него всё равно полный доступ
        if (self::isSiteOwner($userId)) {
            return false;
        }

        $before = self::roleOf($userId);
        $exists = db()->createCommand("SELECT id FROM {{roles}} WHERE user_id = :u")->queryRow(true, array(':u' => $userId));

        if ($role === self::ROLE_USER) {
            if ($exists) {
                db()->createCommand("DELETE FROM {{roles}} WHERE user_id = :u")->execute(array(':u' => $userId));
            }
        } elseif ($exists) {
            db()->createCommand()->update('{{roles}}', array(
                'role' => $role,
                'granted_by' => $actorId,
                'updated_at' => date('Y-m-d H:i:s'),
            ), 'user_id = :u', array(':u' => $userId));
        } else {
            db()->createCommand()->insert('{{roles}}', array(
                'user_id' => $userId,
                'role' => $role,
                'granted_by' => $actorId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ));
        }

        self::$_cache[$userId] = $role;

        try {
            db()->createCommand()->insert('{{role_log}}', array(
                'user_id' => $userId,
                'role_before' => $before,
                'role_after' => $role,
                'actor_id' => $actorId,
                'comment' => mb_substr((string) $comment, 0, 255),
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Exception $e) {
            Yii::log('role_log: ' . $e->getMessage(), CLogger::LEVEL_WARNING, __METHOD__);
        }

        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Списки сотрудников и журналы                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Сотрудники: владельцы + все, кому выдали роль.
     *
     * @return array
     */
    public static function getStaff()
    {
        // ORDER BY FIELD() — только MySQL, поэтому сортировка сделана в PHP:
        // так список одинаково работает и на тестовой SQLite.
        $rows = db()->createCommand(
            "SELECT r.*, u.login, u.email
             FROM {{roles}} r
             LEFT JOIN {{users}} u ON u.user_id = r.user_id"
        )->queryAll(true);

        $has = array();

        foreach ($rows as $row) {
            $has[(int) $row['user_id']] = true;
        }

        // Владельцы, которым роль не выдавали, добавляются вручную
        foreach (self::getOwnerIds() as $ownerId) {
            if (isset($has[$ownerId])) {
                continue;
            }

            $owner = Users::model()->findByPk($ownerId);

            if (!$owner) {
                continue;
            }

            $rows[] = array(
                'id' => 0,
                'user_id' => (int) $ownerId,
                'role' => self::ROLE_OWNER,
                'granted_by' => 0,
                'created_at' => '',
                'updated_at' => '',
                'login' => $owner->login,
                'email' => $owner->email,
            );
        }

        foreach ($rows as $i => $row) {
            $isOwner = self::isSiteOwner($row['user_id']);

            $rows[$i]['is_owner'] = $isOwner;

            if ($isOwner) {
                $rows[$i]['role'] = self::ROLE_OWNER;
            }

            $rows[$i]['_rank'] = self::rankOf($rows[$i]['role']);
        }

        usort($rows, array('RoleService', 'compareStaff'));

        foreach ($rows as $i => $row) {
            unset($rows[$i]['_rank']);
        }

        return $rows;
    }

    /**
     * Сортировка команды: владельцы первыми, дальше по рангу, затем по логину.
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    public static function compareStaff(array $a, array $b)
    {
        if (!empty($a['is_owner']) !== !empty($b['is_owner'])) {
            return !empty($a['is_owner']) ? -1 : 1;
        }

        if ($a['_rank'] !== $b['_rank']) {
            return $a['_rank'] > $b['_rank'] ? -1 : 1;
        }

        return strcasecmp((string) $a['login'], (string) $b['login']);
    }

    /**
     * Последние изменения ролей.
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getLog($limit = 50)
    {
        return db()->createCommand(
            "SELECT l.*, u.login, a.login AS actor_login
             FROM {{role_log}} l
             LEFT JOIN {{users}} u ON u.user_id = l.user_id
             LEFT JOIN {{users}} a ON a.user_id = l.actor_id
             ORDER BY l.id DESC
             LIMIT " . (int) $limit
        )->queryAll(true);
    }

    /**
     * Запись действия сотрудника в журнал.
     *
     * @param string   $action
     * @param string   $targetLogin
     * @param array    $params
     * @param int|null $actorId
     *
     * @return void
     */
    public static function logAction($action, $targetLogin = '', array $params = array(), $actorId = null)
    {
        $actorId = $actorId === null ? (string) user()->getId() : (string) $actorId;

        try {
            db()->createCommand()->insert('{{staff_log}}', array(
                'actor_id' => $actorId,
                'actor_role' => self::roleOf($actorId),
                'action' => mb_substr((string) $action, 0, 48),
                'target_login' => mb_substr((string) $targetLogin, 0, 64),
                'params' => json_encode($params, JSON_UNESCAPED_UNICODE),
                'ip' => request()->getUserHostAddress(),
                'created_at' => date('Y-m-d H:i:s'),
            ));
        } catch (Exception $e) {
            Yii::log('staff_log: ' . $e->getMessage(), CLogger::LEVEL_WARNING, __METHOD__);
        }
    }

    /**
     * Журнал действий сотрудников.
     *
     * @param int $limit
     *
     * @return array
     */
    public static function getActionLog($limit = 100)
    {
        return db()->createCommand(
            "SELECT l.*, u.login
             FROM {{staff_log}} l
             LEFT JOIN {{users}} u ON u.user_id = l.actor_id
             ORDER BY l.id DESC
             LIMIT " . (int) $limit
        )->queryAll(true);
    }

    /**
     * Сброс кешей (после смены ролей и владельцев).
     *
     * @return void
     */
    public static function flushCache()
    {
        self::$_cache = array();
        self::$_owners = null;
    }
}
