<?php
/** Section-aware navigation for the new player cabinet. */
class CabinetNavigation
{
    public static function html($page='', $mode='top')
    {
        $e=function($v){return CHtml::encode((string)$v);};$sections=PortalRuntime::sections();$catalog=CabinetSections::catalog();
        $link=function($href,$label,$key='',$active=false)use($e){return '<a href="'.$e($href).'"'.($key?' data-cabinet-section="'.$e($key).'"':'').($active?' aria-current="page"':'').'>'.$e($label).'</a>';};
        if ($mode==='sidebar') {
            $title=function($key)use($catalog){return PortalRuntime::translate(...$catalog[$key]['label']);};
            $html='<nav class="portal-nav portal-nav--sidebar" aria-label="Разделы личного кабинета">'.$link('/cabinet/',PortalRuntime::translate('Главная','Home','Головна'),'',in_array($page,['home','index','createAccount'],true));
            foreach (['warehouse','exchange'] as $key) if ($sections->enabled($key)) $html.=$link('/cabinet/portal/'.$key.'/',$title($key),$key,$page===$key);
            $html.=$link('/cabinet/portal/security/',PortalRuntime::translate('Настройки','Settings','Налаштування'),'',in_array($page,['security','logins'],true));
            if ($sections->enabled('rating')) $html.=$link('/rating/',$title('rating'),'rating',$page==='rating');
            if ($sections->enabled('deposit')) $html.=$link('/cabinet/deposit/',$title('deposit'),'deposit',$page==='deposit');
            $secondary=['calendar','shop','bonuses','referrals','support','messages','history'];$more='';
            foreach ($secondary as $key) if ($sections->enabled($key)) $more.=$link('/cabinet/portal/'.$key.'/',$title($key),$key,$page===$key);
            if ($more!=='') $html.='<details class="portal-nav-more"'.(in_array($page,$secondary,true)?' open':'').'><summary>'.CHtml::encode(PortalRuntime::translate('Ещё','More','Ще')).'</summary><div>'.$more.'</div></details>';
            return $html.'</nav>';
        }
        $html='<nav class="portal-nav portal-nav--top" aria-label="Разделы личного кабинета">'.$link('/cabinet/','Главная','',in_array($page,['home','index','createAccount'],true));
        foreach(['warehouse','deposit','exchange','calendar','shop','bonuses','referrals','support','messages','history'] as $key)if($sections->enabled($key))$html.=$link($key==='deposit'?'/cabinet/deposit/':'/cabinet/portal/'.$key.'/',PortalRuntime::translate(...$catalog[$key]['label']),$key,$page===$key);
        $html.=$link('/cabinet/portal/security/','Безопасность','',in_array($page,['security','logins'],true));
        if($sections->enabled('rating'))$html.=$link('/rating/','Рейтинг','rating',$page==='rating');
        return $html.'</nav>';
    }
}
