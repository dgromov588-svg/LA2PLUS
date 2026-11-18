<?php
/** Horizontal navigation for the new player cabinet only. */
class CabinetNavigation
{
    public static function html($page='')
    {
        $e=function($v){return CHtml::encode((string)$v);};$sections=PortalRuntime::sections();$catalog=CabinetSections::catalog();
        $link=function($href,$label,$key='',$active=false)use($e){return '<a href="'.$e($href).'"'.($key?' data-cabinet-section="'.$e($key).'"':'').($active?' aria-current="page"':'').'>'.$e($label).'</a>';};
        $html='<nav class="portal-nav portal-nav--top" aria-label="Разделы личного кабинета">'.$link('/cabinet/','Главная','',in_array($page,['home','index','createAccount'],true));
        foreach(['warehouse','deposit','exchange','calendar','shop','bonuses','referrals','support','messages','history'] as $key)if($sections->enabled($key))$html.=$link($key==='deposit'?'/cabinet/deposit/':'/cabinet/portal/'.$key.'/',PortalRuntime::translate(...$catalog[$key]['label']),$key,$page===$key);
        $html.=$link('/cabinet/portal/security/','Безопасность','',in_array($page,['security','logins'],true));
        if($sections->enabled('rating'))$html.=$link('/rating/','Рейтинг','rating',$page==='rating');
        return $html.'</nav>';
    }
}
