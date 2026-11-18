<?php
/** Owner-managed access to player sections. Independent from payment/game readiness gates. */
class CabinetSections
{
    private $store;
    private $values=[];
    public function __construct(PortalStore $store) { $this->store=$store; }
    public static function catalog()
    {
        return [
            'accounts'=>['label'=>['Аккаунты','Accounts','Акаунти'],'description'=>'Игровые аккаунты, персонажи и телепорт.'],
            'warehouse'=>['label'=>['Склад','Warehouse','Склад'],'description'=>'Покупки, награды и выдача предметов персонажам.'],
            'exchange'=>['label'=>['Биржа','Exchange','Біржа'],'description'=>'Лоты, покупки, кошелёк биржи и заявки вывода.'],
            'deposit'=>['label'=>['Пополнение баланса','Top up','Поповнення балансу'],'description'=>'Страница пополнения и перевод CoL персонажу.'],
            'wallet'=>['label'=>['Кошелёк','Wallet','Гаманець'],'description'=>'Общий кошелёк группы и внутренние переводы.'],
            'shop'=>['label'=>['Магазин','Shop','Магазин'],'description'=>'Каталог товаров и покупки с баланса кабинета.'],
            'calendar'=>['label'=>['Календарь','Calendar','Календар'],'description'=>'Сезонный календарь и получение ежедневных наград.'],
            'bonuses'=>['label'=>['Бонусы и промокоды','Bonuses and codes','Бонуси та промокоди'],'description'=>'Активация промокодов и бонусы проекта.'],
            'referrals'=>['label'=>['Рефералы','Referrals','Реферали'],'description'=>'Приглашения друзей и реферальная программа.'],
            'support'=>['label'=>['Поддержка','Support','Підтримка'],'description'=>'Обращения игроков и переписка с поддержкой.'],
            'history'=>['label'=>['История платежей','Payment history','Історія платежів'],'description'=>'Просмотр истории пополнений кабинета.'],
            'messages'=>['label'=>['Уведомления','Notifications','Сповіщення'],'description'=>'Внутренние сообщения и уведомления игрока.'],
            'rating'=>['label'=>['Рейтинг','Rankings','Рейтинг'],'description'=>'Рейтинги персонажей, кланов и замков.'],
        ];
    }
    private function validate($key)
    {
        if (!array_key_exists($key,self::catalog())) throw new InvalidArgumentException('Этот раздел не управляется переключателем.');
    }
    public function enabled($key)
    {
        $this->validate($key);
        if (!array_key_exists($key,$this->values)) $this->values[$key]=$this->store->setting('section.'.$key.'.enabled','1')!=='0';
        return $this->values[$key];
    }
    public function requireEnabled($key)
    {
        if (!$this->enabled($key)) throw new CHttpException(403,'Раздел временно отключён владельцем проекта.');
    }
    public function set($actor,$key,$enabled)
    {
        $this->validate($key);
        if (!in_array($enabled,['0','1'],true)) throw new InvalidArgumentException('Состояние раздела должно быть 0 или 1.');
        $name='section.'.$key.'.enabled';
        $result=$this->store->atomic(function () use ($actor,$key,$enabled,$name) {
            $this->store->wallet($actor,true);
            $before=$this->store->setting($name,'1');
            if ($before===$enabled) return ['section'=>$key,'enabled'=>$enabled==='1','changed'=>false];
            $this->store->query('DELETE FROM {{portal_settings}} WHERE name=?',[$name]);
            $this->store->query('INSERT INTO {{portal_settings}}(name,value) VALUES(?,?)',[$name,$enabled]);
            $this->store->audit($actor,'cabinet.section.changed',$key,['before'=>$before,'after'=>$enabled]);
            return ['section'=>$key,'enabled'=>$enabled==='1','changed'=>true];
        });
        $this->values[$key]=$enabled==='1';return $result;
    }
    public function forRoute($controller,$action)
    {
        $controller=strtolower(str_replace(['-','_'],'',$controller));$action=strtolower(str_replace(['-','_'],'',$action));
        if ($controller==='portal') return in_array($action,['warehouse','exchange'],true)?$action:null;
        if ($controller==='default') return in_array($action,['calendar','calendarbuy','calendarclaim'],true)?'calendar':null;
        $map=['accounts'=>'accounts','characters'=>'accounts','deposit'=>'deposit','shopcoin'=>'deposit','shoputopay'=>'shop','utopayshop'=>'shop','wayforpayshop'=>'shop','wallet'=>'wallet','shop'=>'shop','bonuses'=>'bonuses','referals'=>'referrals','tickets'=>'support','transactionhistory'=>'history','messages'=>'messages'];
        return $map[$controller]??null;
    }
    public function requireOperation($operation)
    {
        $map=[
            'coinQuote'=>['deposit'],'demoCoinTopup'=>['deposit'],'hide'=>['accounts'],'teleport'=>['accounts'],
            'buy'=>['exchange'],'sell'=>['exchange'],'cancel'=>['exchange'],'withdraw'=>['exchange'],'transfer'=>['exchange'],
            'inventory'=>['exchange'],'import'=>['exchange','warehouse'],'payment'=>['exchange'],'paymentStatus'=>['exchange'],'demoTopup'=>['exchange'],
            'deliver'=>['warehouse'],'coins'=>['warehouse','deposit'],
        ];
        foreach ($map[$operation]??[] as $section) $this->requireEnabled($section);
    }
}
