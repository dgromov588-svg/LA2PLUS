<?php
/** A single navigation source for the new dashboard and legacy cabinet pages. */
class CabinetNavigation
{
    public static function html($page='')
    {
        $e=function ($s) {return CHtml::encode((string)$s);};
        $sections=PortalRuntime::sections();$catalog=CabinetSections::catalog();
        $label=function ($key) use ($catalog) {return PortalRuntime::translate(...$catalog[$key]['label']);};
        $link=function ($href,$text,$key='',$current=false) use ($e) {
            return '<a href="'.$e($href).'"'.($key?' data-cabinet-section="'.$e($key).'"':'').($current?' aria-current="page"':'').'>'.$e($text).'</a>';
        };
        $html='<nav class="portal-nav" aria-label="'.$e(PortalRuntime::translate('Кабинет','Account','Кабінет')).'">';
        $html.='<a class="portal-brand" href="/cabinet/">'.BrandLogo::html('nav').'</a>';
        $html.=$link('/cabinet/',PortalRuntime::translate('Главная','Home','Головна'),'',in_array($page,['home','index'],true));
        foreach (['warehouse'=>'/cabinet/portal/warehouse/','exchange'=>'/cabinet/portal/exchange/'] as $key=>$href) if ($sections->enabled($key)) $html.=$link($href,$label($key),$key,$page===$key);
        $html.=$link('/cabinet/portal/security/',PortalRuntime::translate('Безопасность','Security','Безпека'),'', $page==='security');
        if ($sections->enabled('rating')) $html.=$link('/rating/',$label('rating'),'rating',$page==='rating');
        if ($sections->enabled('deposit')) $html.=$link('/cabinet/deposit/',$label('deposit'),'deposit',$page==='deposit');
        if (RoleService::isSiteOwner()) $html.=$link('/cabinet/portal/admin/',PortalRuntime::translate('Управление кабинетом','Cabinet management','Керування кабінетом'),'', $page==='admin');
        $html.='<details class="portal-nav-more"><summary>'. $e(PortalRuntime::translate('Ещё','More','Ще')).'</summary><div>';
        foreach (['accounts'=>'/cabinet/accounts/','wallet'=>'/cabinet/wallet/','calendar'=>'/cabinet/calendar/','support'=>'/cabinet/tickets/','referrals'=>'/cabinet/referals/','history'=>'/cabinet/transaction-history/','shop'=>'/cabinet/shop/','bonuses'=>'/cabinet/bonuses/bonus-code/','messages'=>'/cabinet/messages/'] as $key=>$href) if ($sections->enabled($key)) $html.=$link($href,$label($key),$key,$page===$key);
        if (RoleService::can('panel.view')) $html.=$link('/admin/',PortalRuntime::translate('Админ-панель','Admin panel','Адмін-панель'));
        $html.=$link('https://forum.la2plus.com/',PortalRuntime::translate('Форум','Forum','Форум'));
        $html.=$link('/',PortalRuntime::translate('На сайт','Website','На сайт'));
        $html.=$link('/cabinet/default/logout/',PortalRuntime::translate('Выйти','Sign out','Вийти'));
        return $html.'</div></details></nav>';
    }
}
