<?php

use app\modules\cabinet\models\search\AuthHistorySearch;
use app\modules\cabinet\models\search\MessagesSearch;
use app\modules\cabinet\models\search\TransactionHistorySearch;
use app\modules\cabinet\models\search\UserBonusesSearch;

class DefaultController extends CabinetBaseController
{
    const SUMMARY_ROWS = 3;

    /** Стоимость календаря событий по умолчанию (переопределяется настройкой calendar.price) */
    const CALENDAR_PRICE = 30;

    public function actionIndex()
    {
        if (class_exists('PortalRuntime') && PortalRuntime::enabled()) {
            $this->forward('/cabinet/portal/index');
            return;
        }
        $userId = user()->getId();

        // same query and cache key as CharactersController, so the home and the section list match
        $cacheName = 'lucera-schema-v1.' . strtr(CacheNames::CABINET_CHARACTER_LIST, [':gs_id' => user()->getGsId(), ':user_id' => $userId . '_' . user()->getGameLogin()]);
        if (!($chars = cache()->get($cacheName))) {
            $chars = ['error' => false, 'characters' => []];

            try {
                $l2 = l2('gs', user()->gs_id)->connect();

                $command = $l2->getDb()->createCommand();
                $command->where('account_name = :account_name', [':account_name' => user()->getGameLogin()]);

                $chars['characters'] = $l2->characters($command)->queryAll();

                cache()->set($cacheName, $chars, 600);
            } catch (Exception $e) {
                $chars['error'] = $e->getMessage();
            }
        }

        // the section pages' own data providers, cut to the first rows: same order, filters and caching
        $firstRows = function (CActiveDataProvider $provider) {
            $provider->getPagination()->setPageSize(self::SUMMARY_ROWS);
            $provider->getPagination()->setCurrentPage(0);
            $provider->setSort(false);

            return $provider->getData();
        };

        $successLogins = UsersAuthLogs::model()->findAll([
            'condition' => 'user_id = :u AND status = :s',
            'params' => [':u' => $userId, ':s' => UsersAuthLogs::STATUS_AUTH_SUCCESS],
            'order' => 'id DESC',
            'limit' => 2,
        ]);

        $db = Referals::model()->getDbConnection();

        $this->render('//cabinet/index', [
            'calendarPrice' => (int) CalendarService::getConfig()['price'],
            'characters' => array_slice($chars['characters'], 0, self::SUMMARY_ROWS),
            'charactersTotal' => count($chars['characters']),
            'charactersError' => $chars['error'] !== false,
            'messages' => $firstRows((new MessagesSearch())->search()),
            // read state straight from the DB: the cached message list is only refreshed by its own tag
            'unread' => (int)UserMessages::model()->count('user_id = :u AND `read` = :r', [
                ':u' => $userId,
                ':r' => UserMessages::STATUS_NOT_READ,
            ]),
            'openTickets' => Tickets::model()->opened()->count('user_id = :u', [':u' => $userId]),
            'newReplies' => Tickets::getCountNewMessagesForUser(),
            'referralAllowed' => (bool)config('referral_program.allow'),
            'referalsCount' => count(Referals::getByUserId($userId)),
            'referalsEarned' => (float)$db->createCommand()
                ->select('SUM(profit)')->from(ReferalsProfit::model()->tableName())
                ->where('referer_id = :u', [':u' => $userId])->queryScalar(),
            'bonusesCount' => (int)(new UserBonusesSearch())->search()->getTotalItemCount(),
            'transactions' => $firstRows((new TransactionHistorySearch())->search()),
            'logins' => $firstRows((new AuthHistorySearch())->search()),
            // the newest success row is the current session, so the previous one is the "last login"
            'lastLogin' => isset($successLogins[1]) ? $successLogins[1] : (isset($successLogins[0]) ? $successLogins[0] : null),
        ]);
    }

    /**
     * Календарь событий сервера (/cabinet/calendar/): витрина и личный сезон.
     */
    public function actionCalendar()
    {
        $userId = (int) user()->getId();
        $config = CalendarService::getConfig();
        $season = CalendarService::getActiveSeason($userId);
        $lastSeason = CalendarService::getLastSeason($userId);
        $state = $season ? CalendarService::getDays($season) : null;

        // Наградные дни показываем с названиями и иконками предметов
        $preview = [];

        foreach (CalendarService::getRewards() as $day => $items) {
            $preview[$day] = CalendarService::decorateItems($items);
        }

        $this->render('//cabinet/calendar', [
            'calendarPrice' => (int) $config['price'],
            'calendarDays' => (int) $config['days'],
            'calendarCurrency' => $config['currency'],
            'season' => $season,
            'lastSeason' => $lastSeason,
            'state' => $state,
            'preview' => $preview,
            'characters' => CalendarService::getCharacters($userId),
            'balance' => WalletService::getBalance($userId),
            'premiumDays' => CalendarService::getPremiumDays(),
        ]);
    }

    /**
     * Купить календарь (оплата с кошелька).
     */
    public function actionCalendarBuy()
    {
        if (!request()->getIsPostRequest()) {
            $this->redirect(['/cabinet/calendar']);
        }

        try {
            $result = CalendarService::buy((int) user()->getId());

            user()->setFlash(
                FlashConst::MESSAGE_SUCCESS,
                $result['already']
                    ? 'У вас уже есть активный календарь — награды доступны.'
                    : 'Календарь куплен! Первый день уже открыт.'
            );
        } catch (Exception $e) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, $e->getMessage());
        }

        $this->redirect(['/cabinet/calendar']);
    }

    /**
     * Забрать награду дня календаря.
     */
    public function actionCalendarClaim()
    {
        if (!request()->getIsPostRequest()) {
            $this->redirect(['/cabinet/calendar']);
        }

        $day = (int) request()->getPost('day');
        $charName = trim((string) request()->getPost('char_name'));

        if ($charName === '') {
            $charName = (string) request()->getPost('char');
        }

        try {
            $result = CalendarService::claimDay((int) user()->getId(), $day, $charName);

            $text = [];

            foreach ($result['items'] as $item) {
                $text[] = $item['name'] . ' ×' . (int) $item['count'];
            }

            user()->setFlash(FlashConst::MESSAGE_SUCCESS, sprintf(
                class_exists('PortalRuntime') && PortalRuntime::enabled() && PortalRuntime::store()->enabled('warehouse')
                    ? 'Награда за день %d получена: %s. Откройте склад для выдачи персонажу %s.'
                    : 'Награда за день %d получена: %s. Предметы придут персонажу %s при следующем входе в игру.',
                (int) $result['day'],
                implode(', ', $text),
                CHtml::encode($result['char_name'])
            ));
        } catch (Exception $e) {
            user()->setFlash(FlashConst::MESSAGE_ERROR, $e->getMessage());
        }

        $this->redirect(['/cabinet/calendar']);
    }

    public function actionLogout()
    {
        user()->logout();
        $this->redirect(app()->homeUrl);
    }
}
