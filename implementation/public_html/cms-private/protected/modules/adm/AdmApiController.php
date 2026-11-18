<?php

/**
 * База API админки: проверка прав, JSON-ответы, выборки.
 *
 * Все действия публичные и вызываются из SPA по адресу
 * /adm/<действие>. Ошибки отдаются как {"ok":false,"error":"..."}
 * с кодом 403/400, чтобы интерфейс показал тост, а не белый экран.
 */
abstract class AdmApiController extends Controller
{
    /** @var string роль текущего пользователя */
    public $role;

    /** @var array права текущей роли */
    public $perms = array();

    public function beforeAction($action)
    {
        if (user()->isGuest) {
            $this->fail('Требуется вход на сайт.', 401);
        }

        $this->role = RoleService::currentRole();

        $matrix = RoleService::getMatrix();
        $this->perms = isset($matrix[$this->role]) ? (array) $matrix[$this->role] : array();

        if (!RoleService::can('panel.view')) {
            $this->fail('Раздел доступен только сотрудникам проекта.', 403);
        }

        if(request()->getIsPostRequest()){try{request()->validateCsrfToken(new CEvent(app()));}catch(CHttpException $e){$this->fail('Обновите страницу и повторите действие: токен сессии недействителен.',400);}}
        // Explicit validation also covers requests initialized after onBeginRequest.
        return true;
    }

    /**
     * Единая карта «действие панели → право».
     *
     * До этого карта проверок жила только в этих строках: право требовал
     * каждый action сам, а проверить «у всех ли действий есть проверка»
     * можно было только глазами. Теперь карта одна, и стенд
     * (tests/php/run.php) сверяет её с реальными действиями контроллера:
     * добавили действие без права — тест краснеет.
     *
     * @return array
     */
    public static function actionPermissions()
    {
        return array(
            // чтение снимка: разделы внутри фильтруются по правам
            'state' => 'panel.view',
            'pulse' => 'panel.view',

            'settingsSave' => 'settings.view',
            'cabinetSectionToggle' => 'settings.view',

            'playerSave' => 'users.edit',
            'playerCreate' => 'users.edit',
            'gamePassword' => 'users.edit',
            'roleSet' => 'roles.manage',

            'balance' => 'finance.balance',
            'paymentSet' => 'finance.edit',

            'giveItem' => 'gm.give_item',
            'itemQueue' => 'gm.give_item',
            // бан и разбан различаются внутри действия
            'ban' => 'gm.ban_game',

            'ticketReply' => 'tickets.reply',

            'owner' => 'owner.manage',
        );
    }

    /**
     * Право, закреплённое за действием из карты.
     *
     * @param string $action
     *
     * @return string|null
     */
    protected function permissionOf($action)
    {
        $map = self::actionPermissions();

        return isset($map[$action]) ? $map[$action] : null;
    }

    /**
     * Право или отказ.
     *
     * @param string $permission
     */
    protected function need($permission)
    {
        if (!$this->has($permission)) {
            $this->fail('Недостаточно прав для этого действия (' . $permission . ').', 403);
        }
    }

    /**
     * Есть ли право.
     *
     * @param string $permission
     *
     * @return bool
     */
    protected function has($permission)
    {
        return RoleService::can($permission);
    }

    /**
     * JSON и выход.
     *
     * @param array $data
     * @param int   $status
     */
    protected function ok(array $data = array(), $status = 200)
    {
        $this->json(array_merge(array('ok' => true), $data), $status);
    }

    /**
     * Ошибка и выход.
     *
     * @param string $message
     * @param int    $status
     */
    protected function fail($message, $status = 400)
    {
        $this->json(array('ok' => false, 'error' => $message), $status);
    }

    /**
     * Отправить JSON и завершить запрос.
     *
     * @param array $data
     * @param int   $status
     */
    protected function json($data, $status = 200)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex');
            http_response_code($status);
        }

        echo json_encode((array) $data, JSON_UNESCAPED_UNICODE);

        app()->end();
    }

    /**
     * Тело запроса: JSON или обычный POST, плюс GET как запасной вариант.
     *
     * @return array
     */
    protected function input()
    {
        $raw = file_get_contents('php://input');
        $parsed = array();

        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            if (is_array($decoded)) {
                $parsed = $decoded;
            }
        }

        /*
         * На этом сервере CHttpRequest старый — getQueryParams()/getPostParams()
         * отсутствуют. Читаем через $_GET/$_POST, они есть всегда.
         *
         * Форма может прийти как multipart (вместе с файлами): тогда тело уже
         * разобрано в $_POST, а данные лежат в поле __json.
         */
        if (isset($_POST['__json']) && is_string($_POST['__json'])) {
            $fromForm = json_decode($_POST['__json'], true);

            if (is_array($fromForm)) {
                return array_merge((array) $_GET, $fromForm);
            }
        }

        return array_merge((array) $_GET, (array) $_POST, $parsed);
    }

    /**
     * Параметр запроса с приведением типа.
     *
     * @param string $key
     * @param mixed  $default
     *
     * @return mixed
     */
    protected function inp($key, $default = null)
    {
        $all = $this->input();

        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /**
     * Время в миллисекундах (прототип хранит даты числами).
     *
     * @param string|null $datetime
     *
     * @return int|null
     */
    protected function ms($datetime)
    {
        if ($datetime === null || $datetime === '' || $datetime === '0') {
            return null;
        }

        $ts = strtotime($datetime);

        return $ts === false ? null : $ts * 1000;
    }

    /**
     * Соединение с игровой базой (Lucera2).
     *
     * @return Lineage
     */
    protected function game()
    {
        return l2('gs', user()->getGsId())->connect();
    }

    /**
     * Игровая база или отказ, если связи нет.
     *
     * @return CDbConnection
     */
    protected function gameDb()
    {
        try {
            $db = $this->game()->getDb();
            $db->createCommand('SELECT 1')->queryScalar();

            return $db;
        } catch (Exception $e) {
            $this->fail('Нет связи с игровой базой сервера: ' . $e->getMessage(), 503);
        } catch (Throwable $e) {
            $this->fail('Нет связи с игровой базой сервера: ' . $e->getMessage(), 503);
        }
    }

    /**
     * Пользователь сайта по логину или e-mail.
     *
     * @param string $query
     *
     * @return object|null запись users (user_id, login, email)
     */
    protected function findUser($query)
    {
        $query = trim((string) $query);

        if ($query === '') {
            return null;
        }

        // Чтение идёт запросом, а не через ActiveRecord: поиск нужен в том
        // числе там, где модель не подгружена, и не должен тянуть объект целиком.
        $row = db()->createCommand(
            'SELECT user_id, login, email, activated FROM {{users}}
             WHERE login = :q OR email = :q LIMIT 1'
        )->queryRow(true, array(':q' => $query));

        return $row ? (object) $row : null;
    }

    /**
     * Игровой аккаунт по логину (Lucera2: accounts.login).
     *
     * @param string $login
     *
     * @return array|null
     */
    protected function gameAccount($login)
    {
        $login = trim((string) $login);

        if ($login === '') {
            return null;
        }

        try {
            $row = $this->gameDb()->createCommand(
                'SELECT login, lastactive, accessLevel, lastIP, ban_expire, l2email FROM accounts WHERE login = :l LIMIT 1'
            )->queryRow(true, array(':l' => $login));

            return $row ? $row : null;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Заблокирован ли игровой аккаунт и до когда.
     *
     * @param int $banExpire
     *
     * @return int|null метка времени окончания бана в мс
     */
    protected function banUntil($banExpire)
    {
        $banExpire = (int) $banExpire;

        // Lucera2: 0 — не забанен; иногда пишут -1 или огромное число
        if ($banExpire <= 0) {
            return null;
        }

        return $banExpire * 1000;
    }
}
