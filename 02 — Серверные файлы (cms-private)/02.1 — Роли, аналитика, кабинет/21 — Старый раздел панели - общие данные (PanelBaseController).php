<?php

/**
 * Базовая точка входа для контроллеров панели.
 *
 * Админка оформлена отдельным каркасом //layouts/admin (без баланса, календаря
 * и игрового меню кабинета). Каркас ставится здесь, в beforeAction(), потому что
 * разделы открываются двумя путями — forward() из cabinet/panel/* и прямое
 * обращение к /panel/*; оба проходят через этот класс.
 */
abstract class PanelBaseController extends CabinetBaseController
{
    /** @var string роль текущего пользователя */
    public $role;

    public function beforeAction($action)
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->role = RoleService::currentRole();

        // Отдельный каркас админки: не наследует игровой кабинет.
        $this->layout = '//layouts/admin';

        if (!RoleService::can('panel.view')) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Раздел доступен только сотрудникам проекта. Обратитесь к владельцу проекта.');

            $this->redirect(['/cabinet/default/index']);
        }

        return true;
    }

    /**
     * Проверка права или 403.
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
     * Общие данные для меню панели.
     *
     * @return array
     */
    protected function panelData()
    {
        $roles = RoleService::getRoleList();

        return [
            'role' => $this->role,
            'roleName' => isset($roles[$this->role]) ? $roles[$this->role] : '',
            'can' => function ($permission) {
                return RoleService::can($permission);
            },
            'newTickets' => Tickets::getCountNewMessagesForAdmin(),
            // Общие данные для шапки панели: имя роли, владелец ли игрок, ранг
            'isOwner' => RoleService::isSiteOwner(),
            'canManageOwners' => RoleService::canManageOwners(),
            'ownerCount' => RoleService::countOwners(),
            'myRank' => RoleService::rankOf(RoleService::currentRole()),
            'roleShort' => isset(RoleService::getShortNames()[$this->role])
                ? RoleService::getShortNames()[$this->role]
                : $this->role,
        ];
    }

    /**
     * Поиск пользователя сайта по логину или e-mail.
     *
     * @param string $query
     *
     * @return Users|null
     */
    protected function findUser($query)
    {
        $query = trim((string) $query);

        if ($query === '') {
            return null;
        }

        return Users::model()->find('login = :q OR email = :q', [':q' => $query]);
    }
}
