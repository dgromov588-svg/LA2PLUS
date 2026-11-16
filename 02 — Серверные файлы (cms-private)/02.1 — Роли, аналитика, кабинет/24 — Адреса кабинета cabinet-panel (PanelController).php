<?php

/**
 * Панель управления сотрудников (вторая админка).
 *
 * Открывается из кабинета отдельной кнопкой «Админка»:
 *   /cabinet/panel/panel
 *
 * Готовые разделы лежат в модуле panel (Game, Finance, Users, Staff, Default).
 * Этот контроллер разводит адреса панели по ним: все ссылки из меню панели
 * начинаются с /cabinet/panel/..., и права проверяются в одном месте.
 *
 * Оформление: разделы модуля panel рендерятся в каркасе //layouts/admin —
 * его ставит PanelBaseController::beforeAction(). Разделы, которые рендерятся
 * прямо отсюда (владельцы, аккаунты, персонажи, кланы, аналитика), ставят тот
 * же каркас сами, в beforeAction() ниже.
 *
 * Доступ: только сотрудники (RoleService::can('panel.view')).
 * Владельцы проекта: /cabinet/panel/panelOwners — только для владельца.
 * JSON аналитики:    /cabinet/panel/panelAnalyticsData?days=7
 */
class PanelController extends CabinetBaseController
{
    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (user()->isGuest) {
            $this->redirect(user()->loginUrl);
        }

        if (!RoleService::can('panel.view')) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Раздел доступен только сотрудникам проекта. Обратитесь к администрации.');

            $this->redirect(['/cabinet/default/index']);
        }

        // Отдельный каркас админки, а не игровой кабинет.
        // JSON-действия (data/live) до рендера не доходят — каркас им не мешает.
        $this->layout = '//layouts/admin';

        return true;
    }

    /** Пульт: сводка по серверу, последние действия. */
    public function actionPanel()
    {
        $this->forward('/panel/default/index');
    }

    /** Игроки: поиск по логину или e-mail. */
    public function actionPanelUsers()
    {
        $this->forward('/panel/users/index');
    }

    /** Карточка игрока: баланс, персонажи, платежи, кошелёк. */
    public function actionPanelView($id = 0)
    {
        $id = (int) $id;

        if ($id <= 0) {
            $this->redirect(['/cabinet/panel/panelUsers']);
        }

        $this->forward('/panel/users/view', ['id' => $id]);
    }

    /** Платежи: заявки, ручное начисление и списание. */
    public function actionPanelFinance()
    {
        $this->forward('/panel/finance/index');
    }

    /** Игровые действия: выдача предметов, бан аккаунта. */
    public function actionPanelGame()
    {
        $this->forward('/panel/game/index');
    }

    /** Обработчики форм раздела «Игровые действия». */
    public function actionPanelGiveItem()
    {
        $this->forward('/panel/game/giveItem');
    }

    public function actionPanelBan()
    {
        $this->forward('/panel/game/ban');
    }

    /** Сотрудники и роли. */
    public function actionPanelStaff()
    {
        $this->forward('/panel/staff/index');
    }

    /** Журнал действий сотрудников и кошельков. */
    public function actionPanelLog()
    {
        $this->forward('/panel/staff/log');
    }

    /* ------------------------------------------------------------------ */
    /* Владельцы проекта и JSON аналитики                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Владельцы проекта: список, выдача, снятие.
     *
     * Доступ — только владельцу (RoleService::canManageOwners()). Администратор
     * не попадёт сюда даже по прямой ссылке: «владелец» не выдаётся таблицей
     * ролей, это отдельный список в protected/config/params.php.
     *
     * POST act=grant|revoke, user_id, comment
     */
    public function actionPanelOwners()
    {
        if (!RoleService::canManageOwners()) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Управление владельцами доступно только владельцу проекта.');

            $this->redirect(array('/cabinet/panel/panelStaff'));
        }

        if (request()->getIsPostRequest()) {
            $act = (string) request()->getPost('act');
            $userId = (int) request()->getPost('user_id');
            $comment = trim((string) request()->getPost('comment'));

            $result = $act === 'grant'
                ? RoleService::grantOwner($userId, user()->getId(), $comment)
                : RoleService::revokeOwner($userId, user()->getId(), $comment);

            user()->setFlash(
                $result['ok'] ? FlashConst::MESSAGE_SUCCESS : FlashConst::MESSAGE_ERROR,
                $result['ok']
                    ? ('Список владельцев обновлён. Владельцев сейчас: ' . RoleService::countOwners() . '.')
                    : $result['error']
            );

            $this->redirect(array('/cabinet/panel/panelOwners'));
        }

        $owners = array();

        foreach (RoleService::getOwnerIds() as $ownerId) {
            $owner = Users::model()->findByPk($ownerId);

            if (!$owner) {
                continue;
            }

            $owners[] = array(
                'user_id' => (int) $owner->user_id,
                'login' => $owner->login,
                'email' => $owner->email,
                'created_at' => $owner->created_at,
            );
        }

        $this->render('//cabinet/panel-owners', array_merge($this->panelData(), array(
            'owners' => $owners,
            'canRevoke' => count($owners) > 1,
        )));
    }

    /**
     * Все блоки аналитики одним JSON.
     *
     * GET /cabinet/panel/panelAnalyticsData?days=7|14|30
     * Права: analytics.view.
     */
    public function actionPanelAnalyticsData()
    {
        $this->forward('/panel/analytics/data');
    }

    /**
     * Быстрый счётчик для шапки панели.
     *
     * GET /cabinet/panel/panelLive → {online, tickets, pending, time}
     */
    public function actionPanelLiveJson()
    {
        $this->forward('/panel/analytics/live');
    }

    /* ------------------------------------------------------------------ */
    /* Служебные проверки                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * Права на действие: при нехватке прав — 403, а не «белый экран».
     *
     * @param string $permission
     *
     * @return void
     */
    protected function requirePermission($permission)
    {
        if (!RoleService::can($permission)) {
            throw new CHttpException(403, 'Недостаточно прав для этого действия.');
        }
    }

    /**
     * Общие данные для разделов панели (роль, права, счётчик тикетов).
     *
     * @return array
     */
    protected function panelData()
    {
        $role = RoleService::currentRole();
        $roles = RoleService::getRoleList();
        $short = RoleService::getShortNames();

        return [
            'role' => $role,
            'roleName' => isset($roles[$role]) ? $roles[$role] : '',
            'roleShort' => isset($short[$role]) ? $short[$role] : $role,
            'can' => function ($permission) {
                return RoleService::can($permission);
            },
            'newTickets' => Tickets::getCountNewMessagesForAdmin(),
            'isOwner' => RoleService::isSiteOwner(),
            'canManageOwners' => RoleService::canManageOwners(),
            'ownerCount' => RoleService::countOwners(),
            'myRank' => RoleService::rankOf($role),
        ];
    }

    /**
     * Доступ к игровой базе сервера.
     *
     * @return ServerDataService
     */
    protected function serverData()
    {
        return new ServerDataService(user()->getGsId());
    }

    /* ------------------------------------------------------------------ */
    /* Аккаунты, персонажи, кланы сервера                                  */
    /* ------------------------------------------------------------------ */

    /** Игровые аккаунты сервера. */
    public function actionPanelAccounts()
    {
        $this->requirePermission('server.view');

        $service = $this->serverData();
        $query = trim((string) request()->getQuery('q', ''));
        $filter = (string) request()->getQuery('filter', 'all');
        $page = max(1, (int) request()->getQuery('page', 1));

        list($rows, $total) = $service->getAccounts($query, $filter, $page, 50);

        $this->render('//cabinet/panel-accounts', array_merge($this->panelData(), [
            'rows' => $rows,
            'total' => $total,
            'query' => $query,
            'filter' => $filter,
            'page' => $page,
            'perPage' => 50,
            'serverError' => $service->getError(),
            'summary' => $service->getSummary(),
        ]));
    }

    /** Карточка игрового аккаунта. */
    public function actionPanelAccountView()
    {
        $this->requirePermission('server.view');

        $service = $this->serverData();
        $login = trim((string) request()->getQuery('login', ''));
        $account = $login !== '' ? $service->getAccount($login) : null;

        if (!$account) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Игровой аккаунт не найден.');
            $this->redirect(['/cabinet/panel/panelAccounts']);
        }

        $this->render('//cabinet/panel-account-view', array_merge($this->panelData(), [
            'account' => $account,
            'serverError' => $service->getError(),
        ]));
    }

    /** Персонажи сервера. */
    public function actionPanelCharacters()
    {
        $this->requirePermission('server.view');

        $service = $this->serverData();
        $query = trim((string) request()->getQuery('q', ''));
        $filter = (string) request()->getQuery('filter', 'all');
        $page = max(1, (int) request()->getQuery('page', 1));

        list($rows, $total) = $service->getCharacters($query, $filter, $page, 50);

        $this->render('//cabinet/panel-characters', array_merge($this->panelData(), [
            'rows' => $rows,
            'total' => $total,
            'query' => $query,
            'filter' => $filter,
            'page' => $page,
            'perPage' => 50,
            'serverError' => $service->getError(),
        ]));
    }

    /** Кланы и замки сервера. */
    public function actionPanelClans()
    {
        $this->requirePermission('server.view');

        $service = $this->serverData();

        $this->render('//cabinet/panel-clans', array_merge($this->panelData(), [
            'clans' => $service->getClans(),
            'serverError' => $service->getError(),
            'summary' => $service->getSummary(),
        ]));
    }

    /* ------------------------------------------------------------------ */
    /* Аналитика посещаемости                                              */
    /* ------------------------------------------------------------------ */

    /** Аналитика сайта: метрики за период, разбивка по дням и часам, список визитов. */
    public function actionPanelAnalytics()
    {
        $this->requirePermission('analytics.view');

        $period = (string) request()->getQuery('period', 'today');
        $allowed = ['today', 'yesterday', '7', '30', 'custom'];

        if (!in_array($period, $allowed, true)) {
            $period = 'today';
        }

        switch ($period) {
            case 'yesterday':
                $dateFrom = $dateTo = date('Y-m-d', strtotime('-1 day'));
                break;
            case '7':
                $dateFrom = date('Y-m-d', strtotime('-6 days'));
                $dateTo = date('Y-m-d');
                break;
            case '30':
                $dateFrom = date('Y-m-d', strtotime('-29 days'));
                $dateTo = date('Y-m-d');
                break;
            case 'custom':
                $dateFrom = (string) request()->getQuery('from', date('Y-m-d'));
                $dateTo = (string) request()->getQuery('to', date('Y-m-d'));
                break;
            default:
                $dateFrom = $dateTo = date('Y-m-d');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) || strtotime($dateFrom) === false) {
            $dateFrom = date('Y-m-d');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) || strtotime($dateTo) === false) {
            $dateTo = date('Y-m-d');
        }

        if (strtotime($dateFrom) > strtotime($dateTo)) {
            $swap = $dateFrom;
            $dateFrom = $dateTo;
            $dateTo = $swap;
        }

        // Глубже года не смотрим: тяжёлый запрос подвесил бы панель
        if (strtotime($dateFrom) < strtotime('-370 days')) {
            $dateFrom = date('Y-m-d', strtotime('-370 days'));
        }

        $summary = AnalyticsService::getSummary($dateFrom, $dateTo);

        // Сравнение с предыдущим периодом такой же длины
        $daysLength = max(1, (int) floor((strtotime($dateTo) - strtotime($dateFrom)) / 86400) + 1);
        $prevTo = date('Y-m-d', strtotime($dateFrom . ' -1 day'));
        $prevFrom = date('Y-m-d', strtotime($prevTo . ' -' . ($daysLength - 1) . ' days'));
        $previous = AnalyticsService::getSummary($prevFrom, $prevTo);

        $summary['prev_visitors'] = $previous['visitors'];
        $summary['prev_visits'] = $previous['visits'];
        $summary['prev_views'] = $previous['views'];
        $summary['prev_authorized'] = $previous['authorized'];
        $summary['prev_registered'] = $previous['registered'];
        $summary['prev_logins'] = $previous['logins'];

        $filters = [
            'q' => (string) request()->getQuery('q', ''),
            'device' => (string) request()->getQuery('device', ''),
            'only_users' => (int) request()->getQuery('only_users', 0),
            'only_online' => (int) request()->getQuery('only_online', 0),
            'with_bots' => (int) request()->getQuery('with_bots', 0),
            'page' => max(1, (int) request()->getQuery('page', 1)),
            'per_page' => 30,
        ];

        $visits = AnalyticsService::getVisits(array_merge($filters, [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]));

        $this->render('//cabinet/panel-analytics', array_merge($this->panelData(), [
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'summary' => $summary,
            'daily' => AnalyticsService::getDailySeries($dateFrom, $dateTo),
            'hourly' => AnalyticsService::getHourlySeries(24),
            'visits' => $visits,
            'filters' => $filters,
            'topPages' => AnalyticsService::getTopPages($dateFrom, $dateTo, 8),
            'sources' => AnalyticsService::getSources($dateFrom, $dateTo),
            'breakdown' => AnalyticsService::getBreakdown($dateFrom, $dateTo),
            'topUsers' => AnalyticsService::getTopUsers($dateFrom, $dateTo, 10),
            'hasTable' => AnalyticsService::hasTable(),
        ]));
    }

    /** Живые цифры для автообновления страницы аналитики (JSON). */
    public function actionPanelAnalyticsLive()
    {
        $this->requirePermission('analytics.view');

        $summary = AnalyticsService::getSummary(date('Y-m-d'), date('Y-m-d'));

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'online' => (int) $summary['online'],
            'today_visitors' => (int) $summary['visitors'],
            'today_views' => (int) $summary['views'],
            'registered' => (int) $summary['registered'],
            'time' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE);

        app()->end();
    }
}
