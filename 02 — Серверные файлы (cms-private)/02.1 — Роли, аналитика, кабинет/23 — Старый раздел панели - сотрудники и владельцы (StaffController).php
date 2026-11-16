<?php

/**
 * Сотрудники, роли и владельцы проекта.
 *
 * Раздел «Сотрудники» (/cabinet/panel/panelStaff):
 *  — владельцы проекта идут первыми, их роль видна, но менять её здесь нельзя;
 *  — менять роль можно только у того, кто ниже по рангу (RoleService::canChangeRole);
 *  — «Владелец проекта» роли не выдаётся: только владелец, только через
 *    grantOwner()/revokeOwner(), и последнего владельца снять нельзя.
 *
 * Раньше здесь было право users.roles, из-за чего администратор мог снять
 * владельца: проверка стояла только на «role !== admin». Сейчас все правила
 * вынесены в RoleService и проверяются и на просмотре, и на POST.
 */
class StaffController extends PanelBaseController
{
    /**
     * Список команды + форма выдачи роли + управление владельцами.
     *
     * @return void
     */
    public function actionIndex()
    {
        // Раздел целиком принадлежит владельцу: администратор видит роли в
        // карточке игрока, но командой не управляет (в матрице прав у него
        // нет roles.manage, поэтому и пункта меню он не видит).
        $this->requirePermission('roles.manage');

        if (request()->getIsPostRequest()) {
            $this->handlePost();
            $this->redirect(array('index'));
        }

        $query = trim((string) request()->getQuery('q'));
        $found = $query !== '' ? $this->findUser($query) : null;

        $this->render('//cabinet/panel-staff', array_merge($this->panelData(), array(
            'isAdmin' => RoleService::can('roles.manage'),
            'canManageOwners' => RoleService::canManageOwners(),
            'staff' => RoleService::getStaff(),
            'log' => RoleService::getLog(50),
            'query' => $query,
            'found' => $found,
            'foundRole' => $found ? RoleService::roleOf($found->user_id) : null,
            'roles' => RoleService::getRoleList(),
            'assignable' => RoleService::getAssignableRoles(),
            'shortNames' => RoleService::getShortNames(),
            'ranks' => RoleService::getRanks(),
            'notes' => RoleService::getRoleNotes(),
            'denied' => RoleService::getDenied(),
            'permissionTitles' => RoleService::getPermissionTitles(),
            'matrix' => RoleService::getMatrix(),
            'ownerIds' => RoleService::getOwnerIds(),
            'myRole' => RoleService::currentRole(),
            'myRank' => RoleService::rankOf(RoleService::currentRole()),
        )));
    }

    /**
     * Обработка форм раздела.
     *
     * @return void
     */
    protected function handlePost()
    {
        $act = (string) request()->getPost('act');

        switch ($act) {
            case 'set_role':
                $this->postSetRole();
                break;

            case 'owner_grant':
                $this->postGrantOwner();
                break;

            case 'owner_revoke':
                $this->postRevokeOwner();
                break;

            default:
                user()->setFlash(FlashConst::MESSAGE_ERROR, 'Неизвестное действие.');
        }
    }

    /**
     * Смена роли. Все ограничения — в RoleService::canChangeRole().
     *
     * @return void
     */
    protected function postSetRole()
    {
        $this->requirePermission('roles.manage');

        $userId = (int) request()->getPost('user_id');
        $role = (string) request()->getPost('role');
        $comment = trim((string) request()->getPost('comment'));

        if (!Users::model()->exists('user_id = :u', array(':u' => $userId))) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Пользователь не найден.');

            return;
        }

        $check = RoleService::canChangeRole($userId, $role);

        if (!$check['ok']) {
            // В журнал — попытку, в интерфейс — причину отказа
            RoleService::logAction('Отказ в смене роли', $this->loginOf($userId), array(
                'role' => $role,
                'reason' => $check['error'],
            ));

            user()->setFlash(FlashConst::MESSAGE_ERROR, $check['error']);

            return;
        }

        $login = $this->loginOf($userId);
        $before = RoleService::roleOf($userId);

        if (!RoleService::setRole($userId, $role, user()->getId(), $comment)) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Не удалось изменить роль.');

            return;
        }

        RoleService::logAction('Смена роли', $login, array(
            'user_id' => $userId,
            'role_before' => $before,
            'role_after' => $role,
            'comment' => $comment,
        ));

        $roles = RoleService::getRoleList();

        user()->setFlash(FlashConst::MESSAGE_SUCCESS, $role === RoleService::ROLE_USER
            ? 'Роль снята с игрока ' . $login . '.'
            : 'Игроку ' . $login . ' выдана роль: ' . $roles[$role] . '. Изменения действуют сразу.');
    }

    /**
     * Выдача владельца проекта. Только владелец.
     *
     * @return void
     */
    protected function postGrantOwner()
    {
        if (!RoleService::canManageOwners()) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Выдавать владельца может только владелец проекта.');

            return;
        }

        $userId = (int) request()->getPost('user_id');

        $result = RoleService::grantOwner($userId, user()->getId(), trim((string) request()->getPost('comment')));

        user()->setFlash(
            $result['ok'] ? FlashConst::MESSAGE_SUCCESS : FlashConst::MESSAGE_ERROR,
            $result['ok']
                ? 'Игрок ' . $this->loginOf($userId) . ' стал владельцем проекта. Владельцев сейчас: ' . RoleService::countOwners() . '.'
                : $result['error']
        );
    }

    /**
     * Снятие владельца. Только владелец, последнего снять нельзя.
     *
     * @return void
     */
    protected function postRevokeOwner()
    {
        if (!RoleService::canManageOwners()) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, 'Снимать владельца может только владелец проекта.');

            return;
        }

        $userId = (int) request()->getPost('user_id');

        $result = RoleService::revokeOwner($userId, user()->getId(), trim((string) request()->getPost('comment')));

        user()->setFlash(
            $result['ok'] ? FlashConst::MESSAGE_SUCCESS : FlashConst::MESSAGE_ERROR,
            $result['ok']
                ? 'Игрок ' . $this->loginOf($userId) . ' больше не владелец проекта. Владельцев сейчас: ' . RoleService::countOwners() . '.'
                : $result['error']
        );
    }

    /**
     * Полный журнал действий сотрудников и операций кошелька.
     *
     * @return void
     */
    public function actionLog()
    {
        $this->requirePermission('panel.view');

        $this->render('//cabinet/panel-log', array_merge($this->panelData(), array(
            'staffLog' => RoleService::getActionLog(200),
            'walletLog' => WalletService::getAllHistory('', 200),
            'roleLog' => RoleService::getLog(100),
            'roles' => RoleService::getRoleList(),
        )));
    }

    /**
     * Логин пользователя по ID.
     *
     * @param int $userId
     *
     * @return string
     */
    protected function loginOf($userId)
    {
        $user = Users::model()->findByPk((int) $userId);

        return $user ? (string) $user->login : ('#' . (int) $userId);
    }
}
