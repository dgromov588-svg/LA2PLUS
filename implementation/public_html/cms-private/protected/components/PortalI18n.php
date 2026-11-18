<?php
/** Translate static UI phrases only; user names, monetary values and item snapshots are not rewritten. */
class PortalI18n
{
    public static function text($text,$language)
    {
        if (!in_array($language,['en','uk','ua'],true)) return $text;
        $words=[
            'Админ-панель'=>['Admin panel','Адмін-панель'], 'Управление биржей и скидками'=>['Exchange and discount management','Керування біржею та знижками'], 'Выйти'=>['Sign out','Вийти'],
            'Аккаунты'=>['Accounts','Акаунти'],'Кошелёк'=>['Wallet','Гаманець'],'Календарь'=>['Calendar','Календар'],'Поддержка'=>['Support','Підтримка'],'Рефералы'=>['Referrals','Реферали'],'История платежей'=>['Payment history','Історія платежів'],'Магазин'=>['Shop','Магазин'],'На сайт'=>['Website','На сайт'],'Форум'=>['Forum','Форум'],
            'Перевести CoL:'=>['Transfer CoL:','Переказати CoL:'],'Баланс кабинета:'=>['Account balance:','Баланс кабінету:'],
            'CoL. Перевод выполняется только после вашего подтверждения.'=>['CoL. Transfers require your confirmation.','CoL. Переказ виконується лише після вашого підтвердження.'],
            'Перевод в игру временно недоступен. Монеты остаются на вашем балансе кабинета.'=>['Game transfers are temporarily unavailable. Your coins remain in your account.','Переказ у гру тимчасово недоступний. Монети залишаються на балансі кабінету.'],
            'Нет привязанных игровых аккаунтов. Создайте или привяжите аккаунт.'=>['No linked game accounts. Create or link an account.','Немає прив’язаних ігрових акаунтів. Створіть або прив’яжіть акаунт.'],
            'Привязать аккаунт'=>['Link account','Прив’язати акаунт'],'скрыт'=>['hidden','приховано'],
            'Показать скрытые аккаунты'=>['Show hidden accounts','Показати приховані акаунти'],'Скрыть скрытые аккаунты'=>['Hide hidden accounts','Не показувати приховані акаунти'],
            'Не удалось загрузить персонажей. Попробуйте обновить страницу позже.'=>['Characters could not be loaded. Please try again later.','Не вдалося завантажити персонажів. Оновіть сторінку пізніше.'],
            'Персонаж'=>['Character','Персонаж'],'Уровень'=>['Level','Рівень'],'Статус'=>['Status','Статус'],'Действия'=>['Actions','Дії'],'В город'=>['To town','У місто'],'Пароль аккаунта'=>['Account password','Пароль акаунта'],'Купить CoL'=>['Buy CoL','Купити CoL'],'Онлайн'=>['Online','Онлайн'],'Офлайн'=>['Offline','Офлайн'],
            'Персонажей пока нет'=>['No characters yet','Персонажів поки немає'],'Сменить / восстановить пароль'=>['Change / recover password','Змінити / відновити пароль'],'Скрыть аккаунт'=>['Hide account','Приховати акаунт'],'Показать аккаунт'=>['Show account','Показати акаунт'],'↻ Обновить список персонажей'=>['↻ Refresh characters','↻ Оновити список персонажів'],
            'Пригласить друга'=>['Invite a friend','Запросити друга'],'Мои рефералы'=>['My referrals','Мої реферали'],'Реферальная программа пока недоступна.'=>['The referral program is currently unavailable.','Реферальна програма поки недоступна.'],
            'Приглашайте друзей по вашей ссылке и получайте награды по правилам реферальной программы LA2PLUS.'=>['Invite friends using your link and earn rewards under the LA2PLUS referral program.','Запрошуйте друзів за своїм посиланням та отримуйте нагороди за правилами реферальної програми LA2PLUS.'],
            'Бонус-код'=>['Bonus code','Бонус-код'],'Активируйте промокод проекта.'=>['Activate a project promo code.','Активуйте промокод проєкту.'],'Ввести код'=>['Enter code','Ввести код'],'Календарь и поддержка'=>['Calendar and support','Календар та підтримка'],'Календарь наград'=>['Reward calendar','Календар нагород'],'Обратиться в поддержку'=>['Contact support','Звернутися до підтримки'],'История пополнений'=>['Top-up history','Історія поповнень'],
            'Настройки безопасности'=>['Security settings','Налаштування безпеки'],'Подтверждение почты'=>['Email verification','Підтвердження пошти'],'Текущий пароль'=>['Current password','Поточний пароль'],'Получить код'=>['Get code','Отримати код'],'Код из письма'=>['Email code','Код із листа'],'Подтвердить почту'=>['Verify email','Підтвердити пошту'],
            'Код действует 10 минут. Повторная отправка — через минуту, до 5 писем в час.'=>['Codes expire after 10 minutes. Resend after one minute, up to 5 emails per hour.','Код діє 10 хвилин. Повторна відправка через хвилину, до 5 листів на годину.'],
            'Смена и восстановление пароля игрового аккаунта'=>['Change or recover a game account password','Зміна та відновлення пароля ігрового акаунта'],'Текущий пароль профиля'=>['Current profile password','Поточний пароль профілю'],'Новый пароль (6–16 символов)'=>['New password (6–16 characters)','Новий пароль (6–16 символів)'],'Повтор пароля'=>['Repeat password','Повторіть пароль'],'Сохранить пароль'=>['Save password','Зберегти пароль'],
            'Склад покупок и наград'=>['Purchases and rewards warehouse','Склад покупок і нагород'],'Выберите предмет, свой игровой аккаунт и персонажа. История доставки сохраняется.'=>['Select an item, your game account and a character. Delivery history is retained.','Виберіть предмет, свій ігровий акаунт і персонажа. Історія доставки зберігається.'],'На складе пока нет предметов.'=>['Your warehouse is empty.','На складі поки немає предметів.'],'Аккаунт и персонаж'=>['Account and character','Акаунт і персонаж'],'Выберите персонажа'=>['Select character','Виберіть персонажа'],'Получить в игре'=>['Deliver to game','Отримати у грі'],
            'Биржа LA2PLUS'=>['LA2PLUS Exchange','Біржа LA2PLUS'],'В продаже'=>['For sale','У продажу'],'Продать'=>['Sell','Продати'],'Мои лоты'=>['My listings','Мої лоти'],'Мои покупки'=>['My purchases','Мої покупки'],'Кошелёк биржи'=>['Exchange wallet','Гаманець біржі'],'История'=>['History','Історія'],
            'Торговля пока недоступна. Ваши средства и предметы сохранены.'=>['Trading is currently unavailable. Your funds and items are safe.','Торгівля поки недоступна. Ваші кошти та предмети збережено.'],
            'Пополнение кошелька биржи'=>['Top up exchange wallet','Поповнення гаманця біржі'],'Криптовалюта'=>['Cryptocurrency','Криптовалюта'],'Выбрано'=>['Selected','Обрано'],'Скоро'=>['Coming soon','Незабаром'],'СБП'=>['SBP','СБП'],'Карты Украины'=>['Ukrainian cards','Картки України'],'Международные карты · Stripe'=>['International cards · Stripe','Міжнародні картки · Stripe'],
            'USDT, BTC, ETH и другие · комиссия биржи 0%'=>['USDT, BTC, ETH and more · exchange fee 0%','USDT, BTC, ETH та інші · комісія біржі 0%'],'Пополнение биржи пока недоступно.'=>['Exchange top-ups are currently unavailable.','Поповнення біржі поки недоступне.'],
            'Сумма, $ (5–1000)'=>['Amount, $ (5–1000)','Сума, $ (5–1000)'],'Перейти к оплате'=>['Proceed to payment','Перейти до оплати'],'Перевод на баланс кабинета'=>['Transfer to account balance','Переказ на баланс кабінету'],
            'По базовому курсу LA2PLUS, без бонусов и скидок. Обратный перевод не предусмотрен.'=>['At the LA2PLUS base rate, without bonuses or discounts. Reverse transfers are not available.','За базовим курсом LA2PLUS, без бонусів і знижок. Зворотний переказ не передбачено.'],
            'Списать с биржи, $'=>['Debit from exchange, $','Списати з біржі, $'],'Перевести в кабинет'=>['Transfer to account','Переказати в кабінет'],'Вывод USDT TRC20'=>['Withdraw USDT TRC20','Виведення USDT TRC20'],'Списать с кошелька, $'=>['Debit from wallet, $','Списати з гаманця, $'],'Создать заявку'=>['Create request','Створити заявку'],
            'Минимум 10 $. Комиссия: 3 $ + 25% от суммы. Одна активная заявка, обработка администрацией вручную.'=>['Minimum $10. Fee: $3 + 25% of the amount. One active request, processed manually by administration.','Мінімум 10 $. Комісія: 3 $ + 25% від суми. Одна активна заявка, ручна обробка адміністрацією.'],
            'Пополнения биржи'=>['Exchange top-ups','Поповнення біржі'],'Проверить оплату'=>['Check payment','Перевірити оплату'],'Выставить предмет со склада'=>['List a warehouse item','Виставити предмет зі складу'],'Предмет'=>['Item','Предмет'],'Выберите предмет'=>['Select item','Виберіть предмет'],'Цена лота, $'=>['Listing price, $','Ціна лота, $'],'Выставить'=>['List for sale','Виставити'],'Открыть склад'=>['Open warehouse','Відкрити склад'],
            'Комиссия продажи — 10% с продавца. Предмет резервируется до продажи или отмены лота.'=>['The seller pays a 10% sale fee. The item remains reserved until sold or cancelled.','Комісія продажу — 10% із продавця. Предмет резервується до продажу або скасування лота.'],
            'Перенести предмет из игры'=>['Import an item from game','Перенести предмет із гри'],'Загрузить предметы персонажа'=>['Load character inventory','Завантажити предмети персонажа'],'Предмет из инвентаря'=>['Inventory item','Предмет з інвентарю'],'Сначала загрузите инвентарь'=>['Load inventory first','Спочатку завантажте інвентар'],'Перенести на склад'=>['Move to warehouse','Перенести на склад'],
            'Операции кошелька'=>['Wallet transactions','Операції гаманця'],'Дата'=>['Date','Дата'],'Операция'=>['Operation','Операція'],'Изменение, $'=>['Change, $','Зміна, $'],'Резерв, $'=>['Reserved, $','Резерв, $'],'Заявки на вывод'=>['Withdrawal requests','Заявки на виведення'],
            'Все категории'=>['All categories','Усі категорії'],'Оружие'=>['Weapons','Зброя'],'Броня'=>['Armor','Броня'],'Бижутерия'=>['Jewelry','Біжутерія'],'Все грейды'=>['All grades','Усі грейди'],'Любая аугментация'=>['Any augmentation','Будь-яка аугментація'],'С аугментацией'=>['Augmented','З аугментацією'],'Без аугментации'=>['Not augmented','Без аугментації'],'Показать'=>['Show','Показати'],'Купить'=>['Buy','Купити'],'Снять с продажи'=>['Cancel listing','Зняти з продажу'],'Лотов по выбранным условиям нет.'=>['No listings match these filters.','За обраними умовами лотів немає.'],
            'Персональная скидка'=>['Personal discount','Персональна знижка'],'ID профиля'=>['Profile ID','ID профілю'],'Процент'=>['Percentage','Відсоток'],'Действует до (UTC)'=>['Valid until (UTC)','Діє до (UTC)'],'Назначить скидку'=>['Assign discount','Призначити знижку'],'Выводы USDT TRC20'=>['USDT TRC20 withdrawals','Виведення USDT TRC20'],'TXID'=>['TXID','TXID'],'Взять в обработку'=>['Start processing','Взяти в обробку'],'Отметить выплаченной'=>['Mark as paid','Позначити виплаченою'],'Отклонить и вернуть резерв'=>['Reject and release funds','Відхилити та повернути резерв'],
            'Телепорт в город'=>['Teleport to town','Телепорт у місто'],'Количество CoL'=>['CoL amount','Кількість CoL'],'Перевести в игру'=>['Transfer to game','Переказати у гру'],
            'Операция выполнена.'=>['Operation completed.','Операцію виконано.'],'Сохраняем…'=>['Saving…','Зберігаємо…'],'Закрыть'=>['Close','Закрити'],'Заточка от'=>['Enchant from','Заточка від'],'до'=>['to','до'],
            'Комиссия 15%'=>['Fee 15%','Комісія 15%'],'Комиссия 25%'=>['Fee 25%','Комісія 25%'],'Комиссия 30%'=>['Fee 30%','Комісія 30%'],
            'USDT-адрес TRON (TRC20)'=>['USDT address on TRON (TRC20)','USDT-адреса TRON (TRC20)'],
            'Комиссию сети и Trybit вы платите сверх суммы. На кошелёк поступит указанная сумма.'=>['Network and Trybit fees are paid on top. Your wallet receives the specified amount.','Комісії мережі та Trybit сплачуються понад суму. На гаманець надійде вказана сума.'],
            'Выйдите из игры. Доступные предметы резервируются игровым сервером с сохранением заточки и аугментации.'=>['Log out of the game. Eligible items are reserved by the game server with enchantment and augmentation preserved.','Вийдіть із гри. Доступні предмети резервуються ігровим сервером зі збереженням заточки та аугментації.'],
            'Скидка действует только на услуги и магазин, не суммируется со скидкой товара. Время окончания — UTC.'=>['Discounts apply only to services and the shop and do not stack with product discounts. Expiry time is UTC.','Знижка діє лише на послуги й магазин та не додається до знижки товару. Час завершення — UTC.'],
            'Выплата выполняется вручную. Сначала возьмите заявку в обработку; отметку «Выплачено» сохраняйте только после перевода, с TXID.'=>['Payouts are manual. Start processing first; mark as paid only after sending funds and checking the TXID.','Виплата виконується вручну. Спочатку візьміть заявку в обробку; позначайте виплаченою лише після переказу та перевірки TXID.'],
        ];
        if (preg_match('/^Курс: ([0-9.,]+) \$ за 1 CoL\.$/u',$text,$m)) return $language==='en'?'Rate: '.$m[1].' $ per 1 CoL.':'Курс: '.$m[1].' $ за 1 CoL.';
        return $words[$text][$language==='en'?0:1]??$text;
    }
    public static function html($html,$language)
    {
        if ($language==='ru') return $html;
        $literals=[];
        $html=preg_replace_callback('#<span data-portal-literal>.*?</span>#su',function ($m) use (&$literals) {
            $key='PORTAL_LITERAL_'.count($literals).'_END'; $literals[$key]=$m[0]; return $key;
        },$html);
        $html=preg_replace_callback('/>([^<>]+)</u',function ($m) use ($language) {
            $trim=trim($m[1]); $translated=self::text(html_entity_decode($trim,ENT_QUOTES,'UTF-8'),$language);
            if ($translated===html_entity_decode($trim,ENT_QUOTES,'UTF-8')) return $m[0];
            return '>'.str_replace($trim,htmlspecialchars($translated,ENT_QUOTES,'UTF-8'),$m[1]).'<';
        },$html);
        $html=preg_replace_callback('/(placeholder|aria-label|title|data-confirm)="([^"]*)"/u',function ($m) use ($language) {
            return $m[1].'="'.htmlspecialchars(self::text(html_entity_decode($m[2],ENT_QUOTES,'UTF-8'),$language),ENT_QUOTES,'UTF-8').'"';
        },$html);
        return strtr($html,$literals);
    }
}
