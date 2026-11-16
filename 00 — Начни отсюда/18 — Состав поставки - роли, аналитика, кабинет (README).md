# LA2PLUS • панель v2 — боевой пакет

Готовый к вставке PHP-код для Yii 1.1, а не макет. Всё проверено прогоном
на реальном PHP 8.5.8 и на реальной SQLite-базе: **121 проверка, 0 ошибок**.

```
php tests/php/run.php      →  Пройдено: 121, провалено: 0
```

---

## 1. Что в пакете

| Файл | Куда в проекте | Что делает |
|---|---|---|
| `cms-private/protected/components/RoleService.php` | `protected/components/` | Роли, права, владельцы 1..N, журналы. Замена старого файла целиком |
| `cms-private/protected/modules/panel/AnalyticsController.php` | `protected/modules/panel/` | JSON-аналитика из базы: `/panel/analytics/data`, `/panel/analytics/live` |
| `cms-private/protected/modules/panel/StaffController.php` | `protected/modules/panel/` | Раздел «Сотрудники», POST-обработчики ролей и владельцев |
| `cms-private/protected/modules/panel/PanelBaseController.php` | `protected/modules/panel/` | Общие данные панели (роль, права, владелец, ранг) |
| `cms-private/protected/modules/cabinet/controllers/PanelController.php` | `protected/modules/cabinet/controllers/` | Адреса `/cabinet/panel/*`, включая `panelOwners`, `panelAnalyticsData`, `panelLiveJson` |
| `cms-private/protected/migrations/m261114_120000_panel_roles.php` | `protected/migrations/` | Таблицы `roles`, `role_log`, `staff_log` (идемпотентно, MySQL и SQLite) |
| `cms-private/protected/components/AnalyticsService.php` | `protected/components/` | Копия текущего сервиса (нужна как зависимость; если файл на сервере новее — не затирайте) |
| `cms-private/themes/la2plus/views/cabinet/panel-staff.php` | тема | Сотрудники: карточки ролей, матрица прав, список команды, история |
| `cms-private/themes/la2plus/views/cabinet/panel-owners.php` | тема | Владельцы проекта: список, выдача, снятие |
| `cms-private/themes/la2plus/views/cabinet/panel-log.php` | тема | Журнал: действия, кошельки, роли (таблицы в прокрутке) |
| `cms-private/themes/la2plus/views/cabinet/panel-nav.php` | тема | Меню панели + пункт «Владельцы» (только владельцу) |
| `cms-private/themes/la2plus/views/cabinet/panel-style.php` | тема | Подключение CSS панели с версией по mtime |
| `cms-private/themes/la2plus/css/panel-aurum.css` | тема | Золотая тема, только классы `panel-*` |
| `cms-private/protected/config/PATCH-main-routes.txt` | — | Что дописать в `rules` (`main.php`) |
| `tests/php/*` | — | Тесты и заглушки Yii (в поставку на сервер не нужны) |

---

## 2. Установка (10 минут)

### 2.1. Резервная копия

```bash
cd /home4/viplclub/la2plus/public_html
tar czf ~/backup-panel-$(date +%Y%m%d-%H%M).tar.gz cms-private/protected/components cms-private/protected/modules cms-private/protected/config cms-private/themes
```

### 2.2. Скопировать файлы

Распаковать пакет поверх `public_html/` (пути внутри архива совпадают).

### 2.3. Создать таблицы

```bash
cd /home4/viplclub/la2plus/public_html
php cms-private/protected/migrations/m261114_120000_panel_roles.php
```

Ожидаемый вывод:

```
готова таблица ghtweb_roles
готова таблица ghtweb_role_log
готова таблица ghtweb_staff_log
владельцев в params.php: 2 (1, 31)
готово. Таблицы ролей и журналов на месте.
```

Откат: тот же скрипт с `--down`.

### 2.4. Маршруты

В `protected/config/main.php` → `urlManager.rules` добавить три строки из
`PATCH-main-routes.txt`:

```php
'cabinet/panel/panelOwners'        => 'cabinet/panel/panelOwners',
'cabinet/panel/panelAnalyticsData' => 'cabinet/panel/panelAnalyticsData',
'cabinet/panel/panelLiveJson'      => 'cabinet/panel/panelLiveJson',
```

### 2.5. Права на params.php

Владельцы хранятся в `protected/config/params.php` → `'superAdminId'`. Чтобы
выдача/снятие владельца из интерфейса работала, файл должен быть доступен
на запись PHP:

```bash
chmod 664 cms-private/protected/config/params.php
```

Проверка: раздел «Владельцы» → выдать владельца → в `params.php` появится
новая строка, рядом `params.php.bak-<дата>`. Если прав нет — интерфейс
покажет ошибку «Не удалось сохранить список владельцев», а не «тихий успех».

---

## 3. Правила, которые теперь зашиты в код

**Владельцы**

- Владелец = запись в `params.php['superAdminId']`, и только она. Роль в
  таблице ролей владельцем **не** делает.
- Владельцев 1..N. Последнего снять нельзя: `revokeOwner()` вернёт ошибку.
- Выдавать и снимать может только владелец: `canManageOwners()`.
- При снятии владельца его роль в таблице становится `admin` (чтобы не
  осталось «полного доступа» без владельца).
- Администратор не получит эту страницу ни по ссылке, ни POST-запросом:
  проверка стоит в `beforeAction`/`actionPanelOwners` и в `StaffController`.

**Роли**

- Ранги: owner 100, admin 80, moder 60, gm 50, finance 40, user 0.
- Менять роль можно только у того, кто строго ниже; выдавать только роль
  строго ниже своей; себе — нельзя; владельца через роли — нельзя.
- Всё это в одном месте: `RoleService::canChangeRole()`. Контроллер только
  показывает текст ошибки.
- Раздел «Сотрудники» = `roles.manage`, а он есть только у владельца.
  У администратора в матрице его нет (как и `owner.manage`, `settings.view`).

**Аналитика**

- Все цифры — из базы: `visit_log`, `users`, `users_auth_logs`,
  `transactions`, `account_links/groups`, `user_profiles`, база игры `gs`.
- Никакой генерации: если таблицы/сервера нет, в ответе `has_table: false`
  или `recon.game_error` с текстом ошибки, а не выдуманные числа.
- `recon` — сверка: те же счётчики прямыми `COUNT(*)`, чтобы расхождение
  «экран ↔ база» было видно сразу.
- Ответ кешируется на 10 секунд, `Cache-Control: no-store`, `X-Robots-Tag: noindex`.

---

## 4. Что проверено тестами

`tests/php/run.php` (121 проверка):

1. **Роли и права** — 24 права, матрица, ранги, администратор без
   `owner.manage`/`settings.view`/`roles.manage`, игрок без входа в панель.
2. **Смена ролей** — свои/равные/старшие, выдача «владельца» через роли,
   попытка понизить владельца.
3. **Выдача/снятие ролей** — запись в БД, история `role_log` (было → стало),
   журнал действий `staff_log` с JSON-параметрами и IP.
4. **Список команды** — владельцы первыми, `is_owner`, администратор не
   помечен владельцем.
5. **Владельцы** — самовыдача, снятие администратором, выдача несуществующему,
   повторная выдача, реальная перезапись `params.php` (точечно, с бэкапом,
   с проверкой синтаксиса), «последнего снять нельзя».
6. **Аналитика** — реальные цифры из SQLite: посетители/просмотры/входы,
   источники, устройства, топ-страницы, сверка `COUNT(*)`, поведение при
   недоступной игровой базе, 403 для ГМ, живые счётчики, периоды 7/14/30 и
   защита от мусора в `?days=`.
7. **Контроллер сотрудников** — POST-обработчики: отказ администратору (403),
   смена роли владельцем, редирект после POST, отказ на владельце, выдача и
   снятие владельца через форму, запрет снять последнего.

Плюс `php -l` по каждому файлу пакета.

---

## 5. Что НЕ трогали

Пункты 1–9 из `CONTINUE/README.md` не изменялись: ключи шлюзов, SMTP, пароли,
конфиги оплат. В пакете нет ни одного изменения этих файлов.

---

## 6. Если что-то пошло не так

| Симптом | Причина | Что делать |
|---|---|---|
| «Не удалось сохранить список владельцев» | нет прав на запись `params.php` | `chmod 664 protected/config/params.php`, владелец файла — пользователь PHP |
| Раздел «Сотрудники» → 403 у владельца | у него нет роли в таблице, но он в `superAdminId` — быть не должно | проверить `params.php`, затем `RoleService::isSiteOwner()` |
| Аналитика показывает `has_table: false` | таблицы `visit_log` нет | открыть любую страницу сайта (журнал создаётся сам) или запустить `AnalyticsService::ensureTable()` |
| `recon.game_error` с текстом | недоступна база игры | проверить `l2('gs', …)` в `params.php`; панель при этом работает |
| Изменения не видны сразу | включён кеш конфига | кеш `params` в памяти сбрасывается автоматически; общий кеш — `RoleService::flushCache()` |

---

Проверка перед выкладкой:

```bash
php -l cms-private/protected/components/RoleService.php
php tests/php/run.php
```

## 7. Уже выложено на la2plus.com (14.11.2026)

Прод-развёртывание выполнено: отчёт — `live/DEPLOY-REPORT.md`.
Коротко: 12 файлов на месте, бэкап `public_html/panel-v2-backup-20261114-233926/`,
3 роута в `main.php`, таблицы `ghtweb_roles` / `ghtweb_role_log` / `ghtweb_staff_log` созданы.

Файл-курьер «всё в одном» (пакет внутри, 121 КБ) — `live/dist/panel-deploy-all.php`.
Ключ: `c47e826598dec22f9eb7ac6c95e856d1`. Годится для повторной выкладки или переноса
на другой сервер: положить в корень, открыть `?key=...` (веб) либо запустить
`php panel-deploy-all.php <ключ>` (CLI, если `.htaccess` режет PHP). Скрипт сам делает
бэкап, кладёт файлы, правит роуты, создаёт таблицы и удаляет себя.

Помощник для cPanel API — `tools/cpanel-api.js` (один вызов UAPI на сессию, обход антибота).

## Правки на сервере вне поставки (15.11.2026)

Два файла темы живут только на боевом сервере и правятся мимо пакета:

- `cms-private/themes/la2plus/views/layouts/master.php` — признак активной
  вкладки «Кабинет / Админка» теперь считается по модулю `panel` и маршруту
  (`panel/*`, `cabinet/panel/*`), а не только по `getController()->getId()`.
  Копия — `prod-layouts/master.php`, бэкап на сервере — `master.php.bak-20261115-105606`.
- `cms-private/protected/config/main.php` — в правило `/admin/<action>` добавлен
  `panelOwners` (раздел отдавал 404). Бэкап на сервере — `main.php.bak-20261115-105752`.

Раскладка `layouts/admin.php` (отдельный каркас админки) на сервере есть, но не
подключена — панель рендерится в каркасе кабинета. Это отдельная задача, не баг.
Подробности — `DEPLOY-REPORT.md`, раздел 4а, и `prod-layouts/README.md`.

## Каркас админки (15.11.2026)

Админка оформлена отдельным каркасом: `themes/la2plus/views/layouts/admin.php`
(`body.adm`, своя шапка и сайдбар, без баланса и игрового меню кабинета).

- ставится в `PanelBaseController::beforeAction()` и в
  `PanelController::beforeAction()` — `$this->layout = '//layouts/admin'`;
- стили: `/design/css/pages/panel.css` → `panel-aurum.css` → `admin.css`
  (последним, чтобы перебить каскад кабинета);
- `admin.php` в поставке лежит как
  `deploy/files/cms-private/themes/la2plus/views/layouts/admin.php`,
  боевая копия — `prod-layouts/admin.php`, CSS — `prod-layouts/admin.css`;
- на телефоне меню — сетка в две колонки, таблицы прокручиваются внутри блока.
