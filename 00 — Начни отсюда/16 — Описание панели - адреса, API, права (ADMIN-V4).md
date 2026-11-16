# Админ-панель v4 (Aurum) · la2plus.com

Панель работает на живых данных прода: `/admin-v4` — страница, `/adm/*` — JSON-API.
Никакой копии прототипа в памяти браузера: каждый экран читает базу, каждая форма
пишет в базу.

---

## 1. Адреса

| Что | Адрес | Примечание |
|---|---|---|
| Панель | `https://la2plus.com/admin` | единственный вход, маршрут `adm/page/index` |
| Разделы | `/admin/players`, `/admin/tickets`, `/admin/finance`, … | раздел берётся из адреса |
| Статика | `https://la2plus.com/admin/asset?p=css/app.css` | `p=css/<файл>` или `p=js/<файл>` |
| API | `https://la2plus.com/adm/<action>` | GET для чтения, POST для записи |

Старая панель (модуль `panel` + `cabinet/panel/*`) **удалена с сервера 15.11.2026**.
Старые закладки `/admin/panelUsers`, `/admin/panelFinance`, `/admin/v4` ведут
на новую панель. `/cabinet/panel/panel` больше не существует (404) — это ожидаемо.

Гость, открывший `/admin`, уходит на `/login/?next=/admin`; сотрудник без
права `panel.view` — в `/cabinet/`.

### Почему статика через `?p=`
Сегмент маршрута со слэшем (`<path:...>`) на этом Yii не матчится — путь передаётся
параметром запроса, контроллер читает `$_GET['p']`.

---

## 2. Состав модуля на проде

```
cms-private/protected/modules/adm/
├── AdmModule.php            setControllerPath(.../controllers) + setImport()
├── AdmApiController.php     ввод/вывод JSON, игровая база, CSRF
├── AdmBaseController.php    вся логика (экраны состояния и все действия)
├── AdmPageController.php    страница панели и раздача статики
├── controllers/
│   ├── AdmController.php    обёртка: маршрут /adm/<action>
│   └── PageController.php   обёртка: маршруты /admin-v4
├── views/adm/panel.php      HTML-оболочка панели
└── assets/{css,js}          base.css, themes.css, app.css, data/api/core/views/shell.js
```

Маршруты прописаны в `cms-private/protected/config/main.php`:

```php
'admin'                    => 'adm/page/index',
'admin/asset'              => 'adm/page/asset',
'admin/panel<...>'         => 'adm/page/index',   // старые закладки
'admin/<section:[a-z0-9]+>'=> 'adm/page/index',   // разделы SPA
'adm/<action:[a-zA-Z]+>'   => 'adm/adm/<action>',
```

---

## 3. Вход и права

Вход обычный, через сайт: `dgromov588@gmail.com` → на сайте `h2mbenzo1488`.
Роли хранятся отдельно от игрового аккаунта (`RoleService`).

Права, которые использует панель:

```
panel.view, analytics.view, characters.view, finance.view, finance.balance,
finance.edit, gm.give_item, gm.ban_game, gm.unban_game, log.view,
owner.manage, roles.manage, server.view, staff.view, settings.view,
tickets.close, tickets.reply, users.edit, users.view
```

Аккаунт `h2mbenzo` (#1/#31) не трогали — пароль и роли прежние.

---

## 4. Экраны (все на живых данных)

| Экран | Что показывает | Откуда данные |
|---|---|---|
| Пульт | онлайн, тикеты, заявки, деньги за сутки | обе базы |
| Игроки | 174 аккаунта сайта, поиск, фильтры, CSV | `users`, `accounts` |
| Аккаунты сервера | 192 игровых аккаунта, бан/разбан | `accounts` (gs) |
| Персонажи | 192 персонажа, уровень, класс, клан, онлайн | `characters` + `character_subclasses` |
| Кланы | 10 кланов, лидер, состав, репутация, замок | `clan_data`, `clan_subpledges`, `ally_data` |
| Игровые действия | выдача предметов, блокировка | `items_delayed`, `bans` |
| Платежи | 138 заявок, подтверждение/отклонение | `transactions` |
| Тикеты | переписка, ответ, закрытие | тикеты сайта |
| Сотрудники | роли и права | `RoleService` |
| Аналитика | визиты, регистрации, деньги | `visit_log`, `users` |
| Журнал | кто, когда, что сделал | `RoleService::logAction` |
| Настройки | шлюзы платежей (только чтение) | `ca-config.php` |

Снимок для всех экранов приходит одним запросом `GET /adm/state`.

---

## 5. Что пишется в базу

Проверено на проде, каждое действие с откатом:

| Действие | Куда пишет | Проверка |
|---|---|---|
| Начислить / списать баланс | кошелёк сайта + журнал | 0 → 0.01 → 0 |
| Выдать предмет | `items_delayed` (очередь Lucera2, сервер выдаёт при входе) | строка создана и удалена |
| Бан / разбан | `accounts.ban_expire` | ставится и снимается |
| Ответ в тикет | `tickets_answers` + статус | ответ, закрытие, переоткрытие |
| Подтвердить / отклонить заявку | `transactions.status` + кошелёк + проводка | начисление 105 Coin проверено и откачено |
| Очередь выдачи: «выдано» / «отменить» | `items_delayed` | 5 реальных выдач на экране |
| Смена роли | `RoleService::setRole` + журнал | идемпотентно |
| Создание игрока | `Users::create` + `insertAccount` | — |
| Смена игрового пароля | `accounts.password` (хэш сервера) | — |


### Важно: панель не «демо»
В меню профиля раньше было написано «демо · данные из БД на 13.11.2026» — это
подпись прототипа. Сейчас там строка «Данные из базы на <дата и время снимка>»,
время берётся из `builtAt`, который сервер отдаёт в `/adm/state`.

* Кнопки «Сбросить демо-данные» больше нет. На её месте «Обновить из базы» —
  перечитывает состояние через `DB.reload()` (сервер — единственный источник).
* Блок ролей в профиле — **предпросмотр прав**: клик по роли меняет только то,
  что видно на экране (набор разделов и кнопок), роль в базе не меняется.
  Обратно — кнопка «Владелец».
* «Перейти в кабинет» ведёт на настоящий `/cabinet/`.

### Важно: окна и мобильный режим
* Кнопки «Отмена» и «×» в окнах помечены `data-close`; обработчик живёт в `core.js`
  (клик на документе, потому что содержимое окна перерисовывается).
* На экранах «Тикеты» и «Платежи» при ширине ≤1080px список и карточка не
  помещаются рядом: карточка открывается отдельным экраном, у неё есть
  кнопка «К списку». CSS у `@media(max-width:1080px)` скрывает `.fin-detail`,
  показывая его только с классом `.is-mob`.

### Важно: выдача предметов
Старая панель писала в `items_external` — **в этой сборке Lucera2 такой таблицы нет**.
Выдача идёт в `items_delayed` (`payment_status = 1`), описание `admin:<логин>: <причина>`.
Прямой `INSERT` в `items` запрещён: ломает `object_id` на живом сервере.

### Важно: схема Lucera2 ≠ классический L2J
```
characters:  obj_Id, account_name, char_name, clanid, pvpkills, pkkills, karma, online, lastAccess
             (нет level и classid)
character_subclasses: char_obj_id, class_id, level, active   ← уровень и класс здесь
clan_data:   clan_id, clan_level, reputation_score, hasCastle, ally_id
             (нет clan_name, leader_name, ally_name)
clan_subpledges: clan_id, type (0 = основной), name, leader_id
ally_data:   ally_id, ally_name
```

---

## 6. CSRF

В конфиге прода имя токена — `GHTWEB_CSRF_TOKEN`, и `CHttpRequest` читает его
**только из `$_POST`** (заголовки не смотрит). Поэтому:

* шаблон отдаёт два `meta`: `csrf-token` и `csrf-name`;
* `api.js` собирает `FormData`: поле с именем из `csrf-name` = токен + `__json` = данные;
* `AdmApiController::input()` читает `$_GET`, затем `__json`, затем `$_POST`.

Обычный `Content-Type: application/json` на этом сервере даёт 400 «Невозможно определить CSRF».

---

## 7. Откат

### Быстрый откат панели (сайт продолжает работать)
1. Удалить каталог `cms-private/protected/modules/adm/`.
2. Восстановить конфиг: `cms-private/protected/config/main.php` ← `main.php.bak-adm-v4-20261115`.
3. Проверить `https://la2plus.com/` (200) и `https://la2plus.com/admin/` (старая панель).

Все файлы заливались через cPanel API с перезаписью; бэкап конфига лежит рядом.

### Если правки задели игру
* `items_delayed` — удалить строки `WHERE description LIKE 'admin:%'`.
* `accounts.ban_expire` — выставить `0` у нужного логина.
* Баланс — обратная операция через «Начислить/Списать» либо `WalletService`.

---

## 8. Проверки

Локальный стенд (схема приведена к Lucera2):

```bash
cd v4
rm -f /tmp/bench_site.db /tmp/bench_game.db
ADM_BENCH_DB=/tmp/bench_site.db ADM_BENCH_GAME=/tmp/bench_game.db php tests/php/run.php
# Пройдено: 80, провалено: 0
```

Тесты deploy-слоя: `live/deploy/files/tests/php/run.php` → 121 OK / 0 FAIL.

Приёмочный прогон на проде (390×844, вход владельцем):
страница 200, все 7 файлов статики 200, `/adm/state` 200, 12 экранов с данными,
ошибок JS нет.

### Аналитика: конверты сервиса
`AnalyticsService` возвращает не списки, а объекты с обёрткой:
`getDailySeries → {days, totals}`, `getHourlySeries → {hours}`,
`getVisits → {rows, total}`, `getBreakdown → {device, browser, os}`.
Нормализация в `AdmBaseController` разворачивает обёртки — если экран снова
покажет пустые графики, смотреть сначала сюда.

### Настройки пишутся в базу
Платёжные шлюзы, тестовый режим, акция «первые сутки без доната» и отладка
платежей лежат в таблице `config` (`ghtweb_config`, param → value). Экран
«Настройки» пишет туда через `/adm/settingsSave` (белый список ключей) и
пишет изменение в `staff_log` как `settings.save`.
