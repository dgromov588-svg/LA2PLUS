<?php
/** Private runtime credentials. No database/sample defaults can enable integrations. */
class PortalProduction
{
    private static $loaded=false;
    public static function bootstrap()
    {
        if(self::$loaded)return;self::$loaded=true;
        if(defined('LA2PLUS_STAGING')&&LA2PLUS_STAGING)return;
        $file=dirname(__DIR__).'/config/portal-live.php';
        if(!is_file($file))return;
        $values=require $file;if(!is_array($values))throw new RuntimeException('Некорректная закрытая конфигурация кабинета.');
        foreach(['LA2PLUS_TRYBIT_API_KEY','LA2PLUS_TRYBIT_SHOP_ID','LA2PLUS_TRYBIT_SECRET_KEY','LA2PLUS_GAME_BRIDGE_SECRET'] as $name){
            if(!getenv($name)&&isset($values[$name])&&is_string($values[$name]))putenv($name.'='.$values[$name]);
        }
    }
    public static function readiness()
    {
        self::bootstrap();$s=PortalRuntime::store();
        return [
            ['name'=>'Режим','ready'=>!PortalRuntime::isDemo(),'detail'=>PortalRuntime::isDemo()?'Изолированный тестовый стенд':'Настоящие аккаунты CMS; демонстрационное начисление запрещено'],
            ['name'=>'Trybit','ready'=>PortalCoinPayments::ready(),'detail'=>PortalTrybit::configured()->ready()?'Реквизиты настроены; приём включается после проверки расчётов и подтверждения платежа':'Требуется закрытая конфигурация Trybit'],
            ['name'=>'Почта','ready'=>(bool)config('mail.smtp')&&(bool)config('mail.smtp_host')&&(bool)config('mail.smtp_login'),'detail'=>config('mail.smtp')?'Используется SMTP проекта; требуется проверка доставки':'Используется локальная отправка почты хостинга. SMTP отдельно не настроен; доставку кодов нужно проверить'],
            ['name'=>'Выдача и биржа предметов','ready'=>$s->setting('bridge_verified')==='1'&&(int)$s->setting('col_item_id')>0&&(bool)getenv('LA2PLUS_GAME_BRIDGE_SECRET'),'detail'=>'Нужны исходники Lucera, тестовый сервер, проверенный ID CoL и приёмка игрового адаптера'],
            ['name'=>'NOWPayments','ready'=>PortalCoinPayments::ready('nowpayments'),'detail'=>'Сохранён существующий криптопровайдер проекта; подтверждение оплаты проверяется через API'],
            ['name'=>'Карты WayForPay','ready'=>PortalCoinPayments::ready('wayforpay'),'detail'=>'Защищённая страница оплаты Visa/Mastercard; доступные кошельки определяются WayForPay'],
            ['name'=>'Остальные способы оплаты','ready'=>false,'detail'=>'PayPalych, PayPal, PayOp, Stripe, СБП и ЕРИП: нужны подтверждённые аккаунты и интеграции'],
        ];
    }
}
